<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1;

use erikwang2013\apidoc\annotation as Apidoc;
use common\model\PlatformConfig;
use common\model\Transaction;
use common\model\UserWallet;
use common\model\WithdrawOrder;
use common\service\NotificationService;
use support\Db;
use support\Log;
use support\Request;
use support\Response;

/**
 * 提现审核（单笔 review / 批量 batchReview）—— 自 WithdrawController 原样搬出，零行为变更。
 */
trait WithdrawReviewTrait
{
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
            return $this->fail(trans('Order not found'), 404);
        }

        $action = $request->input('action');
        $note   = $request->input('note', '');
        $adminId = (int) $request->adminId;
        $requireDual = PlatformConfig::get('withdraw', 'require_dual_review', 'off');
        $dualOn = in_array((string) $requireDual, ['on', '1', 'true'], true);

        if ($action === 'confirm') {
            if (!$dualOn) {
                return $this->fail(trans('Dual review is not enabled'), 422);
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
                return $this->fail(trans('Cannot confirm: another administrator must review, or the order status does not match'), 422);
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
            return $this->success([], trans('Dual confirmation passed'));
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
                    return $this->fail(trans('This order is already processed or already has a first reviewer'), 422);
                }
                return $this->success([], trans('First review passed, awaiting confirmation from another administrator'));
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
                return $this->fail(trans('This order is already processed'), 422);
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

            return $this->success([], trans('Approved'));
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

                return $this->success([], trans('Rejected and refunded'));
            });
        } catch (\Throwable $e) {
            if (WithdrawOrder::where('id', $orderId)->value('status') !== 'pending') {
                return $this->fail(trans('This order is already processed'), 422);
            }
            Log::error('Withdraw review refund failed: ' . $e->getMessage());
            return $this->fail(trans('Refund failed, please try again'), 500);
        }
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

        $message = $failedIds
            ? trans('Batch review completed: %ok% processed, %failed% failed', ['%ok%' => (string) $successCount, '%failed%' => (string) count($failedIds)])
            : trans('Batch review completed: %ok% processed', ['%ok%' => (string) $successCount]);
        return $this->success(['processed' => $successCount, 'failed' => $failedIds], $message);
    }
}
