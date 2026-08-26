# Dokumentasi SDK Integrasi Provider Game
<!-- lang-nav -->

Languages: **中文** · [English](PROVIDER-SDK.en.md) · [한국어](PROVIDER-SDK.ko.md) · [Русский](PROVIDER-SDK.ru.md) · [Deutsch](PROVIDER-SDK.de.md) · [Français](PROVIDER-SDK.fr.md) · [Español](PROVIDER-SDK.es.md) · [Português](PROVIDER-SDK.pt.md) · [हिन्दी](PROVIDER-SDK.hi.md) · [العربية](PROVIDER-SDK.ar.md) · [বাংলা](PROVIDER-SDK.bn.md) · [Bahasa Indonesia](PROVIDER-SDK.id.md) · [日本語](PROVIDER-SDK.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Game pihak ketiga terintegrasi dengan platform melalui Provider API, mewujudkan siklus hidup lengkap kueri saldo, notifikasi taruhan, notifikasi settlement, notifikasi refund.

## 1. Ikhtisar

```
Server game                          API Platform
    │                                │
    ├─ POST /api/provider/balance ──→│  Kueri saldo pengguna
    │←── { balance: "100.5000" } ────┤
    │                                │
    ├─ POST /api/provider/bet ──────→│  Notifikasi taruhan (kurangi saldo)
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/settle ───→│  Notifikasi settlement (tambah saldo)
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/refund ───→│  Notifikasi refund
    │←── { success: true } ──────────┤
```

## 2. Algoritma Tanda Tangan

Semua panggilan Provider API wajib membawa tanda tangan HMAC-SHA256.

### Header Permintaan

```
X-Game-Id: <ID Game>
X-Timestamp: <Timestamp Unix detik>
X-Signature: <Tanda tangan HMAC-SHA256>
Content-Type: application/json
```

### Perhitungan Tanda Tangan

```
sign_str = game_id + ":" + timestamp + ":" + method + ":" + path + ":" + body
signature = HMAC-SHA256(sign_str, api_secret)
```

- `game_id`: ID game yang dialokasikan platform
- `timestamp`: timestamp Unix saat ini (detik), jendela valid 5 menit
- `method`: metode permintaan (POST)
- `path`: jalur permintaan (/api/provider/bet)
- `body`: isi permintaan (string JSON)
- `api_secret`: kunci API game yang dialokasikan platform

### Contoh PHP

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

### Contoh Go

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

### Contoh Python

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

## 3. Endpoint API

### 3.1 Kueri Saldo

```
POST /api/provider/balance
Content-Type: application/json
X-Game-Id: <ID Game>
X-Timestamp: <Timestamp>
X-Signature: <Tanda tangan>

Isi permintaan:
{
    "user_id": 9876543210,
    "game_id": 1234567890,
    "currency_id": 5555555555
}

Respons:
{
    "code": 0,
    "message": "success",
    "data": {
        "balance": "1000.50000000"
    }
}
```

### 3.2 Notifikasi Taruhan

```
POST /api/provider/bet

Isi permintaan:
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

Respons:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "990.50000000"
    }
}

Respons error:
{
    "code": 400,
    "message": "Insufficient balance",
    "data": {
        "success": false,
        "balance_after": "0.50000000"
    }
}
```

### 3.3 Notifikasi Settlement

```
POST /api/provider/settle

Isi permintaan:
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

Respons:
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

### 3.4 Notifikasi Refund

```
POST /api/provider/refund

Isi permintaan:
{
    "user_id": 9876543210,
    "session_id": "GAME_SESSION_202608041030001234",
    "amount": "10.00000000",
    "round_id": "ROUND_abc123",
    "reason": "game_crash"
}

Respons:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "1000.50000000"
    }
}
```

## 4. Integrasi Game Buatan Sendiri (SelfProvider)

Game buatan sendiri berbagi database dengan platform, `SelfProvider` menggunakan transaksi DB + `SELECT FOR UPDATE` untuk menjamin konsistensi:

```php
use app\provider\ProviderFactory;

$provider = ProviderFactory::createById($gameId);

// Kueri saldo
$balance = $provider->getBalance($userId, $gameId, $currencyId);

// Taruhan (potong dana dalam transaksi DB platform)
$result = $provider->bet($userId, $gameId, $sessionId, $amount, $roundId, $meta);
if (!$result['success']) { /* Saldo tidak cukup */ }

// Settlement (tambah dana dalam transaksi DB platform)
$result = $provider->settle($userId, $gameId, $sessionId, $amount, $roundId, $meta);

// Refund
$result = $provider->refund($userId, $gameId, $sessionId, $amount, $roundId, 'game_crash');
```

## 5. Manajemen Sesi

Setelah game dimulai, perlu mengirim heartbeat setiap 15 menit:

```php
use app\service\GameSessionService;

// Saat dimulai
GameSessionService::heartbeat($userId, $gameId, $sessionId);

// Heartbeat berkala (disarankan setiap 5 menit)
if (!GameSessionService::isActive($sessionId)) {
    // Sesi sudah timeout, perlu mengakhiri game
}

// Saat berakhir
GameSessionService::endSession($sessionId);
```

Sesi yang timeout akan disettlement otomatis (`GameSessionService::expireStaleSessions()`).

## 6. Konfigurasi Game

Sebelum integrasi, perlu membuat game di backend platform dan mendapatkan:
- `game_id`: ID game yang dialokasikan platform
- `api_key`: hanya untuk SelfProvider
- `api_secret`: kunci tanda tangan HMAC-SHA256
- `provider_config`: konfigurasi ekstensi JSON (opsional)

Konfigurasikan `provider_config` di halaman manajemen game backend:
```json
{
    "currency_id": 5555555555,
    "min_bet": "0.01000000",
    "max_bet": "1000.00000000",
    "house_edge": "0.05",
    "callback_url": "https://game-server.com/callback"
}
```

## 7. Kode Error

| code | Makna |
|------|------|
| 0 | Sukses |
| 400 | Kesalahan parameter atau saldo tidak cukup |
| 401 | Tanda tangan tidak valid atau timestamp kedaluwarsa |
| 404 | Game tidak ada atau sudah dinonaktifkan |
| 422 | Validasi parameter gagal |
| 500 | Error server |

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
