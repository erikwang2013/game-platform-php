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

class FileTokenStorage implements TokenStorageInterface
{
    private $storagePath;
    private $gcProbability = 0.1; // 10% 的概率执行垃圾回收

    public function __construct(string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? sys_get_temp_dir() . '/jwt_blacklist';


        if (!is_dir($this->storagePath)) {
            if (!mkdir($this->storagePath, 0755, true)) {
                throw JWTException::storageError("Cannot create storage directory: {$this->storagePath}");
            }
        }

        // 检查目录是否可写
        if (!is_writable($this->storagePath)) {
            throw JWTException::storageError("Storage directory is not writable: {$this->storagePath}");
        }
    }

    public function blacklist(string $jti, int $expireTime): bool
    {
        $now = time();
        $ttl = $expireTime - $now;

        if ($ttl <= 0) {
            return true; // 已经过期的令牌不需要加入黑名单
        }

        $filePath = $this->getFilePath($jti);
        $data = [
            'jti' => $jti,
            'expire_time' => $expireTime,
            'created_at' => $now
        ];

        $result = file_put_contents($filePath, json_encode($data), LOCK_EX);

        if ($result === false) {
            throw JWTException::storageError("Failed to write blacklist file: {$filePath}");
        }

        // 随机执行垃圾回收
        $this->garbageCollection();

        return true;
    }

    public function isBlacklisted(string $jti): bool
    {
        $filePath = $this->getFilePath($jti);

        if (!file_exists($filePath)) {
            return false;
        }

        // 检查文件是否可读
        if (!is_readable($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        $data = json_decode($content, true);
        if (!$data) {
            return false;
        }

        // 检查是否过期
        if (time() > $data['expire_time']) {
            // 异步删除过期文件（不阻塞当前请求）
            $this->unlinkAsync($filePath);
            return false;
        }

        return true;
    }

    public function cleanup(): bool
    {
        $files = glob($this->storagePath . '/*.json');
        $now = time();
        $cleaned = 0;

        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);
            if ($data && $now > $data['expire_time']) {
                if (unlink($file)) {
                    $cleaned++;
                }
            }
        }

        return true;
    }


    private function getFilePath(string $jti): string
    {
        if (!ctype_xdigit($jti)) {
            throw JWTException::storageError('Invalid JTI format');
        }
        return $this->storagePath . '/' . $jti . '.json';
    }

    private function garbageCollection(): void
    {
        if (mt_rand(1, 100) <= ($this->gcProbability * 100)) {
            $this->cleanup();
        }
    }

    /**
     * 异步删除文件（避免阻塞）
     */
    private function unlinkAsync(string $filePath): void
    {
        if (function_exists('exec') && stripos(PHP_OS, 'WIN') !== 0) {
            // Linux/Unix 系统使用后台删除
            exec("rm -f " . escapeshellarg($filePath) . " > /dev/null 2>&1 &");
        } else {
            // Windows 或其他系统直接删除
            @unlink($filePath);
        }
    }

     /**
     * 设置垃圾回收概率
     */
    public function setGcProbability(float $probability): void
    {
        $this->gcProbability = max(0, min(1, $probability));
    }

    /**
     * 获取存储统计信息
     */
    public function getStats(): array
    {
        $files = glob($this->storagePath . '/*.json') ?: [];
        $now = time();
        $valid = 0;
        $expired = 0;

        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);
            if ($data) {
                if ($now > $data['expire_time']) {
                    $expired++;
                } else {
                    $valid++;
                }
            }
        }

        return [
            'total_files' => count($files),
            'valid_tokens' => $valid,
            'expired_tokens' => $expired,
            'storage_path' => $this->storagePath
        ];
    }
}
