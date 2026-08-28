<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Erikwang2013\Jwt\JWTFactory;
use Erikwang2013\Jwt\JwtWrapper;
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

/**
 * JWT 门面：vendor 的 jwt() 仅返回裸 JWT(无 create/verify/refresh)，应用侧统一走 JwtWrapper
 */
function jwt_wrapper(): JwtWrapper
{
    static $wrapper = null;
    if ($wrapper === null) {
        $wrapper = new JwtWrapper(JWTFactory::createFromConfig(config('plugin.erikwang2013.jwt.jwt')));
    }
    return $wrapper;
}
