<?php

declare(strict_types=1);

namespace Erikwang2013\Season;

class LocaleData
{
    /** Season keys => localized names; language-level entries. Key is lowercase underscore (e.g. "en", "zh_cn"). */
    public const NAMES = [
        'en' => [
            CountrySeason::SEASON_SPRING => 'Spring',
            CountrySeason::SEASON_SUMMER => 'Summer',
            CountrySeason::SEASON_AUTUMN => 'Autumn',
            CountrySeason::SEASON_WINTER => 'Winter',
        ],
        'zh' => [
            CountrySeason::SEASON_SPRING => '春',
            CountrySeason::SEASON_SUMMER => '夏',
            CountrySeason::SEASON_AUTUMN => '秋',
            CountrySeason::SEASON_WINTER => '冬',
        ],
        'ja' => [
            CountrySeason::SEASON_SPRING => '春',
            CountrySeason::SEASON_SUMMER => '夏',
            CountrySeason::SEASON_AUTUMN => '秋',
            CountrySeason::SEASON_WINTER => '冬',
        ],
        'ko' => [
            CountrySeason::SEASON_SPRING => '봄',
            CountrySeason::SEASON_SUMMER => '여름',
            CountrySeason::SEASON_AUTUMN => '가을',
            CountrySeason::SEASON_WINTER => '겨울',
        ],
        'de' => [
            CountrySeason::SEASON_SPRING => 'Frühling',
            CountrySeason::SEASON_SUMMER => 'Sommer',
            CountrySeason::SEASON_AUTUMN => 'Herbst',
            CountrySeason::SEASON_WINTER => 'Winter',
        ],
        'fr' => [
            CountrySeason::SEASON_SPRING => 'printemps',
            CountrySeason::SEASON_SUMMER => 'Été',
            CountrySeason::SEASON_AUTUMN => 'Automne',
            CountrySeason::SEASON_WINTER => 'Hiver',
        ],
        'es' => [
            CountrySeason::SEASON_SPRING => 'Primavera',
            CountrySeason::SEASON_SUMMER => 'Verano',
            CountrySeason::SEASON_AUTUMN => 'Otoño',
            CountrySeason::SEASON_WINTER => 'Invierno',
        ],
        'it' => [
            CountrySeason::SEASON_SPRING => 'Primavera',
            CountrySeason::SEASON_SUMMER => 'Estate',
            CountrySeason::SEASON_AUTUMN => 'Autunno',
            CountrySeason::SEASON_WINTER => 'Inverno',
        ],
        'pt' => [
            CountrySeason::SEASON_SPRING => 'Primavera',
            CountrySeason::SEASON_SUMMER => 'Verão',
            CountrySeason::SEASON_AUTUMN => 'Outono',
            CountrySeason::SEASON_WINTER => 'Inverno',
        ],
        'ru' => [
            CountrySeason::SEASON_SPRING => 'Весна',
            CountrySeason::SEASON_SUMMER => 'Лето',
            CountrySeason::SEASON_AUTUMN => 'Осень',
            CountrySeason::SEASON_WINTER => 'Зима',
        ],
        'nl' => [
            CountrySeason::SEASON_SPRING => 'Lente',
            CountrySeason::SEASON_SUMMER => 'Zomer',
            CountrySeason::SEASON_AUTUMN => 'Herfst',
            CountrySeason::SEASON_WINTER => 'Winter',
        ],
        'pl' => [
            CountrySeason::SEASON_SPRING => 'Wiosna',
            CountrySeason::SEASON_SUMMER => 'Lato',
            CountrySeason::SEASON_AUTUMN => 'Jesień',
            CountrySeason::SEASON_WINTER => 'Zima',
        ],
        'sv' => [
            CountrySeason::SEASON_SPRING => 'Vår',
            CountrySeason::SEASON_SUMMER => 'Sommar',
            CountrySeason::SEASON_AUTUMN => 'Höst',
            CountrySeason::SEASON_WINTER => 'Vinter',
        ],
        'uk' => [
            CountrySeason::SEASON_SPRING => 'Весна',
            CountrySeason::SEASON_SUMMER => 'Літо',
            CountrySeason::SEASON_AUTUMN => 'Осінь',
            CountrySeason::SEASON_WINTER => 'Зима',
        ],
        'ar' => [
            CountrySeason::SEASON_SPRING => 'الربيع',
            CountrySeason::SEASON_SUMMER => 'الصيف',
            CountrySeason::SEASON_AUTUMN => 'الخريف',
            CountrySeason::SEASON_WINTER => 'الشتاء',
        ],
        'hi' => [
            CountrySeason::SEASON_SPRING => 'वसंत',
            CountrySeason::SEASON_SUMMER => 'ग्रीष्म',
            CountrySeason::SEASON_AUTUMN => 'शरद्',
            CountrySeason::SEASON_WINTER => 'शीत',
        ],
        'th' => [
            CountrySeason::SEASON_SPRING => 'ฤดูใบไม้ผลิ',
            CountrySeason::SEASON_SUMMER => 'ฤดูร้อน',
            CountrySeason::SEASON_AUTUMN => 'ฤดูใบไม้ร่วง',
            CountrySeason::SEASON_WINTER => 'ฤดูหนาว',
        ],
        'vi' => [
            CountrySeason::SEASON_SPRING => 'mùa xuân',
            CountrySeason::SEASON_SUMMER => 'mùa hè',
            CountrySeason::SEASON_AUTUMN => 'mùa thu',
            CountrySeason::SEASON_WINTER => 'mùa đông',
        ],
        'id' => [
            CountrySeason::SEASON_SPRING => 'musim semi',
            CountrySeason::SEASON_SUMMER => 'musim panas',
            CountrySeason::SEASON_AUTUMN => 'musim gugur',
            CountrySeason::SEASON_WINTER => 'musim dingin',
        ],
        'tr' => [
            CountrySeason::SEASON_SPRING => 'İlkbahar',
            CountrySeason::SEASON_SUMMER => 'Yaz',
            CountrySeason::SEASON_AUTUMN => 'Sonbahar',
            CountrySeason::SEASON_WINTER => 'Kış',
        ],
        'cs' => [
            CountrySeason::SEASON_SPRING => 'jaro',
            CountrySeason::SEASON_SUMMER => 'léto',
            CountrySeason::SEASON_AUTUMN => 'podzim',
            CountrySeason::SEASON_WINTER => 'zima',
        ],
        'da' => [
            CountrySeason::SEASON_SPRING => 'forår',
            CountrySeason::SEASON_SUMMER => 'sommer',
            CountrySeason::SEASON_AUTUMN => 'efterår',
            CountrySeason::SEASON_WINTER => 'vinter',
        ],
        'fi' => [
            CountrySeason::SEASON_SPRING => 'kevät',
            CountrySeason::SEASON_SUMMER => 'kesä',
            CountrySeason::SEASON_AUTUMN => 'syksy',
            CountrySeason::SEASON_WINTER => 'talvi',
        ],
        'nb' => [
            CountrySeason::SEASON_SPRING => 'vår',
            CountrySeason::SEASON_SUMMER => 'sommer',
            CountrySeason::SEASON_AUTUMN => 'høst',
            CountrySeason::SEASON_WINTER => 'vinter',
        ],
        'ro' => [
            CountrySeason::SEASON_SPRING => 'primăvară',
            CountrySeason::SEASON_SUMMER => 'vară',
            CountrySeason::SEASON_AUTUMN => 'toamnă',
            CountrySeason::SEASON_WINTER => 'iarnă',
        ],
        'el' => [
            CountrySeason::SEASON_SPRING => 'άνοιξη',
            CountrySeason::SEASON_SUMMER => 'καλοκαίρι',
            CountrySeason::SEASON_AUTUMN => 'φθινόπωρο',
            CountrySeason::SEASON_WINTER => 'χειμώνας',
        ],
        'he' => [
            CountrySeason::SEASON_SPRING => 'אביב',
            CountrySeason::SEASON_SUMMER => 'קיץ',
            CountrySeason::SEASON_AUTUMN => 'סתיו',
            CountrySeason::SEASON_WINTER => 'חורף',
        ],
        'hu' => [
            CountrySeason::SEASON_SPRING => 'tavasz',
            CountrySeason::SEASON_SUMMER => 'nyár',
            CountrySeason::SEASON_AUTUMN => 'ősz',
            CountrySeason::SEASON_WINTER => 'tél',
        ],
    ];

    /**
     * Full-locale overrides that differ from the language-level entry.
     * Keys are lowercase underscore (e.g. "en_us").
     */
    public const OVERRIDES = [
        'en_us' => [
            CountrySeason::SEASON_SPRING => 'Spring',
            CountrySeason::SEASON_SUMMER => 'Summer',
            CountrySeason::SEASON_AUTUMN => 'Fall',
            CountrySeason::SEASON_WINTER => 'Winter',
        ],
    ];
}
