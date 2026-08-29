<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\cdn\CdnFactory;
use app\cdn\CdnProviderInterface;
use PHPUnit\Framework\TestCase;

class CdnFactoryTest extends TestCase
{
    public function testResolveAllProviders(): void
    {
        $config = require __DIR__ . '/../config/cdn.php';
        foreach (array_keys($config['providers']) as $provider) {
            $resolved = CdnFactory::resolve($provider, $config);
            $this->assertInstanceOf(CdnProviderInterface::class, $resolved, "provider {$provider}");
        }
    }

    public function testUnknownProviderThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CdnFactory::resolve('akamai', require __DIR__ . '/../config/cdn.php');
    }
}
