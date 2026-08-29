# Dokumentasi API
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · **Bahasa Indonesia** · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Dokumen interaktif online (mendukung debug online):
- Bisnis sisi C: http://localhost:8788/apidoc/
- Backend administrasi: http://localhost:8787/apidoc/
- Kata sandi: admin123

## 1. Konvensi

### 1.1 URL Dasar

| Ujung | Alamat |
|----|------|
| Backend administrasi | `http://localhost:8787` |
| Bisnis sisi C | `http://localhost:8788` |

### 1.2 Header Permintaan Umum

```
Content-Type: application/json
API-Version: v1
Authorization: Bearer <token>    (antarmuka yang memerlukan autentikasi)
```

### 1.3 Format Respons Terpadu

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Arti |
|------|------|
| 0 | Sukses |
| 400 | Kesalahan parameter |
| 401 | Belum autentikasi (Token hilang/kedaluwarsa/tidak valid) |
| 403 | Tanpa izin |
| 404 | Sumber daya tidak ada |
| 422 | Gagal validasi |
| 429 | Terlalu banyak permintaan (terpicu rate limit) |
| 500 | Error server |

### 1.4 Pengodean ID

Semua ID dalam permintaan dan respons antarmuka adalah string berenkode Hashids, bukan nilai BIGINT asli.

```
Eksternal: aB3xK9mW2pQ7rT5v  (string hashid)
Internal: 1750123456789      (Snowflake BIGINT)
```

### 1.5 Format Paginasi

```
Permintaan: ?page=1&per_page=20

Respons: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. Antarmuka Sisi C (service :8788)

### 2.1 Autentikasi

#### POST /api/auth/register — Registrasi Pengguna

```
Permintaan: {
  "username": "player1",
  "password": "123456",
  "email": "player@example.com"     // opsional
}

Respons: {
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

#### POST /api/auth/login — Login Pengguna

```
Permintaan: {
  "username": "player1",
  "password": "123456"
}

Respons: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "...", ... }
}
```

Error: 401 nama pengguna atau kata sandi salah / akun telah dinonaktifkan

#### POST /api/auth/refresh — Perbarui Token

```
Permintaan: (Authorization: Bearer <refresh_token>)

Respons: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 Dompet

#### GET /api/wallet/info — Informasi Dompet

```
Perlu autentikasi: ya

Respons: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/wallet/transactions — Catatan Transaksi

```
Perlu autentikasi: ya
Parameter: ?page=1&per_page=20&type=deposit    (type opsional)

Respons: {
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

Nilai opsional type: deposit / withdraw / exchange_in / exchange_out / game_earn / game_spend
```

### 2.3 Deposit

#### POST /api/deposit/create — Buat Pesanan Deposit

```
Perlu autentikasi: ya

Permintaan: {
  "amount": "10.00",
  "currency": "USD",
  "payment_method_id": "aB3xK..."
}

Respons: {
  "order_id": "aB3xK...",
  "order_no": "DEP202605221030000123",
  "amount": "10.00",
  "platform_amount": "10.0000",
  "checkout_url": "https://checkout.stripe.com/...",
  "expires_at": "2026-05-22 11:30:00"
}
```

Nilai opsional currency: USD / CNY / EUR

checkout_url: tautan pengalihan gateway pembayaran (diisi saat pesanan dibuat); expires_at: kedaluwarsa tautan pembayaran (1 jam setelah dibuat)

#### GET /api/deposit/orders — Catatan Deposit

```
Perlu autentikasi: ya
Parameter: ?page=1&per_page=20

Respons: {
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

Nilai opsional status: pending / paid / confirmed / cancelled

### 2.4 Penukaran

#### POST /api/exchange/quote — Kueri Harga

```
Perlu autentikasi: ya

Permintaan: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "direction": "in",
  "platform_amount": "10.0000"
}

Respons: {
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000",
  "spread_pct": "5.00%"
}
```

direction: in=membeli koin game / out=menjual koin game

#### POST /api/exchange/buy — Beli Koin Game

```
Perlu autentikasi: ya

Permintaan: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "10.0000"
}

Respons: {
  "exchange_id": "aB3xK...",
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000"
}
```

Error: 422 saldo koin platform tidak cukup / 404 game tidak tersedia

#### POST /api/exchange/sell — Jual Koin Game

```
Perlu autentikasi: ya

Permintaan: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "950.0000"
}

Respons: {
  "exchange_id": "aB3xK...",
  "platform_amount": "9.0250",
  "game_amount": "950.0000",
  "spread_fee": "0.4750",
  "rate": "100.00000000"
}
```

Error: 422 saldo koin game tidak cukup

#### GET /api/exchange/records — Catatan Penukaran

```
Perlu autentikasi: ya
Parameter: ?page=1&per_page=20

Respons: {
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

### 2.5 Penarikan

#### POST /api/withdraw/apply — Pengajuan Penarikan

```
Perlu autentikasi: ya

Permintaan: {
  "platform_amount": "50.0000",
  "method": "paypal",
  "account_info": "user@paypal.com"
}

Respons: {
  "order_id": "...",
  "order_no": "WTH202605221030000456",
  "status": "approved"
}
```

Nilai opsional method: paypal / bank / crypto

status:
- approved: lolos otomatis (jumlah < auto_approve_threshold)
- pending: menunggu review (jumlah >= auto_approve_threshold)

Error:
- 403 fungsi penarikan sementara dimatikan (saklar global mati)
- 400 di bawah jumlah penarikan minimum
- 400 melebihi batas penarikan harian
- 400 saldo tidak cukup

#### GET /api/withdraw/orders — Catatan Penarikan

```
Perlu autentikasi: ya
Parameter: ?page=1&per_page=20

Respons: {
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

### 2.6 Game

#### GET /api/game/list — Daftar Game

```
Parameter: ?page=1&per_page=20&keyword=射击&type=self

Respons: {
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

Nilai opsional type: self / third_party

#### GET /api/game/{hashid} — Detail Game

```
Respons: {
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

#### POST /api/game/launch — Luncurkan Game

```
Perlu autentikasi: ya

Permintaan: { "game_id": "aB3xK..." }

Respons: {
  "id": "...",
  "name": "射击大师",
  "type": "self",
  "api_endpoint": "https://game.example.com/play"
}
```

### 2.7 Login Pihak Ketiga OAuth

Mendukung 7 platform: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/auth/oauth/{provider} — Dapatkan URL Otorisasi

```
Parameter: provider = google / facebook / apple / twitter / microsoft / linkedin / github

Respons: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/auth/oauth/{provider}/callback — Callback OAuth

```
Permintaan: { "code": "授权码", "state": "防CSRF状态" }

Respons: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=pengguna baru yang terdaftar / false=akun yang sudah ada ditautkan

### 2.8 Verifikasi KYC Nama Asli

#### GET /api/user/identity/status — Status Verifikasi

```
Perlu autentikasi: ya

Respons: {
  "status": "approved",          // not_submitted / pending / approved / rejected
  "real_name": "J***",
  "id_type": "id_card",
  "review_note": "",
  "submitted_at": "2026-05-22 10:00:00",
  "reviewed_at": "2026-05-23 14:00:00"
}
```

#### POST /api/user/identity/apply — Ajukan Verifikasi

```
Perlu autentikasi: ya

Permintaan: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}

Respons: { "message": "KYC submitted successfully" }
```

### 2.9 Pembayaran

#### POST /api/payment/callback — Callback Pembayaran (publik)

```
Permintaan: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

Respons: { "message": "success" }
```

status: success / failed

Nilai provider: stripe / paypal / nowpayments / coinbase / skrill / neteller / paysafecard / paytm / mercadopago / astropay / paypay / kakaopay / gcash (toss / mpesa / paystack segera hadir)

| provider | Wilayah | Skema tanda tangan | Mata uang yang didukung |
|----------|---------|--------------------|-------------------------|
| stripe | Global (125+ metode pembayaran lokal, termasuk APM Alipay/WeChat Pay) | Webhook HMAC-SHA256 | USD / CNY / EUR |
| paypal | 200+ pasar global | Verifikasi webhook (verify-webhook-signature) | USD / CNY / EUR dan fiat lainnya |
| nowpayments | Global (kripto) | IPN HMAC-SHA512 | USDT TRC20 / ERC20 |
| coinbase | Global (kripto) | Webhook HMAC-SHA256 (base64 secret) | USDC / BTC / ETH |
| skrill | Eropa / Global | Pemeriksaan MD5 secret word | EUR dan fiat lainnya |
| neteller | Eropa / Global | Perbandingan kolom secret key | EUR dan fiat lainnya |
| paysafecard | Eropa (DE / AT / CH dll.) | X-Signature HMAC-SHA256 | EUR dan fiat lainnya |
| paytm | India | SHA256 + AES-128-CBC | INR |
| mercadopago | Amerika Latin (BR / MX dll.) | X-Signature (ts,v1) HMAC-SHA256 | BRL / MXN dan fiat lainnya |
| astropay | Amerika Latin (BR dll.) | MD5(order_id.amount.status.secret) | BRL dan fiat lainnya |
| paypay | Jepang | PayPay-Signature HMAC-SHA256 | JPY |
| kakaopay | Korea Selatan | Tanpa webhook (dua langkah ready/approve) | KRW |
| gcash | Filipina | Paymongo-Signature HMAC-SHA256 | PHP |
| toss | Korea Selatan (segera hadir) | — | KRW |
| mpesa | Kenya / Tanzania dll. (segera hadir) | — | KES / TZS |
| paystack | Nigeria (segera hadir) | — | NGN |

#### GET /api/payment/methods — Metode Pembayaran Tersedia (publik)

```
Respons: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

Difilter berdasarkan negara pengguna (X-Language/Accept-Language → kode negara): countries kosong atau berisi * berarti terlihat global; diurutkan berdasarkan preferensi metode pembayaran country_config negara tersebut

### 2.10 Catatan Game

#### GET /api/game/play-logs — Daftar Catatan Game

```
Perlu autentikasi: ya
Parameter: ?page=1&per_page=20&game_id=xxx&action=start

Respons: {
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

#### GET /api/game/play-log/{hashid} — Detail Catatan Game

```
Perlu autentikasi: ya
Respons: { catatan lengkap, termasuk session_id / game_amount_before / after, dll. }
```

### 2.12 Papan Peringkat

#### GET /api/leaderboard/list — Daftar Papan Peringkat

```
Respons: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/leaderboard/{hashid} — Detail Papan Peringkat

```
Respons: {
  "id": "...",
  "name": "全服累计收入榜",
  "type": "total",
  "rankings": [
    { "rank": 1, "user_id": "...", "score": "50000.0000" }
  ]
}
```

### 2.13 Kupon

#### GET /api/coupon/available — Kupon yang Dapat Diambil

```
Perlu autentikasi: ya
Respons: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/coupon/claim — Ambil Kupon

```
Perlu autentikasi: ya
Permintaan: { "coupon_id": "hashid" }
Respons: { "coupon": { ... } }
```

#### GET /api/coupon/my — Kupon Saya

```
Perlu autentikasi: ya
Parameter: ?status=unused
Respons: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 Konfigurasi Negara

#### GET /api/country/list — Daftar Negara

```
Respons: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/country/{code} — Detail Negara

```
Respons: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 Notifikasi

#### GET /api/notification/list — Daftar Notifikasi

```
Perlu autentikasi: ya
Parameter: ?page=1&per_page=20&is_read=0

Respons: {
  "list": [
    { "id": "...", "type": "deposit", "title": "Deposit Received", "is_read": 0, "created_at": "..." }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### GET /api/notification/unread-count — Jumlah Belum Dibaca

```
Perlu autentikasi: ya
Respons: { "count": 3 }
```

#### POST /api/notification/read — Tandai Telah Dibaca

```
Perlu autentikasi: ya
Permintaan: { "id": "hashid" }  // tidak diisi=semua ditandai dibaca
```

### 2.17 Referral

#### GET /api/referral/my-code — Kode Referral Saya

```
Perlu autentikasi: ya
Respons: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/referral/apply — Gunakan Kode Referral

```
Perlu autentikasi: ya
Permintaan: { "code": "ABC12345" }
Respons: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/user/2fa/status — Status 2FA

```
Perlu autentikasi: ya
Respons: { "enabled": false }
```

#### POST /api/user/2fa/setup — Atur 2FA

```
Perlu autentikasi: ya
Respons: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/user/2fa/enable — Aktifkan 2FA

```
Perlu autentikasi: ya
Permintaan: { "code": "123456" }
Respons: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/2fa/verify — Verifikasi 2FA (publik)

```
Permintaan: { "user_id": "hashid", "code": "123456" }
Respons: { "valid": true }
```

### 2.19 Pencarian

#### GET /api/search — Pencarian Global

```
Parameter: ?q=keyword&type=game&page=1&per_page=20
Respons: { "list": [...], "total": 100 }
```

#### GET /api/game/suggest — Saran Pencarian

```
Parameter: ?q=shoot
Respons: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 Bahasa

#### GET /api/language/list — Daftar Bahasa Tersedia

```
Respons: {
  "current": "en-US",
  "languages": {
    "en-US": { "name": "English", "nativeName": "English", "icon": "us" },
    "zh-CN": { "name": "Chinese (Simplified)", "nativeName": "简体中文", "icon": "cn" },
    "ja-JP": { "name": "Japanese", "nativeName": "日本語", "icon": "jp" },
    "ko-KR": { "name": "Korean", "nativeName": "한국어", "icon": "kr" }
  }
}
```

#### POST /api/language/switch — Ganti Bahasa

```
Permintaan: { "locale": "zh-CN" }
Respons: { "locale": "zh-CN" }
```

Nilai opsional locale: en-US / zh-CN / ja-JP / ko-KR

### 2.8 Pengguna

#### GET /api/user/profile — Informasi Pribadi

```
Perlu autentikasi: ya

Respons: {
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

#### PUT /api/user/profile — Edit Profil

```
Perlu autentikasi: ya

Permintaan: {
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}

Respons: {
  "id": "...",
  "username": "player1",
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}
```

Nilai opsional language: en-US / zh-CN / ja-JP / ko-KR

### 2.9 Pengumuman

#### GET /api/announcement/list — Daftar Pengumuman

```
Respons: {
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

#### GET /api/announcement/detail/{hashid} — Detail Pengumuman

```
Respons: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

## 3. Antarmuka Backend Administrasi (admin :8787)

### 3.1 Dasbor Platform

#### GET /admin/dashboard/platform

```
Perlu autentikasi: ya (AdminAuth + AdminPermission)

Respons: {
  "total_users": 1500,
  "active_users_7d": 320,
  "total_games": 12,
  "pending_withdraws": 5,
  "today_deposits": "500.0000",
  "today_withdraws": "120.0000",
  "total_spread_fee": "1500.5000"
}
```

### 3.2 Manajemen Game

#### GET /admin/game/list — Daftar Game

```
Perlu autentikasi: ya
Parameter: ?page=1&per_page=20&keyword=射击

Respons: {
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

#### POST /admin/game/create — Buat Game

```
Perlu autentikasi: ya

Permintaan: {
  "name": "新游戏",
  "slug": "new-game",
  "type": "self",
  "description": "游戏描述",        // opsional
  "cover_image": "https://...",    // opsional
  "api_endpoint": "https://...",   // opsional
  "api_key": "...",                // opsional
  "api_secret": "...",             // opsional
  "status": 1,                     // opsional, default 0
  "sort": 0                        // opsional, default 0
}

Respons: { "id": "aB3xK..." }
```

Nilai opsional type: self / third_party

#### PUT /admin/game/{hashid} — Edit Game

```
Perlu autentikasi: ya

Permintaan: {
  "name": "新名称",
  "status": 1
  // dapat diperbarui sebagian, kolom sama dengan create
}

Respons: { "message": "更新成功" }
```

#### DELETE /admin/game/{hashid} — Hapus Game

```
Perlu autentikasi: ya
Respons: { "message": "删除成功" }
```

#### POST /admin/game/currency/manage — Kelola Mata Uang

```
Perlu autentikasi: ya

Permintaan: {
  "game_id": "aB3xK...",
  "currencies": [
    {
      "id": "",                       // kosong=baru, ada nilai=perbarui
      "name": "金币",
      "symbol": "G",
      "exchange_rate": "100.00000000",
      "spread_pct": "5.00",
      "min_exchange": "1.0000",
      "max_exchange": "10000.0000"
    }
  ]
}

Respons: { "message": "币种更新成功" }
```

### 3.3 Manajemen Penarikan

#### GET /admin/withdraw/orders — Daftar Pesanan Penarikan

```
Perlu autentikasi: ya
Parameter: ?page=1&per_page=20&status=pending

Respons: {
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

#### PUT /admin/withdraw/review — Review Penarikan

```
Perlu autentikasi: ya

Permintaan: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

Respons: { "message": "已通过" }
```

action: approve=lolos / reject=tolak (saat ditolak, koin platform otomatis dikembalikan)

Error: 422 status pesanan bukan menunggu review

#### PUT /admin/withdraw/switch — Saklar Penarikan Global

```
Perlu autentikasi: ya

Permintaan: { "enabled": 1 }

Respons: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/withdraw/limits/set — Atur Batas Penarikan

```
Perlu autentikasi: ya

Permintaan: {
  "daily_limit": "10000.0000",             // opsional
  "min_amount": "1.0000",                  // opsional
  "auto_approve_threshold": "100.0000"     // opsional
}

Respons: {
  "daily_limit": "10000.0000",
  "min_amount": "1.0000",
  "auto_approve_threshold": "100.0000",
  "global_switch": true
}
```

### 3.4 Manajemen Pengguna Platform

#### GET /admin/platform/user/list — Daftar Pengguna Sisi C

```
Perlu autentikasi: ya
Parameter: ?page=1&per_page=20&keyword=player&status=1

Respons: {
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

#### GET /admin/platform/user/{hashid} — Detail Pengguna

```
Perlu autentikasi: ya

Respons: {
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

#### PUT /admin/platform/user/{hashid} — Edit/Banned Pengguna

```
Perlu autentikasi: ya

Permintaan: {
  "status": 0,         // 0=nonaktif 1=aktif
  "nickname": "..."    // opsional
}

Respons: { "message": "更新成功" }
```

### 3.5 Manajemen Pembayaran

#### GET /admin/payment/method/list

```
Perlu autentikasi: ya

Respons: {
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

#### POST /admin/payment/method/toggle — Aktifkan/Nonaktifkan Metode Pembayaran

```
Perlu autentikasi: ya

Permintaan: { "id": "aB3xK...", "status": 0 }

Respons: { "message": "已更新" }
```

### 3.6 Manajemen Pengumuman

#### GET /admin/announcement/list

```
Perlu autentikasi: ya
Parameter: ?page=1&per_page=20

Respons: {
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

#### POST /admin/announcement/create — Terbitkan Pengumuman

```
Perlu autentikasi: ya

Permintaan: {
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护。",
  "type": "system",           // opsional, default "system"
  "target_lang": "",          // opsional, kosong=semua bahasa
  "status": 1,                // opsional, default 1 (0=draf 1=terbit)
  "start_at": "2026-05-23 02:00:00",  // opsional
  "end_at": "2026-05-23 04:00:00"     // opsional
}

Respons: { "id": "aB3xK..." }
```

### 3.7 Review KYC

#### GET /admin/identity/list — Daftar KYC

```
Perlu autentikasi: ya
Parameter: ?page=1&per_page=20&status=pending

Respons: {
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

#### PUT /admin/identity/review — Review KYC

```
Perlu autentikasi: ya

Permintaan: { "id": "hashid", "action": "approve", "note": "" }

Respons: { "message": "Approved" }
```

action: approve / reject

### 3.8 Manajemen Server Game

#### GET /admin/game/server/list — Daftar Server

```
Perlu autentikasi: ya
Parameter: ?game_id=hashid

Respons: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/game/server/create — Buat Server

```
Perlu autentikasi: ya
Permintaan: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
Respons: { "id": "hashid" }
```

#### PUT /admin/game/server/{hashid} — Edit Server

```
Perlu autentikasi: ya
Permintaan: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/game/server/{hashid} — Hapus Server

```
Perlu autentikasi: ya
```

### 3.9 Manajemen Batas Penarikan Bertingkat

#### GET /admin/withdraw/limits/list

```
Perlu autentikasi: ya

Respons: {
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

#### PUT /admin/withdraw/limits/{hashid} — Perbarui Batas

```
Perlu autentikasi: ya

Permintaan: { "single_max": "10000.0000", "fee_pct": "0.25" }
// dapat diperbarui sebagian
```

### 3.11 Manajemen Kategori Game

#### GET /admin/game/category/list

```
Perlu autentikasi: ya
Respons: { "list": [{ "id": "...", "name": "动作", "slug": "action", "sort": 1 }] }
```

#### POST /admin/game/category/create

```
Perlu autentikasi: ya
Permintaan: { "name": "新分类", "slug": "new-cat", "icon": "star", "sort": 10 }
Respons: { "id": "hashid" }
```

#### PUT /admin/game/category/{hashid} — Edit Kategori

#### DELETE /admin/game/category/{hashid} — Hapus Kategori

#### POST /admin/game/category/assign — Tetapkan Game

```
Perlu autentikasi: ya
Permintaan: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 Manajemen Papan Peringkat

#### GET /admin/leaderboard/list — Daftar Papan Peringkat

```
Perlu autentikasi: ya
Respons: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/leaderboard/create — Buat Papan Peringkat

```
Perlu autentikasi: ya
Permintaan: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(opsional)" }
```

#### PUT /admin/leaderboard/{hashid} — Edit Papan Peringkat

#### DELETE /admin/leaderboard/{hashid} — Hapus Papan Peringkat

#### POST /admin/leaderboard/{hashid}/refresh — Segarkan Cache

### 3.13 Manajemen Kupon

#### GET /admin/coupon/list — Daftar Kupon

#### POST /admin/coupon/create — Buat Kupon

```
Perlu autentikasi: ya
Permintaan: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/coupon/{hashid} — Edit (saat belum diambil)

#### DELETE /admin/coupon/{hashid} — Hapus

#### GET /admin/coupon/{hashid}/stats — Statistik Pengambilan

```
Respons: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 Manajemen Konfigurasi Negara

#### GET /admin/country/config/list — Daftar Konfigurasi Negara

#### POST /admin/country/config/create — Buat Konfigurasi Negara

```
Perlu autentikasi: ya
Permintaan: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/country/config/{hashid} — Edit Konfigurasi Negara

### 3.15 Ekspor Data

#### POST /admin/export/users — Ekspor Pengguna Sisi C

```
Perlu autentikasi: ya
Parameter (JSON): { "status": 1 }   // filter opsional

Respons: unduhan file Excel (xlsx)
```

#### POST /admin/export/transactions — Ekspor Transaksi Platform

```
Perlu autentikasi: ya
Parameter (JSON): { "type": "deposit" }   // filter opsional

Respons: unduhan file Excel (xlsx)
```

### 3.16 Analisis Data (Agregasi real-time MySQL)

Semua endpoint memerlukan autentikasi (AdminAuth + AdminPermission), data diagregasi real-time dari MySQL, tidak bergantung pada ClickHouse.

| Metode | Jalur | Keterangan |
|------|------|------|
| GET | /admin/analytics/overview | Ringkasan platform (hari ini/7 hari terakhir) |
| GET | /admin/analytics/game-ranking | Peringkat game (?days=7) |
| GET | /admin/analytics/dau-trend | Tren DAU (?days=30) |
| GET | /admin/analytics/hourly-trend | Tren per jam |
| GET | /admin/analytics/action-distribution | Distribusi perilaku |
| GET | /admin/analytics/revenue | Analisis pendapatan |
| GET | /admin/analytics/conversion | Rasio konversi game |
| GET | /admin/analytics/probability | Probabilitas gabungan/bersyarat |
| GET | /admin/analytics/retention | Analisis retensi D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Funnel konversi |
| GET | /admin/analytics/arpu | Tren ARPU/ARPPU |
| GET | /admin/analytics/economy | Metrik ekonomi mata uang game |

### 3.17 Manajemen Tiket

Semua endpoint memerlukan autentikasi (AdminAuth + AdminPermission).

| Metode | Jalur | Keterangan |
|------|------|------|
| GET | /admin/ticket/list | Daftar tiket (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | Detail tiket (termasuk balasan) |
| POST | /admin/ticket/{hashid}/reply | Balas tiket |
| POST | /admin/ticket/{hashid}/close | Tutup tiket |
| POST | /admin/ticket/{hashid}/assign | Tetapkan penangan (admin_id) |

## 4. Strategi Rate Limit

| Antarmuka | Batas |
|------|------|
| Default | 60 kali/menit/IP |
| POST /api/auth/login | 10 kali/menit |
| POST /api/auth/register | 5 kali/menit |

Melebihi batas mengembalikan 429, header respons berisi:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1716400830
Retry-After: 60
```

## 5. Penjelasan Autentikasi

### Sisi C (UserAuth)

1. Ekstrak Token dari `Authorization: Bearer <token>`
2. Verifikasi tanda tangan JWT (HS256), parse `sub` (ID pengguna)
3. Kueri tabel `game_user` untuk memverifikasi pengguna ada dan status=1
4. Suntikkan `$request->userId`

### Backend Administrasi (AdminAuth + AdminPermission)

1. AdminAuth: verifikasi tanda tangan JWT, parse `sub` (ID admin), suntikkan `$request->adminId`
2. AdminPermission: cari izin berdasarkan peran pengguna, cocokkan dengan identifikasi izin berformat `method.path`
3. Super admin dengan `slug=*` melewati pemeriksaan izin

## 6. Referensi Kode Error

| code | Arti | Skenario umum |
|------|------|---------|
| 0 | Sukses | - |
| 400 | Kesalahan parameter | Format permintaan salah, jumlah tidak cukup |
| 401 | Belum autentikasi | Token hilang/kedaluwarsa/tidak valid, akun dinonaktifkan |
| 403 | Tanpa izin | Pengguna tidak memiliki izin peran yang sesuai, game tidak tersedia |
| 404 | Tidak ada | Sumber daya tidak ditemukan |
| 422 | Gagal validasi | Parameter formulir tidak sesuai aturan, status pesanan tidak mengizinkan operasi |
| 429 | Rate limit | Terlalu banyak permintaan |
| 500 | Error server | Pengecualian tak terduga |


## 7. API Baru (Perluasan Ekosistem v2.0)

### 7.1 Provider API — Antarmuka Callback Pihak Game

**Metode autentikasi**: tanda tangan HMAC-SHA256 (X-Game-Id + X-Timestamp + X-Signature)
**Jendela waktu**: 5 menit

#### POST /api/provider/balance — Kueri Saldo Pengguna

```
Header permintaan:
  X-Game-Id: 1234567890
  X-Timestamp: 1716400830
  X-Signature: abc123...

Permintaan: {
  "user_id": 1234567890,
  "game_id": 9876543210,
  "currency_id": 5555555555
}

Respons: {
  "code": 0,
  "message": "success",
  "data": { "balance": "1000.50000000" }
}
```

#### POST /api/provider/bet — Notifikasi Taruhan

```
Permintaan: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "bet_type": "straight" }
}

Respons: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "990.50000000"
  }
}
```

#### POST /api/provider/settle — Notifikasi Penyelesaian

```
Permintaan: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}

Respons: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1040.50000000",
    "win_amount": "50.00000000"
  }
}
```

#### POST /api/provider/refund — Notifikasi Pengembalian Dana

```
Permintaan: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "reason": "game_crash"
}

Respons: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1000.50000000"
  }
}
```

### 7.2 API Tiket

#### GET /api/ticket/list — Daftar Tiket

```
Perlu autentikasi: ya
Parameter: ?page=1&per_page=20

Respons: {
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

#### POST /api/ticket/create — Buat Tiket

```
Perlu autentikasi: ya
Permintaan: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
Respons: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/ticket/{hashid} — Detail Tiket

```
Perlu autentikasi: ya
Respons: {
  "id": "...", "type": "deposit", "subject": "...",
  "content": "...", "status": "open",
  "replies": [
    { "id": "...", "content": "...", "is_admin": 1, "created_at": "..." }
  ]
}
```

#### POST /api/ticket/{hashid}/reply — Balas Tiket

```
Perlu autentikasi: ya
Permintaan: { "content": "已核实，将在24小时内处理" }
Respons: { "code": 0, "message": "Reply sent" }
```

### 7.3 API Verifikasi Email

#### POST /api/verify/send-email — Kirim Kode Verifikasi Email

```
Perlu autentikasi: ya
Permintaan: { "email": "user@example.com" }
Respons: { "code": 0, "message": "Verification code sent" }
Error: 429 coba lagi setelah 60 detik
```

#### POST /api/verify/confirm-email — Konfirmasi Email

```
Perlu autentikasi: ya
Permintaan: { "code": "123456" }
Respons: { "code": 0, "message": "Email verified" }
Error: 422 kode verifikasi tidak valid atau sudah kedaluwarsa
```

### 7.4 API VIP

#### GET /api/user/vip-status — Status VIP

```
Perlu autentikasi: ya
Respons: {
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

### 7.5 API Pencapaian

#### GET /api/user/achievements — Daftar Pencapaian

```
Perlu autentikasi: ya
Respons: {
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

### 7.6 API Baru Backend Administrasi

#### GET /admin/ticket/list — Daftar Tiket

```
Perlu autentikasi: ya
Parameter: ?page=1&limit=20&status=pending&type=deposit

Respons: {
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

#### POST /admin/ticket/{hashid}/reply — Balas Tiket

```
Perlu autentikasi: ya
Permintaan: { "content": "已处理" }
Respons: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/ticket/{hashid}/close — Tutup Tiket

```
Perlu autentikasi: ya
Respons: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/ticket/{hashid}/assign — Tetapkan Penangan

```
Perlu autentikasi: ya
Permintaan: { "admin_id": 1234567890 }
Respons: { "code": 0, "message": "Assigned" }
```

#### GET /admin/analytics/retention — Analisis Retensi

```
Perlu autentikasi: ya
Parameter: ?days=30
Respons: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/analytics/funnel — Funnel Konversi

```
Perlu autentikasi: ya
Respons: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — Tren ARPU/ARPPU

```
Perlu autentikasi: ya
Parameter: ?days=30
Respons: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/analytics/economy — Metrik Ekonomi Mata Uang Game

```
Perlu autentikasi: ya
Respons: {
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

## 8. Strategi Rate Limit (Diperbarui)

| Antarmuka | Batas |
|------|------|
| Default | 60 kali/menit/IP |
| POST /api/auth/login | 10 kali/menit |
| POST /api/auth/register | 5 kali/menit |
| POST /api/auth/oauth | 10 kali/menit |
| POST /api/payment/callback | 30 kali/menit |
| POST /api/provider/* | Tanpa batas (autentikasi tanda tangan HMAC) |

## 9. Penjelasan Autentikasi (Diperbarui)

### Autentikasi Provider (ProviderAuth)

1. Ekstrak `X-Game-Id`, `X-Timestamp`, `X-Signature` dari header permintaan
2. Kueri tabel `game_game` untuk memverifikasi game ada dan status=1
3. Verifikasi timestamp dalam jendela 5 menit (cegah replay)
4. Hitung `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` dan bandingkan dengan tanda tangan
5. Suntikkan `$request->gameId` dan `$request->game`


### 7.7 API Teman

#### GET /api/friend/list — Daftar Teman
```
Perlu autentikasi: ya
Respons: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/friend/requests — Permintaan yang Menunggu
```
Perlu autentikasi: ya
Respons: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/friend/request — Kirim Permintaan Teman
```
Perlu autentikasi: ya
Permintaan: { "friend_id": "hashid" }
```

#### POST /api/friend/accept — Terima Permintaan
```
Perlu autentikasi: ya
Permintaan: { "request_id": "hashid" }
```

#### POST /api/friend/reject — Tolak Permintaan
```
Perlu autentikasi: ya
Permintaan: { "request_id": "hashid" }
```

#### POST /api/friend/remove — Hapus Teman
```
Perlu autentikasi: ya
Permintaan: { "friend_id": "hashid" }
```

#### GET /api/friend/search — Cari Pengguna
```
Perlu autentikasi: ya
Parameter: ?q=username
Respons: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 API Chat

#### GET /api/chat/conversations — Daftar Percakapan
```
Perlu autentikasi: ya
Respons: {
  "list": [{
    "peer": { "id": "...", "username": "...", "nickname": "...", "avatar": "..." },
    "last_message": "最近一条消息",
    "unread_count": 3,
    "updated_at": "2026-05-22 10:30:00"
  }]
}
```

#### GET /api/chat/messages/{peerHashid} — Daftar Pesan
```
Perlu autentikasi: ya
Parameter: ?page=1&per_page=50
Respons: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
Otomatis menandai pesan belum dibaca dari lawan bicara sebagai telah dibaca
```

#### POST /api/chat/send — Kirim Pesan
```
Perlu autentikasi: ya
Permintaan: { "to_user_id": "hashid", "content": "Hello!" }
Error: 403 bukan teman tidak dapat mengirim
```

#### GET /api/chat/unread-total — Total Belum Dibaca
```
Perlu autentikasi: ya
Respons: { "count": 5 }
```

**Koneksi WebSocket**: `ws://host:8791`
```
// autentikasi
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// menerima pesan
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 API Webhook

#### GET /api/webhook/list — Daftar Langganan
```
Perlu autentikasi: ya
Respons: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/webhook/register — Daftarkan Langganan
```
Perlu autentikasi: ya
Permintaan: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
Event yang tersedia: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/webhook/delete — Hapus Langganan
```
Perlu autentikasi: ya
Permintaan: { "id": "hook_id" }
```

### 7.10 API Analisis Lanjutan

#### GET /admin/analytics/retention — Analisis Retensi
```
Perlu autentikasi: ya
Respons: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/analytics/funnel — Funnel Konversi
```
Perlu autentikasi: ya
Respons: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — Tren ARPU/ARPPU
```
Perlu autentikasi: ya
Parameter: ?days=30
Respons: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/analytics/economy — Metrik Ekonomi Game
```
Perlu autentikasi: ya
Respons: {
  "currencies": [{
    "game_name": "Shooter Master", "currency": "Gold", "symbol": "G",
    "total_minted": "500000.00000000", "total_burned": "320000.00000000",
    "circulation": "180000.00000000", "inflation_rate": "36.00%"
  }]
}
```


### 7.11 API Turnamen

#### GET /api/tournament/list — Daftar Turnamen
```
Parameter: ?status=active|upcoming|ended&page=1&per_page=20
Respons: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/tournament/{hashid} — Detail Turnamen
```
Respons: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/tournament/{hashid}/join — Daftar Ikut Serta
```
Perlu autentikasi: ya
Error: 422 sudah terdaftar / 400 sudah dimulai atau penuh / 503 FeatureFlag dimatikan
```

### 7.12 Kondisi Kupon (Baru)

JSON `conditions` kupon mendukung:
- `min_deposit`: string, jumlah minimum deposit kumulatif
- `first_user_only`: bool, hanya untuk pengguna baru yang belum pernah deposit
- `game_id`: int, harus pernah memainkan game tertentu

Kondisi divalidasi ganda pada daftar `available()` dan saat pengambilan `claim()`.

### 7.13 Referral Bertingkat (Baru)

Komisi referral menambahkan bagi hasil level dua:
- L1: perujuk langsung mendapatkan `referrer_bonus` (konfigurasi: referral.referrer_bonus)
- L2: perujuk dari perujuk mendapatkan `commission = referrer_bonus * level2_rate` (konfigurasi: referral.level2_rate, default 5%)
- Catat `game_referral_commission` (level/commission_rate/commission_amount)

### 8. Strategi Rate Limit (Diperbarui)

| Antarmuka | Batas |
|------|------|
| POST /api/tournament/{id}/join | 10 kali/menit |
