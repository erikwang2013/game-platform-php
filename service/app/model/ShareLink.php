<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use app\service\ActivityService;
use support\Db;
use support\Log;
use support\Model;

class ShareLink extends Model
{
    protected $table = 'share_link';

    public $incrementing = false;
    protected $keyType = 'int';

    // 表无 updated_at 列
    public $timestamps = false;

    /**
     * 注册绑定转换：校验短码 → conversions 原子 increment → 写 invite 活动进度。
     * 幂等：先查后写（period_key='all' 的 participation 行），并发撞 uk 抛 1062 由本方法捕获转 null。
     * 仅活动绑定链接（activity_id>0）写进度；通用链接只计漏斗。
     *
     * @return array{short_code: string, inviter_user_id: int}|null 无效码/已绑定/系统异常返回 null
     */
    public static function bindConversion(int $userId, string $code): ?array
    {
        $link = self::where('short_code', $code)->first();
        if (!$link || ($link->expires_at && strtotime($link->expires_at) < time())) {
            return null;
        }

        $result = null;
        try {
            Db::transaction(function () use ($userId, $link, &$result) {
                // 同用户同活动只绑一次：period 'all' 已存在进度即已绑定过
                $already = ActivityParticipation::where('user_id', $userId)
                    ->where('period_key', 'all')
                    ->where('activity_id', (int) $link->activity_id)
                    ->exists();
                if ($already) {
                    return;
                }
                $link->increment('conversions');
                if ((int) $link->activity_id > 0) {
                    ActivityService::handle('user.registered', [
                        'user_id'         => $userId,
                        'inviter_user_id' => (int) $link->user_id,
                        'activity_id'     => (int) $link->activity_id,
                        'share_code'      => $link->short_code,
                    ]);
                }
                $result = ['short_code' => $link->short_code, 'inviter_user_id' => (int) $link->user_id];
            });
        } catch (\Throwable $e) {
            Log::warning('ShareLink bindConversion failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'code'    => $code,
            ]);
            return null; // 绑定失败不阻断注册
        }

        return $result;
    }

    protected $fillable = [
        'user_id',
        'activity_id',
        'short_code',
        'clicks',
        'conversions',
        'expires_at',
    ];

    protected $casts = [
        'clicks' => 'int',
        'conversions' => 'int',
    ];
}
