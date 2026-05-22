<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik
 * Author: erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt;

interface TokenStorageInterface
{
    /**
     * 将令牌加入黑名单
     */
    public function blacklist(string $jti, int $expireTime): bool;

    /**
     * 检查令牌是否在黑名单中
     */
    public function isBlacklisted(string $jti): bool;

    /**
     * 清理过期的黑名单条目
     */
    public function cleanup(): bool;


}