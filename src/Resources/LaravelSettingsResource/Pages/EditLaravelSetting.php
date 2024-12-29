<?php

namespace Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages;

use Shopapps\LaravelSettings\Resources\LaravelSettingsResource;
use Filament\Resources\Pages\EditRecord;

class EditLaravelSetting extends EditRecord
{
    protected static string $resource = LaravelSettingsResource::class;

    protected function getRedirectUrl(): ?string
    {
        $resource = static::getResource();

        return config('filament-spatie-roles-permissions.should_redirect_to_index.settings.after_edit', false)
            ? $resource::getUrl('index')
            : parent::getRedirectUrl();
    }
}
