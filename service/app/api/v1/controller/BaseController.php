<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\HashidsService;
use common\SnowflakeService;
use common\model\CountryConfig;
use erikwang2013\apidoc\annotation as Apidoc;
use InvalidArgumentException;
use support\Request;
use support\Response;
use Webman\Exception\BusinessException;

/**
 * C端基础控制器
 * 提供统一响应格式、ID编解码、snowflake ID 生成
 */
#[Apidoc\NotParse()]
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
     *
     * 非法/伪造 hashid 属客户端错误：转 400 业务异常，避免 500 并泄漏堆栈路径
     */
    protected function decodeId(string $hashid): int
    {
        try {
            return HashidsService::decode($hashid);
        } catch (InvalidArgumentException $e) {
            throw new BusinessException($e->getMessage(), 400);
        }
    }

    /**
     * 生成新的 snowflake ID
     */
    protected function generateId(): int
    {
        return SnowflakeService::generate();
    }

    /**
     * 解析请求所属国家：语言头优先（X-Language → Accept-Language），未知返回空串
     */
    protected function resolveCountry(Request $request): string
    {
        $lang = $request->header('X-Language', '') ?: $request->header('Accept-Language', '');
        return CountryConfig::fromLang($lang);
    }
}
