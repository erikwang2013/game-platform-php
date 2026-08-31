<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace common\service;
use app\model\UserVip;
use app\model\VipLevel;
use app\model\ExpLog;
use support\Db;

class VipService
{
    const EXP_DEPOSIT_PER_UNIT = 10;
    const EXP_DAILY_LOGIN = 5;
    const EXP_KYC_COMPLETE = 50;
    const EXP_REFERRAL = 100;

    public static function addExp(int $userId, int $amount, string $source, int $refId = 0, string $refType = ''): void
    {
        Db::transaction(function () use ($userId, $amount, $source, $refId, $refType) {
            $vip = UserVip::where('user_id', $userId)->lockForUpdate()->first();
            if (!$vip) {
                $vip = new UserVip();
                $vip->id = (int)(date('YmdHis') . random_int(10000, 99999));
                $vip->user_id = $userId;
                $vip->level = 0;
                $vip->exp = 0;
                $vip->total_exp = 0;
            }

            $vip->exp += $amount;
            $vip->total_exp += $amount;

            $nextLevel = VipLevel::where('level', $vip->level + 1)->first();
            while ($nextLevel && $vip->exp >= $nextLevel->required_exp) {
                $vip->exp -= $nextLevel->required_exp;
                $vip->level = $nextLevel->level;
                $nextLevel = VipLevel::where('level', $vip->level + 1)->first();
            }
            $vip->save();

            $log = new ExpLog();
            $log->id = (int)(date('YmdHis') . random_int(10000, 99999));
            $log->user_id = $userId;
            $log->amount = $amount;
            $log->source = $source;
            $log->ref_type = $refType;
            $log->ref_id = $refId;
            $log->created_at = date('Y-m-d H:i:s');
            $log->save();
        });
    }

    public static function getExchangeDiscount(int $userId): string
    {
        $vip = UserVip::where('user_id', $userId)->first();
        if (!$vip || $vip->level < 1) return '0';

        $level = VipLevel::find($vip->level);
        if (!$level) return '0';

        $benefits = json_decode($level->benefits, true) ?? [];
        return $benefits['exchange_discount'] ?? '0';
    }

    public static function getWithdrawFeeDiscount(int $userId): string
    {
        $vip = UserVip::where('user_id', $userId)->first();
        if (!$vip || $vip->level < 1) return '0';

        $level = VipLevel::find($vip->level);
        if (!$level) return '0';

        $benefits = json_decode($level->benefits, true) ?? [];
        return $benefits['withdraw_fee_discount'] ?? '0';
    }

    public static function getRateBonus(int $userId): string
    {
        $vip = UserVip::where('user_id', $userId)->first();
        if (!$vip || $vip->level < 1) return '0';

        $level = VipLevel::find($vip->level);
        if (!$level) return '0';

        $benefits = json_decode($level->benefits, true) ?? [];
        return $benefits['rate_bonus'] ?? '0';
    }
}
