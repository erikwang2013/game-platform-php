<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class PlatformConfig extends Model
{
    protected $table = 'platform_config';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Get a configuration value by group and key.
     *
     * @param string     $group
     * @param string     $key
     * @param mixed|null $default
     * @return mixed
     */
    public static function get(string $group, string $key, $default = null)
    {
        $config = static::where('group', $group)->where('key', $key)->first();

        if (!$config) {
            return $default;
        }

        return match ($config->type) {
            'bool' => (bool) $config->value,
            'int' => (int) $config->value,
            'json' => json_decode($config->value, true),
            'decimal' => (string) $config->value,
            default => (string) $config->value,
        };
    }

    /**
     * Set a configuration value by group and key.
     *
     * @param string $group
     * @param string $key
     * @param mixed  $value
     * @param string $type
     * @return void
     */
    public static function set(string $group, string $key, $value, string $type = 'string'): void
    {
        static::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => (string) $value, 'type' => $type]
        );
    }
}
