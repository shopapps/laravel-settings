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
                return (array) json_decode($value, true);
            case self::TYPE_OBJECT:
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
        $query = self::query()->where('key', $key);
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
