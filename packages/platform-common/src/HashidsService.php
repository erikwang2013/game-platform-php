<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common;

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
     * 批量编码数组中的 ID 字段。
     *
     * 认两种形状（这处分叉是踩过坑的）：
     *   - 单行：`['id' => 123, 'name' => 'x']` → 编该行的 id
     *   - 多行：`[['id' => 1], ['id' => 2]]` → **逐行**编，返回同形状的列表
     *
     * 只处理顶层键时，把整张表传进来 `isset($data['id'])` 恒假、会**静默一个都不编**：
     * `/admin/v1/risk/rule/list`、`/admin/v1/analytics/game-ranking`、`/admin/v1/analytics/conversion`
     * 三处都中过，表现是「接口不报错，但返回裸 BIGINT ID」，而配套的 `{hashid}` 端点又只吃 hashid，
     * 前端拿列表里的 id 去调 update/toggle 必然对不上。
     */
    public static function encodeIds(array $data, array $fields = ['id']): array
    {
        if (array_is_list($data)) {
            foreach ($data as $index => $row) {
                if (is_array($row)) {
                    $data[$index] = self::encodeIds($row, $fields);
                }
            }
            return $data;
        }

        foreach ($fields as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                $data[$field] = self::encode((int) $data[$field]);
            }
        }
        return $data;
    }
}
