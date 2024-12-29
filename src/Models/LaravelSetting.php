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
    public const TYPE_FLOAT = 'float';
    public const TYPE_ARRAY = 'array';
    public const TYPE_OBJECT = 'object';
    public const TYPE_STRING = 'string';

    public const TYPES = [
        static::TYPE_BOOLEAN,
        static::TYPE_INTEGER,
        static::TYPE_FLOAT,
        static::TYPE_ARRAY,
        static::TYPE_OBJECT,
        static::TYPE_STRING,
    ];


    public function getTable()
    {
        return config('laravel-settings.table.name', parent::getTable());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getValueAttribute($value)
    {
        switch ($this->type) {
            case static::TYPE_BOOLEAN:
                return (bool) $value;
            case static::TYPE_INTEGER:
                return (int) $value;
            case static::TYPE_FLOAT:
                return (float) $value;
            case static::TYPE_ARRAY:
                return (array) json_decode($value, true);
            case static::TYPE_OBJECT:
                return (object) json_decode($value);
            case static::TYPE_STRING:
            default:
                return (string) $value;
        }
    }
    public function getRawAttribute($value, $default = null)
    {
        return data_get($this->attributes, $value, $default);
    }

    public function getSetting($key, $default = null, $global = true)
    {
        $query = $this->where('key', $key);
        $user_id = auth()->user()?->id() ?? null;
        if(!$global && $user_id) {
            $query->where('user_id', $user_id);
        }
        $setting = $query->first();
        if ($setting) {
            return $setting->value;
        }
        return $default;
    }
}
