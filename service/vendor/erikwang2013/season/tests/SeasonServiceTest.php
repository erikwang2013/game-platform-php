<?php

declare(strict_types=1);

namespace Erikwang2013\Season\Tests;

use Erikwang2013\Season\SeasonService;
use PHPUnit\Framework\TestCase;

class SeasonServiceTest extends TestCase
{
    public function testGetSeason(): void
    {
        $service = new SeasonService();
        $this->assertSame('summer', $service->getSeason('CN', new \DateTimeImmutable('2026-07-15')));
    }

    public function testGetSeasonNameZh(): void
    {
        $service = new SeasonService();
        $this->assertSame('夏', $service->getSeasonNameZh('CN', new \DateTimeImmutable('2026-07-15')));
    }

    public function testGetCountryFlagEmoji(): void
    {
        $service = new SeasonService();
        $this->assertSame('🇨🇳', $service->getCountryFlagEmoji('CN'));
    }

    public function testGetSeasonNameLocalized(): void
    {
        $service = new SeasonService();
        $this->assertSame('Sommer', $service->getSeasonNameLocalized('DE', 'de', new \DateTimeImmutable('2026-07-15')));
    }

    public function testGetHemisphere(): void
    {
        $service = new SeasonService();
        $this->assertSame('north', $service->getHemisphere('CN'));
        $this->assertSame('south', $service->getHemisphere('AU'));
    }

    public function testIsValidCode(): void
    {
        $service = new SeasonService();
        $this->assertTrue($service->isValidCode('CN'));
        $this->assertFalse($service->isValidCode('123'));
    }

    public function testGetSupportedLocales(): void
    {
        $service = new SeasonService();
        $locales = $service->getSupportedLocales();
        $this->assertNotEmpty($locales);
        $this->assertContains('en', $locales);
        $this->assertContains('zh', $locales);
    }

    // ── Default country code ────────────────────────────────────

    public function testGetSeasonForDefaultWithCode(): void
    {
        $service = new SeasonService('CN');
        $season = $service->getSeasonForDefault(new \DateTimeImmutable('2026-07-15'));
        $this->assertSame('summer', $season);
    }

    public function testGetSeasonForDefaultWithNull(): void
    {
        $service = new SeasonService();
        $this->assertNull($service->getSeasonForDefault());
    }

    public function testGetSeasonForDefaultWithEmptyString(): void
    {
        $service = new SeasonService('');
        $this->assertNull($service->getSeasonForDefault());
    }

    public function testSetDefaultCountryCodeValid(): void
    {
        $service = new SeasonService();
        $service->setDefaultCountryCode('CN');
        $this->assertSame('summer', $service->getSeasonForDefault(new \DateTimeImmutable('2026-07-15')));
    }

    public function testSetDefaultCountryCodeLowercase(): void
    {
        $service = new SeasonService();
        $service->setDefaultCountryCode('cn');
        $this->assertSame('summer', $service->getSeasonForDefault(new \DateTimeImmutable('2026-07-15')));
    }

    public function testSetDefaultCountryCodeNull(): void
    {
        $service = new SeasonService('CN');
        $service->setDefaultCountryCode(null);
        $this->assertNull($service->getSeasonForDefault());
    }

    public function testSetDefaultCountryCodeEmpty(): void
    {
        $service = new SeasonService('CN');
        $service->setDefaultCountryCode('');
        $this->assertNull($service->getSeasonForDefault());
    }

    public function testSetDefaultCountryCodeInvalidThrowsException(): void
    {
        $service = new SeasonService();
        $this->expectException(\InvalidArgumentException::class);
        $service->setDefaultCountryCode('ZZZ');
    }

    public function testConstructorRejectsInvalidCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SeasonService('123');
    }

    // ── Exception propagation ───────────────────────────────────

    public function testGetSeasonWithInvalidCodeThrowsException(): void
    {
        $service = new SeasonService();
        $this->expectException(\InvalidArgumentException::class);
        $service->getSeason('XXX');
    }
}
