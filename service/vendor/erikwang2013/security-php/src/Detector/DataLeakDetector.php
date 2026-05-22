<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

use Erikwang2013\Security\ThreatResult;

class DataLeakDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'data_leak';
    }

    protected function patterns(): array
    {
        return [
            // Credit card (Luhn-valid 16-digit format)
            '/\b(?:4\d{3}[ -]?\d{4}[ -]?\d{4}[ -]?\d{4}|'
            . '5[1-5]\d{2}[ -]?\d{4}[ -]?\d{4}[ -]?\d{4}|'
            . '3[47]\d{2}[ -]?\d{6}[ -]?\d{5}|'
            . '6011[ -]?\d{4}[ -]?\d{4}[ -]?\d{4})\b/'
                                                => ['severity' => 'high', 'detail' => 'Credit card number pattern detected'],
            // AWS Access Key
            '/\bAKIA[0-9A-Z]{16}\b/'   => ['severity' => 'critical', 'detail' => 'AWS Access Key ID exposed'],
            // AWS Secret Key
            '/\baws[_-]?secret[_-]?access[_-]?key\s*[:=]\s*["\']?[A-Za-z0-9\/+=]{20,}/i'
                                                => ['severity' => 'critical', 'detail' => 'AWS Secret Access Key exposed'],
            // Private key header
            '/-----BEGIN\s+(?:RSA\s+PRIVATE|EC|DSA|OPENSSH|PRIVATE)\s+KEY-----/i'
                                                => ['severity' => 'critical', 'detail' => 'Private key in request data'],
            // Generic API key / token
            '/\b(?:api[_-]?key|access[_-]?token|auth[_-]?token)\s*[:=]\s*["\']?[A-Za-z0-9._\-]{16,}/i'
                                                => ['severity' => 'high',  'detail' => 'API key / access token exposed'],
            // Database connection string
            '/\b(?:mysql|postgres|sqlserver|mongodb|redis):\/\/[^@\s]+@/i'
                                                => ['severity' => 'critical', 'detail' => 'Database connection string with credentials'],
            // JWT secret
            '/\bjwt[_-]?secret\s*[:=]\s*["\']?[A-Za-z0-9._\-]{8,}/i'
                                                => ['severity' => 'critical', 'detail' => 'JWT secret key exposed'],
            // Password in URL
            '/\bpassword=([^&\s]+)/i'   => ['severity' => 'high',  'detail' => 'Password in URL query string'],
        ];
    }

    public function detect(array $data): ?ThreatResult
    {
        foreach ($data as $field => $value) {
            if (!is_string($value)) {
                continue;
            }
            foreach ($this->patterns() as $pattern => $info) {
                $matchResult = preg_match($pattern, $value);
                if ($matchResult === false) {
                    error_log(sprintf(
                        'Security: Invalid regex pattern in detector "%s": %s',
                        $this->name(),
                        $pattern,
                    ));
                    continue;
                }
                if ($matchResult === 1) {
                    return new ThreatResult(
                        type: 'data_leak',
                        severity: $info['severity'],
                        field: (string) $field,
                        payload: $this->maskPayload($value),
                        detail: $info['detail'],
                    );
                }
            }
        }

        return null;
    }

    private function maskPayload(string $s): string
    {
        return strlen($s) > 20 ? substr($s, 0, 10) . '***' . substr($s, -6) : '***MASKED***';
    }
}
