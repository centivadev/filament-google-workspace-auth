<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures;

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\Pages\EditFilamentUser;
use Illuminate\Support\Facades\DB;

/**
 * Commits a ban of another account between Filament's dehydration and beforeSave, as a concurrent
 * transaction would under READ COMMITTED.
 */
class RacyEditFilamentUser extends EditFilamentUser
{
    public static ?int $concurrentlyBannedId = null;

    protected function afterValidate(): void
    {
        DB::table('filament_users')
            ->where('id', static::$concurrentlyBannedId)
            ->update(['banned_at' => now(), 'is_active' => false]);

        app()->forgetScopedInstances();
    }
}
