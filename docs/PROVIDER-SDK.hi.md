# गेम प्रदाता एकीकरण SDK दस्तावेज़
<!-- lang-nav -->

Languages: [中文](PROVIDER-SDK.md) · [English](PROVIDER-SDK.en.md) · [한국어](PROVIDER-SDK.ko.md) · [Русский](PROVIDER-SDK.ru.md) · [Deutsch](PROVIDER-SDK.de.md) · [Français](PROVIDER-SDK.fr.md) · [Español](PROVIDER-SDK.es.md) · [Português](PROVIDER-SDK.pt.md) · **हिन्दी** · [العربية](PROVIDER-SDK.ar.md) · [বাংলা](PROVIDER-SDK.bn.md) · [Bahasa Indonesia](PROVIDER-SDK.id.md) · [日本語](PROVIDER-SDK.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

थर्ड-पार्टी गेम Provider API के माध्यम से प्लेटफ़ॉर्म के साथ एकीकृत होते हैं, जिससे शेष राशि क्वेरी, दांव सूचना, निपटान सूचना, रिफंड सूचना का पूर्ण जीवनचक्र साकार होता है।

## 1. अवलोकन

```
गेम सर्वर                          प्लेटफ़ॉर्म API
    │                                │
    ├─ POST /api/provider/balance ──→│ उपयोगकर्ता शेष राशि क्वेरी करें
    │←── { balance: "100.5000" } ────┤
    │                                │
    ├─ POST /api/provider/bet ──────→│ दांव सूचना (शेष राशि घटाएँ)
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/settle ───→│ निपटान सूचना (शेष राशि बढ़ाएँ)
    │←── { success: true } ──────────┤
    │                                │
    ├─ POST /api/provider/refund ───→│ रिफंड सूचना
    │←── { success: true } ──────────┤
```

## 2. हस्ताक्षर एल्गोरिथ्म

सभी Provider API कॉल में HMAC-SHA256 हस्ताक्षर होना अनिवार्य है।

### अनुरोध हेडर

```
X-Game-Id: <गेम ID>
X-Timestamp: <Unix सेकंड टाइमस्टैम्प>
X-Signature: <HMAC-SHA256 हस्ताक्षर>
Content-Type: application/json
```

### हस्ताक्षर गणना

```
sign_str = game_id + ":" + timestamp + ":" + method + ":" + path + ":" + body
signature = HMAC-SHA256(sign_str, api_secret)
```

- `game_id`: प्लेटफ़ॉर्म द्वारा आवंटित गेम ID
- `timestamp`: वर्तमान Unix टाइमस्टैम्प (सेकंड), मान्य विंडो 5 मिनट
- `method`: अनुरोध विधि (POST)
- `path`: अनुरोध पथ (/api/provider/bet)
- `body`: अनुरोध निकाय (JSON स्ट्रिंग)
- `api_secret`: प्लेटफ़ॉर्म द्वारा आवंटित गेम API कुंजी

### PHP उदाहरण

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

### Go उदाहरण

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

### Python उदाहरण

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

## 3. API एंडपॉइंट

### 3.1 शेष राशि क्वेरी

```
POST /api/provider/balance
Content-Type: application/json
X-Game-Id: <गेम ID>
X-Timestamp: <टाइमस्टैम्प>
X-Signature: <हस्ताक्षर>

अनुरोध निकाय:
{
    "user_id": 9876543210,
    "game_id": 1234567890,
    "currency_id": 5555555555
}

प्रतिक्रिया:
{
    "code": 0,
    "message": "success",
    "data": {
        "balance": "1000.50000000"
    }
}
```

### 3.2 दांव सूचना

```
POST /api/provider/bet

अनुरोध निकाय:
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

प्रतिक्रिया:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "990.50000000"
    }
}

त्रुटि प्रतिक्रिया:
{
    "code": 400,
    "message": "Insufficient balance",
    "data": {
        "success": false,
        "balance_after": "0.50000000"
    }
}
```

### 3.3 निपटान सूचना

```
POST /api/provider/settle

अनुरोध निकाय:
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

प्रतिक्रिया:
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

### 3.4 रिफंड सूचना

```
POST /api/provider/refund

अनुरोध निकाय:
{
    "user_id": 9876543210,
    "session_id": "GAME_SESSION_202608041030001234",
    "amount": "10.00000000",
    "round_id": "ROUND_abc123",
    "reason": "game_crash"
}

प्रतिक्रिया:
{
    "code": 0,
    "data": {
        "success": true,
        "transaction_id": "ROUND_abc123",
        "balance_after": "1000.50000000"
    }
}
```

## 4. स्वयं-विकसित गेम एकीकरण (SelfProvider)

स्वयं-विकसित गेम प्लेटफ़ॉर्म के साथ डेटाबेस साझा करते हैं, `SelfProvider` स्थिरता सुनिश्चित करने के लिए डेटाबेस ट्रांज़ैक्शन + `SELECT FOR UPDATE` का उपयोग करता है:

```php
use app\provider\ProviderFactory;

$provider = ProviderFactory::createById($gameId);

// शेष राशि क्वेरी
$balance = $provider->getBalance($userId, $gameId, $currencyId);

// दांव (प्लेटफ़ॉर्म DB ट्रांज़ैक्शन के भीतर राशि कटौती)
$result = $provider->bet($userId, $gameId, $sessionId, $amount, $roundId, $meta);
if (!$result['success']) { /* शेष राशि अपर्याप्त */ }

// निपटान (प्लेटफ़ॉर्म DB ट्रांज़ैक्शन के भीतर राशि जमा)
$result = $provider->settle($userId, $gameId, $sessionId, $amount, $roundId, $meta);

// रिफंड
$result = $provider->refund($userId, $gameId, $sessionId, $amount, $roundId, 'game_crash');
```

## 5. सत्र प्रबंधन

गेम शुरू होने के बाद हर 15 मिनट के भीतर हार्टबीट भेजना अनिवार्य है:

```php
use app\service\GameSessionService;

// शुरुआत में
GameSessionService::heartbeat($userId, $gameId, $sessionId);

// नियमित हार्टबीट (हर 5 मिनट का सुझाव)
if (!GameSessionService::isActive($sessionId)) {
    // सत्र समय-समाप्त हो गया, गेम समाप्त करना आवश्यक
}

// समाप्ति पर
GameSessionService::endSession($sessionId);
```

समय-समाप्त सत्र स्वचालित रूप से निपटाए जाते हैं (`GameSessionService::expireStaleSessions()`)।

## 6. गेम कॉन्फ़िगरेशन

एकीकरण से पहले प्लेटफ़ॉर्म कंसोल में गेम बनाना और निम्न प्राप्त करना आवश्यक है:
- `game_id`: प्लेटफ़ॉर्म द्वारा आवंटित गेम ID
- `api_key`: केवल SelfProvider
- `api_secret`: HMAC-SHA256 हस्ताक्षर कुंजी
- `provider_config`: JSON विस्तार कॉन्फ़िगरेशन (वैकल्पिक)

कंसोल गेम प्रबंधन पृष्ठ पर `provider_config` कॉन्फ़िगर करें:
```json
{
    "currency_id": 5555555555,
    "min_bet": "0.01000000",
    "max_bet": "1000.00000000",
    "house_edge": "0.05",
    "callback_url": "https://game-server.com/callback"
}
```

## 7. त्रुटि कोड

| code | अर्थ |
|------|------|
| 0 | सफल |
| 400 | पैरामीटर त्रुटि या शेष राशि अपर्याप्त |
| 401 | हस्ताक्षर अमान्य या टाइमस्टैम्प समाप्त |
| 404 | गेम मौजूद नहीं या निष्क्रिय |
| 422 | पैरामीटर सत्यापन विफल |
| 500 | सर्वर त्रुटि |

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
