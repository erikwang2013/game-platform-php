# Dokumen Desain Arsitektur
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE-DESIGN.md) · [English](ARCHITECTURE-DESIGN.en.md) · [한국어](ARCHITECTURE-DESIGN.ko.md) · [Русский](ARCHITECTURE-DESIGN.ru.md) · [Deutsch](ARCHITECTURE-DESIGN.de.md) · [Français](ARCHITECTURE-DESIGN.fr.md) · [Español](ARCHITECTURE-DESIGN.es.md) · [Português](ARCHITECTURE-DESIGN.pt.md) · [हिन्दी](ARCHITECTURE-DESIGN.hi.md) · [العربية](ARCHITECTURE-DESIGN.ar.md) · [বাংলা](ARCHITECTURE-DESIGN.bn.md) · **Bahasa Indonesia** · [日本語](ARCHITECTURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Tujuan Desain

Membangun platform agregasi game global yang berlaku universal dan terinternasionalisasi. Kebutuhan inti:

- Pengguna dapat melakukan deposit di platform, menukarkan koin game, memainkan game, mendapatkan koin game, dan menarik dana
- Platform mengelola berbagai game secara terpadu (buatan sendiri + pihak ketiga), setiap game memiliki koin game dan kurs independen
- Backend menyediakan kemampuan review, saklar, dan kontrol risiko yang lengkap
- Mendukung operasi global dengan multi-bahasa, multi-mata uang, multi-saluran pembayaran

## 2. Pemilihan Arsitektur

### 2.1 Mengapa memilih Modular Monolith daripada microservices?

Tahap saat ini memilih Modular Monolith:

| Pertimbangan | Modular Monolith | Microservices |
|------|----------|--------|
| Efisiensi pengembangan | Panggilan dalam proses yang sama, tanpa RPC | Perlu menangani latensi jaringan, serialisasi |
| Konsistensi transaksi | Transaksi database lokal | Transaksi terdistribusi (kompleks) |
| Kompleksitas operasional | Deployment proses tunggal | Orkestrasi banyak layanan, service discovery |
| Skalabilitas | Masa depan dapat dipecah menjadi microservices per modul | Mendukung skala independen secara alami |
| Ukuran tim | Cocok untuk tim kecil (1-5 orang) | Cocok untuk pengembangan paralel banyak tim |

**Keputusan**: admin/ (backend administrasi) dan service/ (bisnis sisi C) adalah dua instance webman independen, dapat di-deploy di mesin yang sama (port berbeda) atau di-deploy terpisah. Lapisan bersama common/ menghilangkan duplikasi kode melalui PSR-4 autoload. Setelah volume bisnis tumbuh di masa depan, service/ dapat dipecah menjadi beberapa microservices (layanan pengguna, layanan dompet, layanan game).

### 2.2 Mengapa memilih webman v2 daripada PHP-FPM tradisional?

| Pertimbangan | webman v2 | PHP-FPM (Laravel) |
|------|-----------|-------------------|
| Performa | Memori menetap, dukungan coroutine | Memuat semua file setiap permintaan |
| Konkurensi | Puluhan ribu QPS di satu mesin | Ratusan QPS di satu mesin |
| Deployment | Sederhana, satu proses banyak worker | Konfigurasi Nginx + PHP-FPM rumit |
| Ekosistem | Kompatibel dengan komponen Laravel Illuminate | Ekosistem lengkap |

**Keputusan**: Platform game perlu menangani callback deposit, permintaan penukaran, dan penyelesaian game dengan konkurensi tinggi; memori menetap dan kemampuan konkurensi tinggi webman lebih cocok. Pada saat yang sama kompatibel dengan komponen ORM, Queue, dll. dari Laravel, efisiensi pengembangan tidak kalah dengan framework tradisional.

### 2.3 Mengapa menggunakan gaya PC Flutter Web?

- Satu kode dapat dikompilasi secara bersamaan ke Web (PC), iOS, Android, HarmonyOS
- Pustaka komponen Material 3 matang, tata letak sidebar + top bar gaya PC siap pakai
- Berbagi lapisan logika bisnis dengan klien HarmonyOS
- Menghindari pemeliharaan dua set kode frontend React/Vue + Flutter

## 3. Keputusan Teknis Kunci

### 3.1 Sistem ID

```
Snowflake menghasilkan BIGINT (unik terdistribusi internal)
    ↓
Hashids mengodekan menjadi string pendek (tidak dapat merekonstruksi ID asli dari luar)
    ↓
String hashid ditransmisikan dalam permintaan/respons API
```

**Alasan**:
- Snowflake unik global, tren naik menguntungkan indeks, tidak mengekspos volume bisnis
- Hashids mencegah pihak luar menelusuri data melalui ID berurutan dan memperkirakan skala

### 3.2 Presisi Mata Uang

Koin platform dan koin game secara seragam menggunakan presisi `DECIMAL(18,4)`, sisi PHP menggunakan keluarga fungsi `bcmath` (bcadd/bcsub/bcmul/bcdiv/bccomp) untuk semua perhitungan jumlah.

**Alasan**: Bilangan floating point (float/double) memiliki error presisi, tidak dapat diterima dalam skenario keuangan. DECIMAL + bcmath menjamin perhitungan presisi.

### 3.3 Kunci Optimis Dompet

```sql
UPDATE game_user_wallet 
SET balance = balance + ?, version = version + 1 
WHERE user_id = ? AND version = ?
```

Gagal diperbarui, otomatis coba ulang (maksimal 5 kali).

**Alasan**:
- Deposit, penukaran, dan penarikan platform game semuanya dapat mengoperasikan dompet yang sama secara konkuren
- Kunci pesimis (SELECT FOR UPDATE) berperforma buruk pada konkurensi tinggi
- Kunci optimis berperforma jauh lebih baik daripada kunci pesimis pada skenario tingkat konflik rendah

### 3.4 Alur Review Penarikan

```
Pengguna mengajukan penarikan
  ├─ Saklar global mati → tolak
  ├─ Jumlah < ambang review otomatis → lolos otomatis
  └─ Jumlah >= ambang → review manual → lolos/tolak (ditolak, koin platform dikembalikan)
```

**Alasan**:
- Saklar global digunakan untuk kontrol risiko darurat (seperti ditemukan kerentanan, lalu lintas abnormal)
- Lolos otomatis untuk jumlah kecil mengurangi biaya manual dan meningkatkan pengalaman pengguna
- Review manual untuk jumlah besar mencegah pencucian uang dan penipuan

### 3.5 Model Selisih Penukaran

Setiap koin game memiliki `exchange_rate` independen (1 koin platform = X koin game) dan `spread_pct` (% komisi platform).

Saat membeli: koin game masuk = koin platform × kurs × (1 - % komisi)
Saat menjual: koin platform masuk = koin game ÷ kurs × (1 - % komisi)

**Alasan**:
- Pendapatan platform berasal dari selisih penukaran, bukan pembayaran dalam game
- Kurs independen mendukung strategi penetapan harga yang berbeda untuk game berbeda
- Rasio selisih dapat disesuaikan secara fleksibel, mewujudkan operasi yang presisi

## 4. Arsitektur Keamanan

Berdasarkan 18 lapisan pertahanan mendalam yang ada, lapisan perlindungan baru ditambahkan untuk platform game:

| Lapisan | Tindakan | Alasan |
|------|------|------|
| Keamanan konkurensi | Kunci optimis version dompet | Mencegah pengurangan berulang/penerimaan berulang |
| Keamanan penarikan | Saklar global + ambang jumlah + batas harian/bulanan + verifikasi poster-php | Pencegahan multi-lapis, mengurangi risiko dana |
| Keamanan penukaran | Kueri harga terpisah dari eksekusi, kueri kedaluwarsa 60 detik | Mencegah arbitrase akibat fluktuasi kurs |
| Keamanan game | Verifikasi tanda tangan callback pihak ketiga + daftar putih IP + pertahanan replay attack | Mencegah penyelesaian game palsu |
| Kontrol risiko | Mesin aturan (daftar hitam IP, peringatan jumlah besar, frekuensi abnormal) | Memblokir transaksi mencurigakan secara real-time |

## 5. Desain Internasionalisasi

### 5.1 Deteksi Bahasa

```
Permintaan masuk
  ↓
LanguageMiddleware (middleware global)
  ├── 1. Header X-Language
  ├── 2. Header Accept-Language (zh → zh-CN, en → en-US)
  └── 3. Default en-US
  ↓
TranslationService::setLocale($locale)
  ↓
Fungsi __() di Controller atau TranslationService::trans() mendapatkan teks terjemahan
```

### 5.2 Penyimpanan Terjemahan

- Tabel database `game_translation` menyimpan semua teks terjemahan (group + key + lang_code + value)
- Permintaan pertama memuat semua data dari database ke Redis (key: `i18n:translations`, TTL: 1 jam)
- Permintaan berikutnya langsung membaca dari Redis, cache memori mempercepat
- Backend administrasi dapat memperluas halaman manajemen terjemahan (diimplementasikan di versi lengkap)

### 5.3 Penamaan Kunci Terjemahan

Format: `group.key` seperti `auth.login_success`, `wallet.insufficient_balance`

| Grup | Domain |
|------|------|
| auth | Terkait autentikasi |
| wallet | Terkait dompet |
| exchange | Terkait penukaran |
| withdraw | Terkait penarikan |
| deposit | Terkait deposit |
| game | Terkait game |
| admin | Backend administrasi |
| error | Pesan error |

### 5.4 Strategi Fallback

- Bahasa permintaan memiliki terjemahan yang sesuai → gunakan
- Bahasa permintaan tanpa terjemahan yang sesuai → fallback ke en-US
- en-US juga tidak ada → kembalikan key asli

### 5.5 i18n Frontend

- Flutter menggunakan `AppTranslations` + `LocaleController` (GetX) buatan sendiri
- Preferensi bahasa dipersistensi ke SharedPreferences
- Saat mengganti bahasa, `Get.updateLocale()` memicu render ulang UI global
- Kelas `StringResult` memanfaatkan `toString()` Dart untuk sintaks inline alami: `Text('${AppTranslations.t("key")}')`

## 6. Desain Baru Versi Standar

### 6.1 Mesin Kontrol Risiko

Sebelum operasi dana kritis, eksekusi pemeriksaan aturan multi-lapis:

```
Permintaan deposit/penarikan/penukaran
  ↓
RiskService::check(userId, type, context)
  ├── Deteksi daftar hitam IP (ip_blacklist) → block
  ├── Deteksi anomali jumlah besar (amount_anomaly) → warn
  ├── Deteksi frekuensi (frequency) → warn/block
  └── Deteksi kecepatan (velocity) → block
  ↓
passed → eksekusi normal
warn   → catat log, lanjutkan eksekusi
block  → tolak operasi
```

Aturan disimpan di tabel `game_risk_rule`, dikonfigurasi sebagai JSON, ambang dan tindakan dapat disesuaikan secara dinamis.

### 6.2 Verifikasi Nama Asli KYC

Sistem verifikasi tiga tingkat:
- `default` — belum diverifikasi, batas dasar
- `verified` — lolos review KYC, batas dinaikkan + biaya dikurangi
- `vip` — level VIP, batas tertinggi + nol biaya

Alur verifikasi:
```
Pengguna mengirim informasi dokumen → status=pending
Admin review → approve/reject
approve → pengguna otomatis naik ke level verified
reject → pengguna dapat mengirim ulang
```

### 6.3 Login Pihak Ketiga OAuth

Mendukung login Google / Facebook / Apple:

```
Frontend mengklik tombol OAuth
  → GET /api/auth/oauth/{provider} → dapatkan URL otorisasi
  → lompat ke halaman otorisasi pihak ketiga → pengguna menyetujui
  → callback POST /api/auth/oauth/{provider}/callback
  → cari tautan yang ada → langsung login
  → tanpa tautan → otomatis daftarkan pengguna baru + tautkan + buat dompet
```

### 6.4 Callback Pembayaran

```
Pembayaran pihak ketiga selesai → POST /api/payment/callback
  → validasi daftar putih provider (hanya stripe/paypal)
  → verifikasi tanda tangan fail-closed (tanpa secret/webhook_id, verifikasi tanda tangan gagal, timestamp melebihi ±300s semuanya ditolak)
  → bccomp bandingkan jumlah callback dengan jumlah pesanan (cegah penggunaan lintas saluran)
  → perbarui status pesanan confirmed (transaksional, gagal masuk dana akan rollback)
  → UserWallet::addBalance dana masuk
  → catat Transaction
  → pemeriksaan kontrol risiko RiskService::check
```

### 6.5 Batas Penarikan Bertingkat

Terapkan batas dan biaya berbeda sesuai level KYC pengguna:

| Level | Batas per transaksi | Batas harian | Batas bulanan | Biaya |
|------|---------|--------|--------|--------|
| default | 1,000 | 10,000 | 50,000 | 1.00% |
| verified | 5,000 | 50,000 | 200,000 | 0.50% |
| vip | 20,000 | 200,000 | 1,000,000 | 0.00% |

## 7. Desain Skalabilitas

### 5.1 Skala Horizontal

admin/ dan service/ keduanya mendukung banyak proses worker. Dengan proxy balik Nginx, dapat di-deploy di banyak mesin untuk skala horizontal:

```
Nginx (load balancing)
  ├── admin-1 (:8787)
  ├── admin-2 (:8787)
  ├── service-1 (:8788)
  └── service-2 (:8788)
```

### 5.2 Jalur Pemisahan Modul

Ketika satu service/ menjadi bottleneck, pisahkan sesuai jalur berikut:

```
service/ (monolit)
  → service-user/ (layanan pengguna :8788)
  → service-wallet/ (layanan dompet :8789)
  → service-game/ (layanan game :8790)
  → service-payment/ (layanan pembayaran :8791)
```

Kriteria penentuan waktu pemisahan:
- QPS modul tunggal melebihi kapasitas satu mesin
- Modul tertentu memerlukan tech stack atau strategi deployment independen
- Ukuran tim berkembang hingga perlu mengembangkan modul berbeda secara paralel
