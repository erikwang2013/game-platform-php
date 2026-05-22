<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Bridge\ThinkPHP;

use Erikwang2013\Encryptable\Contracts\DbDriverDetector;
use think\facade\Config;

class ThinkDbDriverDetector implements DbDriverDetector
{
    public function __construct(
        protected ?string $connection = null
    ) {
    }

    public function isPostgres(): bool
    {
        $name = $this->connection ?? (string) Config::get('database.default');
        $type = Config::get("database.connections.{$name}.type", '');

        return in_array(strtolower((string) $type), ['pgsql', 'postgres'], true);
    }
}
