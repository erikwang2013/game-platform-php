<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\api\v1\controller;
use app\event\EventBus;
use app\model\EventOutbox;
use app\model\PlatformConfig;
use support\Log;
use support\Request;
use support\Response;

class WebhookController extends BaseController
{
    private string $configGroup = 'webhook';

    public function list(Request $request): Response
    {
        // 转义 LIKE 通配符：key 为 "{userId}_{hookId}"，不转义时 userId=1 可匹配到 userId=10 的配置
        $hooks = PlatformConfig::where('group', $this->configGroup)->where('key', 'like', $request->userId . '\_%')->get();
        $items = [];
        foreach ($hooks as $h) {
            $items[] = json_decode($h->value, true);
        }
        return $this->success(['list' => $items]);
    }

    public function register(Request $request): Response
    {
        $url = $request->input('url', '');
        $events = $request->input('events', []);
        if (!self::isSafeWebhookUrl($url)) return $this->fail('Invalid URL: 仅支持 https 公网地址', 422);
        if (empty($events) || !is_array($events)) return $this->fail('Events required', 422);

        $allowedEvents = ['deposit.completed', 'withdraw.completed', 'exchange.completed', 'game.played', 'user.registered', 'risk.alert', 'user.vip_upgraded'];
        $filtered = array_intersect($events, $allowedEvents);
        if (empty($filtered)) return $this->fail('No valid events', 422);

        $hookId = bin2hex(random_bytes(16));
        $config = ['id' => $hookId, 'url' => $url, 'events' => $filtered, 'created_at' => date('Y-m-d H:i:s')];

        $key = $request->userId . '_' . $hookId;
        PlatformConfig::set($this->configGroup, $key, json_encode($config));

        return $this->success(['id' => $hookId, 'url' => $url, 'events' => $filtered], 'Webhook registered');
    }

    public function delete(Request $request): Response
    {
        $hookId = $request->input('id', '');
        if (empty($hookId)) return $this->fail('id required', 422);

        $key = $request->userId . '_' . $hookId;
        $config = PlatformConfig::where('group', $this->configGroup)->where('key', $key)->first();
        if (!$config) return $this->fail('Webhook not found', 404);
        $config->delete();

        return $this->success([], 'Webhook deleted');
    }

    public function test(Request $request): Response
    {
        $hookId = $request->input('id', '');
        if (empty($hookId)) return $this->fail('id required', 422);
        $key = $request->userId . '_' . $hookId;
        $config = PlatformConfig::where('group', $this->configGroup)->where('key', $key)->first();
        if (!$config) return $this->fail('Webhook not found', 404);

        $data = json_decode($config->value, true);
        $result = $this->deliver($data['url'], ['event' => 'test', 'timestamp' => time()]);

        return $this->success(['delivered' => $result]);
    }

    public static function dispatch(string $event, array $payload, ?string $eventId = null): void
    {
        // 幂等去重：Outbox 中已消费（status=1）的事件不重复投递，防止重放/崩溃窗口重复消费
        if ($eventId !== null
            && EventOutbox::where('event_id', $eventId)->where('status', EventOutbox::STATUS_SENT)->exists()
        ) {
            return;
        }

        $failed = [];
        try {
            $configs = PlatformConfig::where('group', 'webhook')->get();
            foreach ($configs as $c) {
                $data = json_decode($c->value, true);
                if (!$data || !in_array($event, $data['events'] ?? [], true)) continue;
                $ok = (new self())->deliver($data['url'], [
                    'event' => $event,
                    'event_id' => $eventId,
                    'payload' => $payload,
                    'timestamp' => time(),
                ]);
                if (!$ok) {
                    $failed[] = (string) ($data['url'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Webhook dispatch failed: ' . $e->getMessage(), [
                'event' => $event,
            ]);
            if (in_array($event, EventBus::RELIABLE_EVENTS, true)) {
                throw $e;
            }
        }

        // 关键事件投递失败向上抛，驱动 Outbox 重试直至死信
        if ($failed !== [] && in_array($event, EventBus::RELIABLE_EVENTS, true)) {
            throw new \RuntimeException('Webhook deliver failed: ' . implode(', ', $failed));
        }
    }

    private function deliver(string $url, array $data): bool
    {
        if (!self::isSafeWebhookUrl($url)) return false;
        try {
            // 禁重定向：isSafeWebhookUrl 只校验首跳，跟随 302 可被重定向到内网
            (new \GuzzleHttp\Client(['timeout' => 5, 'allow_redirects' => false]))->post($url, ['json' => $data]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('Webhook deliver failed: ' . $e->getMessage(), [
                'url' => $url,
                'event' => $data['event'] ?? '',
            ]);
            return false;
        }
    }

    /**
     * SSRF 防护: 仅允许 https 公网地址, 拒绝内网/环回/保留 IP 段
     */
    public static function isSafeWebhookUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') return false;
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') return false;
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (empty($ips)) return false;
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }
        return true;
    }
}
