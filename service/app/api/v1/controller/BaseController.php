<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\common\HashidsService;
use app\common\SnowflakeService;
use support\Request;
use support\Response;

/**
 * C端基础控制器
 * 提供统一响应格式、ID编解码、snowflake ID 生成
 */
class BaseController
{
    /**
     * 成功响应
     */
    protected function success($data = [], string $message = 'success', int $code = 0): Response
    {
        return json(['code' => $code, 'message' => $message, 'data' => $data]);
    }

    /**
     * 失败响应
     */
    protected function fail(string $message = 'fail', int $code = 500, $data = []): Response
    {
        return json(['code' => $code, 'message' => $message, 'data' => $data]);
    }

    /**
     * 将模型 ID 编码为 hashid 字符串
     */
    protected function encodeId(int $id): string
    {
        return HashidsService::encode($id);
    }

    /**
     * 将 hashid 字符串解码为原始 ID
     */
    protected function decodeId(string $hashid): int
    {
        return HashidsService::decode($hashid);
    }

    /**
     * 生成新的 snowflake ID
     */
    protected function generateId(): int
    {
        return SnowflakeService::generate();
    }
}
