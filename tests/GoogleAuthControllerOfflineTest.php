<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Services\GoogleOidcService;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\FilamentUser;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;

it('provisions a new user, assigns role, and logs in', function () {
    Role::findOrCreate('guest', 'filament');

    $mock = \Mockery::mock(GoogleOidcService::class);
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

it('blocks users not in the allowlist', function () {
    Config::set('filament-google-workspace-auth.allowed_emails', ['allowed@example.com']);

    $mock = \Mockery::mock(GoogleOidcService::class);
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
