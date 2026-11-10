<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures;

/**
 * Neither the package trait nor an is_active column: only the attribute fallback applies.
 */
class LegacyColumnlessFilamentUser extends LegacyFilamentUser
{
    protected $table = 'columnless_filament_users';
}
