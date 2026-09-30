<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common;

use support\Redis;
use Throwable;

/**
 * 管理端「人工拉黑设备」标记的唯一定义处。
 *
 * 这是 **admin 写、service 读** 的跨应用契约：admin 的 RiskDeviceController 落标记，
 * service 的 RiskService 在风控检查里消费它。此前键名只以字面量写死在 admin 一侧，
 * service 没有任何读取点 —— 拉黑只写了一个没人看的键（按钮亮着、什么都没发生）。
 * 两棵树各写一份前缀，任何一边改动都会静默失效（不报错、不生效），故提到共享包。
 *
 * 与规则驱动的那套区分开：命中与否由规则是否启用决定，而「拉黑」是人工决定，
 * 不该取决于某条规则开没开（种子里的 device_fingerprint 规则 status=0，默认就是关的）。
 */
class RiskDeviceBlock
{
    private const PREFIX = 'risk:device:block:';

    /** 标记存活期：30 天（与 admin 端原字面量一致），到期后自然解封 */
    public const TTL = 30 * 86400;

    public static function key(string $fpHash): string
    {
        return self::PREFIX . $fpHash;
    }

    public static function block(string $fpHash): void
    {
        Redis::setex(self::key($fpHash), self::TTL, '1');
    }

    public static function unblock(string $fpHash): void
    {
        Redis::del(self::key($fpHash));
    }

    /**
     * Redis 不可用时按「未拉黑」返回（fail-open）：一次缓存故障不该把全站登录/提现一起挡掉
     * —— 与 admin 侧原有的取舍一致，也与 config/risk.php 的超时 fail-open 同向。
     */
    public static function isBlocked(string $fpHash): bool
    {
        if ($fpHash === '') {
            return false;
        }

        try {
            return (bool) Redis::get(self::key($fpHash));
        } catch (Throwable) {
            return false;
        }
    }
}
