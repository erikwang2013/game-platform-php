<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\DeviceFingerprint;
use hg\apidoc\annotation as Apidoc;
use support\Redis;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("设备指纹管理")
 * @Apidoc\Group("risk")
 *
 * ponytail: 当前 schema 的 device_fingerprint 无 blocked 列，服务端评估器也不消费拉黑标记；
 *           拉黑/解封为管理端 Redis 标记（TTL 30 天），仅管理端展示；
 *           真正阻断需 service 侧 DeviceFingerprintEvaluator 接入该标记，属后续项。
 */
class RiskDeviceController extends BaseController
{
    private const BLOCK_KEY = 'risk:device:block:';

    /**
     * @Apidoc\Title("设备列表")
     */
    public function list(Request $request): Response
    {
        $query = DeviceFingerprint::query();
        if ($request->get('fp_hash')) {
            $query->where('fp_hash', 'like', (string) $request->get('fp_hash') . '%');
        }
        if ($request->get('account_count_min') !== null && $request->get('account_count_min') !== '') {
            $query->where('account_count', '>=', (int) $request->get('account_count_min'));
        }

        $page = max(1, (int) $request->get('page', 1));
        $size = min(100, max(1, (int) $request->get('size', 20)));
        $total = (clone $query)->count();
        $items = $query->orderBy('last_seen_at', 'desc')->forPage($page, $size)->get()->all();

        $rows = [];
        foreach ($items as $row) {
            $rows[] = [
                'fp_masked' => substr((string) $row->fp_hash, 0, 8) . '****',
                'ip_c_segment' => (string) $row->ip_c_segment,
                'account_count' => (int) $row->account_count,
                'first_seen_at' => (string) $row->first_seen_at,
                'last_seen_at' => (string) $row->last_seen_at,
                'blocked' => $this->isBlocked((string) $row->fp_hash),
            ];
        }

        return $this->success(['total' => $total, 'items' => $rows]);
    }

    /**
     * @Apidoc\Title("拉黑设备")
     * @Apidoc\Desc("管理端标记（Redis TTL 30 天），服务端阻断待接入")
     */
    public function block(Request $request): Response
    {
        try {
            $fpHash = $this->fpHash((string) $request->post('fp_hash', ''));
            Redis::setex(self::BLOCK_KEY . $fpHash, 30 * 86400, '1');
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable) {
            return $this->fail('Redis 不可用');
        }

        return $this->success(['fp_masked' => substr($fpHash, 0, 8) . '****']);
    }

    /**
     * @Apidoc\Title("解封设备")
     */
    public function unblock(Request $request): Response
    {
        try {
            $fpHash = $this->fpHash((string) $request->post('fp_hash', ''));
            Redis::del(self::BLOCK_KEY . $fpHash);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable) {
            return $this->fail('Redis 不可用');
        }

        return $this->success();
    }

    private function isBlocked(string $fpHash): bool
    {
        try {
            return (bool) Redis::get(self::BLOCK_KEY . $fpHash);
        } catch (\Throwable) {
            return false;
        }
    }

    private function fpHash(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if (!preg_match('/^[0-9a-f]{64}$/', $raw)) {
            throw new \InvalidArgumentException('fp_hash 必须是 64 位十六进制');
        }

        return $raw;
    }
}
