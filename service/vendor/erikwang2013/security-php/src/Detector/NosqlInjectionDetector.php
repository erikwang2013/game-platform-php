<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class NosqlInjectionDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'nosql_injection';
    }

    protected function patterns(): array
    {
        return [
            '/\$(?:ne|gt|gte|lt|lte|eq|nin|in|regex|where|or|and)\b/i'
                                                => ['severity' => 'critical', 'detail' => 'MongoDB operator injection'],
            '/\$\s*where\s*:/i'         => ['severity' => 'critical', 'detail' => 'MongoDB $where JavaScript injection'],
            '/\{\s*\$gt\s*:\s*""\s*\}/i'
                                                => ['severity' => 'critical', 'detail' => 'MongoDB authentication bypass ($gt:"")'],
            '/\{\s*\$ne\s*:\s*""\s*\}/i'
                                                => ['severity' => 'critical', 'detail' => 'MongoDB authentication bypass ($ne:"")'],
            '/\$regex\s*:\s*"/i'       => ['severity' => 'high',     'detail' => 'MongoDB regex injection'],
            '/\$where\s*:\s*(?:function|this\.|new\s+Date)/i'
                                                => ['severity' => 'critical', 'detail' => 'MongoDB $where JavaScript injection'],
            '/\$inc\s*:/i'              => ['severity' => 'medium',   'detail' => 'MongoDB $inc modifier injection'],
            '/\$push\s*:/i'             => ['severity' => 'medium',   'detail' => 'MongoDB $push array injection'],
            '/\$pull\s*:/i'             => ['severity' => 'medium',   'detail' => 'MongoDB $pull operator injection'],
            '/sleep\s*\(\s*\d+\s*\)\s*;/i'
                                                => ['severity' => 'high',     'detail' => 'MongoDB sleep() injection (NoSQL)'],
        ];
    }
}
