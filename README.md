# Laravel Settings

Store and retrieve application settings from the database with built-in caching and an optional FilamentPHP admin panel.

Settings work like Laravel's `config()` helper — use dot-notation keys, store any type (strings, booleans, arrays, JSON), and scope settings globally or per-user. A pre-cache system loads all settings into memory in a single read for near-zero latency.

## Requirements

- PHP 8.1+
- Laravel 9, 10, or 11
- FilamentPHP 3.x *(optional, for the admin panel)*

## Installation

```bash
composer require shopapps/laravel-settings
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag="settings-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="settings-config"
```

This creates `config/laravel-settings.php` where you can customise caching, access control, and table options.

---

## Quick Start

```php
// Read a setting (falls back to config(), then null)
$siteName = setting('site.name');

// Read with a default
$perPage = setting('pagination.per_page', 15);

// Write a setting
setting_add('site.name', 'My Application');

// Delete a setting
setting_delete('site.name');
```

---

## Usage

### Reading Settings — `setting()`

```php
// Returns: DB value → config() fallback → null
$value = setting('mail.from_address');

// With an explicit default (skips config fallback)
$value = setting('mail.from_address', 'hello@example.com');

// For the currently authenticated user
$value = setting('theme', 'light', true);

// For a specific user
$value = setting('theme', 'light', $userId);
```

**Dot-notation** keys are fully supported. If you store an array under the key `notifications`, you can access nested values:

```php
setting_add('notifications', ['email' => true, 'sms' => false], 'array');

// Read a nested value
$emailEnabled = setting('notifications.email'); // true
```

### Writing Settings — `setting_add()`

```php
// Simple string
setting_add('site.name', 'My App');

// Boolean
setting_add('maintenance_mode', true, 'boolean');

// Array / JSON
setting_add('mail.recipients', ['admin@example.com', 'ops@example.com'], 'array');

// User-specific setting
setting_add('theme', 'dark', 'string', $userId);
```

The type is auto-detected when omitted, but you can be explicit: `string`, `boolean`, `integer`, `float`, `array`, `object`.

### Deleting Settings — `setting_delete()`

```php
// Global setting
setting_delete('site.name');

// User-specific setting
setting_delete('theme', $userId);
```

### Clearing Cache — `setting_clear_cache()`

```php
// Flush all cached settings (pre-cache + per-key caches)
setting_clear_cache();
```

### Service Class

All helpers delegate to `SettingService`. You can use it directly:

```php
use Shopapps\LaravelSettings\Services\SettingService;

$service = SettingService::make(); // singleton instance

$value = $service->get('site.name');
$service->add('site.name', 'My App');
$service->delete('site.name');
$service->clearAll();
```

### Deprecated Aliases

These function names still work but will be removed in a future major version:

| Deprecated | Use Instead |
|---|---|
| `add_setting()` | `setting_add()` |
| `delete_setting()` | `setting_delete()` |
| `clear_settings()` | `setting_clear_cache()` |

---

## Caching

Laravel Settings has two caching layers that work together:

### Per-Key Cache *(enabled by default)*

Each setting is individually cached on first read. Fast for small numbers of settings, but each key is a separate cache call.

```env
LARAVEL_SETTINGS_CACHE=true
LARAVEL_SETTINGS_CACHE_TTL=86400
```

### Pre-Cache *(recommended for production)*

All database settings are loaded into a **single cache entry** and held **in memory** for the remainder of the request. This is similar to how `php artisan config:cache` works — one read, then pure PHP array lookups.

```env
LARAVEL_SETTINGS_PRE_CACHE=true
LARAVEL_SETTINGS_PRE_CACHE_TTL=86400
```

#### How It Works

1. On the first `setting()` call, the blob is loaded from the cache store into a static property (one I/O operation).
2. All subsequent calls read from the in-memory array — **no cache or database calls**.
3. The blob is **authoritative**: if a key isn't found in the blob, the default is returned immediately without falling through to the database.
4. When you `setting_add()` or `setting_delete()`, the blob is updated **in-place** and persisted — no full rebuild needed.

#### Performance

| Scenario | Typical Latency |
|---|---|
| No cache (direct DB query) | 15–30 ms |
| Per-key cache hit | 1–5 ms |
| Pre-cache — first call (loads blob) | < 0.01 ms |
| Pre-cache — subsequent calls (in-memory) | < 0.005 ms |

### Artisan Commands

Build or clear the pre-cache from the command line:

```bash
# Build the pre-cache (load all DB settings into a single cache entry)
php artisan settings:cache

# Clear the pre-cache blob only (per-key caches remain)
php artisan settings:clear
```

Add `php artisan settings:cache` to your deployment script alongside `config:cache` and `route:cache`:

```bash
php artisan config:cache
php artisan route:cache
php artisan settings:cache
```

---

## Filament Admin Panel

The package ships with a FilamentPHP resource for managing settings through the admin panel.

### Setup

Register the plugin in your panel provider:

```php
// app/Providers/Filament/AdminPanelProvider.php

use Shopapps\LaravelSettings\LaravelSettingsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            LaravelSettingsPlugin::make(),
        ]);
}
```

### Settings Table

The resource displays all settings in a searchable, filterable table with columns for key, value, type, and user scope.

### Cache Management Actions

A **⋮ Cache** dropdown in the page header provides four actions:

| Action | Description |
|---|---|
| **View Cache** | Opens a read-only modal showing all pre-cached key/value pairs |
| **Build Cache** | Loads all DB settings into the pre-cache blob |
| **Clear Pre-Cache** | Removes the pre-cache blob only; per-key caches remain |
| **Clear All Cache** | Removes everything — pre-cache blob and all per-key caches |

### Test Setting Action

Each row in the settings table has a **▶ Test** button that reads the setting via the `setting()` helper and displays:

- **Key** — the setting's key
- **Type** — the stored type (string, boolean, array, etc.)
- **Source** — whether the value was served from `pre-cache` or `per-key cache / DB`
- **Time** — retrieval time in milliseconds, colour-coded: 🟢 < 1 ms, 🟡 < 10 ms, 🔴 ≥ 10 ms
- **Value** — the resolved value (arrays shown as formatted JSON)

This is useful for verifying that caching is working and diagnosing performance.

---

## Configuration

All options can be set via environment variables or in `config/laravel-settings.php`:

### Caching

| Key | Env Variable | Default | Description |
|---|---|---|---|
| `cache_settings` | `LARAVEL_SETTINGS_CACHE` | `true` | Enable per-key caching |
| `cache_ttl` | `LARAVEL_SETTINGS_CACHE_TTL` | `86400` | Per-key cache TTL (seconds) |
| `pre_cache_settings` | `LARAVEL_SETTINGS_PRE_CACHE` | `true` | Enable pre-cache blob |
| `pre_cache_ttl` | `LARAVEL_SETTINGS_PRE_CACHE_TTL` | `86400` | Pre-cache blob TTL (seconds) |

### Access Control

Restrict who can access the Filament resource:

```env
# Enable access control
LARAVEL_SETTINGS_ACCESS_CONTROL_ENABLED=true

# Option 1: Spatie Roles & Permissions
LARAVEL_SETTINGS_SPATIE_PERMISSIONS_ACTIVE=true
LARAVEL_SETTINGS_SPATIE_PERMISSION="laravel_settings.view"

# Option 2: Restrict by email
LARAVEL_SETTINGS_ALLOWED_EMAILS="admin@example.com, ops@example.com"

# Option 3: Restrict by user ID
LARAVEL_SETTINGS_ALLOWED_USER_IDS="1,2,3"
```

### Display

| Key | Env Variable | Default | Description |
|---|---|---|---|
| `edit_mode` | `LARAVEL_SETTINGS_EDIT_MODE` | `text` | Array editing mode: `text` (textarea) or `simple` (key-value fields) |
| `scope_to_tenant` | — | `true` | Scope settings to the current Filament tenant |
| `navigation_section_group` | — | `Settings` | Navigation group label |

---

## Contributing

Feel free to submit pull requests or create issues for bug fixes and new features.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
