# Dokumen Desain Fitur
<!-- lang-nav -->

Languages: [中文](FEATURE-DESIGN.md) · [English](FEATURE-DESIGN.en.md) · [한국어](FEATURE-DESIGN.ko.md) · [Русский](FEATURE-DESIGN.ru.md) · [Deutsch](FEATURE-DESIGN.de.md) · [Français](FEATURE-DESIGN.fr.md) · [Español](FEATURE-DESIGN.es.md) · [Português](FEATURE-DESIGN.pt.md) · [हिन्दी](FEATURE-DESIGN.hi.md) · [العربية](FEATURE-DESIGN.ar.md) · [বাংলা](FEATURE-DESIGN.bn.md) · **Bahasa Indonesia** · [日本語](FEATURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Desain Sistem Mata Uang

### 1.1 Model Mata Uang Tiga Lapis

```
Lapis 1: Fiat (USD / CNY / EUR / JPY ...)
       ↕ Deposit/Penarikan (penukaran sesuai kurs)
Lapis 2: Koin platform (terpadu, presisi decimal(18,4))
       ↕ Penukaran (termasuk kurs + selisih komisi platform)
Lapis 3: Koin game (independen per game, kurs independen)
```

### 1.2 Koin Platform

- Unit harga terpadu di dalam platform
- Presisi: `DECIMAL(18,4)`, unit terkecil 0.0001
- Diperoleh melalui deposit fiat, dapat ditukarkan ke koin game mana pun
- Koin game juga dapat ditukar kembali ke koin platform, lalu ditarik sebagai fiat
- Platform mengenakan selisih penukaran sebagai sumber pendapatan

### 1.3 Koin Game

- Setiap game dapat memiliki beberapa mata uang game (seperti koin emas, berlian, poin)
- Setiap mata uang mengatur kurs penukaran ke koin platform secara independen (`exchange_rate`)
- Setiap mata uang mengatur rasio komisi platform secara independen (`spread_pct`)
- Mendukung pengaturan batas penukaran minimum/maksimum (`min_exchange` / `max_exchange`)

### 1.4 Rumus Penukaran

**Membeli koin game (koin platform → koin game):**
```
Koin game masuk = jumlah koin platform × exchange_rate × (1 - spread_pct / 100)
```

**Menjual koin game (koin game → koin platform):**
```
Koin platform masuk = jumlah koin game ÷ exchange_rate × (1 - spread_pct / 100)
```

**Contoh:**
- exchange_rate = 100 (1 koin platform = 100 koin game)
- spread_pct = 5% (platform memotong selisih 5%)
- Pengguna membeli dengan 10 koin platform: (10 × 100 × 0.95) = 950 koin game
- Pengguna menjual 950 koin game: (950 ÷ 100 × 0.95) = 9.025 koin platform
- Pendapatan platform: 10 - 9.025 = 0.975 koin platform

## 2. Desain Dompet

### 2.1 Dompet Koin Platform (game_user_wallet)

Dibuat otomatis saat pengguna mendaftar, saldo awal 0.

| Kolom | Keterangan |
|------|------|
| balance | Saldo tersedia (dapat deposit/penarikan/penukaran) |
| frozen_balance | Saldo beku (cadangan, seperti saat penarikan berlangsung) |
| total_earned | Pendapatan kumulatif |
| total_spent | Pengeluaran kumulatif |
| version | Nomor versi kunci optimis (setiap pembaruan +1) |

### 2.2 Dompet Koin Game (game_user_game_wallet)

Unik berdasarkan tiga dimensi pengguna+game+mata uang. Dibuat otomatis saat penukaran pertama.

### 2.3 Keamanan Konkurensi

Menggunakan kunci optimis untuk mencegah masalah konkurensi:

```php
// Periksa nomor versi saat memperbarui
$updated = Wallet::where('id', $wallet->id)
    ->where('version', $wallet->version)
    ->update([
        'balance' => bcadd($wallet->balance, $amount, 4),
        'version' => $wallet->version + 1,
    ]);

// Pembaruan gagal (nomor versi berubah) → coba ulang, maksimal 5 kali
```

## 3. Desain Sistem Penarikan

### 3.1 Kontrol Multi-Lapis

```
Lapis 1: Saklar penarikan global
       ├─ mati → semua penarikan ditolak, untuk kontrol risiko darurat
       └─ nyala → masuk pemeriksaan lapis 2

Lapis 2: Pemeriksaan batas
       ├─ Jumlah minimum per transaksi (min_amount)
       ├─ Jumlah maksimum per transaksi (max_amount)
       └─ Batas kumulatif harian (daily_limit)

Lapis 3: Alur review
       ├─ Jumlah < ambang review otomatis → lolos otomatis
       └─ Jumlah >= ambang review otomatis → review manual → lolos/tolak
```

### 3.2 State Machine Penarikan

```
pending (menunggu review)
  ├─→ approved (telah lolos) → completed (selesai)
  └─→ rejected (ditolak) → saldo dikembalikan + transaksi pengembalian
```

### 3.3 Kontrol Backend Administrasi

- **Tombol saklar global**: nyalakan/matikan penarikan semua pengguna dengan satu klik
- **Antrean review**: daftar menunggu review yang diurutkan berdasarkan waktu, tombol lolos/tolak
- **Konfigurasi batas**: atur parameter batas secara visual

## 4. Desain Deposit

### 4.1 Alur Deposit

```
1. Pengguna memilih metode pembayaran dan jumlah
2. Platform membuat pesanan deposit (status=pending, generate order_no unik)
3. Lompat ke halaman pembayaran pihak ketiga
4. Pengguna menyelesaikan pembayaran
5. Callback pihak ketiga memberitahu platform (POST /api/payment/callback)
6. Platform verifikasi tanda tangan → perbarui pesanan (status=confirmed)
7. Koin platform masuk → catat transaksi
```

### 4.2 Metode Pembayaran

| Tipe | Penyedia | Keterangan |
|------|--------|------|
| Fiat | Stripe | Pembayaran kartu kredit internasional |
| Fiat | PayPal | Dompet elektronik global |
| Fiat | Alipay | Alipay (China daratan) |
| Fiat | WeChat Pay | WeChat Pay (China daratan) |
| Kripto | USDT-TRC20 | USDT jaringan Tron |

Versi dasar mengintegrasikan satu metode pembayaran terlebih dahulu (seperti Stripe), versi standar memperluas semua saluran.

## 5. Desain Integrasi Game

### 5.1 Game Buatan Sendiri

Game buatan sendiri terintegrasi langsung ke platform, berbagi sistem pengguna dan dompet:

- Game meminta saldo koin game pengguna melalui API internal
- Penyelesaian game mengurangi/menambah koin game melalui API internal
- Tidak perlu verifikasi tanda tangan tambahan

### 5.2 Game Pihak Ketiga

Game pihak ketiga terhubung melalui SDK/API:

```
Sisi platform:
  1. Pengguna mengklik "masuk game"
  2. Platform membuat tanda tangan (user_id + timestamp + api_secret → HMAC-SHA256)
  3. 302 redirect atau iframe memuat URL game (membawa parameter tanda tangan)

Sisi game:
  4. Verifikasi tanda tangan → buat sesi game
  5. Kueri saldo: GET /api/game/balance?user_id=...&sign=...
  6. Callback penyelesaian: POST /api/game/callback {user_id, amount, type, sign}
  7. Platform verifikasi tanda tangan → perbarui saldo → catat transaksi → kembalikan hasil
```

### 5.3 Algoritma Tanda Tangan

```
signature = HMAC-SHA256(
    secret = api_secret,
    message = user_id + "|" + timestamp + "|" + amount + "|" + nonce,
)
```

Kondisi verifikasi:
- Tanda tangan benar
- Timestamp dalam ±60 detik (cegah replay attack)
- nonce belum pernah digunakan (dicatat di Redis, kedaluwarsa 60 detik)
- IP permintaan dalam daftar putih

## 6. Desain Izin

### 6.1 Prasetel Peran

| Peran | Ruang lingkup izin |
|------|---------|
| Super admin | * (semua izin) |
| Operasi game | Manajemen game, manajemen pengumuman, dasbor |
| Review keuangan | Review penarikan, manajemen pembayaran, lihat transaksi |
| Layanan pelanggan | Lihat pengguna sisi C, lihat pesanan deposit |

### 6.2 Granularitas Izin

```
{method}.{path}

Contoh:
  get.admin/game/list      → lihat daftar game
  post.admin/game/create   → buat game
  put.admin/withdraw/review → review penarikan
  put.admin/withdraw/switch → operasikan saklar penarikan (hanya super admin)
```

## 呼. Desain Baru Versi Standar

### 8.1 Mesin Kontrol Risiko

Empat tipe aturan:
- `ip_blacklist` — pencocokan daftar hitam IP, terkena langsung diblokir
- `amount_anomaly` — deteksi jumlah besar per transaksi, beri peringatan saat melebihi ambang
- `frequency` — deteksi frekuensi operasi dalam jendela waktu
- `velocity` — deteksi asosiasi banyak akun dalam waktu singkat

Aturan dieksekusi urut berdasarkan priority menurun, aturan pertama yang cocok menentukan hasil (block > warn > log).

### 8.2 Login Pihak Ketiga OAuth

Penyedia yang didukung: Google, Facebook, Apple

Alur:
1. Frontend meminta `GET /api/auth/oauth/{provider}` untuk mendapatkan URL otorisasi
2. Pengguna lompat ke pihak ketiga menyelesaikan otorisasi
3. Callback `POST /api/auth/oauth/{provider}/callback` membawa kode otorisasi
4. Backend mencari tautan yang ada → langsung login; tanpa tautan → otomatis daftar+tautkan+buat dompet

### 8.3 Sistem Batas KYC

| Level | Cara didapat | Batas per transaksi | Batas harian | Biaya |
|------|---------|---------|--------|--------|
| default | Default saat daftar | 1,000 | 10,000 | 1.00% |
| verified | Lolos review KYC | 5,000 | 50,000 | 0.50% |
| vip | Diberikan operasional | 20,000 | 200,000 | 0.00% |

### 8.4 Server Game

Setiap game dapat mengonfigurasi beberapa server (region: global/asia/eu/na), status server: pemeliharaan/normal/populer/server baru.

### 8.5 Snapshot Statistik Harian

Crontab dini hari setiap hari menjalankan `ComputeDailyStats::run()`, menghitung lima metrik:
- Statistik pengguna (baru/aktif/kumulatif)
- Statistik deposit (jumlah transaksi/total jumlah)
- Statistik penarikan (jumlah transaksi/total jumlah)
- Statistik penukaran (jumlah transaksi/total biaya)
- Statistik game (jumlah pemain/jumlah sesi)

## 9. Fitur Tingkat Produksi

### 9.1 Sistem Notifikasi

Tipe notifikasi: system/deposit/withdraw/kyc/coupon/announcement

Skenario pemicu otomatis:
- Deposit masuk → NotificationService::send()
- Review penarikan lolos/ditolak → notifikasi otomatis
- Review KYC lolos/ditolak → notifikasi otomatis
- Pengambilan kupon → notifikasi otomatis
- Hadiah referral masuk → notifikasi otomatis

Mendukung dua saluran pesan dalam situs + email (email perlu konfigurasi variabel lingkungan MAIL_HOST).

### 9.2 Komisi Referral

```
Pengguna A membuat kode referral → bagikan ke pengguna B
Pengguna B mengisi kode referral saat daftar → keduanya mendapat hadiah pendaftaran (signup_reward)
Pengguna B deposit → A mendapat komisi deposit (deposit_commission_pct%)
```

### 9.3 Autentikasi Dua Faktor 2FA

- Protokol standar TOTP (RFC 6238), kompatibel dengan Google Authenticator
- Alur aktivasi: dapatkan kunci → pindai kode QR untuk tautkan → verifikasi TOTP → buat 8 kode pemulihan cadangan
- Verifikasi kedua saat login: POST /api/2fa/verify
- Mendukung toleransi jendela waktu ±1 (30 detik)

### 9.4 Integrasi OAuth Nyata

| Penyedia | Endpoint Token | Endpoint Info Pengguna |
|--------|----------|------------|
| Google | oauth2.googleapis.com/token | www.googleapis.com/oauth2/v3/userinfo |
| Facebook | graph.facebook.com/v18.0/oauth/access_token | graph.facebook.com/me |
| Apple | appleid.apple.com/auth/token | decode JWT id_token |

Konfigurasi melalui PlatformConfig atau variabel lingkungan, saat permintaan gagal otomatis fallback ke mode mock.

### 9.5 Verifikasi Tanda Tangan Webhook Pembayaran

- Stripe: verifikasi tanda tangan HMAC-SHA256 (header Stripe-Signature)
- PayPal: POST kembali ke endpoint verifikasi PayPal
- Saat kunci belum dikonfigurasi, verifikasi dilewati otomatis (mode pengembangan)

### 9.6 Papan Peringkat Real-time WebSocket

- Protokol: WebSocket (ws://host:8789)
- Langganan: {action: "subscribe", leaderboard_id: 123}
- Push: {type: "ranking_update", rankings: [...]}
- Mendukung ping/pong heartbeat untuk menjaga koneksi

## 7. Desain Internasionalisasi

### 7.1 Bahasa yang Didukung

| Kode | Nama | Bahasa asli | Ikon |
|------|------|--------|------|
| en-US | English | English | us |
| zh-CN | Chinese (Simplified) | 简体中文 | cn |
| ja-JP | Japanese | 日本語 | jp |
| ko-KR | Korean | 한국어 | kr |

### 7.2 Manajemen Terjemahan

- Terjemahan diorganisir dalam format `group.key` (seperti `auth.login_success`)
- Disimpan di tabel database `game_translation`, cache Redis (TTL 1 jam)
- API: `GET /api/language/list` mendapatkan bahasa tersedia, `POST /api/language/switch` mengganti bahasa
- Frontend mendeteksi otomatis melalui header `X-Language` atau `Accept-Language`
- Saat terjemahan tidak ada, fallback ke en-US; en-US juga tidak ada, kembalikan key asli

### 7.3 Preferensi Bahasa Pengguna

- Saat pengguna mendaftar, diatur otomatis sesuai `Accept-Language` browser
- Setelah login dapat mengubah kolom `language` melalui `PUT /api/user/profile`
- Saat mengganti bahasa, catatan pengguna disinkronkan

## 8. Model Pendapatan Platform

| Sumber pendapatan | Cara perhitungan | Keterangan |
|---------|---------|------|
| Selisih penukaran | spread_fee setiap penukaran | Dipungut dua arah saat beli dan jual |
| Biaya penarikan | jumlah penarikan × fee_pct | Diimplementasikan di versi standar |
| Bagi hasil game | bagi hasil pendapatan game pihak ketiga | Sesuai perjanjian kontrak |
| Selisih kurs deposit | selisih kurs fiat→koin platform | Selisih antara kurs yang ditetapkan platform dan kurs pasar |
