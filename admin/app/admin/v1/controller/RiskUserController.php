<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use common\model\AntiCheatEvent;
use common\model\GamePlayLog;
use common\model\RiskLog;
use common\model\Transaction;
use common\model\User;
use common\model\UserTrust;
use common\model\UserWallet;
use app\service\WalletScope;
use app\service\WalletService;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Db;
use support\Log;
use support\Request;
use support\Response;

/**
 * 队列来自 user_trust（H5 信任分）；时间线合并 risk_log + play_log + anticheat_event。
 */
#[Apidoc\Title("异常用户")]
#[Apidoc\Group("risk")]
class RiskUserController extends BaseController
{
    #[Apidoc\Title("异常用户队列")]
    #[Apidoc\Desc("score_min=信任分上限（<=N），from/to=最近命中时间窗口")]
    public function users(Request $request): Response
    {
        $query = UserTrust::query();
        if ($request->get('score_min') !== null && $request->get('score_min') !== '') {
            $query->where('score', '<=', (int) $request->get('score_min'));
        }
        if ($request->get('from')) {
            $query->where('last_hit_at', '>=', (string) $request->get('from'));
        }
        if ($request->get('to')) {
            $query->where('last_hit_at', '<=', (string) $request->get('to'));
        }

        $page = max(1, (int) $request->get('page', 1));
        $size = min(100, max(1, (int) $request->get('size', 20)));
        $total = (clone $query)->count();
        $rows = $query->orderBy('score', 'asc')->orderBy('last_hit_at', 'desc')
            ->forPage($page, $size)->get()->all();

        $userIds = array_map(static fn ($row) => (int) $row->user_id, $rows);
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $items = [];
        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            $items[] = [
                'user_id' => $this->encodeId($userId),
                'username' => (string) ($users[$userId]->username ?? trans('Unknown')),
                'score' => (int) $row->score,
                'band' => (string) $row->band,
                'hit_count' => (int) $row->hit_count,
                'last_hit_at' => (string) $row->last_hit_at,
                'whitelisted' => (int) $row->whitelisted,
            ];
        }

        return $this->success(['total' => $total, 'items' => $items]);
    }

    #[Apidoc\Title("用户风控时间线")]
    #[Apidoc\Desc("合并 risk_log / play_log / anticheat_event，按时间倒序")]
    public function timeline(Request $request, string $hashid): Response
    {
        $userId = $this->decodeId($hashid);

        $events = [];
        foreach (RiskLog::where('user_id', $userId)->orderBy('created_at', 'desc')->limit(100)->get() as $row) {
            $events[] = [
                'time' => (string) $row->created_at,
                'source' => 'risk',
                'type' => (string) $row->type,
                'action' => (string) $row->action,
                'result' => (string) $row->result,
                'detail' => mb_substr((string) $row->detail, 0, 200),
            ];
        }
        foreach (GamePlayLog::where('user_id', $userId)->orderBy('created_at', 'desc')->limit(100)->get() as $row) {
            $events[] = [
                'time' => (string) ($row->created_at ?? $row->started_at),
                'source' => 'play',
                'type' => (string) $row->action,
                'action' => (string) $row->result,
                'result' => '',
                'detail' => 'bet=' . (string) $row->bet_amount . ' win=' . (string) $row->win_amount,
            ];
        }
        foreach (AntiCheatEvent::where('user_id', $userId)->orderBy('created_at', 'desc')->limit(100)->get() as $row) {
            $events[] = [
                'time' => (string) $row->created_at,
                'source' => 'anticheat',
                'type' => (string) $row->rule_type,
                'action' => (string) $row->action,
                'result' => '',
                'detail' => mb_substr((string) ($row->evidence ?? ''), 0, 200),
            ];
        }

        usort($events, static fn ($a, $b) => strcmp((string) $b['time'], (string) $a['time']));

        return $this->success([
            'user_id' => $this->encodeId($userId),
            'total' => count($events),
            'events' => array_slice($events, 0, 200),
        ]);
    }

    #[Apidoc\Title("冻结账户")]
    #[Apidoc\Desc("调 M1 WalletService::lock 冻结平台可用余额，并写 risk_log(action=block) 留痕")]
    public function hold(Request $request, string $hashid): Response
    {
        $userId = $this->decodeId($hashid);
        if (!User::find($userId)) {
            return $this->fail(trans('User not found'));
        }

        $wallet = UserWallet::where('user_id', $userId)->first();
        $amount = (string) ($wallet->balance ?? '0');
        if (bccomp($amount, '0', 8) <= 0) {
            return $this->fail(trans('User has no freezable balance'));
        }

        $logId = $this->generateId();

        try {
            $ok = Db::transaction(function () use ($userId, $amount, $logId) {
                if (!WalletService::lock($userId, WalletScope::platform(), $amount, 'risk_hold', $logId)) {
                    return false;
                }
                $log = new RiskLog();
                $log->id = $logId;
                $log->user_id = $userId;
                $log->rule_id = 0;
                $log->type = 'manual_hold';
                $log->action = 'block';
                $log->context = json_encode(['amount' => $amount]);
                $log->result = 'blocked';
                $log->detail = trans('Manual freeze by admin (M6 hold)');
                $log->created_at = date('Y-m-d H:i:s');
                $log->save();
                return true;
            });
        } catch (\Throwable $e) {
            return $this->fail(trans('Freeze failed: ') . $e->getMessage());
        }

        if ($ok !== true) {
            return $this->fail(trans('Freeze failed: insufficient balance'));
        }

        return $this->success(['user_id' => $this->encodeId($userId), 'frozen_amount' => $amount]);
    }

    /**
     * 解除冻结（与 hold 配对）。
     *
     * 纯搬移：frozen→available，释放量不得超过 frozen_balance、重复释放被拒，桶内总额不变。
     *
     * 归因口径（2026-09-28 起，per-hold 子台账）：冻结不再是单池列，`frozen_balance` 只是聚合缓存，
     * 权威台账是 game_wallet_hold。释放按笔消费 —— 下面取到的那笔冻结（最新一笔 risk_hold 的 ref）
     * 被提到队首先吃，剩下的量再按 FIFO（最老优先）继续吃；实际消费了哪几笔记在同笔流水的 remark
     * （`hold:<id>,...`），逐笔份额落在台账行的 remaining/status/released_at。不变量
     * `frozen_balance == Σhold.remaining` 由 WalletService 在同一事务内断言，对不上即整笔回滚。
     * （此前是「只能吃最近一笔、归因可能落错笔」，因为当时没有台账。）
     */
    #[Apidoc\Title("解除冻结")]
    #[Apidoc\Desc("与 hold 配对：frozen→available 纯搬移，不铸币；按笔消费冻结子台账（先吃最新一笔风险冻结对应的 hold，不足部分按 FIFO 继续），实际消费的 hold 记在释放流水 remark")]
    public function release(Request $request, string $hashid): Response
    {
        $userId = $this->decodeId($hashid);
        if (!User::find($userId)) {
            return $this->fail(trans('User not found'));
        }

        // 释放量缺省全额（与 hold 全额冻结对称）。语法闸先行：bcmath 对 '1e5'/'abc' 抛 ValueError，
        // 直接冒出去就是 500（同 SelfProvider::isAmountSyntaxValid 的理由）；只认标量，数组/null 一律判非法。
        $raw = $request->input('amount');
        $requested = null;
        if ($raw !== null && $raw !== '') {
            if (!is_string($raw) && !is_int($raw) && !is_float($raw)) {
                return $this->fail(trans('Invalid release amount format'));
            }
            try {
                $requested = bcadd((string) $raw, '0', 8);
            } catch (\ValueError) {
                return $this->fail(trans('Invalid release amount format'));
            }
        }

        // 追溯「释放的是哪一笔」：hold 把写进 risk_log 的主键当 ref_id，冻结流水行带着它。
        // 释放行复用同一个 ref_id ⇒ 释放与冻结在流水上 (ref_type, ref_id) 完全相同，
        // 用 type=lock|unlock 分辨方向，天然配对可查。找不到原冻结行时退化为「释放 + 管理员 id」。
        $holdRefId = (int) (Transaction::where('user_id', $userId)
            ->where('scope', WalletScope::PLATFORM)
            ->where('type', WalletService::TYPE_LOCK)
            ->where('ref_type', 'risk_hold')
            ->orderByDesc('id')
            ->value('ref_id') ?? 0);
        $refType = $holdRefId > 0 ? 'risk_hold' : 'risk_hold_release';
        $refId = $holdRefId > 0 ? $holdRefId : (int) ($request->adminId ?? 0);

        $logId = $this->generateId();
        $released = '0.00000000';
        $failMsg = trans('Unfreeze failed');

        try {
            $ok = Db::transaction(function () use ($userId, $requested, $refType, $refId, $logId, &$released, &$failMsg) {
                // 冻结余额必须在事务内锁行读：先读后动是 check-then-act，并发两次释放会各读到同一个
                // frozen、各搬一笔走（双记）。锁行后第二个事务读到的是 0，被下面的闸挡下。
                $wallet = UserWallet::where('user_id', $userId)->lockForUpdate()->first();
                $frozen = (string) ($wallet->frozen_balance ?? '0');

                $amount = $requested ?? $frozen;
                if (bccomp($amount, '0', 8) <= 0) {
                    $failMsg = trans('User has no frozen balance');
                    return false;
                }
                if (bccomp($amount, $frozen, 8) > 0) {
                    $failMsg = trans('Release amount exceeds frozen balance');
                    return false;
                }

                if (!WalletService::unlock($userId, WalletScope::platform(), $amount, $refType, $refId)) {
                    return false;
                }
                $released = $amount;

                $log = new RiskLog();
                $log->id = $logId;
                $log->user_id = $userId;
                $log->rule_id = 0;
                $log->type = 'manual_release';
                $log->action = 'unblock';
                $log->context = json_encode(['amount' => $amount, 'hold_ref_id' => $refType === 'risk_hold' ? $refId : 0]);
                $log->result = 'unblocked';
                $log->detail = trans('Manual unfreeze by admin (paired with M6 hold)');
                $log->created_at = date('Y-m-d H:i:s');
                $log->save();

                return true;
            });
        } catch (\Throwable $e) {
            // 钱路的意外失败必须留痕：管理员屏幕上一句话不是痕迹（OperationLog 记的是请求，不记异常）。
            // 文案笼统、不带 $e->getMessage()：原始异常可能带 SQL 片段/表列名/驱动文本，
            // 与同仓资金端点同款（app/admin/v1/WithdrawReviewTrait.php:190-196、
            // app/admin/v1/controller/WithdrawController.php:343-346）。
            Log::error('Risk release failed: ' . $e->getMessage(), [
                'user_id'   => $userId,
                'requested' => $requested ?? '(full frozen)',
                'ref_type'  => $refType,
                'ref_id'    => $refId,
                'released'  => $released,
            ]);

            return $this->fail(trans('Unfreeze failed, please try again later'), 500);
        }

        if ($ok !== true) {
            return $this->fail($failMsg);
        }

        return $this->success(['user_id' => $this->encodeId($userId), 'released_amount' => $released]);
    }
}
