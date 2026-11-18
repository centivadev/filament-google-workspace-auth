<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures;

use Illuminate\Foundation\Application;

trait DisablesPendingAccess
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('filament-google-workspace-auth.pending_access.enabled', false);
    }
}
