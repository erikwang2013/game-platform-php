# Dokumen Fitur
<!-- lang-nav -->

Languages: [中文](FEATURES.md) · [English](FEATURES.en.md) · [한국어](FEATURES.ko.md) · [Русский](FEATURES.ru.md) · [Deutsch](FEATURES.de.md) · [Français](FEATURES.fr.md) · [Español](FEATURES.es.md) · [Português](FEATURES.pt.md) · [हिन्दी](FEATURES.hi.md) · [العربية](FEATURES.ar.md) · [বাংলা](FEATURES.bn.md) · **Bahasa Indonesia** · [日本語](FEATURES.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Ringkasan Fitur

### Versi Dasar (MVP) — Selesai

| Domain | Fitur | Status |
|----|------|------|
| Pengguna | Registrasi/login/JWT/CAPTCHA | Selesai |
| Dompet | Saldo koin platform/kueri transaksi | Selesai |
| Deposit | Buat pesanan deposit (Stripe 125+ pembayaran lokal, termasuk Alipay/WeChat Pay APM / NOWPayments USDT TRC20·ERC20 / Coinbase USDC·BTC·ETH / callback PayPal) | Selesai |
| Penukaran | Koin platform⇄koin game (kurs tetap + selisih) | Selesai |
| Penarikan | Ajukan/kueri/saklar global/review otomatis/review manual | Selesai |
| Game | CRUD backend/manajemen mata uang/daftar sisi C/detail/launch | Selesai |
| Manajemen | Manajemen game/review penarikan/manajemen pengguna/manajemen pembayaran/manajemen pengumuman | Selesai |
| Panel | Dasbor platform (DAU/transaksi/pendapatan/peringkat) | Selesai |
| Ekspor | Ekspor Excel pengguna/transaksi/penarikan | Selesai |
| Internasionalisasi | Peralihan 中/Inggris, tabel terjemahan, middleware deteksi bahasa | Selesai |
| Frontend | Backend administrasi Flutter PC + platform pengguna sisi C (termasuk i18n) | Selesai |

### Versi Standar — Selesai

| Domain | Fitur | Status |
|----|------|------|
| Pengguna | Login OAuth (Google/Facebook/Apple/Twitter/Microsoft/LinkedIn/GitHub) | Selesai |
| Pembayaran | Callback otomatis banyak saluran pembayaran (Stripe termasuk Alipay/WeChat Pay APM / PayPal / NOWPayments IPN / Coinbase Webhook) | Selesai |
| Game | Manajemen server, pelacakan catatan game | Selesai |
| Penarikan | Batas bertingkat KYC (default/verified/vip) + biaya | Selesai |
| KYC | Aplikasi verifikasi nama asli + review | Selesai |
| Kontrol risiko | Daftar hitam IP/peringatan jumlah besar/frekuensi/deteksi kecepatan | Selesai |
| Statistik | Snapshot statistik harian (pengguna/deposit/penarikan/penukaran/game) | Selesai |
| Frontend | Admin: review KYC + log kontrol risiko / Platform: OAuth+KYC+catatan game | Selesai |

### Versi Lengkap — Selesai

| Domain | Fitur | Status |
|----|------|------|
| Lobi game | 10 kategori prasetel, filter kategori, relasi game-kategori | Selesai |
| Papan peringkat | Peringkat harian/mingguan/bulanan/total, cache Redis, banyak metrik | Selesai |
| Kupon | Diskon jumlah tetap + rasio, batas waktu dan jumlah, pelacakan pengambilan/penggunaan | Selesai |
| Konfigurasi negara | 8 negara prasetel, metode pembayaran/penarikan terdiferensiasi, deposit minimum | Selesai |
| Statistik | Snapshot statistik harian + pelacakan pendapatan platform | Selesai |
| Pencarian | Pencarian full-text Elasticsearch (terintegrasi di lapisan model) | Selesai |

### Upgrade Tingkat Produksi — Selesai

| Domain | Fitur | Status |
|----|------|------|
| OAuth | Pertukaran token nyata Google/Facebook/Apple | Selesai |
| Pembayaran | Verifikasi tanda tangan callback (Webhook Stripe termasuk Alipay/WeChat Pay APM, Webhook PayPal, NOWPayments IPN HMAC-SHA512, Coinbase HMAC-SHA256 secret base64) | Selesai |
| CAPTCHA | CAPTCHA klik poster-php | Selesai |
| Notifikasi | Pesan dalam situs + email, notifikasi otomatis deposit/penarikan/KYC/kupon | Selesai |
| 2FA | Google Authenticator TOTP + kode pemulihan cadangan | Selesai |
| Referral | Kode referral, hadiah pendaftaran, komisi deposit | Selesai |
| Pencarian | API pencarian ES + saran game + fallback LIKE | Selesai |
| Papan peringkat | Push real-time WebSocket (port 8789) | Selesai |
| CDN | Integrasi lima penyedia (Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS upload + purge + preload) | Selesai |
| Manajemen CDN | Konfigurasi lima penyedia di admin (kredensial terenkripsi/aktif-nonaktif/tes konektivitas HeadBucket), service hanya membaca dari DB | Selesai |
| Laporan | Laporan data admin (ringkasan/harian/ekspor CSV, cache Redis 5 menit, rentang ≤90 hari) | Selesai |
| Statistik platform | Statistik beranda sisi C (total game/pengguna/permainan hari ini/aktif 7 hari) | Selesai |
| Deployment | Docker Compose 7 layanan + reverse proxy Nginx | Selesai |
| Data | Analisis agregasi real-time MySQL + perhitungan probabilitas gabungan/bersyarat | Selesai |
| HarmonyOS | admin 8 halaman; sisi C `apps/harmonyos/` sudah mengimplementasikan login/lobi/detail/dompet/profil (menunjuk 8788) | Sebagian selesai (proyek dapat berjalan, perangkat nyata perlu ubah IP) |
| Dokumentasi API | Dokumentasi interaktif hg/apidoc | Selesai |
| Instal satu klik | Wizard instalasi browser: buat admin, upgrade DB lama, install.lock mencegah instal ulang | Selesai |
| Toleransi kegagalan | CircuitBreaker + Retry + saklar degradasi feature.provider_mock | Selesai |
| Metode pembayaran | CRUD admin + visibilitas negara + rentang jumlah + batasan mata uang | Selesai |
| CI | tag kenaikan otomatis saat push + GitHub Release | Selesai |

### Perluasan Ekosistem (v2.0) — Baru Selesai

| Domain | Fitur | Status |
|----|------|------|
| Integrasi game | Lapisan abstraksi GameProvider (Self/ThirdParty) + tanda tangan HMAC-SHA256 | Selesai |
| Callback game | Gateway API Provider (balance/bet/settle/refund) + middleware ProviderAuth | Selesai |
| Sesi game | Heartbeat Redis + timeout 15 menit penyelesaian otomatis + GameSessionService | Selesai |
| Sistem tiket | Buat/balas sisi C + penanganan/penugasan/penutupan sisi admin, 5 tipe tiket | Selesai |
| Verifikasi email | Kode 6 digit, kedaluwarsa Redis 10 menit, batas kirim ulang 60 detik | Selesai |
| Notifikasi push | PushService (FCM/APNs/推送 Huawei) + model DeviceToken | Selesai |
| Sistem VIP | 5 level (biasa/perak/emas/platina/berlian) + poin pengalaman + upgrade otomatis | Selesai |
| Hak VIP | Diskon penukaran 2-15%, keringanan biaya penarikan 10-100%, bonus kurs 0.1-1.0% | Selesai |
| Sistem pencapaian | 12 pencapaian bawaan; EventConsumer → deteksi berbasis event AchievementService dan pengalaman VIP | Selesai |
| Sistem teman | Ajukan/terima/tolak/hapus/cari, status pending/accepted/blocked | Selesai |
| Pesan pribadi/chat | Pesan pribadi REST + pesan real-time WebSocket (port 8790), hanya teman yang dapat mengirim | Selesai |
| Bus event | Redis Pub/Sub; emit + EventConsumer mengonsumsi pencapaian/Webhook + INCR metrics | Selesai |
| Fitur saklar | FeatureFlag berbasis DB; `inRollout`/`abTest` pengelompokan crc32 membaca `feature.{name}_percent` | Selesai |
| Analisis lanjutan | Retensi/D1-D30, funnel konversi, ARPU/ARPPU, metrik ekonomi mata uang game (agregasi real-time MySQL) | Selesai |
| Webhook | Manajemen langganan + pengiriman event Redis Pub/Sub, 7 event dapat dipilih | Selesai |
| Chat | Pesan pribadi REST + pesan real-time WebSocket (port 8791), hanya teman yang dapat mengirim | Selesai |
| Turnamen | Buat/list/detail/join, saklar FeatureFlag, papan peringkat, batas jumlah peserta | Selesai |
| Komisi bertingkat | Bagi hasil referral dua level, model ReferralCommission, rasio komisi dapat dikonfigurasi | Selesai |
| Kondisi kupon | Tiga kondisi pembatasan min_deposit/first_user_only/game_id | Selesai |
| Dokumentasi SDK | Dokumentasi integrasi Provider (contoh PHP/Go/Python + 4 endpoint API) | Selesai |
| Mini-game | Farm Match-3 P0 (mesin domain + desain 4 level, unit test TypeScript/Vite/Vitest) | Selesai |

## 2. Fitur Pengguna Sisi C

### 2.1 Perjalanan Pengguna

```
Daftar → Login → Verifikasi email/telepon → Jelajahi lobi game → Masuk detail game
                                           ↓
Lihat dompet ← Main game ← Tukar koin game (diskon VIP) ← Deposit koin platform
    ↓
Penarikan (keringanan biaya VIP) → review backend → dana masuk
    ↓
Sistem teman → chat pesan pribadi → kompetisi papan peringkat → pelacakan pencapaian
    ↓
Dukungan tiket
```

### 2.2 Antarmuka API

| Metode | Jalur | Keterangan | Autentikasi |
|------|------|------|------|
| POST | /api/auth/register | Registrasi pengguna | Tidak |
| POST | /api/auth/login | Login pengguna | Tidak |
| POST | /api/auth/refresh | Perbarui Token | Tidak |
| GET | /api/game/list | Daftar game | Tidak |
| GET | /api/game/detail/{id} | Detail game | Tidak |
| GET | /api/announcement/list | Daftar pengumuman | Tidak |
| GET | /api/wallet/info | Saldo dompet | Ya |
| GET | /api/wallet/transactions | Catatan transaksi | Ya |
| POST | /api/deposit/create | Buat pesanan deposit | Ya |
| GET | /api/payment/methods | Daftar metode pembayaran (dirutekan per negara) | Ya |
| POST | /api/exchange/quote | Kueri harga penukaran (diskon VIP) | Ya |
| POST | /api/exchange/buy | Beli koin game | Ya |
| POST | /api/exchange/sell | Jual koin game | Ya |
| POST | /api/withdraw/apply | Ajukan penarikan (keringanan VIP) | Ya |
| POST | /api/game/launch | Luncurkan game | Ya |
| GET | /api/game/play-logs | Catatan game | Ya |
| POST | /api/referral/apply | Gunakan kode referral | Ya |
| POST | /api/verify/send-email | Kirim kode verifikasi email | Ya |
| POST | /api/verify/confirm-email | Konfirmasi email | Ya |
| GET | /api/ticket/list | Daftar tiket | Ya |
| POST | /api/ticket/create | Buat tiket | Ya |
| POST | /api/ticket/{id}/reply | Balas tiket | Ya |

| GET | /api/platform/stats | Statistik Platform | Tidak |
## 3. Fitur Backend Administrasi

### 3.1 Antarmuka API (baru)

| Metode | Jalur | Keterangan |
|------|------|------|
| GET | /admin/dashboard/platform | Data dasbor platform |
| GET | /admin/analytics/overview | Ringkasan platform (agregasi real-time MySQL) |
| GET | /admin/analytics/game-ranking | Peringkat game |
| GET | /admin/analytics/dau-trend | Tren DAU |
| GET | /admin/analytics/hourly-trend | Tren per jam |
| GET | /admin/analytics/action-distribution | Distribusi perilaku |
| GET | /admin/analytics/revenue | Analisis pendapatan |
| GET | /admin/analytics/conversion | Rasio konversi game |
| GET | /admin/analytics/probability | Probabilitas gabungan/bersyarat |
| GET | /admin/analytics/retention | Analisis retensi D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Funnel konversi |
| GET | /admin/analytics/arpu | Tren ARPU/ARPPU |
| GET | /admin/analytics/economy | Metrik ekonomi mata uang game |
| GET | /admin/report/summary | Ringkasan laporan (pengguna baru/deposit/penarikan/penukaran/permainan) |
| GET | /admin/report/daily | Laporan harian (agregasi per hari, tanggal tanpa data diisi 0) |
| GET | /admin/report/export | Ekspor laporan harian CSV (UTF-8 BOM) |
| GET | /admin/game/list | Daftar game |
| POST | /admin/game/create | Buat game (termasuk provider_config) |
| PUT | /admin/game/{id} | Edit game |
| GET | /admin/withdraw/orders | Daftar pesanan penarikan |
| PUT | /admin/withdraw/review | Review penarikan |
| GET | /admin/ticket/list | Daftar tiket |
| GET | /admin/ticket/{id} | Detail tiket |
| POST | /admin/ticket/{id}/reply | Balas tiket |
| POST | /admin/ticket/{id}/close | Tutup tiket |
| POST | /admin/ticket/{id}/assign | Tetapkan penangan |

## 4. Provider API (callback pihak game)

| Metode | Jalur | Keterangan | Autentikasi |
|------|------|------|------|
| POST | /api/provider/balance | Kueri saldo pengguna | HMAC-SHA256 |
| POST | /api/provider/bet | Notifikasi taruhan | HMAC-SHA256 |
| POST | /api/provider/settle | Notifikasi penyelesaian | HMAC-SHA256 |
| POST | /api/provider/refund | Notifikasi pengembalian dana | HMAC-SHA256 |

Algoritma tanda tangan: `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)`
Header permintaan: `X-Game-Id` + `X-Timestamp` + `X-Signature`
Jendela waktu: 5 menit

## 5. Sistem VIP

| Level | EXP kumulatif | Diskon penukaran | Keringanan biaya penarikan | Bonus kurs |
|------|---------|---------|-------------|---------|
| Biasa | 0 | 0% | 0% | Dasar |
| Perak | 500 | 2% | 10% | +0.1% |
| Emas | 2,500 | 5% | 30% | +0.3% |
| Platina | 12,500 | 10% | 50% | +0.5% |
| Berlian | 62,500 | 15% | 100% | +1.0% |

### Perolehan Poin Pengalaman

| Perilaku | EXP |
|------|-----|
| Deposit 1 yuan | 10 |
| Login harian | 5 |
| Menyelesaikan KYC | 50 |
| Mengundang pengguna baru | 100 |
| Mencapai pencapaian | 10-100 |

## 6. Daftar Pencapaian

| Pencapaian | Kondisi | Poin |
|------|------|------|
| First Deposit | Deposit pertama | 20 |
| Century Club | Deposit kumulatif 100 | 50 |
| High Roller | Deposit kumulatif 1000 | 100 |
| Trader | Penukaran pertama | 20 |
| Day Trader | Penukaran kumulatif 100 kali | 100 |
| Explorer | Bermain 3 game | 30 |
| Adventurer | Bermain 5 game | 50 |
| Conqueror | Bermain 10 game | 100 |
| Weekly Warrior | Login 7 hari berturut-turut | 30 |
| Monthly Master | Login 30 hari berturut-turut | 100 |
| Connector | Mengundang 1 teman | 30 |
| Influencer | Mengundang 10 teman | 100 |

## 7. Daftar Tabel Database

### Baru di Perluasan Ekosistem (10 tabel)

| Nama tabel | Keterangan | Fitur kunci |
|------|------|---------|
| game_ticket | Tiket | indeks user_id+type+status, assigned_to |
| game_ticket_reply | Balasan tiket | indeks ticket_id, is_admin membedakan |
| game_device_token | Token perangkat | indeks unik user_id+platform+token |
| game_vip_level | Definisi level VIP | indeks unik level, benefits JSON |
| game_user_vip | Catatan VIP pengguna | indeks unik user_id, level+exp+total_exp |
| game_exp_log | Log poin pengalaman | indeks gabungan user_id+source |
| game_achievement | Definisi pencapaian | indeks unik key, condition_json JSON |
| game_user_achievement | Pencapaian pengguna | indeks unik user_id+achievement_id |
| game_friend | Relasi teman | indeks unik user_id+friend_id |
| game_message | Pesan pribadi | from_user_id+to_user_id / to_user_id+is_read |

### Perubahan Struktur Tabel

| Nama tabel | Perubahan |
|------|------|
| game_game | +provider_config (JSON) |
| game_game_play_log | +round_id, +bet_amount, +win_amount |

**Total: 43 tabel di install.sql** (10 tabel perluasan ekosistem di `install/`, tidak digabung ke install.sql). Model tidak dibagikan: admin 46 / service 44 masing-masing satu salinan.

## 8. Cakupan Pengujian

| File pengujian | Jumlah kasus | Cakupan |
|---------|--------|---------|
| PlatformTest | 56 | presisi bcmath/perhitungan penukaran/biaya penarikan/batas/kontrol risiko/kupon/KYC/i18n |
| BackendEnhancementTest | 23 | layanan enkripsi/Hashids/Snowflake |
| CaptchaTest | 7 | pembuatan/validasi CAPTCHA |
| EncryptionServiceTest | 6 | enkripsi/dekripsi AES/desensitisasi |
| EnvConfigTest | 4 | konfigurasi variabel lingkungan |
| HashidsServiceTest | 8 | roundtrip encode/decode ID |
| SnowflakeServiceTest | 6 | keunikan pembuatan ID |

**Total: admin ~132 kasus / 8 file; service 3 kasus (WebhookUrlSafety + EventBusMessageFormat). service belum termasuk dalam blokir kegagalan CI.**

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
