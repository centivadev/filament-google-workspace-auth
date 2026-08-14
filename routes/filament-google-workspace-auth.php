<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

$middleware = ['web'];

$throttle = config('filament-google-workspace-auth.routes.throttle', '120,1');
if (! empty($throttle)) {
    $middleware[] = 'throttle:' . $throttle;
}

Route::middleware($middleware)->group(function () {
    $prefix = trim((string) config('filament-google-workspace-auth.routes.prefix', 'filament/auth/google'), '/');

    Route::get($prefix, [GoogleAuthController::class, 'redirect'])
        ->name('filament-google-workspace-auth.redirect');

    Route::get($prefix . '/callback', [GoogleAuthController::class, 'callback'])
        ->name('filament-google-workspace-auth.callback');
});
