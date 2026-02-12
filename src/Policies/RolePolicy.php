<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Policies;

use CentivaDev\FilamentGoogleWorkspaceAuth\Policies\Concerns\ChecksPermissions;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    use ChecksPermissions;

    public function viewAny(Authenticatable $user): bool
    {
        return $this->userCan($user, 'filament.roles.view_any');
    }

    public function view(Authenticatable $user, Role $role): bool
    {
        return $this->userCan($user, 'filament.roles.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->userCan($user, 'filament.roles.create');
    }

    public function update(Authenticatable $user, Role $role): bool
    {
        return $this->userCan($user, 'filament.roles.update');
    }

    public function delete(Authenticatable $user, Role $role): bool
    {
        if (in_array($role->name, ['super-admin', 'guest'], true)) {
            return false;
        }

        return $this->userCan($user, 'filament.roles.delete');
    }
}
