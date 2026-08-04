<?php
declare(strict_types=1);
namespace app\model;
use support\Model;

class UserAchievement extends Model
{
    protected $table = 'user_achievement';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = ['user_id', 'achievement_id', 'progress', 'completed'];
    protected $casts = ['progress' => 'int', 'completed' => 'int'];
}
