<?php

namespace Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages;

use Shopapps\LaravelSettings\Resources\LaravelSettingsResource;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Shopapps\LaravelSettings\Models\LaravelSetting as LaravelSettingModel;

class ListLaravelSettings extends ListRecords
{
    protected static string $resource = LaravelSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateFormDataUsing(function(array $data): array {
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
                            if(!is_array($data['value'])) {
                                // starts as a comma delimited list, explode and trim
                                $data['value'] = array_map('trim', explode(',', $data['value']));
                            }
                            $data['value'] =  json_encode($data['value']);
                            break;
                        case LaravelSettingModel::TYPE_STRING:
                        default:
                            $data['value'] = (string) $data['value'];
                            break;
                    }
                    return $data;
            }),
        ];
    }

    protected function getTableBulkActions(): array
    {
        $roleModel = config('permission.models.role');

        return [
            BulkAction::make('Attach Role')
                ->action(function (Collection $records, array $data): void {
                    foreach ($records as $record) {
                        $record->roles()->sync($data['role']);
                        $record->save();
                    }
                })
                ->form([
                    Select::make('role')
                        ->label(__('filament-spatie-roles-permissions::laravel-settings.field.role'))
                        ->options($roleModel::query()->pluck('name', 'id'))
                        ->required(),
                ])->deselectRecordsAfterCompletion(),
        ];

    }
}
