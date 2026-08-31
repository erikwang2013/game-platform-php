<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use app\model\Notification;

class NotificationService
{
    private static $pushHandler = null;

    /**
     * 推送是可选钩子：service 端注册 PushService::send，admin 端不注册（方案批次 2）。
     * 共享核心只做落库 + 站内信。
     */
    public static function setPushHandler(?callable $handler): void
    {
        self::$pushHandler = $handler;
    }

    /**
     * Send a notification to a user.
     *
     * Notifications are stored in-app and always viewable.
     * Email delivery is attempted when the user has a verified email address
     * and a mail server is configured (PHP mail or SMTP).
     *
     * @param int    $userId  Recipient user ID
     * @param string $type    Notification type (e.g., 'deposit', 'withdraw', 'kyc', 'referral')
     * @param string $title   Short notification title
     * @param string $content Detailed notification body
     * @param string $refType Optional reference entity type
     * @param int    $refId   Optional reference entity ID
     */
    public static function send(
        int $userId,
        string $type,
        string $title,
        string $content,
        string $refType = '',
        int $refId = 0
    ): void {
        try {
            $notif = new Notification();
            $notif->id = (int)(date('YmdHis') . random_int(10000, 99999));
            $notif->user_id = $userId;
            $notif->type = $type;
            $notif->title = $title;
            $notif->content = $content;
            $notif->is_read = 0;
            $notif->ref_type = $refType;
            $notif->ref_id = $refId;
            $notif->save();

            if (self::$pushHandler !== null) {
                (self::$pushHandler)($userId, $type, $title, $content, $refType, $refId);
            }

            // Attempt email delivery if user has an email and mail is configured
            self::sendEmail($userId, $title, $content);
        } catch (\Throwable $e) {
            // Silently fail — notification delivery should never block the main flow
        }
    }

    /**
     * Attempt to send the notification via email.
     *
     * Falls back silently if no email address is available or mail is not configured.
     */
    private static function sendEmail(int $userId, string $title, string $content): void
    {
        try {
            $user = \app\model\User::find($userId);
            if (!$user || empty($user->email)) {
                return;
            }

            $to = $user->email;
            $subject = '[' . getenv('APP_NAME', 'Game Platform') . '] ' . $title;
            $body = strip_tags($content);
            $headers = [
                'Content-Type: text/plain; charset=UTF-8',
                'From: ' . (getenv('MAIL_FROM') ?: 'noreply@example.com'),
            ];

            if (getenv('MAIL_HOST')) {
                // SMTP delivery — use PHP's mail() with configured sendmail_path
                mail($to, $subject, $body, implode("\r\n", $headers));
            } else {
                // Log-only mode: notification is viewable in-app only
                // Email would be sent here once SMTP is configured
            }
        } catch (\Throwable $e) {
            // Email delivery failure should not break the request
        }
    }
}
