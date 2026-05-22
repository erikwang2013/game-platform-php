<?php

/**
 * season 插件配置
 * 配置复制到 config/plugin/erikwang2013/season/ 后会被 webman 自动加载
 */
return [
    'enable' => true,

    // 默认国家代码（ISO 3166-1 alpha-2），用于 SeasonService::getSeasonForDefault()
    'default_country_code' => \function_exists('env') ? (env('COUNTRY_SEASON_DEFAULT') ?: 'CN') : (getenv('COUNTRY_SEASON_DEFAULT') ?: 'CN'),
];
