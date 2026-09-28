<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace common\service;
use common\model\UserVip;
use common\model\VipLevel;
use common\model\ExpLog;
use common\SnowflakeService;
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
                // 主键走 snowflake（game_user_vip.id 注释即「由snowflake生成」）；旧写法同秒仅 90000 取值，
                // 撞号会以唯一键冲突打断整个 VIP 经验发放事务。
                $vip->id = SnowflakeService::generate();
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
            $log->id = SnowflakeService::generate();
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
