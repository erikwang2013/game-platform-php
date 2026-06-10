<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace common\service;

class ProbabilityService
{
    public static function joint(array $eventA, array $eventB): array
    {
        return ['joint_probability' => 0, 'confidence' => 0];
    }
}
