<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleOidcService
{
    public function buildAuthorizationUrl(string $state, string $nonce, string $codeChallenge): string
    {
        $params = [
            'client_id' => $this->getClientId(),
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        $hostedDomain = $this->getHostedDomain();
        if (! empty($hostedDomain)) {
            $params['hd'] = $hostedDomain;
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForTokens(string $code, string $codeVerifier): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->getRedirectUri(),
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Google token exchange failed.');
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyIdToken(string $idToken, string $expectedNonce): array
    {
        $keys = $this->getJwkKeySet();
        $decoded = (array) JWT::decode($idToken, $keys);

        $audience = $decoded['aud'] ?? null;
        $issuer = $decoded['iss'] ?? null;
        $nonce = $decoded['nonce'] ?? null;

        if ($audience !== $this->getClientId()) {
            throw new \RuntimeException('Invalid token audience.');
        }

        if (! in_array($issuer, ['https://accounts.google.com', 'accounts.google.com'], true)) {
            throw new \RuntimeException('Invalid token issuer.');
        }

        if (! hash_equals((string) $expectedNonce, (string) $nonce)) {
            throw new \RuntimeException('Invalid token nonce.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJwkKeySet(): array
    {
        $jwks = Cache::remember('filament-google-workspace-auth.jwks', 3600, function () {
            $response = Http::get('https://www.googleapis.com/oauth2/v3/certs');

            if (! $response->successful()) {
                throw new \RuntimeException('Unable to fetch Google JWKs.');
            }

            return $response->json();
        });

        if (! is_array($jwks)) {
            throw new \RuntimeException('Invalid JWK response.');
        }

        return JWK::parseKeySet($jwks);
    }

    protected function getClientId(): string
    {
        $clientId = (string) config('filament-google-workspace-auth.client_id');

        if ($clientId === '') {
            throw new \RuntimeException('Missing Google client_id.');
        }

        return $clientId;
    }

    protected function getClientSecret(): string
    {
        $clientSecret = (string) config('filament-google-workspace-auth.client_secret');

        if ($clientSecret === '') {
            throw new \RuntimeException('Missing Google client_secret.');
        }

        return $clientSecret;
    }

    public function getRedirectUri(): string
    {
        $redirectUri = (string) config('filament-google-workspace-auth.redirect_uri');

        if ($redirectUri !== '') {
            return $redirectUri;
        }

        return url(route('filament-google-workspace-auth.callback', [], false));
    }

    public function getHostedDomain(): string
    {
        return (string) config('filament-google-workspace-auth.hosted_domain');
    }

    public function generateCodeVerifier(): string
    {
        return Str::random(96);
    }

    public function generateCodeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }
}
