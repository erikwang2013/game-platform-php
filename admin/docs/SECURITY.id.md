# Dokumen Desain Arsitektur Keamanan
<!-- lang-nav -->

Languages: [中文](SECURITY.md) · [English](SECURITY.en.md) · [한국어](SECURITY.ko.md) · [Русский](SECURITY.ru.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Español](SECURITY.es.md) · [Português](SECURITY.pt.md) · [हिन्दी](SECURITY.hi.md) · [العربية](SECURITY.ar.md) · [বাংলা](SECURITY.bn.md) · **Bahasa Indonesia** · [日本語](SECURITY.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Panorama Pertahanan Berlapis

Sistem menggunakan model pertahanan berlapis 7 lapis, menyaring permintaan berbahaya dari luar ke dalam lapis demi lapis, memastikan saat satu lapisan gagal masih ada garis pertahanan berikutnya sebagai cadangan.

Seluruh rantai middleware dieksekusi dalam urutan berikut (lihat `config/middleware.php`):

```
请求 → Cors → SecurityFilter → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Lapisan | Middleware/Mekanisme | Target perlindungan |
|----|--------|---------|
| 1 | SecurityFilter | Pemblokiran serangan XSS / Injeksi SQL / path traversal / injeksi perintah / CSRF |
| 2 | Cors | Keamanan lintas domain + injeksi header keamanan respons |
| 3 | RateLimit | Rate limit jendela geser Redis, mencegah brute force |
| 4 | AdminAuth | Autentikasi JWT + logout daftar hitam |
| 5 | AdminPermission | Otorisasi granular RBAC method.path |
| 6 | OperationLog | Audit operasi + pelacakan sumber |
| 7 | Enkripsi data | Obfuskasi ID Hashids + enkripsi DB Encryptable + enkripsi transport EncryptionService |

Frontend tiga lapis (Flutter) memiliki validasi input independen tersendiri, backend tidak mempercayainya, setiap lapisan bertahan secara independen.

---

## 2. Mesin Deteksi Serangan

### 2.0 Batasan Metode HTTP

SecurityFilter memvalidasi metode HTTP terlebih dahulu sebelum semua deteksi serangan, hanya mengizinkan metode standar berikut:

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

Metode non-standar (seperti TRACE, CONNECT, PATCH, metode kustom, dll.) langsung mengembalikan **405 Method Not Allowed**, body respons HTML kosong, tidak masuk ke deteksi serangan atau logika bisnis berikutnya.

Ini adalah garis pertahanan pertama pertahanan berlapis, efektif mencegah:
- Serangan pelacakan lintas situs TRACE (XST)
- Penyalahgunaan proxy terowongan CONNECT
- Deteksi metode WebDAV non-standar
- Enumerasi metode HTTP oleh scanner otomatis

### 2.1 XSS (Cross-Site Scripting)

Semua regex berasal dari `SecurityFilter::PATTERNS['XSS']`, pencocokan tidak sensitif huruf besar/kecil.

| Pola deteksi | Regex | Serangan yang ditangkis |
|----------|------|-----------|
| Tag skrip | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` dan varian dengan spasi |
| Atribut event | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | Event inline seperti `onclick="javascript:..."` |
| Pseudo-protocol JS | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` dll. |
| XSS Data URI | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` dll. |
| Injeksi template | `\{\{.*?\}\}` | Injeksi template server/Angular/Vue seperti `{{constructor}}`, `{{7*7}}` |

### 2.2 Injeksi SQL

| Pola deteksi | Regex | Serangan yang ditangkis |
|----------|------|-----------|
| Kueri gabungan UNION | `\bUNION\s+(?:ALL\s+)?SELECT\b` | `UNION SELECT`, `UNION ALL SELECT` pencurian data |
| Injeksi OR selalu benar | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| Perusakan struktur tabel | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| Pemanggilan stored procedure | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | Eksekusi perintah stored procedure ekstensi MSSQL |
| Deteksi metadata | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | Deteksi struktur database MySQL/PG/SQLite/MSSQL |
| Bypass komentar | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | Bypass komentar `'-- OR SELECT`, `'# AND UPDATE` |

### 2.3 Path Traversal

| Pola deteksi | Regex | Serangan yang ditangkis |
|----------|------|-----------|
| Penelusuran balik direktori | `\.\.[\/\\\\]{2,}` | `../`, `..\`, `....//` penelusuran balik multi-level |
| Deteksi file sensitif | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` dll. |
| Pemotongan null byte | `%00` | `../../../etc/passwd%00.jpg` melewati validasi ekstensi |

### 2.4 Injeksi Perintah

| Pola deteksi | Regex | Serangan yang ditangkis |
|----------|------|-----------|
| Perintah pipe/titik koma | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| Substitusi backtick | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| Substitusi $() | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| Pipe unduhan jarak jauh | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF (Cross-Site Request Forgery)

Logika validasi diimplementasikan di `SecurityFilter::checkCsrf()`:

```php
// 仅 POST/PUT/DELETE 触发校验
// Origin 头和 Referer 均为空 → 放行（非浏览器客户端）
// Origin 非空 → 解析 Origin 域名与 Host 比对
```

Aturan perbandingan:
- Hapus prefix `www.` dari Host lalu bandingkan presisi dengan domain Origin
- Jika Host adalah domain induk dari Origin (seperti `Origin: app.example.com`, `Host: example.com` — memicu `str_contains($originHost, '.' . $hostOnly)`), lolos
- Tidak cocok presisi juga bukan subdomain → mengembalikan 403, dianggap serangan CSRF

Catatan: klien non-browser (seperti curl tanpa Origin/Referer) langsung diloloskan, perlindungan CSRF hanya efektif untuk lingkungan browser.

### 2.6 Unggah File Berbahaya

| Pola deteksi | Regex | Serangan yang ditangkis |
|----------|------|-----------|
| Penyamaran ekstensi ganda | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` melewati whitelist |
| Ekstensi PHP | `\.php\s*$/m` | Parameter permintaan langsung meneruskan path `.php` |

---

## 3. Eskalasi Serangan & Daftar Hitam IP

SecurityFilter memiliki mekanisme eskalasi serangan bawaan, mencegah IP yang sama terus memindai serangan.

### Alur Eskalasi

```
第 1 次扫描命中 → Redis INCR security_escalate:{ip} = 1, TTL=60s
第 2 次扫描命中 → INCR → 2
...
第 5 次扫描命中 → INCR → 5
    → 触发封禁: SETEX security_ban:{ip} 900 1
    → 清除计数器 DEL security_escalate:{ip}
    → 写入安全日志: [SECURITY] IP banned 15min
```

### Perilaku Selama Terblokir

Setiap permintaan masuk SecurityFilter terlebih dahulu memeriksa `isBanned()`:

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

IP yang diblokir dalam 15 menit semua permintaan (termasuk permintaan sah) langsung mengembalikan 403, sepenuhnya melewati logika bisnis berikutnya.

### Konstanta Konfigurasi

| Konstanta | Nilai | Arti |
|------|-----|------|
| ESCALATE_LIMIT | 5 | Ambang jumlah pemicuan dalam jendela 60 detik |
| ESCALATE_WINDOW | 60 | Jendela penghitung (detik) |
| BAN_DURATION | 900 | Durasi daftar hitam (detik), yaitu 15 menit |

### Log Keamanan

Lokasi file: `runtime/logs/security.log`

Contoh format log:
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### Batasan Ukuran Body Permintaan

`Content-Length > 10MB` langsung mengembalikan 413 Payload Too Large, mencegah serangan DoS body permintaan sangat besar.

### Validasi Content-Type

Permintaan POST/PUT **wajib** mendeklarasikan `Content-Type` sebagai `application/json` atau `application/x-www-form-urlencoded`, jika tidak mengembalikan 415 Unsupported Media Type. Permintaan unggah file (dengan kolom file) melewati pemeriksaan ini.

---

## 4. Header Keamanan Respons

Semua header disuntikkan di middleware `Cors`, ditambahkan ke setiap respons melalui `$response->withHeaders()`.

| Header | Nilai | Fungsi |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Mengizinkan lintas domain dari sumber mana pun (skenario backend admin intranet) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Kumpulan metode yang diizinkan |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | Header kustom yang diizinkan |
| Access-Control-Max-Age | `86400` | Cache permintaan preflight 24 jam |
| X-Content-Type-Options | `nosniff` | Melarang MIME sniffing browser |
| X-Frame-Options | `DENY` | Melarang semua embed iframe, mencegah clickjacking |
| X-XSS-Protection | `1; mode=block` | Mengaktifkan filter XSS bawaan browser dan memblokir render halaman |
| Referrer-Policy | `strict-origin-when-cross-origin` | Asal sama mengirim URL lengkap, lintas domain hanya mengirim domain |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Menonaktifkan API kamera/mikrofon/lokasi di seluruh situs |

Permintaan preflight OPTIONS langsung mengembalikan respons kosong 204, tidak masuk rantai middleware berikutnya.

### 4.2 Content-Security-Policy (CSP)

Disuntikkan di middleware Cors bersama header keamanan lainnya, memberikan pertahanan berlapis, membatasi sumber daya yang dapat dimuat dan dieksekusi browser.

| Header | Nilai | Fungsi |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Membatasi sumber skrip/gaya/gambar/koneksi/frame/formulir |
| X-Permitted-Cross-Domain-Policies | `none` | Melarang pemuatan file kebijakan lintas domain Adobe Flash/PDF dll. |

Poin penting kebijakan CSP:
- `default-src 'self'`: default hanya mengizinkan sumber daya asal sama
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: mengizinkan skrip asal sama + skrip inline (wajib untuk Flutter Web) + eval (wajib untuk debugging Flutter Web)
- `frame-ancestors 'none'`: melarang embed iframe oleh halaman mana pun, ganda dengan X-Frame-Options: DENY
- `base-uri 'self'`: membatasi tag `<base>` hanya menunjuk ke asal sama
- `form-action 'self'`: membatasi formulir hanya dapat dikirim ke asal sama

---

## 5. Strategi Rate Limit

### Algoritma

Redis Sorted Set jendela geser + skrip atomik Lua, operasi kunci:

```lua
-- 1. 清理窗口外的旧记录
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. 检查当前窗口计数
local count = redis.call('ZCARD', KEYS[1])
-- 3. 超限则返回 {0, count}，未超限则 ZADD 并返回 {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- 随机后缀避免同毫秒覆盖
redis.call('EXPIRE', KEYS[1], window + 10)
```

Skrip Lua dieksekusi single-threaded di server Redis, **secara alami atomik**, menghilangkan race condition TOCTOU (Time-of-check to Time-of-use).

### Konfigurasi Rate Limit

| Rute | Batas | Jendela | Skenario |
|------|------|------|------|
| Default (semua rute) | 60 kali/menit | 60s | API umum |
| `/api/auth/login` | 10 kali/menit | 60s | Login (mencegah brute force) |
| `/api/auth/register` | 5 kali/menit | 60s | Registrasi (mencegah registrasi massal) |

### Header Respons

Saat rate limit terpicu mengembalikan HTTP 429 dengan body JSON:
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

Semua respons (termasuk respons normal) membawa header berikut:

| Header | Deskripsi |
|----|------|
| X-RateLimit-Limit | Jumlah permintaan maksimum yang diizinkan pada jendela saat ini |
| X-RateLimit-Remaining | Sisa permintaan yang tersedia pada jendela saat ini |
| X-RateLimit-Reset | Timestamp Unix reset jendela |
| Retry-After | Hanya dibawa saat rate limit, detik yang disarankan untuk menunggu |

### Strategi Degradasi

Saat Redis abnormal (timeout koneksi, tidak tersedia, dll.) **fail-closed**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    // Redis down: 限流不可用即拒绝，避免安全防线失效（登录/支付回调限流为空转）
    return json(['code' => 503, 'message' => '服务暂不可用，请稍后再试', 'data' => []])
        ->withStatus(503)->withHeaders(['Retry-After' => '5']);
}
```

Rate limit adalah garis pertahanan pertama pencegahan brute force login dan anti-replay callback pembayaran, saat Redis bermasalah lebih baik menolak permintaan (503) daripada meloloskan.

### 5.4 Mekanisme Penguncian Akun

Antarmuka login, di atas batas kecepatan, menambahkan mekanisme **penguncian akun** untuk mencegah brute force terarah pada pengguna tertentu.

**Alur penguncian**:

```
登录失败 → Redis INCR account_lockout:{userId} TTL=900s
连续 5 次失败 → Redis SETEX account_locked:{userId} 900 1
            → 返回 429 "账号已被锁定，请15分钟后再试"
            → 清除计数器 DEL account_lockout:{userId}
```

**Perilaku selama terkunci**:

Selama masa kunci, semua permintaan login langsung mengembalikan 429, tanpa validasi kata sandi, sepenuhnya mencegah percobaan brute force.

**Konstanta konfigurasi**:

| Konstanta | Nilai | Arti |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Maksimum kegagalan berurutan |
| LOCKOUT_DURATION | 900 | Durasi penguncian (detik), yaitu 15 menit |

Catatan: penguncian akun berbasis `userId` bukan IP, sehingga penyerang yang mengganti IP tidak dapat melewati penguncian. Ditumpuk dengan rate limit IP (10 kali/menit) membentuk perlindungan ganda:
- Level IP: rate limit 10 kali/menit mencegah brute force terdistribusi
- Level akun: 5 kali gagal terkunci mencegah brute force terarah

---

## 6. Autentikasi & Otorisasi

### 6.1 Autentikasi JWT

Diimplementasikan middleware AdminAuth, dipasang pada grup rute yang memerlukan autentikasi.

**Konfigurasi parameter**（`config/plugin/erikwang2013/jwt/jwt`，diinjeksi dari `.env`）:

| Parameter | Nilai | Deskripsi |
|------|-----|------|
| Algoritma | HS256 | Tanda tangan simetris HMAC-SHA256 |
| Kunci | `JWT_SECRET_KEY` | Diinjeksi variabel lingkungan, jika hilang atau masih nilai default **menolak start**（fail-closed） |
| TTL access_token | 7200s (2h) | `JWT_TTL` |
| TTL refresh_token | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Issuer | `open-admin` | `JWT_ISSUER` |
| Audience | `open-admin` | `JWT_AUDIENCE` |

**Ekstraksi Token**: diekstrak dari header `Authorization: Bearer <token>`, strip prefix `Bearer ` untuk mendapatkan JWT asli.

**Alur autentikasi**:
1. Token kosong → langsung 401 `{"code": 401, "message": "未登录"}`
2. Periksa daftar hitam Redis `jwt_blacklist:{md5(token)}` → terdeteksi → 401 `Token已失效，请重新登录`
3. JWT decode → gagal (kedaluwarsa/tanda tangan tidak cocok) → 401 `Token已过期或无效`
4. Berhasil → injeksi `$request->adminId` dan `$request->adminUsername`

**Mekanisme daftar hitam**: saat pengguna logout, tulis `md5(token)` ke Redis, TTL diatur sebagai sisa masa berlaku JWT. Saat Redis bermasalah, pemeriksaan daftar hitam dilewati (fail-open), Token yang sudah logout masih dapat digunakan sementara, tetapi masa berlaku pendek JWT itu sendiri (2h) sebagai perlindungan cadangan.

**Refresh Token**: `POST /api/auth/refresh` memvalidasi refresh token asli (`token_type=refresh` dan belum kedaluwarsa/belum diblokir) sebelum menerbitkan rotasi, dan memvalidasi `sub` harus berupa ID pengguna valid —— **tidak lagi menerbitkan refresh token dengan sub=null**, kegagalan refresh langsung mengembalikan 401.

### 6.2 Batasan Sesi Bersamaan

Untuk mencegah Token bocor disalahgunakan di banyak perangkat, sistem membatasi jumlah Token valid yang dimiliki satu pengguna secara bersamaan.

**Logika pembatasan**:

```
登录成功 → 签发新 Token
         → 查询当前用户有效 Token 数量: Redis SCARD user_tokens:{userId}
         → 若数量 >= 3（MAX_CONCURRENT_SESSIONS）:
            → 按创建时间升序排列，移除最旧的 Token:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → 将新 Token 加入集合: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Konstanta konfigurasi**:

| Konstanta | Nilai | Arti |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Jumlah Token bersamaan maksimum per pengguna |

**Skenario terlempar keluar**: saat pengguna login di perangkat ke-4, Token perangkat ke-1 dipaksa masuk daftar hitam, permintaan berikutnya mengembalikan 401 "Token已失效，请重新登录".

Saat logout, Token saat ini dihapus dari kumpulan. Saat Token kedaluwarsa alami, key Redis otomatis kedaluwarsa, anggota kumpulan pun berkurang.

### 6.3 Model Izin RBAC

Diimplementasikan middleware AdminPermission.

**Model data**: relasi tiga lapis User -> Role -> Permission

- `game_admin_user` (tabel pengguna)
- `game_admin_user_role` (tabel relasi pengguna-peran)
- `game_admin_role` (tabel peran)
- `game_admin_role_permission` (tabel relasi peran-izin)
- `game_admin_permission` (tabel izin)

**Tipe izin**:
| type | Arti | Contoh |
|------|------|------|
| 1 | Izin menu | Mengontrol visibilitas navigasi kiri |
| 2 | Izin tombol | Mengontrol tombol operasi dalam halaman (tambah/edit/hapus) |
| 3 | Izin API | Mengontrol pemanggilan antarmuka backend |

Format identitas izin API: `{method}.{path}`

Contoh:
- `post.admin/user` — membuat pengguna
- `put.admin/user` — mengedit pengguna
- `delete.admin/user` — menghapus pengguna
- `get.admin/user` — melihat daftar pengguna

**Alur otorisasi**:
1. `$request->adminId` kosong (belum login) → langsung 401 `{"code": 401, "message": "未登录"}`, tidak lagi diloloskan
2. Dapatkan pengguna → peran (lewati peran nonaktif `status=0`) → daftar izin
3. Super admin (`slug = '*'`) → langsung diloloskan
4. Bangun `strtolower(method) . '.' . trim(path, '/')` → bandingkan dengan daftar izin
5. Pencocokan gagal → 403 `{"code": 403, "message": "无权限访问"}`

**Konfirmasi ulang**: BaseController menyediakan metode `confirmPassword()`, operasi sensitif (hapus pengguna, ekspor data, dll.) di lapisan Controller menambahkan persyaratan memasukkan kata sandi saat ini, mencegah operasi tidak sah setelah pembajakan sesi.

### 6.4 Verifikasi Tanda Tangan Callback Pembayaran（fail-closed）

`POST /api/payment/callback`（callback deposit Stripe/PayPal）verifikasi tanda tangan menggunakan **fail-closed**, konfigurasi yang hilang atau abnormal validasi apa pun menolak callback:

| Skenario | Perilaku |
|------|------|
| Stripe belum mengonfigurasi `STRIPE_WEBHOOK_SECRET` | Menolak（403）, tidak lagi menerima callback tanpa tanda tangan |
| Tanda tangan Stripe hilang / verifikasi tanda tangan gagal | Menolak（403） |
| Timestamp Stripe `t=` hilang atau selisih waktu server **> ±5 menit** | Menolak（403）, mencegah replay |
| PayPal belum mengonfigurasi `PAYPAL_WEBHOOK_ID` | Menolak（403） |
| Verifikasi balik PayPal abnormal / bukan SUCCESS | Menolak（403） |
| Setelah `CALLBACK_TRUSTED_IPS` opsional dikonfigurasi, IP sumber tidak ada di whitelist | Menolak（403） |
| Provider callback tidak cocok dengan metode pembayaran pesanan / metode pembayaran tidak ada | Menolak（403） |

Kredit callback (pembaruan status + saldo + catatan transaksi) selesai dalam satu transaksi database yang sama, satu langkah gagal seluruhnya di-rollback, mencegah kredit sebagian.

---

## 7. Log Audit

### 7.1 Log Operasi

Middleware OperationLog otomatis mencatat log operasi untuk permintaan POST / PUT / DELETE. Permintaan GET tidak dicatat.

**Kolom yang dicatat**:

| Kolom | Sumber | Deskripsi |
|------|------|------|
| id | SnowflakeService::generate() | ID unik global |
| user_id | `$request->adminId` | ID operator, 0 jika belum login |
| action | `$request->method()` | Sama dengan method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Path permintaan |
| ip | `$request->getRealIp()` | IP asli klien |
| source | detectSource() | Platform sumber klien |
| input | body permintaan (JSON setelah diredaksi) | Data yang dikirim operasi |
| created_at | `date('Y-m-d H:i:s')` | Waktu operasi |

**Filter kolom sensitif**: menelusuri body permintaan secara rekursif, nilai kolom berikut diganti `***`:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Deteksi sumber**（`detectSource()`）: sesuai prioritas:

1. Prioritas membaca header kustom `X-Client-Platform` (dideklarasikan eksplisit oleh klien native)
2. Degradasi ke inferensi string User-Agent (urutan deteksi metode `detectSource()`):

| Platform | Kata kunci UA |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Nilai default cadangan |

**Toleransi kesalahan**: pengecualian penulisan log tidak memblokir permintaan bisnis (`catch (\Throwable)` ditelan diam-diam).

### 7.2 Log Keamanan

**Lokasi file**: `runtime/logs/security.log`

**Isi yang dicatat**:
- Log pemblokiran serangan: kategori serangan, IP, path, kolom, sumber, potongan payload (200 karakter pertama)
- Notifikasi pemblokiran IP: IP yang diblokir, jumlah pemicuan

Izin log adalah `FILE_APPEND | LOCK_EX`, memastikan penulisan aman bersamaan.

---

## 8. Perlindungan Data

Sistem menggunakan strategi perlindungan data tiga lapis, sesuai tiga tahap aliran data.

### 8.1 Lapisan Transport — EncryptionService

`EncryptionService` menggunakan paket `erikwang2013/encryption`, melakukan enkripsi/dekripsi kolom sensitif dalam permintaan/respons API.

**Detail teknis**:
- Algoritma: `aes-256-cbc-hmac`（dengan tanda tangan HMAC bawaan anti-tamper）
- Kunci: variabel lingkungan `ENCRYPTION_KEY`, otomatis diselaraskan ke 32 byte
- Digunakan untuk: mentransmisikan nomor ponsel, nomor identitas, dll. antara klien dan API

**Metode utilitas redaksi**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com`（jika nama pengguna lebih dari 2 karakter）atau `a**@example.com`

### 8.2 Lapisan Penyimpanan — Encryptable Cast

Model `AdminUser` menggunakan cast Eloquent `Erikwang2013\Encryptable\Encryptable`, kolom terkait:

- `email` → cast sebagai Encryptable, enkripsi/dekripsi otomatis
- `phone` → cast sebagai Encryptable, enkripsi/dekripsi otomatis
- `id_card` → cast sebagai Encryptable, enkripsi/dekripsi otomatis

Saat menulis ke database otomatis dienkripsi menjadi teks sandi, saat membaca otomatis didekripsi menjadi teks biasa. Tipe kolom penyimpanan database adalah `VARCHAR(500)`, teks sandi disimpan dalam bentuk base64.

**Sistem kunci**: independen dari enkripsi lapisan transport (`ENCRYPTION_KEY`) menggunakan `ENCRYPTABLE_KEY`, kebocoran satu kunci tidak menyebabkan lapisan lain gagal.

Rotasi kunci: variabel lingkungan `ENCRYPTION_PREVIOUS_KEYS` mendukung daftar kunci historis (dipisah koma), saat membaca data lama mencoba dekripsi dengan kunci historis, saat menulis kembali menggunakan kunci saat ini untuk enkripsi ulang.

### 8.3 Lapisan Tampilan — Obfuskasi ID & Redaksi

**Obfuskasi ID Hashids**: `HashidsService` menggunakan paket `erikwang2013/hashids`.

- ID BIGINT database yang dikembalikan API eksternal dienkode menjadi string hash (seperti `xK3mN9qR2pL7wV8b`)
- Saat klien meminta, mengirim string hash, backend otomatis mendekode menjadi ID asli
- Nilai salt `HASHIDS_SALT` diinjeksi variabel lingkungan, salt berbeda maka hasil enkode/dekode sepenuhnya berbeda
- Panjang minimum hash 16 karakter, menggunakan set karakter alfanumerik 62 bit
- BaseController menyediakan metode praktis `encodeId()`, `decodeId()`, `encodeIds()`

**Redaksi ekspor**: saat ekspor Excel/PDF (ExportController), kolom sensitif diredaksi seragam:
- Nomor ponsel: `138****1234`
- Email: `a***@example.com`
- Nomor identitas: ditutupi penuh menjadi `********`

---

## 9. Manajemen Kunci

Semua kunci diinjeksi melalui variabel lingkungan `.env`, file konfigurasi membaca dengan `getenv()` dan memiliki nilai default cadangan bawaan (hanya aman untuk lingkungan pengembangan).

| Variabel Lingkungan | Kegunaan | Paket | Persyaratan produksi |
|----------|------|-----|---------|
| JWT_SECRET_KEY | Kunci tanda tangan JWT | erikwang2013/jwt-webman | String acak 64+ karakter; hilang atau default menolak start |
| JWT_ALGORITHM | Algoritma tanda tangan JWT | sama | Pertahankan HS256 |
| HASHIDS_SALT | Nilai salt enkode ID | erikwang2013/hashids | String acak |
| SNOWFLAKE_DATACENTER_ID | ID pusat data (0-31) | erikwang2013/snowflake-php | Pusat data tunggal pertahankan default |
| ENCRYPTION_KEY | Kunci enkripsi lapisan transport API | erikwang2013/encryption | String acak 32 byte |
| ENCRYPTABLE_KEY | Kunci enkripsi lapisan penyimpanan DB | erikwang2013/encryptable | String acak 32 byte, berbeda dari kunci transport |

**Persyaratan keamanan**:
- File `.env` sudah masuk `.gitignore`, dilarang keras di-commit ke repositori
- `.env.example` adalah file template publik, tidak berisi kunci asli
- Lingkungan produksi **wajib** mengganti semua kunci default menjadi string acak
- Disarankan menggunakan `openssl rand -base64 32` untuk menghasilkan kunci

### Isolasi Penyimpanan Kunci

| Lapisan | Kunci konfigurasi | Variabel lingkungan kunci |
|----|--------|-------------|
| Enkripsi transport | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Enkripsi penyimpanan | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| Obfuskasi ID | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| Tanda tangan JWT | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET_KEY` |

---

## 10. security.txt (RFC 9116)

Sistem menyediakan endpoint informasi kontak keamanan sesuai standar RFC 9116 di `/.well-known/security.txt`, memudahkan peneliti keamanan menemukan saluran pelaporan dengan cepat saat menemukan kerentanan.

**Cara akses**:

```
GET /.well-known/security.txt
```

**Isi respons**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Penjelasan kolom**:

| Kolom | Deskripsi |
|------|------|
| Contact | Kontak pelaporan kerentanan keamanan |
| Expires | Waktu kedaluwarsa file, perlu diperbarui berkala |
| Preferred-Languages | Bahasa komunikasi pilihan |
| Canonical | URL kanonik file ini |
| Policy | Tautan kebijakan keamanan/kebijakan pengungkapan kerentanan |

Endpoint ini tidak dibatasi rate limit, autentikasi, dll., siapa pun dapat mengakses langsung.

---

## 11. Konfigurasi Keamanan Nginx

Proyek menyediakan `docs/nginx-security.conf` sebagai konfigurasi referensi penguatan keamanan proxy balik Nginx untuk lingkungan produksi.

**Langkah keamanan yang tercakup**:

| Item konfigurasi | Fungsi |
|--------|------|
| `server_tokens off` | Menyembunyikan nomor versi Nginx |
| `client_max_body_size 10m` | Membatasi ukuran body permintaan, bekerja sama dengan SecurityFilter |
| `limit_req_zone` | Pembatasan frekuensi permintaan di level Nginx |
| `limit_conn_zone` | Pembatasan jumlah koneksi bersamaan |
| `add_header` header keamanan | Menambahkan header keamanan seperti X-XSS-Protection di level Nginx |
| `if ($request_method)` | Menolak metode HTTP non-standar di level Nginx |
| Konfigurasi SSL/TLS | Konfigurasi modern TLS 1.2/1.3, menonaktifkan cipher suite lemah |
| Sembunyikan header backend | `proxy_hide_header` menghapus header sensitif seperti versi webman |

**Cara penggunaan**: gabungkan konfigurasi di `docs/nginx-security.conf` ke blok server Nginx Anda, sesuaikan sesuai nama domain dan jalur sertifikat aktual.

---

## 12. Model Ancaman

### 12.1 Ancaman yang Telah Dilindungi

| Jenis ancaman | Vektor serangan | Lapisan pertahanan |
|----------|---------|---------|
| Penyalahgunaan metode HTTP | Serangan XST TRACE/TRACK, proxy terowongan CONNECT, deteksi metode WebDAV | SecurityFilter 405 whitelist metode (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| Brute force terarah | Percobaan kata sandi berulang terhadap pengguna tertentu | Penguncian akun (5 gagal terkunci 15 menit) + RateLimit (login 10/min) + Captcha |
| Brute force | Percobaan nama pengguna/kata sandi berulang dari IP terdistribusi | RateLimit (login 10/min) + Captcha |
| XSS | `<script>`, onerror, javascript: | SecurityFilter (5 pola) + header respons X-XSS-Protection + CSP |
| Injeksi SQL | UNION SELECT, OR 1=1, bypass komentar | SecurityFilter (6 pola) + kueri terparameterisasi Eloquent ORM |
| CSRF | Situs jahat mengirim permintaan atas nama | Validasi Origin/Referer SecurityFilter |
| Path traversal | `../../etc/passwd` | Pola path traversal SecurityFilter + whitelist ekstensi UploadController |
| Injeksi perintah | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 pola) |
| Pembajakan sesi | Mencuri Token JWT | Masa berlaku pendek JWT (2h) + logout daftar hitam + konfirmasi ulang kata sandi operasi sensitif |
| Enumerasi ID | Menelusuri ID numerik menebak volume data | Hashids diobfuskasi menjadi string acak |
| Kebocoran data | Pencurian DB / man-in-the-middle / kebocoran log | Enkripsi/redaksi tiga lapis + filter kolom sensitif OperationLog |
| Serangan DoS | Body permintaan sangat besar / permintaan frekuensi tinggi | Batas body 10MB + RateLimit 60/min + daftar hitam IP |
| Eskalasi izin | Pengguna berizin rendah mengakses antarmuka admin | Otorisasi granular RBAC method.path |
| Serangan unggah file | shell.php.png ekstensi ganda | Deteksi file berbahaya SecurityFilter |

### 12.2 Keterbatasan yang Diketahui

| Keterbatasan | Cakupan dampak | Langkah mitigasi |
|------|---------|---------|
| Perlindungan CSRF hanya efektif untuk browser | Klien non-browser (curl, Postman, App seluler) dapat melewati pemeriksaan Origin/Referer | Klien non-browser secara alami tidak terkena CSRF; mengandalkan autentikasi JWT menggantikan Cookie |
| Saat Redis tidak tersedia, rate limit fail-closed (503), pemeriksaan daftar hitam fail-open | Sebagian permintaan ditolak selama rate limit; Token yang sudah logout dapat digunakan sementara | Monitoring peringatan ketersediaan Redis; masa berlaku pendek JWT sebagai cadangan |
| Tidak ada mesin WAF independen | SecurityFilter menggunakan pencocokan regex `@preg_match`, bukan mesin aturan WAF khusus | Produksi disarankan menggunakan Nginx ModSecurity atau Cloudflare WAF di depan |
| JWT tanpa status tidak dapat dihentikan secara proaktif | Sebelum Token kedaluwarsa, tidak dapat dicabut dari server (selain daftar hitam) | Daftar hitam + TTL 2h pendek mengurangi jendela risiko |
| Daftar hitam IP hanya disimpan di memori | Daftar hitam hilang setelah Redis restart | Durasi blokir hanya 15 menit, dampak terbatas |
| Endpoint admin tanpa rate limit khusus | Antarmuka admin berbagi batas default 60/min dengan antarmuka biasa | Frekuensi operasi admin secara alami rendah, sementara tidak perlu dibedakan |
| `@preg_match` menekan error | Input regex malformed gagal diam-diam | `preg_last_error()` dapat ditambahkan monitoring, saat ini belum diimplementasikan |
