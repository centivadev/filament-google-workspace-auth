<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Http\Middleware\CheckGoogleSessionLifetime;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', CheckGoogleSessionLifetime::class])
        ->get('/_test/google-session', fn () => response('ok'));
});

it('redirects to login when absolute session lifetime is exceeded', function () {
    Config::set('filament-google-workspace-auth.session_lifetime', 60);
    Filament::shouldReceive('getLoginUrl')->andReturn('/login');

    $response = $this->withSession([
        'filament-google.authenticated_at' => time() - (61 * 60),
    ])->get('/_test/google-session');

    $response->assertRedirect('/login');
});

it('passes through when absolute session lifetime has not elapsed', function () {
    Config::set('filament-google-workspace-auth.session_lifetime', 60);

    $response = $this->withSession([
        'filament-google.authenticated_at' => time() - (30 * 60),
    ])->get('/_test/google-session');

    $response->assertOk();
});

it('skips userinfo check when interval has not elapsed', function () {
    Config::set('filament-google-workspace-auth.userinfo_check_interval', 60);

    Http::fake();

    $response = $this->withSession([
        'filament-google.access_token' => 'valid-token',
        'filament-google.access_token_expires_at' => time() + 3600,
        'filament-google.last_userinfo_check' => time() - (30 * 60),
    ])->get('/_test/google-session');

    $response->assertOk();
    Http::assertNothingSent();
});

it('redirects to login when userinfo returns 401 (revoked token)', function () {
    Config::set('filament-google-workspace-auth.userinfo_check_interval', 5);

    Http::fake([
        'openidconnect.googleapis.com/*' => Http::response(null, 401),
    ]);

    Filament::shouldReceive('getLoginUrl')->andReturn('/login');

    $response = $this->withSession([
        'filament-google.access_token' => 'revoked-token',
        'filament-google.access_token_expires_at' => time() + 3600,
        'filament-google.last_userinfo_check' => 0,
    ])->get('/_test/google-session');

    $response->assertRedirect('/login');
    Http::assertSentCount(1);
});

it('redirects to login when userinfo returns 403 (suspended account)', function () {
    Config::set('filament-google-workspace-auth.userinfo_check_interval', 5);

    Http::fake([
        'openidconnect.googleapis.com/*' => Http::response(null, 403),
    ]);

    Filament::shouldReceive('getLoginUrl')->andReturn('/login');

    $response = $this->withSession([
        'filament-google.access_token' => 'suspended-token',
        'filament-google.access_token_expires_at' => time() + 3600,
        'filament-google.last_userinfo_check' => 0,
    ])->get('/_test/google-session');

    $response->assertRedirect('/login');
    Http::assertSentCount(1);
});

it('fails open on network errors during userinfo check', function () {
    Config::set('filament-google-workspace-auth.userinfo_check_interval', 5);

    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Network error');
    });

    $response = $this->withSession([
        'filament-google.access_token' => 'valid-token',
        'filament-google.access_token_expires_at' => time() + 3600,
        'filament-google.last_userinfo_check' => 0,
    ])->get('/_test/google-session');

    $response->assertOk();
});
