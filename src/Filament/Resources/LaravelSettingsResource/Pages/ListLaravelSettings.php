<?php

namespace Shopapps\LaravelSettings\Filament\Resources\LaravelSettingsResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Shopapps\LaravelSettings\Filament\Resources\LaravelSettingsResource;
use Shopapps\LaravelSettings\Models\LaravelSetting as LaravelSettingModel;
use Shopapps\LaravelSettings\Services\SettingService;

class ListLaravelSettings extends ListRecords
{
    protected static string $resource = LaravelSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateFormDataUsing(function (array $data): array {
                switch (data_get($data, 'type')) {
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
                        if (! is_array($data['value'])) {
                            if (config('laravel-settings.edit_mode') == 'text') {
                                $data['value'] = trim($data['value']);
                                $data['value'] = json_decode($data['value'], true);
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    dd('JSON Error: ' . json_last_error_msg());
                                }
                                $data['value'] = Arr::undot($data['value']);
                                $data['value'] = json_encode($data['value'], JSON_PRETTY_PRINT);
                            } else {
                                $data['value'] = array_map('trim', explode(',', $data['value']));
                                $data['value'] = json_encode($data['value']);
                            }
                        }

                        break;
                    case LaravelSettingModel::TYPE_STRING:
                    default:
                        $data['value'] = (string) $data['value'];
                        break;
                }

                return $data;
            }),

            ActionGroup::make([
                Action::make('view_cache')
                    ->label(__('View Cache'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(__('Pre-Cached Settings'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->modalContent(function (): HtmlString {
                        return $this->renderPreCacheContent();
                    }),

                Action::make('build_cache')
                    ->label(__('Build Cache'))
                    ->icon('heroicon-o-bolt')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('Build Settings Cache'))
                    ->modalDescription(__('This will pre-cache all database settings into a single cache entry for faster lookups.'))
                    ->action(function () {
                        $count = SettingService::make()->buildPreCache();

                        Notification::make()
                            ->title(__('Settings Cached'))
                            ->body(__(':count settings cached successfully.', ['count' => $count]))
                            ->success()
                            ->send();
                    }),

                Action::make('clear_cache')
                    ->label(__('Clear Pre-Cache'))
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('Clear Settings Pre-Cache'))
                    ->modalDescription(__('This will remove the pre-cached settings blob. Individual per-key caches will remain.'))
                    ->action(function () {
                        SettingService::make()->clearPreCache();

                        Notification::make()
                            ->title(__('Pre-Cache Cleared'))
                            ->body(__('The settings pre-cache has been cleared.'))
                            ->success()
                            ->send();
                    }),

                Action::make('clear_all_cache')
                    ->label(__('Clear All Cache'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('Clear All Settings Cache'))
                    ->modalDescription(__('This will remove all cached settings — both the pre-cache blob and individual per-key caches. Settings will be fetched from the database on next access.'))
                    ->action(function () {
                        SettingService::make()->clearAll();

                        Notification::make()
                            ->title(__('All Cache Cleared'))
                            ->body(__('All settings caches have been cleared.'))
                            ->success()
                            ->send();
                    }),
            ])
                ->label(__('Cache'))
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button(),
        ];
    }

    protected function renderPreCacheContent(): HtmlString
    {
        $blob = Cache::get(SettingService::PRE_CACHE_KEY);

        if ($blob === null) {
            return new HtmlString(
                '<div class="text-center py-8 text-gray-500 dark:text-gray-400">'
                . '<p class="text-lg font-medium">' . e(__('No pre-cache found')) . '</p>'
                . '<p class="text-sm mt-1">' . e(__('Run "Build Cache" to pre-cache all settings.')) . '</p>'
                . '</div>'
            );
        }

        $rows = '';
        foreach ($blob as $key => $value) {
            $displayValue = is_array($value) || is_object($value)
                ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : e((string) $value);

            $isComplex = is_array($value) || is_object($value);

            $valueHtml = $isComplex
                ? '<pre class="text-xs bg-gray-50 dark:bg-gray-800 rounded p-2 overflow-x-auto max-h-32 whitespace-pre-wrap">' . e($displayValue) . '</pre>'
                : '<span class="text-sm">' . $displayValue . '</span>';

            $rows .= '<tr class="border-b border-gray-200 dark:border-gray-700">'
                . '<td class="py-2 px-3 text-sm font-mono text-gray-900 dark:text-gray-100 align-top whitespace-nowrap">' . e($key) . '</td>'
                . '<td class="py-2 px-3 text-gray-700 dark:text-gray-300">' . $valueHtml . '</td>'
                . '</tr>';
        }

        $count = count($blob);

        return new HtmlString(
            '<div class="space-y-3">'
            . '<p class="text-sm text-gray-500 dark:text-gray-400">'
            . e(__(':count settings in pre-cache.', ['count' => $count]))
            . '</p>'
            . '<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">'
            . '<table class="w-full">'
            . '<thead><tr class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">'
            . '<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">' . e(__('Key')) . '</th>'
            . '<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">' . e(__('Value')) . '</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>'
        );
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
