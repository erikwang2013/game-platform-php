<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use app\model\PlatformConfig;
use app\model\Transaction;
use app\model\User;
use app\model\UserWallet;
use app\model\WithdrawLimit;
use app\model\WithdrawOrder;
use app\service\NotificationService;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("提现管理")
 * @Apidoc\Group("withdraw")
 */
class WithdrawController extends BaseController
{
    /**
     * @Apidoc\Title("提现订单列表")
     * @Apidoc\Desc("分页获取提现订单列表，支持按状态筛选")
     * @Apidoc\Url("/admin/withdraw/orders")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("page", type="int", require=false, desc="页码")
     * @Apidoc\Param("per_page", type="int", require=false, desc="每页数量")
     * @Apidoc\Param("status", type="string", require=false, desc="订单状态(pending,approved,rejected,completed)")
     * @Apidoc\Returned("id", type="string", desc="订单ID(hashid编码)")
     */
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

    /**
     * @Apidoc\Title("审核提现")
     * @Apidoc\Desc("审批或拒绝提现申请")
     * @Apidoc\Url("/admin/withdraw/review")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("order_id", type="string", require=true, desc="订单ID(hashid编码)")
     * @Apidoc\Param("action", type="string", require=true, desc="操作(approve通过,reject拒绝)")
     * @Apidoc\Param("note", type="string", require=false, desc="审核备注")
     */
    public function review(Request $request): Response
    {
        $validator = validator($request->all(), [
            'order_id' => 'required|string',
            'action'   => 'required|string|in:approve,reject',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $orderId = $this->decodeId($request->input('order_id'));
        $order   = WithdrawOrder::find($orderId);
        if (!$order) {
            return $this->fail('订单不存在', 404);
        }

        if ($order->status !== 'pending') {
            return $this->fail('该订单已处理', 422);
        }

        $action = $request->input('action');
        $note   = $request->input('note', '');
        $adminId = $request->adminId;

        $order->reviewer_id = $adminId;
        $order->review_note = $note;
        $order->reviewed_at = date('Y-m-d H:i:s');

        if ($action === 'approve') {
            $order->status = 'approved';
            $order->save();

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

        // reject: refund platform balance to user wallet
        $order->status = 'rejected';
        $order->save();

        $refunded = UserWallet::addBalance($order->user_id, $order->platform_amount);
        if (!$refunded) {
            return $this->fail('退款失败', 500);
        }

        // record refund transaction
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

        NotificationService::send(
            $order->user_id,
            'withdraw',
            'Withdrawal Rejected',
            "Your withdrawal of {$order->platform_amount} platform tokens has been rejected. {$note}",
            'withdraw',
            $order->id
        );

        return $this->success([], '已驳回并退款');
    }

    /**
     * @Apidoc\Title("全局提现开关")
     * @Apidoc\Desc("启用或关闭全局提现功能")
     * @Apidoc\Url("/admin/withdraw/switch")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("enabled", type="int", require=true, desc="是否启用(0关闭,1启用)")
     */
    public function toggleSwitch(Request $request): Response
    {
        $validator = validator($request->all(), [
            'enabled' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $enabled = (int) $request->input('enabled');
        PlatformConfig::set('withdraw', 'global_switch', $enabled, 'bool');

        return $this->success(['global_switch' => (bool) $enabled], '操作成功');
    }

    /**
     * @Apidoc\Title("设置提现限额")
     * @Apidoc\Desc("设置全局提现限额参数")
     * @Apidoc\Url("/admin/withdraw/limits/set")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("daily_limit", type="float", require=false, desc="每日限额")
     * @Apidoc\Param("min_amount", type="float", require=false, desc="最小提现金额")
     * @Apidoc\Param("auto_approve_threshold", type="float", require=false, desc="自动审批阈值")
     */
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

        foreach ($keys as $key) {
            if ($request->has($key) && $request->input($key) !== null) {
                PlatformConfig::set('withdraw', $key, $request->input($key), 'decimal');
            }
        }

        $limits = [];
        foreach ($keys as $key) {
            $limits[$key] = PlatformConfig::get('withdraw', $key, '0');
        }
        $limits['global_switch'] = PlatformConfig::get('withdraw', 'global_switch', false);

        return $this->success($limits, '操作成功');
    }

    /**
     * @Apidoc\Title("阶梯限额列表")
     * @Apidoc\Desc("获取提现阶梯限额配置列表")
     * @Apidoc\Url("/admin/withdraw/limits/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     */
    public function listLimits(Request $request): Response
    {
        $list = WithdrawLimit::all()->map(function ($limit) {
            $data = $limit->toArray();
            return $this->encodeIds($data);
        })->toArray();

        return $this->success(['list' => $list]);
    }

    /**
     * @Apidoc\Title("更新阶梯限额")
     * @Apidoc\Desc("更新指定阶梯限额配置")
     * @Apidoc\Url("/admin/withdraw/limits/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     */
    public function updateLimit(Request $request, string $hashid): Response
    {
        $id    = $this->decodeId($hashid);
        $limit = WithdrawLimit::find($id);

        if (!$limit) {
            return $this->fail('限制记录不存在', 404);
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

    /**
     * @Apidoc\Title("批量审核提现")
     * @Apidoc\Desc("批量审批或拒绝提现申请")
     * @Apidoc\Url("/admin/withdraw/batch-review")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("ids", type="array", require=true, desc="订单ID数组(hashid编码)")
     * @Apidoc\Param("action", type="string", require=true, desc="操作(approve通过,reject拒绝)")
     * @Apidoc\Param("note", type="string", require=false, desc="审核备注")
     */
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

        foreach ($ids as $hashid) {
            $orderId = $this->decodeId($hashid);
            $order = WithdrawOrder::find($orderId);
            if (!$order || $order->status !== 'pending') continue;

            if ($action === 'approve') {
                $order->update([
                    'status' => 'approved',
                    'reviewer_id' => $request->adminId,
                    'review_note' => $note,
                    'reviewed_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $order->update([
                    'status' => 'rejected',
                    'reviewer_id' => $request->adminId,
                    'review_note' => $note,
                    'reviewed_at' => date('Y-m-d H:i:s'),
                ]);
                UserWallet::addBalance($order->user_id, $order->platform_amount);
                $wallet = UserWallet::where('user_id', $order->user_id)->first();
                Transaction::create([
                    'id' => $this->generateId(),
                    'user_id' => $order->user_id,
                    'type' => 'withdraw',
                    'amount' => $order->platform_amount,
                    'balance_after' => $wallet->balance,
                    'ref_type' => 'withdraw',
                    'ref_id' => $order->id,
                    'remark' => '批量审核退回: ' . $note,
                ]);
            }
            $successCount++;
        }

        return $this->success(['processed' => $successCount], "批量处理完成: {$successCount} 笔");
    }
}
