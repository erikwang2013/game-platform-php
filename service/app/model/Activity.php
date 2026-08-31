<?php
declare(strict_types=1);
namespace app\model;
use support\Model;

/**
 * 运营活动定义（配置驱动，运营在管理端建，不发版生效）。
 * config 按 type 定义 schema：signin / daily_task。
 */
class Activity extends Model
{
    protected $table = 'activity';

    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['id', 'type', 'name', 'game_id', 'config', 'status', 'start_at', 'end_at', 'rollout_percent'];
    protected $casts = [
        'game_id'          => 'int',
        'status'           => 'int',
        'rollout_percent'  => 'int',
        'config'           => 'array',
    ];

    public const TYPE_SIGNIN = 'signin';
    public const TYPE_DAILY_TASK = 'daily_task';

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;
    public const STATUS_ENDED = 2;
}
