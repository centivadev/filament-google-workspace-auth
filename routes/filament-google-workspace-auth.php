<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    $prefix = trim((string) config('filament-google-workspace-auth.routes.prefix', 'filament/auth/google'), '/');

    Route::get($prefix, [GoogleAuthController::class, 'redirect'])
        ->name('filament-google-workspace-auth.redirect');

    Route::get($prefix . '/callback', [GoogleAuthController::class, 'callback'])
        ->name('filament-google-workspace-auth.callback');
});
