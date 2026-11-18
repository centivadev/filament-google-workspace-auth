<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Support;

use Illuminate\Database\Eloquent\Model;

class AccessLock
{
    public static function isEnabled(): bool
    {
        return (bool) config('filament-google-workspace-auth.pending_access.enabled', true);
    }

    public static function isLocked(mixed $user): bool
    {
        $userModel = (string) config('filament-google-workspace-auth.user_model');

        return $user instanceof Model
            && $userModel !== ''
            && $user instanceof $userModel
            && static::isPending($user);
    }

    public static function isPending(object $user): bool
    {
        if (! static::isEnabled()) {
            return false;
        }

        if (! $user instanceof Model || ! method_exists($user, 'roles') || ! method_exists($user, 'permissions')) {
            return true;
        }

        $guard = (string) config('filament-google-workspace-auth.guard', 'filament');
        $pendingRoles = (array) (config('filament-google-workspace-auth.pending_access.roles') ?? ['guest']);
        $pendingRoles[] = (string) config('filament-google-workspace-auth.default_role', 'guest');

        $user->loadMissing(['roles', 'permissions']);

        $hasGrantingRole = $user->getRelation('roles')
            ->where('guard_name', $guard)
            ->whereNotIn('name', $pendingRoles)
            ->isNotEmpty();

        return ! $hasGrantingRole && $user->getRelation('permissions')->where('guard_name', $guard)->isEmpty();
    }
}
