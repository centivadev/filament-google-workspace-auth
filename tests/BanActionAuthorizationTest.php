<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\FilamentUserResource;
use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\Pages\ListFilamentUsers;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\FilamentUser;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('filament.users.view_any', 'filament');
    Permission::findOrCreate('filament.users.update', 'filament');
});

/**
 * @return array<string, Action>
 */
function getBanUnbanActions(): array
{
    $table = FilamentUserResource::table(Table::make(new ListFilamentUsers));

    return $table->getFlatRecordActions();
}

it('denies ban and unban to a user without the update permission', function () {
    $viewer = FilamentUser::query()->create([
        'name' => 'Viewer',
        'email' => 'viewer@example.com',
        'password' => bcrypt('secret'),
    ]);
    $viewer->givePermissionTo('filament.users.view_any');

    $record = FilamentUser::query()->create([
        'name' => 'Target User',
        'email' => 'target@example.com',
        'password' => bcrypt('secret'),
    ]);

    $this->actingAs($viewer, 'filament');

    $actions = getBanUnbanActions();

    expect($actions['ban']->record($record)->isAuthorized())->toBeFalse();
    expect($actions['unban']->record($record)->isAuthorized())->toBeFalse();
});

it('allows a user with the update permission to ban and unban a record', function () {
    $admin = FilamentUser::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('secret'),
    ]);
    $admin->givePermissionTo(['filament.users.view_any', 'filament.users.update']);

    $record = FilamentUser::query()->create([
        'name' => 'Target User',
        'email' => 'target@example.com',
        'password' => bcrypt('secret'),
    ]);

    $this->actingAs($admin, 'filament');

    $actions = getBanUnbanActions();

    expect($actions['ban']->record($record)->isAuthorized())->toBeTrue();
    $actions['ban']->record($record)->call();

    expect($record->refresh()->banned_at)->not->toBeNull();
    expect($record->is_active)->toBeFalse();

    expect($actions['unban']->record($record)->isAuthorized())->toBeTrue();
    $actions['unban']->record($record)->call();

    expect($record->refresh()->banned_at)->toBeNull();
    expect($record->is_active)->toBeTrue();
});
