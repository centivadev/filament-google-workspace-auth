<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures;

use Illuminate\Foundation\Application;

trait EnablesPendingAccess
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('filament-google-workspace-auth.pending_access.enabled', true);
        $app['config']->set('filament-google-workspace-auth.pending_access.support_email', 'support@example.com');
    }
}
