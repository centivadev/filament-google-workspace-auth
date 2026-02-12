<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures;

use CentivaDev\FilamentGoogleWorkspaceAuth\Concerns\HasFilamentGoogleWorkspaceUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class FilamentUser extends Authenticatable
{
    use HasFactory;
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
