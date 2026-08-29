<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

interface CdnProviderInterface
{
    /** 上传本地文件到对象存储，返回 CDN 访问 URL */
    public function upload(string $key, string $localPath, array $options = []): string;

    /** 缓存刷新（purge），返回任务 ID 列表（无任务 ID 的厂商返回空数组） */
    public function purge(array $urls): array;

    /** 资源预热（preload），返回任务 ID 列表（无预热 API 的厂商返回空数组） */
    public function preload(array $urls): array;

    /** 按 key 生成 CDN URL（不上传，仅拼接） */
    public function url(string $key): string;
}
