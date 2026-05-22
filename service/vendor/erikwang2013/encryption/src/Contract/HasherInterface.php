<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Contract;

/**
 * 密码学杂凑（如 SM3、SHA-256），输出固定长度摘要。
 */
interface HasherInterface
{
    public function getIdentifier(): string;

    /**
     * 返回二进制摘要（如 SM3 为 32 字节）。
     */
    public function digest(string $data): string;

    /**
     * 返回小写十六进制摘要字符串。
     */
    public function digestHex(string $data): string;
}
