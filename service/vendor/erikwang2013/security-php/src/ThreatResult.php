<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security;

class ThreatResult
{
    public function __construct(
        public readonly string $type,
        public readonly string $severity,
        public readonly string $field,
        public readonly string $payload,
        public readonly string $detail,
    ) {}
}
