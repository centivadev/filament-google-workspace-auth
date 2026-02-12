<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\SimplePage;

class GoogleLogin extends SimplePage
{
    protected string $view = 'filament-google-workspace-auth::login';

    public function getHeading(): string
    {
        return '';
    }

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }
    }

    public function getGoogleRedirectUrl(): string
    {
        return route('filament-google-workspace-auth.redirect');
    }
}
