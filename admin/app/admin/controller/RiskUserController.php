<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\AntiCheatEvent;
use common\model\GamePlayLog;
use common\model\RiskLog;
use common\model\User;
use common\model\UserTrust;
use common\model\UserWallet;
use app\service\WalletScope;
use app\service\WalletService;
use hg\apidoc\annotation as Apidoc;
use support\Db;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("异常用户")
 * @Apidoc\Group("risk")
 *
 * 队列来自 user_trust（H5 信任分）；时间线合并 risk_log + play_log + anticheat_event。
 */
class RiskUserController extends BaseController
{
    /**
     * @Apidoc\Title("异常用户队列")
     * @Apidoc\Desc("score_min=信任分上限（<=N），from/to=最近命中时间窗口")
     */
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
                'username' => (string) ($users[$userId]->username ?? '未知'),
                'score' => (int) $row->score,
                'band' => (string) $row->band,
                'hit_count' => (int) $row->hit_count,
                'last_hit_at' => (string) $row->last_hit_at,
                'whitelisted' => (int) $row->whitelisted,
            ];
        }

        return $this->success(['total' => $total, 'items' => $items]);
    }

    /**
     * @Apidoc\Title("用户风控时间线")
     * @Apidoc\Desc("合并 risk_log / play_log / anticheat_event，按时间倒序")
     */
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

    /**
     * @Apidoc\Title("冻结账户")
     * @Apidoc\Desc("调 M1 WalletService::lock 冻结平台可用余额，并写 risk_log(action=block) 留痕")
     */
    public function hold(Request $request, string $hashid): Response
    {
        $userId = $this->decodeId($hashid);
        if (!User::find($userId)) {
            return $this->fail('用户不存在');
        }

        $wallet = UserWallet::where('user_id', $userId)->first();
        $amount = (string) ($wallet->balance ?? '0');
        if (bccomp($amount, '0', 8) <= 0) {
            return $this->fail('用户无可冻结余额');
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
                $log->detail = '管理端人工冻结（M6 hold）';
                $log->created_at = date('Y-m-d H:i:s');
                $log->save();
                return true;
            });
        } catch (\Throwable $e) {
            return $this->fail('冻结失败：' . $e->getMessage());
        }

        if ($ok !== true) {
            return $this->fail('冻结失败：余额不足');
        }

        return $this->success(['user_id' => $this->encodeId($userId), 'frozen_amount' => $amount]);
    }
}
