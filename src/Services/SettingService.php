<?php

namespace Shopapps\LaravelSettings\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
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
    public const PRE_CACHE_VERSION_KEY = 'laravel_settings_pre_cache_version';

    /**
     * The in-memory pre-cache blob, loaded once per request.
     *
     * - null  = not yet attempted to load
     * - false = attempted but no blob exists in cache
     * - array = the loaded blob
     */
    protected static array|false|null $preCacheBlob = null;
    protected static string|int|null $preCacheVersion = null;

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

        $this->getQuery($key, $user_id)->delete();
        $this->forgetEntry($key, $user_id);
    }

    /**
     * Sync a single setting value into all caches.
     */
    public function syncEntry(string $key, mixed $value, mixed $user_id = null): void
    {
        $user_id = $this->handleUserId($user_id);

        Cache::forget($this->getKey($key, $user_id));
        $this->updatePreCacheEntry($key, $value, $user_id);
    }

    /**
     * Remove a single setting value from all caches.
     */
    public function forgetEntry(string $key, mixed $user_id = null): void
    {
        $user_id = $this->handleUserId($user_id);

        Cache::forget($this->getKey($key, $user_id));
        $this->removePreCacheEntry($key, $user_id);
    }

    /**
     * Delete all settings whose key starts with the given prefix.
     *
     * @return int Number of rows deleted.
     */
    public function deleteByPrefix(string $prefix, $user_id = null): int
    {
        $user_id = $this->handleUserId($user_id);

        $query = $this->getModel()::query()
            ->where(function ($q) use ($prefix) {
                $q->where('key', $prefix)
                    ->orWhere('key', 'like', $prefix.'.%');
            });

        if ($user_id !== null) {
            $query->where('user_id', $user_id);
        } else {
            $query->whereNull('user_id');
        }

        // Clear per-key caches for each matching row.
        $keys = $query->pluck('key');
        foreach ($keys as $key) {
            Cache::forget($this->getKey($key, $user_id));
        }

        $count = $query->delete();

        // Rebuild the pre-cache blob to reflect the deletions.
        if ($this->loadPreCacheBlob() !== false) {
            $this->buildPreCache();
        }

        return $count;
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
     *
     * After loading all DB rows, derives parent keys from dot-notated
     * children so that e.g. setting('reports.tenants') reconstructs
     * the array from 'reports.tenants.0', 'reports.tenants.1', etc.
     */
    public function buildPreCache(): int
    {
        $settings = $this->getModel()::query()->get();

        $blob = [];

        foreach ($settings as $setting) {
            $cacheKey = $this->buildPreCacheEntryKey($setting->key, $setting->user_id);
            $blob[$cacheKey] = $setting->value;
        }

        // Derive parent keys from dot-notated children.
        $blob = $this->deriveParentKeys($blob);

        $this->persistBlob($blob);

        return count($blob);
    }

    /**
     * Derive parent keys from dot-notated entries in the blob.
     *
     * Given entries like 'a.b.c.0' => 'x', 'a.b.c.1' => 'y', 'a.b.enabled' => true,
     * this adds: 'a.b.c' => ['x', 'y'], 'a.b' => ['c' => [...], 'enabled' => true], 'a' => [...]
     */
    protected function deriveParentKeys(array $blob): array
    {
        // Collect all dotted keys (keys with at least one dot segment).
        $dottedKeys = array_filter(array_keys($blob), fn ($k) => str_contains($k, '.'));

        if (empty($dottedKeys)) {
            return $blob;
        }

        // Build a nested structure using Arr::undot, then flatten all
        // intermediate levels back into the blob.
        // If both "a.b" and "a.b.c" exist as DB rows, treat "a.b" as authoritative
        // and ignore "a.b.c" for parent derivation to avoid stale deep-leaf bleed-through.
        usort(
            $dottedKeys,
            fn (string $a, string $b): int => substr_count($a, '.') <=> substr_count($b, '.')
        );

        $flat = [];
        foreach ($dottedKeys as $key) {
            $segments = explode('.', $key);
            $hasAncestor = false;

            while (count($segments) > 1) {
                array_pop($segments);
                if (array_key_exists(implode('.', $segments), $blob)) {
                    $hasAncestor = true;
                    break;
                }
            }

            if ($hasAncestor) {
                continue;
            }

            $flat[$key] = $blob[$key];
        }

        $nested = Arr::undot($flat);

        // Walk the nested structure and add every intermediate node.
        $this->walkAndAddParents($nested, '', $blob);

        return $blob;
    }

    /**
     * Recursively walk a nested array and add each intermediate node to the blob.
     */
    protected function walkAndAddParents(array $data, string $prefix, array &$blob): void
    {
        foreach ($data as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                // Add this intermediate key to the blob (won't overwrite a DB entry
                // that already exists at this exact key).
                if (! array_key_exists($fullKey, $blob)) {
                    $blob[$fullKey] = $value;
                }

                $this->walkAndAddParents($value, $fullKey, $blob);
            }
        }
    }

    /**
     * Clear the pre-cached settings blob from both cache and memory.
     */
    public function clearPreCache(): void
    {
        Cache::forget(self::PRE_CACHE_KEY);
        Cache::forget(self::PRE_CACHE_VERSION_KEY);
        static::$preCacheBlob = null;
        static::$preCacheVersion = null;
    }

    /**
     * Reset the singleton (useful for testing).
     */
    public static function reset(): void
    {
        static::$singleton = null;
        static::$preCacheBlob = null;
        static::$preCacheVersion = null;
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
        $cachedVersion = Cache::get(self::PRE_CACHE_VERSION_KEY);

        if (static::$preCacheBlob === null || static::$preCacheVersion !== $cachedVersion) {
            $cached = Cache::get(self::PRE_CACHE_KEY);
            static::$preCacheBlob = is_array($cached) ? $cached : false;
            static::$preCacheVersion = $cachedVersion;
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

        // Re-derive parent keys so intermediate lookups stay correct.
        $blob = $this->deriveParentKeys($blob);

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

        // Re-derive parent keys to remove stale intermediate entries.
        // First strip all derived (non-DB) keys, then re-derive.
        $dbKeys = $this->getModel()::query()->pluck('key')->all();
        $blob = array_intersect_key($blob, array_flip($dbKeys));
        $blob = $this->deriveParentKeys($blob);

        $this->persistBlob($blob);
    }

    /**
     * Write the blob to both cache and in-memory property.
     */
    protected function persistBlob(array $blob): void
    {
        $ttl = config('laravel-settings.pre_cache_ttl', 86400);
        $version = (string) microtime(true);

        Cache::put(self::PRE_CACHE_KEY, $blob, $ttl);
        Cache::put(self::PRE_CACHE_VERSION_KEY, $version, $ttl);
        static::$preCacheBlob = $blob;
        static::$preCacheVersion = $version;
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
