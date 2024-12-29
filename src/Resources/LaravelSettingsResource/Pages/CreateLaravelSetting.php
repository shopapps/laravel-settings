<?php

namespace Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages;

use Shopapps\LaravelSettings\Resources\LaravelSettingsResource;
use Shopapps\LaravelSettings\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLaravelSetting extends CreateRecord
{
    protected static string $resource = LaravelSettingsResource::class;

    protected function getRedirectUrl(): string
    {
        $resource = static::getResource();
        
        return config('laravel-settings.should_redirect_to_index.settings.after_create', false)
            ? $resource::getUrl('index')
            : parent::getRedirectUrl();
    }
}
