<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Policies;

use CentivaDev\FilamentGoogleWorkspaceAuth\Policies\Concerns\ChecksPermissions;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    use ChecksPermissions;

    public function viewAny(Authenticatable $user): bool
    {
        return $this->userCan($user, 'filament.permissions.view_any');
    }

    public function view(Authenticatable $user, Permission $permission): bool
    {
        return $this->userCan($user, 'filament.permissions.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->userCan($user, 'filament.permissions.create');
    }

    public function update(Authenticatable $user, Permission $permission): bool
    {
        return $this->userCan($user, 'filament.permissions.update');
    }

    public function delete(Authenticatable $user, Permission $permission): bool
    {
        return $this->userCan($user, 'filament.permissions.delete');
    }
}
