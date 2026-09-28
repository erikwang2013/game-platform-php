<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace common\service;
use common\model\WithdrawOrder;
use common\CircuitBreaker;
use support\Db;
use support\Log;
use support\Redis;

final class PayoutService
{
    private const MAX_ATTEMPTS = 5;
    private const TOKEN_TTL = 3300;

    private static $clientFactory = null;

    /**
     * 测试缝：注入 Guzzle 客户端工厂（默认 `new Client`）。同族见 EventPublisher::setPublisher /
     * NotificationService::setPushHandler——外部边界（HTTP）在这仓里一贯留缝，否则 4xx 分支
     * 无法在不起真网络请求的前提下被断言。传 null 复位。
     */
    public static function setClientFactory(?callable $factory): void
    {
        self::$clientFactory = $factory;
    }

    private static function client(array $options): \GuzzleHttp\Client
    {
        return self::$clientFactory !== null
            ? (self::$clientFactory)($options)
            : new \GuzzleHttp\Client($options);
    }

    public static function execute(WithdrawOrder $order): array
    {
        if ($order->status !== 'approved') {
            throw new \RuntimeException('Order is not in approved status');
        }
        if ($order->payout_attempts >= self::MAX_ATTEMPTS) {
            throw new \RuntimeException('Max payout attempts exceeded');
        }

        if (FeatureFlag::isEnabled('provider_mock')) {
            Log::warning('PayoutService mock mode: skip PayPal payout, order ' . $order->order_no);
            self::markCompleted($order);
            return [
                'payout_batch_id' => 'mock-' . $order->order_no,
                'payout_item_id' => 'mock-' . $order->order_no,
                'payout_status' => 'success',
                'payout_attempts' => $order->payout_attempts + 1,
            ];
        }

        // 已有批次 id ⇒ 这笔钱已经提交给 PayPal 了（可能正是响应丢失的那一次）。
        // 重发会按新 attempt 派生出一个**新**的 sender_batch_id，PayPal 视为新批次 ⇒ 真·重复打款；
        // 有批次号时只同步状态，绝不重新 POST。
        if (!empty($order->payout_batch_id)) {
            self::syncStatus($order);
            return self::result($order);
        }

        $attempt = $order->payout_attempts + 1;
        $batchId = $order->order_no . '-' . $attempt;
        $email = self::extractPaypalEmail($order);
        // 回退判据只认「未设置」：fiat_amount 是 DECIMAL(18,4) NOT NULL，全仓唯一写入方是 service 侧
        // 下单时写进报价实收（service/app/api/v1/controller/WithdrawController.php:208，且该处现已
        // 拒收实收非正）⇒ null/'' 只是防御性分支，正常流程走不到。
        // '0' **不是**「未设置」，是「应付 0」。旧判据 `bccomp($fiat,'0',4) > 0` 把 '0' 与负数一并当
        // 未设置 ⇒ 遇 fee_pct=100 吃光本金时按 platform_amount **全额照付**：用户提 10000 币被收
        // 100% 手续费，反而 1:1 拿走 10000 法币。故非正一律 fail-closed：标 failed 让运营看得见，
        // 绝不静默付错金额。
        if ($order->fiat_amount === null || $order->fiat_amount === '') {
            $amount = (string) $order->platform_amount;
        } else {
            $amount = (string) $order->fiat_amount;
            if (bccomp($amount, '0', 4) <= 0) {
                $order->payout_status = 'failed';
                $order->save();
                Log::error('Withdraw payout refused: non-positive fiat_amount', [
                    'order_no' => $order->order_no,
                    'fiat_amount' => $amount,
                    'platform_amount' => $order->platform_amount,
                ]);
                throw new \RuntimeException(
                    'Withdraw order ' . $order->order_no . ' has non-positive fiat_amount, refusing to pay platform_amount'
                );
            }
        }
        $currency = $order->currency ?: 'USD';

        $accessToken = self::getAccessToken();
        $client = self::client(['timeout' => 15]);

        try {
            $response = CircuitBreaker::call('paypal', fn () => $client->post(self::baseUrl() . '/v1/payments/payouts', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'sender_batch_header' => [
                        'sender_batch_id' => $batchId,
                        'email_subject' => 'You have a payout',
                        'email_message' => 'You have received a payout from Game Platform.',
                    ],
                    'items' => [[
                        'recipient_type' => 'EMAIL',
                        'amount' => ['value' => $amount, 'currency' => $currency],
                        'receiver' => $email,
                        'note' => 'Withdrawal ' . $order->order_no,
                        'sender_item_id' => $order->order_no,
                    ]],
                ],
            ]));
        } catch (\Throwable $e) {
            // PayPal 对 30 天内重复的 sender_batch_id 是**拒绝**（4xx），不是回放原批次；错误体里
            // 带一条指向原批次的链接。认出来就认领（落原批次 id + 计数推到本次 attempt——这次 POST
            // 确实发出去了），随后交给 syncStatus 收尾，不自己写同步逻辑。
            // 认不出来一律原样抛出：保持「5xx 用同一个 id 重试」的语义，不猜、不吞、不落库。
            self::adoptDuplicateBatch($order, $e, $attempt, $batchId);
            return self::result($order);
        }

        $body = json_decode((string) $response->getBody(), true);
        $batchHeader = $body['batch_header'] ?? [];
        $item = $body['items'][0] ?? [];

        $order->payout_batch_id = $batchHeader['payout_batch_id'] ?? '';
        $order->payout_item_id = $item['payout_item_id'] ?? '';
        $order->payout_attempts = $attempt;

        $itemStatus = $item['transaction_status'] ?? ($item['payout_item']['transaction_status'] ?? '');

        if ($itemStatus === 'SUCCESS') {
            self::markCompleted($order);
        } elseif ($itemStatus === 'FAILED') {
            $order->payout_status = 'failed';
            $order->save();
        } else {
            $order->payout_status = 'processing';
            $order->save();
        }

        return self::result($order);
    }

    public static function syncStatus(WithdrawOrder $order): string
    {
        if (empty($order->payout_batch_id)) {
            return $order->payout_status;
        }

        if (FeatureFlag::isEnabled('provider_mock')) {
            Log::warning('PayoutService mock mode: skip PayPal status check, order ' . $order->order_no);
            return 'success';
        }

        $accessToken = self::getAccessToken();
        $client = self::client(['timeout' => 10]);

        $response = CircuitBreaker::call('paypal', fn () => $client->get(self::baseUrl() . '/v1/payments/payouts/' . $order->payout_batch_id, [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
        ]));

        $body = json_decode((string) $response->getBody(), true);
        $batchStatus = $body['batch_header']['batch_status'] ?? '';
        // 明细层状态（读法同 execute()）：批次 SUCCESS 只说明批处理跑完了，唯一那条明细仍可能是
        // FAILED/UNCLAIMED/RETURNED。两层冲突时以明细为准——真正会失败的对象是明细，不是批次头。
        $item = $body['items'][0] ?? [];
        $itemStatus = $item['transaction_status'] ?? ($item['payout_item']['transaction_status'] ?? '');

        if (in_array($itemStatus, ['FAILED', 'RETURNED', 'REVERSED', 'BLOCKED', 'UNCLAIMED'], true)) {
            $order->payout_status = 'failed';
            $order->save();
            if (in_array($itemStatus, ['RETURNED', 'REVERSED'], true)) {
                Log::warning('PayPal payout item came back, funds returned to our account', [
                    'order_no' => $order->order_no,
                    'payout_batch_id' => $order->payout_batch_id,
                    'transaction_status' => $itemStatus,
                ]);
            }
            return 'failed';
        }

        if ($batchStatus === 'SUCCESS') {
            self::markCompleted($order);
            return 'success';
        }

        if ($batchStatus === 'DENIED' || $batchStatus === 'CANCELED') {
            $order->payout_status = 'failed';
            $order->save();
            return 'failed';
        }

        if (in_array($batchStatus, ['PENDING', 'PROCESSING'], true)) {
            $order->payout_status = 'processing';
            $order->save();
            return 'processing';
        }

        return $order->payout_status;
    }

    public static function getAccessToken(): string
    {
        try {
            $cached = Redis::get('paypal:token');
            if ($cached) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // 缓存不可用可降级直连 PayPal，但必须告警
            Log::warning('PayPal token Redis get failed, fetching fresh: ' . $e->getMessage());
        }

        $clientId = getenv('PAYPAL_CLIENT_ID');
        $clientSecret = getenv('PAYPAL_CLIENT_SECRET');

        if (empty($clientId) || empty($clientSecret)) {
            throw new \RuntimeException('PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET must be configured');
        }

        $client = self::client(['timeout' => 10]);
        $response = CircuitBreaker::call('paypal', fn () => $client->post(self::baseUrl() . '/v1/oauth2/token', [
            'auth' => [$clientId, $clientSecret],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]));

        $body = json_decode((string) $response->getBody(), true);
        $token = $body['access_token'] ?? '';
        if (empty($token)) {
            throw new \RuntimeException('Failed to obtain PayPal access token');
        }

        try {
            Redis::setex('paypal:token', self::TOKEN_TTL, $token);
        } catch (\Throwable $e) {
            Log::warning('PayPal token Redis setex failed (token still returned): ' . $e->getMessage());
        }
        return $token;
    }

    public static function markCompleted(WithdrawOrder $order): void
    {
        // 幂等：已完成的订单不重复标记，避免 syncStatus 轮询重复发通知
        if ($order->status === 'completed') {
            return;
        }

        // 订单状态行与事件行必须同提交：原先 save() 与 push() 是两次独立提交，两者之间进程崩溃
        // ⇒ 订单已 completed 而 outbox 无行（Health::GAP_SQL 恒报 gap，事件永不补发）。
        // 发布器由宿主注册：admin/config/bootstrap.php:24 → app\bootstrap\EventPublisherBootstrap（唯一运行处）
        // 接到 common\service\OutboxWriter::write，后者在 transactionLevel()>0 时并入本事务（OutboxWriter.php:40-43）。
        // markCompleted 的调用方（本类的 execute()/syncStatus() → admin 控制器 :564/:600）当前都不在事务里，
        // 故此处是事务发起方；将来若被包进外层事务，Illuminate 会退化成 savepoint，OutboxWriter 仍并入外层。
        Db::transaction(static function () use ($order): void {
            $order->status = 'completed';
            $order->payout_status = 'success';
            $order->paid_at = date('Y-m-d H:i:s');
            $order->save();

            // 真正打款完成才发 completed 事件（申请时发的是 withdraw.applied）。
            // eventId 与 Monitor 对账巡检 SQL 的 CONCAT('withdraw_', wo.id, '_completed') 对应。
            EventPublisher::push('withdraw.completed', "withdraw_{$order->id}_completed", [
                'user_id' => $order->user_id,
                'platform_amount' => $order->platform_amount,
                'status' => 'completed',
            ]);
        });

        NotificationService::send(
            $order->user_id,
            'withdraw',
            'Withdrawal Completed',
            "Your withdrawal of {$order->platform_amount} platform tokens has been sent to your account.",
            'withdraw',
            $order->id
        );
    }

    /** execute() 的返回形状（三处出口共用：正常、已有批次、重复 id 认领） */
    private static function result(WithdrawOrder $order): array
    {
        return [
            'payout_batch_id' => $order->payout_batch_id,
            'payout_item_id' => $order->payout_item_id,
            'payout_status' => $order->payout_status,
            'payout_attempts' => $order->payout_attempts,
        ];
    }

    /**
     * 重复 sender_batch_id 的认领：取原批次 id 落库 + 计数推到本次 attempt，再交给 syncStatus 收尾。
     *
     * 认领成功即返回（调用方据此走正常出口）；认不出来**原样抛出**——响应体缺失、形状未知、
     * 或没有可用的批次 id 都算认不出来，绝不猜一个 id 落库（猜错会让 syncStatus 查一个不存在的
     * 批次，比现状更糟），也绝不吞掉异常（吞了就没有重试，MAX_ATTEMPTS 也永不推进）。
     */
    private static function adoptDuplicateBatch(WithdrawOrder $order, \Throwable $e, int $attempt, string $batchId): void
    {
        $response = $e instanceof \GuzzleHttp\Exception\RequestException ? $e->getResponse() : null;
        $raw = $response === null ? null : (string) $response->getBody();
        $originalBatchId = self::extractDuplicateBatchId($raw);

        if ($originalBatchId === null) {
            Log::warning('PayPal payout rejected with unrecognized error body', [
                'order_no' => $order->order_no,
                'sender_batch_id' => $batchId,
                'response' => $raw,
            ]);
            throw $e;
        }

        $order->payout_batch_id = $originalBatchId;
        $order->payout_attempts = $attempt;
        $order->payout_status = 'processing';
        $order->save();

        Log::warning('PayPal duplicate sender_batch_id: adopted original batch', [
            'order_no' => $order->order_no,
            'sender_batch_id' => $batchId,
            'payout_batch_id' => $originalBatchId,
        ]);

        self::syncStatus($order);
    }

    /**
     * 从 PayPal 4xx 错误体里取原批次 id。
     *
     * 重复 sender_batch_id 的错误 JSON 精确形状未经亲验（结论来自两次检索交叉），故按「宽进」认：
     * 递归找任何字符串里形如 /v1/payments/payouts/{id} 的 href，或挂在 payout_batch_id/batch_id
     * 字段上的值。宽进不代价——识别本身要求错误里出现 DUPLICATE 或 sender_batch_id，取不到 id
     * 仍返回 null（调用方 fail-closed）。注意 sender_batch_id 是**我们自己的**单号
     * （order_no-attempt），不是 PayPal 批次 id，拿来当 payout_batch_id 会让 syncStatus 查空。
     */
    private static function extractDuplicateBatchId(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            return null;
        }

        $text = strtoupper($raw);
        if (!str_contains($text, 'DUPLICATE') && !str_contains($text, 'SENDER_BATCH_ID')) {
            return null;
        }

        $found = null;
        $walk = static function ($node) use (&$walk, &$found): void {
            foreach ((array) $node as $value) {
                if (is_array($value)) {
                    $walk($value);
                } elseif (is_string($value)
                    && preg_match('~/v1/payments/payouts/([A-Za-z0-9._-]{6,64})~', $value, $m)
                ) {
                    $found ??= $m[1];
                }
            }
        };
        $walk($body);

        foreach (['payout_batch_id', 'batch_id'] as $key) {
            if (is_string($body[$key] ?? null) && $body[$key] !== '') {
                $found ??= $body[$key];
            }
        }

        return $found;
    }

    private static function baseUrl(): string
    {
        $mode = getenv('PAYPAL_MODE') ?: 'sandbox';
        return $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private static function extractPaypalEmail(WithdrawOrder $order): string
    {
        $info = json_decode($order->account_info, true);
        if (is_array($info)) {
            return $info['paypal_email'] ?? $info['email'] ?? '';
        }
        if (str_contains($order->account_info, '@') && str_contains($order->account_info, '.')) {
            return $order->account_info;
        }
        throw new \RuntimeException('Cannot extract PayPal email from account_info');
    }
}
