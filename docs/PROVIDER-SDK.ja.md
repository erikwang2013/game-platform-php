# 游戏プロバイダー接続 SDK ドキュメント
<!-- lang-nav -->

Languages: **中文** · [English](PROVIDER-SDK.en.md) · [한국어](PROVIDER-SDK.ko.md) · [Русский](PROVIDER-SDK.ru.md) · [Deutsch](PROVIDER-SDK.de.md) · [Français](PROVIDER-SDK.fr.md) · [Español](PROVIDER-SDK.es.md) · [Português](PROVIDER-SDK.pt.md) · [हिन्दी](PROVIDER-SDK.hi.md) · [العربية](PROVIDER-SDK.ar.md) · [বাংলা](PROVIDER-SDK.bn.md) · [Bahasa Indonesia](PROVIDER-SDK.id.md) · [日本語](PROVIDER-SDK.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

第三方游戏は Provider API を通じてプラットフォームと統合し、残高照会、下注通知、決済通知、返金通知という完全なライフサイクルを実現します。

## 1. 概要

```
游戏服务器                         平台API
    │                                │
    ├─ POST /api/provider/balance ──→│ 用户余额の照会
    │←── { balance: "100.5000" } ────┤
    │                                │
    ├─ POST /api/provider/bet ──────→│ 下注通知（残高を減算）
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/settle ───→│ 決済通知（残高を加算）
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/refund ───→│ 返金通知
    │←── { success: true } ──────────┤
```

## 2. 署名アルゴリズム

すべての Provider API 呼び出しは HMAC-SHA256 署名を携帯する必要があります。

### リクエストヘッダー

```
X-Game-Id: <游戏ID>
X-Timestamp: <Unix 秒級タイムスタンプ>
X-Signature: <HMAC-SHA256 署名>
Content-Type: application/json
```

### 署名の計算

```
sign_str = game_id + ":" + timestamp + ":" + method + ":" + path + ":" + body
signature = HMAC-SHA256(sign_str, api_secret)
```

- `game_id`: プラットフォームが割り当てるゲームID
- `timestamp`: 現在の Unix タイムスタンプ（秒）、有効ウィンドウ 5 分
- `method`: リクエストメソッド (POST)
- `path`: リクエストパス (/api/provider/bet)
- `body`: リクエストボディ（JSON 文字列）
- `api_secret`: プラットフォームが割り当てるゲーム API キー

### PHP 例

```php
$gameId = '1234567890';
$apiSecret = 'your_api_secret_here';
$timestamp = time();
$method = 'POST';
$path = '/api/provider/bet';
$body = json_encode([
    'user_id' => 9876543210,
    'session_id' => 'GAME_SESSION_202608041030001234',
    'amount' => '10.00000000',
    'round_id' => 'ROUND_abc123',
]);

$signStr = $gameId . ':' . $timestamp . ':' . $method . ':' . $path . ':' . $body;
$signature = hash_hmac('sha256', $signStr, $apiSecret);

$response = (new GuzzleHttp\Client())->post('https://platform.example.com' . $path, [
    'json' => json_decode($body, true),
    'headers' => [
        'X-Game-Id' => $gameId,
        'X-Timestamp' => (string) $timestamp,
        'X-Signature' => $signature,
        'Content-Type' => 'application/json',
    ],
]);
```

### Go 例

```go
import (
    "crypto/hmac"
    "crypto/sha256"
    "encoding/hex"
    "encoding/json"
    "fmt"
    "time"
)

func signRequest(gameID, apiSecret, method, path string, body map[string]interface{}) (string, int64) {
    ts := time.Now().Unix()
    bodyBytes, _ := json.Marshal(body)
    signStr := fmt.Sprintf("%s:%d:%s:%s:%s", gameID, ts, method, path, string(bodyBytes))
    mac := hmac.New(sha256.New, []byte(apiSecret))
    mac.Write([]byte(signStr))
    return hex.EncodeToString(mac.Sum(nil)), ts
}
```

### Python 例

```python
import hmac, hashlib, json, time, requests

def sign_request(game_id, api_secret, method, path, body):
    ts = int(time.time())
    body_json = json.dumps(body, separators=(',', ':'))
    sign_str = f"{game_id}:{ts}:{method}:{path}:{body_json}"
    signature = hmac.new(api_secret.encode(), sign_str.encode(), hashlib.sha256).hexdigest()
    return signature, ts

def provider_request(game_id, api_secret, base_url, path, body):
    sig, ts = sign_request(game_id, api_secret, 'POST', path, body)
    return requests.post(f"{base_url}{path}", json=body, headers={
        'X-Game-Id': game_id, 'X-Timestamp': str(ts), 'X-Signature': sig
    }).json()
```

## 3. API エンドポイント

### 3.1 残高照会

```
POST /api/provider/balance
Content-Type: application/json
X-Game-Id: <游戏ID>
X-Timestamp: <タイムスタンプ>
X-Signature: <署名>

请求体:
{
    "user_id": 9876543210,
    "game_id": 1234567890,
    "currency_id": 5555555555
}

响应:
{
    "code": 0,
    "message": "success",
    "data": {
        "balance": "1000.50000000"
    }
}
```

### 3.2 下注通知

```
POST /api/provider/bet

请求体:
{
    "user_id": 9876543210,
    "session_id": "GAME_SESSION_202608041030001234",
    "amount": "10.00000000",
    "round_id": "ROUND_abc123",
    "meta": {
        "bet_type": "straight",
        "odds": "2.50"
    }
}

响应:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "990.50000000"
    }
}

错误响应:
{
    "code": 400,
    "message": "Insufficient balance",
    "data": {
        "success": false,
        "balance_after": "0.50000000"
    }
}
```

### 3.3 決済通知

```
POST /api/provider/settle

请求体:
{
    "user_id": 9876543210,
    "session_id": "GAME_SESSION_202608041030001234",
    "amount": "50.00000000",
    "round_id": "ROUND_abc123",
    "meta": {
        "win_type": "straight",
        "odds": "5.00"
    }
}

响应:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "1040.50000000",
        "win_amount": "50.00000000"
    }
}
```

### 3.4 返金通知

```
POST /api/provider/refund

请求体:
{
    "user_id": 9876543210,
    "session_id": "GAME_SESSION_202608041030001234",
    "amount": "10.00000000",
    "round_id": "ROUND_abc123",
    "reason": "game_crash"
}

响应:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "1000.50000000"
    }
}
```

## 4. 自研游戏の接続 (SelfProvider)

自研游戏はプラットフォームとデータベースを共有し、`SelfProvider` はデータベーストランザクション + `SELECT FOR UPDATE` で整合性を保証します：

```php
use app\provider\ProviderFactory;

$provider = ProviderFactory::createById($gameId);

// 残高照会
$balance = $provider->getBalance($userId, $gameId, $currencyId);

// 下注（プラットフォームDBトランザクション内で引落）
$result = $provider->bet($userId, $gameId, $sessionId, $amount, $roundId, $meta);
if (!$result['success']) { /* 残高不足 */ }

// 決済（プラットフォームDBトランザクション内で入金）
$result = $provider->settle($userId, $gameId, $sessionId, $amount, $roundId, $meta);

// 返金
$result = $provider->refund($userId, $gameId, $sessionId, $amount, $roundId, 'game_crash');
```

## 5. セッション管理

ゲーム起動後は 15 分以内ごとにハートビートを送信する必要があります：

```php
use app\service\GameSessionService;

// 起動時
GameSessionService::heartbeat($userId, $gameId, $sessionId);

// 定期ハートビート（5 分ごとを推奨）
if (!GameSessionService::isActive($sessionId)) {
    // セッションがタイムアウトしたため、ゲームを終了する必要があります
}

// 終了時
GameSessionService::endSession($sessionId);
```

タイムアウトしたセッションは自動的に決済されます（`GameSessionService::expireStaleSessions()`）。

## 6. ゲーム設定

接続前にプラットフォームのバックエンドでゲームを作成し、以下を取得する必要があります：
- `game_id`: プラットフォームが割り当てるゲームID
- `api_key`: SelfProvider のみ
- `api_secret`: HMAC-SHA256 署名キー
- `provider_config`: JSON 拡張設定（オプション）

バックエンドのゲーム管理ページで `provider_config` を設定します：
```json
{
    "currency_id": 5555555555,
    "min_bet": "0.01000000",
    "max_bet": "1000.00000000",
    "house_edge": "0.05",
    "callback_url": "https://game-server.com/callback"
}
```

## 7. エラーコード

| code | 意味 |
|------|------|
| 0 | 成功 |
| 400 | パラメータエラーまたは残高不足 |
| 401 | 署名が無効またはタイムスタンプ期限切れ |
| 404 | ゲームが存在しないか無効化済み |
| 422 | パラメータ検証失敗 |
| 500 | サーバーエラー |

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
