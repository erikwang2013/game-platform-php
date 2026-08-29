# Perbandingan Versi
<!-- lang-nav -->

Languages: **中文** · [English](VERSIONS.en.md) · [한국어](VERSIONS.ko.md) · [Русский](VERSIONS.ru.md) · [Deutsch](VERSIONS.de.md) · [Français](VERSIONS.fr.md) · [Español](VERSIONS.es.md) · [Português](VERSIONS.pt.md) · [हिन्दी](VERSIONS.hi.md) · [العربية](VERSIONS.ar.md) · [বাংলা](VERSIONS.bn.md) · [Bahasa Indonesia](VERSIONS.id.md) · [日本語](VERSIONS.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Ikhtisar

| | Versi Dasar (Lite) | Versi Standar (Standard) | Versi Lengkap (Full) |
|------|------|------|------|
| Tabel data (install.sql) | 19 | 29 | **43** (bukan 52 yang pernah ditulis dokumen) |
| Endpoint API | 38 | 54 | ~149 (admin+service, termasuk Webhook/Provider) |
| Controller backend | 14 | 22 | admin 32 + service 30 |
| Model data | Tidak dibagikan | Tidak dibagikan | **admin 46 / service 44 masing-masing satu, tanpa lapisan berbagi** |
| Service dibagikan | Tanpa lapisan berbagi | Tanpa lapisan berbagi | `packages/platform-common` paket berbagi tunggal |
| Halaman frontend Admin | 11 | 13 | 15 |
| Halaman frontend Platform | 8 | 10 | 10 |
| HarmonyOS (admin) | - | Login + dasbor | **8 halaman** `admin/apps/harmonyos/` |
| HarmonyOS (C-end) | - | - | **5 halaman** `apps/harmonyos/` (login/lobi game/detail/dompet/profil) |
| Layanan Docker | - | - | **7** (nginx/admin/service/leaderboard-ws/mysql/redis/elasticsearch) |
| Kasus pengujian | 60 | 60 | admin ~132; service 3 |

---

## Autentikasi Pengguna

| Fitur | Versi Dasar | Versi Standar | Versi Lengkap |
|------|--------|--------|--------|
| Registrasi/login nama pengguna & kata sandi | ✓ | ✓ | ✓ |
| JWT Token (2j+14h) | ✓ | ✓ | ✓ |
| CAPTCHA klik | stub | stub | ✓ poster-php |
| Kunci akun (5 kali/15 menit) | ✓ | ✓ | ✓ |
| Batas sesi (3 konkurensi) | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ 7 platform (termasuk X/MS/LinkedIn/GitHub) |
| 2FA TOTP autentikasi dua faktor | - | - | ✓ |
| Ekspor/hapus data GDPR | - | - | ✓ |

---

## Dompet & Dana

| Fitur | Versi Dasar | Versi Standar | Versi Lengkap |
|------|--------|--------|--------|
| Dompet koin platform | ✓ | ✓ | ✓ |
| Kunci optimis dompet | ✓ | ✓ | ✓ |
| Catatan transaksi | ✓ | ✓ | ✓ |
| Dompet koin game | ✓ | ✓ | ✓ |
| Pembuatan pesanan deposit (langsung mengisi checkout_url/expires_at) | ✓ | ✓ | ✓ |
| Callback deposit masuk otomatis | - | ✓ manual | ✓ verifikasi tanda tangan Stripe (incl. Alipay/WeChat Pay)/PayPal/NowPayments IPN/Coinbase webhook |
| Penukaran harga/kueri beli/jual | ✓ | ✓ | ✓ |
| Pendapatan selisih penukaran | ✓ | ✓ | ✓ |
| Aplikasi penarikan | ✓ | ✓ | ✓ |
| Saklar global penarikan | ✓ | ✓ | ✓ |
| Review penarikan | ✓ manual | ✓ manual | ✓ batch+manual |
| Limit bertingkat KYC | - | ✓ 3 level | ✓ |
| Biaya penarikan | - | - | ✓ |
| Kuitansi PDF | - | - | ✓ |

---

## Manajemen Game

| Fitur | Versi Dasar | Versi Standar | Versi Lengkap |
|------|--------|--------|--------|
| CRUD game | ✓ | ✓ | ✓ |
| Manajemen mata uang game | ✓ | ✓ | ✓ |
| Daftar/detail game C-end | ✓ | ✓ | ✓ |
| Peluncuran game | ✓ | ✓ | ✓ |
| Kategori game (10 kategori) | - | - | ✓ |
| Filter kategori | - | - | ✓ |
| Manajemen server game | - | ✓ | ✓ |
| Pelacakan catatan game | - | ✓ | ✓ |
| Pencarian full-text ES | - | - | ✓ |
| Saran pencarian | - | - | ✓ |
| SDK Provider game pihak ketiga | - | - | ✓ HMAC-SHA256 |

---

## Alat Operasional

| Fitur | Versi Dasar | Versi Standar | Versi Lengkap |
|------|--------|--------|--------|
| Manajemen pengumuman | ✓ | ✓ | ✓ |
| Dasbor | ✓ Backend Administrasi | ✓ Backend Administrasi | ✓ Admin+Platform |
| Ekspor Excel | ✓ | ✓ | ✓ |
| Ekspor PDF | ✓ | ✓ | ✓ |
| Grafik dasbor nyata | - | - | ✓ fl_chart |
| Sistem kupon | - | - | ✓ |
| Papan peringkat (harian/mingguan/bulanan/total) | - | - | ✓ cache Redis |
| Papan peringkat real-time WebSocket | - | - | ✓ port 8789 |
| Sistem notifikasi (pesan dalam situs+email) | - | - | ✓ |
| Komisi referral | - | - | ✓ |
| Snapshot statistik harian | - | ✓ | ✓ |
| Pelacakan pendapatan platform | - | - | ✓ |

---

## Keamanan & Kepatuhan

| Fitur | Versi Dasar | Versi Standar | Versi Lengkap |
|------|--------|--------|--------|
| Pertahanan berlapis 18 tingkat | ✓ | ✓ | ✓ |
| Kontrol izin RBAC | ✓ | ✓ | ✓ |
| Log audit operasi | ✓ | ✓ | ✓ |
| Deteksi sumber 8 platform | ✓ | ✓ | ✓ |
| Rate limit jendela geser Redis | ✓ | ✓ | ✓ |
| Verifikasi identitas KYC | - | ✓ | ✓ |
| Mesin kontrol risiko (4 aturan) | - | ✓ | ✓ |
| Verifikasi tanda tangan callback pembayaran | - | - | ✓ |

---

## Internasionalisasi

| Fitur | Versi Dasar | Versi Standar | Versi Lengkap |
|------|--------|--------|--------|
| Dukungan banyak bahasa | Cina/Inggris | 4 bahasa | 4 bahasa |
| Tabel terjemahan+cache | ✓ | ✓ | ✓ |
| Deteksi bahasa otomatis | ✓ | ✓ | ✓ |
| Konfigurasi diferensiasi negara | - | - | ✓ 8 negara |

---

## Deployment & Operasi

| Fitur | Versi Dasar | Versi Standar | Versi Lengkap |
|------|--------|--------|--------|
| Deployment mandiri webman | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ 7 layanan |
| Reverse proxy Nginx | - | - | ✓ |
| CDN | - | - | ✓ Integrasi 5 vendor + konfigurasi admin/aktif-nonaktif/tes konektivitas (kredensial terenkripsi, service hanya baca dari DB) |
| Tugas terjadwal Crontab | - | ✓ | ✓ |
| Monitoring Prometheus | ✓ | ✓ | ✓ `/metrics` gauge bisnis + counter event |
| Pemeriksaan kesehatan | ✓ | ✓ | ✓ |
| Dokumentasi online hg/apidoc | - | - | ✓ 41 controller |

---

## Klien

| Fitur | Versi Dasar | Versi Standar | Versi Lengkap |
|------|--------|--------|--------|
| Backend Administrasi Flutter Web PC | ✓ 5 halaman | ✓ 11 halaman | ✓ 15 halaman |
| Platform pengguna Flutter Web PC | ✓ 5 halaman | ✓ 8 halaman | ✓ 10 halaman |
| HarmonyOS admin | - | ✓ Login + dasbor | ✓ 8 halaman `admin/apps/harmonyos/` |
| HarmonyOS C-end | - | - | ✓ 5 halaman `apps/harmonyos/` |

---

## Tabel Database

### Versi Dasar (19 tabel)
```
Backend Administrasi (7):  game_admin_user, game_admin_role, game_admin_permission,
               game_admin_user_role, game_admin_role_permission,
               game_operation_log, game_system_config

Platform inti (12): game_user, game_user_wallet, game_user_game_wallet,
               game_game, game_game_currency, game_deposit_order,
               game_withdraw_order, game_exchange_record, game_transaction,
               game_payment_method, game_announcement, game-platform_config
```

### Versi Standar tambahan (10 tabel)
```
game_user_identity, game_user_oauth, game_user_payment_account,
game_user_session, game_game_server, game_game_play_log,
game_withdraw_limit, game_risk_rule, game_risk_log, game_stat_daily
```

### Versi Lengkap tambahan (13 tabel)
```
game_game_category, game_game_category_rel, game_leaderboard,
game_coupon, game_user_coupon, game_language, game_translation,
game_country_config, game-platform_revenue,
game_notification, game_referral, game_referral_reward, game_user_2fa
```

---

## Endpoint API

| Modul | Versi Dasar | Versi Standar | Versi Lengkap |
|------|--------|--------|--------|
| Autentikasi | 3 | 3 | 7 (+OAuth×2 +2FA×5) |
| Dompet | 2 | 2 | 3 (+callback deposit) |
| Penukaran | 4 | 4 | 4 |
| Penarikan | 2 | 2 | 8 (+batch+limit+review) |
| Game | 3 | 4 | 7 (+server+catatan+search) |
| Pengguna | 2 | 2 | 7 (+KYC+GDPR+privasi) |
| Backend Administrasi | 18 | 25 | 79 |
| Alat operasional | - | - | 30 (+papan peringkat+kupon+notifikasi+referral) |
| Internasionalisasi | 2 | 2 | 4 (+konfigurasi negara) |
| **Total** | **38** | **54** | **129** |

---

## Perluasan Ekosistem (v2.0) — Baru

| Fitur | Keterangan |
|------|------|
| Lapisan abstrak GameProvider | SelfProvider (transaksi DB) + ThirdPartyProvider (HTTP+signature) |
| Gateway API Provider | Callback balance/bet/settle/refund + middleware ProviderAuth |
| Sistem tiket | Buat/balas C-end + pemrosesan/penugasan/penutupan admin |
| Verifikasi email | Kode 6 digit, kedaluwarsa Redis 10 menit, batas kirim ulang 60 detik |
| Notifikasi push | PushService (FCM/APNs/push Huawei) |
| Sistem VIP | 5 level, akumulasi EXP, upgrade otomatis, diskon penukaran, keringanan penarikan, bonus kurs |
| Sistem pencapaian | 12 pencapaian bawaan, deteksi berbasis event, pelacakan progres |
| Sistem teman | Ajukan/terima/tolak/hapus/cari |
| Pesan pribadi/chat | REST + pesan real-time WebSocket (port 8790) |
| Bus event | Redis Pub/Sub; emit INCR `metrics:event_*`; proses konsumen `EventConsumer` sudah diwujudkan |
| Saklar fitur | FeatureFlag berbasis DB; `inRollout`/`abTest` membaca `feature.{name}_percent` |
| Webhook | - | - | ✓ 7 jenis event+penyampaian Pub/Sub |
| Chat | - | - | ✓ REST+WebSocket :8791 |
| Sistem turnamen | - | - | ✓ FeatureFlag+tournament |
| Kondisi kupon | - | - | ✓ min_deposit/first_user/game_id |
| Komisi bertingkat | - | - | ✓ bagi hasil dua level |
| Dokumentasi SDK | - | - | ✓ PHP/Go/Python |
| Analisis lanjutan | Retensi/D1-D30, funnel konversi, ARPU/ARPPU |

### Tabel data baru (10 tabel)
```
game_ticket, game_ticket_reply, game_device_token,
game_vip_level, game_user_vip, game_exp_log,
game_achievement, game_user_achievement,
game_friend, game_message
```

### Endpoint Provider API baru (4)
```
POST /api/provider/balance  — Kueri saldo
POST /api/provider/bet      — Notifikasi taruhan
POST /api/provider/settle   — Notifikasi settlement
POST /api/provider/refund   — Notifikasi refund
```

### Endpoint API C-end baru (8)
```
POST /api/verify/send-email    — Kirim kode verifikasi email
POST /api/verify/confirm-email — Konfirmasi email
GET  /api/ticket/list             — Daftar tiket
POST /api/ticket/create           — Buat tiket
GET  /api/ticket/{id}             — Detail tiket
POST /api/ticket/{id}/reply       — Balas tiket
GET  /api/user/vip-status         — Status VIP
GET  /api/user/achievements       — Daftar pencapaian
```

### Endpoint API Backend Administrasi baru (6)
```
GET  /admin/ticket/list          — Daftar tiket
GET  /admin/ticket/{id}          — Detail tiket
POST /admin/ticket/{id}/reply    — Balas tiket
POST /admin/ticket/{id}/close    — Tutup tiket
POST /admin/ticket/{id}/assign   — Tentukan penangan
GET  /admin/analytics/retention  — Analisis retensi
GET  /admin/analytics/funnel     — Funnel konversi
GET  /admin/analytics/arpu       — Tren ARPU
GET  /admin/analytics/economy    — Metrik ekonomi
```
