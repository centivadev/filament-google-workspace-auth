<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Pages;

use Filament\Pages\Page;
use Filament\Panel;

class PendingAccess extends Page
{
    protected static ?string $slug = 'pending-access';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament-panels::components.layout.simple';

    protected string $view = 'filament-google-workspace-auth::pending-access';

    public static function isEmailVerificationRequired(Panel $panel): bool
    {
        return false;
    }

    public static function isMultiFactorAuthenticationRequired(Panel $panel): bool
    {
        return false;
    }

    public static function isTenantSubscriptionRequired(Panel $panel): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return __('filament-google-workspace-auth::filament-google-workspace-auth.pending_access.title');
    }

    public function hasLogo(): bool
    {
        return true;
    }

    public function getSupportEmail(): ?string
    {
        $email = config('filament-google-workspace-auth.pending_access.support_email');

        return filled($email) ? (string) $email : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getLayoutData(): array
    {
        return ['hasTopbar' => false];
    }
}
