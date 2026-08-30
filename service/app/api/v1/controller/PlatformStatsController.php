<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\model\Game;
use app\model\GamePlayLog;
use app\model\User;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("平台统计")
 * @Apidoc\Group("platform")
 */
class PlatformStatsController extends BaseController
{
    /**
     * @Apidoc\Title("平台公开统计")
     * @Apidoc\Desc("C端首页展示：游戏总数、用户总数、今日局数、7日活跃用户")
     * @Apidoc\Url("/api/platform/stats")
     * @Apidoc\Method("GET")
     */
    public function stats(Request $request): Response
    {
        return $this->success([
            'total_games' => Game::where('status', 1)->count(),
            'total_users' => User::count(),
            'today_game_plays' => GamePlayLog::whereDate('created_at', date('Y-m-d'))->count(),
            'active_users_7d' => User::where('last_login_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))->count(),
        ]);
    }
}
