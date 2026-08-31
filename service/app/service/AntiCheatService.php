<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

use common\SnowflakeService;
use app\model\AntiCheatDailyStat;
use app\model\AntiCheatEvent;
use common\model\GamePlayLog;
use app\model\RiskRule;
use app\model\UserTrust;
use app\service\anticheat\AntiCheatDetector;
use support\Log;
use support\Redis;

/**
 * 反作弊服务（P0+P1 最小区间）
 *
 * 数据流: settle 完成 → EventBus::emit(anticheat.round_finished)（实时检测，独立 Redis 路径）
 *   + AntiCheatWorker 每小时增量批处理 game_game_play_log →
 *   AntiCheatDailyStat 每日汇总（uk user+game+date 幂等）→
 *   命中 → AntiCheatEvent（uk user+rule+date 幂等）+ UserTrust 扣分。
 *
 * 规则复用 game_risk_rule（type LIKE 'anticheat_%'），status=1 才生效（灰启默认 0）。
 */
class AntiCheatService
{
    public const EVENT_ROUND_FINISHED = 'anticheat.round_finished';

    /**
     * 对局结算旁路入口（EventConsumer dispatch 调用）。
     * 实时检测 3 项（O(1) Redis，不写 MySQL，命中才写事件）：
     *   1) 投注速率   anticheat_rate  窗口内对局数超 max_rounds（config: window_seconds/max_rounds）
     *   2) 间隔模式   anticheat_rate  相邻对局间隔 ≤ gap_ms_max 连续 min_streak 次（config: gap_ms_max/min_streak）
     *   3) 赔付封顶   anticheat_payout 窗口内累计赔付超 max_payout（config: window_minutes/max_payout）
     * 规则未启用（status=0）跳过；任一检测异常仅记日志，不影响主链路。
     */
    public static function onRoundFinished(array $payload): void
    {
        $userId  = (int) ($payload['user_id'] ?? 0);
        $gameId  = (int) ($payload['game_id'] ?? 0);
        $roundId = (string) ($payload['round_id'] ?? '');
        if ($userId <= 0) {
            return;
        }

        $rules = self::realtimeRules();
        if ($rules === []) {
            return;
        }

        $now = time();

        // 1) 投注速率：滑窗计数超阈值即时触发
        if (isset($rules['anticheat_rate'])) {
            try {
                self::checkRate($userId, $gameId, $roundId, $rules['anticheat_rate'], $now);
            } catch (\Throwable $e) {
                Log::warning('AntiCheat rate check failed: ' . $e->getMessage());
            }
        }

        // 2) 间隔模式：恒短间隔（机器节奏）连续 min_streak 次
        if (isset($rules['anticheat_rate'])) {
            try {
                self::checkInterval($userId, $gameId, $roundId, $rules['anticheat_rate'], $now);
            } catch (\Throwable $e) {
                Log::warning('AntiCheat interval check failed: ' . $e->getMessage());
            }
        }

        // 3) 赔付封顶：窗口内累计赔付超上限
        if (isset($rules['anticheat_payout'])) {
            try {
                self::checkPayout($userId, $gameId, $roundId, $rules['anticheat_payout'], $now, (string) ($payload['win_amount'] ?? '0'));
            } catch (\Throwable $e) {
                Log::warning('AntiCheat payout check failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * 实时检测用规则（Redis 60s 缓存，避免每局结算事件都查库）。
     * 只取实时用到的类型；配置变更最多 60s 生效。
     */
    private static function realtimeRules(): array
    {
        try {
            $cached = Redis::get('ac:rules:realtime');
            if (is_string($cached) && $cached !== '') {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AntiCheat rules cache read failed: ' . $e->getMessage());
        }

        $map = [];
        foreach (self::rules() as $rule) {
            $type = (string) $rule->type;
            if (!in_array($type, ['anticheat_rate', 'anticheat_payout'], true)) {
                continue;
            }
            $map[$type] = [
                'name'   => (string) $rule->name,
                'type'   => $type,
                'config' => (string) $rule->config,
                'action' => (string) $rule->action,
            ];
        }

        try {
            Redis::setex('ac:rules:realtime', 60, json_encode($map, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::warning('AntiCheat rules cache write failed: ' . $e->getMessage());
        }

        return $map;
    }

    /**
     * 投注速率：ac:rate:{uid}:{bucket} INCR，窗口内对局数 ≥ max_rounds 触发。
     */
    private static function checkRate(int $userId, int $gameId, string $roundId, array $rule, int $now): void
    {
        $config    = json_decode((string) ($rule['config'] ?? ''), true) ?? [];
        $windowSec = (int) ($config['window_seconds'] ?? 60);
        $maxRounds = (int) ($config['max_rounds'] ?? 30);
        $bucket    = (int) floor($now / $windowSec);

        $key   = "ac:rate:{$userId}:{$bucket}";
        $count = (int) Redis::incr($key);
        Redis::expire($key, $windowSec + 60);

        if ($count >= $maxRounds) {
            self::recordHit($userId, $gameId, $roundId, $rule, [
                'template' => 'rate',
                'ratio'    => $count / max(1, $maxRounds),
                'detail'   => ['count' => $count, 'max_rounds' => $maxRounds, 'window_seconds' => $windowSec],
            ]);
        }
    }

    /**
     * 间隔模式：相邻对局间隔 ≤ gap_ms_max 连续 min_streak 次（ac:last + ac:streak）。
     * 一旦间隔超限立即重置连击，避免把人工慢节奏误判成机器人。
     */
    private static function checkInterval(int $userId, int $gameId, string $roundId, array $rule, int $now): void
    {
        $config    = json_decode((string) ($rule['config'] ?? ''), true) ?? [];
        $gapMsMax  = (int) ($config['gap_ms_max'] ?? 5000);
        $minStreak = (int) ($config['min_streak'] ?? 10);

        $lastKey   = "ac:last:{$userId}";
        $streakKey = "ac:streak:{$userId}";

        $prev = (int) Redis::get($lastKey);
        if ($prev > 0 && $now > $prev && ($now - $prev) * 1000 <= $gapMsMax) {
            $streak = (int) Redis::incr($streakKey);
            Redis::expire($streakKey, 300);
            if ($streak >= $minStreak) {
                self::recordHit($userId, $gameId, $roundId, $rule, [
                    'template' => 'interval',
                    'ratio'    => $streak / max(1, $minStreak),
                    'detail'   => ['streak' => $streak, 'min_streak' => $minStreak, 'gap_ms_max' => $gapMsMax],
                ]);
            }
        } else {
            Redis::setex($streakKey, 300, '1');
        }
        Redis::setex($lastKey, 300, (string) $now);
    }

    /**
     * 赔付封顶：ac:payout:{uid}:{bucket} INCRBY，窗口内累计赔付 ≥ max_payout 触发。
     */
    private static function checkPayout(int $userId, int $gameId, string $roundId, array $rule, int $now, string $winAmount): void
    {
        if (bccomp($winAmount, '0', 4) <= 0) {
            return; // 无赔付不计入窗口
        }
        $config    = json_decode((string) ($rule['config'] ?? ''), true) ?? [];
        $windowSec = (int) ($config['window_minutes'] ?? 60) * 60;
        $maxPayout = (float) ($config['max_payout'] ?? 1000);
        $bucket    = (int) floor($now / $windowSec);

        $key   = "ac:payout:{$userId}:{$bucket}";
        $total = (float) Redis::incrbyfloat($key, (float) $winAmount);
        Redis::expire($key, $windowSec + 60);

        if ($total >= $maxPayout) {
            self::recordHit($userId, $gameId, $roundId, $rule, [
                'template' => 'payout',
                'ratio'    => $maxPayout > 0 ? $total / $maxPayout : 0,
                'detail'   => ['total' => round($total, 4), 'max_payout' => $maxPayout, 'window_seconds' => $windowSec],
            ]);
        }
    }

    /**
     * 增量批处理：处理 id > $sinceId 的 bet/settle 日志，返回新游标（最大已处理 id）。
     *
     * @return int 新游标；无数据时原样返回 $sinceId
     */
    public static function runBatch(int $sinceId, int $limit = 5000): int
    {
        $rows = GamePlayLog::where('id', '>', $sinceId)
            ->whereIn('action', ['bet', 'settle'])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->all();

        if ($rows === []) {
            return $sinceId;
        }

        $cursor = $sinceId;
        $rules = self::rules();
        $rulesByType = [];
        foreach ($rules as $rule) {
            $rulesByType[$rule->type] = $rule;
        }

        // 按 (user, game) 分组，组内汇总 + 检测
        $groups = [];
        foreach ($rows as $row) {
            $key = $row->user_id . ':' . $row->game_id;
            $groups[$key][] = $row;
            $cursor = max($cursor, (int) $row->id);
        }

        foreach ($groups as $rows) {
            $userId = (int) $rows[0]->user_id;
            $gameId = (int) $rows[0]->game_id;

            foreach ($rows as $row) {
                self::upsertDailyStat($userId, $gameId, substr((string) $row->started_at, 0, 10), $row);
            }

            if ($rulesByType !== []) {
                self::detect($userId, $gameId, $rulesByType);
            }
        }

        return $cursor;
    }

    /**
     * 读取生效的反作弊规则（status=1，灰启规则需运营开启）。
     */
    private static function rules(): array
    {
        return RiskRule::where('type', 'like', 'anticheat_%')
            ->where('status', 1)
            ->orderBy('priority', 'desc')
            ->get()
            ->all();
    }

    /**
     * 每日汇总增量累加（uk user+game+date 幂等）。
     * ponytail: 单进程 worker（count=1）下 firstOrNew+save 无竞态；
     * 并发扩容需改 ON DUPLICATE KEY UPDATE。
     */
    private static function upsertDailyStat(int $userId, int $gameId, string $date, GamePlayLog $row): void
    {
        $stat = AntiCheatDailyStat::where('user_id', $userId)
            ->where('game_id', $gameId)
            ->where('stat_date', $date)
            ->first();

        if ($stat === null) {
            $stat = new AntiCheatDailyStat();
            $stat->id = SnowflakeService::generate();
            $stat->user_id = $userId;
            $stat->game_id = $gameId;
            $stat->stat_date = $date;
            $stat->rounds = 0;
            $stat->wins = 0;
            $stat->bets = '0';
            $stat->wins_total = 0;
            $stat->plays_30d = 0;
            $stat->wins_30d = 0;
            $stat->active_seconds = 0;
        }

        $stat->rounds = $stat->rounds + 1; // 按日志行累计（round_id 去重留待跨批去重表）
        if ($row->action === 'settle') {
            $stat->wins = $stat->wins + (($row->result === 'win') ? 1 : 0);
            $stat->wins_total = $stat->wins_total + (($row->result === 'win') ? 1 : 0);
        }
        if ($row->action === 'bet' && $row->bet_amount !== null) {
            $stat->bets = bcadd((string) $stat->bets, (string) $row->bet_amount, 4);
        }
        $stat->save();
    }

    /**
     * 加载 7 天窗口内对局并按 round_id 组装 bet/won 序列。
     */
    private static function loadRounds(int $userId, int $gameId): array
    {
        $windowDays = (int) config('anticheat.window_days', 7);
        $rows = GamePlayLog::where('user_id', $userId)
            ->where('game_id', $gameId)
            ->whereIn('action', ['bet', 'settle'])
            ->where('started_at', '>=', date('Y-m-d H:i:s', strtotime("-{$windowDays} days")))
            ->orderBy('id')
            ->get()
            ->all();

        $byRound = [];
        foreach ($rows as $row) {
            $roundId = (string) $row->round_id;
            if ($roundId === '') {
                continue;
            }
            $byRound[$roundId] ??= ['bet' => null, 'won' => false, 't' => (string) $row->started_at];
            if ($row->action === 'bet') {
                $byRound[$roundId]['bet'] = (float) ($row->bet_amount ?? 0);
            } elseif ($row->action === 'settle') {
                $byRound[$roundId]['won'] = $row->result === 'win';
                if ($byRound[$roundId]['bet'] === null) {
                    $byRound[$roundId]['bet'] = (float) ($row->bet_amount ?? 0);
                }
            }
        }

        usort($byRound, static fn (array $a, array $b) => strcmp($a['t'], $b['t']));

        return array_values($byRound);
    }

    /**
     * 对单用户单游戏执行全部规则检测：命中写事件（幂等）+ 扣信任分（白名单豁免）。
     */
    private static function detect(int $userId, int $gameId, array $rulesByType): void
    {
        $rounds = self::loadRounds($userId, $gameId);

        foreach ($rulesByType as $type => $rule) {
            $config = json_decode((string) $rule->config, true) ?? [];

            $result = match ($type) {
                'anticheat_bet_pattern' => AntiCheatDetector::detectBetPattern($rounds, $config),
                'anticheat_rate'        => AntiCheatDetector::detectRate($rounds, $config),
                'anticheat_duration'    => AntiCheatDetector::detectDuration($rounds, $config),
                default                 => ['matched' => false],
            };

            if (empty($result['matched'])) {
                continue;
            }

            // uk(user_id, rule_type, stat_date) 幂等：当日同规则只记一次（实时/批处理共用）
            self::recordHit($userId, $gameId, '', [
                'name'   => (string) $rule->name,
                'type'   => $type,
                'config' => (string) $rule->config,
                'action' => (string) $rule->action,
            ], [
                'template' => $result['template'] ?? '',
                'ratio'    => $result['ratio'] ?? 0,
                'detail'   => $result['evidence'] ?? [],
            ]);
        }
    }

    /**
     * 写命中事件（uk user+rule+date 幂等）+ 扣信任分（白名单豁免）。
     * 实时与批处理共用：当日同规则只记一次，重复命中仅多一次 exists 查询。
     */
    private static function recordHit(int $userId, int $gameId, string $roundId, array $rule, array $evidence): void
    {
        $type = (string) $rule['type'];
        $date = date('Y-m-d');

        $exists = AntiCheatEvent::where('user_id', $userId)
            ->where('rule_type', $type)
            ->where('stat_date', $date)
            ->exists();
        if ($exists) {
            return;
        }

        $config = json_decode((string) ($rule['config'] ?? ''), true) ?? [];

        $event = new AntiCheatEvent();
        $event->id = SnowflakeService::generate();
        $event->user_id = $userId;
        $event->game_id = $gameId;
        $event->rule_type = $type;
        $event->rule_name = (string) ($rule['name'] ?? '');
        $event->severity = self::severity((string) ($rule['action'] ?? 'warn'));
        $event->score_delta = (int) ($config['score_delta'] ?? 0);
        $event->action = (string) ($rule['action'] ?? 'warn');
        $event->evidence = json_encode($evidence, JSON_UNESCAPED_UNICODE);
        $event->round_id = $roundId;
        $event->stat_date = $date;
        $event->created_at = date('Y-m-d H:i:s');
        $event->save();

        self::applyTrustPenalty($userId, $event->score_delta);
    }

    /**
     * 信任分扣减：白名单用户只记事件不扣分；分数夹取 0-100 并刷新带位。
     */
    private static function applyTrustPenalty(int $userId, int $scoreDelta): void
    {
        $trust = UserTrust::firstOrNew(['user_id' => $userId]);
        if ($trust->whitelisted === 1) {
            return;
        }
        if (!$trust->exists) {
            $trust->id = SnowflakeService::generate();
            $trust->score = 100;
            $trust->hit_count = 0;
        }
        $trust->score = max(0, min(100, $trust->score + $scoreDelta));
        $trust->band = self::bandFor($trust->score);
        $trust->hit_count = $trust->hit_count + 1;
        $trust->last_hit_at = date('Y-m-d H:i:s');
        $trust->save();
    }

    /**
     * 读取用户信任带位（提现/结算链路调用）：无记录或白名单 → normal（豁免）。
     * 带位由当前分数实时推导，保证手动调分后立即生效。
     */
    public static function trustBand(int $userId): string
    {
        $trust = UserTrust::where('user_id', $userId)->first();
        if ($trust === null || $trust->whitelisted === 1) {
            return 'normal';
        }

        return self::bandFor((int) $trust->score);
    }

    /**
     * 分数 → 信任带位: >=80 normal / >=60 observe / >=30 restrict / else freeze
     */
    public static function bandFor(int $score): string
    {
        if ($score >= 80) {
            return 'normal';
        }
        if ($score >= 60) {
            return 'observe';
        }
        if ($score >= 30) {
            return 'restrict';
        }

        return 'freeze';
    }

    /**
     * 规则 action → 事件严重级别: block=3 / warn=2 / 其他=1
     */
    private static function severity(string $action): int
    {
        return match ($action) {
            'block' => 3,
            'warn'  => 2,
            default => 1,
        };
    }
}
