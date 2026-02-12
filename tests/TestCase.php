<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use CentivaDev\FilamentGoogleWorkspaceAuth\FilamentGoogleWorkspaceAuthServiceProvider;
use CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures\FilamentUser;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Config;
use Livewire\LivewireServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'CentivaDev\\FilamentGoogleWorkspaceAuth\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function tearDown(): void
    {
        \Mockery::close();

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentGoogleWorkspaceAuthServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        Config::set('auth.guards.filament', [
            'driver' => 'session',
            'provider' => 'filament-users',
        ]);

        Config::set('auth.providers.filament-users', [
            'driver' => 'eloquent',
            'model' => FilamentUser::class,
        ]);

        Config::set('filament-google-workspace-auth.user_model', FilamentUser::class);
        Config::set('filament-google-workspace-auth.client_id', 'test-client-id');
        Config::set('filament-google-workspace-auth.client_secret', 'test-client-secret');
        Config::set('filament-google-workspace-auth.redirect_uri', 'https://example.test/auth/google/callback');
        Config::set('filament-google-workspace-auth.hosted_domain', 'example.com');

        Config::set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));

        Config::set('permission.models.permission', Permission::class);
        Config::set('permission.models.role', Role::class);
        Config::set('permission.table_names', [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);
        Config::set('permission.column_names', [
            'model_morph_key' => 'model_id',
        ]);
        Config::set('permission.cache', [
            'expiration_time' => 60,
            'key' => 'spatie.permission.cache',
            'store' => 'array',
        ]);
    }
}
