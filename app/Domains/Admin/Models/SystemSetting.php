<?php

namespace App\Domains\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * Get a setting value by key, with caching and fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "system_setting:{$key}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $setting = static::query()->where('key', $key)->first();
        if ($setting === null) {
            return $default;
        }

        $value = match ($setting->type) {
            'integer', 'int' => (int) $setting->value,
            'float', 'double' => (float) $setting->value,
            'boolean', 'bool' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode((string) $setting->value, true),
            default => $setting->value,
        };

        Cache::put($cacheKey, $value, 3600);

        return $value;
    }

    /**
     * Set a setting value by key and invalidate cache.
     */
    public static function set(string $key, mixed $value, ?string $type = null, ?string $group = 'general', ?string $description = null): static
    {
        $stringValue = is_array($value) ? json_encode($value) : (string) $value;
        $inferredType = $type ?? (is_int($value) ? 'integer' : (is_float($value) ? 'float' : (is_bool($value) ? 'boolean' : (is_array($value) ? 'json' : 'string'))));

        $attributes = [
            'value' => $stringValue,
            'type' => $inferredType,
        ];

        if ($group !== null) {
            $attributes['group'] = $group;
        }
        if ($description !== null) {
            $attributes['description'] = $description;
        }

        $setting = static::query()->updateOrCreate(['key' => $key], $attributes);

        Cache::forget("system_setting:{$key}");

        return $setting;
    }
}
