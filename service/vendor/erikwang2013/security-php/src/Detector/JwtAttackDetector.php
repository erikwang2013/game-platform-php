<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

use Erikwang2013\Security\DetectorInterface;
use Erikwang2013\Security\ThreatResult;

class JwtAttackDetector implements DetectorInterface
{
    public function name(): string
    {
        return 'jwt_attack';
    }

    public function detect(array $data): ?ThreatResult
    {
        // JWT format: header.payload.signature — each part is base64url
        $jwtPattern = '/(?:^|\s|")([A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*)(?:\s|$|")/';

        foreach ($data as $field => $value) {
            if (!is_string($value)) {
                continue;
            }

            // Check for JWT tokens
            if (preg_match($jwtPattern, $value, $matches)) {
                $jwt = $matches[1];
                $parts = explode('.', $jwt);

                // Decode header
                $header = $this->base64UrlDecode($parts[0]);
                if ($header === null) {
                    continue;
                }

                $headerData = json_decode($header, true);
                if (!is_array($headerData)) {
                    continue;
                }

                // Check for "none" algorithm (signature bypass)
                if (strtolower((string) ($headerData['alg'] ?? '')) === 'none') {
                    return new ThreatResult(
                        type: 'jwt_attack',
                        severity: 'critical',
                        field: (string) $field,
                        payload: $jwt,
                        detail: 'JWT algorithm "none" — signature bypass',
                    );
                }

                // Check for "alg" confusion (e.g., RS256 → HS256)
                if (isset($headerData['alg']) && str_starts_with(strtoupper($headerData['alg']), 'H')
                    && isset($headerData['kid'])) {
                    $kid = $headerData['kid'];
                    if (str_contains($kid, '/') || str_contains($kid, '..') || str_contains($kid, '|')) {
                        return new ThreatResult(
                            type: 'jwt_attack',
                            severity: 'critical',
                            field: (string) $field,
                            payload: $jwt,
                            detail: 'JWT kid injection for HMAC key confusion',
                        );
                    }
                }

                // Check for empty signature (third part missing or empty)
                if (count($parts) < 3 || $parts[2] === '') {
                    return new ThreatResult(
                        type: 'jwt_attack',
                        severity: 'critical',
                        field: (string) $field,
                        payload: $jwt,
                        detail: 'JWT missing or empty signature',
                    );
                }
            }
        }

        return null;
    }

    private function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded !== false ? $decoded : null;
    }
}
