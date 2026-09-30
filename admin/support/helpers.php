<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTFactory;
use common\JwtRedisClient;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

/**
 * 创建验证器实例
 *
 * ⚠ 语言行必须显式加载：`new Translator(new ArrayLoader(), 'en')` 里 ArrayLoader 是空的，
 * 校验失败时 `$errors->first()` 会返回**裸规则 key**（`validation.required`），而管理端各控制器
 * 都是原样把它塞进 `fail($validator->errors()->first(), 422)` ⇒ 用户在表单里看到的是机器话。
 * 覆盖范围＝本仓控制器实际用到的规则（见各 validator 的取值统计），漏掉的规则会退回裸 key。
 */
function validator(array $data, array $rules, array $messages = [], array $attributes = []): \Illuminate\Validation\Validator
{
    static $factory = null;
    if ($factory === null) {
        $loader = new ArrayLoader();
        $loader->addMessages('zh', 'validation', [
            'required'       => ':attribute 不能为空',
            'string'         => ':attribute 必须是字符串',
            'integer'        => ':attribute 必须是整数',
            'numeric'        => ':attribute 必须是数字',
            'array'          => ':attribute 必须是数组',
            'boolean'        => ':attribute 必须是布尔值',
            'email'          => ':attribute 必须是合法邮箱',
            'url'            => ':attribute 必须是合法链接',
            'date'           => ':attribute 不是合法日期',
            'regex'          => ':attribute 格式不正确',
            'in'             => ':attribute 取值不在允许范围内',
            'after_or_equal' => ':attribute 必须不早于 :date',
            'lt'             => ':attribute 必须小于 :value',
            'max'            => [
                'numeric' => ':attribute 不能大于 :max',
                'string'  => ':attribute 不能超过 :max 个字符',
                'array'   => ':attribute 不能超过 :max 项',
            ],
            'min'            => [
                'numeric' => ':attribute 不能小于 :min',
                'string'  => ':attribute 不能少于 :min 个字符',
                'array'   => ':attribute 不能少于 :min 项',
            ],
            'between'        => [
                'numeric' => ':attribute 必须在 :min 到 :max 之间',
                'string'  => ':attribute 长度必须在 :min 到 :max 之间',
            ],
            'size'           => [
                'numeric' => ':attribute 必须是 :size',
                'string'  => ':attribute 长度必须是 :size',
                'array'   => ':attribute 必须包含 :size 项',
            ],
        ]);
        $factory = new Factory(new Translator($loader, 'zh'));
    }
    return $factory->make($data, $rules, $messages, $attributes);
}

/**
 * JWT 便捷包装
 *
 * 注意：本函数在 admin 树里是**死代码** —— vendor 的 Laravel/helpers.php 已用同名
 * `jwt()`（返回 app('erik.jwt')）占据该函数名，function_exists 守卫恒为真，本函数永不注册。
 * admin 侧真正用的入口是 jwt_instance()。
 */
if (!function_exists('jwt')) {
    function jwt(): \Erikwang2013\Jwt\JwtWrapper
    {
        static $wrapper = null;
        if ($wrapper === null) {
            $jwt = jwt_instance();
            $wrapper = new \Erikwang2013\Jwt\JwtWrapper($jwt);
        }
        return $wrapper;
    }
}

/**
 * 裸 JWT 实例（AdminAuth / AuthController / ProfileController 的 getJWT() 用）。
 *
 * 第三个参数 $connections 只在 storage.type=redis 时被读取（file 模式下 resolver 永不被调用），
 * 故无条件传入不改变今日默认行为；不传则把 JWT_STORAGE_TYPE 设成 redis 会在构造期就抛
 * "Redis resolver callable required" ⇒ 管理端整条鉴权链 500。resolver 的形状见 common\JwtRedisClient。
 */
function jwt_instance(): JWT
{
    return JWTFactory::createFromConfig(
        config('plugin.erikwang2013.jwt.jwt', []),
        null,
        ['redis' => static fn () => new JwtRedisClient()]
    );
}

/**
 * 规范化验证码点击坐标 —— 实现在 common\Captcha（admin/service 共用，
 * 见 packages/platform-common/src/Captcha.php 里那段「webman 下身份恒为 cli」的说明）
 */
function captcha_clicks(mixed $clicks): array
{
    return \common\Captcha::clicks($clicks);
}

/**
 * 按「真实客户端 IP」归属的验证码校验 —— 实现在 common\Captcha（两棵树共用）。
 *
 * @param string $ip 客户端 IP，传 $request->getRealIp()。别读 $_SERVER['REMOTE_ADDR']（CLI SAPI 下不存在），
 *                   也别传常量——那会退回全局桶，细节见 common\Captcha::verifyFromIp 的注释。
 */
function captcha_verify_from_ip(string $ip, string $key, string $type, mixed $data): bool
{
    return \common\Captcha::verifyFromIp($ip, $key, $type, $data);
}
