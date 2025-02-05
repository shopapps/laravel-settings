<?php

namespace Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages;

use Shopapps\LaravelSettings\Resources\LaravelSettingsResource;
use Shopapps\LaravelSettings\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\LaravelSetting as LaravelSettingModel;

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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // check the type of the data and convert it to the appropriate type
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
