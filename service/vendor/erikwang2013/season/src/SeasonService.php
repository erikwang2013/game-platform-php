<?php

declare(strict_types=1);

namespace Erikwang2013\Season;

use DateTimeInterface;

/**
 * Season service for dependency injection in webman / Laravel / ThinkPHP / Hyperf containers.
 *
 * @method string getSeason(string $countryCode, ?DateTimeInterface $date = null)
 * @method string getSeasonNameZh(string $countryCode, ?DateTimeInterface $date = null)
 * @method string getCountryFlagEmoji(string $countryCode)
 * @method string getSeasonNameLocalized(string $countryCode, string $locale, ?DateTimeInterface $date = null)
 * @method string|null getSeasonForDefault(?DateTimeInterface $date = null)
 * @method string getHemisphere(string $countryCode)
 * @method bool isValidCode(string $countryCode)
 * @method list<string> getSupportedLocales()
 */
class SeasonService
{
    private ?string $defaultCountryCode = null;

    /**
     * @param string|null $defaultCountryCode ISO 3166-1 alpha-2 code; null or empty string means no default
     */
    public function __construct(?string $defaultCountryCode = null)
    {
        $this->setDefaultCountryCode($defaultCountryCode);
    }

    /**
     * Get the season (English key) for a country code and optional date.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @param DateTimeInterface|null $date Defaults to current time
     * @return string spring | summer | autumn | winter
     * @throws \InvalidArgumentException when the country code is invalid
     */
    public function getSeason(string $countryCode, ?DateTimeInterface $date = null): string
    {
        return CountrySeason::getSeason($countryCode, $date);
    }

    /**
     * Get the season name in Chinese.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @param DateTimeInterface|null $date Defaults to current time
     * @return string 春 | 夏 | 秋 | 冬
     * @throws \InvalidArgumentException when the country code is invalid
     */
    public function getSeasonNameZh(string $countryCode, ?DateTimeInterface $date = null): string
    {
        return CountrySeason::getSeasonNameZh($countryCode, $date);
    }

    /**
     * Get the Unicode flag emoji for a country code.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @return string e.g. 🇨🇳, 🇺🇸
     * @throws \InvalidArgumentException when the country code is invalid
     */
    public function getCountryFlagEmoji(string $countryCode): string
    {
        return CountrySeason::getCountryFlagEmoji($countryCode);
    }

    /**
     * Get a localized season name by BCP 47 locale.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @param string $locale BCP 47 locale tag, e.g. zh_CN, en, ja_JP
     * @param DateTimeInterface|null $date Defaults to current time
     * @return string Localized season name
     * @throws \InvalidArgumentException when the country code is invalid
     */
    public function getSeasonNameLocalized(
        string $countryCode,
        string $locale,
        ?DateTimeInterface $date = null
    ): string {
        return CountrySeason::getSeasonNameLocalized($countryCode, $locale, $date);
    }

    /**
     * Get the season for the configured default country code.
     *
     * @param DateTimeInterface|null $date Defaults to current time
     * @return string|null Season key, or null when no default country code is configured
     * @throws \InvalidArgumentException when the default country code is invalid
     */
    public function getSeasonForDefault(?DateTimeInterface $date = null): ?string
    {
        if ($this->defaultCountryCode === null || $this->defaultCountryCode === '') {
            return null;
        }
        return CountrySeason::getSeason($this->defaultCountryCode, $date);
    }

    /**
     * Determine which hemisphere a country is in.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 two-letter code (case-insensitive)
     * @return string north | south
     * @throws \InvalidArgumentException when the country code is invalid
     */
    public function getHemisphere(string $countryCode): string
    {
        return CountrySeason::getHemisphere($countryCode);
    }

    /**
     * Check whether a string looks like a valid ISO 3166-1 alpha-2 code.
     */
    public function isValidCode(string $countryCode): bool
    {
        return CountrySeason::isValidCode($countryCode);
    }

    /**
     * Get the list of built-in locale tags.
     *
     * @return list<string>
     */
    public function getSupportedLocales(): array
    {
        return CountrySeason::getSupportedLocales();
    }

    /**
     * Set the default country code.
     *
     * @throws \InvalidArgumentException when the code is non-null and not a valid two-letter format
     */
    public function setDefaultCountryCode(?string $code): void
    {
        if ($code === null || $code === '') {
            $this->defaultCountryCode = null;
            return;
        }
        $normalized = \strtoupper(\trim($code));
        if (!CountrySeason::isValidCode($normalized)) {
            throw new \InvalidArgumentException(
                'The default country code must be a valid ISO 3166-1 alpha-2 two-letter code, got: ' . $code
            );
        }
        $this->defaultCountryCode = $normalized;
    }
}
