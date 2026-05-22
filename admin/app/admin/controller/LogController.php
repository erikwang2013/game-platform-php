<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use app\model\OperationLog;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("操作日志")
 * @Apidoc\Group("log")
 */
class LogController extends BaseController
{
    /**
     * @Apidoc\Title("操作日志列表")
     * @Apidoc\Desc("分页获取操作日志，支持多条件筛选")
     * @Apidoc\Url("/admin/log")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("page", type="int", require=false, desc="页码")
     * @Apidoc\Param("per_page", type="int", require=false, desc="每页数量")
     * @Apidoc\Param("user_id", type="int", require=false, desc="操作用户ID")
     * @Apidoc\Param("action", type="string", require=false, desc="操作动作")
     * @Apidoc\Param("path", type="string", require=false, desc="请求路径")
     * @Apidoc\Param("start_date", type="string", require=false, desc="开始日期")
     * @Apidoc\Param("end_date", type="string", require=false, desc="结束日期")
     * @Apidoc\Returned("id", type="string", desc="日志ID(hashid编码)")
     */
    public function index(Request $request): Response
    {
        $page      = (int) $request->input('page', 1);
        $limit     = (int) $request->input('limit', 15);
        $userId    = $request->input('user_id');
        $action    = $request->input('action');
        $path      = $request->input('path');
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $query = OperationLog::with('user');

        if ($userId) {
            $query->where('user_id', $userId);
        }
        if ($action) {
            $query->where('action', $action);
        }
        if ($path) {
            $query->where('path', 'like', "%{$path}%");
        }
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $total = $query->count();
        $list  = $query->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->orderBy('id', 'desc')
                       ->get()
                       ->map(function ($log) {
                           $data = $log->toArray();
                           $data['id']        = $this->encodeId($data['id']);
                           $data['user_name'] = $log->user->username ?? '系统';
                           unset($data['user'], $data['user_id']);
                           return $data;
                       });

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }
}
