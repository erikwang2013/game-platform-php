# Dokumen Deployment
<!-- lang-nav -->

Languages: [中文](DEPLOYMENT.md) · [English](DEPLOYMENT.en.md) · [한국어](DEPLOYMENT.ko.md) · [Русский](DEPLOYMENT.ru.md) · [Deutsch](DEPLOYMENT.de.md) · [Français](DEPLOYMENT.fr.md) · [Español](DEPLOYMENT.es.md) · [Português](DEPLOYMENT.pt.md) · [हिन्दी](DEPLOYMENT.hi.md) · [العربية](DEPLOYMENT.ar.md) · [বাংলা](DEPLOYMENT.bn.md) · **Bahasa Indonesia** · [日本語](DEPLOYMENT.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Persyaratan Lingkungan

| Komponen | Versi minimum | Konfigurasi yang direkomendasikan |
|------|---------|---------|
| OS | Linux (Ubuntu 20.04+ / Debian 11+ / CentOS 8+) | Ubuntu 22.04 LTS |
| PHP | 8.3+ | 8.3+ (CLI, OPcache aktif) |
| Ekstensi PHP | pdo, pdo_mysql, pcntl, redis, gd, mbstring, xml | Semua |
| MySQL | 8.0+ | Replikasi master-slave 8.0+ |
| Redis | 6.0+ | Mode sentinel 7.x |
| Elasticsearch | 7.x+ | Node tunggal 8.x |
| Nginx | 1.20+ | Reverse proxy + gzip + SSL |
| Composer | 2.x | Versi stabil terbaru |
| Flutter SDK | 3.x+ | Versi stabil terbaru (hanya dibutuhkan saat build frontend) |

---

## 2. Wizard Instalasi Satu Klik (direkomendasikan untuk deployment baru)

```bash
# 1. Klon proyek
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Mulai wizard instalasi
php -S 0.0.0.0:8888 -t install/

# 3. Buka di browser http://<IP-server>:8888
#    Selesaikan sesuai wizard: pemeriksaan lingkungan → konfigurasi database → akun admin → instalasi otomatis

# 4. Install dependensi
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. Mulai layanan (port default admin 8789 / service 8792, dapat diubah melalui APP_PORT di .env masing-masing)
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. Pembersihan keamanan
rm -rf install/

# 7. Akses backend administrasi: http://<IP-server>:8789 (port default)
```

Yang dilakukan wizard instalasi:
- Pemeriksaan lingkungan PHP (versi, ekstensi, izin direktori)
- Mengeksekusi SQL gabungan (`install/install.sql`), membuat 78 tabel dan mengimpor data seed
- Membuat akun super admin (enkripsi bcrypt, ditautkan ke peran super_admin)
- Otomatis menghasilkan kunci JWT/Encryption/Hashids
- Menulis `admin/.env` dan `service/.env`
- Membuat `install/install.lock` untuk mencegah instalasi ulang

---

## 3. Deployment Docker Compose

### 3.1 Mulai Satu Klik

```bash
# 1. Klon proyek
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Gunakan wizard instalasi satu klik untuk mengonfigurasi lingkungan (atau konfigurasi manual file .env)
#    Parameter Docker seperti port ada di .env direktori root proyek (template .env.example): cp .env.example .env
php -S 0.0.0.0:8888 -t install/
# Cara manual: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. Build dan mulai semua layanan
docker-compose up -d

# 4. Lihat status
docker-compose ps

# 5. Lihat log
docker-compose logs -f
```

### 3.2 Daftar Layanan

| Layanan | Nama container | Port | Keterangan |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | Reverse proxy + file statis |
| admin | game-platform-admin | 8789 | API backend administrasi |
| service | game-platform-service | 8792 | API bisnis sisi C |
| leaderboard-ws | game-platform-ws | 8790, 8791 | WebSocket papan peringkat/chat |
| mysql | game-platform-mysql | 3306 | Database utama |
| redis | game-platform-redis | 6379 | Cache/rate limit |
| elasticsearch | game-platform-es | 9200 | Pencarian full-text |

> **Konfigurasi port**: Port pada tabel di atas adalah nilai default dan semuanya dapat diubah di `.env` direktori root proyek (template `.env.example`; edit setelah `cp .env.example .env`):
> `NGINX_HTTP_PORT`, `NGINX_HTTPS_PORT`, `ADMIN_PORT`, `SERVICE_PORT`, `LEADERBOARD_WS_PORT`, `CHAT_WS_PORT`, `MYSQL_PORT`, `REDIS_PORT`, `ES_PORT`.
> Port upstream di `nginx.conf.template` dirender otomatis oleh envsubst dari image resmi; konfigurasi Nginx tidak perlu diubah manual.
> Pada penerapan Docker, alamat publik (`APP_URL` / `SITE_URL`) secara default otomatis mengikuti `ADMIN_PORT` / `SERVICE_PORT` (format `http://localhost:port`); untuk domain kustom atau HTTPS, setel `APP_URL` / `SITE_URL` di `.env` root (menimpa kunci yang sama di `admin/.env` dan `service/.env`). Pada penerapan bare-metal (manual), alamat tetap perlu diperbarui sendiri saat mengubah port.

### 3.3 Inisialisasi Database

```bash
# File migrasi dieksekusi otomatis saat MySQL pertama kali dimulai
# Atau eksekusi manual:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 3.4 Persistensi Data

Volume data dibuat otomatis, tidak perlu dikelola manual:

| Volume | Jalur | Konten |
|----|------|------|
| mysql_data | /var/lib/mysql | File database |
| redis_data | /data | Persistensi Redis |
| es_data | /usr/share/elasticsearch/data | Indeks ES |

Backup:
```bash
# Backup MySQL
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game-platform | gzip > backup_$(date +%Y%m%d).sql.gz

# Restore
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform
```

---

## 4. Deployment Manual

### 4.1 Konfigurasi Lingkungan PHP

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# Aktifkan OPcache (wajib di produksi)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 4.2 Install Dependensi

```bash
cd /opt/game-platform

# Backend administrasi
cd admin
cp .env.example .env
# Edit .env: koneksi database, JWT_SECRET, HASHIDS_SALT, dll.
composer install --no-dev --optimize-autoloader

# Bisnis sisi C
cd ../service
cp .env.example .env
# Edit .env (perhatian: SNOWFLAKE_WORKER_ID=2)
composer install --no-dev --optimize-autoloader
```

### 4.3 Konfigurasi .env

**Konfigurasi kunci admin/.env:**
```ini
APP_ENV=production
APP_DEBUG=false
APP_PORT=8789  # port HTTP listener webman (harus konsisten dengan APP_URL)
APP_URL=http://localhost:8789  # alamat akses eksternal (baseUrl dokumentasi API, dll.)

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=game-platform
DB_USERNAME=game-platform
DB_PASSWORD=<kata sandi kuat>

JWT_SECRET=<string acak 64 karakter>
JWT_TTL=7200

HASHIDS_SALT=<nilai salt acak>
HASHIDS_MIN_LENGTH=16

SNOWFLAKE_DATACENTER_ID=1
SNOWFLAKE_WORKER_ID=1

ENCRYPTION_KEY=<kunci acak 32 byte>
ENCRYPTABLE_KEY=<kunci AES acak>

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=<kata sandi Redis>

SCOUT_HOSTS=127.0.0.1:9200
```

**Konfigurasi kunci service/.env:**
```ini
# Konfigurasi database, Redis, ES sama dengan admin
APP_PORT=8792  # port HTTP listener webman
LEADERBOARD_WS_PORT=8790  # WebSocket papan peringkat (harus sama dengan alamat koneksi frontend)
CHAT_WS_PORT=8791  # WebSocket chat
SNOWFLAKE_WORKER_ID=2  # harus berbeda dari admin

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<didapat dari Google Cloud Console>
OAUTH_GOOGLE_CLIENT_SECRET=<kunci rahasia>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# Webhook Pembayaran
STRIPE_SECRET_KEY=<kunci rahasia Stripe>
STRIPE_WEBHOOK_SECRET=<didapat dari Stripe Dashboard>
PAYPAL_WEBHOOK_ID=<didapat dari PayPal Developer>
PAYPAL_VERIFY_URL=<alamat verifikasi webhook PayPal>
PAYPAL_CLIENT_ID=<PayPal Client ID>
PAYPAL_CLIENT_SECRET=<PayPal Client Secret>
PAYPAL_MODE=sandbox  # sandbox / live
NOWPAYMENTS_API_KEY=<kunci API NOWPayments>
NOWPAYMENTS_IPN_SECRET=<kunci tanda tangan IPN>
NOWPAYMENTS_API_URL=https://api.nowpayments.io  # alamat default
COINBASE_COMMERCE_API_KEY=<kunci API Coinbase Commerce>
COINBASE_COMMERCE_WEBHOOK_SECRET=<kunci webhook Coinbase Commerce>
SKRILL_API_URL=https://pay.skrill.com
SKRILL_API_KEY=<kunci API Skrill>
SKRILL_MERCHANT_ID=<ID merchant Skrill>
SKRILL_SECRET_WORD=<kunci verifikasi tanda tangan md5sig>
NETELLER_API_URL=https://api.neteller.com
NETELLER_CLIENT_ID=<Neteller Client ID>
NETELLER_CLIENT_SECRET=<Neteller Client Secret>
NETELLER_SECRET=<kunci verifikasi tanda tangan callback>
PAYSAFECARD_API_URL=https://api.paysafecard.com
PAYSAFECARD_API_KEY=<kunci API Paysafecard>
PAYSAFECARD_SECRET=<kunci verifikasi X-Signature HMAC-SHA256>
PAYTM_MID=<ID merchant Paytm>
PAYTM_KEY=<kunci Paytm>
PAYTM_API_URL=https://securegw.paytm.in
PAYTM_WEBSITE=DEFAULT  # produksi DEFAULT / staging WEBSTAGING
MERCADOPAGO_CLIENT_ID=<Mercado Pago Client ID>
MERCADOPAGO_CLIENT_SECRET=<Mercado Pago Client Secret>
MERCADOPAGO_WEBHOOK_SECRET=<kunci verifikasi X-Signature>
MERCADOPAGO_API_URL=https://api.mercadopago.com
ASTROPAY_LOGIN=<nama login AstroPay>
ASTROPAY_API_KEY=<kunci API AstroPay>
ASTROPAY_SECRET=<kunci verifikasi tanda tangan MD5>
ASTROPAY_API_URL=https://api.astropaycard.com
PAYPAY_CLIENT_ID=<PayPay Client ID>
PAYPAY_CLIENT_SECRET=<PayPay Client Secret>
PAYPAY_SIGNING_KEY=<kunci verifikasi PayPay-Signature>
PAYPAY_API_URL=https://api.paypay.ne.jp
KAKAOPAY_ADMIN_KEY=<KakaoPay Admin Key>
KAKAOPAY_CID=<CID merchant KakaoPay>
KAKAOPAY_APPROVAL_URL=<URL redirect persetujuan setelah pembayaran>
KAKAOPAY_API_URL=https://kapi.kakao.com
PAYMONGO_API_KEY=<kunci API PayMongo>
PAYMONGO_WEBHOOK_SECRET=<kunci verifikasi Paymongo-Signature>
PAYMONGO_API_URL=https://api.paymongo.com/v1
# M-Pesa / Paystack / Toss
MPESA_CONSUMER_KEY=<M-Pesa Consumer Key>
MPESA_CONSUMER_SECRET=<M-Pesa Consumer Secret>
MPESA_PASSKEY=<M-Pesa STK Push Passkey>
MPESA_SHORTCODE=<kode pendek M-Pesa>
MPESA_API_URL=https://api.safaricom.co.ke
PAYSTACK_SECRET_KEY=<Paystack Secret Key>
PAYSTACK_API_URL=https://api.paystack.co
TOSS_SECRET_KEY=<Toss Secret Key>
TOSS_API_URL=https://api.tosspayments.com
SITE_URL=https://your-domain.com  # URL situs untuk callback/redirect pembayaran
```

### 4.4 Mulai Layanan

```bash
# Backend administrasi (port default 8789, dapat diubah melalui APP_PORT di admin/.env)
cd /opt/game-platform/admin
php start.php start -d

# Bisnis sisi C (port default 8792, dapat diubah melalui APP_PORT di service/.env)
cd /opt/game-platform/service
php start.php start -d

# Verifikasi
curl http://localhost:8789/health
curl http://localhost:8792/health
```

### 4.5 Manajemen Proses (Systemd)

Buat `/etc/systemd/system/game-platform-admin.service`:

```ini
[Unit]
Description=Game Platform Admin
After=network.target mysql.service redis.service

[Service]
Type=forking
User=www-data
Group=www-data
WorkingDirectory=/opt/game-platform/admin
ExecStart=/usr/bin/php start.php start -d
ExecStop=/usr/bin/php start.php stop
ExecReload=/usr/bin/php start.php reload
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Buat juga `game-platform-service.service` dengan cara sama (ubah WorkingDirectory menjadi `/opt/game-platform/service`).

```bash
systemctl daemon-reload
systemctl enable --now game-platform-admin game-platform-service
```

---

## 5. Reverse Proxy Nginx

### 5.1 File Konfigurasi

Buat `/etc/nginx/sites-available/game-platform`:

```nginx
# Port menggunakan nilai default (admin 8789 / service 8792 / ws 8790); jika .env telah diubah, sesuaikan juga
server {
    listen 80;
    server_name your-domain.com;

    # nginx 自身发出的 301（如目录补斜杠 /admin-panel → /admin-panel/）改用相对
    # Location，客户端按当前 host:port 解析；默认绝对跳转会退回 listen 端口，
    # 非 80 端口部署（如 8080）时会跳错端口。
    absolute_redirect off;

    # API backend administrasi
    location /admin/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # API sisi C
    location /api/ {
        proxy_pass http://127.0.0.1:8792;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket papan peringkat (port default 8790, sama dengan LEADERBOARD_WS_PORT di service/.env)
    location /ws/ {
        proxy_pass http://127.0.0.1:8790;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # Pemeriksaan kesehatan
    location /health {
        proxy_pass http://127.0.0.1:8789;
    }

    # Prometheus 指标
    location /metrics {
        proxy_pass http://127.0.0.1:8789;
    }

    # ================================================================
    # 静态前端。两套前端定位不同：
    #   apps/*         = C 端玩家端（调 /api/ → service）
    #   admin/apps/*   = 管理台（调 /admin/ → admin）
    # 各产物需先构建；React/Angular 必须带子路径前缀构建，否则资源 404：
    #   apps/react            npm run build                （已含 --base=/app-react/）
    #   apps/angular          npm run build                （已含 --base-href=/app-angular/）
    #   admin/apps/react      npm run build                （已含 --base=/admin-react/）
    #   admin/apps/angular    npm run build                （已含 --base-href=/admin-angular/）
    #   admin/apps/flutter    flutter build web --base-href=/admin-flutter/
    #   apps/flutter/platform flutter build web            （挂在根路径）
    # try_files 末项是【内部重定向】，目标 index.html 不存在时会重新匹配同一 location
    # 形成重定向环，nginx 报 500 而非 404。规避方式按 location 类型二选一：
    #   root  型 → 末项追加 =404，把它降级为文件存在性判断；
    #   alias 型 → 追加 =404 会让兜底不再经 alias 解析，已构建的 SPA 深链接也会 404，
    #              所以保留原样，另加 location = 精确匹配兜底 URI（精确匹配优先，
    #              不会再回到前缀 location，环不成立）。
    # alias 的结尾斜杠必须与 location 的结尾斜杠一致（location /x 配 alias .../x，
    # location /x/ 配 alias .../x/）。错配时 /x../<路径> 会越级解析到上级目录，可读
    # 取 docroot 之外的任意文件，且 nginx -t 完全查不出来。
    # ================================================================

    # C 端主入口 — Flutter Web
    location / {
        root /opt/game-platform/apps/flutter/platform/build/web;
        try_files $uri $uri/ /index.html =404;
    }

    # C 端 React / Angular Web（URL 前缀与产物目录名不同，用 alias 直接指向产物）
    location /app-react/ {
        alias /opt/game-platform/apps/react/dist/;
        try_files $uri $uri/ /app-react/index.html;
    }
    location = /app-react/index.html {
        alias /opt/game-platform/apps/react/dist/index.html;
    }

    location /app-angular/ {
        alias /opt/game-platform/apps/angular/dist/game-client-angular/browser/;
        try_files $uri $uri/ /app-angular/index.html;
    }
    location = /app-angular/index.html {
        alias /opt/game-platform/apps/angular/dist/game-client-angular/browser/index.html;
    }

    # 管理台 — 通用投放位：把任一控制台产物拷进 admin/public 即可
    # 注意：location 不以 / 结尾时 alias 也【不能】以 / 结尾，否则 /admin-panel../.env
    # 会解析到上级目录（admin/.env）造成任意文件读取；nginx -t 查不出这类错配。
    location /admin-panel {
        alias /opt/game-platform/admin/public;
        try_files $uri $uri/ /admin-panel/index.html;
    }
    location = /admin-panel/index.html {
        alias /opt/game-platform/admin/public/index.html;
    }

    # 管理台 React / Angular / Flutter
    location /admin-react/ {
        alias /opt/game-platform/admin/apps/react/dist/;
        try_files $uri $uri/ /admin-react/index.html;
    }
    location = /admin-react/index.html {
        alias /opt/game-platform/admin/apps/react/dist/index.html;
    }

    location /admin-angular/ {
        alias /opt/game-platform/admin/apps/angular/dist/game-admin-angular/browser/;
        try_files $uri $uri/ /admin-angular/index.html;
    }
    location = /admin-angular/index.html {
        alias /opt/game-platform/admin/apps/angular/dist/game-admin-angular/browser/index.html;
    }

    location /admin-flutter/ {
        alias /opt/game-platform/admin/apps/flutter/build/web/;
        try_files $uri $uri/ /admin-flutter/index.html;
    }
    location = /admin-flutter/index.html {
        alias /opt/game-platform/admin/apps/flutter/build/web/index.html;
    }
}
```

> Pada deployment manual, Anda menaruh artefak hasil build di direktori tersebut (empat pohon sisi C: `apps/flutter/platform`, `apps/react`, `apps/angular`, `apps/harmonyos`; semua frontend konsol dipasang di `admin/apps/*` plus slot generik `admin/public`).
> Untuk Docker, lihat mount volume nginx di `docker-compose.yml` dan `nginx.conf.template` (path yang sama, root di dalam kontainer `/var/www/...`). HarmonyOS didistribusikan sebagai `.hap` dan tidak melalui nginx.

Aktifkan situs:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 5.2 Sertifikat SSL

```bash
# Gunakan Certbot untuk mendapatkan sertifikat Let's Encrypt otomatis
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# Perpanjangan otomatis (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 6. Tugas Terjadwal (Crontab)

```bash
# Edit crontab
crontab -e

# Snapshot statistik harian (setiap hari pukul 01:00)
0 1 * * * cd /opt/game-platform/admin && php start.php queue ComputeDailyStats

# Backup database (setiap hari pukul 02:00)
0 2 * * * cd /opt/game-platform/admin/database/backup && bash backup.sh

# Perpanjangan otomatis sertifikat SSL
0 3 * * * certbot renew --quiet && systemctl reload nginx

# Segarkan cache papan peringkat (setiap jam)
0 * * * * cd /opt/game-platform/admin && php start.php queue RefreshLeaderboards
```

---

## 7. Monitoring

### 7.1 Metrik Prometheus

Backend administrasi mengekspos endpoint `/metrics`, berisi metrik berikut:

| Metrik | Keterangan |
|------|------|
| openadmin_http_requests_total | Total permintaan |
| openadmin_active_users | Jumlah pengguna aktif |
| openadmin_db_connection_status | Koneksi database (0/1) |
| openadmin_redis_connection_status | Koneksi Redis (0/1) |
| openadmin_memory_usage_bytes | Penggunaan memori |

### 7.2 Pemeriksaan Kesehatan

```bash
# Backend administrasi
curl -f http://localhost:8789/health || echo "Admin DOWN"

# Bisnis sisi C
curl -f http://localhost:8792/health || echo "Service DOWN"

# Dapat dikonfigurasi di load balancer atau sistem monitoring
```

### 7.3 Log

```
admin/runtime/logs/
├── stdout.log          # Output standar
└── webman-<date>.log   # Log Webman

service/runtime/logs/
├── stdout.log
└── webman-<date>.log
```

---

## 8. Optimasi Performa

### 8.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # matikan pemeriksaan file di produksi
```

### 8.2 Optimasi MySQL

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # set ke 50-70% dari memori fisik
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # prioritas performa
max_connections = 200
query_cache_type = 0               # sudah dihapus di MySQL 8.0
```

### 8.3 Jumlah Proses Worker

```php
// config/process.php
'count' => cpu_count() * 2,  // disarankan 2-4 kali jumlah inti CPU di produksi
```

### 8.4 Strategi Cache Redis

| Kunci cache | TTL | Keterangan |
|--------|-----|------|
| dashboard:data | 300s | Data dasbor |
| i18n:translations | 3600s | Teks terjemahan |
| leaderboard:{id} | 3600s | Papan peringkat |
| rate_limit:{ip}:{route} | 60s | Jendela rate limit |

---

## 9. Penguatan Keamanan

### 9.1 Pembuatan Kunci

```bash
# Buat kunci acak
JWT_SECRET=$(openssl rand -hex 32)
HASHIDS_SALT=$(openssl rand -hex 16)
ENCRYPTION_KEY=$(openssl rand -hex 16)
ENCRYPTABLE_KEY=$(openssl rand -hex 16)
REDIS_PASSWORD=$(openssl rand -hex 16)
DB_PASSWORD=$(openssl rand -hex 16)

echo "JWT_SECRET=$JWT_SECRET"
echo "HASHIDS_SALT=$HASHIDS_SALT"
echo "ENCRYPTION_KEY=$ENCRYPTION_KEY"
echo "ENCRYPTABLE_KEY=$ENCRYPTABLE_KEY"
```

### 9.2 Firewall

```bash
# Hanya buka port yang diperlukan
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# Port internal tidak boleh diekspos
# 8789 (admin), 8792 (service), 8790/8791 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# Di atas adalah port default; jika .env root / .env masing-masing telah diubah, gunakan nilai sebenarnya
# Hanya diakses melalui 127.0.0.1
```

### 9.3 Izin File

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 10. Pemecahan Masalah

### 10.1 Layanan Tidak Dapat Dimulai

```bash
# Jalankan di depan untuk melihat error
cd /opt/game-platform/admin && php start.php start

# Periksa penggunaan port
ss -tlnp | grep -E '8789|8792'

# Periksa log
tail -f runtime/logs/webman-$(date +%F).log
```

### 10.2 Gagal Koneksi Database

```bash
# Uji koneksi
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# Periksa konfigurasi .env
grep DB_ admin/.env
```

### 10.3 Gagal Koneksi Redis

```bash
# Uji koneksi
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# Diharapkan mengembalikan PONG
```

### 10.4 Elasticsearch Tidak Tersedia

```bash
# Uji koneksi
curl http://127.0.0.1:9200

# Fungsi pencarian otomatis fallback ke kueri LIKE, layanan tidak terganggu
```

### 10.5 Masalah Performa

```bash
# Periksa jumlah proses worker
php start.php status

# Lihat penggunaan memori
free -h

# Periksa kueri lambat database
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 11. Panduan Upgrade

```bash
# 1. Tarik kode terbaru
cd /opt/game-platform && git pull origin main

# 2. Perbarui dependensi
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. Jalankan migrasi baru (jika ada)
mysql -u game-platform -p game-platform < install/File-migrasi-baru.sql

# 4. Restart halus (tidak menghentikan layanan)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
