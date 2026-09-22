<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common;

use Erikwang2013\Encryptable\Encryptable as BaseEncryptable;

/**
 * 加密 cast（JSON 结构版）：让「明文 JSON」的列也能读成 array，而不是 string。
 *
 * 适用：语义上是 JSON 结构的加密列（common\model\PaymentMethod::$casts['config']、
 * common\model\CdnProvider::$casts['config']）。
 *
 * 病根：install/install.sql 用裸 SQL 播种，绕过 Eloquent 的 set()，落的是**明文** JSON；
 * 而父类 Encryptable::get() 走 PHPEncrypter::decrypt($strict = false)，非密文时**原样返回
 * 入参**（既不解密也不报错）⇒ config 读回来是 string，读取点（StripeGateway/Paysafecard/
 * GrabPay/PaymentController）却按 array 取值 ⇒ `?? $default` 静默回落，种子里的
 * apm_types/country 完全失效（实测见 /tmp/encryptfix/probe_baseline.txt）。
 * 注意：经 set() 落的密文行读回来同样是 string —— Serializer 只支持标量（数组会抛
 * SerializationException），故该列**只可能**是 JSON 文本或 NULL，无歧义。
 *
 * 结构判据：仅当结果首字符是 `{` 或 `[` 时才 json_decode。标量字段（User.phone 的
 * "1234567890"、email、TOTP secret、OAuth token 等）首字符是数字/字母/引号，不会进入
 * json_decode，更不会被解成 int。解码失败返回原 string：不抛异常、不返回 null。
 *
 * 不要用于「JSON 文本语义」的列：User2FA.backup_codes、WithdrawOrder.account_info 的
 * 读取点是 json_decode((string) $x, true) / str_contains($x, '@')，读成 array 会 TypeError。
 */
class JsonEncryptable extends BaseEncryptable
{
    public function get($model, string $key, mixed $value, array $attributes): mixed
    {
        $decrypted = parent::get($model, $key, $value, $attributes);

        if (!is_string($decrypted)) {
            return $decrypted;
        }

        $trimmed = ltrim($decrypted);
        if (!str_starts_with($trimmed, '{') && !str_starts_with($trimmed, '[')) {
            return $decrypted;
        }

        // 对象/数组字面量解码成功必为 array；此处 null 只可能是 JSON 语法错误 ⇒ 回退原串
        return json_decode($decrypted, true) ?? $decrypted;
    }
}
