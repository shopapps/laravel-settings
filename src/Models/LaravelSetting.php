<?php
namespace Shopapps\LaravelSettings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaravelSetting extends Model {

    use SoftDeletes;

    protected $table = 'laravel_settings';

    protected $fillable = [
        'key',
        'type',
        'value',
        'user_id',
    ];

    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_FLOAT   = 'float';
    public const TYPE_ARRAY   = 'array';
    public const TYPE_OBJECT  = 'object';
    public const TYPE_STRING  = 'string';

    public const TYPES = [
        self::TYPE_BOOLEAN => self::TYPE_BOOLEAN,
        self::TYPE_INTEGER => self::TYPE_INTEGER,
        self::TYPE_FLOAT   => self::TYPE_FLOAT,
        self::TYPE_ARRAY   => self::TYPE_ARRAY,
        self::TYPE_OBJECT  => self::TYPE_OBJECT,
        self::TYPE_STRING  => self::TYPE_STRING,
    ];


    public function getTable()
    {
        return config('laravel-settings.table.name', parent::getTable());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getValueAttribute($value) : mixed
    {
        switch ($this->type) {
            case self::TYPE_BOOLEAN:
                return (bool) $value;
            case self::TYPE_INTEGER:
                return (int) $value;
            case self::TYPE_FLOAT:
                return (float) $value;
            case self::TYPE_ARRAY:
                if(is_array($value)) {
                    return $value;
                }
                return (array) json_decode($value, true);
            case self::TYPE_OBJECT:
                if(is_array($value)) {
                    return (object) $value;
                }
                return (object) json_decode($value);
            case self::TYPE_STRING:
            default:
                return (string) $value;
        }

        return $value;
    }
    public function getRawAttribute($value, $default = null)
    {
        return data_get($this->attributes, $value, $default);
    }

    public static function getSetting($key, $default = null, $global = true)
    {
        $base_query = self::query();
        $user_id = auth()->user()?->id() ?? null;
        if(!$global && $user_id) {
            $base_query->where('user_id', $user_id);
        }

        $query = $base_query->clone()->where('key', $key);

        $setting = $query->first();

        if ($setting) {
            return $setting->value;
        }



        // If key not found, start peeling off the last dot-segment
        //    e.g. from "test.key.name" -> "test.key" + tail "name"
        $keyParts = explode('.', $key);

        // If there's only one segment, there's nothing to peel off:
        if (count($keyParts) < 2) {
            return $default;
        }

        // We'll accumulate the "tail" segments in an array
        // so that after popping "name", we can do data_get($value, 'name'),
        // or after popping "key" then "name", we do data_get($value, 'key.name'), etc.
        $tail = [];

        // We'll keep looping until we find a setting or we run out of segments
        while (count($keyParts) > 0) {
            // Pop the last segment and push it to the front of the tail
            $lastSegment = array_pop($keyParts);
            array_unshift($tail, $lastSegment);

            // Now try the partial key (e.g. "test.key", then "test", etc.)
            $partialKey = implode('.', $keyParts);

            // If it becomes empty, we've run out of segments
            if (empty($partialKey)) {
                break;
            }

            $candidate = $base_query->clone()->where('key', $partialKey)->first();
            if ($candidate) {
                // Use data_get on the candidate's value, looking up whatever is in $tail
                // e.g. first iteration, $tail = ['name'], second iteration $tail = ['key','name'] etc.
                $tailString = implode('.', $tail);

                // data_get returns null if the sub-key doesn't exist (unless you pass a default)
                $subValue = data_get($candidate->value, $tailString, null);

                if (! is_null($subValue)) {
                    return $subValue;
                }
            }
        }

        // If we exhaust all segments and never find anything, return the default
        return $default;
    }
}
