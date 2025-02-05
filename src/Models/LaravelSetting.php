<?php
namespace Shopapps\LaravelSettings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

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
                try {
                    if(config('laravel-settings.edit_mode') == 'text') {
                        /*
                         * take example string and tidy up to convert to array:
                         * {
                                "tenant_id": 1021,
                                "recipients": ["test 1","test 2", "test 3 "]
                                }
                         */
                        // need to clean up the json string to remove \n and other unwanted characters first
                        // but do not remove from within the values or keys
                        //$value = str_replace(["\n", "\t"], '', $value);
                        $value = trim($value);
                        $value = json_decode($value, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            dd("JSON Error: " . json_last_error_msg());
                        }
                        $value = Arr::undot($value);
                    } else {
                        $value = Arr::undot(json_decode($value, true));
                    }
                } catch (\Exception $e) {
                    //
                }


                return $value;
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

            /** @var static $candidate */
            $candidate = $base_query->clone()->where('key', $partialKey)->first();
            if ($candidate) {
                // Use data_get on the candidate's value, looking up whatever is in $tail
                // e.g. first iteration, $tail = ['name'], second iteration $tail = ['key','name'] etc.
                $tailString = implode('.', $tail);

                $values = $candidate->getRawAttribute('value');

                if($candidate->type == self::TYPE_ARRAY || $candidate->type == self::TYPE_OBJECT) {

                    $value_array = json_decode($values, true);

                    // dot flatten it and restructure
                    try {
                        $values = Arr::undot($value_array);
                    } catch (\Exception $e) {
                        $values = $value_array;
                    }

                }
                $subValue = data_get($values, $tailString, null);

                if (! is_null($subValue)) {
                    return $subValue;
                }
            }
        }

        // If we exhaust all segments and never find anything, return the default
        return $default;
    }

    public function getPrettyValueAttribute()
    {
        $value = $this->value;
        if($this->type == self::TYPE_ARRAY || $this->type == self::TYPE_OBJECT) {
            $value = json_encode($value, JSON_PRETTY_PRINT);
        }
        return $value;
    }
}
