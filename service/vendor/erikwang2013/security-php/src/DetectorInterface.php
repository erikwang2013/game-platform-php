<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security;

interface DetectorInterface
{
    /**
     * Unique name matching config detector key (e.g. 'xss', 'sql_injection').
     */
    public function name(): string;

    /**
     * Scan flat key=>value array for attack patterns.
     * Returns ThreatResult if threat found, null if safe.
     */
    public function detect(array $data): ?ThreatResult;
}
