# Laravel Settings

**Laravel Settings** is a simple way to store and retrieve settings from the database, with built-in caching. It also ships with an optional FilamentPHP plugin for quick admin management.

## Installation

You can install the package via Composer:

```bash
composer require shopapps/laravel-settings
```

### Publish Migrations and Config

Publish the migration and run it:

```bash
php artisan vendor:publish --tag="settings-migrations"
php artisan migrate
```

Then publish the config file:

```bash
php artisan vendor:publish --tag="settings-config"
```
A file named `laravel-settings.php` will appear in your `config` folder. Tweak it as needed.

## Filament Admin Integration

If you're using Filament, you can optionally load the plugin:
```php

    // in App\Providers\Filament\AdminPanelProvider

    use Shopapps\LaravelSettings\LaravelSettingsPlugin;

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                // existing plugins...
                LaravelSettingsPlugin::make(),
            ]);
    }
```
## Usage

### Helper Functions

#### Retrieve a setting:
```php
$value = setting({key}, {value}, {user_id}, {save});
$value = setting('my.key', 'default_value');
# or...
$value = setting('my.key');

```
##### For the currently authenticated user:
```php
$value = setting('my.key', null, true);
# or ...
$value = setting('my.key', 'default_value', true);
```

##### For a specific user ID:
```php
$user_id = 1; // id of user you want the setting for

$value = setting('my.key', 'default_value', $user_id);
```

#### Add or update a setting:
```php
add_setting('my.key', 'some value');
```

##### User-specific:
```php
add_setting({key}, {value}, {type}, {user_id});
add_setting('my.key', 'some value', 'string', 123);
```
##### Or via `setting` helper (pass `true` to save):
```php
setting('my.key', 'some new value', true, true);
```
#### Delete a setting:

```php
delete_setting('my.key');
delete_setting('my.key', 123);
```
#### Clear cached settings:
```php
clear_settings();
```

This flushes all cached settings from Redis or your default cache store.

### Service Class

Under the hood, these helpers call methods on `Shopapps\LaravelSettings\Services\SettingService`. You can also use it directly:
```php
    use Shopapps\LaravelSettings\Services\SettingService;

    // get
    $value = SettingService::make()->get('my.key');

    // add/update
    SettingService::make()->add('my.key', 'some value');

    // delete
    SettingService::make()->delete('my.key');

    // clear all caches
    SettingService::make()->clearAll();
```

## Contributing

Feel free to submit pull requests or create issues for bug fixes and new features.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
