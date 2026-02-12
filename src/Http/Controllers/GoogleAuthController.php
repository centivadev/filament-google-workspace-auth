<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Http\Controllers;

use CentivaDev\FilamentGoogleWorkspaceAuth\Services\GoogleOidcService;
use CentivaDev\FilamentGoogleWorkspaceAuth\Support\RoleSetup;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class GoogleAuthController
{
    public function __construct(private readonly GoogleOidcService $oidc) {}

    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $nonce = Str::random(40);
        $codeVerifier = $this->oidc->generateCodeVerifier();
        $codeChallenge = $this->oidc->generateCodeChallenge($codeVerifier);

        $request->session()->put('filament-google.state', $state);
        $request->session()->put('filament-google.nonce', $nonce);
        $request->session()->put('filament-google.code_verifier', $codeVerifier);

        return redirect()->away($this->oidc->buildAuthorizationUrl($state, $nonce, $codeChallenge));
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            abort(403, 'Google authentication failed.');
        }

        $state = (string) $request->session()->pull('filament-google.state');
        $nonce = (string) $request->session()->pull('filament-google.nonce');
        $codeVerifier = (string) $request->session()->pull('filament-google.code_verifier');

        if (! hash_equals($state, (string) $request->query('state'))) {
            abort(403, 'Invalid authentication state.');
        }

        $code = (string) $request->query('code');
        if ($code === '') {
            abort(403, 'Missing authorization code.');
        }

        $tokens = $this->oidc->exchangeCodeForTokens($code, $codeVerifier);
        $idToken = (string) ($tokens['id_token'] ?? '');

        if ($idToken === '') {
            abort(403, 'Missing ID token.');
        }

        $claims = $this->oidc->verifyIdToken($idToken, $nonce);

        $email = (string) ($claims['email'] ?? '');
        $emailVerified = (bool) ($claims['email_verified'] ?? false);
        $hostedDomain = (string) ($claims['hd'] ?? '');
        $sub = (string) ($claims['sub'] ?? '');

        if ($email === '' || ! $emailVerified) {
            abort(403, 'Email not verified.');
        }

        $expectedDomain = (string) config('filament-google-workspace-auth.hosted_domain');
        if ($expectedDomain !== '') {
            if ($hostedDomain !== '' && $hostedDomain !== $expectedDomain) {
                abort(403, 'Invalid Google Workspace domain.');
            }

            if (! Str::endsWith($email, '@' . $expectedDomain)) {
                abort(403, 'Invalid Google Workspace email.');
            }
        }

        $allowedEmails = config('filament-google-workspace-auth.allowed_emails', []);
        if (is_array($allowedEmails) && count($allowedEmails) > 0 && ! in_array($email, $allowedEmails, true)) {
            abort(403, 'Email not allowed.');
        }

        $userModel = (string) config('filament-google-workspace-auth.user_model');

        if ($userModel === '' || ! class_exists($userModel)) {
            abort(500, 'Invalid user model.');
        }

        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = $userModel::query()
            ->where('google_sub', $sub)
            ->orWhere('email', $email)
            ->first();

        $isNew = false;
        if (! $user) {
            if (! config('filament-google-workspace-auth.auto_provision', true)) {
                abort(403, 'User not provisioned.');
            }

            $user = new $userModel;
            $isNew = true;
        }

        $user->fill([
            'name' => (string) ($claims['name'] ?? $email),
            'email' => $email,
            'google_sub' => $sub,
            'avatar_url' => (string) ($claims['picture'] ?? null),
            'last_login_at' => now(),
            'email_verified_at' => $emailVerified ? now() : null,
        ]);

        if ($isNew && empty($user->password)) {
            $user->forceFill([
                'password' => Hash::make(Str::random(64)),
            ]);
        }

        if (property_exists($user, 'is_active')) {
            $user->setAttribute('is_active', true);
        }

        $user->save();

        if (! empty($user->banned_at) || (property_exists($user, 'is_active') && ! $user->is_active)) {
            abort(403, 'User is banned.');
        }

        $guard = (string) config('filament-google-workspace-auth.guard', 'filament');

        RoleSetup::ensureBaseRoles($guard);

        $superAdmins = config('filament-google-workspace-auth.super_admin_emails', []);
        if (is_array($superAdmins) && in_array($email, $superAdmins, true)) {
            $user->assignRole(Role::findByName('super-admin', $guard));
        } elseif ($isNew) {
            $defaultRole = (string) config('filament-google-workspace-auth.default_role', 'guest');
            $user->assignRole(Role::findByName($defaultRole, $guard));
        }

        Auth::guard($guard)->login($user, true);

        return redirect()->intended(Filament::getUrl());
    }
}
