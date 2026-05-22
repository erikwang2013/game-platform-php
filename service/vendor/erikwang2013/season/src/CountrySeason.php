<?php

declare(strict_types=1);

namespace Erikwang2013\Season;

use DateTimeInterface;

/**
 * Resolve the current season from an ISO 3166-1 alpha-2 country code.
 *
 * Northern hemisphere: spring Mar–May, summer Jun–Aug, autumn Sep–Nov, winter Dec/Jan/Feb.
 * Southern hemisphere: autumn Mar–May, winter Jun–Aug, spring Sep–Nov, summer Dec/Jan/Feb.
 */
class CountrySeason
{
    public const SEASON_SPRING = 'spring';
    public const SEASON_SUMMER = 'summer';
    public const SEASON_AUTUMN = 'autumn';
    public const SEASON_WINTER = 'winter';

    public const HEMISPHERE_NORTH = 'north';
    public const HEMISPHERE_SOUTH = 'south';

    /**
     * ISO 3166-1 alpha-2 codes for countries/territories in the southern hemisphere (keyed for O(1) lookup).
     *
     * Codes not listed here (including equator-bordering countries and non-sovereign territories)
     * default to northern hemisphere season mapping.
     */
    private const SOUTH_HEMISPHERE_CODES = [
        'AQ' => true, 'AR' => true, 'AU' => true, 'BV' => true, 'BO' => true,
        'BW' => true, 'BR' => true, 'IO' => true, 'BI' => true, 'CL' => true,
        'CC' => true, 'CK' => true, 'FK' => true, 'FJ' => true, 'TF' => true,
        'GS' => true, 'GY' => true, 'HM' => true, 'KI' => true, 'LS' => true,
        'MG' => true, 'MW' => true, 'MU' => true, 'YT' => true, 'NR' => true,
        'NC' => true, 'NZ' => true, 'NU' => true, 'NF' => true, 'PG' => true,
        'PY' => true, 'PE' => true, 'PN' => true, 'RE' => true, 'RW' => true,
        'SH' => true, 'WS' => true, 'SC' => true, 'SB' => true, 'ZA' => true,
        'SR' => true, 'SZ' => true, 'TO' => true, 'TV' => true, 'UM' => true,
        'UY' => true, 'VU' => true, 'WF' => true, 'ZM' => true, 'ZW' => true,
        'CX' => true, 'TK' => true, 'PF' => true, 'CD' => true, 'MZ' => true,
        'NA' => true, 'TZ' => true, 'AO' => true, 'KM' => true,
    ];

    private const MONTH_TO_SEASON_NORTH = [
        1 => self::SEASON_WINTER, 2 => self::SEASON_WINTER,
        3 => self::SEASON_SPRING, 4 => self::SEASON_SPRING, 5 => self::SEASON_SPRING,
        6 => self::SEASON_SUMMER, 7 => self::SEASON_SUMMER, 8 => self::SEASON_SUMMER,
        9 => self::SEASON_AUTUMN, 10 => self::SEASON_AUTUMN, 11 => self::SEASON_AUTUMN,
        12 => self::SEASON_WINTER,
    ];

    private const MONTH_TO_SEASON_SOUTH = [
        1 => self::SEASON_SUMMER, 2 => self::SEASON_SUMMER,
        3 => self::SEASON_AUTUMN, 4 => self::SEASON_AUTUMN, 5 => self::SEASON_AUTUMN,
        6 => self::SEASON_WINTER, 7 => self::SEASON_WINTER, 8 => self::SEASON_WINTER,
        9 => self::SEASON_SPRING, 10 => self::SEASON_SPRING, 11 => self::SEASON_SPRING,
        12 => self::SEASON_SUMMER,
    ];

    /**
     * Get the season (English key) for a country code and optional date.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @param DateTimeInterface|null $date Defaults to current time
     * @return string spring | summer | autumn | winter
     * @throws \InvalidArgumentException when the country code is invalid
     */
    public static function getSeason(string $countryCode, ?DateTimeInterface $date = null): string
    {
        $code = self::normalizeCountryCode($countryCode);
        $month = (int) ($date ?? new \DateTimeImmutable())->format('n');
        $hemisphere = self::getHemisphere($code);
        return self::monthToSeason($month, $hemisphere);
    }

    /**
     * Get the season name in Chinese.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @param DateTimeInterface|null $date Defaults to current time
     * @return string 春 | 夏 | 秋 | 冬
     * @throws \InvalidArgumentException when the country code is invalid
     */
    public static function getSeasonNameZh(string $countryCode, ?DateTimeInterface $date = null): string
    {
        $season = self::getSeason($countryCode, $date);
        return self::seasonToNameZh($season);
    }

    /**
     * Determine which hemisphere a country is in.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @return string north | south
     * @throws \InvalidArgumentException when the country code is invalid
     */
    public static function getHemisphere(string $countryCode): string
    {
        $code = self::normalizeCountryCode($countryCode);
        return isset(self::SOUTH_HEMISPHERE_CODES[$code])
            ? self::HEMISPHERE_SOUTH
            : self::HEMISPHERE_NORTH;
    }

    /**
     * Check whether a string looks like a valid ISO 3166-1 alpha-2 code (2 letters).
     * This only validates format, not whether it's a real assigned country code.
     */
    public static function isValidCode(string $countryCode): bool
    {
        return \strlen($countryCode) === 2 && \ctype_alpha($countryCode);
    }

    /**
     * Get the Unicode flag emoji for a country code.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @return string e.g. 🇨🇳, 🇺🇸
     * @throws \InvalidArgumentException when the country code is invalid
     */
    public static function getCountryFlagEmoji(string $countryCode): string
    {
        $code = self::normalizeCountryCode($countryCode);
        $a = \ord($code[0]) - 65;
        $b = \ord($code[1]) - 65;
        if ($a < 0 || $a > 25 || $b < 0 || $b > 25) {
            throw new \InvalidArgumentException(
                'Invalid country code for flag emoji: ' . $code
            );
        }
        $base = 0x1F1E6;
        return \mb_chr($base + $a, 'UTF-8')
            . \mb_chr($base + $b, 'UTF-8');
    }

    /**
     * Get a localized season name by BCP 47 locale.
     *
     * The country code determines the hemisphere/season; the locale determines the display language.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @param string $locale BCP 47 locale tag, e.g. zh_CN, en, ja_JP, pt_BR (hyphens and underscores OK)
     * @param DateTimeInterface|null $date Defaults to current time
     * @return string Localized season name
     * @throws \InvalidArgumentException when the country code is invalid
     */
    public static function getSeasonNameLocalized(
        string $countryCode,
        string $locale,
        ?DateTimeInterface $date = null
    ): string {
        $season = self::getSeason($countryCode, $date);
        $names = self::resolveSeasonNamesForLocale($locale);
        return $names[$season];
    }

    /**
     * Get the list of built-in locale tags (lowercase, underscore).
     *
     * @return list<string>
     */
    public static function getSupportedLocales(): array
    {
        $langKeys = \array_keys(LocaleData::NAMES);
        $overrideKeys = \array_keys(LocaleData::OVERRIDES);
        $keys = \array_unique(\array_merge($langKeys, $overrideKeys));
        \sort($keys, \SORT_STRING);
        return $keys;
    }

    /**
     * Normalize a country code: trim, uppercase, validate.
     *
     * @throws \InvalidArgumentException when empty or not two letters
     */
    private static function normalizeCountryCode(string $countryCode): string
    {
        $code = \strtoupper(\trim($countryCode));
        if ($code === '' || !self::isValidCode($code)) {
            throw new \InvalidArgumentException(
                'The country code must be in the two letter format of ISO 3166-1 alpha-2, currently passed in: '
                . (\strlen($countryCode) > 20 ? \substr($countryCode, 0, 20) . '...' : $countryCode)
            );
        }
        return $code;
    }

    private static function monthToSeason(int $month, string $hemisphere): string
    {
        $map = $hemisphere === self::HEMISPHERE_SOUTH
            ? self::MONTH_TO_SEASON_SOUTH
            : self::MONTH_TO_SEASON_NORTH;
        if (!isset($map[$month])) {
            throw new \LogicException("Unexpected month: $month");
        }
        return $map[$month];
    }

    protected static function seasonToNameZh(string $season): string
    {
        return match ($season) {
            self::SEASON_SPRING => '春',
            self::SEASON_SUMMER => '夏',
            self::SEASON_AUTUMN => '秋',
            self::SEASON_WINTER => '冬',
        };
    }

    /**
     * Resolve localized season names for a locale tag.
     *
     * Resolution order: exact match (including overrides), then language-only fallback, then en.
     *
     * @return array<string, string> season key => localized name
     */
    protected static function resolveSeasonNamesForLocale(string $locale): array
    {
        $key = \strtolower(\str_replace('-', '_', \trim($locale)));
        if ($key === '') {
            return LocaleData::NAMES['en'];
        }
        if (isset(LocaleData::OVERRIDES[$key])) {
            return LocaleData::OVERRIDES[$key];
        }
        if (isset(LocaleData::NAMES[$key])) {
            return LocaleData::NAMES[$key];
        }
        $underscore = \strpos($key, '_');
        if ($underscore !== false) {
            $lang = \substr($key, 0, $underscore);
            if (isset(LocaleData::NAMES[$lang])) {
                return LocaleData::NAMES[$lang];
            }
        }
        return LocaleData::NAMES['en'];
    }
}
