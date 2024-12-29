<?php

namespace shopapps\LaravelSettings;

use shopapps\LaravelSettings\Commands\Permission;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelSettingsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'laravel-settings';

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-settings')
            ->hasConfigFile('laravel-settings')
            ->hasMigration('create_laravel_settings_table')
            ->hasTranslations()
            ->publishableProviderName('laravel-settings')
            ->publishesServiceProvider();
    }
}
