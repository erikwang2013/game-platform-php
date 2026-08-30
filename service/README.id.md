# service/ — Layanan API Platform Pengguna (Sisi C)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · **Bahasa Indonesia** · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Layanan API platform pengguna (sisi C) adalah backend PHP berkinerja tinggi berbasis webman v2 (Workerman) yang memberikan kepada pengguna seluruh kemampuan platform agregasi game: registrasi dan login, dompet, deposit, penarikan, penukaran, game, papan peringkat, kupon, tiket dukungan, VIP, pencapaian, fitur sosial, dan pengumuman.

## Daftar Fitur

| Modul | Deskripsi |
|------|------|
| Pengguna | Registrasi/login (nama pengguna+kata sandi + OAuth 7 platform + 2FA TOTP), profil |
| Dompet | Dompet koin platform (kunci optimistis) + dompet koin game + riwayat transaksi |
| Deposit | 13 gateway pembayaran (Stripe/PayPal/NowPayments/Coinbase, dll.) verifikasi tanda tangan callback dan kredit otomatis |
| Penarikan | Pengajuan → peninjauan → pembayaran, batas berjenjang KYC |
| Penukaran | Kuotasi real-time koin platform ⇄ koin game, diskon VIP dan bonus kurs |
| Game | Daftar/kategori/pencarian game, riwayat bermain, callback penyelesaian Provider |
| Papan peringkat | Harian/mingguan/bulanan/seluruh waktu + push WebSocket real-time |
| Kupon | Jumlah tetap + diskon persentase, terbatas waktu dan jumlah |
| Tiket | Pengguna membuat/membalas tiket dukungan |
| VIP | Loyalitas 5 tingkat, akumulasi pengalaman, diskon penukaran |
| Pencapaian | 12 pencapaian bawaan, deteksi berbasis peristiwa |
| Sosial | Sistem teman + pesan WebSocket real-time |
| Pengumuman | Pengumuman dalam aplikasi + notifikasi/email |

## Tumpukan Teknologi

- PHP 8.3+ / webman v2 (workerman/webman)
- MySQL 8.0+ (prefiks tabel `game_`, kunci utama BIGINT tanpa auto-increment)
- Redis (Sesi / Cache / Pembatasan kecepatan)
- ClickHouse (analitik OLAP / perhitungan probabilitas)
- Elasticsearch (pencarian teks lengkap)
- Autentikasi JWT + tanda tangan HMAC-SHA256 Provider

## Struktur Proyek

```
service/
├── app/
│   ├── api/v1/controller/  # Kontroler API sisi C (35)
│   ├── middleware/         # Middleware (Cors/SecurityFilter/RateLimit/ApiVersion/UserAuth/ProviderAuth)
│   ├── model/              # Model data
│   ├── service/            # Layanan bisnis (VIP/papan peringkat/risiko/notifikasi, dll.)
│   ├── event/              # Bus peristiwa (EventBus Redis Pub/Sub)
│   ├── provider/           # Lapisan Provider game
│   └── payment/            # Gateway pembayaran
├── common/                 # Direktori layanan bersama (diimplementasikan di paket erik/platform-common)
├── config/                 # File konfigurasi
├── public/                 # Pintu masuk web
├── tests/                  # Tes PHPUnit
├── start.php               # Pintu masuk startup
└── composer.json
```

## Instalasi Sekali Klik

Gunakan wizard instalasi sekali klik di akar proyek (jalankan dari akar proyek):

```bash
# 1. Mulai wizard instalasi
php -S 0.0.0.0:8888 -t install/

# 2. Buka http://localhost:8888 di browser
#    Ikuti wizard: pemeriksaan lingkungan → konfigurasi database → akun admin → instalasi otomatis
```

Atau jalankan semuanya dengan Docker Compose (akar proyek):

```bash
docker compose up -d
```

## Instalasi Manual

```bash
# 1. Instal dependensi
cd service && composer install

# 2. Konfigurasi variabel lingkungan
cp .env.example .env
# Edit .env: koneksi database, kunci JWT, dll.

# 3. Mulai layanan (port default 8788)
php start.php start        # latar depan
php start.php start -d     # latar belakang (daemon)
```

## Penggunaan

- Referensi API: `docs/API.md` (referensi lengkap)
- Dokumentasi daring: http://localhost:8788/apidoc/ (dokumentasi interaktif hg/apidoc)
- Pemeriksaan kesehatan: `GET http://localhost:8788/health`
- Frontend sisi C: `apps/flutter/platform/` (platform pengguna Flutter Web)
- Backend admin: `admin/` (backend admin dan frontend `admin/apps/flutter/`)

## Pengujian

```bash
cd service
SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
```
