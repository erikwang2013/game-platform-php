<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;
use Erikwang2013\Encryptable\Encryptable;

class CdnProvider extends Model
{
    protected $table = 'cdn_provider';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'provider',
        'config',
        'status',
        'sort',
    ];

    protected $casts = [
        'status' => 'int',
        'sort' => 'int',
        'config' => Encryptable::class,
    ];
}
