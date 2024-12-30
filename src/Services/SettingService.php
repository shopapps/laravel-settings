<?php

namespace Shopapps\LaravelSettings\Services;

use Shopapps\LaravelSettings\Models\LaravelSetting as LaravelSettingModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;


class SettingService {

    public static function instance() {
        return new static();
    }
    public function get($key, $default = null, $user_id = null) {

        $user_id = $this->handleUserId($user_id);

        return Cache::remember($this->getKey($key, $user_id), function () use ($key, $default, $user_id) {
            return self::getModel()::getSetting($key, $default, $user_id);
        }, config('laravel-settings.cache_ttl', 86400));
    }

    public function getKey($key, $user_id = false) {
        $key = 'settings_' . Str::slug($key);
        return $key . $this->handleUserId($user_id);
    }

    public function add($key, $value, $type = null, $user_id = null) {
        if($type === null) {
            $type = gettype($value);
        }

        $data = [
            'key'     => $key,
            'type'    => $type,
            'value'   => $value,
            'user_id' => $this->handleUserId($user_id),
        ];

        $this->getModel()::updateOrCreate([
            'key'     => $key,
            'user_id' => $this->handleUserId($user_id),
        ], $data);

        return $value;
    }

    public function delete($key, $user_id = null) {
        // delete from cache and from the db
        Cache::forget(self::getKey($key, $user_id));
        $this->getQuery($key, $user_id)->delete();
    }

    public function getQuery($key, $user_id = null) {
        return $this->getModel()::query()
            ->where('key', $key)
            ->where('user_id', $this->handleUserId($user_id));
    }

    public function clearAll() {
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
            $settings = self::instance()->getModel()->query()->select('key', 'user_id')->get();
            foreach ($settings as $setting) {
                Cache::forget($this->getKey($setting->key, null));
                Cache::forget($this->getKey($setting->key, $setting->user_id));

            }
        }
    }

    public function getModel() : Model {
        return config('laravel-settings.models.laravel-setting', LaravelSettingModel::class);
    }

    // convert static calls to these methods to instance calls
    public static function __callStatic($method, $args) {
        return self::instance()->$method(...$args);
    }

    private function handleUserId(mixed $user_id = null) {
        if ($user_id === true) {
            $user_id = auth()->id();
        } elseif (!$user_id || $user_id === false) {
            $user_id = null;
        }
        return $user_id;
    }
}
