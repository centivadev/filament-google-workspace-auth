<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Http\Middleware;

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Pages\PendingAccess;
use CentivaDev\FilamentGoogleWorkspaceAuth\Support\AccessLock;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccessIsGranted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard((string) config('filament-google-workspace-auth.guard', 'filament'))->user();
        $panel = Filament::getCurrentOrDefaultPanel();
        $routeName = $request->route()?->getName();
        $pendingRouteName = PendingAccess::getRouteName($panel);

        if ($user === null || ! AccessLock::isPending($user)) {
            return $routeName === $pendingRouteName
                ? redirect()->to((string) $panel->getUrl())
                : $next($request);
        }

        if (in_array($routeName, [$pendingRouteName, $panel->generateRouteName('auth.logout')], true)) {
            return $next($request);
        }

        abort_unless(Route::has($pendingRouteName), 403);

        return redirect()->to(PendingAccess::getUrl(panel: $panel->getId()));
    }
}
