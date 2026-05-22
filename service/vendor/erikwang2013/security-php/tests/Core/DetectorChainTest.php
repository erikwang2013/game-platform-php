<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Tests\Core;

use Erikwang2013\Security\DetectorChain;
use Erikwang2013\Security\DetectorInterface;
use Erikwang2013\Security\ThreatResult;
use PHPUnit\Framework\TestCase;

class DetectorChainTest extends TestCase
{
    public function testEmptyChainReturnsNoThreats(): void
    {
        $chain = new DetectorChain();
        $threats = $chain->scan(['x' => 'attack']);

        $this->assertEmpty($threats);
        $this->assertSame(0, $chain->count());
    }

    public function testAddReturnsSelfForFluentChaining(): void
    {
        $chain = new DetectorChain();
        $result = $chain->add($this->makeDetector('test'));

        $this->assertSame($chain, $result);
    }

    public function testScanRunsAllDetectorsAndCollectsThreats(): void
    {
        $chain = new DetectorChain();
        $chain->add($this->makeDetector('a'));
        $chain->add($this->makeDetector('b'));

        $threats = $chain->scan(['x' => 'trigger']);

        $this->assertCount(2, $threats);
        $this->assertSame('a', $threats[0]->type);
        $this->assertSame('b', $threats[1]->type);
    }

    public function testScanSkipsNullReturns(): void
    {
        $chain = new DetectorChain();
        $chain->add($this->makeSafeDetector('safe'));
        $chain->add($this->makeDetector('danger'));

        $threats = $chain->scan(['x' => 'trigger']);

        $this->assertCount(1, $threats);
        $this->assertSame('danger', $threats[0]->type);
    }

    public function testCountReturnsCorrectNumber(): void
    {
        $chain = new DetectorChain();
        $this->assertSame(0, $chain->count());

        $chain->add($this->makeDetector('a'));
        $this->assertSame(1, $chain->count());

        $chain->add($this->makeDetector('b'));
        $chain->add($this->makeDetector('c'));
        $this->assertSame(3, $chain->count());
    }

    public function testScanWithEmptyData(): void
    {
        $chain = new DetectorChain();
        $chain->add($this->makeSafeDetector('test'));
        $threats = $chain->scan([]);

        $this->assertEmpty($threats, 'Empty data array should produce no threats when detector returns null');
    }

    private function makeDetector(string $name): DetectorInterface
    {
        return new class($name) implements DetectorInterface {
            public function __construct(private string $detectorName) {}
            public function name(): string { return $this->detectorName; }
            public function detect(array $data): ?ThreatResult {
                return new ThreatResult(
                    type: $this->detectorName,
                    severity: 'high',
                    field: 'x',
                    payload: 'test',
                    detail: 'test match',
                );
            }
        };
    }

    private function makeSafeDetector(string $name): DetectorInterface
    {
        return new class($name) implements DetectorInterface {
            public function __construct(private string $detectorName) {}
            public function name(): string { return $this->detectorName; }
            public function detect(array $data): ?ThreatResult {
                return null;
            }
        };
    }
}
