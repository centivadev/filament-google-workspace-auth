<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Policies\Concerns;

trait ChecksPermissions
{
    protected function userCan(mixed $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'can')) {
            return (bool) $user->can($permission);
        }

        return method_exists($user, 'hasRole') && $user->hasRole('super-admin');
    }
}
