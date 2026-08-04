<?php
declare(strict_types=1);
namespace app\model;
use support\Model;

class ExpLog extends Model
{
    protected $table = 'exp_log';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = ['user_id', 'amount', 'source', 'ref_type', 'ref_id'];
    protected $casts = ['amount' => 'int'];
}
