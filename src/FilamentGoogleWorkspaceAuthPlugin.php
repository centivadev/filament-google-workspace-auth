<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth;

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Pages\GoogleLogin;
use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\FilamentUserResource;
use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Permissions\PermissionResource;
use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Roles\RoleResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentGoogleWorkspaceAuthPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-google-workspace-auth';
    }

    public function register(Panel $panel): void
    {
        $panel->login(GoogleLogin::class);

        $resources = [];
        $resourceConfig = config('filament-google-workspace-auth.resources', []);

        if (($resourceConfig['users'] ?? true) === true) {
            $resources[] = FilamentUserResource::class;
        }

        if (($resourceConfig['roles'] ?? true) === true) {
            $resources[] = RoleResource::class;
        }

        if (($resourceConfig['permissions'] ?? true) === true) {
            $resources[] = PermissionResource::class;
        }

        if (count($resources) > 0) {
            $panel->resources($resources);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
