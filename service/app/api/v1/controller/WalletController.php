<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\Transaction;
use common\model\UserWallet;
use support\Request;
use support\Response;

class WalletController extends BaseController
{
    /**
     * GET /api/wallet/info
     */
    public function info(Request $request): Response
    {
        $userId = $request->userId;

        $wallet = UserWallet::where('user_id', $userId)->first();
        if (!$wallet) {
            return $this->fail('Wallet not found', 404);
        }

        return $this->success([
            'id'             => $this->encodeId($wallet->id),
            'balance'        => $wallet->balance,
            'frozen_balance' => $wallet->frozen_balance,
            'total_earned'   => $wallet->total_earned,
            'total_spent'    => $wallet->total_spent,
        ]);
    }

    /**
     * GET /api/wallet/transactions
     */
    public function transactions(Request $request): Response
    {
        $userId  = $request->userId;
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);
        $type    = $request->input('type');

        $query = Transaction::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('type', $type);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $items     = [];

        foreach ($paginator->items() as $transaction) {
            $items[] = [
                'id'            => $this->encodeId($transaction->id),
                'type'          => $transaction->type,
                'amount'        => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'ref_type'      => $transaction->ref_type,
                'ref_id'        => $transaction->ref_id ? $this->encodeId($transaction->ref_id) : null,
                'remark'        => $transaction->remark,
                'created_at'    => $transaction->created_at,
            ];
        }

        return $this->success([
            'items'      => $items,
            'total'      => $paginator->total(),
            'page'       => $paginator->currentPage(),
            'per_page'   => $paginator->perPage(),
            'last_page'  => $paginator->lastPage(),
        ]);
    }
}
