# इंटरफ़ेस दस्तावेज़
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · **हिन्दी** · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

ऑनलाइन इंटरैक्टिव दस्तावेज़ (ऑनलाइन डिबगिंग समर्थित):
- C-छोर व्यवसाय: http://localhost:8788/apidoc/
- प्रशासन कंसोल: http://localhost:8787/apidoc/
- पासवर्ड: admin123

## 1. सम्मेलन

### 1.1 आधार URL

| छोर | पता |
|----|------|
| प्रशासन कंसोल | `http://localhost:8787` |
| C-छोर व्यवसाय | `http://localhost:8788` |

### 1.2 सामान्य अनुरोध हेडर

```
Content-Type: application/json
Authorization: Bearer <token>    (प्रमाणीकरण आवश्यक इंटरफ़ेस)
```

### 1.3 एकीकृत प्रतिक्रिया प्रारूप

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | अर्थ |
|------|------|
| 0 | सफल |
| 400 | पैरामीटर त्रुटि |
| 401 | प्रमाणीकरण नहीं (Token अनुपस्थित/समाप्त/अमान्य) |
| 403 | कोई अनुमति नहीं |
| 404 | संसाधन मौजूद नहीं |
| 422 | सत्यापन विफल |
| 429 | अनुरोध बहुत बार-बार (दर सीमा ट्रिगर) |
| 500 | सर्वर त्रुटि |

### 1.4 ID एन्कोडिंग

सभी इंटरफ़ेस अनुरोधों और प्रतिक्रियाओं में ID Hashids-एन्कोडेड स्ट्रिंग हैं, मूल BIGINT मान नहीं।

```
बाहरी: aB3xK9mW2pQ7rT5v  (hashid स्ट्रिंग)
आंतरिक: 1750123456789      (Snowflake BIGINT)
```

### 1.5 पृष्ठांकन प्रारूप

```
अनुरोध: ?page=1&per_page=20

प्रतिक्रिया: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. C-छोर इंटरफ़ेस (service :8788)

### 2.1 प्रमाणीकरण

#### POST /api/v1/auth/register — उपयोगकर्ता पंजीकरण

```
अनुरोध: {
  "username": "player1",
  "password": "123456",
  "email": "player@example.com"     // वैकल्पिक
}

प्रतिक्रिया: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": {
    "id": "aB3xK9mW2pQ7rT5v",
    "username": "player1",
    "nickname": "",
    "avatar": ""
  }
}
```

#### POST /api/v1/auth/login — उपयोगकर्ता लॉगिन

```
अनुरोध: {
  "username": "player1",
  "password": "123456"
}

प्रतिक्रिया: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "...", ... }
}
```

त्रुटि: 401 उपयोगकर्ता नाम या पासवर्ड गलत / खाता अक्षम

#### POST /api/v1/auth/refresh — Token रिफ्रेश

```
अनुरोध: (Authorization: Bearer <refresh_token>)

प्रतिक्रिया: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 वॉलेट

#### GET /api/v1/wallet/info — वॉलेट जानकारी

```
प्रमाणीकरण आवश्यक: हाँ

प्रतिक्रिया: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/v1/wallet/transactions — लेनदेन रिकॉर्ड

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&per_page=20&type=deposit    (type वैकल्पिक)

प्रतिक्रिया: {
  "list": [
    {
      "id": "...",
      "type": "deposit",
      "amount": "100.0000",
      "balance_after": "100.5000",
      "remark": "充值到账",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 25,
  "page": 1,
  "per_page": 20
}

type वैकल्पिक मान: deposit / withdraw / exchange_in / exchange_out / game_earn / game_spend
```

### 2.3 रिचार्ज

#### POST /api/v1/deposit/create — रिचार्ज ऑर्डर बनाएं

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "amount": "10.00",
  "currency": "USD",
  "payment_method_id": "aB3xK..."
}

प्रतिक्रिया: {
  "order_id": "aB3xK...",
  "order_no": "DEP202605221030000123",
  "amount": "10.00",
  "platform_amount": "10.0000",
  "checkout_url": "https://checkout.stripe.com/...",
  "expires_at": "2026-05-22 11:30:00"
}
```

currency वैकल्पिक मान: USD / CNY / EUR

checkout_url: भुगतान गेटवे रीडायरेक्ट लिंक (ऑर्डर बनाते समय भरा जाता है); expires_at: भुगतान लिंक की समाप्ति (बनाने के 1 घंटे बाद)

#### GET /api/v1/deposit/orders — रिचार्ज रिकॉर्ड

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&per_page=20

प्रतिक्रिया: {
  "list": [
    {
      "id": "...",
      "order_no": "DEP...",
      "amount": "10.00",
      "currency": "USD",
      "platform_amount": "10.0000",
      "status": "pending",
      "paid_at": null,
      "created_at": "2026-05-22 10:25:00"
    }
  ],
  "total": 5,
  "page": 1,
  "per_page": 20
}
```

status वैकल्पिक मान: pending / paid / confirmed / cancelled

### 2.4 विनिमय

#### POST /api/v1/exchange/quote — मूल्य पूछताछ

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "direction": "in",
  "platform_amount": "10.0000"
}

प्रतिक्रिया: {
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000",
  "spread_pct": "5.00%"
}
```

direction: in=गेम कॉइन खरीदना / out=गेम कॉइन बेचना

#### POST /api/v1/exchange/buy — गेम कॉइन खरीदें

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "10.0000"
}

प्रतिक्रिया: {
  "exchange_id": "aB3xK...",
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000"
}
```

त्रुटि: 422 प्लेटफ़ॉर्म कॉइन शेष अपर्याप्त / 404 गेम अनुपलब्ध

#### POST /api/v1/exchange/sell — गेम कॉइन बेचें

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "950.0000"
}

प्रतिक्रिया: {
  "exchange_id": "aB3xK...",
  "platform_amount": "9.0250",
  "game_amount": "950.0000",
  "spread_fee": "0.4750",
  "rate": "100.00000000"
}
```

त्रुटि: 422 गेम कॉइन शेष अपर्याप्त

#### GET /api/v1/exchange/records — विनिमय रिकॉर्ड

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&per_page=20

प्रतिक्रिया: {
  "list": [
    {
      "id": "...",
      "game_id": "...",
      "direction": "in",
      "platform_amount": "10.0000",
      "game_amount": "950.0000",
      "rate": "100.00000000",
      "spread_fee": "50.0000",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 15,
  "page": 1,
  "per_page": 20
}
```

### 2.5 निकासी

#### POST /api/v1/withdraw/apply — निकासी आवेदन

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "platform_amount": "50.0000",
  "method": "paypal",
  "account_info": "user@paypal.com"
}

प्रतिक्रिया: {
  "order_id": "...",
  "order_no": "WTH202605221030000456",
  "status": "approved"
}
```

method वैकल्पिक मान: paypal / bank / crypto

status:
- approved: स्वचालित स्वीकृति (राशि < auto_approve_threshold)
- pending: समीक्षा लंबित (राशि >= auto_approve_threshold)

त्रुटि:
- 403 निकासी फ़ंक्शन अस्थायी रूप से बंद (वैश्विक स्विच बंद)
- 400 न्यूनतम निकासी राशि से कम
- 400 दैनिक निकासी सीमा से अधिक
- 400 शेष अपर्याप्त

#### GET /api/v1/withdraw/orders — निकासी रिकॉर्ड

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&per_page=20

प्रतिक्रिया: {
  "list": [
    {
      "id": "...",
      "order_no": "WTH...",
      "platform_amount": "50.0000",
      "method": "paypal",
      "status": "pending",
      "review_note": "",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 3,
  "page": 1,
  "per_page": 20
}
```

### 2.6 गेम

#### GET /api/v1/game/list — गेम सूची

```
पैरामीटर: ?page=1&per_page=20&keyword=射击&type=self

प्रतिक्रिया: {
  "list": [
    {
      "id": "aB3xK...",
      "name": "射击大师",
      "slug": "shooter-master",
      "type": "self",
      "description": "一款精彩的射击游戏",
      "cover_image": "https://...",
      "currencies": [
        {
          "id": "...",
          "name": "金币",
          "symbol": "G",
          "exchange_rate": "100.00000000",
          "min_exchange": "1.0000",
          "max_exchange": "10000.0000"
        }
      ]
    }
  ],
  "total": 20,
  "page": 1,
  "per_page": 20
}
```

type वैकल्पिक मान: self / third_party

#### GET /api/v1/game/{hashid} — गेम विवरण

```
प्रतिक्रिया: {
  "id": "...",
  "name": "射击大师",
  "slug": "shooter-master",
  "type": "self",
  "description": "...",
  "cover_image": "https://...",
  "currencies": [
    {
      "id": "...",
      "name": "金币",
      "symbol": "G",
      "exchange_rate": "100.00000000",
      "spread_pct": "5.00",
      "min_exchange": "1.0000",
      "max_exchange": "10000.0000"
    }
  ]
}
```

#### POST /api/v1/game/launch — गेम लॉन्च करें

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: { "game_id": "aB3xK..." }

प्रतिक्रिया: {
  "id": "...",
  "name": "射击大师",
  "type": "self",
  "api_endpoint": "https://game.example.com/play"
}
```

### 2.7 OAuth तृतीय-पक्ष लॉगिन

7 प्लेटफ़ॉर्म समर्थित: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/v1/auth/oauth/{provider} — प्राधिकरण URL प्राप्त करें

```
पैरामीटर: provider = google / facebook / apple / twitter / microsoft / linkedin / github

प्रतिक्रिया: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/v1/auth/oauth/{provider}/callback — OAuth कॉलबैक

```
अनुरोध: { "code": "授权码", "state": "防CSRF状态" }

प्रतिक्रिया: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=नया पंजीकृत उपयोगकर्ता / false=मौजूदा खाते से बाइंड किया गया

### 2.8 KYC वास्तविक नाम प्रमाणीकरण

#### GET /api/v1/user/identity/status — प्रमाणीकरण स्थिति

```
प्रमाणीकरण आवश्यक: हाँ

प्रतिक्रिया: {
  "status": "approved",          // not_submitted / pending / approved / rejected
  "real_name": "J***",
  "id_type": "id_card",
  "review_note": "",
  "submitted_at": "2026-05-22 10:00:00",
  "reviewed_at": "2026-05-23 14:00:00"
}
```

#### POST /api/v1/user/identity/apply — प्रमाणीकरण जमा करें

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}

प्रतिक्रिया: { "message": "KYC submitted successfully" }
```

### 2.9 भुगतान

#### POST /api/v1/payment/callback — भुगतान कॉलबैक (सार्वजनिक)

```
अनुरोध: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

प्रतिक्रिया: { "message": "success" }
```

status: success / failed

provider के मान: stripe / paypal / nowpayments / coinbase / skrill / neteller / paysafecard / paytm / mercadopago / astropay / paypay / kakaopay / gcash (toss / mpesa / paystack जल्द आ रहे हैं)

| provider | क्षेत्र | हस्ताक्षर योजना | समर्थित मुद्राएँ |
|----------|---------|----------------|------------------|
| stripe | वैश्विक (125+ स्थानीय भुगतान विधियाँ, Alipay/WeChat Pay APM सहित) | Webhook HMAC-SHA256 | USD / CNY / EUR |
| paypal | दुनिया भर के 200+ बाज़ार | Webhook सत्यापन (verify-webhook-signature) | USD / CNY / EUR और अन्य फिएट |
| nowpayments | वैश्विक (क्रिप्टो) | IPN HMAC-SHA512 | USDT TRC20 / ERC20 |
| coinbase | वैश्विक (क्रिप्टो) | Webhook HMAC-SHA256 (base64 secret) | USDC / BTC / ETH |
| skrill | यूरोप / वैश्विक | Secret word MD5 जांच | EUR और अन्य फिएट |
| neteller | यूरोप / वैश्विक | Secret key फ़ील्ड तुलना | EUR और अन्य फिएट |
| paysafecard | यूरोप (DE / AT / CH आदि) | X-Signature HMAC-SHA256 | EUR और अन्य फिएट |
| paytm | भारत | SHA256 + AES-128-CBC | INR |
| mercadopago | लैटिन अमेरिका (BR / MX आदि) | X-Signature (ts,v1) HMAC-SHA256 | BRL / MXN और अन्य फिएट |
| astropay | लैटिन अमेरिका (BR आदि) | MD5(order_id.amount.status.secret) | BRL और अन्य फिएट |
| paypay | जापान | PayPay-Signature HMAC-SHA256 | JPY |
| kakaopay | दक्षिण कोरिया | कोई webhook नहीं (ready/approve दो-चरणीय) | KRW |
| gcash | फिलीपींस | Paymongo-Signature HMAC-SHA256 | PHP |
| toss | दक्षिण कोरिया (जल्द आ रहा है) | — | KRW |
| mpesa | केन्या / तंजानिया आदि (जल्द आ रहा है) | — | KES / TZS |
| paystack | नाइजीरिया (जल्द आ रहा है) | — | NGN |

#### GET /api/v1/payment/methods — उपलब्ध भुगतान विधियाँ (सार्वजनिक)

```
प्रतिक्रिया: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

उपयोगकर्ता के देश के अनुसार फ़िल्टर (X-Language/Accept-Language → देश कोड): countries खाली या * वाला वैश्विक रूप से दृश्य; उस देश की country_config भुगतान विधि प्राथमिकता के अनुसार क्रमबद्ध

### 2.10 गेम रिकॉर्ड

#### GET /api/v1/game/play-logs — गेम रिकॉर्ड सूची

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&per_page=20&game_id=xxx&action=start

प्रतिक्रिया: {
  "list": [
    {
      "id": "...",
      "game_id": "...",
      "action": "start",
      "game_amount_change": "-10.0000",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 50, "page": 1, "per_page": 20
}
```

#### GET /api/v1/game/play-log/{hashid} — गेम रिकॉर्ड विवरण

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { पूर्ण रिकॉर्ड, session_id / game_amount_before / after आदि सहित }
```

### 2.12 लीडरबोर्ड

#### GET /api/v1/leaderboard/list — लीडरबोर्ड सूची

```
प्रतिक्रिया: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/v1/leaderboard/{hashid} — लीडरबोर्ड विवरण

```
प्रतिक्रिया: {
  "id": "...",
  "name": "全服累计收入榜",
  "type": "total",
  "rankings": [
    { "rank": 1, "user_id": "...", "score": "50000.0000" }
  ]
}
```

### 2.13 कूपन

#### GET /api/v1/coupon/available — उपलब्ध कूपन

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/v1/coupon/claim — कूपन प्राप्त करें

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "coupon_id": "hashid" }
प्रतिक्रिया: { "coupon": { ... } }
```

#### GET /api/v1/coupon/my — मेरे कूपन

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?status=unused
प्रतिक्रिया: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 देश कॉन्फ़िगरेशन

#### GET /api/v1/country/list — देश सूची

```
प्रतिक्रिया: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/v1/country/{code} — देश विवरण

```
प्रतिक्रिया: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 अधिसूचनाएँ

#### GET /api/v1/notification/list — अधिसूचना सूची

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&per_page=20&is_read=0

प्रतिक्रिया: {
  "list": [
    { "id": "...", "type": "deposit", "title": "Deposit Received", "is_read": 0, "created_at": "..." }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### GET /api/v1/notification/unread-count — अपठित संख्या

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "count": 3 }
```

#### POST /api/v1/notification/read — पढ़ा हुआ चिह्नित करें

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "id": "hashid" }  // नहीं दिया तो = सभी पढ़े गए
```

### 2.17 रेफरल

#### GET /api/v1/referral/my-code — मेरा रेफरल कोड

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/v1/referral/apply — रेफरल कोड का उपयोग करें

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "code": "ABC12345" }
प्रतिक्रिया: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/v1/user/2fa/status — 2FA स्थिति

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "enabled": false }
```

#### POST /api/v1/user/2fa/setup — 2FA सेट करें

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/v1/user/2fa/enable — 2FA सक्षम करें

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "code": "123456" }
प्रतिक्रिया: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/v1/2fa/verify — 2FA सत्यापित करें (सार्वजनिक)

```
अनुरोध: { "user_id": "hashid", "code": "123456" }
प्रतिक्रिया: { "valid": true }
```

### 2.19 खोज

#### GET /api/v1/search — वैश्विक खोज

```
पैरामीटर: ?q=keyword&type=game&page=1&per_page=20
प्रतिक्रिया: { "list": [...], "total": 100 }
```

#### GET /api/v1/game/suggest — खोज सुझाव

```
पैरामीटर: ?q=shoot
प्रतिक्रिया: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 भाषा

#### GET /api/v1/language/list — उपलब्ध भाषा सूची

```
प्रतिक्रिया: {
  "current": "en-US",
  "languages": {
    "en-US": { "name": "English", "nativeName": "English", "icon": "us" },
    "zh-CN": { "name": "Chinese (Simplified)", "nativeName": "简体中文", "icon": "cn" },
    "ja-JP": { "name": "Japanese", "nativeName": "日本語", "icon": "jp" },
    "ko-KR": { "name": "Korean", "nativeName": "한국어", "icon": "kr" }
  }
}
```

#### POST /api/v1/language/switch — भाषा बदलें

```
अनुरोध: { "locale": "zh-CN" }
प्रतिक्रिया: { "locale": "zh-CN" }
```

locale वैकल्पिक मान: en-US / zh-CN / ja-JP / ko-KR

### 2.8 उपयोगकर्ता

#### GET /api/v1/user/profile — व्यक्तिगत जानकारी

```
प्रमाणीकरण आवश्यक: हाँ

प्रतिक्रिया: {
  "id": "...",
  "username": "player1",
  "nickname": "Player One",
  "avatar": "https://...",
  "email": "p***@example.com",
  "phone": "",
  "country": "US",
  "language": "en-US",
  "last_login_at": "2026-05-22 10:00:00",
  "created_at": "2026-05-20 08:00:00"
}
```

#### PUT /api/v1/user/profile — प्रोफ़ाइल संपादित करें

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}

प्रतिक्रिया: {
  "id": "...",
  "username": "player1",
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}
```

language वैकल्पिक मान: en-US / zh-CN / ja-JP / ko-KR

### 2.9 घोषणाएँ

#### GET /api/v1/announcement/list — घोषणा सूची

```
प्रतिक्रिया: {
  "list": [
    {
      "id": "...",
      "title": "系统维护通知",
      "type": "system",
      "created_at": "2026-05-22 09:00:00"
    }
  ]
}
```

#### GET /api/v1/announcement/detail/{hashid} — घोषणा विवरण

```
प्रतिक्रिया: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

### 2.21 प्लेटफ़ॉर्म सांख्यिकी

| विधि | पथ | विवरण | प्रमाणीकरण |
|------|------|------|------|
| GET | /api/v1/platform/stats | प्लेटफ़ॉर्म सार्वजनिक सांख्यिकी (कुल गेम / कुल उपयोगकर्ता / आज के प्ले / 7 दिन सक्रिय) | नहीं |

#### GET /api/v1/platform/stats — प्लेटफ़ॉर्म सांख्यिकी

```
无需认证

响应: {
  "total_games": 12,
  "total_users": 1500,
  "today_game_plays": 320,
  "active_users_7d": 450
}
```

## 3. प्रशासन कंसोल इंटरफ़ेस (admin :8787)

### 3.1 प्लेटफ़ॉर्म डैशबोर्ड

#### GET /admin/dashboard/platform

```
प्रमाणीकरण आवश्यक: हाँ (AdminAuth + AdminPermission)

प्रतिक्रिया: {
  "total_users": 1500,
  "active_users_7d": 320,
  "total_games": 12,
  "pending_withdraws": 5,
  "today_deposits": "500.0000",
  "today_withdraws": "120.0000",
  "total_spread_fee": "1500.5000"
}
```

### 3.2 गेम प्रबंधन

#### GET /admin/game/list — गेम सूची

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&per_page=20&keyword=射击

प्रतिक्रिया: {
  "list": [
    {
      "id": "...",
      "name": "射击大师",
      "slug": "shooter-master",
      "type": "self",
      "status": 1,
      "sort": 0,
      "currency_count": 2,
      "created_at": "2026-05-20 08:00:00"
    }
  ],
  "total": 12,
  "page": 1,
  "per_page": 20
}
```

#### POST /admin/game/create — गेम बनाएं

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "name": "新游戏",
  "slug": "new-game",
  "type": "self",
  "description": "游戏描述",        // वैकल्पिक
  "cover_image": "https://...",    // वैकल्पिक
  "api_endpoint": "https://...",   // वैकल्पिक
  "api_key": "...",                // वैकल्पिक
  "api_secret": "...",             // वैकल्पिक
  "status": 1,                     // वैकल्पिक, डिफ़ॉल्ट 0
  "sort": 0                        // वैकल्पिक, डिफ़ॉल्ट 0
}

प्रतिक्रिया: { "id": "aB3xK..." }
```

type वैकल्पिक मान: self / third_party

#### PUT /admin/game/{hashid} — गेम संपादित करें

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "name": "新名称",
  "status": 1
  // आंशिक अपडेट संभव, फ़ील्ड create के समान
}

प्रतिक्रिया: { "message": "更新成功" }
```

#### DELETE /admin/game/{hashid} — गेम हटाएं

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "message": "删除成功" }
```

#### POST /admin/game/currency/manage — मुद्रा प्रबंधित करें

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "game_id": "aB3xK...",
  "currencies": [
    {
      "id": "",                       // खाली=नया, मान है=अपडेट
      "name": "金币",
      "symbol": "G",
      "exchange_rate": "100.00000000",
      "spread_pct": "5.00",
      "min_exchange": "1.0000",
      "max_exchange": "10000.0000"
    }
  ]
}

प्रतिक्रिया: { "message": "币种更新成功" }
```

### 3.3 निकासी प्रबंधन

#### GET /admin/withdraw/orders — निकासी ऑर्डर सूची

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&per_page=20&status=pending

प्रतिक्रिया: {
  "list": [
    {
      "id": "...",
      "order_no": "WTH...",
      "user": {
        "id": "...",
        "username": "player1"
      },
      "platform_amount": "500.0000",
      "method": "paypal",
      "status": "pending",
      "reviewer_id": null,
      "review_note": "",
      "reviewed_at": null,
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 5,
  "page": 1,
  "per_page": 20
}
```

#### PUT /admin/withdraw/review — निकासी समीक्षा

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

प्रतिक्रिया: { "message": "已通过" }
```

action: approve=स्वीकृति / reject=अस्वीकृति (अस्वीकृति पर स्वचालित रूप से प्लेटफ़ॉर्म कॉइन वापस)

त्रुटि: 422 ऑर्डर स्थिति समीक्षा लंबित नहीं है

#### PUT /admin/withdraw/switch — वैश्विक निकासी स्विच

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: { "enabled": 1 }

प्रतिक्रिया: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/withdraw/limits/set — निकासी सीमा सेट करें

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "daily_limit": "10000.0000",             // वैकल्पिक
  "min_amount": "1.0000",                  // वैकल्पिक
  "auto_approve_threshold": "100.0000"     // वैकल्पिक
}

प्रतिक्रिया: {
  "daily_limit": "10000.0000",
  "min_amount": "1.0000",
  "auto_approve_threshold": "100.0000",
  "global_switch": true
}
```

### 3.4 प्लेटफ़ॉर्म उपयोगकर्ता प्रबंधन

#### GET /admin/platform/user/list — C-छोर उपयोगकर्ता सूची

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&per_page=20&keyword=player&status=1

प्रतिक्रिया: {
  "list": [
    {
      "id": "...",
      "username": "player1",
      "nickname": "Player One",
      "country": "US",
      "status": 1,
      "last_login_at": "2026-05-22 10:00:00",
      "created_at": "2026-05-20 08:00:00"
    }
  ],
  "total": 1500,
  "page": 1,
  "per_page": 20
}
```

#### GET /admin/platform/user/{hashid} — उपयोगकर्ता विवरण

```
प्रमाणीकरण आवश्यक: हाँ

प्रतिक्रिया: {
  "id": "...",
  "username": "player1",
  "nickname": "Player One",
  "email": "p***@example.com",
  "phone": "",
  "country": "US",
  "language": "en-US",
  "status": 1,
  "wallet": {
    "balance": "100.5000",
    "frozen_balance": "0.0000"
  },
  "last_login_at": "2026-05-22 10:00:00",
  "created_at": "2026-05-20 08:00:00"
}
```

#### PUT /admin/platform/user/{hashid} — उपयोगकर्ता संपादित/प्रतिबंधित करें

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "status": 0,         // 0=अक्षम 1=सक्षम
  "nickname": "..."    // वैकल्पिक
}

प्रतिक्रिया: { "message": "更新成功" }
```

### 3.5 भुगतान प्रबंधन

#### GET /admin/payment/method/list

```
प्रमाणीकरण आवश्यक: हाँ

प्रतिक्रिया: {
  "list": [
    {
      "id": "...",
      "name": "Stripe",
      "type": "fiat",
      "provider": "stripe",
      "status": 1
    }
  ]
}
```

#### POST /admin/payment/method/toggle — भुगतान विधि सक्षम/अक्षम

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: { "id": "aB3xK...", "status": 0 }

प्रतिक्रिया: { "message": "已更新" }
```

### 3.6 घोषणा प्रबंधन

#### GET /admin/announcement/list

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&per_page=20

प्रतिक्रिया: {
  "list": [
    {
      "id": "...",
      "title": "系统维护通知",
      "type": "system",
      "status": 1,
      "start_at": "2026-05-23 02:00:00",
      "end_at": "2026-05-23 04:00:00",
      "created_at": "2026-05-22 09:00:00"
    }
  ],
  "total": 5,
  "page": 1,
  "per_page": 20
}
```

#### POST /admin/announcement/create — घोषणा प्रकाशित करें

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: {
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护。",
  "type": "system",           // वैकल्पिक, डिफ़ॉल्ट "system"
  "target_lang": "",          // वैकल्पिक, खाली=सभी भाषाएँ
  "status": 1,                // वैकल्पिक, डिफ़ॉल्ट 1 (0=ड्राफ़्ट 1=प्रकाशित)
  "start_at": "2026-05-23 02:00:00",  // वैकल्पिक
  "end_at": "2026-05-23 04:00:00"     // वैकल्पिक
}

प्रतिक्रिया: { "id": "aB3xK..." }
```

### 3.7 KYC समीक्षा

#### GET /admin/identity/list — KYC सूची

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&per_page=20&status=pending

प्रतिक्रिया: {
  "list": [
    {
      "id": "...",
      "user": { "id": "...", "username": "player1" },
      "real_name": "J***",
      "id_type": "id_card",
      "status": "pending",
      "created_at": "2026-05-22 10:00:00"
    }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### PUT /admin/identity/review — KYC समीक्षा

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: { "id": "hashid", "action": "approve", "note": "" }

प्रतिक्रिया: { "message": "Approved" }
```

action: approve / reject

### 3.8 गेम क्षेत्र/सर्वर प्रबंधन

#### GET /admin/game/server/list — क्षेत्र/सर्वर सूची

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?game_id=hashid

प्रतिक्रिया: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/game/server/create — क्षेत्र/सर्वर बनाएं

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
प्रतिक्रिया: { "id": "hashid" }
```

#### PUT /admin/game/server/{hashid} — क्षेत्र/सर्वर संपादित करें

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/game/server/{hashid} — क्षेत्र/सर्वर हटाएं

```
प्रमाणीकरण आवश्यक: हाँ
```

### 3.9 निकासी स्तरीय सीमा प्रबंधन

#### GET /admin/withdraw/limits/list

```
प्रमाणीकरण आवश्यक: हाँ

प्रतिक्रिया: {
  "list": [
    {
      "id": "...",
      "user_level": "verified",
      "single_min": "1.0000",
      "single_max": "5000.0000",
      "daily_limit": "50000.0000",
      "monthly_limit": "200000.0000",
      "fee_pct": "0.50",
      "fee_max": "25.0000",
      "auto_approve_threshold": "500.0000"
    }
  ]
}
```

#### PUT /admin/withdraw/limits/{hashid} — सीमा अपडेट करें

```
प्रमाणीकरण आवश्यक: हाँ

अनुरोध: { "single_max": "10000.0000", "fee_pct": "0.25" }
// आंशिक अपडेट संभव
```

### 3.11 गेम श्रेणी प्रबंधन

#### GET /admin/game/category/list

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "list": [{ "id": "...", "name": "动作", "slug": "action", "sort": 1 }] }
```

#### POST /admin/game/category/create

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "name": "新分类", "slug": "new-cat", "icon": "star", "sort": 10 }
प्रतिक्रिया: { "id": "hashid" }
```

#### PUT /admin/game/category/{hashid} — श्रेणी संपादित करें

#### DELETE /admin/game/category/{hashid} — श्रेणी हटाएं

#### POST /admin/game/category/assign — गेम आवंटित करें

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 लीडरबोर्ड प्रबंधन

#### GET /admin/leaderboard/list — लीडरबोर्ड सूची

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/leaderboard/create — लीडरबोर्ड बनाएं

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(वैकल्पिक)" }
```

#### PUT /admin/leaderboard/{hashid} — लीडरबोर्ड संपादित करें

#### DELETE /admin/leaderboard/{hashid} — लीडरबोर्ड हटाएं

#### POST /admin/leaderboard/{hashid}/refresh — कैश रिफ्रेश करें

### 3.13 कूपन प्रबंधन

#### GET /admin/coupon/list — कूपन सूची

#### POST /admin/coupon/create — कूपन बनाएं

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/coupon/{hashid} — संपादित करें (जब प्राप्त नहीं हुआ)

#### DELETE /admin/coupon/{hashid} — हटाएं

#### GET /admin/coupon/{hashid}/stats — प्राप्ति आँकड़े

```
प्रतिक्रिया: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 देश कॉन्फ़िगरेशन प्रबंधन

#### GET /admin/country/config/list — देश कॉन्फ़िग सूची

#### POST /admin/country/config/create — देश कॉन्फ़िग बनाएं

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/country/config/{hashid} — देश कॉन्फ़िग संपादित करें

### 3.15 डेटा निर्यात

#### POST /admin/export/users — C-छोर उपयोगकर्ता निर्यात

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर(JSON): { "status": 1 }   // वैकल्पिक फ़िल्टर

प्रतिक्रिया: Excel फ़ाइल डाउनलोड (xlsx)
```

#### POST /admin/export/transactions — प्लेटफ़ॉर्म लेनदेन निर्यात

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर(JSON): { "type": "deposit" }   // वैकल्पिक फ़िल्टर

प्रतिक्रिया: Excel फ़ाइल डाउनलोड (xlsx)
```

### 3.16 डेटा विश्लेषण (MySQL वास्तविक समय एकत्रीकरण)

सभी एंडपॉइंट्स को प्रमाणीकरण आवश्यक है (AdminAuth + AdminPermission), डेटा MySQL से वास्तविक समय में एकत्रित होता है, ClickHouse पर निर्भर नहीं।

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/analytics/overview | प्लेटफ़ॉर्म सारांश (आज/पिछले 7 दिन) |
| GET | /admin/analytics/game-ranking | गेम रैंकिंग (?days=7) |
| GET | /admin/analytics/dau-trend | DAU प्रवृत्ति (?days=30) |
| GET | /admin/analytics/hourly-trend | घंटे-वार प्रवृत्ति |
| GET | /admin/analytics/action-distribution | व्यवहार वितरण |
| GET | /admin/analytics/revenue | राजस्व विश्लेषण |
| GET | /admin/analytics/conversion | गेम रूपांतरण दर |
| GET | /admin/analytics/probability | संयुक्त/सशर्त संभावना |
| GET | /admin/analytics/retention | प्रतिधारण विश्लेषण D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | रूपांतरण फ़नल |
| GET | /admin/analytics/arpu | ARPU/ARPPU प्रवृत्ति |
| GET | /admin/analytics/economy | गेम मुद्रा आर्थिक मीट्रिक्स |

### 3.17 टिकट प्रबंधन

सभी एंडपॉइंट्स को प्रमाणीकरण आवश्यक है (AdminAuth + AdminPermission)।

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/ticket/list | टिकट सूची (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | टिकट विवरण (उत्तर सहित) |
| POST | /admin/ticket/{hashid}/reply | टिकट का उत्तर दें |
| POST | /admin/ticket/{hashid}/close | टिकट बंद करें |
| POST | /admin/ticket/{hashid}/assign | प्रबंधक नियुक्त करें (admin_id) |

### 3.18 CDN कॉन्फ़िगरेशन प्रबंधन

सभी एंडपॉइंट्स को प्रमाणीकरण आवश्यक है (AdminAuth + AdminPermission)।

| विधि | पथ | विवरण | प्रमाणीकरण |
|------|------|------|------|
| GET | /admin/cdn/provider/list | CDN प्रदाताओं की सूची (क्रेडेंशियल वापस नहीं भेजे जाते) | AdminAuth + RBAC: cdn |
| POST | /admin/cdn/provider/toggle | प्रदाता सक्षम/अक्षम करें {id, status} | AdminAuth + RBAC: cdn |
| POST | /admin/cdn/provider/create | बनाएँ {name, provider, config(JSON), status, sort}，provider अद्वितीयता जाँच | AdminAuth + RBAC: cdn |
| PUT | /admin/cdn/provider/{hashid} | संपादित करें (खाली config = अपरिवर्तित) | AdminAuth + RBAC: cdn |
| DELETE | /admin/cdn/provider/{hashid} | हटाएँ | AdminAuth + RBAC: cdn |
| POST | /admin/cdn/provider/test | कनेक्टिविटी परीक्षण HeadBucket {id} | AdminAuth + RBAC: cdn |

### 3.19 डेटा रिपोर्ट

सभी एंडपॉइंट्स को प्रमाणीकरण आवश्यक है (AdminAuth + AdminPermission)।

| विधि | पथ | विवरण | प्रमाणीकरण |
|------|------|------|------|
| GET | /admin/report/summary | रिपोर्ट सारांश (नए उपयोगकर्ता/जमा/निकासी/विनिमय/गेम प्ले) | AdminAuth + RBAC: report |
| GET | /admin/report/daily | दैनिक रिपोर्ट (दिन-वार एग्रीगेशन, खाली तारीखों पर 0 भरा जाता है) | AdminAuth + RBAC: report |
| GET | /admin/report/export | दैनिक रिपोर्ट CSV निर्यात (UTF-8 BOM) | AdminAuth + RBAC: report |

## 4. दर सीमा रणनीति

| इंटरफ़ेस | सीमा |
|------|------|
| डिफ़ॉल्ट | 60 बार/मिनट/IP |
| POST /api/v1/auth/login | 10 बार/मिनट |
| POST /api/v1/auth/register | 5 बार/मिनट |

सीमा पार होने पर 429 लौटाता है, प्रतिक्रिया हेडर शामिल है:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1716400830
Retry-After: 60
```

## 5. प्रमाणीकरण विवरण

### C-छोर (UserAuth)

1. `Authorization: Bearer <token>` से Token निकालें
2. JWT हस्ताक्षर सत्यापन (HS256), `sub` (उपयोगकर्ता ID) पार्स करें
3. उपयोगकर्ता के अस्तित्व और status=1 की पुष्टि के लिए `game_user` तालिका क्वेरी करें
4. `$request->userId` इंजेक्ट करें

### प्रशासन कंसोल (AdminAuth + AdminPermission)

1. AdminAuth: JWT हस्ताक्षर सत्यापन, `sub` (प्रशासक ID) पार्स, `$request->adminId` इंजेक्ट
2. AdminPermission: उपयोगकर्ता भूमिका से अनुमतियाँ ढूँढता है, `method.path` प्रारूप की अनुमति पहचान से मिलान करता है
3. `slug=*` वाले सुपर एडमिन अनुमति जाँच छोड़ देते हैं

## 6. त्रुटि कोड त्वरित संदर्भ

| code | अर्थ | सामान्य परिदृश्य |
|------|------|---------|
| 0 | सफल | - |
| 400 | पैरामीटर त्रुटि | अनुरोध प्रारूप गलत, राशि अपर्याप्त |
| 401 | प्रमाणीकरण नहीं | Token अनुपस्थित/समाप्त/अमान्य, खाता अक्षम |
| 403 | कोई अनुमति नहीं | उपयोगकर्ता के पास संबंधित भूमिका अनुमति नहीं, गेम अनुपलब्ध |
| 404 | मौजूद नहीं | संसाधन नहीं मिला |
| 422 | सत्यापन विफल | फ़ॉर्म पैरामीटर नियमों के अनुरूप नहीं, ऑर्डर स्थिति संचालन की अनुमति नहीं |
| 429 | दर सीमा | अनुरोध बहुत बार-बार |
| 500 | सर्वर त्रुटि | अप्रत्याशित अपवाद |


## 7. नए API (v2.0 पारिस्थितिकी विस्तार)

### 7.1 Provider API — गेम पक्ष कॉलबैक इंटरफ़ेस

**प्रमाणीकरण विधि**: HMAC-SHA256 हस्ताक्षर (X-Game-Id + X-Timestamp + X-Signature)
**समय विंडो**: 5 मिनट

#### POST /api/provider/balance — उपयोगकर्ता शेष क्वेरी

```
अनुरोध हेडर:
  X-Game-Id: 1234567890
  X-Timestamp: 1716400830
  X-Signature: abc123...

अनुरोध: {
  "user_id": 1234567890,
  "game_id": 9876543210,
  "currency_id": 5555555555
}

प्रतिक्रिया: {
  "code": 0,
  "message": "success",
  "data": { "balance": "1000.50000000" }
}
```

#### POST /api/provider/bet — दांव अधिसूचना

```
अनुरोध: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "bet_type": "straight" }
}

प्रतिक्रिया: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "990.50000000"
  }
}
```

#### POST /api/provider/settle — निपटान अधिसूचना

```
अनुरोध: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}

प्रतिक्रिया: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1040.50000000",
    "win_amount": "50.00000000"
  }
}
```

#### POST /api/provider/refund — रिफंड अधिसूचना

```
अनुरोध: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "reason": "game_crash"
}

प्रतिक्रिया: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1000.50000000"
  }
}
```

### 7.2 टिकट API

#### GET /api/v1/ticket/list — टिकट सूची

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&per_page=20

प्रतिक्रिया: {
  "list": [
    {
      "id": "aB3xK...",
      "type": "deposit",
      "subject": "充值未到账",
      "status": "open",
      "priority": 0,
      "reply_count": 1,
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 3, "page": 1, "last_page": 1
}
```

type: deposit / withdraw / game / account / other
status: open / waiting / replied / closed

#### POST /api/v1/ticket/create — टिकट बनाएं

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
प्रतिक्रिया: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/v1/ticket/{hashid} — टिकट विवरण

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: {
  "id": "...", "type": "deposit", "subject": "...",
  "content": "...", "status": "open",
  "replies": [
    { "id": "...", "content": "...", "is_admin": 1, "created_at": "..." }
  ]
}
```

#### POST /api/v1/ticket/{hashid}/reply — टिकट का उत्तर दें

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "content": "已核实，将在24小时内处理" }
प्रतिक्रिया: { "code": 0, "message": "Reply sent" }
```

### 7.3 ईमेल सत्यापन API

#### POST /api/v1/verify/send-email — ईमेल सत्यापन कोड भेजें

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "email": "user@example.com" }
प्रतिक्रिया: { "code": 0, "message": "Verification code sent" }
त्रुटि: 429 कृपया 60 सेकंड बाद पुनः प्रयास करें
```

#### POST /api/v1/verify/confirm-email — ईमेल की पुष्टि करें

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "code": "123456" }
प्रतिक्रिया: { "code": 0, "message": "Email verified" }
त्रुटि: 422 सत्यापन कोड अमान्य या समाप्त
```

### 7.4 VIP API

#### GET /api/v1/user/vip-status — VIP स्थिति

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: {
  "level": 2,
  "level_name": "Gold",
  "exp": 300,
  "total_exp": 2800,
  "next_level": { "level": 3, "name": "Platinum", "required_exp": 12500 },
  "benefits": {
    "exchange_discount": "0.05",
    "withdraw_fee_discount": "0.30",
    "rate_bonus": "0.003"
  }
}
```

### 7.5 उपलब्धि API

#### GET /api/v1/user/achievements — उपलब्धि सूची

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: {
  "achievements": [
    {
      "key": "first_deposit",
      "name": "First Deposit",
      "description": "Make your first deposit",
      "icon": "",
      "points": 20,
      "progress": 1,
      "completed": true
    }
  ]
}
```

### 7.6 प्रशासन कंसोल नए API

#### GET /admin/ticket/list — टिकट सूची

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&limit=20&status=pending&type=deposit

प्रतिक्रिया: {
  "list": [
    {
      "id": "...", "user_name": "player1",
      "type": "deposit", "subject": "...",
      "status": "open", "reply_count": 0,
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 5, "page": 1, "limit": 20
}
```

#### POST /admin/ticket/{hashid}/reply — टिकट का उत्तर दें

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "content": "已处理" }
प्रतिक्रिया: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/ticket/{hashid}/close — टिकट बंद करें

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/ticket/{hashid}/assign — प्रबंधक नियुक्त करें

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "admin_id": 1234567890 }
प्रतिक्रिया: { "code": 0, "message": "Assigned" }
```

#### GET /admin/analytics/retention — प्रतिधारण विश्लेषण

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?days=30
प्रतिक्रिया: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/analytics/funnel — रूपांतरण फ़नल

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — ARPU/ARPPU प्रवृत्ति

```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?days=30
प्रतिक्रिया: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/analytics/economy — गेम मुद्रा आर्थिक मीट्रिक्स

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: {
  "currencies": [
    {
      "game_name": "Shooter Master",
      "currency": "Gold",
      "total_minted": "500000.0000",
      "total_burned": "320000.0000",
      "circulation": "180000.0000",
      "inflation_rate": "2.3%"
    }
  ]
}
```


#### GET /admin/cdn/provider/list — CDN प्रदाताओं की सूची (क्रेडेंशियल वापस नहीं भेजे जाते)

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "list": [ { "id": "...", "name": "...", "provider": "cloudflare", "status": 1, "sort": 0 } ] }
```

#### POST /admin/cdn/provider/toggle — प्रदाता सक्षम/अक्षम करें {id, status}

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "id": "...", "status": 1 }
प्रतिक्रिया: { "code": 0, "message": "..." }
```

#### POST /admin/cdn/provider/create — बनाएँ {name, provider, config(JSON), status, sort}，provider अद्वितीयता जाँच

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "name": "...", "provider": "aliyun", "config": "{...}", "status": 1, "sort": 0 }
प्रतिक्रिया: { "code": 0, "data": { "id": "..." } }
```

#### PUT /admin/cdn/provider/{hashid} — संपादित करें (खाली config = अपरिवर्तित)

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "name": "...", "config": "" }
प्रतिक्रिया: { "code": 0, "message": "..." }
```

#### DELETE /admin/cdn/provider/{hashid} — हटाएँ

```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "code": 0, "message": "..." }
```

#### POST /admin/cdn/provider/test — कनेक्टिविटी परीक्षण HeadBucket {id}

```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "id": "..." }
प्रतिक्रिया: { "code": 0, "data": { "ok": true } }
```
#### GET /admin/report/summary — रिपोर्ट सारांश

```
需认证: 是
参数: ?start=Y-m-d&end=Y-m-d (缺省最近30天，跨度 ≤90 天，Redis 缓存5分钟)
响应: {
  "start": "2026-08-01", "end": "2026-08-31",
  "new_users": 120, "deposit_amount": "5000.0000", "deposit_count": 45,
  "withdraw_amount": "1200.0000", "withdraw_count": 8,
  "exchange_amount": "3000.0000", "play_count": 1500
}
```


#### GET /admin/report/daily — दैनिक रिपोर्ट

```
需认证: 是
参数: ?start=Y-m-d&end=Y-m-d
响应: {
  "start": "2026-08-01", "end": "2026-08-31",
  "rows": [ { "date": "2026-08-01", "new_users": 12, "deposit_amount": "500.0000", "deposit_count": 4, "withdraw_amount": "100.0000", "withdraw_count": 1, "exchange_amount": "300.0000", "play_count": 150 } ]
}
```


#### GET /admin/report/export — दैनिक रिपोर्ट CSV निर्यात

```
需认证: 是
参数: ?start=Y-m-d&end=Y-m-d&format=excel
响应: CSV 文件（UTF-8 BOM），文件名 report_{start}_{end}.csv，Excel 可直接打开
```

## 8. दर सीमा रणनीति (अद्यतन)

| इंटरफ़ेस | सीमा |
|------|------|
| डिफ़ॉल्ट | 60 बार/मिनट/IP |
| POST /api/v1/auth/login | 10 बार/मिनट |
| POST /api/v1/auth/register | 5 बार/मिनट |
| POST /api/v1/auth/oauth | 10 बार/मिनट |
| POST /api/v1/payment/callback | 30 बार/मिनट |
| POST /api/provider/* | कोई सीमा नहीं (HMAC हस्ताक्षर प्रमाणीकरण) |

## 9. प्रमाणीकरण विवरण (अद्यतन)

### Provider प्रमाणीकरण (ProviderAuth)

1. अनुरोध हेडर से `X-Game-Id`, `X-Timestamp`, `X-Signature` निकालें
2. गेम के अस्तित्व और status=1 की पुष्टि के लिए `game_game` तालिका क्वेरी करें
3. टाइमस्टैम्प को 5 मिनट की विंडो के भीतर सत्यापित करें (रीप्ले सुरक्षा)
4. `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` की गणना करके हस्ताक्षर से तुलना करें
5. `$request->gameId` और `$request->game` इंजेक्ट करें


### 7.7 मित्र API

#### GET /api/v1/friend/list — मित्र सूची
```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/v1/friend/requests — लंबित आवेदन
```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/v1/friend/request — मित्र आवेदन भेजें
```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "friend_id": "hashid" }
```

#### POST /api/v1/friend/accept — आवेदन स्वीकारें
```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "request_id": "hashid" }
```

#### POST /api/v1/friend/reject — आवेदन अस्वीकारें
```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "request_id": "hashid" }
```

#### POST /api/v1/friend/remove — मित्र हटाएं
```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "friend_id": "hashid" }
```

#### GET /api/v1/friend/search — उपयोगकर्ता खोजें
```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?q=username
प्रतिक्रिया: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 चैट API

#### GET /api/v1/chat/conversations — वार्तालाप सूची
```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: {
  "list": [{
    "peer": { "id": "...", "username": "...", "nickname": "...", "avatar": "..." },
    "last_message": "最近一条消息",
    "unread_count": 3,
    "updated_at": "2026-05-22 10:30:00"
  }]
}
```

#### GET /api/v1/chat/messages/{peerHashid} — संदेश सूची
```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?page=1&per_page=50
प्रतिक्रिया: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
स्वचालित रूप से दूसरे पक्ष से आए अपठित संदेशों को पढ़ा हुआ चिह्नित करता है
```

#### POST /api/v1/chat/send — संदेश भेजें
```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "to_user_id": "hashid", "content": "Hello!" }
त्रुटि: 403 गैर-मित्र को नहीं भेज सकते
```

#### GET /api/v1/chat/unread-total — अपठित कुल
```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "count": 5 }
```

**WebSocket कनेक्शन**: `ws://host:8791`
```
// प्रमाणीकरण
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// संदेश प्राप्त करना
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 Webhook API

#### GET /api/v1/webhook/list — सदस्यता सूची
```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/v1/webhook/register — सदस्यता पंजीकृत करें
```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
उपलब्ध घटनाएँ: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/v1/webhook/delete — सदस्यता हटाएं
```
प्रमाणीकरण आवश्यक: हाँ
अनुरोध: { "id": "hook_id" }
```

### 7.10 उन्नत विश्लेषण API

#### GET /admin/analytics/retention — प्रतिधारण विश्लेषण
```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/analytics/funnel — रूपांतरण फ़नल
```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — ARPU/ARPPU प्रवृत्ति
```
प्रमाणीकरण आवश्यक: हाँ
पैरामीटर: ?days=30
प्रतिक्रिया: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/analytics/economy — गेम आर्थिक मीट्रिक्स
```
प्रमाणीकरण आवश्यक: हाँ
प्रतिक्रिया: {
  "currencies": [{
    "game_name": "Shooter Master", "currency": "Gold", "symbol": "G",
    "total_minted": "500000.00000000", "total_burned": "320000.00000000",
    "circulation": "180000.00000000", "inflation_rate": "36.00%"
  }]
}
```


### 7.11 टूर्नामेंट API

#### GET /api/v1/tournament/list — टूर्नामेंट सूची
```
पैरामीटर: ?status=active|upcoming|ended&page=1&per_page=20
प्रतिक्रिया: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/v1/tournament/{hashid} — टूर्नामेंट विवरण
```
प्रतिक्रिया: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/v1/tournament/{hashid}/join — टूर्नामेंट में भाग लें
```
प्रमाणीकरण आवश्यक: हाँ
त्रुटि: 422 पहले से नामांकित / 400 शुरू हो चुका या भरा हुआ / 503 FeatureFlag बंद
```

### 7.12 कूपन शर्तें (नया)

कूपन `conditions` JSON समर्थन करता है:
- `min_deposit`: स्ट्रिंग, न्यूनतम संचयी रिचार्ज राशि
- `first_user_only`: bool, केवल उन नए उपयोगकर्ताओं के लिए जिन्होंने कभी रिचार्ज नहीं किया
- `game_id`: int, निर्दिष्ट गेम खेलना आवश्यक

शर्तों की `available()` सूची फ़िल्टर और `claim()` प्राप्ति दोनों में दोहरी जाँच होती है।

### 7.13 बहु-स्तरीय रेफरल (नया)

रेफरल कमीशन में दूसरे स्तर का लाभ-साझाकरण जोड़ा गया:
- L1: प्रत्यक्ष रेफरर को `referrer_bonus` मिलता है (कॉन्फ़िग: referral.referrer_bonus)
- L2: रेफरर का रेफरर `commission = referrer_bonus * level2_rate` प्राप्त करता है (कॉन्फ़िग: referral.level2_rate, डिफ़ॉल्ट 5%)
- `game_referral_commission` रिकॉर्ड करता है (level/commission_rate/commission_amount)

### 8. दर सीमा रणनीति (अद्यतन)

| इंटरफ़ेस | सीमा |
|------|------|
| POST /api/v1/tournament/{id}/join | 10 बार/मिनट |

---

## 10. नए API (v1.3.15-v1.3.22)

### 10.1 जोखिम प्रबंधन (एडमिन :8787)

| एंडपॉइंट | विवरण |
|------|------|
| GET /admin/risk/dashboard | रिस्क डैशबोर्ड अवलोकन |
| GET /admin/risk/overview | जोखिम अवलोकन मेट्रिक्स |
| GET /admin/risk/hit-trend | हिट ट्रेंड |
| GET /admin/risk/action-distribution | एक्शन वितरण |
| GET /admin/risk/rule-performance | रूल प्रदर्शन |
| GET /admin/risk/rule/list | रूल सूची |
| POST /admin/risk/rule/create | रूल बनाएँ |
| PUT /admin/risk/rule/{hashid} | रूल अपडेट करें |
| POST /admin/risk/rule/{hashid}/toggle | रूल सक्षम/अक्षम करें |
| POST /admin/risk/rule/test | रूल परीक्षण |
| GET /admin/risk/event/list | जोखिम इवेंट सूची |
| GET /admin/risk/event/{hashid} | इवेंट विवरण |
| POST /admin/risk/event/{hashid}/handle | इवेंट संभालें |
| GET /admin/risk/device/list | डिवाइस फिंगरप्रिंट सूची |
| POST /admin/risk/device/block | डिवाइस ब्लॉक करें |
| POST /admin/risk/device/unblock | डिवाइस अनब्लॉक करें |
| GET /admin/risk/ip/list | IP सूची |
| POST /admin/risk/ip/block | IP ब्लॉक करें |
| POST /admin/risk/ip/whitelist | IP व्हाइटलिस्ट |
| POST /admin/risk/ip/appeal | IP अपील |
| POST /admin/risk/ip/recheck | IP पुनर्जांच |
| GET /admin/risk/graph/clusters | क्लस्टर सूची |
| GET /admin/risk/graph/{userId} | उपयोगकर्ता लिंक ग्राफ |
| GET /admin/risk/clusters | जोखिम क्लस्टर सूची |

### 10.2 एंटी-चीट प्रबंधन (एडमिन :8787)

| एंडपॉइंट | विवरण |
|------|------|
| GET /admin/anticheat/events | एंटी-चीट इवेंट सूची |
| GET /admin/anticheat/events/{hashid} | इवेंट विवरण |
| POST /admin/anticheat/events/{hashid}/review | इवेंट समीक्षा |

### 10.3 गतिविधियाँ (एडमिन :8787 + क्लाइंट :8788)

| एंडपॉइंट | विवरण |
|------|------|
| GET /admin/activities/list | गतिविधि सूची (एडमिन) |
| POST /admin/activities/create | गतिविधि बनाएँ (एडमिन) |
| PUT /admin/activities/{hashid} | गतिविधि अपडेट करें (एडमिन) |
| DELETE /admin/activities/{hashid} | गतिविधि हटाएँ (एडमिन) |
| GET /api/v1/activities/list | गतिविधि सूची (क्लाइंट) |
| GET /api/v1/activities/progress | भागीदारी प्रगति (क्लाइंट) |
| GET /api/v1/activities/{hashid} | गतिविधि विवरण (क्लाइंट) |
| POST /api/v1/activities/{hashid}/checkin | चेक-इन (क्लाइंट) |

### 10.4 समूह / शेयर (क्लाइंट :8788 + एडमिन :8787)

| एंडपॉइंट | विवरण |
|------|------|
| POST /api/v1/groups | समूह बनाएँ |
| GET /api/v1/groups/{hashid} | समूह विवरण |
| GET /api/v1/groups/{hashid}/members | सदस्य सूची |
| POST /api/v1/groups/{hashid}/join | समूह से जुड़ें |
| POST /api/v1/groups/{hashid}/leave | समूह छोड़ें |
| PUT /api/v1/groups/{hashid}/role | सदस्य भूमिका |
| POST /api/v1/shares | शेयर लिंक बनाएँ |
| POST /api/v1/shares/visit | शेयर विज़िट ट्रैकिंग |
| GET /admin/groups | समूह सूची (एडमिन) |
| GET /admin/groups/{hashid}/audit | समूह ऑडिट (एडमिन) |
| GET /admin/share/stats | शेयर आँकड़े (एडमिन) |

### 10.5 भुगतान गेटवे विस्तार (L1)

| गेटवे | विवरण |
|------|------|
| Adyen | नया भुगतान गेटवे (जमा / कॉलबैक सत्यापन / स्वतः जमा) |
| GrabPay | नया भुगतान गेटवे (जमा / कॉलबैक सत्यापन / स्वतः जमा) |
