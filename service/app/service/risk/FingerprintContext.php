<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\risk;

/**
 * 指纹上下文派生（H4 §5.5）：把原始上下文一次性派生为 hash 字段，供评估器复用。
 *
 * PII 原则：只产出 hash（fp_hash / ip_hash / ip_c_segment / user_agent_hash /
 * accept_lang_hash），明文 IP / UA 不进任何风控表。
 *
 * fp_hash = sha256(salt | ua | ip | accept_lang | accept_enc)，与 game_device_fingerprint 表注释一致。
 * salt 为空时不产 fp_hash（指纹退化为不可用，评估器自行 miss，不 fail-closed）。
 */
class FingerprintContext
{
    /**
     * @param int   $userId 仅用于占位/审计语义，当前派生不依赖 userId
     * @param array $context 原始上下文：ip / user_agent(或 ua / device_info) / accept_lang / accept_encoding
     * @return array<string,string> 派生 hash 字段，无对应输入则省略
     */
    public static function build(int $userId, array $context): array
    {
        $ip   = (string) ($context['ip'] ?? '');
        $ua   = (string) ($context['user_agent'] ?? ($context['ua'] ?? ($context['device_info'] ?? '')));
        $lang = (string) ($context['accept_lang'] ?? '');
        $enc  = (string) ($context['accept_encoding'] ?? '');

        $out = [];

        if ($ip !== '') {
            $out['ip_hash']       = hash('sha256', $ip);
            $out['ip_c_segment']  = self::cSegment($ip);
        }
        if ($ua !== '') {
            $out['user_agent_hash'] = hash('sha256', $ua);
        }
        if ($lang !== '') {
            $out['accept_lang_hash'] = hash('sha256', $lang);
        }

        $salt = (string) config('risk.fingerprint_salt', '');
        if ($salt !== '' && $ua !== '') {
            $out['fp_hash'] = hash('sha256', $salt . '|' . $ua . '|' . $ip . '|' . $lang . '|' . $enc);
        }

        return $out;
    }

    /**
     * IP C 段：IPv4 前三段 / IPv6 /48（前 3 个 hextet）。
     * ponytail: 不做完整 IPv6 规范化（压缩形式等），按原样取前 3 段；聚合用途足够。
     */
    private static function cSegment(string $ip): string
    {
        if (strpos($ip, ':') === false) {
            if (preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3})\./', $ip, $m) === 1) {
                return $m[1];
            }
            return $ip;
        }

        $packed = @inet_pton($ip);
        if ($packed !== false && strlen($packed) === 16) {
            // 前 48 bit = 前 3 个 hextet
            $hex = bin2hex(substr($packed, 0, 6));
            return implode(':', [substr($hex, 0, 4), substr($hex, 4, 4), substr($hex, 8, 4)]) . '::';
        }

        $parts = explode(':', $ip);
        return implode(':', array_slice($parts, 0, 3));
    }
}
