<?php

namespace Shopapps\LaravelSettings\Resources\PermissionResource\Pages;

use Shopapps\LaravelSettings\Resources\PermissionResource;
use Shopapps\LaravelSettings\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLaravelSetting extends CreateRecord
{
    protected static string $resource = PermissionResource::class;

    protected function getRedirectUrl(): string
    {
        $resource = static::getResource();

        return config('filament-spatie-roles-permissions.should_redirect_to_index.permissions.after_create', false)
            ? $resource::getUrl('index')
            : parent::getRedirectUrl();
    }
}
