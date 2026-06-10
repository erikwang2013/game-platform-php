<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace support;

use Illuminate\Validation\Factory;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;

class Validator
{
    private static ?Factory $factory = null;

    public static function factory(): Factory
    {
        if (self::$factory === null) {
            $loader = new ArrayLoader();
            $translator = new Translator($loader, 'en');
            self::$factory = new Factory($translator);
        }
        return self::$factory;
    }

    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): \Illuminate\Validation\Validator
    {
        return self::factory()->make($data, $rules, $messages, $attributes);
    }
}
