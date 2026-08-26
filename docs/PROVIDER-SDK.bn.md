# 游戏提供商接入 SDK 文档
<!-- lang-nav -->

Languages: [中文](PROVIDER-SDK.md) · [English](PROVIDER-SDK.en.md) · [한국어](PROVIDER-SDK.ko.md) · [Русский](PROVIDER-SDK.ru.md) · [Deutsch](PROVIDER-SDK.de.md) · [Français](PROVIDER-SDK.fr.md) · [Español](PROVIDER-SDK.es.md) · [Português](PROVIDER-SDK.pt.md) · [हिन्दी](PROVIDER-SDK.hi.md) · [العربية](PROVIDER-SDK.ar.md) · **বাংলা** · [Bahasa Indonesia](PROVIDER-SDK.id.md) · [日本語](PROVIDER-SDK.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

থার্ড-পার্টি গেম Provider API-এর মাধ্যমে প্ল্যাটফর্মের সাথে ইন্টিগ্রেট হয়, ব্যালেন্স কুয়েরি, বেট নোটিফিকেশন, সেটেলমেন্ট নোটিফিকেশন, রিফান্ড নোটিফিকেশনের সম্পূর্ণ লাইফসাইকেল বাস্তবায়ন করে।

## 1. ওভারভিউ

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

## 2. সিগনেচার অ্যালগরিদম

সব Provider API কল অবশ্যই HMAC-SHA256 সিগনেচার বহন করবে।

### রিকোয়েস্ট হেডার

```
X-Game-Id: <游戏ID>
X-Timestamp: <Unix 秒级时间戳>
X-Signature: <HMAC-SHA256 签名>
Content-Type: application/json
```

### সিগনেচার গণনা

```
sign_str = game_id + ":" + timestamp + ":" + method + ":" + path + ":" + body
signature = HMAC-SHA256(sign_str, api_secret)
```

- `game_id`: প্ল্যাটফর্ম প্রদত্ত গেম ID
- `timestamp`: বর্তমান Unix টাইমস্ট্যাম্প (সেকেন্ড), বৈধ উইন্ডো ৫ মিনিট
- `method`: রিকোয়েস্ট মেথড (POST)
- `path`: রিকোয়েস্ট পাথ (/api/provider/bet)
- `body`: রিকোয়েস্ট বডি (JSON স্ট্রিং)
- `api_secret`: প্ল্যাটফর্ম প্রদত্ত গেম API সিক্রেট

### PHP উদাহরণ

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

### Go উদাহরণ

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

### Python উদাহরণ

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

## 3. API এন্ডপয়েন্ট

### 3.1 ব্যালেন্স কুয়েরি

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

### 3.2 বেট নোটিফিকেশন

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

### 3.3 সেটেলমেন্ট নোটিফিকেশন

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

### 3.4 রিফান্ড নোটিফিকেশন

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

## 4. নিজস্ব গেম ইন্টিগ্রেশন (SelfProvider)

নিজস্ব গেম প্ল্যাটফর্মের সাথে ডেটাবেস শেয়ার করে, `SelfProvider` ডেটাবেস ট্রানজেকশন + `SELECT FOR UPDATE` দিয়ে সামঞ্জস্য নিশ্চিত করে:

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

## 5. সেশন ম্যানেজমেন্ট

গেম লঞ্চের পর প্রতি ১৫ মিনিটের মধ্যে হার্টবিট পাঠাতে হবে:

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

টাইমআউট হওয়া সেশন অটো সেটেল হয় (`GameSessionService::expireStaleSessions()`)।

## 6. গেম কনফিগ

ইন্টিগ্রেশনের আগে প্ল্যাটফর্ম ব্যাকএন্ডে গেম তৈরি করে নিতে হবে এবং পেতে হবে:
- `game_id`: প্ল্যাটফর্ম প্রদত্ত গেম ID
- `api_key`: শুধুমাত্র SelfProvider
- `api_secret`: HMAC-SHA256 সিগনেচার সিক্রেট
- `provider_config`: JSON এক্সটেনশন কনফিগ (ঐচ্ছিক)

ব্যাকএন্ড গেম ম্যানেজমেন্ট পেজে `provider_config` কনফিগ করুন:
```json
{
    "currency_id": 5555555555,
    "min_bet": "0.01000000",
    "max_bet": "1000.00000000",
    "house_edge": "0.05",
    "callback_url": "https://game-server.com/callback"
}
```

## 7. ত্রুটি কোড

| code | অর্থ |
|------|------|
| 0 | সফল |
| 400 | প্যারামিটার ত্রুটি বা ব্যালেন্স অপর্যাপ্ত |
| 401 | সিগনেচার অবৈধ বা টাইমস্ট্যাম্প মেয়াদোত্তীর্ণ |
| 404 | গেম অস্তিত্ব নেই বা ডিসেবল |
| 422 | প্যারামিটার ভেরিফিকেশন ব্যর্থ |
| 500 | সার্ভার ত্রুটি |

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
