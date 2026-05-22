<?php

declare(strict_types=1);

use Erikwang2013\Season\CountrySeason;

if (!function_exists('country_season')) {
    /**
     * Get the season (English key) for a country code and optional date.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @param \DateTimeInterface|null $date Defaults to current time
     * @return string spring | summer | autumn | winter
     * @throws \InvalidArgumentException when the country code is invalid
     */
    function country_season(string $countryCode, ?\DateTimeInterface $date = null): string
    {
        return CountrySeason::getSeason($countryCode, $date);
    }
}

if (!function_exists('country_season_zh')) {
    /**
     * Get the season name in Chinese.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @param \DateTimeInterface|null $date Defaults to current time
     * @return string 春 | 夏 | 秋 | 冬
     * @throws \InvalidArgumentException when the country code is invalid
     */
    function country_season_zh(string $countryCode, ?\DateTimeInterface $date = null): string
    {
        return CountrySeason::getSeasonNameZh($countryCode, $date);
    }
}

if (!function_exists('country_season_flag')) {
    /**
     * Get the Unicode flag emoji for a country code.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @return string e.g. 🇨🇳, 🇺🇸
     * @throws \InvalidArgumentException when the country code is invalid
     */
    function country_season_flag(string $countryCode): string
    {
        return CountrySeason::getCountryFlagEmoji($countryCode);
    }
}

if (!function_exists('country_season_locale')) {
    /**
     * Get a localized season name by BCP 47 locale.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @param string $locale BCP 47 locale tag, e.g. zh_CN, en, ja_JP
     * @param \DateTimeInterface|null $date Defaults to current time
     * @return string Localized season name
     * @throws \InvalidArgumentException when the country code is invalid
     */
    function country_season_locale(
        string $countryCode,
        string $locale,
        ?\DateTimeInterface $date = null
    ): string {
        return CountrySeason::getSeasonNameLocalized($countryCode, $locale, $date);
    }
}
