<?php

namespace Shopapps\LaravelSettings\Filament\Resources;

use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Shopapps\LaravelSettings\Filament\Resources\LaravelSettingsResource\Pages\CreateLaravelSetting;
use Shopapps\LaravelSettings\Filament\Resources\LaravelSettingsResource\Pages\EditLaravelSetting;
use Shopapps\LaravelSettings\Filament\Resources\LaravelSettingsResource\Pages\ListLaravelSettings;
use Shopapps\LaravelSettings\Filament\Resources\LaravelSettingsResource\Pages\ViewLaravelSetting;
use Shopapps\LaravelSettings\Models\LaravelSetting;
use Shopapps\LaravelSettings\Resources\LaravelSettingsResource\RelationManager\RoleRelationManager;
use Shopapps\LaravelSettings\Services\SettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;


class LaravelSettingsResource extends Resource
{

    public static function isScopedToTenant(): bool
    {
        return config('laravel-settings.scope_to_tenant', true);
    }

    public static function getNavigationIcon(): ?string
    {
        return  config('laravel-settings.icons.settings_navigation');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return config('laravel-settings.should_register_on_navigation.settings', true);
    }

    public static function getModel(): string
    {
        return config('settings::laravel-settings.models.laravel-setting', LaravelSetting::class);
    }

    public static function getLabel(): string
    {
        return __('settings::laravel-settings.section.settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(config('settings::laravel-settings.navigation_section_group', 'settings::laravel-settings.section.settings'));
    }

    public static function getNavigationSort(): ?int
    {
        return  config('settings::laravel-settings.sort.settings_navigation');
    }

    public static function getPluralLabel(): string
    {
        return __('settings::laravel-settings.section.settings');
    }



    public static function canAccess(): bool
    {
        // default mode is to allow access unless some level of security is applied
        if(!config('laravel-settings.access_control.enabled')) {
            return true;
        }

        /*
         * Access Control is enabled so now return false unless the user is allowed
         */
        if(config('laravel-settings.access_control.spatie.enabled')) {
            if(auth()->user()?->hasPermissionTo(config('laravel-settings.access_control.spatie.permission'))) {
                return true;
            }
        }


        if(in_array(auth()->user()?->email, config('laravel-settings.access_control.allowed.emails'))) {
            return true;
        }

        if(in_array(auth()->user()?->id, config('laravel-settings.access_control.allowed.user_ids'))) {
            return true;
        }

        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('key')
                                ->label(__('settings::laravel-settings.field.value.key'))
                                ->required(),
                            Select::make('type')
                                ->label(__('settings::laravel-settings.field.value.type'))
                                ->options(self::getModel()::TYPES)
                                ->live()
//                                ->afterStateUpdated(fn(Set $set) => $set('roles', null))
                                ->required(),
                            Grid::make(1)->columnSpan(2)->schema([
                                TextInput::make('value')
                                    ->label(__('settings::laravel-settings.field.value.value'))
                                    ->required()
                                    ->visible(fn(Get $get) => $get('type') !== LaravelSetting::TYPE_ARRAY && $get('type') !== LaravelSetting::TYPE_OBJECT && $get('type') !== LaravelSetting::TYPE_BOOLEAN),
                                Toggle::make('value')
                                    ->label(__('settings::laravel-settings.field.value.value'))
                                    ->required()
                                    ->visible(fn(Get $get) => $get('type') === LaravelSetting::TYPE_BOOLEAN),
                            ]),
                            KeyValue::make('value')
                                ->columnSpan(2)
                                ->label(__('settings::laravel-settings.field.value.value'))
                                ->addActionLabel(__('settings::laravel-settings.add_value'))
                                ->formatStateUsing(fn($state, ?Model $record) => $record?->value)
                                ->required()
                                ->visible(fn(Get $get) => ($get('type') === LaravelSetting::TYPE_ARRAY || $get('type') === LaravelSetting::TYPE_OBJECT) && config('laravel-settings.edit_mode') == 'simple'),

                            Textarea::make('value')
                                ->columnSpan(2)
                                ->rows(10)
                                ->label(__('settings::laravel-settings.field.value.json'))
                                ->hint(__('settings::laravel-settings.field.value.hint.json'))
                                ->rules(['json'])

                                ->formatStateUsing(function($state, ?Model $record) {
                                    return $record?->pretty_value;
                                })
                                ->required()
                                ->visible(fn(Get $get) => ($get('type') === LaravelSetting::TYPE_ARRAY || $get('type') === LaravelSetting::TYPE_OBJECT) && config('laravel-settings.edit_mode') == 'text'),
                        ]),
                    ]),
            ])
            ;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('key')
                    ->label(__('settings::laravel-settings.field.value.key'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('settings::laravel-settings.field.value.type')),
                TextColumn::make('value')
                    ->label(__('settings::laravel-settings.field.value.value')),
            ])
            ->filters([
                TrashedFilter::make(),
            ])->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function(array $data, Model $record): array {
                        switch(data_get($data, 'type')) {
                            case LaravelSetting::TYPE_BOOLEAN:
                                $data['value'] = (bool) $data['value'];
                                break;
                            case LaravelSetting::TYPE_INTEGER:
                                $data['value'] = (int) $data['value'];
                                break;
                            case LaravelSetting::TYPE_FLOAT:
                                $data['value'] = (float) $data['value'];
                                break;
                            case LaravelSetting::TYPE_ARRAY:

                                if(config('laravel-settings.edit_mode') == 'text') {
                                    // legacy when using textarea and php array string format
                                    // nothing to do aas it will be json anyway
//                                    $data['value'] = $record->parsePhpArrayString($data['value']);
//
//                                    dd($data['value']);
                                } else {
                                    // starts as a comma delimited list, explode and trim
//                                    $data['value'] = array_map('trim', explode(',', $data['value']));
                                    $data['value'] =  json_encode($data['value']);
                                }

                                break;
                            case LaravelSetting::TYPE_STRING:
                            default:
                                $data['value'] = (string) $data['value'];
                                break;
                        }
                        return $data;
                    }),
                TableAction::make('test_setting')
                    ->label(__('Test'))
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->modalHeading(fn (Model $record): string => __('Test Setting: :key', ['key' => $record->key]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->modalContent(function (Model $record): HtmlString {
                        $key = $record->key;

                        // Measure the time to retrieve the setting value.
                        $start = hrtime(true);
                        $value = setting($key);
                        $elapsed = (hrtime(true) - $start) / 1_000_000; // ms

                        $source = Cache::has(SettingService::PRE_CACHE_KEY) ? 'pre-cache' : 'per-key cache / DB';

                        $displayValue = is_array($value) || is_object($value)
                            ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                            : (is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? 'null'));

                        $isComplex = is_array($value) || is_object($value);

                        $valueHtml = $isComplex
                            ? '<pre class="text-xs bg-gray-50 dark:bg-gray-800 rounded p-3 overflow-x-auto whitespace-pre-wrap font-mono">' . e($displayValue) . '</pre>'
                            : '<span class="text-sm font-mono bg-gray-50 dark:bg-gray-800 rounded px-2 py-1">' . e($displayValue) . '</span>';

                        $timeColor = $elapsed < 1 ? 'text-green-600 dark:text-green-400' : ($elapsed < 10 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400');

                        return new HtmlString(
                            '<div class="space-y-4">'
                            . '<div class="grid grid-cols-2 gap-4">'
                            . '<div>'
                            . '<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">' . e(__('Key')) . '</p>'
                            . '<p class="text-sm font-mono mt-1">' . e($key) . '</p>'
                            . '</div>'
                            . '<div>'
                            . '<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">' . e(__('Type')) . '</p>'
                            . '<p class="text-sm mt-1">' . e($record->type ?? gettype($value)) . '</p>'
                            . '</div>'
                            . '<div>'
                            . '<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">' . e(__('Source')) . '</p>'
                            . '<p class="text-sm mt-1">' . e($source) . '</p>'
                            . '</div>'
                            . '<div>'
                            . '<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">' . e(__('Time')) . '</p>'
                            . '<p class="text-sm mt-1 font-mono ' . $timeColor . '">' . number_format($elapsed, 3) . ' ms</p>'
                            . '</div>'
                            . '</div>'
                            . '<div>'
                            . '<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">' . e(__('Value')) . '</p>'
                            . $valueHtml
                            . '</div>'
                            . '</div>'
                        );
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        if (config('laravel-settings.should_use_simple_modal_resource.settings')) {
            return [
                'index' => ListLaravelSettings::route('/'),
            ];
        }

        return [
            'index'  => ListLaravelSettings::route('/'),
            'create' => CreateLaravelSetting::route('/create'),
            'edit'   => EditLaravelSetting::route('/{record}/edit'),
            'view'   => ViewLaravelSetting::route('/{record}'),
        ];
    }



}
