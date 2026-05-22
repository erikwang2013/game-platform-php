<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * C端 - 排行榜
 *
 * @Apidoc\Title("排行榜")
 * @Apidoc\Group("leaderboard")
 */
class LeaderboardController extends BaseController
{
    /**
     * @Apidoc\Title("排行榜列表")
     * @Apidoc\Url("/api/leaderboard/list")
     * @Apidoc\Method("GET")
     */
    public function list(Request $request): Response
    {
        return $this->success([
            'list' => [
                [
                    'id'    => $this->encodeId(1),
                    'name'  => 'Daily Top Earners',
                    'type'  => 'earning',
                    'period' => 'daily',
                ],
                [
                    'id'    => $this->encodeId(2),
                    'name'  => 'Weekly Champions',
                    'type'  => 'game',
                    'period' => 'weekly',
                ],
            ],
        ]);
    }

    /**
     * @Apidoc\Title("排行榜详情")
     * @Apidoc\Url("/api/leaderboard/{hashid}")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name:"hashid",type:"string",require:true,desc:"排行榜HashID")
     */
    public function ranking(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);

        return $this->success([
            'id'       => $this->encodeId($id),
            'name'     => 'Daily Top Earners',
            'rankings' => [],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
