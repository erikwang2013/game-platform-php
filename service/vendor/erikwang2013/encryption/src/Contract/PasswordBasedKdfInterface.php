<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Contract;

/**
 * 基于口令的密钥派生（典型为 PBKDF2）：从人类可读口令得到固定长度密钥。
 */
interface PasswordBasedKdfInterface
{
    public function getIdentifier(): string;

    /**
     * @param string $password 口令（原始字节，建议为 UTF-8 编码的字符串）
     * @param string $salt     随机盐（建议每用户/每密钥唯一）
     * @param int    $length   输出字节长度
     *
     * @throws \Erikwang2013\Encryption\Exception\EncryptionException
     */
    public function deriveFromPassword(string $password, string $salt, int $length): string;
}
