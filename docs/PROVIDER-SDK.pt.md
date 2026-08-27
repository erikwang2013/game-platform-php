# Documentação do SDK de integração de provedores de jogos
<!-- lang-nav -->

Languages: **中文** · [English](PROVIDER-SDK.en.md) · [한국어](PROVIDER-SDK.ko.md) · [Русский](PROVIDER-SDK.ru.md) · [Deutsch](PROVIDER-SDK.de.md) · [Français](PROVIDER-SDK.fr.md) · [Español](PROVIDER-SDK.es.md) · [Português](PROVIDER-SDK.pt.md) · [हिन्दी](PROVIDER-SDK.hi.md) · [العربية](PROVIDER-SDK.ar.md) · [বাংলা](PROVIDER-SDK.bn.md) · [Bahasa Indonesia](PROVIDER-SDK.id.md) · [日本語](PROVIDER-SDK.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Jogos de terceiros se integram à plataforma através da Provider API, cobrindo o ciclo de vida completo de consulta de saldo, notificação de aposta, notificação de liquidação e notificação de reembolso.

## 1. Visão geral

```
Servidor do jogo                          API da plataforma
    │                                │
    ├─ POST /api/provider/balance ──→│ Consultar saldo do usuário
    │←── { balance: "100.5000" } ────┤
    │                                │
    ├─ POST /api/provider/bet ──────→│ Notificar aposta (deduzir saldo)
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/settle ───→│ Notificar liquidação (adicionar saldo)
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/refund ───→│ Notificar reembolso
    │←── { success: true } ──────────┤
```

## 2. Algoritmo de assinatura

Todas as chamadas da Provider API devem carregar a assinatura HMAC-SHA256.

### Cabeçalhos da requisição

```
X-Game-Id: <ID do jogo>
X-Timestamp: <timestamp Unix em segundos>
X-Signature: <assinatura HMAC-SHA256>
Content-Type: application/json
```

### Cálculo da assinatura

```
sign_str = game_id + ":" + timestamp + ":" + method + ":" + path + ":" + body
signature = HMAC-SHA256(sign_str, api_secret)
```

- `game_id`: ID do jogo atribuído pela plataforma
- `timestamp`: timestamp Unix atual (segundos), janela válida de 5 minutos
- `method`: método da requisição (POST)
- `path`: caminho da requisição (/api/provider/bet)
- `body`: corpo da requisição (string JSON)
- `api_secret`: chave de API do jogo atribuída pela plataforma

### Exemplo em PHP

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

### Exemplo em Go

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

### Exemplo em Python

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

## 3. Endpoints da API

### 3.1 Consultar saldo

```
POST /api/provider/balance
Content-Type: application/json
X-Game-Id: <ID do jogo>
X-Timestamp: <timestamp>
X-Signature: <assinatura>

Corpo da requisição:
{
    "user_id": 9876543210,
    "game_id": 1234567890,
    "currency_id": 5555555555
}

Resposta:
{
    "code": 0,
    "message": "success",
    "data": {
        "balance": "1000.50000000"
    }
}
```

### 3.2 Notificar aposta

```
POST /api/provider/bet

Corpo da requisição:
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

Resposta:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "990.50000000"
    }
}

Resposta de erro:
{
    "code": 400,
    "message": "Insufficient balance",
    "data": {
        "success": false,
        "balance_after": "0.50000000"
    }
}
```

### 3.3 Notificar liquidação

```
POST /api/provider/settle

Corpo da requisição:
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

Resposta:
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

Corpo da requisição:
{
    "user_id": 9876543210,
    "session_id": "GAME_SESSION_202608041030001234",
    "amount": "10.00000000",
    "round_id": "ROUND_abc123",
    "reason": "game_crash"
}

Resposta:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "1000.50000000"
    }
}
```

## 4. Integração de jogos próprios (SelfProvider)

Jogos próprios compartilham o banco de dados com a plataforma; `SelfProvider` usa transação de banco + `SELECT FOR UPDATE` para garantir consistência:

```php
use app\provider\ProviderFactory;

$provider = ProviderFactory::createById($gameId);

// Consultar saldo
$balance = $provider->getBalance($userId, $gameId, $currencyId);

// Apostar (dedução dentro da transação DB da plataforma)
$result = $provider->bet($userId, $gameId, $sessionId, $amount, $roundId, $meta);
if (!$result['success']) { /* saldo insuficiente */ }

// Liquidar (crédito dentro da transação DB da plataforma)
$result = $provider->settle($userId, $gameId, $sessionId, $amount, $roundId, $meta);

// Reembolsar
$result = $provider->refund($userId, $gameId, $sessionId, $amount, $roundId, 'game_crash');
```

## 5. Gerenciamento de sessão

Após iniciar o jogo, é preciso enviar heartbeat a cada 15 minutos:

```php
use app\service\GameSessionService;

// Ao iniciar
GameSessionService::heartbeat($userId, $gameId, $sessionId);

// Heartbeat periódico (recomendado a cada 5 minutos)
if (!GameSessionService::isActive($sessionId)) {
    // A sessão expirou, é preciso encerrar o jogo
}

// Ao encerrar
GameSessionService::endSession($sessionId);
```

Sessões expiradas são liquidadas automaticamente (`GameSessionService::expireStaleSessions()`).

## 6. Configuração do jogo

Antes da integração, é preciso criar o jogo no painel administrativo da plataforma e obter:
- `game_id`: ID do jogo atribuído pela plataforma
- `api_key`: apenas para SelfProvider
- `api_secret`: chave de assinatura HMAC-SHA256
- `provider_config`: configuração estendida JSON (opcional)

Configure `provider_config` na página de gerenciamento de jogos do painel:
```json
{
    "currency_id": 5555555555,
    "min_bet": "0.01000000",
    "max_bet": "1000.00000000",
    "house_edge": "0.05",
    "callback_url": "https://game-server.com/callback"
}
```

## 7. Códigos de erro

| code | Significado |
|------|------|
| 0 | Sucesso |
| 400 | Erro de parâmetros ou saldo insuficiente |
| 401 | Assinatura inválida ou timestamp expirado |
| 404 | Jogo inexistente ou desabilitado |
| 422 | Falha na validação de parâmetros |
| 500 | Erro do servidor |

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
