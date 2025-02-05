<?php

namespace Shopapps\LaravelSettings\Filament\Resources\LaravelSettingsResource\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Shopapps\LaravelSettings\Filament\Resources\LaravelSettingsResource;

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
