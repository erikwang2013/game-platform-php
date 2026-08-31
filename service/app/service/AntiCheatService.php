<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

use app\common\SnowflakeService;
use app\model\AntiCheatDailyStat;
use app\model\AntiCheatEvent;
use app\model\GamePlayLog;
use app\model\RiskRule;
use app\model\UserTrust;
use app\service\anticheat\AntiCheatDetector;
use support\Log;

/**
 * 反作弊服务（P0+P1 最小区间）
 *
 * 数据流: settle 完成 → EventBus::emit(anticheat.round_finished)（realtime 后置占位）
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
     * 实时检测（速率/间隔/赔付封顶）后置；当前只做事件锚点，批处理走 runBatch。
     */
    public static function onRoundFinished(array $payload): void
    {
        // realtime 检测后置：见设计稿 §6 后置清单，接入时在此挂 Redis 速率/间隔检测
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
        $date = date('Y-m-d');

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

            // uk(user_id, rule_type, stat_date) 幂等：当日同规则只记一次（单进程无竞态）
            $exists = AntiCheatEvent::where('user_id', $userId)
                ->where('rule_type', $type)
                ->where('stat_date', $date)
                ->exists();
            if ($exists) {
                continue;
            }

            $event = new AntiCheatEvent();
            $event->id = SnowflakeService::generate();
            $event->user_id = $userId;
            $event->game_id = $gameId;
            $event->rule_type = $type;
            $event->rule_name = (string) $rule->name;
            $event->severity = self::severity((string) $rule->action);
            $event->score_delta = (int) ($config['score_delta'] ?? 0);
            $event->action = (string) $rule->action;
            $event->evidence = json_encode([
                'template' => $result['template'] ?? '',
                'ratio' => $result['ratio'] ?? 0,
                'detail' => $result['evidence'] ?? [],
            ], JSON_UNESCAPED_UNICODE);
            $event->stat_date = $date;
            $event->created_at = date('Y-m-d H:i:s');
            $event->save();

            self::applyTrustPenalty($userId, $event->score_delta);
        }
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
