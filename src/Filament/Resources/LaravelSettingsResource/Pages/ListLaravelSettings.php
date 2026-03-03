<?php

namespace Shopapps\LaravelSettings\Filament\Resources\LaravelSettingsResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
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
                                    dd('JSON Error: '.json_last_error_msg());
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

            Action::make('import_config')
                ->label(__('Import Config'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->modalHeading(__('Import Config Setting'))
                ->modalDescription(__('Resolve a config value (with all env() calls evaluated) and save it to the settings database for dynamic override.'))
                ->modalSubmitActionLabel(__('Import'))
                ->modalWidth('xl')
                ->form([
                    TextInput::make('config_key')
                        ->label(__('Config Key'))
                        ->placeholder('e.g. reports.msisdn_active_inactive')
                        ->helperText(__('Enter a dot-notation config key. The resolved value will be imported.'))
                        ->required()
                        ->live(debounce: 500),

                    Toggle::make('flatten')
                        ->label(__('Flatten into separate entries'))
                        ->helperText(__('When enabled, each top-level array key becomes its own setting row (e.g. "config.key.sub_key"). When disabled, the entire value is stored as a single entry.'))
                        ->default(true)
                        ->live()
                        ->visible(fn (callable $get): bool => filled($get('config_key')) && is_array(config($get('config_key')))),

                    Placeholder::make('preview')
                        ->label(__('Preview'))
                        ->content(fn (callable $get): HtmlString => new HtmlString(
                            $this->renderConfigPreview($get('config_key'), $get('flatten') ?? true)
                        ))
                        ->visible(fn (callable $get): bool => filled($get('config_key'))),
                ])
                ->action(function (array $data): void {
                    $configKey = $data['config_key'];
                    $flatten = $data['flatten'] ?? true;
                    $value = config($configKey);

                    if ($value === null) {
                        Notification::make()
                            ->title(__('Config Not Found'))
                            ->body(__('No config value exists for ":key".', ['key' => $configKey]))
                            ->danger()
                            ->send();

                        return;
                    }

                    $entries = $this->resolveImportEntries($configKey, $value, $flatten);
                    $imported = 0;
                    $updated = 0;

                    $model = config('laravel-settings.models.laravel-setting', LaravelSettingModel::class);

                    foreach ($entries as $entry) {
                        $existing = $model::withTrashed()
                            ->where('key', $entry['key'])
                            ->whereNull('user_id')
                            ->first();

                        if ($existing) {
                            if ($existing->trashed()) {
                                $existing->restore();
                            }
                            $existing->update(['type' => $entry['type'], 'value' => $entry['value']]);
                            $updated++;
                        } else {
                            $model::create([
                                'key' => $entry['key'],
                                'type' => $entry['type'],
                                'value' => $entry['value'],
                                'user_id' => null,
                            ]);
                            $imported++;
                        }

                        Cache::forget(SettingService::make()->getKey($entry['key']));
                    }

                    SettingService::make()->clearPreCache();

                    $parts = [];
                    if ($imported > 0) {
                        $parts[] = __(':count imported', ['count' => $imported]);
                    }
                    if ($updated > 0) {
                        $parts[] = __(':count updated', ['count' => $updated]);
                    }

                    Notification::make()
                        ->title(__('Config Imported'))
                        ->body(implode(', ', $parts).'.')
                        ->success()
                        ->send();
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

    /**
     * Resolve config value into importable entries.
     *
     * When flatten is true and the value is a multi-dimensional array,
     * each top-level key becomes its own setting row.
     *
     * @return array<int, array{key: string, type: string, value: string}>
     */
    protected function resolveImportEntries(string $configKey, mixed $value, bool $flatten): array
    {
        // Scalar or non-array — always a single entry.
        if (! is_array($value)) {
            return [$this->buildEntry($configKey, $value)];
        }

        // Flat array (no nested arrays) or flatten disabled — single entry.
        $hasNestedArrays = collect($value)->contains(fn ($v) => is_array($v));

        if (! $flatten || ! $hasNestedArrays) {
            return [$this->buildEntry($configKey, $value)];
        }

        // Multi-dimensional array with flatten enabled — one row per top-level key.
        $entries = [];

        foreach ($value as $subKey => $subValue) {
            $entries[] = $this->buildEntry("{$configKey}.{$subKey}", $subValue);
        }

        return $entries;
    }

    /**
     * Build a single import entry with auto-detected type and encoded value.
     *
     * @return array{key: string, type: string, value: string}
     */
    protected function buildEntry(string $key, mixed $value): array
    {
        $type = match (true) {
            is_array($value) => LaravelSettingModel::TYPE_ARRAY,
            is_bool($value) => LaravelSettingModel::TYPE_BOOLEAN,
            is_int($value) => LaravelSettingModel::TYPE_INTEGER,
            is_float($value) => LaravelSettingModel::TYPE_FLOAT,
            default => LaravelSettingModel::TYPE_STRING,
        };

        $storeValue = match ($type) {
            LaravelSettingModel::TYPE_ARRAY => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LaravelSettingModel::TYPE_BOOLEAN => $value ? '1' : '0',
            default => (string) $value,
        };

        return ['key' => $key, 'type' => $type, 'value' => $storeValue];
    }

    /**
     * Render a preview of the config value that will be imported.
     */
    protected function renderConfigPreview(?string $configKey, bool $flatten = true): string
    {
        if (blank($configKey)) {
            return '';
        }

        $value = config($configKey);

        if ($value === null) {
            return '<span class="text-sm text-red-500 dark:text-red-400">'
                .e(__('No config value found for this key.'))
                .'</span>';
        }

        $entries = $this->resolveImportEntries($configKey, $value, $flatten);

        if (count($entries) === 1) {
            return $this->renderSingleEntryPreview($entries[0]);
        }

        return $this->renderMultiEntryPreview($entries);
    }

    protected function renderSingleEntryPreview(array $entry): string
    {
        $displayValue = $entry['type'] === LaravelSettingModel::TYPE_ARRAY
            ? $entry['value']
            : e($entry['value']);

        $lines = substr_count($displayValue, "\n") + 1;

        if ($lines > 30) {
            $displayLines = implode("\n", array_slice(explode("\n", $displayValue), 0, 30));
            $displayValue = $displayLines."\n... (".($lines - 30).' more lines)';
        }

        return '<div class="space-y-2">'
            .'<div class="flex items-center gap-3 text-sm">'
            .'<span class="text-gray-500 dark:text-gray-400">'.e(__('Type:')).'</span>'
            .'<span class="font-medium">'.e($entry['type']).'</span>'
            .'</div>'
            .'<pre class="text-xs bg-gray-50 dark:bg-gray-800 rounded p-3 overflow-x-auto max-h-96 whitespace-pre-wrap font-mono border border-gray-200 dark:border-gray-700">'
            .e($displayValue)
            .'</pre>'
            .'</div>';
    }

    protected function renderMultiEntryPreview(array $entries): string
    {
        $rows = '';

        foreach ($entries as $entry) {
            $preview = $entry['type'] === LaravelSettingModel::TYPE_ARRAY
                ? Str::limit($entry['value'], 120)
                : $entry['value'];

            $rows .= '<tr class="border-b border-gray-200 dark:border-gray-700">'
                .'<td class="py-2 px-3 text-xs font-mono text-gray-900 dark:text-gray-100 align-top whitespace-nowrap">'.e($entry['key']).'</td>'
                .'<td class="py-2 px-3 text-xs text-gray-500 dark:text-gray-400 align-top">'.e($entry['type']).'</td>'
                .'<td class="py-2 px-3 text-xs text-gray-700 dark:text-gray-300 align-top font-mono break-all">'.e($preview).'</td>'
                .'</tr>';
        }

        return '<div class="space-y-2">'
            .'<p class="text-sm text-gray-500 dark:text-gray-400">'
            .e(__(':count entries will be created:', ['count' => count($entries)]))
            .'</p>'
            .'<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 max-h-96 overflow-y-auto">'
            .'<table class="w-full">'
            .'<thead class="sticky top-0"><tr class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">'
            .'<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">'.e(__('Key')).'</th>'
            .'<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">'.e(__('Type')).'</th>'
            .'<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">'.e(__('Value')).'</th>'
            .'</tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>'
            .'</div>'
            .'</div>';
    }

    protected function renderPreCacheContent(): HtmlString
    {
        $blob = Cache::get(SettingService::PRE_CACHE_KEY);

        if ($blob === null) {
            return new HtmlString(
                '<div class="text-center py-8 text-gray-500 dark:text-gray-400">'
                .'<p class="text-lg font-medium">'.e(__('No pre-cache found')).'</p>'
                .'<p class="text-sm mt-1">'.e(__('Run "Build Cache" to pre-cache all settings.')).'</p>'
                .'</div>'
            );
        }

        $rows = '';
        foreach ($blob as $key => $value) {
            $displayValue = is_array($value) || is_object($value)
                ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : e((string) $value);

            $isComplex = is_array($value) || is_object($value);

            $valueHtml = $isComplex
                ? '<pre class="text-xs bg-gray-50 dark:bg-gray-800 rounded p-2 overflow-x-auto max-h-32 whitespace-pre-wrap">'.e($displayValue).'</pre>'
                : '<span class="text-sm">'.$displayValue.'</span>';

            $rows .= '<tr class="border-b border-gray-200 dark:border-gray-700">'
                .'<td class="py-2 px-3 text-sm font-mono text-gray-900 dark:text-gray-100 align-top whitespace-nowrap">'.e($key).'</td>'
                .'<td class="py-2 px-3 text-gray-700 dark:text-gray-300">'.$valueHtml.'</td>'
                .'</tr>';
        }

        $count = count($blob);

        return new HtmlString(
            '<div class="space-y-3">'
            .'<p class="text-sm text-gray-500 dark:text-gray-400">'
            .e(__(':count settings in pre-cache.', ['count' => $count]))
            .'</p>'
            .'<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">'
            .'<table class="w-full">'
            .'<thead><tr class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">'
            .'<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">'.e(__('Key')).'</th>'
            .'<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">'.e(__('Value')).'</th>'
            .'</tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>'
            .'</div>'
            .'</div>'
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
