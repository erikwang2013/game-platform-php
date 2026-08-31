<?php
declare(strict_types=1);
namespace app\model;
use support\Model;

/**
 * 活动发奖记录。uk(participation_id, reward_type, reward_ref) 幂等：
 * 同一进度的同一类奖只发一次（发奖与进度累加同事务，失败整体回滚）。
 */
class ActivityRewardLog extends Model
{
    protected $table = 'activity_reward_log';

    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['id', 'user_id', 'activity_id', 'participation_id', 'period_key', 'reward_type', 'reward_ref', 'amount', 'status', 'fail_reason'];
    protected $casts = [
        'user_id'          => 'int',
        'activity_id'      => 'int',
        'participation_id' => 'int',
        'reward_ref'       => 'int',
        'amount'           => 'string',
    ];

    public const REWARD_PLATFORM_COIN = 'platform_coin';
    public const REWARD_GAME_COIN = 'game_coin';
}
