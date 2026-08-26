# SDK-Dokumentation für Spielanbieter-Integration
<!-- lang-nav -->

Languages: **中文** · [English](PROVIDER-SDK.en.md) · [한국어](PROVIDER-SDK.ko.md) · [Русский](PROVIDER-SDK.ru.md) · [Deutsch](PROVIDER-SDK.de.md) · [Français](PROVIDER-SDK.fr.md) · [Español](PROVIDER-SDK.es.md) · [Português](PROVIDER-SDK.pt.md) · [हिन्दी](PROVIDER-SDK.hi.md) · [العربية](PROVIDER-SDK.ar.md) · [বাংলা](PROVIDER-SDK.bn.md) · [Bahasa Indonesia](PROVIDER-SDK.id.md) · [日本語](PROVIDER-SDK.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Drittanbieter-Spiele integrieren sich über die Provider-API in die Plattform und decken damit den vollständigen Lebenszyklus ab: Guthabenabfrage, Einsatzbenachrichtigung, Abrechnungsbenachrichtigung und Rückerstattungsbenachrichtigung.

## 1. Übersicht

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

## 2. Signaturalgorithmus

Alle Provider-API-Aufrufe müssen eine HMAC-SHA256-Signatur tragen.

### Request-Header

```
X-Game-Id: <游戏ID>
X-Timestamp: <Unix 秒级时间戳>
X-Signature: <HMAC-SHA256 签名>
Content-Type: application/json
```

### Signaturberechnung

```
sign_str = game_id + ":" + timestamp + ":" + method + ":" + path + ":" + body
signature = HMAC-SHA256(sign_str, api_secret)
```

- `game_id`: Die von der Plattform zugewiesene Spiel-ID
- `timestamp`: Aktueller Unix-Zeitstempel (Sekunden), gültiges Fenster 5 Minuten
- `method`: Anfragemethode (POST)
- `path`: Anforderungspfad (/api/provider/bet)
- `body`: Anforderungstext (JSON-String)
- `api_secret`: Der von der Plattform zugewiesene Spiel-API-Schlüssel

### PHP-Beispiel

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

### Go-Beispiel

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

### Python-Beispiel

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

## 3. API-Endpunkte

### 3.1 Guthaben abfragen

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

### 3.2 Einsatz melden

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

### 3.3 Abrechnung melden

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

### 3.4 Rückerstattung melden

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

## 4. Integration eigener Spiele (SelfProvider)

Eigene Spiele teilen sich die Datenbank mit der Plattform; `SelfProvider` verwendet Datenbanktransaktionen + `SELECT FOR UPDATE`, um die Konsistenz zu gewährleisten:

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

## 5. Session-Verwaltung

Nach dem Spielstart muss alle 15 Minuten ein Heartbeat gesendet werden:

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

Abgelaufene Sessions werden automatisch abgerechnet (`GameSessionService::expireStaleSessions()`).

## 6. Spielkonfiguration

Vor der Integration muss das Spiel im Plattform-Backend erstellt werden; anschließend erhält man:
- `game_id`: Die von der Plattform zugewiesene Spiel-ID
- `api_key`: nur für SelfProvider
- `api_secret`: HMAC-SHA256-Signaturschlüssel
- `provider_config`: JSON-Erweiterungskonfiguration (optional)

`provider_config` auf der Spielverwaltungsseite des Backends konfigurieren:
```json
{
    "currency_id": 5555555555,
    "min_bet": "0.01000000",
    "max_bet": "1000.00000000",
    "house_edge": "0.05",
    "callback_url": "https://game-server.com/callback"
}
```

## 7. Fehlercodes

| code | Bedeutung |
|------|------|
| 0 | Erfolg |
| 400 | Parameterfehler oder unzureichendes Guthaben |
| 401 | Ungültige Signatur oder abgelaufener Zeitstempel |
| 404 | Spiel existiert nicht oder ist deaktiviert |
| 422 | Parametervalidierung fehlgeschlagen |
| 500 | Serverfehler |

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
