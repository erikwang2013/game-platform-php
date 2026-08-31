<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use app\service\WalletScope;
use app\service\WalletService;
use support\Model;

class UserWallet extends Model
{
    protected $table = 'user_wallet';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'balance',
        'frozen_balance',
        'total_earned',
        'total_spent',
        'version',
    ];

    protected $casts = [
        'balance' => 'string',
        'frozen_balance' => 'string',
        'total_earned' => 'string',
        'total_spent' => 'string',
        'version' => 'int',
    ];

    /**
     * Add balance to a user's wallet (M1: 委托统一钱包服务记账+流水).
     *
     * @param int    $userId
     * @param string $amount  正=收入，负=支出。bcmath。
     * @param string $type    流水类型: deposit/withdraw/exchange_in/exchange_out/game_earn/game_spend...
     * @param string $refType 关联单据类型
     * @param int    $refId   关联单据ID
     * @return bool
     */
    public static function addBalance(int $userId, string $amount, string $type = 'deposit', string $refType = '', int $refId = 0): bool
    {
        return WalletService::mutate($userId, WalletScope::platform(), bcadd($amount, '0', WalletService::SCALE), $type, $refType, $refId);
    }

    /**
     * Deduct balance from a user's wallet.
     *
     * @param int    $userId
     * @param string $amount  Positive amount to deduct.
     * @return bool
     */
    public static function deductBalance(int $userId, string $amount, string $type = 'withdraw', string $refType = '', int $refId = 0): bool
    {
        $negated = bccomp($amount, '0', WalletService::SCALE) > 0 ? '-' . $amount : $amount;
        return static::addBalance($userId, $negated, $type, $refType, $refId);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
