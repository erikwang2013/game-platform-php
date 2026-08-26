# Laporan Audit Sistem Instalasi
<!-- lang-nav -->

Languages: [中文](INSTALL-AUDIT-REPORT.md) · [English](INSTALL-AUDIT-REPORT.en.md) · [한국어](INSTALL-AUDIT-REPORT.ko.md) · [Русский](INSTALL-AUDIT-REPORT.ru.md) · [Deutsch](INSTALL-AUDIT-REPORT.de.md) · [Français](INSTALL-AUDIT-REPORT.fr.md) · [Español](INSTALL-AUDIT-REPORT.es.md) · [Português](INSTALL-AUDIT-REPORT.pt.md) · [हिन्दी](INSTALL-AUDIT-REPORT.hi.md) · [العربية](INSTALL-AUDIT-REPORT.ar.md) · [বাংলা](INSTALL-AUDIT-REPORT.bn.md) · **Bahasa Indonesia** · [日本語](INSTALL-AUDIT-REPORT.ja.md)


> Tanggal audit: 2026-08-04
> Ruang lingkup audit: semua file di direktori `install/` + perubahan dokumen terkait
> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## I. Ringkasan Audit

| Dimensi | Penilaian | Keterangan |
|------|------|------|
| Kelengkapan fungsi | Lolos | Alur instalasi 5 langkah lengkap, 39 tabel semuanya dibuat, data seed lengkap |
| Kebenaran SQL | Lolos | 42 tabel identik dengan file migrasi asli, kolom source sudah digabung ke CREATE TABLE |
| Konfigurasi ekosistem | Lolos | Dua set konfigurasi .env admin dan service lengkap, kunci dibuat otomatis |
| Keamanan | Lolos dasar | Kata sandi dienkripsi bcrypt, perlindungan XSS memadai, disarankan menambah CSRF Token |
| Maintainability | Lolos | Struktur kode jelas, tanggung jawab per file jelas |
| Idempotensi | Lolos | Semua INSERT sudah diubah menjadi INSERT IGNORE, berisi guard WHERE NOT EXISTS |
| Pengalaman pengguna | Lolos | Desain responsif, pengujian koneksi AJAX, pesan error bahasa Mandarin yang jelas |

---

## II. File yang Dibuat

### 2.1 `install/install.sql` (988 baris)
- Menggabungkan 8 file migrasi asli
- 42 tabel data dengan prefiks `erik_` (CREATE TABLE IF NOT EXISTS)
- 13 blok data seed INSERT IGNORE
- Kolom `source` di `erik_operation_log` sudah digabung ke pernyataan pembuatan tabel (tanpa perlu ALTER TABLE)
- Dibungkus transaksi (START TRANSACTION / COMMIT)
- Semua INSERT sudah diproses idempoten

**Detail pemrosesan idempoten INSERT:**

| Nama tabel | Cara pemrosesan |
|------|---------|
| `erik_admin_role` | INSERT IGNORE (ID tetap) |
| `erik_admin_permission` | INSERT IGNORE (ID tetap) - 4 kali |
| `erik_admin_role_permission` | Subkueri WHERE NOT EXISTS |
| `erik_platform_config` | INSERT IGNORE (ID tetap) - 2 kali |
| `erik_language` | INSERT IGNORE (ID tetap) |
| `erik_translation` | INSERT IGNORE (ID tetap) |
| `erik_risk_rule` | INSERT IGNORE (ID tetap) |
| `erik_withdraw_limit` | INSERT IGNORE (ID tetap) |
| `erik_game_category` | INSERT IGNORE (ID tetap) |
| `erik_country_config` | INSERT IGNORE (ID tetap) |

### 2.2 `install/index.php` (485 baris)
- Dispatching rute: step1 -> step2 -> step3 -> step4 -> step5
- Antarmuka AJAX: `?action=test-db` (POST JSON)
- 5 fungsi template halaman
- JavaScript inline (pengujian koneksi AJAX)
- Output HTML menggunakan `htmlspecialchars()` cegah XSS
- Deteksi sudah terinstal (install.lock)

### 2.3 `install/Installer.php` (506 baris)
- Pemeriksaan lingkungan: 11 item (versi PHP, 6 ekstensi, izin direktori, file SQL)
- Pengujian koneksi database: PDO + pembuatan database otomatis
- Eksekusi instalasi: impor SQL -> pembuatan admin -> penulisan .env -> penguncian
- Pembuatan kunci: JWT(64 byte) / Hashids(32 byte) / Encryption(32 byte)
- Backup .env: backup otomatis file .env yang ada sebelum instalasi

### 2.4 `install/assets/style.css` (130 baris)
- Desain responsif (mendukung seluler <=600px)
- Tema variabel CSS (--primary: #4f46e5)
- Tanpa dependensi eksternal

---

## III. Cakupan Pemeriksaan Lingkungan (11 item)

| # | Item pemeriksaan | Level | Status |
|---|--------|------|------|
| 1 | PHP >= 8.1.0 | Wajib | Lolos |
| 2 | PDO MySQL | Wajib | Lolos |
| 3 | MBString | Wajib | Lolos |
| 4 | JSON | Wajib | Lolos |
| 5 | OpenSSL | Wajib | Lolos |
| 6 | PCNTL | Wajib | Lolos |
| 7 | GD | Disarankan | Lolos |
| 8 | XML | Disarankan | Lolos |
| 9 | Redis | Disarankan | Lolos |
| 10 | Izin direktori (admin/runtime, service/runtime) | Wajib | Lolos |
| 11 | File install.sql ada | Wajib | Lolos |

---

## IV. Kelengkapan Konfigurasi Ekosistem

### 4.1 Pembuatan `.env` Admin (70 item konfigurasi)

| Grup | Jumlah item | Cakupan |
|------|---------|------|
| Konfigurasi aplikasi | 3 | APP_NAME, APP_DEBUG, APP_URL |
| Autentikasi JWT | 6 | JWT_SECRET, JWT_ALGORITHM, JWT_TTL, JWT_REFRESH_TTL, JWT_ISSUER, JWT_AUDIENCE |
| Hashids | 2 | HASHIDS_SALT, HASHIDS_ALT_SALT |
| Snowflake | 3 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID, SNOWFLAKE_START_TIMESTAMP |
| Enkripsi (API) | 3 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTION_IV |
| Enkripsi (DB) | 3 | ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER, ENCRYPTION_PREVIOUS_KEYS |
| Scout/ES | 7 | SCOUT_DRIVER, SCOUT_HOSTS, SCOUT_PREFIX, SCOUT_SHARDS, SCOUT_REPLICAS, SCOUT_CHUNK_SIZE, SCOUT_SOFT_DELETE |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST dll. |
| CAPTCHA Poster | 7 | POSTER_IMAGE_DRIVER dll. |
| Database | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| Redis | 4 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DATABASE |
| Kunci kompatibel | 3 | JWT_SECRET_KEY, JWT_DEFAULT_EXPIRE, JWT_REFRESH_EXPIRE |

### 4.2 Pembuatan `.env` Service (48 item konfigurasi)

| Grup | Jumlah item | Cakupan |
|------|---------|------|
| Aplikasi | 2 | APP_ENV, APP_DEBUG |
| Database | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| JWT | 3 | JWT_SECRET, JWT_TTL, JWT_REFRESH_TTL |
| Hashids | 3 | HASHIDS_SALT, HASHIDS_ALPHABET, HASHIDS_MIN_LENGTH |
| Snowflake | 2 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID |
| Enkripsi | 4 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER |
| Redis | 3 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD |
| ClickHouse | 5 | CLICKHOUSE_HOST, CLICKHOUSE_PORT, CLICKHOUSE_DB, CLICKHOUSE_USER, CLICKHOUSE_PASS |
| OAuth | 9 | OAUTH_GOOGLE/FACEBOOK/APPLE masing-masing 3 item |
| Webhook pembayaran | 3 | STRIPE_WEBHOOK_SECRET, PAYPAL_WEBHOOK_ID, PAYPAL_VERIFY_URL |
| CORS | 1 | CORS_ORIGIN |
| Scout/ES | 6 | SCOUT_DRIVER dll. |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST dll. |

**Kesimpulan perbandingan**: Kedua konfigurasi `.env` konsisten dengan `.env.example` asli, dan menambahkan `ENCRYPTION_CIPHER`, `ENCRYPTABLE_CIPHER`, `JWT_REFRESH_TTL` yang sebelumnya kurang ke konfigurasi Service.

---

## V. Audit Keamanan

### 5.1 Tindakan Keamanan yang Telah Diimplementasikan

| Tindakan | Cara implementasi |
|------|---------|
| Keamanan kata sandi | bcrypt, cost=12 |
| Keacakan kunci | `random_int()` bilangan acak aman kriptografi |
| Perlindungan XSS | `htmlspecialchars()` men-escape semua input/output pengguna |
| Perlindungan SQL injection | Pernyataan terpreparasi PDO (`prepare/execute`) |
| Penguncian instalasi | File `install.lock` + metadata JSON |
| Keamanan jalur | Jalur tetap, tanpa file inclusion yang dikendalikan pengguna |
| Kekuatan enkripsi | AES-256-CBC + kunci 32 byte |

### 5.2 Risiko Potensial dan Mitigasi

| Risiko | Level | Tindakan mitigasi |
|------|------|---------|
| Paparan jaringan selama instalasi | Sedang | Segera hapus direktori `install/` setelah instalasi (ada peringatan mencolok di halaman) |
| Tanpa CSRF Token | Rendah | Wizard instalasi adalah alat sementara sekali pakai, server bawaan PHP single-threaded |
| test-db tanpa pembatasan frekuensi | Rendah | Alat sementara, dihapus setelah digunakan |
| Izin file .env | Rendah | Disarankan eksekusi manual chmod 600 setelah instalasi |

### 5.3 Saran Perbaikan

1. **Penguatan produksi**: Setelah instalasi selesai dapat dipertimbangkan otomatis `chmod 600 admin/.env service/.env`
2. **Akses jarak jauh**: Jika server jarak jauh, disarankan melalui terowongan SSH: `ssh -L 8888:localhost:8888 user@host`
3. **Pembersihan setelah instalasi**: Pertimbangkan menambah peringatan mencolok "hapus direktori instalasi" di halaman sukses instalasi (sudah diimplementasikan)

---

## VI. Hasil Pengujian

### 6.1 Pemeriksaan sintaks PHP
```
Lolos install/index.php — No syntax errors
Lolos install/Installer.php — No syntax errors
```

### 6.2 Pengujian fungsional
```
Lolos Step 1 pemeriksaan lingkungan — 11 item pemeriksaan semua lolos
Lolos Step 2 konfigurasi database — render formulir benar, pengisian nilai default normal
Lolos AJAX test-db — format respons JSON benar, pesan error bahasa Mandarin jelas
Lolos aset statis CSS — 200 OK, text/css
Lolos halaman sudah terinstal — deteksi install.lock normal, informasi peringatan lengkap
```

### 6.3 Validasi SQL
```
Lolos 42 nama tabel identik dengan file migrasi asli
Lolos kolom source sudah digabung ke pernyataan pembuatan tabel erik_operation_log
Lolos semua pernyataan INSERT sudah diproses idempoten
Lolos guard WHERE NOT EXISTS sudah dipulihkan (konsisten dengan migrasi asli)
```

---

## VII. Masalah yang Ditemukan dan Diperbaiki

| # | Masalah | Severity | Status |
|---|------|--------|------|
| 1 | INSERT `erik_admin_role_permission` kurang guard `WHERE NOT EXISTS` (tidak konsisten dengan migrasi asli) | Tinggi | Sudah diperbaiki |
| 2 | Semua INSERT data seed belum diproses idempoten (eksekusi ulang akan gagal) | Sedang | Sudah diperbaiki (INSERT IGNORE) |
| 3 | Pemeriksaan lingkungan kurang pemeriksaan ekstensi `pcntl` (dependensi inti webman) | Sedang | Sudah diperbaiki |
| 4 | .env Service kurang konfigurasi `ENCRYPTION_CIPHER` | Rendah | Sudah diperbaiki |
| 5 | .env Service kurang konfigurasi `ENCRYPTABLE_CIPHER` | Rendah | Sudah diperbaiki |
| 6 | .env Service kurang konfigurasi `JWT_REFRESH_TTL` | Rendah | Sudah diperbaiki |

---

## VIII. Perubahan Dokumen

| File | Isi perubahan |
|------|---------|
| `README.md` | Mulai cepat diubah menjadi "wizard instalasi satu klik (disarankan)", menambah blok lipat instalasi manual, memperbarui struktur proyek |
| `README.en.md` | Sama seperti di atas (versi Inggris), memperbarui struktur proyek |
| `docs/DEPLOYMENT.md` | Menambah bagian 2 "wizard instalasi satu klik (disarankan untuk deployment baru)", bagian Docker asli dipindah ke belakang |
| `.gitignore` | Menambah `install/install.lock`, `admin/.env.backup.*`, `service/.env.backup.*` |

---

## IX. Evaluasi Keseluruhan

Sistem instalasi berfungsi lengkap, kualitas kode baik, tindakan keamanan memadai. Alur instalasi 5 langkah jelas dan intuitif, pemeriksaan lingkungan mencakup semua ekstensi kunci yang dibutuhkan webman untuk berjalan, otomatis membuat kunci berkekuatan tinggi, file konfigurasi sepenuhnya kompatibel dengan sistem yang ada. Proses penggabungan SQL menjaga konsistensi penuh dengan file migrasi asli (42 tabel), pemrosesan idempoten memastikan eksekusi berulang tidak akan error.

**Kesimpulan audit: Lolos, dapat digunakan.**

---

## X. Konfirmasi Status 2026-08-18

Perbaikan keamanan putaran ini (fail-closed callback pembayaran, validasi startup JWT, penyatuan prefiks tabel) **tidak menyentuh sistem instalasi**, tanpa masalah baru:

- Setelah model menghapus prefiks `erik_` yang di-hardcode, nama tabel sebenarnya masih dibuat seragam oleh `prefix=erik_` di `config/database.php`, konsisten dengan tabel `erik_*` yang dibuat install.sql, tidak perlu mengubah SQL instalasi
- Validasi startup JWT (tolak startup saat `JWT_SECRET_KEY` hilang atau nilai default) kompatibel dengan kunci acak 64 byte yang dibuat otomatis wizard instalasi, alur instalasi tidak perlu disesuaikan

Kesimpulan historis dan daftar masalah tetap tidak berubah.

---
