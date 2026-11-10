<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Http\Middleware;

use CentivaDev\FilamentGoogleWorkspaceAuth\Support\AccountStatus;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard((string) config('filament-google-workspace-auth.guard', 'filament'));
        $user = $guard->user();

        if ($user instanceof Model && ! AccountStatus::allowsSignIn($user)) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to(Filament::getLoginUrl());
        }

        return $next($request);
    }
}
