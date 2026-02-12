<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentGoogleWorkspaceAuthServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-google-workspace-auth';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasRoutes('filament-google-workspace-auth')
            ->hasMigrations($this->getMigrations())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('centivadev/filament-google-workspace-auth');
            });
    }

    public function packageRegistered(): void {}

    public function packageBooted(): void
    {
        //
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'add_google_fields_to_filament_users_table',
        ];
    }
}
