<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\FilamentUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('authorizes permission resource actions via policies', function () {
    $user = FilamentUser::query()->create([
        'name' => 'Policy User',
        'email' => 'policy@example.com',
        'password' => bcrypt('secret'),
    ]);

    Permission::findOrCreate('filament.permissions.view_any', 'filament');
    Permission::findOrCreate('filament.permissions.create', 'filament');
    Permission::findOrCreate('filament.permissions.update', 'filament');
    Permission::findOrCreate('filament.permissions.delete', 'filament');

    $user->givePermissionTo([
        'filament.permissions.view_any',
        'filament.permissions.create',
        'filament.permissions.update',
        'filament.permissions.delete',
    ]);

    expect($user->can('viewAny', Permission::class))->toBeTrue();
    expect($user->can('create', Permission::class))->toBeTrue();
    expect($user->can('update', Permission::findByName('filament.permissions.update', 'filament')))->toBeTrue();
    expect($user->can('delete', Permission::findByName('filament.permissions.delete', 'filament')))->toBeTrue();
});

it('blocks deletion of protected roles even with delete permission', function () {
    $user = FilamentUser::query()->create([
        'name' => 'Role User',
        'email' => 'role@example.com',
        'password' => bcrypt('secret'),
    ]);

    Permission::findOrCreate('filament.roles.delete', 'filament');
    $user->givePermissionTo('filament.roles.delete');

    $protected = Role::findOrCreate('super-admin', 'filament');
    $regular = Role::findOrCreate('editor', 'filament');

    expect($user->can('delete', $protected))->toBeFalse();
    expect($user->can('delete', $regular))->toBeTrue();
});
