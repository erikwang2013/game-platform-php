<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use support\Log;
use support\Request;
use app\process\Http;

global $argv;

return [
    'webman' => [
        'handler' => Http::class,
        // 监听端口由 service/.env 的 APP_PORT 配置，默认 8792
        'listen' => 'http://0.0.0.0:' . (getenv('APP_PORT') ?: '8792'),
        'count' => 3,//cpu_count() * 4,
        'user' => '',
        'group' => '',
        'reusePort' => false,
        'eventLoop' => '',
        'context' => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger' => Log::channel('default'),
            'appPath' => app_path(),
            'publicPath' => public_path()
        ]
    ],
    // File update detection and automatic reload
    // 墓碑：leaderboard-ws（端口 8790）于 2026-10-02 移除。它是个死子系统 —— broadcastRanking()
    // 全仓零调用点（排行榜的实时推送从未实现），四棵客户端树也零处引用该端口；REST
    // （LeaderboardController + LeaderboardService）已满足需求。要做实时请单独立项，
    // 别再把这个进程放回来。遗留引用见 docker-compose.yml / service/README.*.md / docs/**。
    'chat-ws' => [
        'handler' => app\process\ChatWebSocket::class,
        // 端口由 service/.env 的 CHAT_WS_PORT 配置，默认 8791
        'listen' => 'websocket://0.0.0.0:' . (getenv('CHAT_WS_PORT') ?: '8791'),
        'count' => 1,
    ],

    'event-consumer' => [
        'handler' => app\process\EventConsumer::class,
        'count' => 1,
    ],
    // 非关键事件（game.played/referral.applied）仍走 Redis Pub/Sub，与 Outbox 轮询进程分离
    'event-subscriber' => [
        'handler' => app\process\EventSubscriber::class,
        'count' => 1,
        // 本进程在 onWorkerStart 里做阻塞式 subscribe（EventBus::subscribe 把 phpredis 的
        // OPT_READ_TIMEOUT 设为 -1，且 phpredis 无异步订阅）⇒ onWorkerStart 永不返回，
        // workerman 的事件循环起不来、信号永不派发。
        // reloadable=false 的确切语义（workerman Worker.php:1987-1995 / 2026-2030）：
        //   master 侧：不进 pidsToRestart ⇒ 不做「逐个优雅 reload」，也不挂 stopTimeout 后的
        //     SIGKILL 定时器（那只对 pidsToRestart 里的 pid 生效），只在 reload 时立刻发一次信号；
        //   子进程侧：reload 信号（SIGUSR1/SIGUSR2 都走 reload，:1385-1392）只 resetStd()，不 stopAll()。
        //   ⇒ reload 既不刷新它、也不强杀它（不写这一行则相反：每次 reload 等 2 秒再 SIGKILL，
        //     日志留一条 status 9）。
        // 代价：改本进程代码后 reload 不生效，必须整进程重启。stop 能杀掉它——master stopAll
        //   对全部 pid 发停止信号并在 ceil(stopTimeout) 后 SIGKILL（:2049-2061，不看 reloadable）。
        'reloadable' => false,
    ],

    // 反作弊批处理：每小时增量扫描对局日志（单实例，游标文件）
    'anti-cheat' => [
        'handler' => app\process\AntiCheatWorker::class,
        'count' => 1,
    ],

    // 组队/公会定时校正（M4）：每小时 到期解散 + member_count 对齐（单实例）
    'group-sweep' => [
        'handler' => app\process\GroupSweepWorker::class,
        'count' => 1,
    ],

    // 健康探活（L4）：每分钟探测 MySQL/Redis，失败写日志 + 指标归零（health:mysql / health:redis）
    'health' => [
        'handler' => app\process\Health::class,
        'count' => 1,
    ],

    'monitor' => [
        'handler' => app\process\Monitor::class,
        'reloadable' => false,
        'constructor' => [
            // Monitor these directories
            'monitorDir' => array_merge([
                app_path(),
                config_path(),
                base_path() . '/process',
                base_path() . '/support',
                base_path() . '/resource',
                base_path() . '/.env',
            ], glob(base_path() . '/plugin/*/app'), glob(base_path() . '/plugin/*/config'), glob(base_path() . '/plugin/*/api')),
            // Files with these suffixes will be monitored
            'monitorExtensions' => [
                'php', 'html', 'htm', 'env'
            ],
            'options' => [
                'enable_file_monitor' => !in_array('-d', $argv) && DIRECTORY_SEPARATOR === '/',
                'enable_memory_monitor' => DIRECTORY_SEPARATOR === '/',
            ]
        ]
    ]
];
