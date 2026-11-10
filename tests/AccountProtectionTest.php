<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Exceptions\AccountProtectionException;
use CentivaDev\FilamentGoogleWorkspaceAuth\Support\AccountProtection;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\FilamentUser;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;

// RefreshDatabase keeps every test inside a transaction, which the ensure* methods require.

function protection(): AccountProtection
{
    return app(AccountProtection::class);
}

/**
 * @return array<string, array<int, string>>
 */
function protectionErrors(Closure $check): array
{
    try {
        $check();
    } catch (AccountProtectionException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('Expected an AccountProtectionException.');
}

it('refuses self-deactivation and applies the error key prefix', function () {
    $user = createFilamentUser();

    expect(protectionErrors(fn () => protection()->ensureCanUpdate($user, $user, ['is_active' => false])))
        ->toHaveKey('is_active')
        ->toHaveCount(1);

    expect(protectionErrors(fn () => protection()->ensureCanUpdate($user, $user, ['is_active' => false], 'data.')))
        ->toBe(['data.is_active' => [__('filament-google-workspace-auth::filament-google-workspace-auth.protection.self_deactivate')]]);

    protection()->ensureCanUpdate($user, $user, ['is_active' => true]);
});

it('refuses a self-ban', function () {
    $user = createFilamentUser();

    expect(protectionErrors(fn () => protection()->ensureCanBan($user, $user)))->toHaveKey('banned_at');
    expect(protectionErrors(fn () => protection()->ensureCanUpdate($user, $user, ['banned_at' => now()])))->toHaveKey('banned_at');
    expect(protection()->canDeactivate($user, $user))->toBeFalse();
});

it('refuses the removal of the own super-admin role but allows adding roles', function () {
    createSuperAdmin();
    $user = createSuperAdmin();
    $superAdmin = Role::findByName(AccountProtection::SUPER_ADMIN_ROLE, 'filament');
    $editor = Role::findOrCreate('editor', 'filament');

    expect(protectionErrors(fn () => protection()->ensureCanUpdate($user, $user, ['roles' => [$editor->id]])))
        ->toBe(['roles' => [__('filament-google-workspace-auth::filament-google-workspace-auth.protection.self_super_admin')]]);
    expect(protection()->canRemoveSuperAdmin($user, $user))->toBeFalse();

    protection()->ensureCanUpdate($user, $user, ['roles' => [(string) $superAdmin->id, (string) $editor->id]]);
    protection()->ensureCanUpdate($user, $user, ['roles' => [$superAdmin->id, $editor->id]]);
    protection()->ensureCanUpdate($user, $user, []);
});

it('ignores a super-admin role of another guard', function () {
    $user = createFilamentUser();
    $webSuperAdmin = Role::findOrCreate(AccountProtection::SUPER_ADMIN_ROLE, 'web');
    $user->roles()->attach($webSuperAdmin);

    expect(protection()->isLastActiveSuperAdmin($user))->toBeFalse();
    protection()->ensureCanDelete($user);
});

it('protects the last active super-admin from another user', function () {
    $target = createSuperAdmin();
    createSuperAdmin(['banned_at' => now()]);
    createSuperAdmin(['is_active' => false]);
    $actor = grantUserManagement(createFilamentUser());
    $lastSuperAdmin = __('filament-google-workspace-auth::filament-google-workspace-auth.protection.last_super_admin');

    expect(protection()->isLastActiveSuperAdmin($target))->toBeTrue();

    expect(protectionErrors(fn () => protection()->ensureCanUpdate($actor, $target, ['roles' => []], 'data.')))
        ->toBe(['data.roles' => [$lastSuperAdmin]]);
    expect(protectionErrors(fn () => protection()->ensureCanUpdate($actor, $target, ['is_active' => false])))
        ->toBe(['is_active' => [$lastSuperAdmin]]);
    expect(protectionErrors(fn () => protection()->ensureCanUpdate($actor, $target, ['banned_at' => now()])))
        ->toBe(['banned_at' => [$lastSuperAdmin]]);
    expect(protectionErrors(fn () => protection()->ensureCanBan($actor, $target)))->not->toBeEmpty();
    expect(protectionErrors(fn () => protection()->ensureCanDelete($target)))
        ->toBe(['record' => [__('filament-google-workspace-auth::filament-google-workspace-auth.protection.last_super_admin_delete')]]);

    expect(protection()->canRemoveSuperAdmin($actor, $target))->toBeFalse();
    expect(protection()->canDeactivate($actor, $target))->toBeFalse();
    expect(protection()->canDelete($target))->toBeFalse();
    expect($actor->can('delete', $target))->toBeFalse();

    protection()->ensureCanUpdate($actor, $target, ['roles' => [Role::findByName(AccountProtection::SUPER_ADMIN_ROLE, 'filament')->id]]);
    expect($target->refresh()->hasRole(AccountProtection::SUPER_ADMIN_ROLE))->toBeTrue();
});

it('does not count a banned or inactive super-admin acting on the last active one', function (array $actorAttributes) {
    $target = createSuperAdmin();
    $actor = grantUserManagement(createSuperAdmin($actorAttributes));

    expect(protectionErrors(fn () => protection()->ensureCanUpdate($actor, $target, ['roles' => []])))->toHaveKey('roles');
    expect(protectionErrors(fn () => protection()->ensureCanBan($actor, $target)))->not->toBeEmpty();
    expect(protectionErrors(fn () => protection()->ensureCanDelete($target)))->toHaveKey('record');
    expect($actor->can('delete', $target))->toBeFalse();
})->with([
    'banned' => [['banned_at' => '2024-01-01 00:00:00']],
    'inactive' => [['is_active' => false]],
]);

it('lets another user demote, ban or delete a super-admin while a second one is active', function () {
    $target = createSuperAdmin();
    createSuperAdmin();
    $actor = grantUserManagement(createFilamentUser());

    expect(protection()->isLastActiveSuperAdmin($target))->toBeFalse();
    expect(protection()->canRemoveSuperAdmin($actor, $target))->toBeTrue();
    expect(protection()->canDeactivate($actor, $target))->toBeTrue();
    expect(protection()->canDelete($target))->toBeTrue();
    expect($actor->can('delete', $target))->toBeTrue();

    protection()->ensureCanUpdate($actor, $target, ['roles' => [], 'is_active' => false, 'banned_at' => now()]);
    protection()->ensureCanBan($actor, $target);
    protection()->ensureCanDelete($target);
});

it('lets a user with the delete permission delete an ordinary user', function () {
    createSuperAdmin();
    $actor = grantUserManagement(createFilamentUser());

    expect($actor->can('delete', createFilamentUser()))->toBeTrue();
});

it('rejects an unknown change key', function () {
    $user = createFilamentUser();

    expect(fn () => protection()->ensureCanUpdate($user, $user, ['roles' => [], 'email' => 'x@example.com']))
        ->toThrow(InvalidArgumentException::class, 'Unsupported account changes: email.');
});

it('requires a transaction on the user model connection', function () {
    // RefreshDatabase only wraps the default connection, so a second one has no open transaction.
    Config::set('database.connections.untransacted', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

    $user = (new FilamentUser)->setConnection('untransacted')->forceFill(['id' => 1]);

    expect($user->getConnection()->transactionLevel())->toBe(0);

    expect(fn () => protection()->ensureCanUpdate($user, $user, []))->toThrow(LogicException::class);
    expect(fn () => protection()->ensureCanBan($user, $user))->toThrow(LogicException::class);
    expect(fn () => protection()->ensureCanDelete($user))->toThrow(LogicException::class);
});
