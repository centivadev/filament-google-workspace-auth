<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures;

/**
 * Uses the package trait on a table that has no is_active column.
 */
class ColumnlessFilamentUser extends FilamentUser
{
    protected $table = 'columnless_filament_users';
}
