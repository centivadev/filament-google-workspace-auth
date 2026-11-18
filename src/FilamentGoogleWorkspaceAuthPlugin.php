<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth;

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Pages\GoogleLogin;
use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Pages\PendingAccess;
use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\FilamentUserResource;
use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Permissions\PermissionResource;
use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Roles\RoleResource;
use CentivaDev\FilamentGoogleWorkspaceAuth\Http\Middleware\CheckGoogleSessionLifetime;
use CentivaDev\FilamentGoogleWorkspaceAuth\Http\Middleware\EnsureAccessIsGranted;
use CentivaDev\FilamentGoogleWorkspaceAuth\Http\Middleware\EnsureAccountIsAllowed;
use CentivaDev\FilamentGoogleWorkspaceAuth\Support\AccessLock;
use Filament\Contracts\Plugin;
use Filament\Panel;
use LogicException;

class FilamentGoogleWorkspaceAuthPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-google-workspace-auth';
    }

    public function register(Panel $panel): void
    {
        $panel->login(GoogleLogin::class);
        $panel->authMiddleware([CheckGoogleSessionLifetime::class], isPersistent: true);
        $panel->authMiddleware([EnsureAccountIsAllowed::class], isPersistent: true);

        if (AccessLock::isEnabled()) {
            $panel->pages([PendingAccess::class]);
            $panel->authMiddleware([EnsureAccessIsGranted::class], isPersistent: true);
        }

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
        if (! AccessLock::isEnabled()) {
            return;
        }

        if ($panel->hasTenancy()) {
            throw new LogicException("The pending access lock does not support tenancy, on panel [{$panel->getId()}].");
        }

        $guard = (string) config('filament-google-workspace-auth.guard', 'filament');

        if ($panel->getAuthGuard() !== $guard) {
            throw new LogicException("The pending access lock needs the panel [{$panel->getId()}] to use the [{$guard}] guard.");
        }

        if (config('permission.teams')) {
            throw new LogicException('The pending access lock does not support spatie teams.');
        }
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
