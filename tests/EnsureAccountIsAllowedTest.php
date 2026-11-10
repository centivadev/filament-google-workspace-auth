<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\FilamentUserResource;
use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\Pages\ListFilamentUsers;
use CentivaDev\FilamentGoogleWorkspaceAuth\Policies\FilamentUserPolicy;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\ColumnlessFilamentUser;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\LegacyColumnlessFilamentUser;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\LegacyFilamentUser;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\TestCase;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    // The fixture models do not implement FilamentUser, which Filament only tolerates locally.
    Config::set('app.env', 'local');

    // Filament's Authenticate also redirects to the login page, but never fires Logout.
    Event::fake([Logout::class]);
});

/**
 * @param  class-string<Model&Authenticatable>  $model
 */
function useUserModel(string $model): void
{
    Config::set('auth.providers.filament-users.model', $model);
    Config::set('filament-google-workspace-auth.user_model', $model);
    Gate::policy($model, FilamentUserPolicy::class);
}

/**
 * @return TestResponse<Response>
 */
function visitUsersIndex(TestCase $test, Model $user): TestResponse
{
    return $test->withSession([Auth::guard('filament')->getName() => $user->getKey(), 'unrelated.marker' => true])
        ->get(FilamentUserResource::getUrl('index'));
}

/**
 * Loads the users index over HTTP and returns the ListFilamentUsers snapshot it rendered.
 */
function usersIndexSnapshot(TestCase $test, Model $user): string
{
    $html = (string) visitUsersIndex($test, $user)->assertOk()->getContent();

    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

    foreach ($matches[1] as $encoded) {
        $snapshot = htmlspecialchars_decode($encoded);

        if ((json_decode($snapshot, true)['memo']['name'] ?? null) === ListFilamentUsers::class) {
            return $snapshot;
        }
    }

    throw new RuntimeException('ListFilamentUsers snapshot not found in the rendered page.');
}

/**
 * Replays a $refresh on the users index the way the browser does, through livewire/update.
 *
 * @return TestResponse<Response>
 */
function refreshUsersIndex(TestCase $test, string $snapshot): TestResponse
{
    // A second request in the same test would otherwise reuse the resolved guard and skip the middleware.
    Livewire::flushState();
    Auth::forgetGuards();

    return $test->postJson(app(HandleRequests::class)->getUpdateUri(), [
        '_token' => session()->token(),
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => (object) [],
            'calls' => [['method' => '$refresh', 'params' => [], 'metadata' => (object) []]],
        ]],
    ], ['X-Livewire' => '1']);
}

function expectSessionCut(Model $user): void
{
    Event::assertDispatched(Logout::class, fn (Logout $event) => $event->guard === 'filament' && $user->is($event->user));
    expect(Auth::guard('filament')->check())->toBeFalse();
    expect(session()->has(Auth::guard('filament')->getName()))->toBeFalse();
    expect(session()->has('unrelated.marker'))->toBeFalse();
}

it('lets an active user through on a panel page and on livewire/update', function () {
    $user = grantUserManagement(createFilamentUser());

    $snapshot = usersIndexSnapshot($this, $user);

    refreshUsersIndex($this, $snapshot)->assertOk()->assertJsonStructure(['components']);
    expect(Auth::guard('filament')->check())->toBeTrue();
    Event::assertNotDispatched(Logout::class);
});

it('cuts the session of a deactivated user on a panel page', function () {
    $user = grantUserManagement(createFilamentUser());
    $user->forceFill(['is_active' => false])->save();

    visitUsersIndex($this, $user)->assertRedirect(Filament::getLoginUrl());

    expectSessionCut($user);
});

it('cuts the session of a banned user on a panel page', function () {
    $user = grantUserManagement(createFilamentUser());
    $user->forceFill(['banned_at' => now()])->save();

    visitUsersIndex($this, $user)->assertRedirect(Filament::getLoginUrl());

    expectSessionCut($user);
});

it('cuts the session on livewire/update once the user is deactivated', function () {
    $user = grantUserManagement(createFilamentUser());

    $snapshot = usersIndexSnapshot($this, $user);

    $user->forceFill(['is_active' => false])->save();

    refreshUsersIndex($this, $snapshot)->assertRedirect(Filament::getLoginUrl());

    expectSessionCut($user);
});

it('cuts the session on livewire/update once the user is banned', function () {
    $user = grantUserManagement(createFilamentUser());

    $snapshot = usersIndexSnapshot($this, $user);

    $user->forceFill(['banned_at' => now()])->save();

    refreshUsersIndex($this, $snapshot)->assertRedirect(Filament::getLoginUrl());

    expectSessionCut($user);
});

it('accepts a user whose table has no is_active column', function () {
    createColumnlessUsersTable();
    useUserModel(ColumnlessFilamentUser::class);

    $user = ColumnlessFilamentUser::query()->create(['name' => 'No Column', 'email' => 'no-column@example.com']);
    grantUserManagement($user);

    $snapshot = usersIndexSnapshot($this, $user);

    refreshUsersIndex($this, $snapshot)->assertOk();
    expect(Auth::guard('filament')->check())->toBeTrue();
});

it('accepts a model without the trait and without the is_active column, and still cuts it once banned', function () {
    createColumnlessUsersTable();
    useUserModel(LegacyColumnlessFilamentUser::class);

    $user = LegacyColumnlessFilamentUser::query()->create(['name' => 'Legacy', 'email' => 'legacy@example.com']);
    grantUserManagement($user);

    expect(method_exists($user, 'isActive'))->toBeFalse();

    $snapshot = usersIndexSnapshot($this, $user);

    refreshUsersIndex($this, $snapshot)->assertOk();

    $user->forceFill(['banned_at' => now()])->save();

    refreshUsersIndex($this, $snapshot)->assertRedirect(Filament::getLoginUrl());
    expectSessionCut($user);
});

it('cuts a deactivated model that does not use the trait', function () {
    useUserModel(LegacyFilamentUser::class);

    $user = LegacyFilamentUser::query()->create(['name' => 'Legacy', 'email' => 'legacy@example.com', 'is_active' => false]);
    grantUserManagement($user);

    visitUsersIndex($this, $user)->assertRedirect(Filament::getLoginUrl());
    expectSessionCut($user);
});
