<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Services\GoogleOidcService;

it('builds an authorization url with hosted domain', function () {
    $service = new GoogleOidcService;

    $url = $service->buildAuthorizationUrl('state-123', 'nonce-456', 'challenge-789');

    $parts = parse_url($url);
    expect($parts['host'])->toBe('accounts.google.com');

    parse_str($parts['query'] ?? '', $query);

    expect($query['client_id'])->toBe('test-client-id');
    expect($query['redirect_uri'])->toBe('https://example.test/auth/google/callback');
    expect($query['response_type'])->toBe('code');
    expect($query['scope'])->toBe('openid email profile');
    expect($query['state'])->toBe('state-123');
    expect($query['nonce'])->toBe('nonce-456');
    expect($query['code_challenge'])->toBe('challenge-789');
    expect($query['code_challenge_method'])->toBe('S256');
    expect($query['hd'])->toBe('example.com');
});

it('generates a stable code challenge', function () {
    $service = new GoogleOidcService;

    $verifier = 'test-verifier';
    $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    expect($service->generateCodeChallenge($verifier))->toBe($expected);
});
