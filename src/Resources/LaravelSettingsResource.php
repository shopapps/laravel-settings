<?php

namespace Shopapps\LaravelSettings\Resources;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages\CreateLaravelSetting;
use Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages\EditLaravelSetting;
use Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages\ListLaravelSettings;
use Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages\ViewLaravelSetting;
use Shopapps\LaravelSettings\Resources\LaravelSettingsResource\RelationManager\RoleRelationManager;
use Shopapps\LaravelSettings\Models\LaravelSetting;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
        return  config('settings::laravel-settings.sort.permission_navigation');
    }

    public static function getPluralLabel(): string
    {
        return __('settings::laravel-settings.section.settings');
    }

    public static function getCluster(): ?string
    {
        return config('laravel-settings.clusters.settings', null);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('key')
                                ->label(__('settings::laravel-settings.field.key'))
                                ->required(),
                            Select::make('type')
                                ->label(__('settings::laravel-settings.field.type'))
                                ->options(self::getModel()::TYPES)
                                ->live()
//                                ->afterStateUpdated(fn(Set $set) => $set('roles', null))
                                ->required(),
                            Grid::make(1)->columnSpan(2)->schema([
                                TextInput::make('value')
                                    ->label(__('settings::laravel-settings.field.value'))
                                    ->required()
                                    ->visible(fn(Get $get) => $get('type') !== LaravelSetting::TYPE_ARRAY && $get('type') !== LaravelSetting::TYPE_OBJECT && $get('type') !== LaravelSetting::TYPE_BOOLEAN),
                                Toggle::make('value')
                                    ->label(__('settings::laravel-settings.field.value'))
                                    ->required()
                                    ->visible(fn(Get $get) => $get('type') === LaravelSetting::TYPE_BOOLEAN),
                                ]),
                                KeyValue::make('value')
                                    ->columnSpan(2)
                                    ->label(__('settings::laravel-settings.field.value'))
                                    ->addActionLabel(__('settings::laravel-settings.add_value'))
                                    ->formatStateUsing(fn($state, Model $record) => $record->value)
                                    ->required()
                                    ->visible(fn(Get $get) => $get('type') === LaravelSetting::TYPE_ARRAY || $get('type') === LaravelSetting::TYPE_OBJECT),
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
                    ->label(__('settings::laravel-settings.field.key'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('settings::laravel-settings.field.type')),
                TextColumn::make('value')
                    ->label(__('settings::laravel-settings.field.value')),
            ])
            ->filters([
                //
            ])->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function(array $data): array {
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
                            case LaravelSetting::TYPE_OBJECT:
                            case LaravelSetting::TYPE_ARRAY:
                                if(!is_array($data['value'])) {
                                    // legacy when usoing textarea and comma delimited list, explode and trim
                                    $data['value'] = array_map('trim', explode(',', $data['value']));
                                }
                                $data['value'] =  json_encode($data['value']);
                                break;
                            case LaravelSetting::TYPE_STRING:
                            default:
                                $data['value'] = (string) $data['value'];
                                break;
                        }
                        return $data;
                    }),
                Tables\Actions\ViewAction::make(),
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
