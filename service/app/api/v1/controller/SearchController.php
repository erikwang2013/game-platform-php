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
 * C端 - 搜索
 *
 * @Apidoc\Title("搜索")
 * @Apidoc\Group("search")
 */
class SearchController extends BaseController
{
    /**
     * @Apidoc\Title("全局搜索")
     * @Apidoc\Url("/api/search")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name:"keyword",type:"string",require:true,desc:"搜索关键词")
     */
    public function search(Request $request): Response
    {
        $keyword = $request->input('keyword', '');

        return $this->success([
            'keyword' => $keyword,
            'games'  => [],
            'total'  => 0,
        ]);
    }
}
