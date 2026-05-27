<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Transport;

interface TransportInterface
{
    public function send(string $sql, array $bindings = []): mixed;
    public function close(): void;
}