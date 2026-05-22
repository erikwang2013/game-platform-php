<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * Global helper functions for manual security scanning.
 * No framework dependency — works with plain PHP arrays.
 */

use Erikwang2013\Security\SecurityGuard;

if (!function_exists('security_scan')) {
    /**
     * Scan an arbitrary key=>value array for security threats.
     * Returns ThreatResult[] — empty array means safe.
     */
    function security_scan(array $data): array
    {
        return SecurityGuard::guard($data);
    }
}

if (!function_exists('security_scan_current_request')) {
    /**
     * Scan the current HTTP request superglobals.
     * Extracts GET, POST, COOKIE, and FILES automatically.
     */
    function security_scan_current_request(): array
    {
        $data = array_merge(
            $_COOKIE,
            $_GET,
            $_POST,
        );

        if (!empty($_FILES)) {
            foreach ($_FILES as $key => $file) {
                $data[$key] = [
                    'name'     => $file['name'] ?? '',
                    'tmp_name' => $file['tmp_name'] ?? '',
                ];
            }
        }

        return SecurityGuard::guard($data, [
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri'    => $_SERVER['REQUEST_URI'] ?? '/',
        ]);
    }
}

if (!function_exists('security_is_safe')) {
    /**
     * Quick check: is the given data safe?
     */
    function security_is_safe(array $data): bool
    {
        return security_scan($data) === [];
    }
}

if (!function_exists('security_guard')) {
    /**
     * Scan current request and die with 403 if any detector is in block mode.
     * Suitable for non-framework projects or bootstrap files.
     */
    function security_guard(): void
    {
        $threats = security_scan_current_request();

        if (!empty($threats) && SecurityGuard::shouldBlock($threats)) {
            http_response_code(SecurityGuard::blockStatusCode());
            header('Content-Type: text/plain; charset=utf-8');
            die(SecurityGuard::blockMessage());
        }
    }
}
