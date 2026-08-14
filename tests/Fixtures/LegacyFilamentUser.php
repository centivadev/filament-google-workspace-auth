<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * A consumer model that predates HasFilamentGoogleWorkspaceUser and therefore exposes neither
 * isBanned() nor isActive(). The account-status checks must still apply.
 *
 * @property string $name
 * @property Carbon|null $last_login_at
 * @property Carbon|null $banned_at
 * @property bool $is_active
 */
class LegacyFilamentUser extends Authenticatable
{
    use HasRoles;

    protected $table = 'filament_users';

    protected $fillable = [
        'name',
        'email',
        'google_sub',
        'avatar_url',
        'last_login_at',
        'email_verified_at',
        'banned_at',
        'is_active',
        'password',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'banned_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}
