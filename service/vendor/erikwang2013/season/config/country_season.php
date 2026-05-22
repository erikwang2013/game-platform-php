<?php

/**
 * 默认国家代码（ISO 3166-1 alpha-2），用于 SeasonService::getSeasonForDefault()
 *
 * Laravel / ThinkPHP / Hyperf 发布或加载此配置后，可通过各框架 config 读取 country_season.default_country_code
 */
return [
    'default_country_code' => \function_exists('env')
        ? (env('COUNTRY_SEASON_DEFAULT') ?: 'CN')
        : (getenv('COUNTRY_SEASON_DEFAULT') ?: 'CN'),
];
