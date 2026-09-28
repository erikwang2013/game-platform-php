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

return [
    support\bootstrap\Session::class,
    support\bootstrap\Database::class,
    // 事件出口接线：把 common\service\EventPublisher（默认 no-op）注册到共享的 OutboxWriter，
    // 否则 admin 侧 PayoutService::markCompleted() 的 withdraw.completed 永远发不出去
    app\bootstrap\EventPublisherBootstrap::class,
    // poster-php 项目配置合并（验证码驱动/存储），未注册则使用包内默认配置
    Erikwang2013\Poster\Adapters\Webman\CaptchaPlugin::class,
];
