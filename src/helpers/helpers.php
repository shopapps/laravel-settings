<?php

use Shopapps\LaravelSettings\Models\LaravelSetting as LarevelSettingModel;

declare(strict_types=1);


if (! function_exists('settings')) {
    function settings(string $key, $default=null, $global = true)
    {
        $key = 'settings_' . Str::slug($key);
        if(!$global) {
            $key .= \Illuminate\Support\Facades\Auth::id();
        }
        Cache::remember($key, function () use ($key, $default, $global) {
            return LarevelSettingModel::getSetting($key, $default, $global);
        }, config('laravel-settings.cache_ttl', 86400));
        
    }
}
