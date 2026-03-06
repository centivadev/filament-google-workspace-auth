<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class CheckGoogleSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->hasExceededAbsoluteLifetime($request)) {
            return $this->forceLogout($request);
        }

        if ($this->shouldCheckUserInfo($request)) {
            if (! $this->isGoogleAccountActive($request)) {
                return $this->forceLogout($request);
            }

            $request->session()->put('filament-google.last_userinfo_check', time());
        }

        return $next($request);
    }

    private function hasExceededAbsoluteLifetime(Request $request): bool
    {
        $lifetime = config('filament-google-workspace-auth.session_lifetime');

        if (! $lifetime || ! is_numeric($lifetime) || (int) $lifetime <= 0) {
            return false;
        }

        $authenticatedAt = $request->session()->get('filament-google.authenticated_at');

        if (! $authenticatedAt) {
            return false;
        }

        return time() > ($authenticatedAt + ((int) $lifetime * 60));
    }

    private function shouldCheckUserInfo(Request $request): bool
    {
        $interval = config('filament-google-workspace-auth.userinfo_check_interval');

        if (! $interval || ! is_numeric($interval) || (int) $interval <= 0) {
            return false;
        }

        $accessToken = $request->session()->get('filament-google.access_token');
        $accessTokenExpiresAt = $request->session()->get('filament-google.access_token_expires_at');

        if (! $accessToken || ! $accessTokenExpiresAt || time() >= (int) $accessTokenExpiresAt) {
            return false;
        }

        $lastCheck = $request->session()->get('filament-google.last_userinfo_check', 0);

        return time() >= ($lastCheck + ((int) $interval * 60));
    }

    private function isGoogleAccountActive(Request $request): bool
    {
        $accessToken = (string) $request->session()->get('filament-google.access_token');

        try {
            $response = Http::withToken($accessToken)
                ->timeout(5)
                ->get('https://openidconnect.googleapis.com/v1/userinfo');

            if ($response->status() === 401) {
                return false;
            }
        } catch (\Throwable) {
            // Fail open on network errors to avoid disrupting legitimate users
        }

        return true;
    }

    private function forceLogout(Request $request): Response
    {
        $guard = (string) config('filament-google-workspace-auth.guard', 'filament');
        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(Filament::getLoginUrl());
    }
}
