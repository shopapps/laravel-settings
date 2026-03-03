<?php

namespace Shopapps\LaravelSettings\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Shopapps\LaravelSettings\Models\LaravelSetting as LaravelSettingModel;

class SettingService
{
    /**
     * The cache key used to store the pre-cached settings blob.
     */
    public const PRE_CACHE_KEY = 'laravel_settings_pre_cache';

    /**
     * The in-memory pre-cache blob, loaded once per request.
     *
     * - null  = not yet attempted to load
     * - false = attempted but no blob exists in cache
     * - array = the loaded blob
     */
    protected static array|false|null $preCacheBlob = null;

    /**
     * Singleton instance, so the in-memory blob survives across calls.
     */
    protected static ?self $singleton = null;

    public static function make(): static
    {
        if (static::$singleton === null) {
            static::$singleton = new static();
        }

        return static::$singleton;
    }

    public static function instance(): static
    {
        return static::make();
    }

    public function get($key, $default = null, $user_id = null)
    {
        $user_id = $this->handleUserId($user_id);

        // Check the in-memory pre-cache blob first (single array, zero I/O after first load).
        if (config('laravel-settings.pre_cache_settings')) {
            $blob = $this->loadPreCacheBlob();

            if ($blob !== false) {
                // Blob is loaded and authoritative — don't fall through to DB.
                return $this->resolveFromBlob($blob, $key, $user_id, $default);
            }
        }

        if (config('laravel-settings.cache')) {
            return Cache::remember(
                $this->getKey($key, $user_id),
                config('laravel-settings.cache_ttl', 86400),
                fn () => self::getModel()::getSetting($key, $default, $user_id)
            );
        }

        return self::getModel()::getSetting($key, $default, $user_id);
    }

    public function getKey($key, $user_id = false): string
    {
        $key = 'settings_' . Str::slug($key);

        return $key . $this->handleUserId($user_id);
    }

    public function add($key, $value, $type = null, $user_id = null)
    {
        if ($type === null) {
            $type = gettype($value);
        }

        $user_id = $this->handleUserId($user_id);

        $data = [
            'key' => $key,
            'type' => $type,
            'value' => $value,
            'user_id' => $user_id,
        ];

        $this->getModel()::updateOrCreate([
            'key' => $key,
            'user_id' => $user_id,
        ], $data);

        // Update the in-memory blob and cache in-place (no full rebuild needed).
        $this->updatePreCacheEntry($key, $value, $user_id);

        // Also clear the per-key cache so it doesn't serve stale data.
        Cache::forget($this->getKey($key, $user_id));

        return $value;
    }

    public function delete($key, $user_id = null)
    {
        $user_id = $this->handleUserId($user_id);

        Cache::forget($this->getKey($key, $user_id));
        $this->getQuery($key, $user_id)->delete();

        // Remove from the in-memory blob and persist.
        $this->removePreCacheEntry($key, $user_id);
    }

    public function getQuery($key, $user_id = null)
    {
        return $this->getModel()::query()
            ->where('key', $key)
            ->where('user_id', $this->handleUserId($user_id));
    }

    public function clearAll(): void
    {
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
            $settings = self::instance()->getModel()->query()->select('key', 'user_id')->get();
            foreach ($settings as $setting) {
                Cache::forget($this->getKey($setting->key, null));
                Cache::forget($this->getKey($setting->key, $setting->user_id));
            }
        }

        $this->clearPreCache();
    }

    /**
     * Build the pre-cache blob from all database settings.
     *
     * Stores all settings as a keyed array in a single cache entry,
     * similar to how Laravel's config:cache works.
     */
    public function buildPreCache(): int
    {
        $settings = $this->getModel()::query()->get();

        $blob = [];

        foreach ($settings as $setting) {
            $cacheKey = $this->buildPreCacheEntryKey($setting->key, $setting->user_id);
            $blob[$cacheKey] = $setting->value;
        }

        $this->persistBlob($blob);

        return count($blob);
    }

    /**
     * Clear the pre-cached settings blob from both cache and memory.
     */
    public function clearPreCache(): void
    {
        Cache::forget(self::PRE_CACHE_KEY);
        static::$preCacheBlob = null;
    }

    /**
     * Reset the singleton (useful for testing).
     */
    public static function reset(): void
    {
        static::$singleton = null;
        static::$preCacheBlob = null;
    }

    // ──────────────────────────────────────────────────────────────────
    // Pre-cache internals
    // ──────────────────────────────────────────────────────────────────

    /**
     * Load the pre-cache blob into memory (once per request).
     *
     * Returns the blob array, or false if no blob exists in cache.
     */
    protected function loadPreCacheBlob(): array|false
    {
        if (static::$preCacheBlob === null) {
            $cached = Cache::get(self::PRE_CACHE_KEY);
            static::$preCacheBlob = is_array($cached) ? $cached : false;
        }

        return static::$preCacheBlob;
    }

    /**
     * Resolve a value from the in-memory blob.
     *
     * The blob is authoritative: if loaded and the key isn't found,
     * $default is returned immediately (no fallthrough to DB).
     */
    protected function resolveFromBlob(array $blob, string $key, mixed $user_id, mixed $default): mixed
    {
        $cacheKey = $this->buildPreCacheEntryKey($key, $user_id);

        if (array_key_exists($cacheKey, $blob)) {
            return $blob[$cacheKey];
        }

        // Support dot-notation sub-key resolution.
        // e.g. "notifications.recipients" might match a cached "notifications" array key.
        $keyParts = explode('.', $key);

        if (count($keyParts) >= 2) {
            $tail = [];

            while (count($keyParts) > 0) {
                $lastSegment = array_pop($keyParts);
                array_unshift($tail, $lastSegment);

                $partialKey = implode('.', $keyParts);

                if (empty($partialKey)) {
                    break;
                }

                $partialCacheKey = $this->buildPreCacheEntryKey($partialKey, $user_id);

                if (array_key_exists($partialCacheKey, $blob)) {
                    $value = $blob[$partialCacheKey];

                    if (is_array($value)) {
                        $subValue = data_get($value, implode('.', $tail));

                        if ($subValue !== null) {
                            return $subValue;
                        }
                    }
                }
            }
        }

        // Blob is authoritative — key genuinely doesn't exist.
        return $default;
    }

    /**
     * Update a single entry in the pre-cache blob (in-place).
     */
    protected function updatePreCacheEntry(string $key, mixed $value, mixed $user_id): void
    {
        $blob = $this->loadPreCacheBlob();

        if ($blob === false) {
            return;
        }

        $cacheKey = $this->buildPreCacheEntryKey($key, $user_id);
        $blob[$cacheKey] = $value;

        $this->persistBlob($blob);
    }

    /**
     * Remove a single entry from the pre-cache blob.
     */
    protected function removePreCacheEntry(string $key, mixed $user_id): void
    {
        $blob = $this->loadPreCacheBlob();

        if ($blob === false) {
            return;
        }

        $cacheKey = $this->buildPreCacheEntryKey($key, $user_id);
        unset($blob[$cacheKey]);

        $this->persistBlob($blob);
    }

    /**
     * Write the blob to both cache and in-memory property.
     */
    protected function persistBlob(array $blob): void
    {
        $ttl = config('laravel-settings.pre_cache_ttl', 86400);

        Cache::put(self::PRE_CACHE_KEY, $blob, $ttl);
        static::$preCacheBlob = $blob;
    }

    /**
     * Build a unique key for a setting within the pre-cache blob.
     */
    protected function buildPreCacheEntryKey(string $key, mixed $user_id = null): string
    {
        $entry = $key;

        if ($user_id !== null) {
            $entry .= ':user:' . $user_id;
        }

        return $entry;
    }

    // ──────────────────────────────────────────────────────────────────
    // Infrastructure
    // ──────────────────────────────────────────────────────────────────

    public function getModel(): ?Model
    {
        return app(config('laravel-settings.models.laravel-setting', LaravelSettingModel::class));
    }

    public static function __callStatic($method, $args)
    {
        return static::instance()->$method(...$args);
    }

    private function handleUserId(mixed $user_id = null): mixed
    {
        if ($user_id === true) {
            $user_id = auth()->id();
        } elseif (! $user_id || $user_id === false) {
            $user_id = null;
        }

        return $user_id;
    }
}
