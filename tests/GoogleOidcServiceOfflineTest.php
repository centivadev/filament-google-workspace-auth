<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Services\GoogleOidcService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('builds the authorization url with hosted domain', function () {
    Config::set('filament-google-workspace-auth.client_id', 'client-id');
    Config::set('filament-google-workspace-auth.redirect_uri', 'https://example.test/callback');
    Config::set('filament-google-workspace-auth.hosted_domain', 'example.com');

    $service = new GoogleOidcService();

    $url = $service->buildAuthorizationUrl('state-123', 'nonce-123', 'challenge-123');
    $parts = parse_url($url);
    parse_str($parts['query'] ?? '', $query);

    expect($parts['host'] ?? null)->toBe('accounts.google.com');
    expect($query['client_id'] ?? null)->toBe('client-id');
    expect($query['redirect_uri'] ?? null)->toBe('https://example.test/callback');
    expect($query['response_type'] ?? null)->toBe('code');
    expect($query['scope'] ?? null)->toBe('openid email profile');
    expect($query['state'] ?? null)->toBe('state-123');
    expect($query['nonce'] ?? null)->toBe('nonce-123');
    expect($query['code_challenge'] ?? null)->toBe('challenge-123');
    expect($query['code_challenge_method'] ?? null)->toBe('S256');
    expect($query['hd'] ?? null)->toBe('example.com');
});

it('throws when client id is missing', function () {
    Config::set('filament-google-workspace-auth.client_id', '');
    Config::set('filament-google-workspace-auth.redirect_uri', 'https://example.test/callback');

    $service = new GoogleOidcService();

    $call = fn () => $service->buildAuthorizationUrl('state', 'nonce', 'challenge');

    expect($call)->toThrow(RuntimeException::class, 'Missing Google client_id.');
});

it('exchanges code for tokens using http fake', function () {
    Config::set('filament-google-workspace-auth.client_id', 'client-id');
    Config::set('filament-google-workspace-auth.client_secret', 'client-secret');
    Config::set('filament-google-workspace-auth.redirect_uri', 'https://example.test/callback');

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['id_token' => 'token'], 200),
    ]);

    $service = new GoogleOidcService();
    $tokens = $service->exchangeCodeForTokens('code', 'verifier');

    expect($tokens['id_token'] ?? null)->toBe('token');
});

it('throws when token exchange fails', function () {
    Config::set('filament-google-workspace-auth.client_id', 'client-id');
    Config::set('filament-google-workspace-auth.client_secret', 'client-secret');
    Config::set('filament-google-workspace-auth.redirect_uri', 'https://example.test/callback');

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid'], 400),
    ]);

    $service = new GoogleOidcService();

    $call = fn () => $service->exchangeCodeForTokens('code', 'verifier');

    expect($call)->toThrow(RuntimeException::class, 'Google token exchange failed.');
});

it('generates a stable code challenge for a known verifier', function () {
    $service = new GoogleOidcService();
    $challenge = $service->generateCodeChallenge('abc');

    expect($challenge)->toBe('ungWv48Bz-pBQUDeXa4iI7ADYaOWF3qctBD_YfIAFa0');
});
