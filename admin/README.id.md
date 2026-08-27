# Backend Administrasi Terbuka (open-admin)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · **Bahasa Indonesia** · [日本語](README.ja.md)


Sistem backend administrasi full-stack berbasis webman v2 + Flutter.

> [English version](README.en.md) | [Diagram arsitektur](docs/ARCHITECTURE.id.md) | [Dokumen desain](docs/DESIGN.id.md) | [Arsitektur keamanan](docs/SECURITY.id.md) | [Referensi API](docs/API.id.md)

## Daftar Fitur

| Domain Bisnis | Fitur | Deskripsi |
|--------|------|------|
| 🔐 Autentikasi | Login/Registrasi/Refresh token/Logout | CAPTCHA klik + JWT + daftar hitam |
| | Penguncian akun | 5 kali gagal mengunci 15 menit |
| | Batasan sesi bersamaan | Maksimal 3 Token aktif per pengguna |
| 📊 Dasbor | Statistik real-time/grafik tren/grafik distribusi/operasi terbaru | Cache Redis 5 menit |
| 📈 Analisis data | 12 endpoint: ringkasan/peringkat/DAU/jam/distribusi perilaku/pendapatan/konversi/probabilitas/retensi/funnel/ARPU/metrik ekonomi | Agregasi real-time MySQL, DB gagal mengembalikan data kosong |
| 👥 Manajemen pengguna | CRUD + hapus massal/aktif-nonaktif | Soft delete + konfirmasi ulang kata sandi |
| | Impor massal Excel | Validasi per baris + laporan kesalahan |
| 🔒 Peran & izin | CRUD peran + pohon izin | Otorisasi RBAC granular method.path |
| ⚙ Konfigurasi sistem | CRUD pasangan kunci-nilai | Manajemen grup |
| 📋 Audit operasi | Kueri log + deteksi sumber | Identifikasi otomatis 8 platform |
| 📁 Manajemen file | Unggah/Ekspor Excel/Ekspor PDF | Data sensitif otomatis diredaksi |
| 🛡 Proteksi keamanan | 18 lapisan pertahanan berlapis | XSS/Injeksi SQL/path traversal/injeksi perintah/CSRF/rate limit/CSP... |
| 🏥 Operasional | Health check/metrics/dokumen API/security.txt | Prometheus + OpenAPI 3.0 |

## Tumpukan Teknologi

| Lapisan | Teknologi | Deskripsi |
|---|------|------|
| Framework backend | webman v2 (workerman) | Framework proses tetap PHP berperforma sangat tinggi |
| Versi PHP | 8.3+ | |
| Database | MySQL 8.0+ | Prefix tabel `erik_`, primary key BIGINT non-auto-increment |
| Mesin pencari | Elasticsearch | Sinkronisasi & kueri melalui `webman-scout` |
| Frontend admin | Flutter 3.x | Web dalam gaya backend admin PC (`apps/flutter/`) |
| Seluler | HarmonyOS ArkTS | Klien native HarmonyOS (`apps/harmonyos/`), mendukung ponsel/tablet/2in1 |

## Dependensi Inti

| Paket | Kegunaan |
|---|------|
| `erikwang2013/snowflake-php` | Algoritma Snowflake menghasilkan primary key BIGINT unik global |
| `erikwang2013/hashids` | Enkripsi/dekripsi ID di lapisan API, menyembunyikan ID database asli |
| `erikwang2013/jwt-webman` | Penerbitan dan validasi token autentikasi JWT |
| `erikwang2013/encryption` | Enkripsi/dekripsi data sensitif di lapisan transport antarmuka |
| `erikwang2013/encryptable` | Enkripsi/dekripsi otomatis kolom sensitif di lapisan penyimpanan database |
| `erikwang2013/webman-scout` | Sinkronisasi data Elasticsearch dan pencarian teks penuh |
| `erikwang2013/season` | Data bendera negara |
| `erikwang2013/poster-php` | Pembuatan & validasi CAPTCHA klik + pembuatan poster |
| `phpoffice/phpspreadsheet` | Ekspor Excel |
| `barryvdh/laravel-dompdf` | Ekspor PDF (berbasis Dompdf) |

## Struktur Proyek

```
open-admin/
├── app/
│   ├── admin/controller/       # Kontroler sisi admin
│   │   ├── DashboardController.php # Dasbor (cache Redis)
│   │   ├── UserController.php      # CRUD pengguna + operasi massal
│   │   ├── RoleController.php      # CRUD peran
│   │   ├── PermissionController.php# CRUD izin
│   │   ├── ConfigController.php    # CRUD konfigurasi sistem
│   │   ├── LogController.php       # Kueri log operasi
│   │   ├── ProfileController.php   # Pusat pribadi + logout
│   │   ├── ExportController.php    # Ekspor Excel/PDF
│   │   ├── ImportController.php    # Impor pengguna Excel
│   │   ├── UploadController.php    # Unggah file
│   │   ├── HealthController.php    # Health check
│   │   ├── DocsController.php      # Dokumen OpenAPI
│   │   └── BaseController.php      # Kontroler dasar
│   ├── api/
│   │   └── v1/controller/          # Kontroler API v1 (versi dikontrol header permintaan API-Version)
│   │       ├── CaptchaController.php # CAPTCHA klik
│   │       └── AuthController.php    # Login/Registrasi/Refresh token
│   ├── common/                 # Kelas utilitas publik
│   │   ├── HashidsService.php  # Enkode/dekode ID
│   │   ├── SnowflakeService.php# Pembuatan ID Snowflake
│   │   └── EncryptionService.php # Enkripsi/dekripsi data + redaksi
│   ├── middleware/             # Middleware
│   │   ├── Cors.php            # CORS
│   │   ├── SecurityFilter.php  # Deteksi & pemblokiran serangan (batasan metode HTTP/XSS/Injeksi SQL/path traversal/injeksi perintah/CSRF)
│   │   ├── RateLimit.php       # Rate limit Redis (jendela geser + header respons)
│   │   ├── ApiVersion.php      # Validasi versi API
│   │   ├── AdminAuth.php       # Autentikasi JWT + daftar hitam
│   │   ├── AdminPermission.php # Validasi izin RBAC
│   │   └── OperationLog.php    # Pencatatan log operasi otomatis (termasuk deteksi sumber)
│   └── model/                  # Model data
├── apps/
│   ├── flutter/                # Backend administrasi Flutter Web (gaya PC)
│   │   └── lib/app/
│   │       ├── pages/          # 5 halaman lengkap (dasbor/pengguna/peran/konfigurasi/log/pusat pribadi)
│   │       ├── services/       # ApiService (interceptor JWT) + AuthService (persistensi Token)
│   │       └── layouts/        # Tata letak backend admin responsif (sidebar+topbar+area konten)
│   └── harmonyos/              # Klien native HarmonyOS (refresh Token tanpa terasa)
├── config/                     # File konfigurasi (termasuk komentar bahasa China)
│   ├── route.php               # Rute + strategi versi API
│   ├── middleware.php           # Registrasi middleware global
│   └── ...                     # Konfigurasi tiap komponen
├── install/        # File migrasi SQL (termasuk data seed izin)
├── public/                     # Titik masuk publik
├── runtime/                    # File runtime
└── vendor/                     # Dependensi Composer
```

## Persyaratan Lingkungan

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (hanya untuk pengembangan frontend)
- Elasticsearch >= 7.x (opsional, diperlukan untuk fitur pencarian)

## Mulai Cepat

### 1. Instal Dependensi

```bash
composer install
```

### 2. Konfigurasi Variabel Lingkungan

Salin dan modifikasi variabel lingkungan (opsional, jika tidak dikonfigurasi akan menggunakan nilai default di `config/*.php`):

```bash
cp .env.example .env
```

Item konfigurasi utama:

| Variabel Lingkungan | Deskripsi | Nilai Default |
|---------|------|--------|
| `JWT_SECRET` | Kunci tanda tangan JWT | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Nilai salt Hashids | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | Kunci enkripsi API | Nilai default 32 byte |
| `SNOWFLAKE_DATACENTER_ID` | ID pusat data (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID node kerja (0-31) | `1` |
| `SCOUT_HOSTS` | Alamat ES | `http://localhost:9200` |

**Di lingkungan produksi, semua kunci WAJIB diubah menjadi string acak.**

### 3. Inisialisasi Database

Jalankan file SQL di `install/` secara berurutan:

```bash
mysql -u root -p < install/install.sql
```

### 4. Menjalankan Layanan

```bash
php start.php start
```

Default mendengarkan di `http://0.0.0.0:8787`.

### 5. Menjalankan Frontend (Opsional)

**Backend administrasi Flutter (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (gaya backend admin PC)
```

**Klien HarmonyOS (ponsel):**

Gunakan DevEco Studio untuk membuka direktori `apps/harmonyos/`, lalu jalankan dengan perangkat nyata atau emulator.

### 6. Deployment Docker Compose Satu-Klik (disarankan untuk produksi)

Proyek menyediakan solusi orkestrasi Docker lengkap, mencakup 5 layanan: Nginx, PHP (aplikasi webman), MySQL, Redis, Elasticsearch.

```bash
# 1. Konfigurasi variabel lingkungan Docker
cp .env.docker .env

# 2. Menjalankan semua layanan
docker-compose up -d

# 3. Inisialisasi database (jalankan di dalam kontainer app)
docker-compose exec app mysql -h mysql -u root -p < install/install.sql

# 4. Akses
# http://localhost:8787  (webman)
# http://localhost:8080  (proxy balik Nginx)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, berbasis `php:8.3-cli`
- `docker-compose.yml`: Orkestrasi 5 layanan, isolasi jaringan, persistensi volume data
- `.env.docker`: Variabel lingkungan khusus lingkungan Docker


## Standar Database

- **Prefix tabel**: `erik_`
- **Primary key**: primary key semua tabel adalah `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT dilarang**
- **Pembuatan ID**: ID primary key dihasilkan oleh `SnowflakeService::generate()` di lapisan aplikasi, unik terdistribusi
- **Kolom wajib**: setiap tabel harus memiliki `id`, `created_at`, `updated_at`
- **Soft delete**: tabel yang memerlukan soft delete menambahkan `deleted_at DATETIME DEFAULT NULL`
- **Kolom sensitif**: nomor ponsel, email, nomor identitas, dll. menggunakan plugin `encryptable` untuk enkripsi/dekripsi otomatis, kolom database menggunakan `VARCHAR(500)` untuk menyimpan teks sandi

## Standar API

### Format Respons Terpadu

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Kode Error Bisnis

| Kode Error | Arti | Deskripsi |
|-------|------|------|
| `0` | Berhasil | |
| `400` | Parameter permintaan salah | |
| `401` | Belum login (Token tidak valid atau kedaluwarsa) | |
| `403` | Tidak ada izin / pemblokiran keamanan | Gagal otorisasi RBAC / deteksi serangan SecurityFilter |
| `404` | Sumber daya tidak ada | |
| `422` | Validasi parameter gagal | |
| `413` | Body permintaan terlalu besar | Dipicu SecurityFilter, melebihi 10MB |
| `405` | Metode permintaan tidak diizinkan | Dipicu SecurityFilter, hanya mengizinkan GET/POST/PUT/DELETE/OPTIONS/HEAD |
| `415` | Tipe media tidak didukung | Dipicu SecurityFilter, Content-Type bukan JSON |
| `429` | Permintaan terlalu sering | Dipicu RateLimit / penguncian akun (5 kali gagal login mengunci 15 menit) |
| `500` | Kesalahan internal server | |

### Penanganan ID

- **ID dalam permintaan/respons**: dienkripsi menjadi string dengan hashids, tidak mengekspos ID database asli
- **Path antarmuka**: `GET /admin/user/{hashid}` — `{id}` di path adalah string hashid
- **Penyimpanan database**: nilai BIGINT asli, dihasilkan oleh snowflake

### Versi API

Versi API dikontrol melalui header permintaan, **tidak tercermin di URL**:

```http
API-Version: v1
```

- Jika tidak membawa nomor versi, default menggunakan `v1`
- Versi yang tidak didukung mengembalikan `400 Bad Request`
- Untuk menambahkan versi baru, cukup buat direktori `app/api/{version}/controller/` dan daftarkan versi baru di middleware

### Rate Limit

Berbasis algoritma jendela geser Redis, default 60 kali/menit/IP/rute. Antarmuka sensitif lebih ketat:
- Login: 10 kali/menit
- Registrasi: 5 kali/menit

Header respons berisi `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. Melebihi batas mengembalikan 429 dengan `Retry-After`.

### Arsitektur Middleware

Middleware global berlaku untuk semua permintaan, dieksekusi berurutan:

```
Cors（praproses CORS + header respons）
  → SecurityFilter（batasan metode HTTP/ukuran body/validasi Content-Type/pemblokiran serangan XSS/Injeksi SQL/path traversal/injeksi perintah/CSRF）
  → RateLimit（rate limit jendela geser Redis + penguncian akun：5 kali gagal login mengunci 15 menit）
  → ApiVersion（validasi versi API，grup rute /api）
  → AdminAuth（autentikasi JWT + daftar hitam，grup rute /admin）
  → AdminPermission（otorisasi RBAC，grup rute /admin）
  → OperationLog（pencatatan otomatis POST/PUT/DELETE，termasuk deteksi sumber，grup rute /admin）
```

`/health` dan `/api/docs` adalah endpoint publik, hanya melewati `Cors → SecurityFilter → RateLimit`.

Penguatan keamanan:
- **Penguncian akun**: 5 kali gagal login berturut-turut, akun otomatis dikunci 15 menit, login selama masa kunci mengembalikan 429
- **Batasan sesi bersamaan**: maksimal 3 Token valid per pengguna, Token paling lama otomatis masuk daftar hitam saat melebihi
- **security.txt**: `GET /.well-known/security.txt` menyediakan informasi kontak keamanan standar RFC 9116
- **Konfigurasi keamanan Nginx**: lihat `docs/nginx-security.conf` untuk contoh penguatan keamanan proxy balik lengkap

### Autentikasi

Login dan registrasi harus melewati validasi **CAPTCHA klik** terlebih dahulu:

1. Klien meminta `POST /api/captcha/generate` untuk mendapatkan gambar CAPTCHA (PNG base64) dan daftar target teks
2. Pengguna mengklik posisi teks terkait pada gambar secara berurutan, mengumpulkan koordinat klik `[{x, y}, ...]`
3. Saat login, kirim `captcha_key` dan `clicks` sekaligus, server memvalidasi CAPTCHA terlebih dahulu kemudian kredensial

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

Antarmuka sisi admin selanjutnya memerlukan autentikasi JWT:

```http
Authorization: Bearer <token>
```

Setelah login berhasil, mengembalikan access_token dengan masa berlaku 2 jam; juga mengembalikan refresh_token dengan masa berlaku 14 hari.

Saat logout, Token masuk daftar hitam Redis dan tidak dapat digunakan kembali selama masa berlaku. POST /admin/profile/logout

### Konfirmasi Ulang Operasi Sensitif

Operasi sensitif seperti menghapus pengguna, peran, izin memerlukan `password` pengguna yang sedang login di body permintaan untuk konfirmasi ulang identitas:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## Daftar API

> Semua antarmuka `/api/*` perlu membawa `API-Version: v1` di header permintaan (default v1 jika tidak dikirim).

### Antarmuka Publik

| Metode | Path | Deskripsi |
|-----|------|------|
| `GET` | `/health` | Health check (status DB/Redis/ES) |
| `GET` | `/api/docs` | Dokumen spesifikasi OpenAPI 3.0 |
| `POST` | `/api/captcha/generate` | Membuat CAPTCHA klik |
| `POST` | `/api/captcha/verify` | Memvalidasi CAPTCHA klik |
| `POST` | `/api/auth/login` | Login (memerlukan captcha) |
| `POST` | `/api/auth/register` | Registrasi (memerlukan captcha) |
| `POST` | `/api/auth/refresh` | Refresh token |
| `GET` | `/metrics` | Metrik monitoring Prometheus |

### Antarmuka Admin (memerlukan JWT + RBAC)

| Metode | Path | Deskripsi |
|-----|------|------|
| `GET` | `/admin/dashboard` | Data dasbor (cache Redis 5 menit) |
| `GET` | `/admin/user` | Daftar pengguna (paginasi + pencarian) |
| `POST` | `/admin/user` | Membuat pengguna |
| `GET` | `/admin/user/{id}` | Detail pengguna |
| `PUT` | `/admin/user/{id}` | Memperbarui pengguna |
| `DELETE` | `/admin/user/{id}` | Menghapus pengguna (soft delete, perlu konfirmasi kata sandi) |
| `POST` | `/admin/user/batch/destroy` | Menghapus pengguna massal (perlu konfirmasi kata sandi) |
| `POST` | `/admin/user/batch/status` | Mengaktifkan/menonaktifkan pengguna massal |
| `GET` | `/admin/role` | Daftar peran |
| `POST` | `/admin/role` | Membuat peran |
| `PUT` | `/admin/role/{id}` | Memperbarui peran |
| `DELETE` | `/admin/role/{id}` | Menghapus peran (perlu konfirmasi kata sandi) |
| `GET` | `/admin/permission` | Pohon izin |
| `POST` | `/admin/permission` | Membuat izin |
| `PUT` | `/admin/permission/{id}` | Memperbarui izin |
| `DELETE` | `/admin/permission/{id}` | Menghapus izin (kaskade ke sub-izin, perlu konfirmasi kata sandi) |
| `GET` | `/admin/config` | Daftar konfigurasi sistem |
| `POST` | `/admin/config` | Membuat item konfigurasi |
| `PUT` | `/admin/config/{id}` | Memperbarui item konfigurasi |
| `DELETE` | `/admin/config/{id}` | Menghapus item konfigurasi (perlu konfirmasi kata sandi) |
| `GET` | `/admin/log` | Log operasi (paginasi + filter) |
| `PUT` | `/admin/profile` | Memperbarui informasi pribadi |
| `PUT` | `/admin/profile/password` | Mengubah kata sandi |
| `POST` | `/admin/profile/logout` | Logout (daftar hitam JWT) |
| `POST` | `/admin/export/excel` | Ekspor Excel |
| `POST` | `/admin/export/pdf` | Ekspor PDF |
| `POST` | `/admin/import/users` | Impor pengguna Excel |
| `POST` | `/admin/upload` | Unggah file (gambar/dokumen, maksimal 10MB) |

## Penjelasan Frontend

### Backend Administrasi Flutter (gaya PC)

- **Tata letak**: sidebar (dapat dilipat 64px/240px) + topbar + area konten, tiga breakpoint responsif (ponsel/tablet/desktop)
- **Halaman**: login, dasbor, manajemen pengguna, peran & izin, konfigurasi sistem, log operasi, pusat pribadi
- **Manajemen status**: GetX (`ApiService` singleton + `AuthService` persistensi Token)
- **Dasbor**: kartu statistik, grafik garis tren (fl_chart), pie chart, log operasi terbaru
- **Ekspor**: ekspor Excel/PDF, PDF menyertakan informasi hak cipta yang tidak dapat dihapus
- **Operasi massal**: hapus massal multi-pilih, aktif/nonaktif massal
- **Tema**: Material 3 tema ganda terang/gelap

### Klien Seluler HarmonyOS

- **Halaman**: login, dasbor, daftar/detail pengguna, pusat pribadi
- **Autentikasi**: JWT Bearer + refresh Token otomatis tanpa terasa saat 401, gagal refresh otomatis dialihkan ke halaman login
- **Penyimpanan**: Token dikelola melalui AppStorage

## Standar Pengembangan

- Referensi fungsi/kelas global tanpa awalan `\`, gunakan impor `use` secara seragam
- Semua file PHP wajib menyertakan pernyataan hak cipta di bagian atas
- Semua file konfigurasi wajib menyertakan komentar bahasa China
- Primary key database wajib dihasilkan oleh snowflake di lapisan aplikasi, auto-increment dilarang
- Semua parameter dan ID dalam respons di lapisan API wajib dienkripsi/dekripsi dengan hashids
- Middleware AdminPermission menggunakan cache Redis untuk izin pengguna (TTL=60s), menghilangkan bottleneck kueri N+1

## Deployment

### Docker Compose (disarankan)

Direktori root proyek menyediakan `docker-compose.yml`, mengorkestrasi 5 layanan:

| Layanan | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | dibangun `Dockerfile` lokal | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

Image PHP dibangun melalui `Dockerfile`, image dasar `php:8.3-cli`, dengan OPcache diaktifkan.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

Pipeline integrasi berkelanjutan GitHub Actions: `.github/workflows/ci.yml`

- Pemeriksaan sintaks PHP (`php -l`)
- Pengujian unit PHPUnit
- Analisis statis Flutter (`flutter analyze`)

### Backup Database

Direktori `database/backup/`:

- `backup.sh` — backup mysqldump + gzip, otomatis membersihkan backup lama lebih dari 30 hari
- `restore.sh` — pemulihan interaktif, mencantumkan backup yang tersedia untuk dipilih

### Konfigurasi Keamanan Nginx

Untuk deployment produksi, lihat `docs/nginx-security.conf` untuk konfigurasi penguatan keamanan proxy balik.

## Open Source Itu Tidak Mudah, Selamat Mendukung

| WeChat Pay | Alipay |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
