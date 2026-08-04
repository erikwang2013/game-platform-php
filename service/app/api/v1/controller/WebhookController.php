<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\api\v1\controller;
use app\model\PlatformConfig;
use support\Request;
use support\Response;

class WebhookController extends BaseController
{
    private string $configGroup = 'webhook';

    public function list(Request $request): Response
    {
        $hooks = PlatformConfig::where('group', $this->configGroup)->where('key', $request->userId . '_%', 'like')->get();
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
        if (!filter_var($url, FILTER_VALIDATE_URL)) return $this->fail('Invalid URL', 422);
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

    public static function dispatch(string $event, array $payload): void
    {
        try {
            $configs = PlatformConfig::where('group', 'webhook')->get();
            foreach ($configs as $c) {
                $data = json_decode($c->value, true);
                if (!$data || !in_array($event, $data['events'] ?? [], true)) continue;
                (new self())->deliver($data['url'], ['event' => $event, 'payload' => $payload, 'timestamp' => time()]);
            }
        } catch (\Throwable $e) {}
    }

    private function deliver(string $url, array $data): bool
    {
        try {
            (new \GuzzleHttp\Client(['timeout' => 5]))->post($url, ['json' => $data]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
