<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class OperationLog extends Model
{
    protected $table = 'operation_log';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['user_id', 'action', 'method', 'path', 'ip', 'source', 'input'];
    protected $casts = ['user_id' => 'integer'];

    public function user()
    {
        return $this->belongsTo(AdminUser::class, 'user_id');
    }
}
