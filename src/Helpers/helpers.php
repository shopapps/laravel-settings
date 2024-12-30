<?php
declare(strict_types=1);

use Shopapps\LaravelSettings\Models\LaravelSetting as LaravelSettingModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Shopapps\LaravelSettings\Services\SettingService;
use Illuminate\Support\Str;


if (! function_exists('setting')) {
    /**
     * Retrieves a setting value from the cache or fetches it from the database if not cached.
     * Allows options for local/global scope and saving the value directly.
     *
     * @param string     $key     The key of the setting.
     * @param mixed|null $default The default value to return if the setting is not found.
     * @param bool       $global  Whether the setting applies globally or locally to the authenticated user. Defaults to true for global.
     * @param bool       $save    Whether to save the retrieved or updated setting value. Defaults to false.
     *
     * @return mixed The value of the setting.
     */
    function setting(string $key, $default=null, $user_id = null, $save = false) : mixed
    {
        $value = SettingService::get($key, $default, $user_id);
        if($save) {
            $type = gettype($value);
            SettingService::add($key, $value, $type, $user_id);
        }
        return $value;
    }
}

if(!function_exists('add_setting')) {
    /**
     * Add or update a setting in the database.
     *
     * @param string $key    The unique key identifying the setting.
     * @param mixed  $value  The value to associate with the key.
     * @param bool   $global Determines whether the setting is global or user-specific. Defaults to true for global settings.
     */
    function add_setting($key, $value, $type = null, $user_id = true) {
        return SettingService::add($key, $value, $type, $user_id);
    }
}

if(! function_exists('clear_settings')) {
    /**
     * Clear all application settings from cache.
     *
     * This function removes all cached settings stored in Redis or the default cache store.
     * If Redis is configured, it scans and deletes all keys matching the pattern `settings_*`.
     * Otherwise, it loops through the settings in the database and clears them from the default cache.
     *
     * @return void
     */
    function clear_settings() {
        // This uses Redis directly to scan and delete all keys matching settings_*
        SettingService::clearAll();
    }
}

if(! function_exists('delete_setting')) {
    /**
     * Delete a setting from the database and cache.
     *
     * @param string $key The key of the setting to delete.
     * @param bool $user_id The user_id of the setting to delete.
     *
     * @return void
     */
    function delete_setting($key, $user_id = null) {
        SettingService::delete($key, $user_id);
    }
}

