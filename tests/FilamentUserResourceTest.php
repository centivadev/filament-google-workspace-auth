<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\FilamentUserResource;
use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\Pages\EditFilamentUser;
use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\Pages\ListFilamentUsers;
use CentivaDev\FilamentGoogleWorkspaceAuth\Support\AccountProtection;
use CentivaDev\FilamentGoogleWorkspaceAuth\Support\AccountStatus;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\ColumnlessFilamentUser;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\FilamentUser;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\RacyEditFilamentUser;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Support\Exceptions\Cancel;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @return Testable<EditFilamentUser>
 */
function editUser(Model $record): Testable
{
    return Livewire::test(EditFilamentUser::class, ['record' => $record->getRouteKey()]);
}

/**
 * @template TComponent of Component
 *
 * @param  Testable<EditFilamentUser>  $page
 * @param  class-string<TComponent>  $type
 * @return TComponent
 */
function formComponent(Testable $page, string $name, string $type): Component
{
    $component = $page->instance()->getSchema('form')?->getComponent($name);

    if (! $component instanceof $type) {
        throw new RuntimeException("Form component [{$name}] of type [{$type}] not found.");
    }

    return $component;
}

function banAction(Model $record): Action
{
    $actions = FilamentUserResource::table(Table::make(new ListFilamentUsers))->getFlatRecordActions();

    return $actions['ban']->record($record);
}

/**
 * @return array<int, int>
 */
function roleIds(FilamentUser $user): array
{
    return $user->refresh()->roles->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
}

it('wraps the edit page save in a database transaction', function () {
    $actor = grantUserManagement(createSuperAdmin());
    $this->actingAs($actor, 'filament');

    expect(editUser(createFilamentUser())->instance()->hasDatabaseTransactions())->toBeTrue();
});

it('refuses to let a super-admin remove their own super-admin role via the form', function () {
    createSuperAdmin();
    $actor = grantUserManagement(createSuperAdmin());
    $editor = Role::findOrCreate('editor', 'filament');
    $this->actingAs($actor, 'filament');

    editUser($actor)
        ->fillForm(['roles' => [$editor->id]])
        ->call('save')
        ->assertHasFormErrors(['roles']);

    expect($actor->refresh()->hasRole(AccountProtection::SUPER_ADMIN_ROLE))->toBeTrue();
    expect($actor->hasRole('editor'))->toBeFalse();
});

it('refuses a forged roles state that drops the own super-admin role', function () {
    createSuperAdmin();
    $actor = grantUserManagement(createSuperAdmin());
    $this->actingAs($actor, 'filament');

    editUser($actor)
        ->set('data.roles', [])
        ->call('save')
        ->assertHasFormErrors(['roles']);

    expect($actor->refresh()->hasRole(AccountProtection::SUPER_ADMIN_ROLE))->toBeTrue();
});

it('lets a super-admin add a role to themselves', function () {
    $actor = grantUserManagement(createSuperAdmin());
    $superAdmin = Role::findByName(AccountProtection::SUPER_ADMIN_ROLE, 'filament');
    $editor = Role::findOrCreate('editor', 'filament');
    $this->actingAs($actor, 'filament');

    editUser($actor)
        ->fillForm(['roles' => [$superAdmin->id, $editor->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(roleIds($actor))->toBe([(int) $superAdmin->id, (int) $editor->id]);

    $viewer = Role::findOrCreate('viewer', 'filament');

    editUser($actor)
        ->set('data.roles', [(string) $superAdmin->id, (string) $editor->id, (string) $viewer->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(roleIds($actor))->toBe([(int) $superAdmin->id, (int) $editor->id, (int) $viewer->id]);
});

it('lets a user with the update permission add a role to themselves', function () {
    createSuperAdmin();
    $actor = grantUserManagement(createFilamentUser());
    $editor = Role::findOrCreate('editor', 'filament');
    $this->actingAs($actor, 'filament');

    editUser($actor)
        ->fillForm(['roles' => [$editor->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($actor->refresh()->hasRole('editor'))->toBeTrue();
});

it('disables the ban action on the own record and refuses its body', function () {
    createSuperAdmin();
    $actor = grantUserManagement(createFilamentUser());
    $this->actingAs($actor, 'filament');

    Livewire::test(ListFilamentUsers::class)
        ->assertActionDisabled(TestAction::make('ban')->table($actor));

    expect(fn () => banAction($actor)->call())->toThrow(Cancel::class);

    expect($actor->refresh()->banned_at)->toBeNull();
    expect($actor->is_active)->toBeTrue();
    Notification::assertNotified(__('filament-google-workspace-auth::filament-google-workspace-auth.protection.self_deactivate'));
});

it('disables banned_at on the own record and refuses a forged value', function () {
    createSuperAdmin();
    $actor = grantUserManagement(createFilamentUser(['name' => 'Original']));
    $this->actingAs($actor, 'filament');

    editUser($actor)
        ->assertFormFieldDisabled('banned_at')
        ->set('data.banned_at', now()->toDateTimeString())
        ->set('data.name', 'Renamed')
        ->call('save')
        ->assertHasFormErrors(['banned_at']);

    expect($actor->refresh()->name)->toBe('Original');
    expect($actor->refresh()->banned_at)->toBeNull();
    expect($actor->is_active)->toBeTrue();
});

it('protects the last active super-admin from another user with the update permission', function () {
    $target = createSuperAdmin();
    createSuperAdmin(['banned_at' => now()]);
    createSuperAdmin(['is_active' => false]);
    $actor = grantUserManagement(createFilamentUser());
    $editor = Role::findOrCreate('editor', 'filament');
    $this->actingAs($actor, 'filament');

    editUser($target)
        ->fillForm(['roles' => [$editor->id]])
        ->call('save')
        ->assertHasFormErrors(['roles']);

    expect($target->refresh()->hasRole(AccountProtection::SUPER_ADMIN_ROLE))->toBeTrue();

    editUser($target)->assertFormFieldDisabled('banned_at');

    Livewire::test(ListFilamentUsers::class)
        ->assertActionDisabled(TestAction::make('ban')->table($target));

    expect(fn () => banAction($target)->call())->toThrow(Cancel::class);
    expect($target->refresh()->banned_at)->toBeNull();
    expect($target->is_active)->toBeTrue();
    Notification::assertNotified(__('filament-google-workspace-auth::filament-google-workspace-auth.protection.last_super_admin'));
});

it('lets another user demote a super-admin when a second one is active', function () {
    $target = createSuperAdmin();
    createSuperAdmin();
    $actor = grantUserManagement(createFilamentUser());
    $editor = Role::findOrCreate('editor', 'filament');
    $this->actingAs($actor, 'filament');

    editUser($target)
        ->fillForm(['roles' => [$editor->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($target->refresh()->hasRole(AccountProtection::SUPER_ADMIN_ROLE))->toBeFalse();
    expect($target->hasRole('editor'))->toBeTrue();
});

it('bans a record whose table has no is_active column', function () {
    createColumnlessUsersTable();
    Config::set('auth.providers.filament-users.model', ColumnlessFilamentUser::class);
    Config::set('filament-google-workspace-auth.user_model', ColumnlessFilamentUser::class);

    $actor = grantUserManagement(ColumnlessFilamentUser::query()->create(['name' => 'Admin', 'email' => 'admin@example.com']));
    $record = ColumnlessFilamentUser::query()->create(['name' => 'Target', 'email' => 'target@example.com']);
    $this->actingAs($actor, 'filament');

    expect(AccountStatus::hasActiveColumn($record))->toBeFalse();
    expect(banAction($record)->isAuthorized())->toBeTrue();
    banAction($record)->call();

    expect($record->refresh()->banned_at)->not->toBeNull();
    expect(array_key_exists('is_active', $record->getAttributes()))->toBeFalse();

    $actions = FilamentUserResource::table(Table::make(new ListFilamentUsers))->getFlatRecordActions();
    $actions['unban']->record($record)->call();

    expect($record->refresh()->banned_at)->toBeNull();
});

it('neither offers nor detaches a permission of another guard', function () {
    $actor = grantUserManagement(createSuperAdmin());
    $target = createFilamentUser();
    $webPermission = Permission::findOrCreate('web.only', 'web');
    $filamentPermission = Permission::findOrCreate('reports.view', 'filament');
    $webRole = Role::findOrCreate('web-editor', 'web');
    $target->permissions()->attach($webPermission);
    $target->roles()->attach($webRole);
    $this->actingAs($actor, 'filament');

    $page = editUser($target);

    $permissionOptions = formComponent($page, 'permissions', Select::class)->getOptions();
    expect($permissionOptions)->toHaveKey($filamentPermission->id);
    expect($permissionOptions)->not->toHaveKey($webPermission->id);
    expect(formComponent($page, 'roles', Select::class)->getOptions())->not->toHaveKey($webRole->id);

    $page->fillForm(['permissions' => [$filamentPermission->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    $target->refresh();
    expect($target->permissions->pluck('id')->all())->toContain($webPermission->id, $filamentPermission->id);
    expect($target->roles->pluck('id')->all())->toContain($webRole->id);
});

it('makes a direct permission effective right after save', function () {
    $actor = grantUserManagement(createSuperAdmin());
    $target = createFilamentUser();
    $permission = Permission::findOrCreate('reports.view', 'filament');
    $this->actingAs($actor, 'filament');

    expect($target->hasPermissionTo('reports.view', 'filament'))->toBeFalse();

    $page = editUser($target)
        ->fillForm(['permissions' => [$permission->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    $pageRecord = $page->instance()->getRecord();

    expect($pageRecord)->toBeInstanceOf(FilamentUser::class);
    expect($pageRecord instanceof FilamentUser && $pageRecord->hasPermissionTo('reports.view', 'filament'))->toBeTrue();
    expect($target->refresh()->hasPermissionTo('reports.view', 'filament'))->toBeTrue();
});

it('shows permissions inherited from roles apart from direct permissions', function () {
    $actor = grantUserManagement(createSuperAdmin());
    $target = createFilamentUser();
    $direct = Permission::findOrCreate('reports.export', 'filament');
    $inherited = Permission::findOrCreate('reports.view', 'filament');
    Role::findOrCreate('reporter', 'filament')->givePermissionTo($inherited);
    $target->assignRole('reporter');
    $target->givePermissionTo($direct);
    $this->actingAs($actor, 'filament');

    $page = editUser($target);

    expect(formComponent($page, 'permissions_via_roles', TextEntry::class)->getState())->toBe(['reports.view']);
    expect(array_map('intval', (array) formComponent($page, 'permissions', Select::class)->getState()))->toBe([(int) $direct->id]);
    expect(array_map('intval', (array) $page->get('data.permissions')))->toBe([(int) $direct->id]);
});

it('re-checks banned_at under lock when the field became disabled after dehydration', function () {
    $target = createSuperAdmin();
    $other = createSuperAdmin();
    $actor = grantUserManagement(createFilamentUser());
    $this->actingAs($actor, 'filament');

    RacyEditFilamentUser::$concurrentlyBannedId = $other->getKey();

    Livewire::test(RacyEditFilamentUser::class, ['record' => $target->getKey()])
        ->fillForm(['banned_at' => now()->toDateTimeString()])
        ->call('save')
        ->assertHasFormErrors(['banned_at']);

    expect($target->refresh()->banned_at)->toBeNull();
});
