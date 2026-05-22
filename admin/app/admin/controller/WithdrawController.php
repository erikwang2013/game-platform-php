<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\PlatformConfig;
use common\model\Transaction;
use common\model\User;
use common\model\UserWallet;
use common\model\WithdrawOrder;
use support\Request;
use support\Response;

class WithdrawController extends BaseController
{
    /**
     * 提现订单列表（分页）
     * GET /admin/withdraw/orders
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
     * 审核提现订单
     * PUT /admin/withdraw/review
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

        return $this->success([], '已驳回并退款');
    }

    /**
     * 提现全局开关
     * PUT /admin/withdraw/switch
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
     * 设置提现限制
     * POST /admin/withdraw/limits/set
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
}
