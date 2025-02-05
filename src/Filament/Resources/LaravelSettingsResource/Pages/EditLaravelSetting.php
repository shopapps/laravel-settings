<?php

namespace Shopapps\LaravelSettings\Filament\Resources\LaravelSettingsResource\Pages;

use App\Models\LaravelSetting as LaravelSettingModel;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Shopapps\LaravelSettings\Filament\Resources\LaravelSettingsResource;
use function Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages\config;
use function Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages\data_get;

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


    protected function mutateFormDataBeforeSave(array $data): array
    {
        switch(data_get($data, 'type')) {
            case LaravelSettingModel::TYPE_BOOLEAN:
                $data['value'] = (bool) $data['value'];
                break;
            case LaravelSettingModel::TYPE_INTEGER:
                $data['value'] = (int) $data['value'];
                break;
            case LaravelSettingModel::TYPE_FLOAT:
                $data['value'] = (float) $data['value'];
                break;
            case LaravelSettingModel::TYPE_ARRAY:
                // starts as a comma delimited list, explode and trim
                $data['value'] = array_map('trim', explode(',', $data['value']));
                $data['value'] =  json_encode($data['value']);
                break;
            case LaravelSettingModel::TYPE_OBJECT:
                $data['value'] = array_map('trim', explode(',', $data['value']));
                $data['value'] = (object) json_encode($data['value']);
                break;
            case LaravelSettingModel::TYPE_STRING:
            default:
                $data['value'] = (string) $data['value'];
                break;
        }
        return $data;
    }

}
