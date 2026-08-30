# Changelog
<!-- lang-nav -->

Languages: [中文](CHANGELOG.md) · [English](CHANGELOG.en.md) · [한국어](CHANGELOG.ko.md) · [Русский](CHANGELOG.ru.md) · [Deutsch](CHANGELOG.de.md) · [Français](CHANGELOG.fr.md) · [Español](CHANGELOG.es.md) · [Português](CHANGELOG.pt.md) · [हिन्दी](CHANGELOG.hi.md) · [العربية](CHANGELOG.ar.md) · [বাংলা](CHANGELOG.bn.md) · **Bahasa Indonesia** · [日本語](CHANGELOG.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Catatan perubahan yang dapat dibaca manusia. PHP tidak meng-import file ini. Bersesuaian dengan P2-21 dari PROJECT-PLAN.

## [1.1] — 2026-08-07

- Integrasi plugin Redis, layanan analisis, penurunan kapasitas Redis, perbaikan pengujian.

## [1.1] security / ops — 2026-08-18

### Keamanan

- Callback pembayaran: daftar putih provider (stripe/paypal), verifikasi tanda tangan fail-closed, pemeriksaan jumlah, pencatatan dana transaksional, timestamp Stripe ±300s cegah replay.
- JWT: tolak startup saat `JWT_SECRET_KEY` / `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY` hilang atau nilai default.
- Apple id_token: verifikasi tanda tangan JWKS (RS256) + aud/iss/exp.
- Webhook: hanya URL https publik, tolak alamat internal/tercadang (SSRF).
- 2FA: kunci TOTP HMAC menggunakan hasil decode Base32 RFC 4648; `/api/2fa/verify` kunci per pengguna setelah gagal (5 kali / 15 menit, fail-closed saat Redis error).
- Penarikan: update status atomik dengan kondisi UPDATE untuk review/pembayaran; opsional double review (`withdraw.require_dual_review`); kunci pengguna Redis di sisi aplikasi mencegah pelanggaran batas secara konkuren.
- Rate limit: fail-closed saat Redis error.

### Ketersediaan

- 12 rute `/admin/analytics/*` layanan analisis admin terpasang.
- Model menghapus prefiks `game_` yang di-hardcode; DepositLog audit masuk database; model Test dihapus.

### Observabilitas

- `GET /metrics` menambah penarikan menunggu review, deposit terkonfirmasi hari ini (kueri COUNT cache Redis 30s), hitungan emit/consume event, `memory_usage`, `info version=1.1`.
- FeatureFlag: `inRollout` / `abTest` mengelompokkan berdasarkan crc32 untuk membaca `feature.{name}_percent`.
- EventBus `emit` / `consume` melakukan INCR pada `metrics:event_emit_total` / `metrics:event_consume_total` di Redis.

### Klien / Bersama (dilengkapi hari yang sama)

- Flutter Platform: tabel rute `app_pages.dart`; tambah halaman pengaturan/verifikasi 2FA, kupon, papan peringkat, notifikasi, callback OAuth; pintu masuk lobi sudah terpasang navigasi.
- Sisi C HarmonyOS: `apps/harmonyos/` lima halaman (login/lobi/detail/dompet/profil), `BASE_URL` default menunjuk ke service `8788`.
- Lapisan bersama: `packages/platform-common` (path repo `erik/platform-common`) mengekstrak DepositLog / GameDashboard / Probability / GamePlayLog; model masih ganda.
- ClickHouse: dependensi composer sudah dilepas; analisis tetap agregasi real-time MySQL.
- CI: admin / service menjalankan phpunit di job terpisah, gagal langsung memblokir.

### Kesenjangan yang Masih Ada

- Model admin/service **masih ganda** (hanya sebagian `common/service` yang masuk paket path).
- `webman/queue` belum tersambung; probabilitas/retensi belum dimigrasi ke OLAP.
- Sebagian paragraf PROJECT-PLAN / VERSIONS / laporan audit mungkin masih tertinggal dari CHANGELOG ini; yang berlaku adalah file ini dan kondisi disk.

## [1.1] resilience — 2026-08-27

### Stabilitas

- Lapisan bersama: ditambahkan `CircuitBreaker` (status di Redis, ambang 5 / jendela 30 detik, fail-open jika Redis tidak tersedia) dan `Retry` (backoff eksponensial, hanya pengecualian jaringan, maks. 5 percobaan), di `packages/platform-common/src/`.
- Sakelar degradasi `feature.provider_mock`: PushService (FCM/APNs/HarmonyOS), PayoutService (PayPal), ThirdPartyProvider short-circuit saat `on`, tanpa panggilan jaringan nyata.
- Memperbaiki 11 cacat tipe `getenv($name, '')` (TypeError pada strict_types); pemeriksaan mock PushService dipindah ke try/catch.
- Tes baru: CircuitBreakerTest / RetryTest / ResilienceMockTest; rangkaian service 45 → 60 kasus, semua hijau (laporan: [test-reports/resilience.md](test-reports/resilience.md)).

## [1.1] payments — 2026-08-29

- Multi-payment gateway: Stripe Checkout / NOWPayments (USDT TRC20+ERC20) / Coinbase Commerce (USDC) + Alipay/WeChat Pay (Stripe Checkout APM).
- CRUD metode pembayaran di admin + visibilitas negara + rentang nominal; pesanan top-up langsung mengisi checkout_url / expires_at.
- Migrasi baru install/migrations/2026_08_29_multi_payment.sql (harus dijalankan).

## [1.1] cdn — 2026-08-29

- Integrasi CDN lima penyedia (Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS upload + purge + preload) + konfigurasi admin (tabel game_cdn_provider CRUD/aktif-nonaktif/tes konektivitas via HeadBucket), service hanya membaca dari DB (config/cdn.php dihapus).

## [1.1] features — 2026-08-29

- Mini-game Farm Match-3 P0: mesin domain + desain 4 level + unit test Vitest (`game/xiaoxiaole/`).
- Wizard instalasi satu klik: buat admin di browser, upgrade DB yang ada (memperbaiki HY093 ketidakcocokan parameter terikat, Unknown column 'countries'), install.lock mencegah instal ulang.
- CI: tag inkremental otomatis saat push + publikasi GitHub Release.
- Infrastruktur: database diubah nama menjadi game-platform, prefiks tabel `game_` diseragamkan.
- Sinkronisasi dokumen: FEATURES.md dilengkapi di 13 bahasa untuk resiliency (saklar circuit-breaker/retry/degradation), CRUD metode pembayaran, mini-game, instalasi satu klik, baris CI (sesuai entri [1.1] resilience / payments di atas).

## [1.1] reports — 2026-08-31

- Laporan data: admin `/admin/report/summary|daily|export` (ringkasan/harian/ekspor CSV, cache Redis 5 menit, rentang ≤90 hari).
- Statistik platform (sisi C): `GET /api/platform/stats` (total game/pengguna/permainan hari ini/aktif 7 hari), terhubung ke tampilan beranda.
- Flutter admin: kartu statistik dasbor terhubung ke data nyata, halaman laporan baru ReportsPage (/reports).
- Sinkronisasi dokumen: FEATURES/VERSIONS/API dilengkapi entri laporan dan statistik dalam 13 bahasa, kotak analisis statistik pada diagram fitur diperbarui.
