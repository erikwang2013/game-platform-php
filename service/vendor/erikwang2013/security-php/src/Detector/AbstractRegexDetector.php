<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

use Erikwang2013\Security\DetectorInterface;
use Erikwang2013\Security\ThreatResult;

abstract class AbstractRegexDetector implements DetectorInterface
{
    abstract public function name(): string;

    /**
     * @return array<string, array{severity: string, detail: string}>
     */
    abstract protected function patterns(): array;

    public function detect(array $data): ?ThreatResult
    {
        foreach ($data as $field => $value) {
            if (!is_string($value)) {
                continue;
            }
            foreach ($this->patterns() as $pattern => $info) {
                $result = preg_match($pattern, $value);
                if ($result === false) {
                    error_log(sprintf(
                        'Security: Invalid regex pattern in detector "%s": %s',
                        $this->name(),
                        $pattern,
                    ));
                    continue;
                }
                if ($result === 1) {
                    return new ThreatResult(
                        type: $this->name(),
                        severity: $info['severity'],
                        field: (string) $field,
                        payload: $value,
                        detail: $info['detail'],
                    );
                }
            }
        }
        return null;
    }
}
