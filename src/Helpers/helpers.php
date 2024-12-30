<?php
declare(strict_types=1);

use Shopapps\LaravelSettings\Models\LaravelSetting as LaravelSettingModel;
use \Illuminate\Support\Facades\Cache;
use \Illuminate\Support\Facades\Auth;


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
    function setting(string $key, $default=null, $global = true, $save = false) : mixed
    {
        $cache_key = 'settings_' . Str::slug($key);
        if(!$global) {
            $cache_key .= Auth()?->id();
        }
        $value =  Cache::remember(
            $cache_key,
            config('laravel-settings.cache_ttl', 86400),
            fn () => LaravelSettingModel::getSetting($key, $default, $global)
        );
        
        if($save) {
            add_setting($key, $value, $global);
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
    function add_setting($key, $value, $global = true) {
        $data = [
            'key' => $key,
            'value' => $value,
        ];
        if(!$global) {
            $data['user_id'] = Auth()->id;
        }
        LaravelSettingModel::updateOrCreate([
            'key' => $key,
            'user_id' => data_get($data, 'user_id'),
        ], $data);
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
        if (config('cache.default') === 'redis') {
            $redisConnection = Redis::connection(
                config('cache.stores.redis.connection') ?? null
            );
            $prefix = config('cache.stores.redis.prefix') ?: '';
            $cursor = '0';
            do {
                [$cursor, $keys] = $redisConnection->scan($cursor, [
                    'MATCH' => $prefix . 'settings_*',
                    'COUNT' => 100,
                ]);
                foreach ($keys as $key) {
                    $redisConnection->del($key);
                }
            } while ($cursor !== '0');
        } else {
            // fall back to looping through the keys in the DB if not using redis
            $settings = LaravelSettingModel::select('key', 'user_id')->get();
            foreach ($settings as $setting) {
                $baseKey = 'settings_' . Str::slug($setting->key);
                Cache::forget($baseKey);
                if ($setting->user_id) {
                    Cache::forget($baseKey . $setting->user_id);
                }
            }
        }
    }
}
