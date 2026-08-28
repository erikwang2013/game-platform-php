# Dokumen Referensi API
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · **Bahasa Indonesia** · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Ringkasan

Backend administrasi terbuka (open-admin) dibangun di atas webman v2, menyediakan RESTful JSON API. Semua antarmuka sisi admin memerlukan autentikasi JWT dan validasi izin RBAC, antarmuka publik dirutekan ke kontroler ber-versi melalui header versi API.

- **URL dasar**: `http://localhost:8787`
- **Versi API**: dikontrol melalui header permintaan `API-Version: v1` (default v1 jika tidak ada)

> **Ringkasan endpoint**: Autentikasi(5) | Dasbor(1) | Pengguna(7) | Peran(4) | Izin(4) | Konfigurasi(4) | Log(1) | Pusat pribadi(3) | Impor ekspor(3) | Unggah(1) | Operasional(4: health/metrics/docs/security.txt) | Total 37 endpoint
- **Autentikasi**: `Authorization: Bearer <token>`（JWT）
- **Format respons**: `{ "code": 0, "message": "success", "data": {...} }`
- **Endpoint dokumen**: `GET /api/docs` mengembalikan spesifikasi JSON OpenAPI 3.0

### Persyaratan Permintaan

- Hanya mengizinkan metode `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD`, penggunaan metode HTTP lain (seperti TRACE, CONNECT, PATCH) akan mengembalikan 405
- Semua permintaan `POST` / `PUT` harus mengatur `Content-Type: application/json` (kecuali unggah file), jika tidak mengembalikan 415
- Ukuran body permintaan tidak boleh melebihi 10MB, jika tidak mengembalikan 413
- Filter keamanan memindai semua input permintaan untuk XSS, injeksi SQL, path traversal, injeksi perintah, jika terdeteksi mengembalikan 403
- 5 kali gagal login berturut-turut akan memicu penguncian akun (15 menit), permintaan login selama masa kunci mengembalikan 429
- Satu pengguna paling banyak memegang 3 Token valid secara bersamaan, jika melebihi Token paling lama otomatis masuk daftar hitam

## 2. Kode Error

| code | Arti | Skenario pemicu |
|------|------|---------|
| 0 | Berhasil | |
| 400 | Parameter permintaan salah | Format permintaan tidak benar |
| 401 | Tidak terautentikasi | Token hilang / kedaluwarsa / sudah di daftar hitam |
| 403 | Tidak ada izin / pemblokiran keamanan | Izin RBAC tidak cukup / terdeteksi SecurityFilter |
| 404 | Sumber daya tidak ada | Target kueri/perbaruan/penghapusan tidak ada |
| 405 | Metode permintaan tidak diizinkan | Hanya mengizinkan GET/POST/PUT/DELETE/OPTIONS/HEAD, metode non-standar langsung ditolak |
| 413 | Body permintaan terlalu besar | Content-Length melebihi 10MB |
| 415 | Tipe media tidak didukung | Content-Type permintaan POST/PUT bukan JSON dan bukan unggah file |
| 422 | Validasi parameter gagal | Kolom wajib hilang, format tidak sesuai, validasi bisnis tidak lulus |
| 429 | Permintaan terlalu sering | Dipicu RateLimit / penguncian akun (5 kali gagal login berturut-turut mengunci 15 menit) |
| 500 | Kesalahan internal server | |

## 3. Endpoint Publik

Semua endpoint publik terpasang di bawah grup `/api`, didistribusikan ke kontroler ber-versi yang sesuai (seperti `app\api\v1\controller\AuthController`) melalui middleware `ApiVersion` sesuai header `API-Version`.

### 3.1 Health Check

```
GET /health
```

- **Autentikasi**: Tidak diperlukan
- **Rate limit**: Tidak ada

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.1",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

Nilai `database`, `redis`, `elasticsearch`: `"ok"` | `"unavailable"`. `elasticsearch` mengembalikan `"unavailable"` saat ES tidak dapat dijangkau, jika status kesehatan cluster bukan green/yellow mengembalikan nilai status aktual (seperti `"red"`).

### 3.2 Dokumen API

```
GET /api/docs
```

- **Autentikasi**: Tidak diperlukan
- **Rate limit**: Default global (60 kali/menit)
- **Respons**: Spesifikasi JSON OpenAPI 3.0.3, berisi semua definisi endpoint, parameter, dan Schema

### 3.3 Membuat CAPTCHA Klik

```
POST /api/captcha/generate
```

- **Autentikasi**: Tidak diperlukan
- **Header permintaan**: `API-Version: v1`（wajib）
- **Rate limit**: Default global (60 kali/menit)

**Body permintaan**:
```json
{
  "difficulty": "medium"
}
```

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| difficulty | string | Tidak | `easy` / `medium` / `hard`, default `medium` |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| Kolom | Tipe | Deskripsi |
|------|------|------|
| key | string | Identitas CAPTCHA, dikirim kembali saat validasi |
| image | string | Gambar PNG berenkode base64 |
| extra.targets[].order | int | Urutan klik |
| extra.targets[].text | string | Teks petunjuk target klik |

### 3.4 Memvalidasi CAPTCHA Klik

```
POST /api/captcha/verify
```

- **Autentikasi**: Tidak diperlukan
- **Header permintaan**: `API-Version: v1`（wajib）
- **Rate limit**: Default global (60 kali/menit)

**Body permintaan**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| key | string | Ya | Kunci CAPTCHA, dikembalikan oleh generate |
| clicks | array{object} | Ya | Array koordinat klik, setiap elemen berisi `x`（int）dan `y`（int） |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

Saat validasi gagal, `code` adalah 422, `message` adalah `"验证失败，请重试"`, `data.valid` adalah `false`.

### 3.5 Login

```
POST /api/auth/login
```

- **Autentikasi**: Tidak diperlukan
- **Header permintaan**: `API-Version: v1`（wajib）
- **Rate limit**: 10 kali/menit (per IP + path)

**Body permintaan**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Kolom | Tipe | Wajib | Aturan validasi | Deskripsi |
|------|------|------|---------|------|
| username | string | Ya | min:3, max:50 | Nama pengguna |
| password | string | Ya | min:6, max:32 | Kata sandi |
| captcha_key | string | Ya | | Kunci CAPTCHA |
| clicks | array{object} | Ya | min:2 | Array koordinat klik |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| Kolom | Tipe | Deskripsi |
|------|------|------|
| access_token | string | Token akses JWT |
| refresh_token | string | Token refresh JWT |
| expires_in | int | Masa berlaku token akses (detik), default 7200 |
| user.id | string | ID pengguna terenkripsi hashid |
| user.username | string | Nama pengguna |
| user.real_name | string | Nama asli |

**Kesalahan yang mungkin**:
- 422: Validasi parameter gagal (kolom wajib hilang, format tidak sesuai)
- 422: CAPTCHA salah, silakan coba lagi
- 401: Nama pengguna atau kata sandi salah
- 403: Akun telah dinonaktifkan
- 429: Akun telah dikunci, silakan coba lagi setelah 15 menit (dipicu 5 kali gagal login berturut-turut)

### 3.6 Registrasi

```
POST /api/auth/register
```

- **Autentikasi**: Tidak diperlukan
- **Header permintaan**: `API-Version: v1`（wajib）
- **Rate limit**: 5 kali/menit (per IP + path)

**Body permintaan**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Kolom | Tipe | Wajib | Aturan validasi | Deskripsi |
|------|------|------|---------|------|
| username | string | Ya | min:3, max:50 | Nama pengguna (unik) |
| password | string | Ya | min:6, max:32 | Kata sandi (disimpan hash bcrypt) |
| real_name | string | Ya | max:50 | Nama asli |
| captcha_key | string | Ya | | Kunci CAPTCHA |
| clicks | array{object} | Ya | min:2 | Array koordinat klik |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

Setelah registrasi berhasil, token JWT langsung dikembalikan, status pengguna default aktif (status=1).

### 3.7 Refresh Token

```
POST /api/auth/refresh
```

- **Autentikasi**: Tidak diperlukan
- **Header permintaan**: `API-Version: v1`（wajib）
- **Rate limit**: Default global (60 kali/menit)

**Body permintaan**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| refresh_token | string | Ya | refresh_token yang didapat saat login/registrasi |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

Refresh yang berhasil mengembalikan access_token dan refresh_token baru sekaligus, token lama otomatis tidak berlaku. Saat refresh, waktu login terakhir dan IP pengguna diperbarui.

**Kesalahan yang mungkin**:
- 422: Refresh token tidak ada
- 401: Refresh token tidak valid atau sudah kedaluwarsa

### 3.8 Metrik Monitoring Prometheus

```
GET /metrics
```

- **Autentikasi**: Tidak diperlukan
- **Rate limit**: Tidak ada
- **Format respons**: Prometheus text format (`text/plain; version=0.0.4`)

Endpoint metrik monitoring Prometheus publik, untuk diambil Grafana/Prometheus.

**Contoh respons**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| Nama metrik | Tipe | Deskripsi |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Total jumlah permintaan HTTP kumulatif |
| `openadmin_active_users` | gauge | Jumlah pengguna aktif saat ini (login dalam 24 jam) |
| `openadmin_db_connection_status` | gauge | Status koneksi database, 1=normal, 0=abnormal |
| `openadmin_redis_connection_status` | gauge | Status koneksi Redis, 1=normal, 0=abnormal |
| `openadmin_memory_usage_bytes` | gauge | Penggunaan memori proses PHP saat ini (bytes) |

## 4. Dasbor

Semua antarmuka sisi admin terpasang di bawah grup `/admin`, melewati tiga middleware `AdminAuth` (autentikasi JWT), `AdminPermission` (validasi izin RBAC), `OperationLog` (pencatatan operasi).

### 4.1 Data Dasbor

```
GET /admin/dashboard
```

- **Autentikasi**: JWT + RBAC
- **Cache**: Redis 5 menit

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| Kolom stats | Tipe | Deskripsi |
|------|------|------|
| label | string | Nama metrik |
| value | string | Nilai metrik (tipe string) |
| icon | string | Nama ikon Material |
| color | string | Nilai warna kartu |
| trend | float? | Tingkat pertumbuhan harian berurutan (persen), hanya "total pengguna" yang memiliki kolom ini |

| Kolom trends | Tipe | Deskripsi |
|------|------|------|
| dates | array{string} | Urutan tanggal 30 hari terakhir |
| series | array{object} | Data garis tren, setiap item berisi name (nama), data (array nilai), color (warna) |

## 5. Manajemen Pengguna

Semua `id` yang dikembalikan antarmuka manajemen pengguna adalah string terenkripsi hashid. Kolom kata sandi telah dikecualikan dari respons. Nomor ponsel dan email ditampilkan teredaksi di antarmuka daftar, dan dikembalikan dalam teks biasa di antarmuka detail (kolom database terenkripsi didekripsi otomatis oleh trait Encryptable).

### 5.1 Daftar Pengguna

```
GET /admin/user
```

- **Autentikasi**: JWT + RBAC

**Parameter kueri**:

| Parameter | Tipe | Wajib | Nilai default | Deskripsi |
|------|------|------|------|------|
| page | int | Tidak | 1 | Nomor halaman |
| limit | int | Tidak | 15 | Jumlah per halaman |
| keyword | string | Tidak | | Kata kunci pencarian, mencocokkan nama pengguna dan nama asli |
| status | int | Tidak | | Filter status, 0=nonaktif, 1=aktif |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| Kolom | Tipe | Deskripsi |
|------|------|------|
| id | string | ID pengguna terenkripsi hashid |
| username | string | Nama pengguna |
| real_name | string | Nama asli |
| phone | string | Nomor ponsel teredaksi (format `138****5678`) |
| email | string | Email teredaksi (format `a***@example.com`) |
| status | int | 1=aktif, 0=nonaktif |
| last_login_at | string | Waktu login terakhir (datetime) |
| created_at | string | Waktu pembuatan (datetime) |

### 5.2 Membuat Pengguna

```
POST /admin/user
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| Kolom | Tipe | Wajib | Aturan validasi | Deskripsi |
|------|------|------|---------|------|
| username | string | Ya | min:3, max:50 | Nama pengguna (unik) |
| password | string | Ya | min:6, max:32 | Kata sandi (disimpan bcrypt) |
| real_name | string | Ya | max:50 | Nama asli |
| phone | string | Tidak | | Nomor ponsel (disimpan terenkripsi Encryptable) |
| email | string | Tidak | | Email (disimpan terenkripsi Encryptable) |
| status | int | Tidak | in:0,1 | Status, default 1 (aktif) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**Kesalahan yang mungkin**:
- 422: Nama pengguna sudah ada
- 422: Validasi parameter gagal (kolom wajib hilang)

### 5.3 Detail Pengguna

```
GET /admin/user/{id}
```

- **Autentikasi**: JWT + RBAC
- **Parameter path**: `{id}` adalah ID pengguna terenkripsi hashid

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

Di antarmuka detail, `phone` dan `email` dikembalikan dalam teks biasa (di database tersimpan terenkripsi, didekripsi otomatis oleh cast Encryptable), tidak teredaksi. `password` dan `id_card` tidak pernah ada di respons.

**Kesalahan yang mungkin**:
- 404: Pengguna tidak ada

### 5.4 Memperbarui Pengguna

```
PUT /admin/user/{id}
```

- **Autentikasi**: JWT + RBAC
- **Parameter path**: `{id}` adalah ID pengguna terenkripsi hashid

**Body permintaan**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| real_name | string | Tidak | Nama asli, jika tidak dikirim tetap nilai lama |
| password | string | Tidak | Kata sandi baru, string kosong atau tidak dikirim berarti tidak diubah |
| phone | string | Tidak | Nomor ponsel |
| email | string | Tidak | Email |
| status | int | Tidak | 0=nonaktif, 1=aktif |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**Kesalahan yang mungkin**:
- 404: Pengguna tidak ada

### 5.5 Menghapus Pengguna

```
DELETE /admin/user/{id}
```

- **Autentikasi**: JWT + RBAC
- **Parameter path**: `{id}` adalah ID pengguna terenkripsi hashid
- **Operasi sensitif**: memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "password": "admin_password"
}
```

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| password | string | Ya | Kata sandi pengguna yang sedang login (konfirmasi ulang) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Melakukan soft delete (Eloquent SoftDeletes), data ditandai deleted_at tanpa dihapus fisik.

**Kesalahan yang mungkin**:
- 404: Pengguna tidak ada
- 422: Operasi sensitif perlu memasukkan kata sandi untuk konfirmasi (password kosong)
- 422: Validasi kata sandi gagal (kata sandi tidak cocok)

### 5.6 Menghapus Pengguna Massal

```
POST /admin/user/batch/destroy
```

- **Autentikasi**: JWT + RBAC
- **Operasi sensitif**: memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| ids | array{string} | Ya | Array ID pengguna terenkripsi hashid |
| password | string | Ya | Kata sandi pengguna yang sedang login (konfirmasi ulang) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Melakukan soft delete, `data.count` adalah jumlah yang benar-benar dihapus.

**Kesalahan yang mungkin**:
- 422: Silakan pilih pengguna yang akan dihapus (ids kosong)
- 422: ID tidak valid (gagal dekode hashid)
- 422: Validasi kata sandi gagal

### 5.7 Mengaktifkan/Menonaktifkan Pengguna Massal

```
POST /admin/user/batch/status
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| ids | array{string} | Ya | Array ID pengguna terenkripsi hashid |
| status | int | Ya | 0=nonaktif, 1=aktif |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message berubah dinamis sesuai nilai status menjadi `"批量启用成功"` atau `"批量禁用成功"`.

**Kesalahan yang mungkin**:
- 422: Silakan pilih pengguna (ids kosong)
- 422: Nilai status tidak valid (status bukan 0 atau 1)

## 6. Manajemen Peran

### 6.1 Daftar Peran

```
GET /admin/role
```

- **Autentikasi**: JWT + RBAC

**Parameter kueri**:

| Parameter | Tipe | Wajib | Nilai default | Deskripsi |
|------|------|------|------|------|
| page | int | Tidak | 1 | Nomor halaman |
| limit | int | Tidak | 15 | Jumlah per halaman |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| Kolom | Tipe | Deskripsi |
|------|------|------|
| id | string | ID peran terenkripsi hashid |
| name | string | Nama peran |
| slug | string | Identitas peran (unik, digunakan untuk penilaian izin) |
| description | string | Deskripsi peran |
| status | int | 1=aktif, 0=nonaktif |
| users_count | int | Jumlah pengguna yang memiliki peran ini |

### 6.2 Membuat Peran

```
POST /admin/role
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Kolom | Tipe | Wajib | Aturan validasi | Deskripsi |
|------|------|------|---------|------|
| name | string | Ya | max:50 | Nama peran |
| slug | string | Ya | max:50 | Identitas peran |
| description | string | Tidak | | Deskripsi peran, default string kosong |
| status | int | Tidak | | Status, default 1 |
| permission_ids | array{int} | Tidak | | Array ID izin (ID INT asli, bukan hashid) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 Memperbarui Peran

```
PUT /admin/role/{id}
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| name | string | Tidak | Nama peran |
| description | string | Tidak | Deskripsi |
| status | int | Tidak | 0=nonaktif, 1=aktif |
| permission_ids | array{int} | Tidak | Array ID izin, jika dikirim akan disinkronkan (menimpa) izin peran |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 Menghapus Peran

```
DELETE /admin/role/{id}
```

- **Autentikasi**: JWT + RBAC
- **Operasi sensitif**: memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "password": "admin_password"
}
```

**Contoh respons**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Saat menghapus, hubungan antara peran dengan semua izin dan pengguna otomatis dilepas, kemudian catatan peran dihapus fisik.

## 7. Manajemen Izin

Izin menggunakan struktur pohon (self-referensi parent_id), dibagi menjadi tiga jenis. Antarmuka daftar mengembalikan pohon izin lengkap.

### 7.1 Pohon Izin

```
GET /admin/permission
```

- **Autentikasi**: JWT + RBAC

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| Kolom | Tipe | Deskripsi |
|------|------|------|
| id | string | Terenkripsi hashid |
| parent_id | string | hashid izin induk, "0" berarti node akar |
| name | string | Nama izin |
| slug | string | Identitas izin (identitas rute/tombol) |
| type | int | 1=menu, 2=tombol, 3=antarmuka |
| icon | string | Ikon menu (nama ikon Material) |
| path | string | Path rute frontend |
| sort | int | Nilai urutan (ascending) |
| children | array? | Daftar izin anak (rekursif), kolom ini tidak ada jika tidak ada node anak |

### 7.2 Membuat Izin

```
POST /admin/permission
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| Kolom | Tipe | Wajib | Aturan validasi | Deskripsi |
|------|------|------|---------|------|
| parent_id | int | Tidak | | ID izin induk (tipe INT asli), default 0 |
| name | string | Ya | max:50 | Nama izin |
| slug | string | Ya | max:100 | Identitas izin |
| type | int | Ya | in:1,2,3 | 1=menu, 2=tombol, 3=antarmuka |
| icon | string | Tidak | | Ikon menu, default kosong |
| path | string | Tidak | | Path rute frontend, default kosong |
| sort | int | Tidak | | Nilai urutan, default 0 |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 Memperbarui Izin

```
PUT /admin/permission/{id}
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| name | string | Tidak | Nama izin |
| icon | string | Tidak | Ikon |
| path | string | Tidak | Path rute |
| sort | int | Tidak | Nilai urutan |

### 7.4 Menghapus Izin

```
DELETE /admin/permission/{id}
```

- **Autentikasi**: JWT + RBAC
- **Operasi sensitif**: memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "password": "admin_password"
}
```

**Contoh respons**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Saat menghapus, semua sub-izin dihapus secara kaskade (catatan dengan `parent_id` = ID izin saat ini), sekaligus melepas hubungan dengan semua peran.

## 8. Konfigurasi Sistem

Konfigurasi sistem unik berdasarkan kombinasi `group` + `key`.

### 8.1 Daftar Konfigurasi

```
GET /admin/config
```

- **Autentikasi**: JWT + RBAC

**Parameter kueri**:

| Parameter | Tipe | Wajib | Nilai default | Deskripsi |
|------|------|------|------|------|
| page | int | Tidak | 1 | Nomor halaman |
| limit | int | Tidak | 15 | Jumlah per halaman |
| group | string | Tidak | | Filter berdasarkan grup konfigurasi |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| Kolom | Tipe | Deskripsi |
|------|------|------|
| id | string | hashid |
| group | string | Grup konfigurasi (seperti `system`, `email`, `storage`) |
| key | string | Kunci konfigurasi |
| value | string | Nilai konfigurasi |
| type | string | Petunjuk tipe nilai (`string`, `integer`, `boolean`, `json`, dll.) |
| description | string | Deskripsi konfigurasi |

### 8.2 Membuat Konfigurasi

```
POST /admin/config
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Kolom | Tipe | Wajib | Aturan validasi | Deskripsi |
|------|------|------|---------|------|
| group | string | Ya | max:100 | Grup konfigurasi |
| key | string | Ya | max:100 | Kunci konfigurasi (unik dalam grup yang sama) |
| value | string | Ya | | Nilai konfigurasi |
| type | string | Tidak | | Tipe nilai, default `string` |
| description | string | Tidak | | Deskripsi konfigurasi, default kosong |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**Kesalahan yang mungkin**:
- 422: Item konfigurasi sudah ada (group + key yang sama)

### 8.3 Memperbarui Konfigurasi

```
PUT /admin/config/{id}
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| value | string | Tidak | Perbarui nilai konfigurasi |
| type | string | Tidak | Perbarui tipe nilai |
| description | string | Tidak | Perbarui teks deskripsi |

### 8.4 Menghapus Konfigurasi

```
DELETE /admin/config/{id}
```

- **Autentikasi**: JWT + RBAC
- **Operasi sensitif**: memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "password": "admin_password"
}
```

Menghapus fisik catatan konfigurasi.

## 9. Log Operasi

Log operasi adalah antarmuka read-only, ditulis otomatis oleh middleware `OperationLog` pada setiap permintaan POST/PUT/DELETE, kolom penyimpanan meliputi `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Daftar Log Operasi

```
GET /admin/log
```

- **Autentikasi**: JWT + RBAC

**Parameter kueri**:

| Parameter | Tipe | Wajib | Nilai default | Deskripsi |
|------|------|------|------|------|
| page | int | Tidak | 1 | Nomor halaman |
| limit | int | Tidak | 15 | Jumlah per halaman |
| user_id | int | Tidak | | Filter presisi berdasarkan ID pengguna (tipe INT asli) |
| action | string | Tidak | | Filter presisi berdasarkan aksi operasi |
| path | string | Tidak | | Filter fuzzy berdasarkan path permintaan |
| start_date | string | Tidak | | Tanggal mulai (format Y-m-d) |
| end_date | string | Tidak | | Tanggal selesai (format Y-m-d) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| Kolom | Tipe | Deskripsi |
|------|------|------|
| id | string | hashid |
| user_name | string | Nama pengguna operasi (didapat melalui relasi user, operasi tanpa login menampilkan "sistem") |
| action | string | Deskripsi aksi operasi |
| method | string | Metode HTTP (POST/PUT/DELETE) |
| path | string | Path permintaan |
| ip | string | IP klien |
| source | string | Sumber permintaan |
| input | string | String JSON parameter permintaan (tidak termasuk file) |
| created_at | string | Waktu operasi (datetime) |

## 10. Pusat Pribadi

Antarmuka pusat pribadi hanya memerlukan autentikasi JWT (tidak memerlukan validasi izin RBAC — middleware `AdminPermission` harus menambahkannya ke daftar putih).

### 10.1 Memperbarui Informasi Pribadi

```
PUT /admin/profile
```

- **Autentikasi**: JWT

**Body permintaan**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| real_name | string | Tidak | Nama asli |
| phone | string | Tidak | Nomor ponsel (disimpan terenkripsi Encryptable) |
| email | string | Tidak | Email (disimpan terenkripsi Encryptable) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

Dalam respons, `phone` dan `email` dikembalikan dalam teks biasa, `password` dan `id_card` telah dihilangkan.

### 10.2 Mengubah Kata Sandi

```
PUT /admin/profile/password
```

- **Autentikasi**: JWT

**Body permintaan**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Kolom | Tipe | Wajib | Aturan validasi | Deskripsi |
|------|------|------|---------|------|
| old_password | string | Ya | | Kata sandi saat ini |
| new_password | string | Ya | min:6, max:32 | Kata sandi baru |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Kesalahan yang mungkin**:
- 422: Silakan isi kata sandi lama dan kata sandi baru
- 422: Kata sandi lama salah
- 422: Panjang kata sandi baru 6-32 karakter

### 10.3 Logout

```
POST /admin/profile/logout
```

- **Autentikasi**: JWT

**Body permintaan**: Tidak ada (tanpa requestBody, baca token dari header Authorization)

**Contoh respons**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Logika logout: dekode JWT untuk mendapatkan sisa masa berlaku (exp - now), tulis hash md5 token tersebut ke daftar hitam Redis `jwt_blacklist:{md5}`, TTL = sisa masa berlaku. Token di daftar hitam diblokir di middleware `AdminAuth`, mengembalikan 401.

Tanpa token mengembalikan 401. Token kedaluwarsa/tidak valid (pengecualian saat dekode) tetap dianggap logout berhasil.

## 11. Impor & Ekspor

### 11.1 Ekspor Excel

```
POST /admin/export/excel
```

- **Autentikasi**: JWT + RBAC
- **Tipe respons**: Unduhan file (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Body permintaan**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| Kolom | Tipe | Wajib | Nilai default | Deskripsi |
|------|------|------|------|------|
| table | string | Tidak | `admin_user` | Nama tabel ekspor. Didukung: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | Tidak | | Array nama kolom yang diekspor, kosong berarti mengekspor semua kolom tabel |
| conditions | object | Tidak | `{}` | Kondisi filter, pasangan key-value, digunakan untuk WHERE saat nilai tidak kosong |
| title | string | Tidak | `数据导出` | Judul Excel (ditampilkan sebagai nama Sheet) |

**Tabel dan kolom yang didukung**:

| table | Kolom yang tersedia |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Kolom sensitif `phone`, `email`, `id_card` otomatis diredaksi saat ekspor. Batas data 10000 baris. Baris pertama Excel dikunci, filter otomatis.

### 11.2 Ekspor PDF

```
POST /admin/export/pdf
```

- **Autentikasi**: JWT + RBAC
- **Tipe respons**: Unduhan file (`application/pdf`, A4 lanskap)

**Body permintaan**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

Atau mode tabel:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| Kolom | Tipe | Wajib | Nilai default | Deskripsi |
|------|------|------|------|------|
| type | string | Tidak | `table` | Tipe ekspor: `table` / `dashboard` |
| title | string | Tidak | `数据导出` | Judul PDF |
| data | object | Tidak | `{}` | Data ekspor |

Saat `type=dashboard`, `data` harus berisi array `stats` (dirender dalam bentuk kartu); saat `type=table`, `data` harus berisi array `columns` dan `rows`.

Template PDF berisi informasi hak cipta dan timestamp ekspor.

### 11.3 Impor Pengguna (Excel)

```
POST /admin/import/users
```

- **Autentikasi**: JWT + RBAC
- **Tipe permintaan**: `multipart/form-data`（unggah file）

**Kolom form**:

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| file | file | Ya | Format `.xlsx` atau `.xls` |

**Persyaratan kolom Excel**:

| Nama kolom | Wajib | Deskripsi |
|------|------|------|
| username | Ya | Nama pengguna (unik) |
| password | Ya | Kata sandi (disimpan hash bcrypt) |
| real_name | Ya | Nama asli |
| phone | Tidak | Nomor ponsel |
| email | Tidak | Email |
| status | Tidak | Status, default 1 |

Baris 1 adalah judul kolom (tidak sensitif huruf besar/kecil), mulai baris 2 adalah data.

**Contoh respons**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| Kolom | Tipe | Deskripsi |
|------|------|------|
| total | int | Total baris (tidak termasuk baris judul) |
| success | int | Jumlah berhasil diimpor |
| failed | int | Jumlah gagal |
| errors | array | Detail kegagalan, setiap item berisi row (nomor baris Excel) dan reason (alasan kegagalan) |

## 12. Unggah File

```
POST /admin/upload
```

- **Autentikasi**: JWT + RBAC
- **Tipe permintaan**: `multipart/form-data`

**Kolom form**:

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| file | file | Ya | File yang diunggah |

**Tipe file yang diizinkan**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Ukuran file maksimal**: 10MB

**Contoh respons**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

File disimpan dalam direktori terpisah per tanggal di `public/upload/{Y-m-d}/`, nama file adalah `md5(uniqid) + ekstensi asli`. `url` adalah path relatif terhadap root situs.

**Kesalahan yang mungkin**:
- 422: Silakan pilih file (tidak diunggah)
- 422: Tipe file tidak didukung
- 422: Ukuran file tidak boleh melebihi 10MB
- 500: Gagal unggah file (file tidak valid)

## 13. Header Respons

Semua antarmuka (disuntikkan pada lapisan middleware global) berisi header respons berikut:

| Header | Deskripsi |
|----|------|
| `X-RateLimit-Limit` | Batas rate limit (jumlah) |
| `X-RateLimit-Remaining` | Sisa jumlah permintaan |
| `X-RateLimit-Reset` | Timestamp reset jendela rate limit |
| `Retry-After` | Hanya dikembalikan saat rate limit terpicu, detik yang disarankan untuk menunggu |
| `X-Content-Type-Options` | `nosniff` (default webman, melarang MIME sniffing) |
| `X-Frame-Options` | `DENY` (disediakan middleware CORS/konfigurasi dasar webman) |

Detail rate limit:
- Limit global default: 60 kali/menit / IP+path
- Endpoint login `/api/auth/login`: 10 kali/menit
- Endpoint registrasi `/api/auth/register`: 5 kali/menit
- Menggunakan algoritma jendela geser atomik Redis (Lua ZSET), menghindari race condition TOCTOU
- Saat Redis tidak tersedia fail-closed: mengembalikan 503 (`Retry-After: 5`), tidak meloloskan permintaan

## 14. Analisis Data (Analytics)

Semua endpoint memerlukan autentikasi (`AdminAuth` + `AdminPermission`), agregasi real-time MySQL, total 12:

| Metode | Path | Deskripsi |
|------|------|------|
| GET | /admin/analytics/overview | Ringkasan platform (hari ini/7 hari terakhir) |
| GET | /admin/analytics/game-ranking | Peringkat game (?days=7) |
| GET | /admin/analytics/dau-trend | Tren DAU (?days=30) |
| GET | /admin/analytics/hourly-trend | Tren per jam |
| GET | /admin/analytics/action-distribution | Distribusi perilaku |
| GET | /admin/analytics/revenue | Analisis pendapatan |
| GET | /admin/analytics/conversion | Tingkat konversi game |
| GET | /admin/analytics/probability | Probabilitas gabungan/kondisional |
| GET | /admin/analytics/retention | Analisis retensi D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Funnel konversi |
| GET | /admin/analytics/arpu | Tren ARPU/ARPPU |
| GET | /admin/analytics/economy | Metrik ekonomi mata uang game |

## 15. Manajemen Tiket (Ticket)

Semua endpoint memerlukan autentikasi (`AdminAuth` + `AdminPermission`), total 5:

| Metode | Path | Deskripsi |
|------|------|------|
| GET | /admin/ticket/list | Daftar tiket (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | Detail tiket (termasuk balasan) |
| POST | /admin/ticket/{hashid}/reply | Membalas tiket |
| POST | /admin/ticket/{hashid}/close | Menutup tiket |
| POST | /admin/ticket/{hashid}/assign | Menugaskan penangan (admin_id) |

## 16. Alur Autentikasi

Urutan autentikasi lengkap:

```
1. Klien meminta POST /api/captcha/generate
   (Header permintaan: API-Version: v1)
    ↓
   Server mengembalikan: key + gambar base64 + petunjuk target klik

2. Pengguna mengklik posisi target pada gambar, frontend/klien mengumpulkan koordinat klik

3. Klien meminta POST /api/auth/login
   (Header permintaan: API-Version: v1, Content-Type: application/json)
   Body permintaan: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   Server:
   a. Validasi parameter → 422
   b. Validasi CAPTCHA → 422
   c. Validasi kredensial pengguna → 401
   d. Periksa status akun → 403
   e. Terbitkan JWT (access + refresh) → 200
   f. Perbarui last_login_at / last_login_ip
    ↓
   Klien menyimpan: access_token, refresh_token, expires_in

4. Permintaan selanjutnya membawa JWT
   Header permintaan: Authorization: Bearer <access_token>
    ↓
   Middleware AdminAuth:
   a. Ekstrak token Bearer
   b. Periksa daftar hitam (Redis jwt_blacklist:{md5}) → 401
   c. Dekode JWT, validasi kedaluwarsa → 401
   d. Setel $request->adminId = kolom sub
    ↓
   Middleware AdminPermission:
   a. Belum login (adminId kosong) → 401
   b. Parsing identitas izin untuk rute sumber daya
   c. Kueri peran pengguna → izin peran, lakukan pencocokan
   d. Tidak ada izin → 403
    ↓
   Controller memproses permintaan
    ↓
   Response + header X-RateLimit-*

5. Refresh sebelum Access Token kedaluwarsa
   Klien meminta POST /api/auth/refresh
   Body permintaan: { refresh_token: "..." }
    ↓
   Server mendekode refresh_token → menerbitkan access + refresh baru
    ↓
   Klien memperbarui token lokal

6. Logout
   Klien meminta POST /admin/profile/logout
   Header permintaan: Authorization: Bearer <access_token>
    ↓
   Server:
   a. Dekode JWT untuk mendapatkan sisa TTL
   b. Tulis ke daftar hitam Redis: jwt_blacklist:{md5(token)} = 1, TTL = sisa masa berlaku
   c. Kembalikan sukses
```

### Struktur JWT

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, TTL default 7200 detik (dikontrol konfigurasi JWT `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, TTL default 1209600 detik (dikontrol konfigurasi JWT `refresh_expire`, yaitu 14 hari)

### Manajemen Keamanan

- Kata sandi disimpan sebagai hash `PASSWORD_BCRYPT`
- Kolom sensitif (phone, email, id_card) menggunakan `erikwang2013/encryptable` untuk enkripsi/dekripsi transparan di lapisan database
- ID di lapisan API dienkripsi dengan `erikwang2013/hashids` untuk transmisi, menghindari ekspos urutan ID snowflake asli
- SecurityFilter memindai XSS, injeksi SQL, path traversal, injeksi perintah secara global, IP yang sama 5 kali/60 detik masuk daftar hitam sementara 15 menit
- Operasi sensitif (menghapus pengguna, peran, izin, konfigurasi) memerlukan konfirmasi ulang kata sandi pengguna yang sedang login
- Batasan sesi bersamaan: maksimal 3 Token valid per pengguna, saat perangkat ke-4 login, Token paling lama dipaksa masuk daftar hitam
- Penguncian akun: 5 kali gagal login berturut-turut memicu penguncian akun 15 menit, selama masa kunci mengembalikan 429

## 15. Deployment & Operasional

### Docker Compose

Direktori root proyek menyediakan `docker-compose.yml`, mengorkestrasi 5 layanan (Nginx, aplikasi webman, MySQL, Redis, Elasticsearch). PHP dibangun melalui `Dockerfile` (berbasis `php:8.3-cli`, OPcache diaktifkan).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` mendefinisikan pipeline integrasi berkelanjutan GitHub Actions:
- Pemeriksaan sintaks `php -l`
- Pengujian unit PHPUnit
- Analisis statis `flutter analyze`

### Backup Database

Direktori `database/backup/` menyediakan skrip backup dan pemulihan:
- `backup.sh` — backup kompresi mysqldump + gzip, otomatis membersihkan file backup lama lebih dari 30 hari
- `restore.sh` — pemulihan interaktif, mencantumkan backup yang ada untuk dipilih pengguna

### Konfigurasi Keamanan Nginx

Untuk deployment produksi, lihat `docs/nginx-security.conf` untuk konfigurasi penguatan keamanan proxy balik.

## 16. Analisis Data (Analytics)

Antarmuka analisis data disediakan oleh `AnalyticsController`, semuanya berbasis agregasi real-time MySQL (`game_game_play_log` log perilaku game / `game_deposit_order` pesanan deposit), saat database bermasalah mengembalikan data kosong bukan 500. Kecuali disebutkan khusus, semuanya memerlukan autentikasi JWT + RBAC, format pembungkus respons terpadu `{ "code": 0, "message": "success", "data": ... }`.

### 16.1 Ringkasan Platform

```
GET /admin/analytics/overview
```

**Respons**: `today` / `week` masing-masing berisi `dau` (jumlah pengguna aktif), `revenue` (total deposit terkonfirmasi, string), `new_users` (jumlah pengguna baru).

### 16.2 Peringkat Game

```
GET /admin/analytics/game-ranking?days=7
```

**Respons**: 10 teratas diurutkan menurun berdasarkan jumlah perilaku game, setiap item berisi `game_id`（hashid）、`name`、`plays`、`players`.

### 16.3 Tren DAU

```
GET /admin/analytics/dau-trend?days=30
```

**Respons**: `{ "日期": 活跃数, ... }`, tanggal yang hilang diisi 0.

### 16.4 Tren Per Jam

```
GET /admin/analytics/hourly-trend?game_id=<hashid>
```

**Respons**: `{ "0": 次数, ... "23": 次数 }` 24 slot jam penuh; saat `game_id` kosong menghitung semua game.

### 16.5 Distribusi Perilaku

```
GET /admin/analytics/action-distribution?game_id=<hashid>&hours=24
```

**Respons**: `{ "start": n, "end": n, "earn": n, "spend": n }` empat jenis penghitungan perilaku; `hours` maksimal 168.

### 16.6 Ringkasan Pendapatan

```
GET /admin/analytics/revenue?days=7
```

**Respons**: `{ "total": "总额", "trend": { "日期": "当日额", ... } }`, hanya menghitung pesanan `status=confirmed`.

### 16.7 Tingkat Konversi Game

```
GET /admin/analytics/conversion?days=30
```

**Respons**: Setiap game berisi `game_id`（hashid）、`game_name`、`players`（jumlah pemain unik）、`depositors`（jumlah penyetor unik）、`conversion_rate`（tingkat konversi deposit, 0~1）.

### 16.8 Probabilitas Gabungan

```
GET /admin/analytics/probability?game_a=<hashid>&game_b=<hashid>
```

**Respons**: `{ "joint": { "joint_probability": 0.12, "confidence": 0.3 } }` — koefisien Jaccard (pemain bersama dua game / gabungan pemain) dan confidence (pemain bersama / pemain game A).

### 16.9 Analisis Retensi

```
GET /admin/analytics/retention?days=30
```

**Respons**: `{ "D1": "8.5%", "D3": "...", "D7": "...", "D30": "..." }` tingkat retensi hari ke-1/3/7/30 berdasarkan grup tanggal registrasi.

### 16.10 Funnel Konversi

```
GET /admin/analytics/funnel?days=30
```

**Respons**: Empat langkah registrasi → deposit pertama → penukaran pertama → game pertama, berisi `step`、`count`、`rate`（persentase relatif terhadap jumlah registrasi）.

### 16.11 Tren ARPU/ARPPU

```
GET /admin/analytics/arpu?days=30
```

**Respons**: `{ "dates": [...], "arpu": [...], "arppu": [...] }` pendapatan rata-rata per pengguna harian (ARPU) dan pendapatan rata-rata per pengguna pembayar (ARPPU).

### 16.12 Metrik Ekonomi Game

```
GET /admin/analytics/economy
```

**Respons**: array `currencies`, setiap item berisi `game_name`、`currency`、`symbol`、`total_minted`（total mint）、`total_burned`（total burn）、`circulation`（jumlah beredar）、`inflation_rate`（tingkat inflasi）, menggunakan perhitungan presisi tinggi bcmath.

## 17. Manajemen Pembayaran (Payment)

Manajemen metode pembayaran disediakan oleh `PaymentController`; 5 endpoint semuanya memerlukan autentikasi JWT + RBAC. Daftar putih `provider`: `stripe` / `nowpayments` / `coinbase`. `config` adalah string JSON konfigurasi pembayaran (disimpan terenkripsi di database).

| Metode | Jalur | Deskripsi |
|------|------|------|
| GET | /admin/payment/method/list | Daftar metode pembayaran (ascending by sort) |
| POST | /admin/payment/method/toggle | Aktifkan/nonaktifkan metode pembayaran |
| POST | /admin/payment/method/create | Buat metode pembayaran |
| PUT | /admin/payment/method/{hashid} | Perbarui metode pembayaran |
| DELETE | /admin/payment/method/{hashid} | Hapus metode pembayaran (ditolak jika ada pesanan pending) |

### 17.1 Daftar Metode Pembayaran

```
GET /admin/payment/method/list
```

- **Autentikasi**: JWT + RBAC

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "name": "Kartu Kredit Stripe",
        "type": "fiat",
        "provider": "stripe",
        "status": 1,
        "sort": 1,
        "countries": ["US", "SG"],
        "currency": "USD",
        "min_amount": "10",
        "max_amount": "5000",
        "config": null,
        "created_at": "2026-08-29 10:00:00",
        "updated_at": "2026-08-29 10:00:00"
      }
    ]
  }
}
```

| Kolom | Tipe | Deskripsi |
|------|------|------|
| id | string | ID metode pembayaran (dikodekan hashid) |
| name | string | Nama metode pembayaran |
| type | string | `fiat` (mata uang fiat) / `crypto` (kripto) |
| provider | string | Penyedia gateway: `stripe` / `nowpayments` / `coinbase` |
| status | int | 1=aktif, 0=nonaktif |
| sort | int | Nilai urutan (ascending) |
| countries | array{string} | Array kode negara yang terlihat (array kosong = terlihat global) |
| currency | string | Mata uang (mis. USD/USDT), kosong = tanpa batasan |
| min_amount / max_amount | string | Rentang jumlah (string menjaga presisi), 0 = tanpa batas |
| config | string? | JSON konfigurasi pembayaran (terenkripsi; null jika tidak diatur) |

### 17.2 Aktifkan/Nonaktifkan Metode Pembayaran

```
POST /admin/payment/method/toggle
```

**Badan permintaan**:
```json
{
  "id": "a1b2c3d4",
  "status": 1
}
```

| Kolom | Tipe | Wajib | Deskripsi |
|------|------|------|------|
| id | string | Ya | ID metode pembayaran (hashid) |
| status | int | Ya | 0=nonaktif, 1=aktif |

**Kemungkinan error**:
- 422: validasi gagal (id/status hilang atau status bukan 0/1)
- 404: metode pembayaran tidak ditemukan

### 17.3 Buat Metode Pembayaran

```
POST /admin/payment/method/create
```

**Badan permintaan**:
```json
{
  "name": "Kripto USDT",
  "type": "crypto",
  "provider": "nowpayments",
  "status": 1,
  "sort": 2,
  "countries": [],
  "currency": "USDT",
  "min_amount": "10",
  "max_amount": "10000",
  "config": "{\"api_key\":\"...\"}"
}
```

| Kolom | Tipe | Wajib | Validasi | Deskripsi |
|------|------|------|---------|------|
| name | string | Ya | max:50 | Nama metode pembayaran |
| type | string | Ya | in:fiat,crypto | Tipe: fiat/kripto |
| provider | string | Ya | in:stripe,nowpayments,coinbase | Daftar putih penyedia gateway |
| status | int | Ya | in:0,1 | Status |
| sort | int | Tidak | integer,min:0 | Urutan, default 0 |
| countries | array{string} | Tidak | max:2 | Kode negara terlihat, kosong = global |
| currency | string | Tidak | max:10 | Mata uang, default kosong |
| min_amount / max_amount | string | Tidak | numeric,min:0 | Rentang jumlah, default "0" |
| config | string | Tidak | | JSON konfigurasi pembayaran (terenkripsi); string kosong disimpan sebagai NULL |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "berhasil dibuat",
  "data": { "id": "e5f6g7h8" }
}
```

**Kemungkinan error**:
- 422: validasi gagal

### 17.4 Perbarui Metode Pembayaran

```
PUT /admin/payment/method/{hashid}
```

- **Parameter jalur**: `{hashid}` adalah ID metode pembayaran yang dikodekan hashid
- **Badan permintaan**: sama dengan buat (17.3), semua kolom opsional, hanya kolom yang dikirim yang diperbarui

**Kemungkinan error**:
- 404: metode pembayaran tidak ditemukan
- 422: validasi gagal

### 17.5 Hapus Metode Pembayaran

```
DELETE /admin/payment/method/{hashid}
```

- **Parameter jalur**: `{hashid}` adalah ID metode pembayaran yang dikodekan hashid

**Kemungkinan error**:
- 404: metode pembayaran tidak ditemukan
- 422: ada pesanan deposit pending (status=pending), tidak dapat dihapus
