<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Bridge\Hyperf;

use Hyperf\DbConnection\Db;
use Erikwang2013\Encryptable\Contracts\DbDriverDetector;

class HyperfDbDriverDetector implements DbDriverDetector
{
    public function __construct(
        protected ?string $connection = null
    ) {
    }

    public function isPostgres(): bool
    {
        $connection = $this->connection !== null
            ? Db::connection($this->connection)
            : Db::connection();

        if (method_exists($connection, 'getDriverName')) {
            $name = (string) $connection->getDriverName();
        } elseif (method_exists($connection, 'getConfig')) {
            $name = (string) ($connection->getConfig('driver') ?? '');
        } else {
            return false;
        }

        return in_array(strtolower($name), ['pgsql', 'postgres'], true);
    }
}
