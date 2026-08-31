<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

/**
 * Provider HMAC-SHA256 签名验签工具（独立运行，无框架依赖）。
 *
 * 与 service/app/provider/GameProvider.php::signRequest 及
 * service/app/middleware/ProviderAuth.php 的协议保持一致：
 *   签名串 = {game_id}:{timestamp}:{METHOD}:{path}:{bodyJson}
 *   bodyJson 编码 = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
 *   请求头 = X-Game-Id / X-Timestamp / X-Signature
 *   时间戳窗口 = ±300 秒
 *
 * 用法：
 *   php verify-signature.php sign --game-id 1 --secret s \
 *     --method POST --path /api/provider/settle --body '{"user_id":1}'
 *   php verify-signature.php verify --game-id 1 --secret s \
 *     --method POST --path /api/provider/settle --body '{"user_id":1}' \
 *     --timestamp 1750000000 --signature <hex>
 *   php verify-signature.php              # 自检：篡改/过期均返回非零
 */

const MAX_DRIFT_SECONDS = 300;

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function arg(array $argv, string $key): ?string
{
    foreach ($argv as $i => $a) {
        if ($a === $key && isset($argv[$i + 1])) {
            return $argv[$i + 1];
        }
    }
    return null;
}

function bodyJson(string $raw): string
{
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return $raw; // 非 JSON body（如空串）按原样参与签名
}

function signString(string $gameId, int $ts, string $method, string $path, string $rawBody): string
{
    return $gameId . ':' . $ts . ':' . strtoupper($method) . ':' . $path . ':' . bodyJson($rawBody);
}

function computeSignature(string $gameId, int $ts, string $method, string $path, string $rawBody, string $secret): string
{
    return hash_hmac('sha256', signString($gameId, $ts, $method, $path, $rawBody), $secret);
}

/** @return array{0: bool, 1: string} [是否有效, 原因] */
function verify(string $gameId, int $ts, string $method, string $path, string $rawBody, string $secret, string $signature): array
{
    if (abs(time() - $ts) > MAX_DRIFT_SECONDS) {
        return [false, "timestamp expired (drift " . (time() - $ts) . "s > " . MAX_DRIFT_SECONDS . "s)"];
    }
    $expected = computeSignature($gameId, $ts, $method, $path, $rawBody, $secret);
    if (!hash_equals($expected, strtolower($signature))) {
        return [false, "signature mismatch (expected {$expected})"];
    }
    return [true, 'valid'];
}

function selfCheck(): never
{
    $gameId = '1001';
    $secret = 'demo-secret';
    $method = 'POST';
    $path = '/api/provider/settle';
    $body = '{"user_id":1,"amount":"10.00000000","round_id":"r-001"}';
    $ts = time();

    $sig = computeSignature($gameId, $ts, $method, $path, $body, $secret);

    [$ok, $reason] = verify($gameId, $ts, $method, $path, $body, $secret, $sig);
    $ok && printf("PASS sign/verify roundtrip\n") || fail("roundtrip: {$reason}");

    $tampered = str_replace('"amount":"10.00000000"', '"amount":"99.00000000"', $body);
    [$ok, $reason] = verify($gameId, $ts, $method, $path, $tampered, $secret, $sig);
    $ok && fail("tampered body accepted") || printf("PASS tampered body rejected ({$reason})\n");

    [$ok, $reason] = verify($gameId, $ts - MAX_DRIFT_SECONDS - 1, $method, $path, $body, $secret, $sig);
    $ok && fail("expired timestamp accepted") || printf("PASS expired timestamp rejected ({$reason})\n");

    printf("self-check OK\n");
    exit(0);
}

$argv = $argv ?? $_SERVER['argv'] ?? [];
$cmd = $argv[1] ?? '';

if ($cmd === 'sign') {
    $gameId = arg($argv, '--game-id') ?? fail('--game-id required');
    $secret = arg($argv, '--secret') ?? fail('--secret required');
    $method = arg($argv, '--method') ?? fail('--method required');
    $path = arg($argv, '--path') ?? fail('--path required');
    $body = arg($argv, '--body') ?? '';
    $ts = (int) (arg($argv, '--timestamp') ?? time());

    $sig = computeSignature($gameId, $ts, $method, $path, $body, $secret);
    printf("X-Game-Id: %s\nX-Timestamp: %d\nX-Signature: %s\n", $gameId, $ts, $sig);
    exit(0);
}

if ($cmd === 'verify') {
    $gameId = arg($argv, '--game-id') ?? fail('--game-id required');
    $secret = arg($argv, '--secret') ?? fail('--secret required');
    $method = arg($argv, '--method') ?? fail('--method required');
    $path = arg($argv, '--path') ?? fail('--path required');
    $body = arg($argv, '--body') ?? '';
    $ts = (int) (arg($argv, '--timestamp') ?? fail('--timestamp required'));
    $signature = arg($argv, '--signature') ?? fail('--signature required');

    [$ok, $reason] = verify($gameId, $ts, $method, $path, $body, $secret, $signature);
    printf("%s: %s\n", $ok ? 'VALID' : 'INVALID', $reason);
    exit($ok ? 0 : 1);
}

selfCheck();
