<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\service\AntiCheatService;
use common\service\GameDashboardService;
use common\service\RateLimitDashboardService;
use common\service\RecommendService;
use common\service\RetentionService;
use common\service\RiskClickHouseService;
use common\service\SmartCouponService;
use common\service\UserProfileService;
use support\Request;
use support\Response;

class AnalyticsController extends BaseController
{
    public function overview(Request $request): Response
    {
        return $this->success([
            'today' => GameDashboardService::overview(1),
            'week'  => GameDashboardService::overview(7),
        ]);
    }

    public function gameRanking(Request $request): Response
    {
        $days = (int) $request->input('days', 7);
        return $this->success(GameDashboardService::gameRanking($days));
    }

    public function dauTrend(Request $request): Response
    {
        $days = (int) $request->input('days', 30);
        return $this->success(GameDashboardService::dauTrend($days));
    }

    public function hourlyTrend(Request $request): Response
    {
        $gameId = (int) $request->input('game_id', 0);
        return $this->success(GameDashboardService::hourlyTrend($gameId));
    }

    public function retention(Request $request): Response
    {
        $days = (int) $request->input('days', 30);
        return $this->success([
            'cohort'     => RetentionService::cohortRetention($days),
            'by_game'    => RetentionService::retentionByGame($days),
            'churn_rate' => RetentionService::churnRate($days),
        ]);
    }

    public function trending(Request $request): Response
    {
        $hours = (int) $request->input('hours', 168);
        return $this->success(RecommendService::trending($hours));
    }

    public function topIps(Request $request): Response
    {
        $hours = (int) $request->input('hours', 24);
        return $this->success(RateLimitDashboardService::topIps($hours));
    }

    public function suspiciousIps(Request $request): Response
    {
        $hours = (int) $request->input('hours', 24);
        $threshold = (int) $request->input('threshold', 100);
        return $this->success(RateLimitDashboardService::suspiciousIps($hours, $threshold));
    }

    public function riskAlerts(Request $request): Response
    {
        return $this->success([
            'high_frequency' => RiskClickHouseService::detectHighFrequency(5, 30),
            'multi_account'  => RiskClickHouseService::detectMultiAccount(24, 3),
            'ip_hopping'     => RiskClickHouseService::detectIpHopping(1, 3),
        ]);
    }

    public function antiCheat(Request $request): Response
    {
        return $this->success([
            'bot_pattern'     => AntiCheatService::detectBotPattern(),
            '24h_activity'    => AntiCheatService::detect24HourActivity(),
            'density_anomaly' => AntiCheatService::detectDensityAnomaly(),
            'account_farming' => AntiCheatService::detectAccountFarming(),
        ]);
    }

    public function userProfile(Request $request): Response
    {
        $userId = (int) $request->input('user_id', 0);
        if ($userId <= 0) {
            return $this->fail('user_id required', 422);
        }
        return $this->success(UserProfileService::getProfile($userId));
    }

    public function churnRisk(Request $request): Response
    {
        $days = (int) $request->input('days', 7);
        return $this->success(SmartCouponService::retentionRecommendations($days));
    }
}
