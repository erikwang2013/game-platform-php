<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\risk\evaluators;

use app\model\IpReputation;
use app\service\risk\RiskEvaluator;
use support\Db;
use support\Log;
use support\Redis;
use Throwable;

/**
 * IP 信誉检测
 *
 * 同步路径只读 Redis（命中即用），miss 才回落 DB 并回填缓存；
 * 外部代理 / VPN 检测不在此处实时调用，由离线定时进程写库。
 * ponytail: 5ms 超时由 redis 客户端配置（read/connect timeout）约束，此处不逐次设置，
 *           Redis 任何异常统一降级 unknown（fail-open），外部检测异常不阻断提现。
 */
class IpReputationEvaluator implements RiskEvaluator
{
    private const CACHE_TTL = 86400;
    private const CACHE_PREFIX = 'risk:ip_rep:';

    public function type(): string
    {
        return 'ip_reputation';
    }

    public function evaluate(int $userId, string $checkType, array $context, array $config): array
    {
        $ip = $context['ip'] ?? '';
        if ($ip === '') {
            return $this->miss('IP 缺失，无法评估');
        }

        $blockBelow = (int) ($config['block_score_below'] ?? 30);
        $warnBelow  = (int) ($config['warn_score_below'] ?? 60);

        $row = $this->lookup($ip);
        if ($row === null) {
            // 外部检测异步化 + unknown 放行：默认不阻断，可用 block_unknown 显式收紧
            if ($config['block_unknown'] ?? false) {
                return ['matched' => true, 'message' => "IP {$ip} 信誉未知（fail-closed 配置）", 'severity' => 'high'];
            }
            return $this->miss("IP {$ip} 信誉未知（unknown 放行）");
        }

        $score  = (int) $row['reputation_score'];
        $source = (string) $row['source'];

        // 白名单优先于任何评分与黑名单判定
        if ($source === 'internal_whitelist') {
            return $this->miss("IP {$ip} 在白名单内（score {$score}）");
        }

        if ($score < $blockBelow) {
            $this->bumpHit($ip);
            return ['matched' => true, 'message' => "IP {$ip} 信誉分 {$score} < 阻断阈值 {$blockBelow}（{$source}）", 'severity' => 'high'];
        }

        if ($score < $warnBelow) {
            $this->bumpHit($ip);
            return ['matched' => true, 'message' => "IP {$ip} 信誉分 {$score} < 预警阈值 {$warnBelow}（{$source}）", 'severity' => 'medium'];
        }

        return $this->miss("IP {$ip} 信誉正常（score {$score}）");
    }

    /**
     * @return array{reputation_score:int,source:string}|null null = unknown（放行）
     */
    private function lookup(string $ip): ?array
    {
        $key = self::CACHE_PREFIX . hash('sha256', $ip);

        try {
            $cached = Redis::get($key);
            if (is_string($cached) && $cached !== '') {
                $data = json_decode($cached, true);
                if (is_array($data) && isset($data['reputation_score'], $data['source'])) {
                    return $data;
                }
            }
        } catch (Throwable $e) {
            // Redis 不可用 → 回落 DB（DB 是内部黑名单的权威来源）
            Log::error('IpReputation cache read failed: ' . $e->getMessage());
        }

        try {
            $row = IpReputation::where('ip_hash', hash('sha256', $ip))->first();
            if ($row === null) {
                return null;
            }
            $data = ['reputation_score' => (int) $row->reputation_score, 'source' => (string) $row->source];
            try {
                Redis::setex($key, self::CACHE_TTL, json_encode($data));
            } catch (Throwable $e) {
                Log::error('IpReputation cache write failed: ' . $e->getMessage());
            }
            return $data;
        } catch (Throwable $e) {
            // 软路径 fail-open；硬规则（ip_blacklist）不受此处影响
            Log::error('IpReputation lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    private function bumpHit(string $ip): void
    {
        try {
            Db::table('ip_reputation')->where('ip_hash', hash('sha256', $ip))->increment('hit_count');
        } catch (Throwable $e) {
            Log::error('IpReputation hit_count increment failed: ' . $e->getMessage());
        }
    }

    private function miss(string $message): array
    {
        return ['matched' => false, 'message' => $message, 'severity' => 'low'];
    }
}
