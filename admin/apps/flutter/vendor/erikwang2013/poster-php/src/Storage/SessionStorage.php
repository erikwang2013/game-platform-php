<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

class SessionStorage implements StorageInterface
{
    private string $prefix = 'poster_captcha';

    public function set(string $key, array $data, int $ttl = 300): bool
    {
        $_SESSION[$this->prefix][$key] = [
            'data'      => $data,
            'expire_at' => time() + $ttl,
            'attempts'  => $data['attempts'] ?? 0,
        ];
        return true;
    }

    public function get(string $key): ?array
    {
        if (!isset($_SESSION[$this->prefix][$key])) {
            return null;
        }
        $entry = $_SESSION[$this->prefix][$key];
        if ($entry['expire_at'] < time()) {
            unset($_SESSION[$this->prefix][$key]);
            return null;
        }
        return $entry['data'];
    }

    public function del(string $key): bool
    {
        unset($_SESSION[$this->prefix][$key]);
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function incrementAttempts(string $key): int
    {
        if (!isset($_SESSION[$this->prefix][$key])) {
            return 0;
        }
        $_SESSION[$this->prefix][$key]['attempts'] = ($_SESSION[$this->prefix][$key]['attempts'] ?? 0) + 1;
        return $_SESSION[$this->prefix][$key]['attempts'];
    }
}
