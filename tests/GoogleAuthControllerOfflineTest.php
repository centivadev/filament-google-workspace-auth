<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Services\GoogleOidcService;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\FilamentUser;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\TestCase;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drive a full, well-formed callback for an already-existing account.
 *
 * @return TestResponse<Response>
 */
function attemptGoogleLogin(TestCase $test, string $email, string $sub, string $nonce): TestResponse
{
    $mock = Mockery::mock(GoogleOidcService::class);
    $mock->shouldReceive('exchangeCodeForTokens')->andReturn(['id_token' => 'token']);
    $mock->shouldReceive('verifyIdToken')->andReturn([
        'email' => $email,
        'email_verified' => true,
        'hd' => 'example.com',
        'sub' => $sub,
        'name' => 'Renamed By Google',
        'nonce' => $nonce,
    ]);

    app()->instance(GoogleOidcService::class, $mock);

    return $test->withSession([
        'filament-google.state' => 'state-' . $nonce,
        'filament-google.nonce' => $nonce,
        'filament-google.code_verifier' => 'code-verifier',
    ])->get(route('filament-google-workspace-auth.callback', [
        'state' => 'state-' . $nonce,
        'code' => 'auth-code',
    ]));
}

it('provisions a new user, assigns role, and logs in', function () {
    Role::findOrCreate('guest', 'filament');

    $mock = Mockery::mock(GoogleOidcService::class);
    $mock->shouldReceive('exchangeCodeForTokens')
        ->once()
        ->with('auth-code', 'code-verifier')
        ->andReturn(['id_token' => 'token']);
    $mock->shouldReceive('verifyIdToken')
        ->once()
        ->with('token', 'nonce-123')
        ->andReturn([
            'email' => 'user@example.com',
            'email_verified' => true,
            'hd' => 'example.com',
            'sub' => 'sub-123',
            'name' => 'Test User',
            'picture' => 'https://example.test/avatar.png',
            'nonce' => 'nonce-123',
        ]);

    app()->instance(GoogleOidcService::class, $mock);

    Filament::shouldReceive('getUrl')->andReturn('/admin');

    $response = $this->withSession([
        'filament-google.state' => 'state-123',
        'filament-google.nonce' => 'nonce-123',
        'filament-google.code_verifier' => 'code-verifier',
    ])->get(route('filament-google-workspace-auth.callback', [
        'state' => 'state-123',
        'code' => 'auth-code',
    ]));

    $response->assertRedirect('/admin');

    $user = FilamentUser::query()->first();

    expect($user)->not->toBeNull();
    expect($user->email)->toBe('user@example.com');
    expect($user->google_sub)->toBe('sub-123');
    expect($user->hasRole('guest', 'filament'))->toBeTrue();
    expect(Auth::guard('filament')->check())->toBeTrue();
});

it('rejects a callback that never went through the redirect flow', function () {
    // Regression: an absent session value and an absent query parameter both cast to '',
    // and hash_equals('', '') returns true, which used to accept the callback outright.
    $mock = Mockery::mock(GoogleOidcService::class);
    $mock->shouldNotReceive('exchangeCodeForTokens');
    $mock->shouldNotReceive('verifyIdToken');

    app()->instance(GoogleOidcService::class, $mock);

    $response = $this->get(route('filament-google-workspace-auth.callback', [
        'code' => 'attacker-code',
    ]));

    $response->assertStatus(403);
    expect(FilamentUser::query()->count())->toBe(0);
    expect(Auth::guard('filament')->check())->toBeFalse();
});

it('rejects a callback when only part of the flow state survives', function () {
    $mock = Mockery::mock(GoogleOidcService::class);
    $mock->shouldNotReceive('exchangeCodeForTokens');

    app()->instance(GoogleOidcService::class, $mock);

    // State present but the nonce and verifier were lost: the flow is not trustworthy.
    $response = $this->withSession([
        'filament-google.state' => 'state-partial',
    ])->get(route('filament-google-workspace-auth.callback', [
        'state' => 'state-partial',
        'code' => 'auth-code',
    ]));

    $response->assertStatus(403);
});

it('rejects an id token without a subject claim', function () {
    $mock = Mockery::mock(GoogleOidcService::class);
    $mock->shouldReceive('exchangeCodeForTokens')->once()->andReturn(['id_token' => 'token']);
    $mock->shouldReceive('verifyIdToken')->once()->andReturn([
        'email' => 'user@example.com',
        'email_verified' => true,
        'hd' => 'example.com',
        'nonce' => 'nonce-nosub',
        // no 'sub'
    ]);

    app()->instance(GoogleOidcService::class, $mock);

    $response = $this->withSession([
        'filament-google.state' => 'state-nosub',
        'filament-google.nonce' => 'nonce-nosub',
        'filament-google.code_verifier' => 'code-verifier',
    ])->get(route('filament-google-workspace-auth.callback', [
        'state' => 'state-nosub',
        'code' => 'auth-code',
    ]));

    $response->assertStatus(403);
    expect(FilamentUser::query()->count())->toBe(0);
});

it('blocks a deactivated user and leaves the record untouched', function () {
    // Regression: the guard used property_exists(), which is always false for Eloquent
    // column values, so deactivated users could sign in and were silently re-activated.
    $user = FilamentUser::create([
        'name' => 'Disabled User',
        'email' => 'disabled@example.com',
        'google_sub' => 'sub-disabled',
        'last_login_at' => '2020-01-01 00:00:00',
        'is_active' => false,
    ]);

    $response = attemptGoogleLogin($this, 'disabled@example.com', 'sub-disabled', 'nonce-disabled');

    $response->assertStatus(403);
    expect(Auth::guard('filament')->check())->toBeFalse();

    $user->refresh();
    expect($user->is_active)->toBeFalse();
    expect($user->name)->toBe('Disabled User');
    expect($user->last_login_at->toDateTimeString())->toBe('2020-01-01 00:00:00');
});

it('blocks a banned user and leaves the record untouched', function () {
    $user = FilamentUser::create([
        'name' => 'Banned User',
        'email' => 'banned@example.com',
        'google_sub' => 'sub-banned',
        'last_login_at' => '2020-01-01 00:00:00',
        'banned_at' => '2021-06-01 00:00:00',
    ]);

    $response = attemptGoogleLogin($this, 'banned@example.com', 'sub-banned', 'nonce-banned');

    $response->assertStatus(403);
    expect(Auth::guard('filament')->check())->toBeFalse();

    $user->refresh();
    expect($user->name)->toBe('Banned User');
    expect($user->last_login_at->toDateTimeString())->toBe('2020-01-01 00:00:00');
});

it('blocks users not in the allowlist', function () {
    Config::set('filament-google-workspace-auth.allowed_emails', ['allowed@example.com']);

    $mock = Mockery::mock(GoogleOidcService::class);
    $mock->shouldReceive('exchangeCodeForTokens')
        ->once()
        ->andReturn(['id_token' => 'token']);
    $mock->shouldReceive('verifyIdToken')
        ->once()
        ->andReturn([
            'email' => 'blocked@example.com',
            'email_verified' => true,
            'hd' => 'example.com',
            'sub' => 'sub-456',
            'nonce' => 'nonce-allow',
        ]);

    app()->instance(GoogleOidcService::class, $mock);

    $response = $this->withSession([
        'filament-google.state' => 'state-allow',
        'filament-google.nonce' => 'nonce-allow',
        'filament-google.code_verifier' => 'code-verifier',
    ])->get(route('filament-google-workspace-auth.callback', [
        'state' => 'state-allow',
        'code' => 'auth-code',
    ]));

    $response->assertStatus(403);
});
