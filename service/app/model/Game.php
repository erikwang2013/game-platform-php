<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;
use Erikwang2013\Encryptable\Encryptable;

class Game extends Model
{
    protected $table = 'game';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'cover_image',
        'api_endpoint',
        'api_key',
        'api_secret',
        'status',
        'sort',
        'provider_config',
        'sdk_version',
        'platform',
        'region',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
    ];

    protected $casts = [
        'status' => 'int',
        'sort' => 'int',
        'api_key' => Encryptable::class,
        'api_secret' => Encryptable::class,
    ];

    public function currencies()
    {
        return $this->hasMany(GameCurrency::class, 'game_id');
    }

    public function categories()
    {
        return $this->belongsToMany(GameCategory::class, 'game_category_rel', 'game_id', 'category_id');
    }
}
