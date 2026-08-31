<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\bootstrap;

use app\service\PushService;
use common\service\NotificationService;
use Webman\Bootstrap;
use Workerman\Worker;

/**
 * service 端注册站内信推送钩子；admin 端不注册（方案批次 2：推送只发生在 C 端）。
 */
class NotificationBootstrap implements Bootstrap
{
    public static function start(?Worker $worker): void
    {
        NotificationService::setPushHandler(static function (
            int $userId,
            string $type,
            string $title,
            string $content,
            string $refType,
            int $refId
        ): void {
            PushService::send($userId, $title, $content, [
                'type' => $type,
                'ref_type' => $refType,
                'ref_id' => (string) $refId,
            ]);
        });
    }
}
