<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;
use support\Db;
use support\Redis;
use Throwable;

/**
 * @Apidoc\Title("健康检查")
 * @Apidoc\Group("health")
 */
class HealthController
{
    /**
     * @Apidoc\Title("健康检查")
     * @Apidoc\Desc("检查应用、数据库、Redis和Elasticsearch的运行状态")
     * @Apidoc\Url("/health")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     */
    public function index(Request $request): Response
    {
        return json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'database'      => $this->checkDb(),
                'redis'         => $this->checkRedis(),
                'elasticsearch' => $this->checkES(),
                'timestamp'     => time(),
            ],
        ]);
    }

    private function checkDb(): string
    {
        try {
            Db::select('SELECT 1');
            return 'ok';
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    private function checkRedis(): string
    {
        try {
            Redis::ping();
            return 'ok';
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    private function checkES(): string
    {
        try {
            $hosts = config('plugin.erikwang2013.webman-scout.scout.hosts', ['http://localhost:9200']);
            $client = new \GuzzleHttp\Client(['timeout' => 2]);
            $resp = $client->get(rtrim($hosts[0], '/') . '/_cluster/health');
            $body = json_decode((string) $resp->getBody(), true);
            return $body['status'] ?? 'unknown';
        } catch (Throwable) {
            return 'unavailable';
        }
    }
}
