<?php

namespace Shopapps\LaravelSettings;

use Shopapps\LaravelSettings\Commands\SettingsCache;
use Shopapps\LaravelSettings\Commands\SettingsClear;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelSettingsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'laravel-settings';
    public ?string $publishableProviderName = 'laravel-settings';

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-settings')
            ->hasConfigFile('laravel-settings')
            ->hasMigration('create_laravel_settings_table')
            ->hasTranslations()
            ->hasCommands([
                SettingsCache::class,
                SettingsClear::class,
            ]);
    }
}
