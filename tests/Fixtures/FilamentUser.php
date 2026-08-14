<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures;

use CentivaDev\FilamentGoogleWorkspaceAuth\Concerns\HasFilamentGoogleWorkspaceUser;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property string|null $google_sub
 * @property string|null $avatar_url
 * @property Carbon|null $last_login_at
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $banned_at
 * @property bool $is_active
 */
class FilamentUser extends Authenticatable
{
    use HasFilamentGoogleWorkspaceUser;
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
