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
