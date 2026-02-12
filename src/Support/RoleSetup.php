<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Support;

use Spatie\Permission\Models\Role;

class RoleSetup
{
    public static function ensureBaseRoles(string $guard): void
    {
        $roles = ['super-admin', 'admin', 'guest'];

        foreach ($roles as $role) {
            Role::findOrCreate($role, $guard);
        }
    }
}
