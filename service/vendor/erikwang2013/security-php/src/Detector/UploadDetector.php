<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

use Erikwang2013\Security\DetectorInterface;
use Erikwang2013\Security\ThreatResult;

class UploadDetector implements DetectorInterface
{
    public function name(): string
    {
        return 'upload';
    }

    private const SAFE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'md', 'json',
        'zip', 'tar', 'gz', 'rar', '7z',
        'mp3', 'mp4', 'avi', 'mov', 'wav', 'flac',
    ];

    public function detect(array $data): ?ThreatResult
    {
        foreach ($data as $field => $file) {
            if (!$this->isUploadFile($file)) {
                continue;
            }

            $name = $file['name'] ?? 'unknown';
            $tmpPath = $file['tmp_name'] ?? null;

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== '' && !in_array($ext, self::SAFE_EXTENSIONS, true)) {
                return new ThreatResult(
                    type: 'upload',
                    severity: 'high',
                    field: (string) $field,
                    payload: $name,
                    detail: "Forbidden file extension: .{$ext}",
                );
            }

            if ($tmpPath && file_exists($tmpPath)) {
                $head = file_get_contents($tmpPath, false, null, 0, 1024);
                if ($head !== false && preg_match('/<\?(?:php|=)/i', $head)) {
                    return new ThreatResult(
                        type: 'upload',
                        severity: 'critical',
                        field: (string) $field,
                        payload: $name,
                        detail: 'PHP code detected in file content',
                    );
                }
            }
        }

        return null;
    }

    private function isUploadFile(mixed $value): bool
    {
        return is_array($value)
            && isset($value['tmp_name'], $value['name'])
            && is_string($value['tmp_name'])
            && $value['tmp_name'] !== '';
    }
}
