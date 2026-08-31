<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\IpReputation;
use hg\apidoc\annotation as Apidoc;
use support\Redis;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("IP 信誉/黑名单管理")
 * @Apidoc\Group("risk")
 *
 * 黑名单 = ip_reputation source=internal_blacklist score=0（IpReputationEvaluator 消费生效）；
 * 白名单/申诉 = source=internal_whitelist score=100（评估器先判白名单直接放行）。
 * 写库后必须删除 Redis 信誉缓存，否则 TTL 内不生效。
 */
class RiskIpController extends BaseController
{
    private const CACHE_PREFIX = 'risk:ip_rep:';

    /**
     * @Apidoc\Title("IP 列表")
     */
    public function list(Request $request): Response
    {
        $query = IpReputation::query();
        if ($request->get('source')) {
            $query->where('source', (string) $request->get('source'));
        }
        if ($request->get('keyword')) {
            $query->where('ip_hash', 'like', (string) $request->get('keyword') . '%');
        }
        if ($request->get('score_min') !== null && $request->get('score_min') !== '') {
            $query->where('reputation_score', '>=', (int) $request->get('score_min'));
        }

        $page = max(1, (int) $request->get('page', 1));
        $size = min(100, max(1, (int) $request->get('size', 20)));
        $total = (clone $query)->count();
        $items = $query->orderBy('last_seen_at', 'desc')->forPage($page, $size)->get()->all();

        return $this->success([
            'total' => $total,
            'items' => array_map(static fn ($row) => [
                'ip_masked' => substr((string) $row->ip_hash, 0, 8) . '****',
                'reputation_score' => (int) $row->reputation_score,
                'source' => (string) $row->source,
                'hit_count' => (int) $row->hit_count,
                'first_seen_at' => (string) $row->first_seen_at,
                'last_seen_at' => (string) $row->last_seen_at,
            ], $items),
        ]);
    }

    /**
     * @Apidoc\Title("拉黑 IP")
     */
    public function block(Request $request): Response
    {
        return $this->writeReputation($request, 'internal_blacklist', 0);
    }

    /**
     * @Apidoc\Title("加入白名单")
     */
    public function whitelist(Request $request): Response
    {
        return $this->writeReputation($request, 'internal_whitelist', 100);
    }

    /**
     * @Apidoc\Title("IP 误判申诉放行")
     * @Apidoc\Desc("与白名单同效：source=internal_whitelist score=100")
     */
    public function appeal(Request $request): Response
    {
        return $this->writeReputation($request, 'internal_whitelist', 100);
    }

    /**
     * @Apidoc\Title("重查")
     * @Apidoc\Desc("删除本地信誉缓存（外部代理/VPN 检测服务未接入，仅重新读取 DB）")
     */
    public function recheck(Request $request): Response
    {
        try {
            $ipHash = hash('sha256', $this->ip((string) $request->post('ip', '')));
            Redis::del(self::CACHE_PREFIX . $ipHash);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable) {
            // Redis 不可用时缓存随 TTL 自然过期
        }

        return $this->success(['message' => '已刷新信誉缓存（外部检测服务未接入）']);
    }

    private function writeReputation(Request $request, string $source, int $score): Response
    {
        try {
            $ipHash = hash('sha256', $this->ip((string) $request->post('ip', '')));
            $now = date('Y-m-d H:i:s');

            $row = IpReputation::where('ip_hash', $ipHash)->first();
            if ($row) {
                $row->source = $source;
                $row->reputation_score = $score;
                $row->last_seen_at = $now;
                $row->save();
            } else {
                IpReputation::create([
                    'ip_hash' => $ipHash,
                    'reputation_score' => $score,
                    'source' => $source,
                    'hit_count' => 0,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]);
            }
            try {
                Redis::del(self::CACHE_PREFIX . $ipHash);
            } catch (\Throwable) {
                // 缓存删不掉则 TTL 内稍后失效
            }
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        }

        return $this->success(['ip_masked' => substr($ipHash, 0, 8) . '****', 'source' => $source]);
    }

    private function ip(string $raw): string
    {
        $ip = filter_var(trim($raw), FILTER_VALIDATE_IP);
        if ($ip === false) {
            throw new \InvalidArgumentException('非法 IP 地址');
        }

        return $ip;
    }
}
