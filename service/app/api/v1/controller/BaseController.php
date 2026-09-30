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
     * 点击验证码校验：登录/注册/敏感操作（提现申请、兑换卖出、领券）都要过这一关。
     *
     * 身份按客户端 IP 归属，必须传 $request->getRealIp()，实现见 common\Captcha::verifyFromIp
     * （别自己读 $_SERVER['REMOTE_ADDR']：CLI SAPI 下它不存在，会把限流退化成全局桶）。
     * 调用方负责回 422，消息统一用「验证码错误，请重试」（不区分缺失与填错，避免给探测者线索）。
     */
    protected function captchaOk(Request $request): bool
    {
        return captcha_verify_from_ip(
            $request->getRealIp(),
            (string) $request->input('captcha_key', ''),
            'click',
            captcha_clicks($request->input('clicks'))
        );
    }

    /**
     * 解析请求所属国家：语言头优先（X-Language → Accept-Language），未知返回空串
     */
    protected function resolveCountry(Request $request): string
    {
        $lang = $request->header('X-Language', '') ?: $request->header('Accept-Language', '');
        return CountryConfig::fromLang($lang);
    }

    /**
     * 订单号熵源：uniqid 微秒+进程熵的后 6 位，避免同秒撞 uk_order_no。
     *
     * 由调用方与 generateOrderNo() 组合：订单号 = 前缀 + 时间 + 本函数。
     */
    protected static function orderNoSuffix(): string
    {
        return strtoupper(substr(uniqid('', true), -6));
    }

    /**
     * 拼装订单号：前缀 + YmdHis + 熵后缀（如 WTH20260928201013460143）。
     *
     * 时间与后缀都从参数注入（而非在函数内取 now()/uniqid()），故可被单测钉住精确值；
     * 调用方固定写法：self::generateOrderNo('WTH', time(), self::orderNoSuffix())。
     */
    protected static function generateOrderNo(string $prefix, int $timestamp, string $suffix): string
    {
        return $prefix . date('YmdHis', $timestamp) . strtoupper($suffix);
    }
}
