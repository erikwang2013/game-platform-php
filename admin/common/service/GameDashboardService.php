<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace common\service;

class GameDashboardService
{
    public static function overview(int $days): array
    {
        return ['dau' => 0, 'revenue' => 0, 'new_users' => 0];
    }

    public static function gameRanking(int $days): array { return []; }

    public static function dauTrend(int $days): array { return []; }

    public static function hourlyTrend(int $gameId = 0): array { return []; }

    public static function actionDistribution(int $gameId, int $hours): array { return []; }
}
