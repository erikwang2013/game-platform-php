<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Tests\Core;

use Erikwang2013\Security\Logger;
use Erikwang2013\Security\ThreatResult;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/security_test_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }
        // Clean up rotated files
        foreach (glob($this->logPath . '.*') as $file) {
            unlink($file);
        }
    }

    public function testLogWritesToFile(): void
    {
        $logger = new Logger([
            'enabled' => true,
            'path' => $this->logPath,
            'max_size' => 10,
        ]);

        $threat = new ThreatResult('xss', 'critical', 'comment', '<script>x</script>', 'Script tag');
        $logger->log($threat, ['ip' => '1.2.3.4', 'method' => 'POST', 'uri' => '/api/test']);

        $this->assertFileExists($this->logPath);
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('xss', $content);
        $this->assertStringContainsString('critical', $content);
        $this->assertStringContainsString('1.2.3.4', $content);
        $this->assertStringContainsString('POST', $content);
        $this->assertStringContainsString('/api/test', $content);
    }

    public function testLogSanitizesNewlines(): void
    {
        $logger = new Logger([
            'enabled' => true,
            'path' => $this->logPath,
            'max_size' => 10,
        ]);

        $threat = new ThreatResult('xss', 'high', 'field', "normal", "evil\r\ndetail\ninjection");
        $logger->log($threat, ['ip' => '1.1.1.1', 'method' => 'GET', 'uri' => '/']);

        $content = file_get_contents($this->logPath);
        // The sanitize() method replaces \r with \\r and \n with \\n
        // So the log should contain the literal strings \r and \n, not actual CRLF
        $this->assertStringContainsString('\\r', $content);
        $this->assertStringContainsString('\\n', $content);
        // But the actual CR and LF chars should be gone from the threat fields
        $originalLine = trim($content);
        $fieldsAfterTimestamp = substr($originalLine, strpos($originalLine, '|'));
        $this->assertStringNotContainsString("\r", $fieldsAfterTimestamp, 'Threat detail field should not contain real CR');
    }

    public function testLogSanitizesPipeCharacters(): void
    {
        $logger = new Logger([
            'enabled' => true,
            'path' => $this->logPath,
            'max_size' => 10,
        ]);

        $threat = new ThreatResult('sql', 'high', 'id', 'normal', 'detail|fake|fields');
        $logger->log($threat, ['ip' => '1.1.1.1', 'method' => 'GET', 'uri' => '/']);

        $content = file_get_contents($this->logPath);
        // Pipe in detail should be replaced with space
        $this->assertStringContainsString('detail fake fields', $content);
    }

    public function testLogDisabledDoesNotWrite(): void
    {
        $logger = new Logger([
            'enabled' => false,
            'path' => $this->logPath,
            'max_size' => 10,
        ]);

        $threat = new ThreatResult('xss', 'critical', 'x', 'test', 'test');
        $logger->log($threat, ['ip' => '1.1.1.1', 'method' => 'GET', 'uri' => '/']);

        $this->assertFileDoesNotExist($this->logPath);
    }

    public function testLogRotationBySize(): void
    {
        // Use a small max_size (0.001 MB ≈ 1 KB) to test rotation quickly
        $logger = new Logger([
            'enabled' => true,
            'path' => $this->logPath,
            'max_size' => 0.001,
            'dedup_seconds' => 0,
        ]);

        $threat = new ThreatResult('test', 'low', 'f', str_repeat('x', 500), 'detail text here');
        // Each line ~600 bytes → need ~2 writes per 1KB → 10 writes should rotate ~5 times
        for ($i = 0; $i < 10; $i++) {
            $logger->log($threat, ['ip' => '1.2.3.4', 'method' => 'POST', 'uri' => '/test']);
        }

        $this->assertFileExists($this->logPath);
        $rotated = glob($this->logPath . '.*');
        $this->assertNotEmpty($rotated, 'Log rotation should create rotated files');
    }

    public function testLogWithMissingMetaFields(): void
    {
        $logger = new Logger([
            'enabled' => true,
            'path' => $this->logPath,
            'max_size' => 10,
        ]);

        $threat = new ThreatResult('test', 'low', 'f', 'p', 'd');
        $logger->log($threat, []);

        $this->assertFileExists($this->logPath);
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('-', $content, 'Missing meta values should show as dash');
    }

    public function testTruncateLongPayloads(): void
    {
        $logger = new Logger([
            'enabled' => true,
            'path' => $this->logPath,
            'max_size' => 10,
        ]);

        $longPayload = str_repeat('ABCDEFGHIJ', 50); // 500 chars
        $threat = new ThreatResult('test', 'low', 'f', $longPayload, 'd');
        $logger->log($threat, ['ip' => '1.1.1.1', 'method' => 'GET', 'uri' => '/']);

        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('...', $content, 'Long payload should be truncated with ellipsis');
    }
}
