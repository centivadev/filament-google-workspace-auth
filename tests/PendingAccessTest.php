<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Pages\PendingAccess;
use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\FilamentUserResource;
use CentivaDev\FilamentGoogleWorkspaceAuth\FilamentGoogleWorkspaceAuthPlugin;
use CentivaDev\FilamentGoogleWorkspaceAuth\Http\Middleware\EnsureAccessIsGranted;
use CentivaDev\FilamentGoogleWorkspaceAuth\Support\AccessLock;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\EnablesPendingAccess;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\FilamentUser;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\LegacyFilamentUser;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\MarkerPage;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Auth\GenericUser as NonEloquentUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as GenericUser;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Filament\get_authorization_response;

uses(EnablesPendingAccess::class);

beforeEach(function () {
    Config::set('app.env', 'local');

    MarkerPage::$calls = [];
});

function createGuest(): FilamentUser
{
    $user = createFilamentUser();
    $user->assignRole(Role::findOrCreate('guest', 'filament'));

    return $user;
}

function createEditor(): FilamentUser
{
    $user = createFilamentUser();
    $user->assignRole(Role::findOrCreate('editor', 'filament'));

    return $user;
}

function pendingAccessUrl(): string
{
    return PendingAccess::getUrl(panel: 'admin');
}

function withoutEnv(string $name, Closure $callback): mixed
{
    $server = $_SERVER[$name] ?? null;
    $env = $_ENV[$name] ?? null;
    $putenv = getenv($name);

    unset($_SERVER[$name], $_ENV[$name]);
    putenv($name);

    try {
        return $callback();
    } finally {
        if ($server !== null) {
            $_SERVER[$name] = $server;
        }

        if ($env !== null) {
            $_ENV[$name] = $env;
        }

        if ($putenv !== false) {
            putenv("{$name}={$putenv}");
        }
    }
}

it('enables the lock by default', function () {
    $shipped = withoutEnv('FILAMENT_GOOGLE_PENDING_ACCESS', fn () => require __DIR__ . '/../config/filament-google-workspace-auth.php');

    expect($shipped['pending_access']['enabled'])->toBeTrue();

    Config::set('filament-google-workspace-auth.pending_access', ['roles' => ['guest']]);

    expect(AccessLock::isEnabled())->toBeTrue();

    Config::set('filament-google-workspace-auth', Arr::except(config('filament-google-workspace-auth'), 'pending_access'));

    expect(AccessLock::isEnabled())->toBeTrue();
});

it('keeps the guest role pending when the pending roles are null', function () {
    Config::set('filament-google-workspace-auth.pending_access.roles', null);

    getAs($this, createGuest(), MarkerPage::getUrl(panel: 'admin'))->assertRedirect(pendingAccessUrl());
});

it('refuses a locked account with a 403 when the panel has no pending page route', function () {
    Route::middleware(['web', EnsureAccessIsGranted::class])->get('/app-route', fn () => 'app');
    Filament::setCurrentPanel(Panel::make()->id('bare'));

    getAs($this, createGuest(), '/app-route')->assertForbidden();
});

it('locks a guest-only account on every panel page', function (string $url) {
    getAs($this, createGuest(), $url)->assertRedirect(pendingAccessUrl());
})->with([
    'home' => fn () => Filament::getPanel('admin')->getUrl(),
    'resource' => fn () => FilamentUserResource::getUrl('index', panel: 'admin'),
    'custom page' => fn () => MarkerPage::getUrl(panel: 'admin'),
]);

it('locks an account without any role', function () {
    getAs($this, createFilamentUser(), MarkerPage::getUrl(panel: 'admin'))->assertRedirect(pendingAccessUrl());
});

it('locks an account whose only other role belongs to another guard', function () {
    $user = createGuest();
    $user->roles()->attach(Role::findOrCreate('editor', 'web'));

    getAs($this, $user, MarkerPage::getUrl(panel: 'admin'))->assertRedirect(pendingAccessUrl());
});

it('locks an account whose only direct permission belongs to another guard', function () {
    $user = createGuest();
    $user->permissions()->attach(Permission::findOrCreate('filament.users.view_any', 'web'));

    getAs($this, $user, MarkerPage::getUrl(panel: 'admin'))->assertRedirect(pendingAccessUrl());
});

it('locks every role listed as pending', function () {
    Config::set('filament-google-workspace-auth.pending_access.roles', ['guest', 'pending']);

    $user = createFilamentUser();
    $user->assignRole(Role::findOrCreate('pending', 'filament'));

    getAs($this, $user, MarkerPage::getUrl(panel: 'admin'))->assertRedirect(pendingAccessUrl());
});

it('locks a panel account even when user_model names another class', function () {
    Config::set('filament-google-workspace-auth.user_model', LegacyFilamentUser::class);

    getAs($this, createGuest(), MarkerPage::getUrl(panel: 'admin'))->assertRedirect(pendingAccessUrl());
});

it('fails closed for a user model without the spatie relations', function () {
    Config::set('filament-google-workspace-auth.user_model', GenericUser::class);
    Gate::define('anything', fn () => true);

    expect(Gate::forUser(new GenericUser)->allows('anything'))->toBeFalse();
});

it('lets an account through once it holds a granting role or a direct permission', function (FilamentUser $user) {
    getAs($this, $user, MarkerPage::getUrl(panel: 'admin'))->assertOk()->assertSee('marker');
})->with([
    'another role' => fn () => createEditor(),
    'guest and another role' => fn () => createGuest()->assignRole(Role::findOrCreate('editor', 'filament')),
    'super-admin' => fn () => createSuperAdmin(),
    'a direct permission' => fn () => createGuest()->givePermissionTo(Permission::findOrCreate('filament.users.view_any', 'filament')),
]);

it('sends an account that has access away from the pending page', function () {
    getAs($this, createEditor(), pendingAccessUrl())->assertRedirect(Filament::getPanel('admin')->getUrl());
});

it('logs a banned guest out before locking it', function () {
    $user = createGuest();
    $user->forceFill(['banned_at' => now()])->save();

    getAs($this, $user, MarkerPage::getUrl(panel: 'admin'))->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
});

it('renders the pending page with the support email and a logout button only', function () {
    getAs($this, createGuest(), pendingAccessUrl())
        ->assertOk()
        ->assertSee('Access not configured')
        ->assertSee('Your Google account was recognized, but no access has been granted to it yet.')
        ->assertSee('mailto:support@example.com', escape: false)
        ->assertSee('action="' . Filament::getPanel('admin')->getLogoutUrl() . '"', escape: false)
        ->assertSee('fi-ac fi-width-full', escape: false)
        ->assertDontSee('fi-sidebar', escape: false)
        ->assertDontSee('fi-user-menu', escape: false);
});

it('hides the support line when no support email is configured', function () {
    Config::set('filament-google-workspace-auth.pending_access.support_email', null);

    getAs($this, createGuest(), pendingAccessUrl())
        ->assertOk()
        ->assertDontSee('mailto:', escape: false)
        ->assertDontSee('Contact the technical team');
});

it('lets a locked account log out', function () {
    $user = createGuest();

    $this->withSession([Auth::guard('filament')->getName() => $user->getKey()])
        ->post(Filament::getPanel('admin')->getLogoutUrl())
        ->assertRedirect();

    expect(Auth::guard('filament')->check())->toBeFalse();
});

it('refuses a livewire/update call once the account is locked, without running it', function () {
    $user = createEditor();
    $snapshot = markerPageSnapshot($this, $user);

    callMark($this, $snapshot)->assertOk();
    expect(MarkerPage::$calls)->toBe(['mark']);

    $user->removeRole('editor');

    callMark($this, $snapshot)->assertRedirect(pendingAccessUrl());
    expect(MarkerPage::$calls)->toBe(['mark']);
});

it('applies a role change on the next request', function () {
    $user = createGuest();
    $url = MarkerPage::getUrl(panel: 'admin');

    getAs($this, $user, $url)->assertRedirect(pendingAccessUrl());

    $user->assignRole(Role::findOrCreate('editor', 'filament'));
    getAs($this, $user, $url)->assertOk();

    $user->removeRole('editor');
    getAs($this, $user, $url)->assertRedirect(pendingAccessUrl());
});

it('denies every gate check to a locked account, whatever the app grants', function () {
    $guest = createGuest();
    $editor = createEditor();

    Role::findByName('guest', 'filament')->givePermissionTo(Permission::findOrCreate('filament.users.view_any', 'filament'));
    Gate::define('anything', fn () => true);
    Gate::before(fn () => true);

    expect(Gate::forUser($guest->fresh())->allows('anything'))->toBeFalse()
        ->and(Gate::forUser($guest->fresh())->allows('filament.users.view_any'))->toBeFalse()
        ->and(Gate::forUser($editor)->allows('anything'))->toBeTrue();
});

it('denies a filament resource that has no policy to a locked account', function () {
    Filament::setCurrentPanel('admin');

    Auth::guard('filament')->setUser(createGuest());
    expect(get_authorization_response('viewAny', DatabaseNotification::class)->allowed())->toBeFalse();

    Auth::guard('filament')->setUser(createEditor());
    expect(get_authorization_response('viewAny', DatabaseNotification::class)->allowed())->toBeTrue();
});

it('leaves the gate untouched for other user models', function () {
    Gate::define('anything', fn () => true);

    expect(Gate::forUser(new GenericUser)->allows('anything'))->toBeTrue();
});

it('locks an app route that uses the middleware', function () {
    Route::middleware(['web', EnsureAccessIsGranted::class])->get('/app-route', fn () => 'app');

    getAs($this, createGuest(), '/app-route')->assertRedirect(pendingAccessUrl());
    getAs($this, createEditor(), '/app-route')->assertOk()->assertSee('app');
});

it('keeps the pending page free of email verification and multi-factor redirects', function () {
    $panel = Panel::make()
        ->id('strict')
        ->emailVerification()
        ->multiFactorAuthentication([EmailAuthentication::make()], isRequired: true);

    expect(MarkerPage::getRouteMiddleware($panel))->toHaveCount(2)
        ->and(PendingAccess::getRouteMiddleware($panel))->toBe([]);
});

it('refuses a panel with tenancy', function () {
    $panel = Panel::make()->id('tenant')->tenant(DatabaseNotification::class);

    expect(fn () => FilamentGoogleWorkspaceAuthPlugin::make()->boot($panel))
        ->toThrow(LogicException::class, 'The pending access lock does not support tenancy, on panel [tenant].');
});

it('refuses a panel whose guard is not the configured one', function () {
    $panel = Panel::make()->id('other')->authGuard('web');

    expect(fn () => FilamentGoogleWorkspaceAuthPlugin::make()->boot($panel))
        ->toThrow(LogicException::class, 'The pending access lock needs the panel [other] to use the [filament] guard.');
});

it('refuses spatie teams', function () {
    Config::set('permission.teams', true);

    expect(fn () => FilamentGoogleWorkspaceAuthPlugin::make()->boot(Panel::make()->id('teams')->authGuard('filament')))
        ->toThrow(LogicException::class, 'The pending access lock does not support spatie teams.');
});

it('always treats the default role as pending', function () {
    Config::set('filament-google-workspace-auth.default_role', 'pending');

    $user = createFilamentUser();
    $user->assignRole(Role::findOrCreate('pending', 'filament'));

    getAs($this, $user, MarkerPage::getUrl(panel: 'admin'))->assertRedirect(pendingAccessUrl());
});

it('denies a locked account loaded with others while lazy loading is prevented', function () {
    createGuest();
    createGuest();
    Gate::define('anything', fn () => true);
    Model::preventLazyLoading();

    try {
        expect(Gate::forUser(FilamentUser::query()->get()[0])->allows('anything'))->toBeFalse();
    } finally {
        Model::preventLazyLoading(false);
    }
});

it('locks a signed-in user that is not an eloquent model', function () {
    Route::middleware(['web', EnsureAccessIsGranted::class])->get('/app-route', fn () => 'app');
    Auth::guard('filament')->setUser(new NonEloquentUser(['id' => 1]));

    $this->get('/app-route')->assertRedirect(pendingAccessUrl());
});
