<?php
declare(strict_types=1);
namespace app\model;
use support\Model;

class Achievement extends Model
{
    protected $table = 'achievement';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = ['key', 'name', 'description', 'icon', 'condition_json', 'points'];
    protected $casts = ['points' => 'int'];
}
