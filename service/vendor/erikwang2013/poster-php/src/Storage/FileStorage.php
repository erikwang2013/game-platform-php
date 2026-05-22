<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

use Erikwang2013\Poster\PosterConfig;
use RuntimeException;

class FileStorage implements StorageInterface
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? PosterConfig::get('captcha.file.path') ?? sys_get_temp_dir() . '/poster-captcha';
        if (!is_dir($this->path)) {
            if (!mkdir($this->path, 0755, true) && !is_dir($this->path)) {
                throw new RuntimeException("Cannot create directory: {$this->path}");
            }
        }
    }

    public function set(string $key, array $data, int $ttl = 300): bool
    {
        $file = $this->filePath($key);
        $payload = [
            'data'      => $data,
            'expire_at' => time() + $ttl,
            'attempts'  => $data['attempts'] ?? 0,
        ];
        return file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    }

    public function get(string $key): ?array
    {
        $file = $this->filePath($key);
        if (!is_file($file)) {
            return null;
        }
        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }
        $payload = json_decode($content, true);
        if (!is_array($payload)) {
            return null;
        }
        if ($payload['expire_at'] < time()) {
            unlink($file);
            return null;
        }
        return $payload['data'];
    }

    public function del(string $key): bool
    {
        $file = $this->filePath($key);
        if (is_file($file)) {
            unlink($file);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function incrementAttempts(string $key): int
    {
        $file = $this->filePath($key);
        if (!is_file($file)) {
            return 0;
        }
        $content = file_get_contents($file);
        if ($content === false) {
            return 0;
        }
        $payload = json_decode($content, true);
        if (!is_array($payload)) {
            return 0;
        }
        $payload['attempts'] = ($payload['attempts'] ?? 0) + 1;
        file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $payload['attempts'];
    }

    private function filePath(string $key): string
    {
        return $this->path . '/' . md5($key) . '.json';
    }
}
