<?php

declare(strict_types=1);

namespace Erikwang2013\Season\Tests;

use Erikwang2013\Season\CountrySeason;
use PHPUnit\Framework\TestCase;

class CountrySeasonTest extends TestCase
{
    // ── getSeason ────────────────────────────────────────────────

    public function testGetSeasonNorthernHemisphere(): void
    {
        $this->assertSame('winter', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-01-15')));
        $this->assertSame('winter', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-02-15')));
        $this->assertSame('spring', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-03-15')));
        $this->assertSame('spring', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-04-15')));
        $this->assertSame('spring', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-05-15')));
        $this->assertSame('summer', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-06-15')));
        $this->assertSame('summer', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-07-15')));
        $this->assertSame('summer', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-08-15')));
        $this->assertSame('autumn', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-09-15')));
        $this->assertSame('autumn', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-10-15')));
        $this->assertSame('autumn', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-11-15')));
        $this->assertSame('winter', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-12-15')));
    }

    public function testGetSeasonSouthernHemisphere(): void
    {
        $this->assertSame('summer', CountrySeason::getSeason('AU', new \DateTimeImmutable('2026-01-15')));
        $this->assertSame('summer', CountrySeason::getSeason('AU', new \DateTimeImmutable('2026-02-15')));
        $this->assertSame('autumn', CountrySeason::getSeason('AU', new \DateTimeImmutable('2026-03-15')));
        $this->assertSame('autumn', CountrySeason::getSeason('AU', new \DateTimeImmutable('2026-04-15')));
        $this->assertSame('autumn', CountrySeason::getSeason('AU', new \DateTimeImmutable('2026-05-15')));
        $this->assertSame('winter', CountrySeason::getSeason('AU', new \DateTimeImmutable('2026-06-15')));
        $this->assertSame('winter', CountrySeason::getSeason('AU', new \DateTimeImmutable('2026-07-15')));
        $this->assertSame('winter', CountrySeason::getSeason('AU', new \DateTimeImmutable('2026-08-15')));
        $this->assertSame('spring', CountrySeason::getSeason('AU', new \DateTimeImmutable('2026-09-15')));
        $this->assertSame('spring', CountrySeason::getSeason('AU', new \DateTimeImmutable('2026-10-15')));
        $this->assertSame('spring', CountrySeason::getSeason('AU', new \DateTimeImmutable('2026-11-15')));
        $this->assertSame('summer', CountrySeason::getSeason('AU', new \DateTimeImmutable('2026-12-15')));
    }

    public function testGetSeasonDefaultDate(): void
    {
        $season = CountrySeason::getSeason('CN');
        $this->assertContains($season, ['spring', 'summer', 'autumn', 'winter']);
    }

    public function testGetSeasonCaseInsensitive(): void
    {
        $this->assertSame(
            CountrySeason::getSeason('cn', new \DateTimeImmutable('2026-06-15')),
            CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-06-15'))
        );
        $this->assertSame(
            CountrySeason::getSeason('  us  ', new \DateTimeImmutable('2026-06-15')),
            CountrySeason::getSeason('US', new \DateTimeImmutable('2026-06-15'))
        );
    }

    // ── getSeasonNameZh ─────────────────────────────────────────

    public function testGetSeasonNameZh(): void
    {
        $this->assertSame('夏', CountrySeason::getSeasonNameZh('CN', new \DateTimeImmutable('2026-07-15')));
        $this->assertSame('冬', CountrySeason::getSeasonNameZh('CN', new \DateTimeImmutable('2026-01-15')));
        $this->assertSame('冬', CountrySeason::getSeasonNameZh('AU', new \DateTimeImmutable('2026-07-15')));
    }

    // ── getHemisphere ───────────────────────────────────────────

    public function testGetHemisphere(): void
    {
        $this->assertSame('north', CountrySeason::getHemisphere('CN'));
        $this->assertSame('north', CountrySeason::getHemisphere('US'));
        $this->assertSame('north', CountrySeason::getHemisphere('JP'));
        $this->assertSame('north', CountrySeason::getHemisphere('DE'));
    }

    public function testGetHemisphereSouthern(): void
    {
        $southernCountries = ['AU', 'AR', 'BR', 'NZ', 'ZA', 'CL', 'UY', 'PY', 'PE'];
        foreach ($southernCountries as $code) {
            $this->assertSame('south', CountrySeason::getHemisphere($code), "Failed for $code");
        }
    }

    // ── isValidCode ─────────────────────────────────────────────

    public function testIsValidCode(): void
    {
        $this->assertTrue(CountrySeason::isValidCode('CN'));
        $this->assertTrue(CountrySeason::isValidCode('US'));
        $this->assertTrue(CountrySeason::isValidCode('xx'));
    }

    public function testIsValidCodeInvalid(): void
    {
        $this->assertFalse(CountrySeason::isValidCode(''));
        $this->assertFalse(CountrySeason::isValidCode('C'));
        $this->assertFalse(CountrySeason::isValidCode('CHN'));
        $this->assertFalse(CountrySeason::isValidCode('12'));
        $this->assertFalse(CountrySeason::isValidCode('C1'));
    }

    // ── getCountryFlagEmoji ─────────────────────────────────────

    public function testGetCountryFlagEmoji(): void
    {
        $this->assertSame('🇨🇳', CountrySeason::getCountryFlagEmoji('CN'));
        $this->assertSame('🇺🇸', CountrySeason::getCountryFlagEmoji('US'));
        $this->assertSame('🇯🇵', CountrySeason::getCountryFlagEmoji('JP'));
        $this->assertSame('🇬🇧', CountrySeason::getCountryFlagEmoji('GB'));
        $this->assertSame('🇦🇺', CountrySeason::getCountryFlagEmoji('AU'));
    }

    public function testGetCountryFlagEmojiCaseInsensitive(): void
    {
        $this->assertSame('🇨🇳', CountrySeason::getCountryFlagEmoji('cn'));
        $this->assertSame('🇺🇸', CountrySeason::getCountryFlagEmoji('us'));
    }

    public function testGetCountryFlagEmojiTrimmed(): void
    {
        $this->assertSame('🇨🇳', CountrySeason::getCountryFlagEmoji('  cn  '));
        $this->assertSame('🇺🇸', CountrySeason::getCountryFlagEmoji('  us  '));
    }

    // ── getSeasonNameLocalized ──────────────────────────────────

    public function testGetSeasonNameLocalizedEnglish(): void
    {
        $this->assertSame('Summer', CountrySeason::getSeasonNameLocalized('US', 'en', new \DateTimeImmutable('2026-07-15')));
        $this->assertSame('Winter', CountrySeason::getSeasonNameLocalized('CN', 'en', new \DateTimeImmutable('2026-01-15')));
    }

    public function testGetSeasonNameLocalizedEnUSFall(): void
    {
        $this->assertSame('Fall', CountrySeason::getSeasonNameLocalized('US', 'en_US', new \DateTimeImmutable('2026-10-15')));
    }

    public function testGetSeasonNameLocalizedEnGBAutumn(): void
    {
        $this->assertSame('Autumn', CountrySeason::getSeasonNameLocalized('GB', 'en_GB', new \DateTimeImmutable('2026-10-15')));
    }

    public function testGetSeasonNameLocalizedChinese(): void
    {
        $this->assertSame('夏', CountrySeason::getSeasonNameLocalized('CN', 'zh', new \DateTimeImmutable('2026-07-15')));
        $this->assertSame('冬', CountrySeason::getSeasonNameLocalized('CN', 'zh_CN', new \DateTimeImmutable('2026-01-15')));
    }

    public function testGetSeasonNameLocalizedGerman(): void
    {
        $this->assertSame('Sommer', CountrySeason::getSeasonNameLocalized('DE', 'de', new \DateTimeImmutable('2026-07-15')));
        $this->assertSame('Winter', CountrySeason::getSeasonNameLocalized('DE', 'de_DE', new \DateTimeImmutable('2026-01-15')));
    }

    public function testGetSeasonNameLocalizedHyphenLocale(): void
    {
        $this->assertSame('Fall', CountrySeason::getSeasonNameLocalized('US', 'en-us', new \DateTimeImmutable('2026-10-15')));
    }

    public function testGetSeasonNameLocalizedFallbackToLanguage(): void
    {
        // zh_TW falls through to zh (same names, no override needed)
        $this->assertSame('夏', CountrySeason::getSeasonNameLocalized('CN', 'zh_TW', new \DateTimeImmutable('2026-07-15')));
    }

    public function testGetSeasonNameLocalizedFallbackToEnglish(): void
    {
        $this->assertSame('Summer', CountrySeason::getSeasonNameLocalized('CN', 'xx_XX', new \DateTimeImmutable('2026-07-15')));
    }

    public function testGetSeasonNameLocalizedEmptyLocale(): void
    {
        $this->assertSame('Summer', CountrySeason::getSeasonNameLocalized('US', '', new \DateTimeImmutable('2026-07-15')));
    }

    // ── getSupportedLocales ─────────────────────────────────────

    public function testGetSupportedLocales(): void
    {
        $locales = CountrySeason::getSupportedLocales();
        $this->assertIsArray($locales);
        $this->assertNotEmpty($locales);
        $this->assertContains('en', $locales);
        $this->assertContains('en_us', $locales);
        $this->assertContains('zh', $locales);
        $this->assertContains('ja', $locales);
        $this->assertContains('de', $locales);
    }

    // ── Exceptions ──────────────────────────────────────────────

    public function testInvalidCodeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CountrySeason::getSeason('XXX');
    }

    public function testEmptyCodeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CountrySeason::getSeason('');
    }

    public function testNumericCodeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CountrySeason::getSeason('12');
    }

    public function testFlagEmojiInvalidCodeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CountrySeason::getCountryFlagEmoji('123');
    }

    // ── Edge cases ──────────────────────────────────────────────

    public function testMonthBoundaries(): void
    {
        // Feb 28 → winter (north)
        $this->assertSame('winter', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-02-28')));
        // Mar 1 → spring (north)
        $this->assertSame('spring', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-03-01')));
        // May 31 → spring (north)
        $this->assertSame('spring', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-05-31')));
        // Jun 1 → summer (north)
        $this->assertSame('summer', CountrySeason::getSeason('CN', new \DateTimeImmutable('2026-06-01')));
    }

    public function testAllSouthernCountriesAreValid(): void
    {
        // Every listed southern hemisphere code should pass validation
        $southern = ['AQ', 'AR', 'AU', 'BV', 'BO', 'BW', 'BR', 'IO', 'BI', 'CL',
            'CC', 'CK', 'FK', 'FJ', 'TF', 'GS', 'GY', 'HM', 'KI', 'LS',
            'MG', 'MW', 'MU', 'YT', 'NR', 'NC', 'NZ', 'NU', 'NF', 'PG',
            'PY', 'PE', 'PN', 'RE', 'RW', 'SH', 'WS', 'SC', 'SB', 'ZA',
            'SR', 'SZ', 'TO', 'TV', 'UM', 'UY', 'VU', 'WF', 'ZM', 'ZW',
            'CX', 'TK', 'PF', 'CD', 'MZ', 'NA', 'TZ', 'AO', 'KM'];
        foreach ($southern as $code) {
            $this->assertSame('south', CountrySeason::getHemisphere($code), "Failed for $code");
        }
    }
}
