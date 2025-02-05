<?php

namespace shopapps\LaravelSettings;

use Filament\Contracts\Plugin;
use Filament\Panel;

class LaravelSettingsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'laravel-settings';
    }

    public function register(Panel $panel): void
    {
        $panel->resources(config('laravel-settings.resources'));
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
