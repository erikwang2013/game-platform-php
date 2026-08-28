# 项目全面规划 (Project Plan)
<!-- lang-nav -->

Languages: **中文** · [English](PROJECT-PLAN.en.md) · [한국어](PROJECT-PLAN.ko.md) · [Русский](PROJECT-PLAN.ru.md) · [Deutsch](PROJECT-PLAN.de.md) · [Français](PROJECT-PLAN.fr.md) · [Español](PROJECT-PLAN.es.md) · [Português](PROJECT-PLAN.pt.md) · [हिन्दी](PROJECT-PLAN.hi.md) · [العربية](PROJECT-PLAN.ar.md) · [বাংলা](PROJECT-PLAN.bn.md) · [Bahasa Indonesia](PROJECT-PLAN.id.md) · [日本語](PROJECT-PLAN.ja.md)


> Tanggal dibuat: 2026-08-16 · Berdasarkan inventarisasi read-only tim 6 orang (researcher/architect/backend-dev/frontend-dev/tester/reviewer) + verifikasi empiris atas klaim kunci
> Mencakup: ringkasan status / masalah & risiko / peta jalan P0-P1-P2 / perbaikan dokumen / gerbang kualitas

---

## I. Status Proyek Saat Ini

**Platform agregasi game global** — PHP 8.3 + webman v2, monorepo dua aplikasi:
`admin/` (8787 Backend Administrasi) + `service/` (8788 C-side) + `apps/` (Flutter + HarmonyOS) + `install/` (wizard instalasi 43 tabel).

| Dimensi | Ukuran terukur |
|------|---------|
| Controller | admin 32 + service 30 = 62 |
| Endpoint API | ~149 (admin 103 / service 88, termasuk callback Webhook/Provider) |
| Model data | admin 46 / service 44, admin/service **disalin ganda** (tanpa lapisan berbagi) |
| Pengujian | 132 kasus / 8 file (proyek admin), proyek service **nol pengujian** |
| Versi | v1.1 (2026-08-07): plugin Redis, layanan analisis, degradasi Redis, perbaikan pengujian |

Kemampuan yang sudah diimplementasikan: JWT+RBAC, kunci optimis dompet, deposit (verifikasi tanda tangan Stripe/PayPal/NowPayments/Coinbase), selisih penukaran, review penarikan + pembayaran PayPal, CRUD game/gateway Provider (HMAC), kupon/VIP/pencapaian/tiket/komisi referral/2FA/sosial (teman/chat WS)/turnamen/Webhook/push (FCM/APNs/Huawei)/i18n dua bahasa.

---

## II. Masalah & Risiko (sudah diverifikasi langsung)

### CRITICAL — Keamanan Dana

| # | Masalah | Lokasi |
|---|------|------|
| C1 | `provider` callback pembayaran dikirim klien, selain stripe/paypal **sepenuhnya melewati verifikasi tanda tangan**, callback palsu langsung masuk saldo | service/.../PaymentController.php:36-42 |
| C2 | Verifikasi tanda tangan fail-open: `STRIPE_WEBHOOK_SECRET` tidak dikonfigurasi → `return true`; pengecualian apa pun di PayPal → `return true`. Rantai serangan: buat pesanan deposit sendiri→callback palsu→deposit tak terbatas | PaymentController.php:167, 225 |
| C3 | `JWT_SECRET_KEY` fallback ke kunci hardcode publik `open-admin-jwt-secret-change-in-production` saat tidak ada, produksi tanpa env dapat memalsukan Token admin | admin+service config/plugin/erikwang2013/jwt/jwt.php:12 |

### HIGH — Kebenaran/Konsistensi

| # | Masalah | Lokasi |
|---|------|------|
| H1 | Layanan analisis AnalyticsController 12 metode sudah diimplementasikan penuh tetapi **nol rute**, semua 404 dead code, namun VERSIONS.md mengklaim sudah dikirim | admin/config/route.php (0 tempat analytics) |
| H2 | Bus event putus: emit punya 4 panggilan (game.played/withdraw.completed/exchange.completed/referral.applied), `subscribe()` tidak ada proses yang terdaftar, event diterbitkan lalu hilang; mesin VIP/pencapaian/notifikasi semuanya menggantung | admin+service app/event/EventBus.php |
| H3 | common/ dan model/ salinan ganda dan sudah menyimpang (DepositLogService dua salinan isinya beda, User.php tidak konsisten), perbaikan satu titik jadi pekerjaan ganda. **common/service sudah diekstrak** `packages/platform-common` (erik/platform-common, common-php lama sudah digabung); model dan pembungkus app/common masih ganda | admin/common vs service/common → packages/platform-common |
| H4 | ~~C-end HarmonyOS `apps/harmonyos/` direktori kosong, 0 halaman vs klaim 5 halaman di VERSIONS.md~~ — sudah diwujudkan (2026-08-18: 5 halaman diimplementasikan di `apps/harmonyos/`) | apps/harmonyos/ |
| H5 | Callback Stripe tidak memvalidasi toleransi timestamp `t=` (bisa replay), dan jumlah masuk tidak diperiksa terhadap jumlah pembayaran aktual gateway | PaymentController.php:191-194 |
| H6 | Apple id_token hanya base64 decode payload, tidak verifikasi tanda tangan, tidak memeriksa aud/iss/exp, risiko kebingungan identitas lintas aplikasi | OAuthController.php:376-380 |

### MEDIUM — Keandalan/Defek Implementasi

| # | Masalah |
|---|------|
| M1 | Defek 2FA ganda: `/api/2fa/verify` publik tanpa penguncian percobaan per pengguna (oracle brute-force); TOTP memakai string Base32 langsung sebagai kunci HMAC (tanpa decode), tidak cocok dengan Authenticator → **2FA sebenarnya tidak bisa dipakai** |
| M2 | Review/pembayaran penarikan check-then-act tanpa pembaruan status atomik, konkurensi bisa membayar berulang; tanpa double review |
| M3 | URL callback Webhook hanya divalidasi filter_var, bisa menunjuk IP internal (SSRF), dispatch POST ke URL mana pun |
| M4 | Limit penarikan harian/bulanan "query dulu baru insert" tidak atomik, konkurensi bisa menembus limit |
| M5 | Redis fail-open tanpa abstraksi seragam: blacklist JWT logout tidak efektif, rate limit senyap tidak efektif; celah degradasi: PayoutService::getAccessToken, ChatWebSocket brpop, akses state OAuth |
| M6 | ClickHouse nol pemakaian: perhitungan probabilitas sebenarnya COUNT(DISTINCT) real-time MySQL + subquery JOIN, risiko O(n²) pada tabel besar; composer mengisi dependensi tanpa kemampuan |
| M7 | Antrean setengah jadi: admin/app/queue punya ComputeDailyStats + 3 tugas ES, tetapi webman/queue belum diinstal, process.php tidak terdaftar, semua tanpa pemanggil |
| M8 | Dead code: layanan Vip/Achievement/Notification/FeatureFlag nol pemanggil; DepositLogService::log() implementasi kosong; model Test sisa; algoritma retensi satu cohort kasar |

### LOW
- Penarikan tanpa paksa 2FA/KYC bisa langsung dibayar ke email PayPal mana pun; catatan review masuk ke teks notifikasi (permukaan XSS)
- Dokumen tidak sesuai kenyataan: install.sql 43 tabel vs dokumen pernah tulis 52; docker-compose 7 layanan vs FEATURES.md pernah tulis 8; "Shared Model 34" tidak benar (admin 46 / service 44 masing-masing satu, tanpa lapisan berbagi). CHANGELOG sudah dilengkapi, lihat `docs/CHANGELOG.md`.

### Item yang Lolos (audit keamanan konfirmasi tanpa masalah)
Kunci optimis dompet + pembaruan kondisi versi benar; callback idempoten `where status=pending` pembaruan kondisi benar; semua ORM tanpa gabungan SQL mentah; .env tidak masuk git; semua rute admin dipasang AdminAuth+RBAC default tolak; validasi state OAuth + konsumsi sekali benar.

> **Status perbaikan 2026-08-18**: C1/C2/C3/H1/H5/H6 sudah diperbaiki; H2 bus event: `process.php` sudah mendaftarkan `event-consumer` dan kelas konsumen `EventConsumer` sudah diwujudkan, emit punya konsumen; M1 Base32 + penguncian per pengguna sudah diperbaiki; M2 atomisasi status penarikan + double review opsional sudah dilakukan; M3 SSRF Webhook sudah diblokir; M4 kunci pengguna Redis saat aplikasi penarikan sudah dilakukan; M5 sebagian selesai (RateLimit fail-closed); P2-19 metrik bisnis + grayscale FeatureFlag sudah diwujudkan. Daftar masalah dipertahankan sebagai kesimpulan audit historis.

---

## III. Peta Jalan

### P0 — Keamanan Dana + Kebenaran (dikerjakan dulu, memblokir peluncuran)

1. **Callback pembayaran fail-closed**: daftar putih provider (hanya stripe/paypal/nowpayments/coinbase) + kunci hilang wajib 500 tolak + pengecualian PayPal wajib tolak (C1/C2) — ✅ Selesai (2026-08-18: daftar putih provider + validasi penyalahgunaan lintas saluran + validasi IP sumber opsional + transaksionalisasi pencatatan dana callback)
2. **Validasi startup JWT**: env tanpa `JWT_SECRET_KEY` tolak startup (C3) — ✅ Selesai (2026-08-18: JWT_SECRET_KEY hilang atau nilai default `open-admin-jwt-secret-change-in-production` tolak startup, admin/service konsisten)
3. **Pasang rute layanan analisis**: daftarkan 12 rute analytics + titik izin, perbaiki janji VERSIONS.md (H1) — ✅ Selesai (2026-08-18: admin/config/route.php mendaftarkan 12 rute `/admin/analytics/*`)
4. **Bus event tersambung**: daftarkan proses konsumen tetap atau ubah panggilan sinkron langsung; event masuk database + retry gagal (H2) — ✅ Selesai (2026-08-18: emit/consume sudah INCR hitungan Redis; `service/config/process.php` mendaftarkan `event-consumer`, `service/app/process/EventConsumer.php` mengonsumsi event)
5. **Verifikasi tanda tangan Apple id_token**: validasi JWKS + aud/iss/exp (H6) — ✅ Selesai (2026-08-18: RS256 JWKS + refresh kid + aud/iss/exp)
6. **Cegah replay & periksa jumlah Stripe**: toleransi timestamp + bandingkan dengan jumlah gateway (H5) — ✅ Selesai (2026-08-18: timestamp t= ±300s anti-replay + pemeriksaan jumlah presisi bccomp + secret/webhook_id tidak dikonfigurasi atau pengecualian verifikasi semua ditolak)

### P1 — Keandalan + Konsistensi

7. **Deduplikasi lapisan berbagi**: common/model diekstrak ke composer path repo (atau symlink), hilangkan penyimpangan ganda (H3) — 🔶 Sebagian selesai (2026-08-18: `common/service` sudah diekstrak ke satu `packages/platform-common` / `erik/platform-common` path repo (common-php lama sudah digabung), dirujuk admin+service; model dan pembungkus `app/common` terikat host masih ganda, lihat `packages/platform-common/DUAL_MODELS.md`)
8. **Pembungkus degradasi Redis seragam**: strategi fail dieksplisitkan + peringatan tidak senyap; lengkapi fallback PayoutService/OAuth/ChatWebSocket (M5) — 🔶 Sebagian selesai (RateLimit fail-closed sudah diwujudkan: saat Redis error limit tolak bukan lepas senyap; sisanya belum)
9. **Koneksi webman/queue**: menampung pengiriman event & webhook (retry konsumsi, dead letter), aktifkan atau hapus tugas ComputeDailyStats/ES (M7) — ⬜ Belum
10. **Perbaikan 2FA**: decode Base32 + verify tambah status login & penguncian percobaan per pengguna (M1) — ✅ Selesai (2026-08-18: HMAC setelah decode Base32 RFC 4648; `/api/2fa/verify` 5 kali gagal kunci 15 menit, Redis fail-closed)
11. **Atomisasi penarikan**: review/pembayaran pembaruan kondisi + double review; limit Redis Lua/constraint unik (M2/M4) — 🔶 Sebagian selesai (2026-08-18: pending→approved/rejected, approved→processing UPDATE bersyarat; double review opsional `withdraw.require_dual_review`; kunci pengguna Redis sisi aplikasi. Tanpa Lua limit/constraint unik)
12. **Blokir SSRF Webhook**: tolak alamat internal/cadangan (M3) — ✅ Selesai (2026-08-18: `isSafeWebhookUrl()` hanya https publik)
13. **ClickHouse pilih salah satu**: integrasi nyata atau cabut dependensi + revisi dokumen (M6) — ⬜ Belum
14. **Pembersihan dead code**: sambungkan atau hapus Vip/Achievement/Notification/FeatureFlag; hapus model Test; audit DepositLog masuk database (M8) — 🔶 Sebagian selesai (2026-08-18: model Test sudah dihapus, audit DepositLog masuk database; Vip/FeatureFlag/Notification sudah punya pemanggil; AchievementService sudah dipanggil EventConsumer)
15. **Pengujian service + gerbang CI**: pengujian integrasi verifikasi tanda tangan callback/alur penarikan/degradasi Redis/perhitungan probabilitas/konkurensi kunci optimis; phpunit gagal memblokir; service masuk CI (sekarang `|| echo warning` membiarkan gagal) — 🔶 Sebagian selesai (service sudah punya WebhookUrlSafety / EventBusMessageFormat; sudah masuk CI job `phpunit-service` gagal memblokir)

**Putaran ini (2026-08-18) tambahan selesai (di luar penomoran asli)**:
- **Perbaikan prefiks tabel**: 52 model menghapus prefiks `game_` hardcode, hilangkan prefiks ganda `game_game_`; prefiks DB seragam disediakan config/database.php `prefix=game_`, install.sql tidak perlu diubah
- **Penulisan ulang refresh token**: logika refresh token service AuthController ditulis ulang
- **Porting DepositLogService versi service**: service/common/service/DepositLogService.php dilengkapi (menghilangkan salah satu drift ganda admin/service)

### P2 — Observabilitas / Perluasan / Pengalaman

16. **C-end HarmonyOS** implementasikan 5 halaman dari nol (login/lobi/detail/dompet/profil) (H4) — ✅ Selesai (2026-08-18: `apps/harmonyos/entry/src/main/ets/pages/` 5 halaman ada di repositori)
17. **Pelengkapan frontend**: halaman verifikasi 2FA, entri kupon/papan peringkat/notifikasi, UI pencarian ES; gabungkan sumber rute main.dart/app_pages.dart; callback OAuth nyata; lapisan transmisi AES frontend
18. **Perhitungan probabilitas pindah ClickHouse** atau tabel statistik materialisasi MySQL + cache; retensi dihitung ulang dengan cohort nyata
19. **Metrik bisnis Prometheus** (tingkat pengiriman/konsumsi event, kedalaman antrean) + middleware AB split grayscale (pakai ulang FeatureFlag) — 🔶 Sebagian selesai (2026-08-18: `GET /metrics` penarikan menunggu review/deposit terkonfirmasi hari ini/hitungan emit·consume event; FeatureFlag `inRollout`/`abTest` bucket crc32. Kedalaman antrean belum)
20. **Lingkup tertutup rantai data WebSocket**: konfirmasi persistensi papan peringkat/chat
21. **Penyelarasan dokumen**: perbaikan deskripsi jumlah tabel/jumlah layanan/lapisan berbagi, penyelarasan dokumen API dengan implementasi, lengkapi CHANGELOG — ✅ Selesai (2026-08-18: lihat `docs/CHANGELOG.md`, FEATURES/VERSIONS/PROJECT-PLAN/laporan audit §10)

---

## IV. Gerbang Kualitas (kolaborasi tim)

- Setiap perubahan kode: pengujian penuh admin `vendor/bin/phpunit` wajib lolos (hapus `|| echo warning`)
- Jalur sensitif baru (pembayaran/penarikan/autentikasi) wajib disertai pengujian
- Mengubah common/model wajib sinkronisasi admin+service dua sisi (sebelum lapisan berbagi diwujudkan)
- Poin penting rekomendasi laporan review: tanda tangan ProviderAuth, enkripsi AES, SQL tulis tangan ProbabilityService

## V. Tim

Tim game-platform (6 anggota: researcher/architect/backend-dev/frontend-dev/tester/reviewer) sudah siap, dapat langsung mengeksekusi P0.
