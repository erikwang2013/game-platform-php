<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTFactory;
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
 * JWT 便捷包装
 */
if (!function_exists('jwt')) {
    function jwt(): \Erikwang2013\Jwt\JwtWrapper
    {
        static $wrapper = null;
        if ($wrapper === null) {
            $jwt = JWTFactory::createFromConfig(config('plugin.erikwang2013.jwt.jwt'));
            $wrapper = new \Erikwang2013\Jwt\JwtWrapper($jwt);
        }
        return $wrapper;
    }
}

/**
 * 规范化验证码点击坐标为 [x, y] 元组格式（poster-php 包期望的格式）
 */
function captcha_clicks(mixed $clicks): array
{
    if (!is_array($clicks)) {
        return [];
    }
    return array_map(
        fn($c) => [$c['x'] ?? $c[0] ?? 0, $c['y'] ?? $c[1] ?? 0],
        $clicks
    );
}
