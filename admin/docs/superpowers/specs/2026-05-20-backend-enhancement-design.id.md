# Subproyek A: Peningkatan Backend — Spesifikasi Desain
<!-- lang-nav -->

Languages: [中文](2026-05-20-backend-enhancement-design.md) · [English](2026-05-20-backend-enhancement-design.en.md) · [한국어](2026-05-20-backend-enhancement-design.ko.md) · [Русский](2026-05-20-backend-enhancement-design.ru.md) · [Deutsch](2026-05-20-backend-enhancement-design.de.md) · [Français](2026-05-20-backend-enhancement-design.fr.md) · [Español](2026-05-20-backend-enhancement-design.es.md) · [Português](2026-05-20-backend-enhancement-design.pt.md) · [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) · [العربية](2026-05-20-backend-enhancement-design.ar.md) · [বাংলা](2026-05-20-backend-enhancement-design.bn.md) · **Bahasa Indonesia** · [日本語](2026-05-20-backend-enhancement-design.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Ruang Lingkup

Ini adalah peningkatan backend, total 15 poin fungsi, melibatkan 9 file baru + 4 file yang dimodifikasi.

---

## Daftar File Baru/Dimodifikasi

```
app/middleware/
├── OperationLog.php          # Baru: pencatatan log operasi otomatis
├── Cors.php                  # Baru: CORS
└── RateLimit.php             # Baru: rate limit Redis
app/admin/controller/
├── ConfigController.php      # Baru: CRUD konfigurasi sistem
├── LogController.php         # Baru: kueri log operasi
├── ProfileController.php     # Baru: pusat pribadi (termasuk logout)
├── UploadController.php      # Baru: unggah file
├── ImportController.php      # Baru: impor pengguna Excel
└── HealthController.php      # Baru: pemeriksaan kesehatan
app/model/
├── AdminUser.php             # Modifikasi: tambah trait SoftDeletes + Searchable
└── OperationLog.php          # Modifikasi: tambah public $timestamps = false
app/middleware/
└── AdminAuth.php             # Modifikasi: validasi daftar hitam JWT
app/admin/controller/
├── DashboardController.php   # Modifikasi: ubah menjadi statistik database real-time
└── UserController.php        # Modifikasi: tambah aksi batch
config/
└── route.php                 # Modifikasi: tambah rute + middleware
```

---

## 1. Middleware

### 1.1 Middleware CORS

**File**: `app/middleware/Cors.php`

- Permintaan preflight OPTIONS langsung mengembalikan 204
- Permintaan non-preflight menambahkan `Access-Control-Allow-Origin: *` pada header respons
- Header yang diizinkan: `Authorization, Content-Type, API-Version`
- Cache maksimum: 86400 detik

Pemasangan: middleware global (`config/middleware.php`)

### 1.2 Middleware Rate Limit

**File**: `app/middleware/RateLimit.php`

- Penyimpanan: jendela geser Redis Sorted Set
- Default: 60 kali/menit/IP/rute
- Antarmuka sensitif:
  - `/api/auth/login`: 10 kali/menit
  - `/api/auth/register`: 5 kali/menit
- Melebihi batas mengembalikan `429 Too Many Requests`

Pemasangan: middleware global (`config/middleware.php`), setelah Cors, sebelum ApiVersion

### 1.3 Middleware Log Operasi

**File**: `app/middleware/OperationLog.php`

- Hanya mencatat POST/PUT/DELETE
- Kolom yang dicatat: user_id, action, method, path, ip, input(JSON)
- Ditulis secara asinkron setelah respons dikembalikan (tidak memblokir)

Pemasangan: grup rute `/admin`, setelah AdminPermission

### 1.4 Rantai Eksekusi Middleware Global

```
Semua permintaan:
  Cors → RateLimit → ApiVersion → {Middleware rute} → Controller

Permintaan /admin/*:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Logout (daftar hitam JWT)

**File**: `app/middleware/AdminAuth.php` (modifikasi)

**Prinsip**: JWT sendiri tidak memiliki status; saat logout, token dimasukkan ke daftar hitam Redis, AdminAuth memeriksa daftar hitam terlebih dahulu saat validasi.

**Perubahan AdminAuth**:
- Tambahkan di awal `process()`: periksa dari koleksi `jwt_blacklist` Redis apakah token saat ini ada di daftar hitam
- Jika terkena daftar hitam, kembalikan 401

**Rute logout** (di bawah pusat pribadi):

| Metode | Rute | Keterangan |
|------|------|------|
| `POST` | `/admin/profile/logout` | Menambahkan token Bearer saat ini ke daftar hitam Redis, TTL=sisa masa berlaku token |

**Logika Logout**:
```php
// Parse sisa masa berlaku token
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// Tambahkan ke daftar hitam
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Controller Baru dan Perubahan yang Ada

### 2.1 CRUD Konfigurasi Sistem (`ConfigController`)

Mewarisi `BaseController`.

| Metode | Rute | Keterangan |
|------|------|------|
| `index()` | GET `/admin/config` | Daftar berhalaman, dapat difilter dengan `group`, paginasi `page`/`limit` |
| `store()` | POST `/admin/config` | Membuat item konfigurasi, wajib: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Memperbarui value/type/description item konfigurasi |
| `destroy()` | DELETE `/admin/config/{id}` | Menghapus item konfigurasi, perlu `confirmPassword()` |

### 2.2 Kueri Log Operasi (`LogController`)

Mewarisi `BaseController`.

| Metode | Rute | Keterangan |
|------|------|------|
| `index()` | GET `/admin/log` | Daftar berhalaman, mendukung filter: user_id, action, path, created_at (rentang) |

Tidak menyediakan tambah/hapus/ubah, log dicatat otomatis oleh middleware.

### 2.3 Pusat Pribadi (`ProfileController`)

Mewarisi `BaseController`. Beroperasi pada pengguna yang sedang login (`$request->adminId`).

| Metode | Rute | Keterangan |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Memperbarui real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Mengubah kata sandi, perlu old_password, new_password, new_password_confirmation |

### 2.4 Unggah File (`UploadController`)

Mewarisi `BaseController`.

| Metode | Rute | Keterangan |
|------|------|------|
| `upload()` | POST `/admin/upload` | Menerima file, mendukung image/jpeg/png/gif/pdf/xlsx/docx |

- Maksimal 10MB
- Jalur penyimpanan: `public/upload/{date}/{hash}.{ext}`
- Mengembalikan: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Data Dasbor Nyata

**File**: `app/admin/controller/DashboardController.php` (modifikasi)

Mengubah data palsu yang di-hardcode menjadi statistik database real-time:

| Metrik | Sumber | Keterangan |
|------|------|------|
| Total pengguna | `AdminUser::count()` | Tidak termasuk soft delete |
| Baru hari ini | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Total peran | `AdminRole::count()` | |
| Total izin | `AdminPermission::count()` | |
| Data tren | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Statistik harian pengguna baru 7 hari terakhir |
| Data distribusi | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Distribusi menurut status |
| Operasi terbaru | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | 10 log operasi terbaru |

### 2.6 Operasi Batch Pengguna

**File**: `app/admin/controller/UserController.php` (modifikasi, metode baru)

| Metode | Rute | Keterangan |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Hapus massal, body permintaan `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Aktifkan/nonaktifkan massal, body permintaan `{ ids: [hashid, ...], status: 1|0 }` |

- Setiap id dikonversi ke BIGINT dengan `decodeId()` terlebih dahulu
- `batchDestroy()` harus melewati validasi `confirmPassword()`

### 2.7 Impor Data

**File**: `app/admin/controller/ImportController.php` (baru)

| Metode | Rute | Keterangan |
|------|------|------|
| `users()` | POST `/admin/import/users` | Mengunggah file Excel, membuat pengguna secara massal |

Alur:
1. Menerima file `.xlsx`
2. Parse dengan PhpSpreadsheet, kolom yang diharapkan: `username, password, real_name, phone, email, status`
3. Validasi + buat per baris (ID dihasilkan snowflake, kata sandi bcrypt, phone/email dienkripsi encryption)
4. Mengembalikan hasil: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Pemeriksaan Kesehatan

**File**: `app/admin/controller/HealthController.php` (baru)

`GET /health` (tidak perlu autentikasi, tidak dihitung ke log operasi):

Mengembalikan status koneksi setiap komponen:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- Saat deteksi komponen gagal, nilai kolom terkait adalah string deskripsi error
- Rute tidak diberi prefiks `/admin`, didaftarkan terpisah di global

---

## 3. Perbaikan Model

### 3.1 Timestamp OperationLog

**File**: `app/model/OperationLog.php` (modifikasi)

Tabel `erik_operation_log` hanya memiliki kolom `created_at` (tanpa `updated_at`). `save()` default Eloquent akan mencoba menulis `updated_at`, menyebabkan error SQL.

Perbaikan: `public $timestamps = false;` + tentukan `created_at` secara manual saat menulis.

### 3.2 Perubahan Model AdminUser

- Tambahkan trait `Searchable`
- Implementasikan `toSearchableArray()`: mengembalikan username, real_name
- `UserController::index()` saat mendeteksi kata kunci menggunakan `AdminUser::search($kw)->get()` alih-alih MySQL LIKE

ES perlu membuat indeks terlebih dahulu, dapat menggunakan perintah Scout:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Perubahan Rute

Rute baru di `config/route.php`:

```php
// Tambahkan di dalam grup rute /admin:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// Pemeriksaan kesehatan (rute global, bukan dalam grup /admin)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// Middleware:
tambahkan app\middleware\OperationLog::class pada middleware grup /admin
```

Registrasi middleware global di `config/middleware.php`:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Kode Error Tambahan

| code | Arti | Skenario pemicu |
|------|------|---------|
| 429 | Terlalu banyak permintaan | RateLimit terpicu |

---

## 6. Tidak Termasuk dalam Lingkup Ini

- Sistem notifikasi (membutuhkan antrean pesan + infrastruktur push frontend)
- Halaman frontend Flutter (subproyek B)
- Penyegaran Token HarmonyOS (subproyek C)
