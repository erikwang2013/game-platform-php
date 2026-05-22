<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class Notification extends Model
{
    protected $table = 'erik_notification';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'content',
        'is_read',
        'ref_type',
        'ref_id',
    ];

    protected $casts = [
        'is_read' => 'int',
    ];

    /**
     * Boot the model — set created_at on create.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = $model->created_at ?? date('Y-m-d H:i:s');
        });
    }
}
