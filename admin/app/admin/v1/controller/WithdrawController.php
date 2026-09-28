<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use common\model\PlatformConfig;
use common\model\Transaction;
use common\model\User;
use common\model\UserWallet;
use common\model\WithdrawLimit;
use common\model\WithdrawOrder;
use common\service\NotificationService;
use common\service\PayoutService;
use support\Db;
use support\Log;
use support\Request;
use support\Response;

#[Apidoc\Title("提现管理")]
#[Apidoc\Group("withdraw")]
class WithdrawController extends BaseController
{
    #[Apidoc\Title("提现订单列表")]
    #[Apidoc\Desc("分页获取提现订单列表，支持按状态筛选")]
    #[Apidoc\Url("/admin/v1/withdraw/orders")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "page", type: "int", require: false, desc: "页码")]
    #[Apidoc\Param(name: "limit", type: "int", require: false, desc: "每页数量")]
    #[Apidoc\Param(name: "status", type: "string", require: false, desc: "订单状态(pending,approved,rejected,completed)")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "订单ID(hashid编码)")]
    public function orders(Request $request): Response
    {
        $page   = (int) $request->input('page', 1);
        $limit  = (int) $request->input('limit', 15);
        $status = $request->input('status');

        $query = WithdrawOrder::with('user');
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('created_at', 'desc')
                      ->get()
                      ->map(function ($order) {
                          $data = $order->toArray();
                          $data = $this->encodeIds($data);
                          if ($order->user) {
                              $data['user'] = $this->encodeIds([
                                  'id'       => $order->user->id,
                                  'username' => $order->user->username,
                              ]);
                          }
                          return $data;
                      });

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    #[Apidoc\Title("审核提现")]
    #[Apidoc\Desc("审批或拒绝提现申请")]
    #[Apidoc\Url("/admin/v1/withdraw/review")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "order_id", type: "string", require: true, desc: "订单ID(hashid编码)")]
    #[Apidoc\Param(name: "action", type: "string", require: true, desc: "操作(approve通过,reject拒绝,confirm确认打款)")]
    #[Apidoc\Param(name: "note", type: "string", require: false, desc: "审核备注")]
    public function review(Request $request): Response
    {
        $validator = validator($request->all(), [
            'order_id' => 'required|string',
            'action'   => 'required|string|in:approve,reject,confirm',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $orderId = $this->decodeId($request->input('order_id'));
        if (!WithdrawOrder::find($orderId)) {
            return $this->fail('订单不存在', 404);
        }

        $action = $request->input('action');
        $note   = $request->input('note', '');
        $adminId = (int) $request->adminId;
        $requireDual = PlatformConfig::get('withdraw', 'require_dual_review', 'off');
        $dualOn = in_array((string) $requireDual, ['on', '1', 'true'], true);

        if ($action === 'confirm') {
            if (!$dualOn) {
                return $this->fail('双重审核未启用', 422);
            }
            $payload = [
                'status'       => 'approved',
                'confirmed_by' => $adminId,
                'confirmed_at' => date('Y-m-d H:i:s'),
                'reviewed_at'  => date('Y-m-d H:i:s'),
            ];
            if ($note !== '') {
                $payload['review_note'] = $note;
            }
            // 补单语义：双审开启前已批准但从未确认的历史订单（approved + confirmed_by=0）
            // 允许补确认；若原订单无第一审核人（reviewer_id 为空/0），确认人即审核人并写入
            $currentReviewer = (int) WithdrawOrder::where('id', $orderId)->value('reviewer_id');
            if ($currentReviewer <= 0) {
                $payload['reviewer_id'] = $adminId;
            }

            $flipped = WithdrawOrder::where('id', $orderId)
                ->where(function ($q) use ($adminId) {
                    // 常规双审：初审后待另一管理员确认
                    $q->where('status', 'pending')
                      ->where('reviewer_id', '>', 0)
                      ->where('reviewer_id', '!=', $adminId);
                    // 补单：已批准未确认的历史订单，仍需满足双审（reviewer_id 非本人或为空）
                    $q->orWhere(function ($q2) use ($adminId) {
                        $q2->where('status', 'approved')
                           ->where('confirmed_by', 0)
                           ->where(function ($q3) use ($adminId) {
                               $q3->whereNull('reviewer_id')
                                  ->orWhere('reviewer_id', '!=', $adminId);
                           });
                    });
                })
                ->update($payload);
            if (!$flipped) {
                return $this->fail('无法确认：需另一管理员复核，或订单状态不符', 422);
            }

            $order = WithdrawOrder::find($orderId);
            NotificationService::send(
                $order->user_id,
                'withdraw',
                'Withdrawal Approved',
                "Your withdrawal of {$order->platform_amount} platform tokens has been approved.",
                'withdraw',
                $order->id
            );
            return $this->success([], '二次确认通过');
        }

        if ($action === 'approve') {
            if ($dualOn) {
                // 第一审核：仅记录 reviewer_id，保持 pending，等待 confirm
                $flipped = WithdrawOrder::where('id', $orderId)
                    ->where('status', 'pending')
                    ->where(function ($q) {
                        $q->where('reviewer_id', 0)->orWhereNull('reviewer_id');
                    })
                    ->update([
                        'reviewer_id' => $adminId,
                        'review_note' => $note,
                        'reviewed_at' => date('Y-m-d H:i:s'),
                    ]);
                if (!$flipped) {
                    return $this->fail('该订单已处理或已有第一审核人', 422);
                }
                return $this->success([], '初审通过，等待另一管理员确认');
            }

            // 原子状态翻转：仅 pending 可处理，防止并发双击重复审核
            $flipped = WithdrawOrder::where('id', $orderId)
                ->where('status', 'pending')
                ->update([
                    'status'      => 'approved',
                    'reviewer_id' => $adminId,
                    'review_note' => $note,
                    'reviewed_at' => date('Y-m-d H:i:s'),
                ]);
            if (!$flipped) {
                return $this->fail('该订单已处理', 422);
            }

            $order = WithdrawOrder::find($orderId);
            NotificationService::send(
                $order->user_id,
                'withdraw',
                'Withdrawal Approved',
                "Your withdrawal of {$order->platform_amount} platform tokens has been approved.",
                'withdraw',
                $order->id
            );

            return $this->success([], '审核通过');
        }

        // reject: 状态翻转 + 退款 + 流水同一事务，失败整体回滚，订单保持 pending 可重试
        try {
            return Db::transaction(function () use ($orderId, $note, $adminId) {
                $flipped = WithdrawOrder::where('id', $orderId)
                    ->where('status', 'pending')
                    ->update([
                        'status'      => 'rejected',
                        'reviewer_id' => $adminId,
                        'review_note' => $note,
                        'reviewed_at' => date('Y-m-d H:i:s'),
                    ]);
                if (!$flipped) {
                    throw new \RuntimeException('order already processed');
                }

                $order = WithdrawOrder::find($orderId);

                $refunded = UserWallet::addBalance($order->user_id, $order->platform_amount);
                if (!$refunded) {
                    throw new \RuntimeException('refund failed');
                }

                $wallet = UserWallet::where('user_id', $order->user_id)->first();
                $transaction = new Transaction();
                $transaction->id            = $this->generateId();
                $transaction->user_id       = $order->user_id;
                $transaction->type          = 'refund';
                $transaction->amount        = $order->platform_amount;
                $transaction->balance_after = $wallet ? $wallet->balance : '0';
                $transaction->ref_type      = 'withdraw';
                $transaction->ref_id        = $order->id;
                $transaction->remark        = '提现驳回退款';
                $transaction->save();

                return $this->success([], '已驳回并退款');
            });
        } catch (\Throwable $e) {
            if (WithdrawOrder::where('id', $orderId)->value('status') !== 'pending') {
                return $this->fail('该订单已处理', 422);
            }
            Log::error('Withdraw review refund failed: ' . $e->getMessage());
            return $this->fail('退款失败，请重试', 500);
        }
    }

    #[Apidoc\Title("全局提现开关")]
    #[Apidoc\Desc("启用或关闭全局提现功能")]
    #[Apidoc\Url("/admin/v1/withdraw/switch")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "enabled", type: "int", require: true, desc: "是否启用(0关闭,1启用)")]
    public function toggleSwitch(Request $request): Response
    {
        // GET 只读：界面 loadSwitch() 读的是 data.enabled / data.status，此前无 GET 路由（405）⇒ 恒显"已开启"
        if (strtoupper((string) $request->method()) === 'GET') {
            return $this->success($this->switchState());
        }

        $validator = validator($request->all(), [
            'enabled' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $enabled = (int) $request->input('enabled');
        PlatformConfig::set('withdraw', 'global_switch', $enabled, 'bool');

        return $this->success($this->switchState(), '操作成功');
    }

    /** 全局开关读数：global_switch 沿用原 PUT 响应键名，enabled/status 对齐管理端界面的两种读法 */
    private function switchState(): array
    {
        $enabled = (bool) PlatformConfig::get('withdraw', 'global_switch', false);

        return ['global_switch' => $enabled, 'enabled' => $enabled, 'status' => $enabled ? 1 : 0];
    }

    #[Apidoc\Title("设置提现限额")]
    #[Apidoc\Desc("设置全局提现限额参数")]
    #[Apidoc\Url("/admin/v1/withdraw/limits/set")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "daily_limit", type: "float", require: false, desc: "每日限额")]
    #[Apidoc\Param(name: "min_amount", type: "float", require: false, desc: "最小提现金额")]
    #[Apidoc\Param(name: "auto_approve_threshold", type: "float", require: false, desc: "自动审批阈值")]
    public function setLimits(Request $request): Response
    {
        $validator = validator($request->all(), [
            'daily_limit'           => 'nullable|numeric|min:0',
            'min_amount'            => 'nullable|numeric|min:0',
            'auto_approve_threshold' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $keys = ['daily_limit', 'min_amount', 'auto_approve_threshold'];

        // 界面上这三个值是「无档位」的全局限额，但报价优先读 withdraw_limit 档位行
        // （service/app/api/v1/controller/WithdrawController.php:95-104），而 install/install.sql:1359-1362
        // 把 default/verified/vip 三档全种下了 ⇒ 只写 platform_config 时回落分支不可达，运营改的数静默失效。
        // 故写穿到所有档位行。语义分工：本端点 = **全档位重置**；精调单一档位走 updateLimit()。
        // 列名映射：min_amount → single_min，daily_limit / auto_approve_threshold 同名；
        // single_max / monthly_limit 不在本端点范围内（保持各档既有值）。
        $tiers = WithdrawLimit::all();
        $newMin = $request->has('min_amount') && $request->input('min_amount') !== null
            ? (string) $request->input('min_amount')
            : null;

        // 逐档校验：新下限若高于某档既有上限，整笔拒绝——宁可让运营看见报错，也不写出一条自相矛盾的档位
        if ($newMin !== null) {
            foreach ($tiers as $tier) {
                // single_max=0 是「不限」，不与下限比较（同 updateLimit）
                if (bccomp((string) $tier->single_max, '0', 4) > 0
                    && bccomp($newMin, (string) $tier->single_max, 4) > 0
                ) {
                    return $this->fail(
                        "档位 {$tier->user_level} 的单笔最高（{$tier->single_max}）低于新的单笔最低（{$newMin}），整笔未生效",
                        422
                    );
                }
            }
        }

        // 档位行与回落配置同事务提交：分开写会出现「某档已生效、某档还是旧值 / 平台配置已变」的半写状态
        Db::transaction(function () use ($request, $keys, $tiers) {
            foreach ($tiers as $tier) {
                foreach (['daily_limit' => 'daily_limit', 'min_amount' => 'single_min', 'auto_approve_threshold' => 'auto_approve_threshold'] as $key => $column) {
                    if ($request->has($key) && $request->input($key) !== null) {
                        $tier->{$column} = (string) $request->input($key);
                    }
                }
                $tier->save();
            }

            foreach ($keys as $key) {
                if ($request->has($key) && $request->input($key) !== null) {
                    PlatformConfig::set('withdraw', $key, $request->input($key), 'decimal');
                }
            }
        });

        $limits = [];
        foreach ($keys as $key) {
            $limits[$key] = PlatformConfig::get('withdraw', $key, '0');
        }
        $limits['global_switch'] = PlatformConfig::get('withdraw', 'global_switch', false);

        return $this->success($limits, '操作成功');
    }

    #[Apidoc\Title("阶梯限额列表")]
    #[Apidoc\Desc("获取提现阶梯限额配置列表")]
    #[Apidoc\Url("/admin/v1/withdraw/limits/list")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    public function listLimits(Request $request): Response
    {
        $list = WithdrawLimit::all()->map(function ($limit) {
            $data = $limit->toArray();
            return $this->encodeIds($data);
        })->toArray();

        return $this->success(['list' => $list]);
    }

    #[Apidoc\Title("更新阶梯限额")]
    #[Apidoc\Desc("更新指定阶梯限额配置")]
    #[Apidoc\Url("/admin/v1/withdraw/limits/{hashid}")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Author("erik")]
    public function updateLimit(Request $request, string $hashid): Response
    {
        // fee_pct 列是 unsigned decimal(5,2)（上限 999.99），挡不住 >100 的费率；配 fee_max=0（不封顶）
        // 可把报价里的实收算成负数，而非正 fiat_amount 在 PayoutService 里已 fail-closed ⇒ 打款直接
        // 失败（下界另在 service 侧 withdrawQuote 兜底）。此题列均为 unsigned，负值本就存不进去，
        // 无校验时是撞 DB（1264 报 500）而非入库，这里把 500 收成 422。
        // 上界取 <100 而非 ≤100：fee_pct=100 会把实收吃成恰好 0，0 同样是坏数据（PayoutService 拒付），
        // 放行它只会给运营一个「设得进去、打款必失败」的档位。
        $validator = validator($request->all(), [
            'single_min'             => 'nullable|numeric|min:0',
            'single_max'             => 'nullable|numeric|min:0',
            'daily_limit'            => 'nullable|numeric|min:0',
            'monthly_limit'          => 'nullable|numeric|min:0',
            'fee_pct'                => 'nullable|numeric|min:0|lt:100',
            'fee_max'                => 'nullable|numeric|min:0',
            'auto_approve_threshold' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $id    = $this->decodeId($hashid);
        $limit = WithdrawLimit::find($id);

        if (!$limit) {
            return $this->fail('限制记录不存在', 404);
        }

        // 单笔下限不得高于上限（single_max=0 是「不限」，此时不比较）
        $singleMin = (string) $request->input('single_min', $limit->single_min);
        $singleMax = (string) $request->input('single_max', $limit->single_max);
        if (bccomp($singleMax, '0', 4) > 0 && bccomp($singleMin, $singleMax, 4) > 0) {
            return $this->fail('单笔最低不得高于单笔最高', 422);
        }

        $limit->fill($request->only([
            'single_min',
            'single_max',
            'daily_limit',
            'monthly_limit',
            'fee_pct',
            'fee_max',
            'auto_approve_threshold',
        ]));
        $limit->save();

        return $this->success($this->encodeIds($limit->toArray()), '更新成功');
    }

    #[Apidoc\Title("批量审核提现")]
    #[Apidoc\Desc("批量审批或拒绝提现申请")]
    #[Apidoc\Url("/admin/v1/withdraw/batch-review")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "ids", type: "array", require: true, desc: "订单ID数组(hashid编码)")]
    #[Apidoc\Param(name: "action", type: "string", require: true, desc: "操作(approve通过,reject拒绝)")]
    #[Apidoc\Param(name: "note", type: "string", require: false, desc: "审核备注")]
    public function batchReview(Request $request)
    {
        $validator = validator($request->all(), [
            'ids' => 'required|array|min:1',
            'action' => 'required|in:approve,reject',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $ids = $request->input('ids');
        $action = $request->input('action');
        $note = $request->input('note', '');
        $successCount = 0;
        $failedIds = [];

        foreach ($ids as $hashid) {
            $orderId = $this->decodeId($hashid);

            if ($action === 'approve') {
                $requireDual = PlatformConfig::get('withdraw', 'require_dual_review', 'off');
                $dualOn = in_array((string) $requireDual, ['on', '1', 'true'], true);
                if ($dualOn) {
                    $flipped = WithdrawOrder::where('id', $orderId)
                        ->where('status', 'pending')
                        ->where(function ($q) {
                            $q->where('reviewer_id', 0)->orWhereNull('reviewer_id');
                        })
                        ->update([
                            'reviewer_id' => $request->adminId,
                            'review_note' => $note,
                            'reviewed_at' => date('Y-m-d H:i:s'),
                        ]);
                    if ($flipped) $successCount++;
                    continue;
                }
                // 原子状态翻转，跳过已处理订单
                $flipped = WithdrawOrder::where('id', $orderId)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'approved',
                        'reviewer_id' => $request->adminId,
                        'review_note' => $note,
                        'reviewed_at' => date('Y-m-d H:i:s'),
                    ]);
                if ($flipped) $successCount++;
                continue;
            }

            // reject: 状态翻转 + 退款同一事务，失败回滚保持 pending 可重试
            try {
                $flipped = Db::transaction(function () use ($orderId, $note, $request) {
                    $flipped = WithdrawOrder::where('id', $orderId)
                        ->where('status', 'pending')
                        ->update([
                            'status' => 'rejected',
                            'reviewer_id' => $request->adminId,
                            'review_note' => $note,
                            'reviewed_at' => date('Y-m-d H:i:s'),
                        ]);
                    if (!$flipped) {
                        return false;
                    }
                    $order = WithdrawOrder::find($orderId);
                    if (!UserWallet::addBalance($order->user_id, $order->platform_amount)) {
                        throw new \RuntimeException('refund failed');
                    }
                    $wallet = UserWallet::where('user_id', $order->user_id)->first();
                    $transaction = new Transaction();
                    $transaction->id            = $this->generateId();
                    $transaction->user_id       = $order->user_id;
                    $transaction->type          = 'refund';
                    $transaction->amount        = $order->platform_amount;
                    $transaction->balance_after = $wallet ? $wallet->balance : '0';
                    $transaction->ref_type      = 'withdraw';
                    $transaction->ref_id        = $order->id;
                    $transaction->remark        = '批量审核退回: ' . $note;
                    $transaction->save();
                    return true;
                });
                if ($flipped) $successCount++;
            } catch (\Throwable $e) {
                Log::error('Withdraw batch review refund failed: ' . $e->getMessage());
                $failedIds[] = $hashid;
            }
        }

        return $this->success(['processed' => $successCount, 'failed' => $failedIds], "批量处理完成: {$successCount} 笔" . ($failedIds ? ", 失败 " . count($failedIds) . " 笔" : ''));
    }

    #[Apidoc\Title("执行打款")]
    #[Apidoc\Desc("对已审批的提现订单执行PayPal打款")]
    #[Apidoc\Url("/admin/v1/withdraw/execute-payout")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "order_id", type: "string", require: true, desc: "订单ID(hashid编码)")]
    public function executePayout(Request $request): Response
    {
        $validator = validator($request->all(), [
            'order_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $orderId = $this->decodeId($request->input('order_id'));
        $order = WithdrawOrder::find($orderId);
        if (!$order) {
            return $this->fail('订单不存在', 404);
        }

        $requireDual = PlatformConfig::get('withdraw', 'require_dual_review', 'off');
        $dualOn = in_array((string) $requireDual, ['on', '1', 'true'], true);
        if ($dualOn) {
            $confirmedBy = (int) ($order->confirmed_by ?? 0);
            $reviewerId  = (int) ($order->reviewer_id ?? 0);
            if ($confirmedBy <= 0 || $confirmedBy === $reviewerId) {
                return $this->fail('该订单尚未完成双重审核确认', 422);
            }
        }

        // 原子状态翻转 approved→processing：并发/重试只会有一个请求进入打款
        $flipped = WithdrawOrder::where('id', $orderId)
            ->where('status', 'approved')
            ->update(['status' => 'processing', 'payout_status' => 'processing']);
        if (!$flipped) {
            return $this->fail('该订单已完成或正在打款中', 422);
        }

        try {
            $result = PayoutService::execute($order);
            return $this->success($result, $result['payout_status'] === 'success' ? '打款成功' : '打款已提交');
        } catch (\Throwable $e) {
            Log::error('Withdraw payout failed: ' . $e->getMessage());
            // 失败回退为 approved 允许重试。**回退只改 status/payout_status，刻意保留 payout_batch_id**：
            // 有批次号 = 这笔钱已经提交给 PayPal 了（可能正是响应丢失的那一次），重试时
            // PayoutService::execute() 的守卫（packages/platform-common/src/service/PayoutService.php:57-63）
            // 只查 syncStatus、绝不重新 POST；若重新 POST，attempt 后缀会派生出一个**新** sender_batch_id，
            // PayPal 视为新批次 ⇒ 真·重复打款。
            // 旧注释写的「sender_batch_id 幂等防重复打款」是错的：PayPal 对 30 天内重复的
            // sender_batch_id 是**拒绝**（4xx）并在错误体里带原批次链接，不是回放原批次 ⇒ 靠它防重
            // 会让丢响应后的重试永久卡死。别再照着旧模型改这段。
            WithdrawOrder::where('id', $orderId)
                ->where('status', 'processing')
                ->update(['status' => 'approved', 'payout_status' => 'failed']);
            return $this->fail('打款失败，请稍后重试', 500);
        }
    }

    #[Apidoc\Title("同步打款状态")]
    #[Apidoc\Desc("从PayPal查询打款批次状态并同步")]
    #[Apidoc\Url("/admin/v1/withdraw/sync-payout")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "order_id", type: "string", require: true, desc: "订单ID(hashid编码)")]
    public function syncPayout(Request $request): Response
    {
        $validator = validator($request->all(), [
            'order_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $orderId = $this->decodeId($request->input('order_id'));
        $order = WithdrawOrder::find($orderId);
        if (!$order) {
            return $this->fail('订单不存在', 404);
        }
        if (empty($order->payout_batch_id)) {
            return $this->fail('该订单尚未执行打款', 422);
        }

        try {
            $status = PayoutService::syncStatus($order);
            return $this->success([
                'payout_status' => $order->payout_status,
                'order_status' => $order->status,
                'synced_status' => $status,
            ]);
        } catch (\Throwable $e) {
            Log::error('Withdraw payout sync failed: ' . $e->getMessage());
            return $this->fail('同步失败，请稍后重试', 500);
        }
    }
}
