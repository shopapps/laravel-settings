## Installation

You can install the package via composer:

```bash
composer require shopapps/laravel-settings
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="settings-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="settings-config"
```

This is the contents of the published config file:

```php
return [
];
```


## To install into filamentphp admin interface.
Edit: App\Providers\Filament\AdminPanelProvider

add:

```php
// add this near top of file...
use use Shopapps\LaravelSettings\LaravelSettingsPlugin;

 ->plugins([
... existign plugins
LaravelSettingsPlugin::make(),
])
```

## To install into filamentphp admin interface.
Edit: App\Providers\Filament\AdminPanelProvider

add:

```php
// add this near top of file...
use use Shopapps\LaravelSettings\LaravelSettingsPlugin;

 ->plugins([
... existign plugins
LaravelSettingsPlugin::make(),
])
```

## Usage

```php
$variable = settings(key:'test.setting', default:'Hello World');
or just simply
$variable = setting('test.setting');
```

add keys and values into the database direct or add the plugin to filammentphp PanelProvider
