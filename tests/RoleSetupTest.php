<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Support\RoleSetup;
use Spatie\Permission\Models\Role;

it('ensures base roles exist', function () {
    RoleSetup::ensureBaseRoles('filament');

    $roles = Role::query()
        ->where('guard_name', 'filament')
        ->pluck('name')
        ->all();

    expect($roles)->toContain('super-admin');
    expect($roles)->toContain('admin');
    expect($roles)->toContain('guest');
});
