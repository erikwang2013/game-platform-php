# 게임 제공사 연동 SDK 문서
<!-- lang-nav -->

Languages: [中文](PROVIDER-SDK.md) · [English](PROVIDER-SDK.en.md) · **한국어** · [Русский](PROVIDER-SDK.ru.md) · [Deutsch](PROVIDER-SDK.de.md) · [Français](PROVIDER-SDK.fr.md) · [Español](PROVIDER-SDK.es.md) · [Português](PROVIDER-SDK.pt.md) · [हिन्दी](PROVIDER-SDK.hi.md) · [العربية](PROVIDER-SDK.ar.md) · [বাংলা](PROVIDER-SDK.bn.md) · [Bahasa Indonesia](PROVIDER-SDK.id.md) · [日本語](PROVIDER-SDK.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

서드파티 게임이 Provider API를 통해 플랫폼과 통합되어 잔액 조회, 하베팅 알림, 정산 알림, 환불 알림의 전체 라이프사이클을 구현합니다.

## 1. 개요

```
게임 서버                          플랫폼 API
    │                                │
    ├─ POST /api/provider/balance ──→│ 사용자 잔액 조회
    │←── { balance: "100.5000" } ────┤
    │                                │
    ├─ POST /api/provider/bet ──────→│ 베팅 알림（잔액 차감）
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/settle ───→│ 정산 알림（잔액 증가）
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/refund ───→│ 환불 알림
    │←── { success: true } ──────────┤
```

## 2. 서명 알고리즘

모든 Provider API 호출은 HMAC-SHA256 서명을 반드시 포함해야 합니다.

### 요청 헤더

```
X-Game-Id: <게임 ID>
X-Timestamp: <Unix 초 단위 타임스탬프>
X-Signature: <HMAC-SHA256 서명>
Content-Type: application/json
```

### 서명 계산

```
sign_str = game_id + ":" + timestamp + ":" + method + ":" + path + ":" + body
signature = HMAC-SHA256(sign_str, api_secret)
```

- `game_id`: 플랫폼이 할당한 게임 ID
- `timestamp`: 현재 Unix 타임스탬프（초）, 유효 창 5분
- `method`: 요청 메서드 (POST)
- `path`: 요청 경로 (/api/provider/bet)
- `body`: 요청 본문（JSON 문자열）
- `api_secret`: 플랫폼이 할당한 게임 API 키

### PHP 예시

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

### Go 예시

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

### Python 예시

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

## 3. API 엔드포인트

### 3.1 잔액 조회

```
POST /api/provider/balance
Content-Type: application/json
X-Game-Id: <게임 ID>
X-Timestamp: <타임스탬프>
X-Signature: <서명>

요청 본문:
{
    "user_id": 9876543210,
    "game_id": 1234567890,
    "currency_id": 5555555555
}

응답:
{
    "code": 0,
    "message": "success",
    "data": {
        "balance": "1000.50000000"
    }
}
```

### 3.2 베팅 알림

```
POST /api/provider/bet

요청 본문:
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

응답:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "990.50000000"
    }
}

오류 응답:
{
    "code": 400,
    "message": "Insufficient balance",
    "data": {
        "success": false,
        "balance_after": "0.50000000"
    }
}
```

### 3.3 정산 알림

```
POST /api/provider/settle

요청 본문:
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

응답:
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

### 3.4 환불 알림

```
POST /api/provider/refund

요청 본문:
{
    "user_id": 9876543210,
    "session_id": "GAME_SESSION_202608041030001234",
    "amount": "10.00000000",
    "round_id": "ROUND_abc123",
    "reason": "game_crash"
}

응답:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "1000.50000000"
    }
}
```

## 4. 자체 개발 게임 연동 (SelfProvider)

자체 개발 게임은 플랫폼과 데이터베이스를 공유하며, `SelfProvider`가 데이터베이스 트랜잭션 + `SELECT FOR UPDATE`로 일관성을 보장합니다:

```php
use app\provider\ProviderFactory;

$provider = ProviderFactory::createById($gameId);

// 잔액 조회
$balance = $provider->getBalance($userId, $gameId, $currencyId);

// 베팅（플랫폼 DB 트랜잭션 내 차감）
$result = $provider->bet($userId, $gameId, $sessionId, $amount, $roundId, $meta);
if (!$result['success']) { /* 잔액 부족 */ }

// 정산（플랫폼 DB 트랜잭션 내 추가）
$result = $provider->settle($userId, $gameId, $sessionId, $amount, $roundId, $meta);

// 환불
$result = $provider->refund($userId, $gameId, $sessionId, $amount, $roundId, 'game_crash');
```

## 5. 세션 관리

게임 시작 후 15분마다 하트비트를 보내야 합니다:

```php
use app\service\GameSessionService;

// 시작 시
GameSessionService::heartbeat($userId, $gameId, $sessionId);

// 주기적 하트비트（5분마다 권장）
if (!GameSessionService::isActive($sessionId)) {
    // 세션 타임아웃, 게임 종료 필요
}

// 종료 시
GameSessionService::endSession($sessionId);
```

타임아웃된 세션은 자동 정산됩니다（`GameSessionService::expireStaleSessions()`）.

## 6. 게임 설정

연동 전에 플랫폼 백오피스에서 게임을 생성하고 다음 정보를 받아야 합니다:
- `game_id`: 플랫폼이 할당한 게임 ID
- `api_key`: SelfProvider 전용
- `api_secret`: HMAC-SHA256 서명 키
- `provider_config`: JSON 확장 설정（선택）

백오피스 게임 관리 페이지에서 `provider_config`를 설정합니다:
```json
{
    "currency_id": 5555555555,
    "min_bet": "0.01000000",
    "max_bet": "1000.00000000",
    "house_edge": "0.05",
    "callback_url": "https://game-server.com/callback"
}
```

## 7. 오류 코드

| code | 의미 |
|------|------|
| 0 | 성공 |
| 400 | 파라미터 오류 또는 잔액 부족 |
| 401 | 서명 무효 또는 타임스탬프 만료 |
| 404 | 게임 없음 또는 비활성화됨 |
| 422 | 파라미터 검증 실패 |
| 500 | 서버 오류 |

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
