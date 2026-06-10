<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

/**
 * 创建验证器实例
 */
function validator(array $data, array $rules, array $messages = [], array $attributes = []): \Illuminate\Validation\Validator
{
    static $factory = null;
    if ($factory === null) {
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'));
    }
    return $factory->make($data, $rules, $messages, $attributes);
}
