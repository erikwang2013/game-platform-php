<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use common\RiskDeviceBlock;
use common\model\DeviceFingerprint;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * 拉黑/解封为管理端 Redis 标记（TTL 30 天，键定义在共享包 `common\RiskDeviceBlock`，**service 侧同一处读取**）。
 *
 * 它**不是**一条风控规则：`RiskService::check()` 里对标记做短路，不看任何规则是否启用
 * —— 人工拉黑是人的决定，不该取决于 device_fingerprint 规则开没开（install.sql 的种子里
 * 那条规则 status=0，默认就是关的，早先按「等规则命中」实现等于拉黑了也不会阻断）。
 * schema 的 device_fingerprint 仍无 blocked 列，标记只在 Redis，故有 TTL（到期自然解封）。
 *
 * 列表回传完整 `fp_hash`：block/unblock 收的就是它，只给掩码等于让管理端拿不到能提交的标识。
 */
#[Apidoc\Title("设备指纹管理")]
#[Apidoc\Group("risk")]
class RiskDeviceController extends BaseController
{
    #[Apidoc\Title("设备列表")]
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
                // 完整哈希是 block/unblock 的入参（64 位十六进制），掩码提交不上去 ⇒ 两者都给：
                // fp_hash 供行内动作拼请求体，fp_masked 供展示（列表列仍优先显示掩码）
                'fp_hash' => (string) $row->fp_hash,
                'fp_masked' => substr((string) $row->fp_hash, 0, 8) . '****',
                'ip_c_segment' => (string) $row->ip_c_segment,
                'account_count' => (int) $row->account_count,
                'first_seen_at' => (string) $row->first_seen_at,
                'last_seen_at' => (string) $row->last_seen_at,
                'blocked' => RiskDeviceBlock::isBlocked((string) $row->fp_hash),
            ];
        }

        return $this->success(['total' => $total, 'items' => $rows]);
    }

    #[Apidoc\Title("拉黑设备")]
    #[Apidoc\Desc("管理端标记（Redis TTL 30 天）：RiskService::check() 直接短路成阻断，不依赖规则是否启用")]
    public function block(Request $request): Response
    {
        try {
            $fpHash = $this->fpHash((string) $request->post('fp_hash', ''));
            RiskDeviceBlock::block($fpHash);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 400);
        } catch (\Throwable) {
            return $this->fail(trans('Redis is unavailable'));
        }

        return $this->success(['fp_masked' => substr($fpHash, 0, 8) . '****']);
    }

    #[Apidoc\Title("解封设备")]
    public function unblock(Request $request): Response
    {
        try {
            $fpHash = $this->fpHash((string) $request->post('fp_hash', ''));
            RiskDeviceBlock::unblock($fpHash);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 400);
        } catch (\Throwable) {
            return $this->fail(trans('Redis is unavailable'));
        }

        return $this->success();
    }

    private function fpHash(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if (!preg_match('/^[0-9a-f]{64}$/', $raw)) {
            throw new \InvalidArgumentException(trans('fp_hash must be 64 hex characters'));
        }

        return $raw;
    }
}
