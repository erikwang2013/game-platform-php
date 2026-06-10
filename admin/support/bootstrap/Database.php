<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace support\bootstrap;

use Illuminate\Database\Capsule\Manager as Capsule;
use Webman\Bootstrap;
use Workerman\Worker;

class Database implements Bootstrap
{
    public static function start(?Worker $worker): void
    {
        $config = config('database');
        $capsule = new Capsule();

        foreach ($config['connections'] as $name => $connection) {
            $capsule->addConnection($connection, $name);
        }

        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
