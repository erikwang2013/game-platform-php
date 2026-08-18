<?php
declare(strict_types=1);
namespace app\model;
use support\Model;

class VipLevel extends Model
{
    protected $table = 'vip_level';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = ['level', 'name', 'required_exp', 'benefits'];
    protected $casts = ['level' => 'int', 'required_exp' => 'int'];
}
