<?php

use Illuminate\Support\Facades\Route;

it('rate limits the redirect and callback routes', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'filament-google-workspace-auth.'));

    expect($routes)->toHaveCount(2);

    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->toContain('web')->toContain('throttle:120,1');
    }
});
