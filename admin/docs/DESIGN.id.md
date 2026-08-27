# Backend Administrasi Terbuka — Dokumen Desain
<!-- lang-nav -->

Languages: [中文](DESIGN.md) · [English](DESIGN.en.md) · [한국어](DESIGN.ko.md) · [Русский](DESIGN.ru.md) · [Deutsch](DESIGN.de.md) · [Français](DESIGN.fr.md) · [Español](DESIGN.es.md) · [Português](DESIGN.pt.md) · [हिन्दी](DESIGN.hi.md) · [العربية](DESIGN.ar.md) · [বাংলা](DESIGN.bn.md) · **Bahasa Indonesia** · [日本語](DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Untuk diagram Mermaid yang detail, lihat [ARCHITECTURE.md](ARCHITECTURE.id.md)（dapat dirender otomatis di GitHub/GitLab/VS Code）.

## 1. Arsitektur Sistem

> **Daftar fitur**: Autentikasi (login/register/refresh/logout + penguncian akun + batasan sesi) | Dasbor (cache Redis) | CRUD pengguna + massal + impor | Peran & izin (RBAC) | Konfigurasi sistem | Audit operasi (8 platform sumber) | File (unggah + ekspor + redaksi) | Keamanan (18 lapisan pertahanan) | Operasional (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Arsitektur Backend

### 2.1 Desain Berlapis

| Lapisan | Direktori | Tanggung jawab |
|---|------|------|
| Rute | `config/route.php` | Pemetaan URL ke kontroler, pengikatan middleware, rute ber-versi |
| Middleware | `app/middleware/` | Pemblokiran serangan (SecurityFilter), rate limit (RateLimit), autentikasi (JWT), otorisasi (RBAC), versi API (ApiVersion) |
| Kontroler | 30: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs/Metrics/Analytics/Game/Payment/Withdraw... (sisi admin) + Captcha/Auth (API v1) | Validasi parameter permintaan, pemanggilan logika bisnis, format respons |
| Layanan bisnis | `common/service/` | Analisis data: GameDashboardService（ringkasan/peringkat/trend）、DepositLogService（pendapatan/konversi）、ProbabilityService（probabilitas gabungan/kondisional, pembangun SQL）；saat DB bermasalah mengembalikan data kosong bukan error |
| Model data | `app/model/` | Pemetaan ORM, relasi, enkripsi/dekripsi kolom |
| Utilitas publik | `app/common/` | Layanan Hashids, Snowflake, Encryption |

### 2.2 Siklus Hidup Permintaan

```
客户端请求
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 匹配
  │
  ▼
中间件链:
  SecurityFilter ──────► HTTP方法检查 → 405 (仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截 (403)
  ▼
RateLimit ───────────► Redis 滑动窗口限流
  │ (失败返回 429 + Retry-After 头)
  ▼
ApiVersion ─────────► API-Version 头校验，注入 $request->apiVersion
  │ (失败返回 400)
  ▼
AdminAuth ──────────► JWT 验证，注入 $request->adminId
  │ (失败返回 401)
  ▼
AdminPermission ────► RBAC 权限校验（Redis 60s 缓存）
  │ (失败返回 403)
  ▼
OperationLog ───────► 操作日志记录 (POST/PUT/DELETE)，自动检测来源端
  │
  ▼
Controller::method()
  │
  ├─► 参数验证 (validator)
  ├─► 敏感操作确认 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 操作 (自动 encryptable 加解密)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 Siklus Hidup ID

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Sistem Enkripsi Data

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-128-ECB，独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. Desain Database

### 3.1 Hubungan ER

```
game_admin_user ──┬── game_admin_user_role ──┬── game_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    game_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    game_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           game_operation_log
             (操作日志)

game_system_config (系统配置) — 独立表
```

### 3.2 Struktur Tabel Inti

| Nama tabel | Jumlah kolom | Deskripsi |
|------|-------|------|
| `game_admin_user` | 14 | Pengguna admin, phone/email/id_card disimpan terenkripsi, mendukung soft delete |
| `game_admin_role` | 7 | Peran, slug unik |
| `game_admin_permission` | 10 | Pohon izin (self-referensi parent_id), type: 1=menu 2=tombol 3=API |
| `game_admin_user_role` | 2 | Tabel perantara many-to-many pengguna-peran |
| `game_admin_role_permission` | 2 | Tabel perantara many-to-many peran-izin |
| `game_system_config` | 8 | Konfigurasi pasangan kunci-nilai, group+key unik gabungan |
| `game_operation_log` | 9 | Log audit operasi (termasuk source sumber) |

### 3.3 Standar Primary Key

- Tipe: `BIGINT UNSIGNED NOT NULL`
- Karakteristik: **non-auto-increment**, dihasilkan algoritma Snowflake di lapisan aplikasi
- Keunggulan: unik global, ramah terdistribusi, kenaikan tren bagus untuk indeks, tidak mengekspos volume bisnis
- Konfigurasi: datacenter_id(0-31) + worker_id(0-31), mendukung 1024 node bersamaan

## 4. Desain API

### 4.1 Standar URL

```
公开接口:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

管理端:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

资源路由:
  GET    /admin/user          → 列表
  POST   /admin/user          → 创建
  GET    /admin/user/{hashid} → 详情
  PUT    /admin/user/{hashid} → 更新
  DELETE /admin/user/{hashid} → 删除（需密码确认）

系统配置:  /admin/config[/{hashid}]
操作日志:  /admin/log
个人中心:  /admin/profile[/password|/logout]
导入:     /admin/import/users
上传:     /admin/upload
批量:     /admin/user/batch/{destroy|status}
文档:     /api/docs     (OpenAPI 3.0)
健康:     /health
```

### 4.2 Strategi Versi API

Versi API dikontrol melalui header permintaan, **tidak tercermin di path URL**:

```http
API-Version: v1
```

| Mekanisme | Deskripsi |
|------|------|
| Versi default | Saat tidak membawa header `API-Version`, default `v1` |
| Validasi | Divalidasi middleware `ApiVersion`, versi tidak didukung mengembalikan 400 |
| Rute | Fungsi bantu `v()` secara dinamis menyelesaikan kelas kontroler sesuai versi |
| Direktori | Kontroler diorganisir per versi: `app/api/{version}/controller/` |

Contoh ekstensi——menambahkan API v2:
1. Buat `app/api/v2/controller/AuthController.php`
2. Tambahkan `'v2'` pada konstanta `SUPPORTED` middleware `ApiVersion`
3. Definisi rute tidak perlu diubah

```bash
# 使用 v1
curl -H "API-Version: v1" /api/auth/login

# 使用 v2
curl -H "API-Version: v2" /api/auth/login

# 不传，默认 v1
curl /api/auth/login
```

### 4.3 Strategi Rate Limit

Berbasis algoritma jendela geser Redis Sorted Set, dieksekusi skrip Lua atomik:

| Antarmuka | Batas |
|------|------|
| Default | 60 kali/menit/IP/rute |
| POST /api/auth/login | 10 kali/menit |
| POST /api/auth/register | 5 kali/menit |

Melebihi batas mengembalikan 429, header respons berisi X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 Respons Terpadu

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Arti | Skenario pemicu |
|------|------|---------|
| 0 | Berhasil | Respons normal |
| 400 | Parameter salah | Format permintaan tidak benar |
| 401 | Tidak terautentikasi | Token hilang/kedaluwarsa/tidak valid |
| 403 | Tidak ada izin | Peran pengguna tidak memiliki izin yang dibutuhkan |
| 404 | Tidak ada | Sumber daya tidak ditemukan |
| 422 | Validasi gagal | Parameter form tidak sesuai aturan / konfirmasi kata sandi gagal |
| 500 | Kesalahan server | Pengecualian tak terduga |

### 4.5 Alur Autentikasi (termasuk CAPTCHA klik)

```
客户端                               服务端
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② 用户点击图中文字位置              │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 Model Izin (RBAC)

```
  用户 ──┬── 角色 ──┬── 权限
  User     Role      Permission
                 │
                 ├── type=1: 菜单 (控制侧边栏可见)
                 ├── type=2: 按钮 (控制页面内操作)
                 └── type=3: API  (控制接口访问)

  权限标识格式: {method}.{path}
  例: get.admin/user  post.admin/user  delete.admin/user
  超级管理员标识: * (跳过所有权限检查)
```

### 4.7 Konfirmasi Ulang Operasi Sensitif

Operasi sensitif seperti menghapus pengguna, peran, izin, perlu mengirim kata sandi pengguna saat ini di body permintaan untuk pemeriksaan ulang identitas:

```
客户端                           服务端
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 密码错误返回 422
  │                                │ → 密码正确继续执行
  │◄── 200 { code: 0 }           │
```

Frontend menampilkan dialog konfirmasi sebelum memicu operasi hapus, mengumpulkan kata sandi pengguna lalu mengirim permintaan.

## 5. Desain Frontend

### 5.1 Backend Administrasi Flutter Web

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

Fitur: sidebar dapat dilipat, tema ganda Material 3, tabel data kepadatan tinggi, dialog popup, interaksi hover mouse

### 5.2 Klien Seluler HarmonyOS

Rute halaman:

| Halaman | Rute | Deskripsi |
|------|------|------|
| LoginPage | `pages/LoginPage` | Login nama pengguna/kata sandi + CAPTCHA klik |
| DashboardPage | `pages/DashboardPage` | Kartu statistik + operasi terbaru |
| UserListPage | `pages/UserListPage` | Daftar pengguna, pencarian + tarik untuk refresh + muat saat scroll naik |
| UserDetailPage | `pages/UserDetailPage` | Tambah/edit/lihat/hapus (konfirmasi AlertDialog) |
| ProfilePage | `pages/ProfilePage` | Pusat pribadi, logout (konfirmasi AlertDialog) |

Aliran data: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Desain Keamanan

### 6.1 Pertahanan Berlapis

| Lapisan | Langkah |
|------|------|
| Batasan metode | Whitelist metode HTTP SecurityFilter, hanya mengizinkan GET/POST/PUT/DELETE/OPTIONS/HEAD, metode non-standar mengembalikan 405 |
| Pemblokiran serangan | Middleware SecurityFilter, deteksi & pemblokiran XSS/Injeksi SQL/path traversal/injeksi perintah/CSRF |
| Verifikasi manusia | CAPTCHA klik（Click Captcha）, validasi wajib saat login/registrasi |
| Penguncian akun | 5 kali gagal login berturut-turut mengunci akun 15 menit, selama masa kunci mengembalikan 429 |
| Batasan sesi | Maksimal 3 Token bersamaan per pengguna, Token paling lama otomatis masuk daftar hitam saat melebihi |
| Rate limit | Middleware RateLimit, jendela geser Redis, atomik Lua |
| CSP | Header Content-Security-Policy membatasi sumber sumber daya, mencegah XSS dan injeksi data |
| Konfirmasi operasi | Operasi sensitif seperti hapus perlu memasukkan kata sandi pengguna saat ini untuk konfirmasi ulang |
| Transport | HTTPS + JWT Bearer Token |
| ID antarmuka | Enkripsi Hashids, ID asli tidak dapat ditelusuri balik dari luar |
| Body permintaan | Enkripsi kolom sensitif AES-256-CBC |
| Database | Primary key BIGINT（tidak mengekspos increment） |
| Database | Kolom sensitif disimpan terenkripsi AES-128-ECB |
| Autentikasi | JWT HS256, kedaluwarsa 2 jam + refresh token |
| Otorisasi | RBAC, kontrol izin granular method.path |
| Audit | OperationLog mencatat semua operasi (termasuk deteksi otomatis source sumber) |

### 6.2 Manajemen Kunci

```
JWT_SECRET          → 环境变量注入，64位随机字符串
HASHIDS_SALT        → 唯一盐值，泄漏后需全局更换
ENCRYPTION_KEY      → API 传输加密密钥，32字节
ENCRYPTABLE_KEY     → DB 存储加密密钥，与传输密钥独立
SCOUT_HOSTS         → ES 地址，内网部署
```

### 6.3 Perlindungan Data Sensitif

| Skenario | Kolom | Langkah |
|------|------|------|
| Tampilan daftar | phone | Redaksi: 138****1234 |
| Tampilan daftar | email | Redaksi: a***@example.com |
| Lihat detail | phone/email | Perlu antarmuka dekripsi |
| Ekspor Excel | phone/email | Diekspor setelah diredaksi |
| Ekspor PDF | Semua kolom | Redaksi + watermark hak cipta yang tidak dapat dihapus |
| Penyimpanan | phone/email/id_card | encryptable dienkripsi menjadi teks sandi |

## 7. Desain Ekspor

### 7.1 Ekspor Excel

```
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 Ekspor PDF

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. Arsitektur Deployment

### 8.1 Topologi yang Disarankan

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                    静态文件: Flutter Web build/
```

### 8.2 Docker Compose (disarankan untuk produksi)

`docker-compose.yml` di direktori root proyek mengorkestrasi semua layanan topologi di atas:

| Layanan | Image/Build | Port | Deskripsi |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Proxy balik + file statis + Gzip |
| `app` | dibangun `Dockerfile` lokal | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Database utama, persistensi volume data |
| `redis` | redis:7-alpine | 6379 | Cache / rate limit / CAPTCHA |
| `elasticsearch` | elasticsearch:8.x | 9200 | Pencarian teks penuh |

Sebelum start, ganti kunci di `docker-compose.yml` seperti `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY` dengan string acak.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

Integrasi berkelanjutan GitHub Actions didefinisikan di `.github/workflows/ci.yml`:
- Pemeriksaan sintaks PHP (`php -l`)
- Pengujian unit PHPUnit
- Analisis statis Flutter (`flutter analyze`)

### 8.4 Backup Database

`database/backup/backup.sh` — backup mysqldump + gzip, otomatis membersihkan backup lama lebih dari 30 hari.
`database/backup/restore.sh` — pilih dan pulihkan backup secara interaktif.

### 8.5 Monitoring

Endpoint `GET /metrics`（`MetricsController`）mengekspos 5 metrik gauge dalam format teks Prometheus: total permintaan HTTP, jumlah pengguna aktif, status koneksi database/Redis, penggunaan memori.

### 8.6 Persyaratan Lingkungan

| Komponen | Versi minimum | Konfigurasi yang disarankan |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ OPcache diaktifkan |
| MySQL | 8.0+ | 8.0+ replikasi master-slave |
| Elasticsearch | 7.x | 8.x cluster 3 node |
| Redis | 6.x | 7.x mode sentinel |
| Nginx | 1.20+ | Proxy balik + gzip + SSL |
| Flutter SDK | 3.41+ | Versi stabil terbaru |
| HarmonyOS | API 12 | DevEco Studio 5.x |
