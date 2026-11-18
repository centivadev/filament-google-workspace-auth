<?php

use CentivaDev\FilamentGoogleWorkspaceAuth\FilamentGoogleWorkspaceAuthPlugin;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\DisablesPendingAccess;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\MarkerPage;
use Filament\Panel;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

uses(DisablesPendingAccess::class);

it('leaves the panel, the gate and the boot checks untouched while the lock is disabled', function () {
    Config::set('app.env', 'local');

    $guest = createFilamentUser();
    $guest->assignRole(Role::findOrCreate('guest', 'filament'));

    expect(Route::has('filament.admin.pages.pending-access'))->toBeFalse();

    $this->withSession([Auth::guard('filament')->getName() => $guest->getKey()])
        ->get(MarkerPage::getUrl(panel: 'admin'))
        ->assertOk();

    Gate::define('anything', fn () => true);
    expect(Gate::forUser($guest)->allows('anything'))->toBeTrue();

    FilamentGoogleWorkspaceAuthPlugin::make()->boot(Panel::make()->id('tenant')->tenant(DatabaseNotification::class));

    Config::set('permission.teams', true);
    FilamentGoogleWorkspaceAuthPlugin::make()->boot(Panel::make()->id('other')->authGuard('web'));
});
