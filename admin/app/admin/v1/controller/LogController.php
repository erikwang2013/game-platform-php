<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use app\model\OperationLog;
use support\Request;
use support\Response;

#[Apidoc\Title("操作日志")]
#[Apidoc\Group("log")]
class LogController extends BaseController
{
    #[Apidoc\Title("操作日志列表")]
    #[Apidoc\Desc("分页获取操作日志，支持多条件筛选")]
    #[Apidoc\Url("/admin/v1/log")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "page", type: "int", require: false, desc: "页码")]
    #[Apidoc\Param(name: "limit", type: "int", require: false, desc: "每页数量")]
    #[Apidoc\Param(name: "user_id", type: "int", require: false, desc: "操作用户ID")]
    #[Apidoc\Param(name: "action", type: "string", require: false, desc: "操作动作")]
    #[Apidoc\Param(name: "path", type: "string", require: false, desc: "请求路径")]
    #[Apidoc\Param(name: "start_date", type: "string", require: false, desc: "开始日期")]
    #[Apidoc\Param(name: "end_date", type: "string", require: false, desc: "结束日期")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "日志ID(hashid编码)")]
    public function index(Request $request): Response
    {
        $page      = (int) $request->input('page', 1);
        // clamp [1,200]：200 是本仓客户端的最大合法取数（游戏/角色下拉一次拉全）；无上界时 ?limit=10000000 直接拉全表
        $limit     = min(200, max(1, (int) $request->input('limit', 15)));
        $userId    = $request->input('user_id');
        $action    = $request->input('action');
        $path      = $request->input('path');
        // 只取日期部分再自己拼时分秒：whereDate() 会被编译成 `date(created_at) >= ?`
        // （vendor Grammar.php:526-531），列被函数包住 ⇒ 索引失效 ⇒ 每次筛选都全表扫。
        // 输入是 <input type="date"> 给的 Y-m-d（DocsController:141 也声明为 'date?'），
        // 但请求串是原始的，先归一成日期再拼，避免把 '2026-10-01 12:00:00' 拼成非法值。
        $startDate = $this->dateOnly($request->input('start_date'));
        $endDate   = $this->dateOnly($request->input('end_date'));

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
            $query->where('created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $total = $query->count();
        $list  = $query->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->orderBy('id', 'desc')
                       ->get()
                       ->map(function ($log) {
                           $data = $log->toArray();
                           $data['id']        = $this->encodeId($data['id']);
                           $data['user_name'] = $log->user->username ?? trans('System');
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

    /**
     * 取日期部分（Y-m-d）。不是日期形状就返回 null = 视为未传、不做过滤。
     *
     * 端点契约是纯日期（DocsController:141 `'start_date' => 'date?'`，前端是
     * `<input type="date">`），所以正常路径永远命中第一个分支；归一只是防止
     * 手拼 ' 23:59:59' 时把带时间的串拼成 `'2026-10-01 12:00:00 23:59:59'` 这种非法值。
     */
    private function dateOnly(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^(\d{4}-\d{2}-\d{2})/', trim($value), $m)) {
            return null;
        }

        return $m[1];
    }
}
