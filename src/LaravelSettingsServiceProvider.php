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
            ->hasConfigFile()
            ->hasTranslations();
    }
}
