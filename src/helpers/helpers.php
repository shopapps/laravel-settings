<?php

use Shopapps\LaravelSettings\Models\LaravelSetting as LaravelSettingModel;

declare(strict_types=1);


if (! function_exists('settings')) {
    function settings(string $key, $default=null, $global = true)
    {
        $key = 'settings_' . Str::slug($key);
        if(!$global) {
            $key .= \Illuminate\Support\Facades\Auth::id();
        }
        \Illuminate\Support\Facades\Cache::remember($key, function () use ($key, $default, $global) {
            return LaravelSettingModel::getSetting($key, $default, $global);
        }, config('laravel-settings.cache_ttl', 86400));
        
    }
}
