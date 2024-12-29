<?php

namespace Shopapps\LaravelSettings\Resources;

use Filament\Forms\Components\Textarea;
use Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages\CreateLaravelSetting;
use Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages\EditLaravelSetting;
use Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages\ListLaravelSettings;
use Shopapps\LaravelSettings\Resources\LaravelSettingsResource\Pages\ViewLaravelSetting;
use Shopapps\LaravelSettings\Resources\LaravelSettingsResource\RelationManager\RoleRelationManager;
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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
        return config('laravel-settings.models.laravel-setting', LaravelSetting::class);
    }

    public static function getLabel(): string
    {
        return __('laravel-settings::filament-spatie.section.permission');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(config('laravel-settings.navigation_section_group', 'laravel-settings::filament-spatie.section.roles_and_permissions'));
    }

    public static function getNavigationSort(): ?int
    {
        return  config('laravel-settings.sort.permission_navigation');
    }

    public static function getPluralLabel(): string
    {
        return __('laravel-settings::filament-spatie.section.permissions');
    }

    public static function getCluster(): ?string
    {
        return config('laravel-settings.clusters.permissions', null);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('key')
                                ->label(__('laravel-settings::laravel-settings.field.key'))
                                ->required(),
                            Select::make('type')
                                ->label(__('laravel-settings::laravel-settings.field.type'))
                                ->options(self::getModel()::TYPES)
                                ->live()
//                                ->afterStateUpdated(fn(Set $set) => $set('roles', null))
                                ->required(),
                            Textarea::make('value')
                                ->rows(4)
                                ->label(__('laravel-settings::laravel-settings.field.value'))
                                ->required(),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('key')
                    ->label(__('laravel-settings::laravel-settings.field.key'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('laravel-settings::laravel-settings.field.type')),
                TextColumn::make('value')
                    ->label(__('laravel-settings::laravel-settings.field.value')),
            ])
            ->filters([
                //
            ])->actions([
                Tables\Actions\EditAction::make(),
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
        if (config('laravel-settings.should_use_simple_modal_resource.permissions')) {
            return [
                'index' => ListLaravelSettings::route('/'),
            ];
        }

        return [
            'index' => ListLaravelSettings::route('/'),
            'create' => CreateLaravelSetting::route('/create'),
            'edit' => EditLaravelSetting::route('/{record}/edit'),
            'view' => ViewLaravelSetting::route('/{record}'),
        ];
    }
}
