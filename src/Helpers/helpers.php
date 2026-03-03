<?php
declare(strict_types=1);

use Shopapps\LaravelSettings\Models\LaravelSetting as LaravelSettingModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Shopapps\LaravelSettings\Services\SettingService;
use Illuminate\Support\Str;

if (! function_exists('settings')) {
    function settings(string $key, $default = null, $user_id = null, $save = false): mixed
    {
        return setting($key, $default, $user_id, $save);
    }
}

if (! function_exists('setting')) {
    /**
     * Retrieve or save a setting value.
     *
     * Acts as a drop-in replacement for config() when reading values:
     * returns the database value if it exists, otherwise falls back to
     * config($key), and finally returns $default.
     *
     * @param string     $key      The dot-notation key.
     * @param mixed|null $default  Default value when neither DB nor config has the key.
     * @param mixed|null $user_id  User ID, true for authenticated user, or null for global.
     * @param bool       $save     Whether to persist the resolved value back to the database.
     *
     * @return mixed
     */
    function setting(string $key, $default = null, $user_id = null, $save = false): mixed
    {
        $value = SettingService::make()->get($key, null, $user_id);

        if ($value === null && func_num_args() <= 1) {
            $value = config($key);
        }

        if ($value === null) {
            $value = $default;
        }

        if ($save) {
            $type = gettype($value);
            SettingService::make()->add($key, $value, $type, $user_id);
        }

        return $value;
    }
}

if (! function_exists('setting_add')) {
    /**
     * Add or update a setting in the database.
     *
     * @param string      $key     The unique key identifying the setting.
     * @param mixed       $value   The value to associate with the key.
     * @param string|null $type    The value type (auto-detected when null).
     * @param mixed       $user_id User ID, true for authenticated user, or null for global.
     *
     * @return mixed The stored value.
     */
    function setting_add($key, $value, $type = null, $user_id = null): mixed
    {
        return SettingService::make()->add($key, $value, $type, $user_id);
    }
}

if (! function_exists('setting_clear_cache')) {
    /**
     * Clear all application settings from cache.
     *
     * Removes all cached settings stored in Redis or the default cache store.
     *
     * @return void
     */
    function setting_clear_cache(): void
    {
        SettingService::make()->clearAll();
    }
}

if (! function_exists('setting_delete')) {
    /**
     * Delete a setting from the database and cache.
     *
     * @param string     $key     The key of the setting to delete.
     * @param mixed|null $user_id User ID, true for authenticated user, or null for global.
     *
     * @return void
     */
    function setting_delete($key, $user_id = null): void
    {
        SettingService::make()->delete($key, $user_id);
    }
}

/*
|--------------------------------------------------------------------------
| Backward-compatible aliases (deprecated)
|--------------------------------------------------------------------------
|
| These aliases map the old function names to the new ones.
| They will be removed in a future major version.
|
*/

if (! function_exists('add_setting')) {
    /** @deprecated Use setting_add() instead. */
    function add_setting($key, $value, $type = null, $user_id = true)
    {
        return setting_add($key, $value, $type, $user_id);
    }
}

if (! function_exists('clear_settings')) {
    /** @deprecated Use setting_clear_cache() instead. */
    function clear_settings(): void
    {
        setting_clear_cache();
    }
}

if (! function_exists('delete_setting')) {
    /** @deprecated Use setting_delete() instead. */
    function delete_setting($key, $user_id = null): void
    {
        setting_delete($key, $user_id);
    }
}

