<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;
use Erikwang2013\Encryptable\Encryptable;

class UserIdentity extends Model
{
    protected $table = 'erik_user_identity';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'real_name',
        'id_type',
        'id_number',
        'id_front_photo',
        'id_back_photo',
        'selfie_photo',
        'country',
        'status',
        'reviewer_id',
        'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'real_name' => Encryptable::class,
        'id_number' => Encryptable::class,
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
