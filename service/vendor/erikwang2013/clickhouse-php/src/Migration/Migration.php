<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Migration;

use Erikwang2013\ClickHouse\Schema\Builder;

abstract class Migration
{
    protected Builder $schema;

    public function setSchema(Builder $schema): void
    {
        $this->schema = $schema;
    }

    abstract public function up(): void;

    public function down(): void
    {
    }
}