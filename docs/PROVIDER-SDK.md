# 游戏提供商接入 SDK 文档

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

第三方游戏通过 Provider API 与平台集成，实现余额查询、下注通知、结算通知、退款通知的完整生命周期。

## 1. 概览

```
游戏服务器                         平台API
    │                                │
    ├─ POST /api/provider/balance ──→│ 查询用户余额
    │←── { balance: "100.5000" } ────┤
    │                                │
    ├─ POST /api/provider/bet ──────→│ 通知下注（扣减余额）
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/settle ───→│ 通知结算（增加余额）
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/refund ───→│ 通知退款
    │←── { success: true } ──────────┤
```

## 2. 签名算法

所有 Provider API 调用必须携带 HMAC-SHA256 签名。

### 请求头

```
X-Game-Id: <游戏ID>
X-Timestamp: <Unix 秒级时间戳>
X-Signature: <HMAC-SHA256 签名>
Content-Type: application/json
```

### 签名计算

```
sign_str = game_id + ":" + timestamp + ":" + method + ":" + path + ":" + body
signature = HMAC-SHA256(sign_str, api_secret)
```

- `game_id`: 平台分配的游戏ID
- `timestamp`: 当前 Unix 时间戳（秒），有效窗口 5 分钟
- `method`: 请求方法 (POST)
- `path`: 请求路径 (/api/provider/bet)
- `body`: 请求体（JSON 字符串）
- `api_secret`: 平台分配的游戏 API 密钥

### PHP 示例

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

### Go 示例

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

### Python 示例

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

## 3. API 端点

### 3.1 查询余额

```
POST /api/provider/balance
Content-Type: application/json
X-Game-Id: <游戏ID>
X-Timestamp: <时间戳>
X-Signature: <签名>

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

### 3.2 通知下注

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

### 3.3 通知结算

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

### 3.4 通知退款

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

## 4. 自研游戏接入 (SelfProvider)

自研游戏与平台共享数据库，`SelfProvider` 使用数据库事务 + `SELECT FOR UPDATE` 保证一致性：

```php
use app\provider\ProviderFactory;

$provider = ProviderFactory::createById($gameId);

// 查询余额
$balance = $provider->getBalance($userId, $gameId, $currencyId);

// 下注（平台DB事务内扣款）
$result = $provider->bet($userId, $gameId, $sessionId, $amount, $roundId, $meta);
if (!$result['success']) { /* 余额不足 */ }

// 结算（平台DB事务内加款）
$result = $provider->settle($userId, $gameId, $sessionId, $amount, $roundId, $meta);

// 退款
$result = $provider->refund($userId, $gameId, $sessionId, $amount, $roundId, 'game_crash');
```

## 5. 会话管理

游戏启动后需每 15 分钟内发送心跳：

```php
use app\service\GameSessionService;

// 启动时
GameSessionService::heartbeat($userId, $gameId, $sessionId);

// 定期心跳（建议每 5 分钟）
if (!GameSessionService::isActive($sessionId)) {
    // 会话已超时，需结束游戏
}

// 结束时
GameSessionService::endSession($sessionId);
```

超时的会话会被自动结算（`GameSessionService::expireStaleSessions()`）。

## 6. 游戏配置

接入前需在平台后台创建游戏并获取：
- `game_id`: 平台分配的游戏ID
- `api_key`: 仅 SelfProvider
- `api_secret`: HMAC-SHA256 签名密钥
- `provider_config`: JSON 扩展配置（可选）

在后台游戏管理页面配置 `provider_config`：
```json
{
    "currency_id": 5555555555,
    "min_bet": "0.01000000",
    "max_bet": "1000.00000000",
    "house_edge": "0.05",
    "callback_url": "https://game-server.com/callback"
}
```

## 7. 错误码

| code | 含义 |
|------|------|
| 0 | 成功 |
| 400 | 参数错误或余额不足 |
| 401 | 签名无效或时间戳过期 |
| 404 | 游戏不存在或已禁用 |
| 422 | 参数验证失败 |
| 500 | 服务端错误 |

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
