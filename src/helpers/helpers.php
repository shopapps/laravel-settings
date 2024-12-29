<?php
declare(strict_types=1);

use Shopapps\LaravelSettings\Models\LaravelSetting as LaravelSettingModel;
use \Illuminate\Support\Facades\Cache;


if (! function_exists('setting')) {
    function setting(string $key, $default=null, $global = true)
    {
        $cache_key = 'settings_' . Str::slug($key);
        if(!$global) {
            $cache_key .= Auth()?->id();
        }
        return Cache::remember(
            $cache_key,
            config('laravel-settings.cache_ttl', 86400),
            fn () => LaravelSettingModel::getSetting($key, $default, $global)
        );
    }
}

if(! function_exists('clear_settings')) {
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
