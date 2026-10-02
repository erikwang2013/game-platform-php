<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use common\HashidsService;
use common\SnowflakeService;
use common\EncryptionService;
use app\model\AdminUser;
use InvalidArgumentException;
use support\Response;
use Webman\Exception\BusinessException;

/**
 * 管理端基础控制器
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
     * 批量编码数组中的 ID 字段
     */
    protected function encodeIds(array $data, array $idFields = ['id']): array
    {
        return HashidsService::encodeIds($data, $idFields);
    }

    /**
     * 生成新的 snowflake ID
     */
    /**
     * 用户行里的联系方式与登录 IP 一律脱敏后再下发。
     *
     * 为什么必须有：`game_user` 的 phone/email 是 Encryptable 列，**读回就是明文**
     * （cast 在取值那一刻已经解过密），列表/详情端点只要直接 `toArray()` 就等于把联系方式
     * 明文外发。`User::$hidden` 只有 `['password']`（packages/platform-common/src/model/User.php:33），
     * 挡不住这三个字段。
     *
     * 用 `EncryptionService` 的两个函数而不是就地写正则：它们能处理带国家码的号
     * （`+8613812345678` → `+86****5678`），而 `^(\d{3})\d+(\d{4})$` 这种正则一旦匹配不上
     * 就**原样返回**，等于把号码整串漏出去。
     * ⚠ 但这两个函数自己也有同样的早返回：phone 短于 7 位、email 不含 `@` 时原样返回 ——
     * 那是脏数据，仍然是 PII，所以下面按「没变化就整串打掉」兜底，宁可少显示不可多显示。
     *
     * `last_login_ip` 没有现成函数：IPv4 抹掉最后一段、保留前三段（网段仍可用于判断
     * 「是不是同一地点登录」），IPv6/畸形值直接整串打掉。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function maskUserContact(array $data): array
    {
        foreach (['phone' => 'maskPhone', 'email' => 'maskEmail'] as $field => $fn) {
            if (!isset($data[$field]) || !is_string($data[$field]) || $data[$field] === '') {
                continue;
            }
            $masked = EncryptionService::{$fn}($data[$field]);
            $data[$field] = ($masked === $data[$field]) ? '***' : $masked;
        }

        if (isset($data['last_login_ip']) && is_string($data['last_login_ip']) && $data['last_login_ip'] !== '') {
            $ip     = $data['last_login_ip'];
            $masked = preg_replace('/\.\d+$/', '.*', $ip);
            $data['last_login_ip'] = ($masked === null || $masked === $ip) ? '***' : $masked;
        }

        return $data;
    }

    protected function generateId(): int
    {
        return SnowflakeService::generate();
    }

    /**
     * 二次确认 — 验证当前登录用户密码
     * 敏感操作（删除、导出等）调用此方法确认身份
     *
     * @param int $adminId 当前登录用户 ID
     * @param string $password 用户输入的密码
     * @return string|null 错误消息，null 表示验证通过
     */
    protected function confirmPassword(int $adminId, string $password): ?string
    {
        if (empty($password)) {
            return trans('This sensitive operation requires password confirmation');
        }

        $admin = AdminUser::find($adminId);
        if (!$admin || !password_verify($password, $admin->password)) {
            return trans('Password verification failed');
        }

        return null; // 验证通过
    }
}
