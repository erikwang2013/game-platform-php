<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Bridge\Laravel;

use Illuminate\Support\Facades\DB;
use Erikwang2013\Encryptable\Contracts\DbDriverDetector;

class IlluminateDbDriverDetector implements DbDriverDetector
{
    public function __construct(
        protected ?string $connection = null
    ) {
    }

    public function isPostgres(): bool
    {
        $connection = $this->connection !== null
            ? DB::connection($this->connection)
            : DB::connection();

        return $connection->getDriverName() === 'pgsql';
    }
}
