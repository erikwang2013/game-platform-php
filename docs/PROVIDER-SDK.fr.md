# Documentation SDK d'intégration des providers de jeux
<!-- lang-nav -->

Languages: [中文](PROVIDER-SDK.md) · [English](PROVIDER-SDK.en.md) · [한국어](PROVIDER-SDK.ko.md) · [Русский](PROVIDER-SDK.ru.md) · [Deutsch](PROVIDER-SDK.de.md) · **Français** · [Español](PROVIDER-SDK.es.md) · [Português](PROVIDER-SDK.pt.md) · [हिन्दी](PROVIDER-SDK.hi.md) · [العربية](PROVIDER-SDK.ar.md) · [বাংলা](PROVIDER-SDK.bn.md) · [Bahasa Indonesia](PROVIDER-SDK.id.md) · [日本語](PROVIDER-SDK.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Les jeux tiers s'intègrent à la plateforme via l'API Provider, couvrant le cycle de vie complet : consultation du solde, notification de mise, notification de règlement, notification de remboursement.

## 1. Vue d'ensemble

```
Serveur de jeu                          API de la plateforme
    │                                │
    ├─ POST /api/provider/balance ──→│ Interroger le solde utilisateur
    │←── { balance: "100.5000" } ────┤
    │                                │
    ├─ POST /api/provider/bet ──────→│ Notifier la mise (débit du solde)
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/settle ───→│ Notifier le règlement (crédit du solde)
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/refund ───→│ Notifier le remboursement
    │←── { success: true } ──────────┤
```

## 2. Algorithme de signature

Tous les appels à l'API Provider doivent porter une signature HMAC-SHA256.

### En-têtes de requête

```
X-Game-Id: <ID du jeu>
X-Timestamp: <horodatage Unix en secondes>
X-Signature: <signature HMAC-SHA256>
Content-Type: application/json
```

### Calcul de la signature

```
sign_str = game_id + ":" + timestamp + ":" + method + ":" + path + ":" + body
signature = HMAC-SHA256(sign_str, api_secret)
```

- `game_id`: ID du jeu attribué par la plateforme
- `timestamp`: horodatage Unix courant (secondes), fenêtre de validité 5 minutes
- `method`: méthode de requête (POST)
- `path`: chemin de requête (/api/provider/bet)
- `body`: corps de requête (chaîne JSON)
- `api_secret`: clé API du jeu attribuée par la plateforme

### Exemple PHP

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

### Exemple Go

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

### Exemple Python

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

## 3. Points d'API

### 3.1 Interroger le solde

```
POST /api/provider/balance
Content-Type: application/json
X-Game-Id: <ID du jeu>
X-Timestamp: <horodatage>
X-Signature: <signature>

Corps de requête:
{
    "user_id": 9876543210,
    "game_id": 1234567890,
    "currency_id": 5555555555
}

Réponse:
{
    "code": 0,
    "message": "success",
    "data": {
        "balance": "1000.50000000"
    }
}
```

### 3.2 Notifier la mise

```
POST /api/provider/bet

Corps de requête:
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

Réponse:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "990.50000000"
    }
}

Réponse d'erreur:
{
    "code": 400,
    "message": "Insufficient balance",
    "data": {
        "success": false,
        "balance_after": "0.50000000"
    }
}
```

### 3.3 Notifier le règlement

```
POST /api/provider/settle

Corps de requête:
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

Réponse:
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

### 3.4 Notifier le remboursement

```
POST /api/provider/refund

Corps de requête:
{
    "user_id": 9876543210,
    "session_id": "GAME_SESSION_202608041030001234",
    "amount": "10.00000000",
    "round_id": "ROUND_abc123",
    "reason": "game_crash"
}

Réponse:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "1000.50000000"
    }
}
```

## 4. Intégration des jeux propriétaires (SelfProvider)

Les jeux propriétaires partagent la base de données de la plateforme ; `SelfProvider` utilise les transactions de base de données + `SELECT FOR UPDATE` pour garantir la cohérence :

```php
use app\provider\ProviderFactory;

$provider = ProviderFactory::createById($gameId);

// Interroger le solde
$balance = $provider->getBalance($userId, $gameId, $currencyId);

// Mise (débit dans la transaction DB de la plateforme)
$result = $provider->bet($userId, $gameId, $sessionId, $amount, $roundId, $meta);
if (!$result['success']) { /* Solde insuffisant */ }

// Règlement (crédit dans la transaction DB de la plateforme)
$result = $provider->settle($userId, $gameId, $sessionId, $amount, $roundId, $meta);

// Remboursement
$result = $provider->refund($userId, $gameId, $sessionId, $amount, $roundId, 'game_crash');
```

## 5. Gestion des sessions

Après le lancement du jeu, un heartbeat doit être envoyé toutes les 15 minutes :

```php
use app\service\GameSessionService;

// Au lancement
GameSessionService::heartbeat($userId, $gameId, $sessionId);

// Heartbeat périodique (recommandé toutes les 5 minutes)
if (!GameSessionService::isActive($sessionId)) {
    // La session a expiré, il faut terminer le jeu
}

// À la fin
GameSessionService::endSession($sessionId);
```

Les sessions expirées sont réglées automatiquement (`GameSessionService::expireStaleSessions()`).

## 6. Configuration du jeu

Avant l'intégration, créer le jeu dans le backend de la plateforme et obtenir :
- `game_id`: ID du jeu attribué par la plateforme
- `api_key`: SelfProvider uniquement
- `api_secret`: clé de signature HMAC-SHA256
- `provider_config`: configuration étendue JSON (optionnelle)

Configurer `provider_config` dans la page de gestion des jeux du backend :
```json
{
    "currency_id": 5555555555,
    "min_bet": "0.01000000",
    "max_bet": "1000.00000000",
    "house_edge": "0.05",
    "callback_url": "https://game-server.com/callback"
}
```

## 7. Codes d'erreur

| code | Signification |
|------|------|
| 0 | Succès |
| 400 | Erreur de paramètres ou solde insuffisant |
| 401 | Signature invalide ou horodatage expiré |
| 404 | Jeu inexistant ou désactivé |
| 422 | Échec de la validation des paramètres |
| 500 | Erreur serveur |

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
