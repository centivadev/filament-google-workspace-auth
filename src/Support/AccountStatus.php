<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Prefers the HasFilamentGoogleWorkspaceUser helpers so a model may override them, and falls
 * back to the same rule read straight off the attributes for models predating the trait.
 */
class AccountStatus
{
    public static function isBanned(Model $user): bool
    {
        return method_exists($user, 'isBanned')
            ? (bool) $user->isBanned()
            : ! empty($user->getAttribute('banned_at'));
    }

    public static function isActive(Model $user): bool
    {
        return method_exists($user, 'isActive')
            ? (bool) $user->isActive()
            : ! array_key_exists('is_active', $user->getAttributes()) || (bool) $user->getAttribute('is_active');
    }

    /**
     * @var array<string, bool>
     */
    protected static array $activeColumns = [];

    public static function hasActiveColumn(Model $user): bool
    {
        if (array_key_exists('is_active', $user->getAttributes())) {
            return true;
        }

        $key = $user->getConnectionName() . '|' . $user->getTable();

        return static::$activeColumns[$key] ??= $user->getConnection()->getSchemaBuilder()->hasColumn($user->getTable(), 'is_active');
    }

    public static function allowsSignIn(Model $user): bool
    {
        return ! static::isBanned($user) && static::isActive($user);
    }
}
