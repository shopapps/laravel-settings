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
```

run the migration:

```bash 
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="settings-config"
```
A file named `laravel-settings.php` will appear in your `config` folder. Tweak it as needed.

## Filament Admin Integration

If you're using Filament, you can optionally load the plugin:
```php

    // typically in App\Providers\Filament\AdminPanelProvider

    use Shopapps\LaravelSettings\LaravelSettingsPlugin; // put this near top of file

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                // ... existing plugins ...
                LaravelSettingsPlugin::make(), // <-- add this line
            ]);
    }
```
## Usage

### Helper Functions

#### Retrieve a setting — `setting()`

```php
// Single argument: returns DB value → config() fallback → null
$value = setting('my.key');

// With explicit default (no config fallback when a default is supplied)
$value = setting('my.key', 'default_value');
```

The `setting()` helper acts as a drop-in replacement for `config()` when reading values.
When called with **a single argument**, it will:

1. Check the database/cache for the key
2. Fall back to `config($key)` if the database value is `null`
3. Return `null` if neither has a value

When called with **two or more arguments**, the `$default` parameter is used directly — no config fallback occurs.

##### For the currently authenticated user:
```php
$value = setting('my.key', null, true);
$value = setting('my.key', 'default_value', true);
```

##### For a specific user ID:
```php
$value = setting('my.key', 'default_value', $user_id);
```

#### Add or update a setting — `setting_add()`
```php
setting_add('my.key', 'some value');
```

##### User-specific:
```php
setting_add('my.key', 'some value', 'string', 123);
```

##### Or via `setting` helper (pass `true` to save):
```php
setting('my.key', 'some new value', null, true);
```

#### Delete a setting — `setting_delete()`

```php
setting_delete('my.key');
setting_delete('my.key', 123);
```

#### Clear cached settings — `setting_clear_cache()`
```php
setting_clear_cache();
```

This flushes all cached settings from Redis or your default cache store.

### Deprecated Aliases

The following function names still work but are deprecated and will be removed in a future major version:

| Deprecated | Replacement |
|---|---|
| `add_setting()` | `setting_add()` |
| `delete_setting()` | `setting_delete()` |
| `clear_settings()` | `setting_clear_cache()` |

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


# Access Control
To restrict access to the Resource and Page publish the config and edit the config file or add the following to your .env file:
```bash
# enable access control
LARAVEL_SETTINGS_ACCESS_CONTROL_ENABLED=true
# using Spaties - Roles & Permissions - set the permission name default = 'laravel_settings.view'
LARAVEL_SETTINGS_SPATIE_PERMISSIONS_ACTIVE=true
LARAVEL_SETTINGS_SPATIE_PERMISSION="laravel_settings.view"
# alternatively restrict access only to a list of specific emails
LARAVEL_SETTINGS_ALLOWED_EMAILS="admin@test.com, admin2@test.com"
# or optional user_ids...
LARAVEL_SETTINGS_ALLOWED_USER_IDS="1,2,3,4"
```

## Contributing

Feel free to submit pull requests or create issues for bug fixes and new features.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
