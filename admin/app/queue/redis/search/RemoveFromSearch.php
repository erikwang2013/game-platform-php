<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace app\queue\redis\search;


use Webman\RedisQueue\Consumer;

use Erikwang2013\WebmanScout\Jobs\RemoveableScoutCollection;

class RemoveFromSearch implements Consumer
{
    // 要消费的队列名
    public $queue = 'scout_remove';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';

    // 消费
    public function consume($models)
    {
        $models = unserialize($models);
        $models = RemoveableScoutCollection::make($models);
        if ($models->isNotEmpty()) {
            $models->first()->searchableUsing()->delete($models);
        }
    }
}
