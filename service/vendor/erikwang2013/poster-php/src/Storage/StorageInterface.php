<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

interface StorageInterface
{
    public function set(string $key, array $data, int $ttl = 300): bool;

    public function get(string $key): ?array;

    public function del(string $key): bool;

    public function has(string $key): bool;

    public function incrementAttempts(string $key): int;
}
