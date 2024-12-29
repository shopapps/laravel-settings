<?php

namespace Shopapps\LaravelSettings\Resources\PermissionResource\Pages;

use Shopapps\LaravelSettings\Resources\LaravelSettingsResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLaravelSetting extends ViewRecord
{
    protected static string $resource = LaravelSettingsResource::class;

    public function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
