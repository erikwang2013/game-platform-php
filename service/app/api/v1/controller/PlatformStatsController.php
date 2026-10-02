<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\Game;
use common\model\GamePlayLog;
use common\model\User;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Redis;
use support\Request;
use support\Response;

#[Apidoc\Title("平台统计")]
#[Apidoc\Group("platform")]
class PlatformStatsController extends BaseController
{
    /** 整包结果缓存键；**public** 是为了让用例能清键（同 Health::GAP_SQL 口径）。改口径时升版本号即可让旧值自然失效 */
    public const CACHE_KEY = 'platform:stats:v1';
    /** 整包缓存 TTL（秒）：也就是本端点口径的**最大滞后** */
    private const CACHE_TTL = 60;

    #[Apidoc\Title("平台公开统计")]
    #[Apidoc\Desc("C端首页展示：游戏总数、用户总数、今日局数、7日活跃用户")]
    #[Apidoc\Url("/api/v1/platform/stats")]
    #[Apidoc\Method("GET")]
    public function stats(Request $request): Response
    {
        // 整包缓存 60 秒。本端点公开无鉴权，每次请求要跑 4 条 COUNT，其中 Game::count()/User::count()
        // 是**全表计数**（InnoDB 只能扫索引），匿名可无限触发 ⇒ 是个放大杠杆。
        // ⚠ 代价明确：**口径滞后最多 60 秒**（首页展示型数字可接受，别把它当强一致读用）。
        // 读失败一律回落现算：一次缓存故障不该把公开首页打挂。
        try {
            $decoded = json_decode((string) Redis::get(self::CACHE_KEY), true);
            if (is_array($decoded)) {
                return $this->success($decoded);
            }
        } catch (\Throwable) {
        }

        // 今日局数必须写成**裸列**的半开区间 [今天 00:00:00, 明天 00:00:00)：whereDate 编译成
        // `date(created_at) = ?`（Grammar 的日期包装），列被函数包住 ⇒ game_game_play_log.idx_created_at
        // 失效、退化成全表扫；本端点公开无鉴权，匿名可无限触发。口径同 MetricsController::todayRange。
        $today    = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $stats = [
            'total_games' => Game::where('status', 1)->count(),
            'total_users' => User::count(),
            'today_game_plays' => GamePlayLog::where('created_at', '>=', $today . ' 00:00:00')
                ->where('created_at', '<', $tomorrow . ' 00:00:00')
                ->count(),
            'active_users_7d' => User::where('last_login_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))->count(),
        ];

        // 写失败静默：值已经算出来了，正常返回即可（Redis 不可用时端点退化为「每次都现算」）
        try {
            Redis::setex(self::CACHE_KEY, self::CACHE_TTL, (string) json_encode($stats, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
        }

        return $this->success($stats);
    }
}
