<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Contract;

/**
 * 对称加密实现契约（与 {@see SymmetricCipherInterface} 等价，保留别名以兼容旧代码）。
 */
interface EncryptorInterface extends SymmetricCipherInterface
{
}
