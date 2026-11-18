<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Support\AccountProtection;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\FilamentUser;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\MarkerPage;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

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

/**
 * @return TestResponse<Response>
 */
function getAs(TestCase $test, FilamentUser $user, string $url): TestResponse
{
    Auth::forgetGuards();

    return $test->withSession([Auth::guard('filament')->getName() => $user->getKey()])->get($url);
}

function markerPageSnapshot(TestCase $test, FilamentUser $user): string
{
    $html = (string) getAs($test, $user, MarkerPage::getUrl(panel: 'admin'))->assertOk()->getContent();

    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

    foreach ($matches[1] as $encoded) {
        $snapshot = htmlspecialchars_decode($encoded);

        if ((json_decode($snapshot, true)['memo']['name'] ?? null) === MarkerPage::class) {
            return $snapshot;
        }
    }

    throw new RuntimeException('MarkerPage snapshot not found in the rendered page.');
}

/**
 * @return TestResponse<Response>
 */
function callMark(TestCase $test, string $snapshot): TestResponse
{
    Livewire::flushState();
    Auth::forgetGuards();

    return $test->postJson(app(HandleRequests::class)->getUpdateUri(), [
        '_token' => session()->token(),
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => (object) [],
            'calls' => [['method' => 'mark', 'params' => [], 'metadata' => (object) []]],
        ]],
    ], ['X-Livewire' => '1']);
}
