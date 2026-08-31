<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use support\Log;
use support\Request;
use support\Response;

/**
 * 链路追踪中间件（L4 可观测性）：
 * - 每个请求生成/透传 trace_id（响应头 X-Trace-Id 返回客户端）
 * - 每个请求写一条结构化日志（trace_id + method + path + status + 耗时），
 *   支付回调链路 createPayment → verifyCallback → 入账 可按 trace_id 串联检索
 */
class TraceId
{
    public function process(Request $request, callable $handler): Response
    {
        $traceId = (string) $request->header('x-trace-id', '');
        if ($traceId === '' || strlen($traceId) > 64) {
            $traceId = bin2hex(random_bytes(8));
        }
        $request->traceId = $traceId;

        $start    = microtime(true);
        $response = $handler($request);
        $costMs   = (int) round((microtime(true) - $start) * 1000);

        Log::info(sprintf(
            'trace_id=%s method=%s path=%s status=%d cost_ms=%d',
            $traceId,
            $request->method(),
            $request->path(),
            $response->getStatusCode(),
            $costMs
        ));

        return $response->withHeaders(['X-Trace-Id' => $traceId]);
    }
}
