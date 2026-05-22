<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\common;

use support\Container;
use InvalidArgumentException;

/**
 * Hashids 编解码服务
 * 用于 API 层 ID 加解密，对外暴露 hash 字符串，隐藏真实数据库 BIGINT ID
 */
class HashidsService
{
    public static function encode(int $id): string
    {
        return Container::get('hashids')->encode($id);
    }

    public static function decode(string $hashid): int
    {
        $ids = Container::get('hashids')->decode($hashid);
        if (empty($ids)) {
            throw new InvalidArgumentException('无效的加密ID');
        }
        return (int) $ids[0];
    }

    /**
     * 批量编码数组中的 ID 字段
     */
    public static function encodeIds(array $data, array $fields = ['id']): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                $data[$field] = self::encode((int) $data[$field]);
            }
        }
        return $data;
    }
}
