<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Policies;

use CentivaDev\FilamentGoogleWorkspaceAuth\Policies\Concerns\ChecksPermissions;
use Illuminate\Contracts\Auth\Authenticatable;

class FilamentUserPolicy
{
    use ChecksPermissions;

    public function viewAny(Authenticatable $user): bool
    {
        return $this->userCan($user, 'filament.users.view_any');
    }

    public function view(Authenticatable $user, mixed $model): bool
    {
        return $this->userCan($user, 'filament.users.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->userCan($user, 'filament.users.create');
    }

    public function update(Authenticatable $user, mixed $model): bool
    {
        return $this->userCan($user, 'filament.users.update');
    }

    public function delete(Authenticatable $user, mixed $model): bool
    {
        return $this->userCan($user, 'filament.users.delete');
    }
}
