# Documentación del SDK de integración de proveedores de juegos
<!-- lang-nav -->

Languages: [中文](PROVIDER-SDK.md) · [English](PROVIDER-SDK.en.md) · [한국어](PROVIDER-SDK.ko.md) · [Русский](PROVIDER-SDK.ru.md) · [Deutsch](PROVIDER-SDK.de.md) · [Français](PROVIDER-SDK.fr.md) · **Español** · [Português](PROVIDER-SDK.pt.md) · [हिन्दी](PROVIDER-SDK.hi.md) · [العربية](PROVIDER-SDK.ar.md) · [বাংলা](PROVIDER-SDK.bn.md) · [Bahasa Indonesia](PROVIDER-SDK.id.md) · [日本語](PROVIDER-SDK.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Los juegos de terceros se integran con la plataforma a través de la Provider API, implementando el ciclo de vida completo de consulta de saldo, notificación de apuesta, notificación de liquidación y notificación de reembolso.

## 1. Visión general

```
Servidor de juego                          API de la plataforma
    │                                │
    ├─ POST /api/provider/balance ──→│ Consultar saldo del usuario
    │←── { balance: "100.5000" } ────┤
    │                                │
    ├─ POST /api/provider/bet ──────→│ Notificar apuesta (deducir saldo)
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/settle ───→│ Notificar liquidación (añadir saldo)
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/refund ───→│ Notificar reembolso
    │←── { success: true } ──────────┤
```

## 2. Algoritmo de firma

Todas las llamadas a la Provider API deben llevar una firma HMAC-SHA256.

### Cabeceras de solicitud

```
X-Game-Id: <ID del juego>
X-Timestamp: <marca de tiempo Unix en segundos>
X-Signature: <firma HMAC-SHA256>
Content-Type: application/json
```

### Cálculo de la firma

```
sign_str = game_id + ":" + timestamp + ":" + method + ":" + path + ":" + body
signature = HMAC-SHA256(sign_str, api_secret)
```

- `game_id`: ID de juego asignado por la plataforma
- `timestamp`: marca de tiempo Unix actual (segundos), ventana válida de 5 minutos
- `method`: método de solicitud (POST)
- `path`: ruta de solicitud (/api/provider/bet)
- `body`: cuerpo de la solicitud (cadena JSON)
- `api_secret`: clave API del juego asignada por la plataforma

### Ejemplo en PHP

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

### Ejemplo en Go

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

### Ejemplo en Python

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

## 3. Endpoints de API

### 3.1 Consultar saldo

```
POST /api/provider/balance
Content-Type: application/json
X-Game-Id: <ID del juego>
X-Timestamp: <marca de tiempo>
X-Signature: <firma>

Cuerpo de la solicitud:
{
    "user_id": 9876543210,
    "game_id": 1234567890,
    "currency_id": 5555555555
}

Respuesta:
{
    "code": 0,
    "message": "success",
    "data": {
        "balance": "1000.50000000"
    }
}
```

### 3.2 Notificar apuesta

```
POST /api/provider/bet

Cuerpo de la solicitud:
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

Respuesta:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "990.50000000"
    }
}

Respuesta de error:
{
    "code": 400,
    "message": "Insufficient balance",
    "data": {
        "success": false,
        "balance_after": "0.50000000"
    }
}
```

### 3.3 Notificar liquidación

```
POST /api/provider/settle

Cuerpo de la solicitud:
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

Respuesta:
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

### 3.4 Notificar reembolso

```
POST /api/provider/refund

Cuerpo de la solicitud:
{
    "user_id": 9876543210,
    "session_id": "GAME_SESSION_202608041030001234",
    "amount": "10.00000000",
    "round_id": "ROUND_abc123",
    "reason": "game_crash"
}

Respuesta:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "1000.50000000"
    }
}
```

## 4. Integración de juegos propios (SelfProvider)

Los juegos propios comparten la base de datos con la plataforma; `SelfProvider` usa transacciones de base de datos + `SELECT FOR UPDATE` para garantizar la consistencia:

```php
use app\provider\ProviderFactory;

$provider = ProviderFactory::createById($gameId);

// Consultar saldo
$balance = $provider->getBalance($userId, $gameId, $currencyId);

// Apostar (deducción dentro de la transacción DB de la plataforma)
$result = $provider->bet($userId, $gameId, $sessionId, $amount, $roundId, $meta);
if (!$result['success']) { /* Saldo insuficiente */ }

// Liquidar (abono dentro de la transacción DB de la plataforma)
$result = $provider->settle($userId, $gameId, $sessionId, $amount, $roundId, $meta);

// Reembolsar
$result = $provider->refund($userId, $gameId, $sessionId, $amount, $roundId, 'game_crash');
```

## 5. Gestión de sesiones

Tras iniciar el juego, hay que enviar un heartbeat cada 15 minutos:

```php
use app\service\GameSessionService;

// Al iniciar
GameSessionService::heartbeat($userId, $gameId, $sessionId);

// Heartbeat periódico (recomendado cada 5 minutos)
if (!GameSessionService::isActive($sessionId)) {
    // La sesión ha expirado, hay que finalizar el juego
}

// Al finalizar
GameSessionService::endSession($sessionId);
```

Las sesiones con timeout se liquidan automáticamente (`GameSessionService::expireStaleSessions()`).

## 6. Configuración del juego

Antes de la integración hay que crear el juego en el panel de administración de la plataforma y obtener:
- `game_id`: ID de juego asignado por la plataforma
- `api_key`: solo para SelfProvider
- `api_secret`: clave de firma HMAC-SHA256
- `provider_config`: configuración extendida JSON (opcional)

Configurar `provider_config` en la página de gestión de juegos del panel:
```json
{
    "currency_id": 5555555555,
    "min_bet": "0.01000000",
    "max_bet": "1000.00000000",
    "house_edge": "0.05",
    "callback_url": "https://game-server.com/callback"
}
```

## 7. Códigos de error

| code | Significado |
|------|------|
| 0 | Éxito |
| 400 | Error de parámetros o saldo insuficiente |
| 401 | Firma inválida o marca de tiempo caducada |
| 404 | El juego no existe o está deshabilitado |
| 422 | Fallo de validación de parámetros |
| 500 | Error del servidor |

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
