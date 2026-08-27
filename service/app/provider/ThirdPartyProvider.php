<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\provider;

use app\service\FeatureFlag;
use common\CircuitBreaker;
use common\Retry;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use support\Log;

class ThirdPartyProvider extends GameProvider
{
    private Client $http;

    public function __construct(\app\model\Game $game)
    {
        parent::__construct($game);
        $this->http = new Client([
            'base_uri' => $game->api_endpoint,
            'timeout' => 10,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);
    }

    public function getBalance(int $userId, int $gameId, int $currencyId): string
    {
        $result = $this->request('GET', '/api/game/balance', [
            'user_id' => $userId, 'game_id' => $gameId, 'currency_id' => $currencyId,
        ]);
        return $result['balance'] ?? '0.00000000';
    }

    public function bet(int $userId, int $gameId, string $sessionId, string $amount, string $roundId, array $meta = []): array
    {
        return $this->request('POST', '/api/game/bet', [
            'user_id' => $userId, 'game_id' => $gameId,
            'session_id' => $sessionId, 'amount' => $amount,
            'round_id' => $roundId, 'meta' => $meta,
        ]);
    }

    public function settle(int $userId, int $gameId, string $sessionId, string $amount, string $roundId, array $meta = []): array
    {
        return $this->request('POST', '/api/game/settle', [
            'user_id' => $userId, 'game_id' => $gameId,
            'session_id' => $sessionId, 'amount' => $amount,
            'round_id' => $roundId, 'meta' => $meta,
        ]);
    }

    public function refund(int $userId, int $gameId, string $sessionId, string $amount, string $roundId, string $reason): array
    {
        return $this->request('POST', '/api/game/refund', [
            'user_id' => $userId, 'game_id' => $gameId,
            'session_id' => $sessionId, 'amount' => $amount,
            'round_id' => $roundId, 'reason' => $reason,
        ]);
    }

    public function rollback(int $userId, int $gameId, string $sessionId, string $roundId): array
    {
        return $this->request('POST', '/api/game/rollback', [
            'user_id' => $userId, 'game_id' => $gameId,
            'session_id' => $sessionId, 'round_id' => $roundId,
        ]);
    }

    public function verifySignature(array $payload, string $signature): bool
    {
        $ts = $payload['timestamp'] ?? '';
        $method = $payload['method'] ?? 'POST';
        $path = $payload['path'] ?? '';
        $body = $payload;
        unset($body['timestamp'], $body['signature'], $body['method'], $body['path']);

        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signStr = $this->game->id . ':' . $ts . ':' . strtoupper($method) . ':' . $path . ':' . $bodyJson;
        $expected = hash_hmac('sha256', $signStr, $this->game->api_secret);

        return hash_equals($expected, $signature);
    }

    private function request(string $method, string $path, array $body): array
    {
        if (FeatureFlag::isEnabled('provider_mock')) {
            Log::warning("ThirdPartyProvider mock mode: provider {$this->game->name} {$method} {$path}");
            return ['success' => true];
        }

        $auth = $this->signRequest($method, $path, $body);

        try {
            return CircuitBreaker::call('provider:' . $this->game->name, function () use ($method, $path, $body, $auth) {
                try {
                    $response = $this->http->request($method, $path, [
                        'json' => $body,
                        'headers' => [
                            'X-Game-Id' => (string) $this->game->id,
                            'X-Timestamp' => (string) $auth['timestamp'],
                            'X-Signature' => $auth['signature'],
                            'Accept' => 'application/json',
                        ],
                    ]);

                    $data = json_decode((string) $response->getBody(), true);
                    if (!is_array($data)) {
                        return ['success' => false, 'error' => 'Invalid response from game server'];
                    }
                    return $data;
                } catch (GuzzleException $e) {
                    if (Retry::isRetryable($e)) {
                        throw $e; // 连接失败/5xx/超时 → 计入熔断
                    }
                    return ['success' => false, 'error' => 'Game server error: ' . $e->getMessage()];
                }
            });
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Game server unreachable: ' . $e->getMessage()];
        }
    }
}
