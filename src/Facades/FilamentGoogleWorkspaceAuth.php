<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \CentivaDev\FilamentGoogleWorkspaceAuth\FilamentGoogleWorkspaceAuth
 */
class FilamentGoogleWorkspaceAuth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CentivaDev\FilamentGoogleWorkspaceAuth\FilamentGoogleWorkspaceAuth::class;
    }
}
