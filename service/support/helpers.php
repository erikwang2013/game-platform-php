<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Erikwang2013\Jwt\JWTFactory;
use Erikwang2013\Jwt\JwtWrapper;
use common\JwtRedisClient;
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
 *
 * 第三个参数 $connections 只在 storage.type=redis 时被读取（file 模式下 resolver 永不被调用），
 * 故无条件传入不改变今日默认行为；不传则 JWT_STORAGE_TYPE=redis 会在构造期就抛
 * "Redis resolver callable required" ⇒ 整条鉴权链 500。resolver 的形状见 common\JwtRedisClient。
 */
function jwt_wrapper(): JwtWrapper
{
    static $wrapper = null;
    if ($wrapper === null) {
        $wrapper = new JwtWrapper(JWTFactory::createFromConfig(
            config('plugin.erikwang2013.jwt.jwt'),
            null,
            ['redis' => static fn () => new JwtRedisClient()]
        ));
    }
    return $wrapper;
}
