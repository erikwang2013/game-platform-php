<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Contract;

/**
 * 基于密钥材料的派生（典型为 HKDF）：从 IKM + salt + info 得到任意长度输出密钥。
 */
interface KeyDerivationInterface
{
    public function getIdentifier(): string;

    /**
     * @param string $ikm  输入密钥材料（如主密钥、DH 共享秘密）
     * @param string $salt 盐值（可为空，但建议非空随机）
     * @param int    $length 输出字节长度
     * @param string $info 可选上下文信息（如协议标签）
     *
     * @throws \Erikwang2013\Encryption\Exception\EncryptionException
     */
    public function derive(string $ikm, string $salt, int $length, string $info = ''): string;
}
