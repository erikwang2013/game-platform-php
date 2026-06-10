<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

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
     * Add balance to a user's wallet using optimistic locking.
     *
     * @param int    $userId
     * @param string $amount  Positive to add, negative to deduct. Uses bcmath.
     * @return bool
     */
    public static function addBalance(int $userId, string $amount): bool
    {
        $maxRetries = 5;

        for ($i = 0; $i < $maxRetries; $i++) {
            $wallet = static::where('user_id', $userId)->lockForUpdate()->first();

            if (!$wallet) {
                return false;
            }

            $currentVersion = (int) $wallet->version;
            $newBalance = bcadd($wallet->balance, $amount, 2);

            if (bccomp($newBalance, '0', 2) < 0) {
                return false;
            }

            $affected = static::where('id', $wallet->id)
                ->where('version', $currentVersion)
                ->update([
                    'balance' => $newBalance,
                    'version' => $currentVersion + 1,
                ]);

            if ($affected > 0) {
                if (bccomp($amount, '0', 2) > 0) {
                    $wallet->increment('total_earned', $amount);
                } elseif (bccomp($amount, '0', 2) < 0) {
                    $absAmount = ltrim($amount, '-');
                    $wallet->increment('total_spent', $absAmount);
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Deduct balance from a user's wallet.
     *
     * @param int    $userId
     * @param string $amount  Positive amount to deduct.
     * @return bool
     */
    public static function deductBalance(int $userId, string $amount): bool
    {
        $negated = bccomp($amount, '0', 2) > 0 ? '-' . $amount : $amount;
        return static::addBalance($userId, $negated);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
