<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Support\AccountProtection;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\FilamentUser;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

/**
 * @param  array<string, mixed>  $attributes
 */
function createFilamentUser(array $attributes = []): FilamentUser
{
    return FilamentUser::query()->create([
        'name' => 'User ' . Str::random(6),
        'email' => Str::lower(Str::random(10)) . '@example.com',
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createSuperAdmin(array $attributes = []): FilamentUser
{
    $user = createFilamentUser($attributes);
    $user->assignRole(Role::findOrCreate(AccountProtection::SUPER_ADMIN_ROLE, 'filament'));

    return $user;
}

/**
 * Grants the users resource permissions the edit page and the table actions need.
 *
 * @template TUser of Model
 *
 * @param  TUser  $user
 * @return TUser
 */
function grantUserManagement(Model $user): Model
{
    foreach (['filament.users.view_any', 'filament.users.view', 'filament.users.update', 'filament.users.delete'] as $name) {
        Permission::findOrCreate($name, 'filament');
    }

    $user->givePermissionTo(['filament.users.view_any', 'filament.users.view', 'filament.users.update', 'filament.users.delete']);

    return $user;
}

function createColumnlessUsersTable(): void
{
    Schema::create('columnless_filament_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->string('google_sub')->nullable()->index();
        $table->string('avatar_url')->nullable();
        $table->timestamp('last_login_at')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamp('banned_at')->nullable();
        $table->timestamps();
    });
}
