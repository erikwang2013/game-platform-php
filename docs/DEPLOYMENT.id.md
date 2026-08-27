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

# 5. Mulai layanan
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. Pembersihan keamanan
rm -rf install/

# 7. Akses backend administrasi: http://<IP-server>:8787
```

Yang dilakukan wizard instalasi:
- Pemeriksaan lingkungan PHP (versi, ekstensi, izin direktori)
- Mengeksekusi SQL gabungan (`install/install.sql`), membuat 52 tabel dan mengimpor data seed
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
php -S 0.0.0.0:8888 -t install/
# Cara manual: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. Build dan mulai semua layanan
docker-compose up -d

# 4. Lihat status
docker-compose ps

# 5. Lihat log
docker-compose logs -f
```

### 2.2 Daftar Layanan

| Layanan | Nama container | Port | Keterangan |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | Reverse proxy + file statis |
| admin | game-platform-admin | 8787 | API backend administrasi |
| service | game-platform-service | 8788 | API bisnis sisi C |
| leaderboard-ws | game-platform-ws | 8789 | WebSocket papan peringkat |
| mysql | game-platform-mysql | 3306 | Database utama |
| redis | game-platform-redis | 6379 | Cache/rate limit |
| elasticsearch | game-platform-es | 9200 | Pencarian full-text |

### 2.3 Inisialisasi Database

```bash
# File migrasi dieksekusi otomatis saat MySQL pertama kali dimulai
# Atau eksekusi manual:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < install/install.sql
```

### 2.4 Persistensi Data

Volume data dibuat otomatis, tidak perlu dikelola manual:

| Volume | Jalur | Konten |
|----|------|------|
| mysql_data | /var/lib/mysql | File database |
| redis_data | /data | Persistensi Redis |
| es_data | /usr/share/elasticsearch/data | Indeks ES |

Backup:
```bash
# Backup MySQL
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game_platform | gzip > backup_$(date +%Y%m%d).sql.gz

# Restore
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform
```

---

## 3. Deployment Manual

### 3.1 Konfigurasi Lingkungan PHP

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# Aktifkan OPcache (wajib di produksi)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 3.2 Install Dependensi

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

### 3.3 Konfigurasi .env

**Konfigurasi kunci admin/.env:**
```ini
APP_ENV=production
APP_DEBUG=false

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=game_platform
DB_USERNAME=game_platform
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
SNOWFLAKE_WORKER_ID=2  # harus berbeda dari admin

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<didapat dari Google Cloud Console>
OAUTH_GOOGLE_CLIENT_SECRET=<kunci rahasia>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# Webhook Pembayaran
STRIPE_WEBHOOK_SECRET=<didapat dari Stripe Dashboard>
PAYPAL_WEBHOOK_ID=<didapat dari PayPal Developer>
```

### 3.4 Mulai Layanan

```bash
# Backend administrasi (port 8787)
cd /opt/game-platform/admin
php start.php start -d

# Bisnis sisi C (port 8788)
cd /opt/game-platform/service
php start.php start -d

# Verifikasi
curl http://localhost:8787/health
curl http://localhost:8788/health
```

### 3.5 Manajemen Proses (Systemd)

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

## 4. Reverse Proxy Nginx

### 4.1 File Konfigurasi

Buat `/etc/nginx/sites-available/game-platform`:

```nginx
server {
    listen 80;
    server_name your-domain.com;

    # API backend administrasi
    location /admin/ {
        proxy_pass http://127.0.0.1:8787;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # API sisi C
    location /api/ {
        proxy_pass http://127.0.0.1:8788;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket papan peringkat
    location /ws/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # Pemeriksaan kesehatan
    location /health {
        proxy_pass http://127.0.0.1:8787;
    }

    # Metrik Prometheus
    location /metrics {
        proxy_pass http://127.0.0.1:8787;
    }

    # Frontend backend administrasi
    location /admin-panel {
        alias /opt/game-platform/admin/apps/flutter/build/web;
        try_files $uri $uri/ /admin-panel/index.html;
    }

    # Frontend platform sisi C
    location / {
        root /opt/game-platform/apps/flutter/platform/build/web;
        try_files $uri $uri/ /index.html;
    }
}
```

Aktifkan situs:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 4.2 Sertifikat SSL

```bash
# Gunakan Certbot untuk mendapatkan sertifikat Let's Encrypt otomatis
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# Perpanjangan otomatis (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 5. Tugas Terjadwal (Crontab)

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

## 6. Monitoring

### 6.1 Metrik Prometheus

Backend administrasi mengekspos endpoint `/metrics`, berisi metrik berikut:

| Metrik | Keterangan |
|------|------|
| openadmin_http_requests_total | Total permintaan |
| openadmin_active_users | Jumlah pengguna aktif |
| openadmin_db_connection_status | Koneksi database (0/1) |
| openadmin_redis_connection_status | Koneksi Redis (0/1) |
| openadmin_memory_usage_bytes | Penggunaan memori |

### 6.2 Pemeriksaan Kesehatan

```bash
# Backend administrasi
curl -f http://localhost:8787/health || echo "Admin DOWN"

# Bisnis sisi C
curl -f http://localhost:8788/health || echo "Service DOWN"

# Dapat dikonfigurasi di load balancer atau sistem monitoring
```

### 6.3 Log

```
admin/runtime/logs/
├── stdout.log          # Output standar
└── workerman.log       # Log Workerman

service/runtime/logs/
├── stdout.log
└── workerman.log
```

---

## 7. Optimasi Performa

### 7.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # matikan pemeriksaan file di produksi
```

### 7.2 Optimasi MySQL

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # set ke 50-70% dari memori fisik
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # prioritas performa
max_connections = 200
query_cache_type = 0               # sudah dihapus di MySQL 8.0
```

### 7.3 Jumlah Proses Worker

```php
// config/process.php
'count' => cpu_count() * 2,  // disarankan 2-4 kali jumlah inti CPU di produksi
```

### 7.4 Strategi Cache Redis

| Kunci cache | TTL | Keterangan |
|--------|-----|------|
| dashboard:data | 300s | Data dasbor |
| i18n:translations | 3600s | Teks terjemahan |
| leaderboard:{id} | 3600s | Papan peringkat |
| rate_limit:{ip}:{route} | 60s | Jendela rate limit |

---

## 8. Penguatan Keamanan

### 8.1 Pembuatan Kunci

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

### 8.2 Firewall

```bash
# Hanya buka port yang diperlukan
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# Port internal tidak boleh diekspos
# 8787 (admin), 8788 (service), 8789 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# Hanya diakses melalui 127.0.0.1
```

### 8.3 Izin File

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 9. Pemecahan Masalah

### 9.1 Layanan Tidak Dapat Dimulai

```bash
# Jalankan di depan untuk melihat error
cd /opt/game-platform/admin && php start.php start

# Periksa penggunaan port
ss -tlnp | grep -E '8787|8788'

# Periksa log
tail -f runtime/logs/workerman.log
```

### 9.2 Gagal Koneksi Database

```bash
# Uji koneksi
mysql -h 127.0.0.1 -u game_platform -p game_platform -e "SELECT 1"

# Periksa konfigurasi .env
grep DB_ admin/.env
```

### 9.3 Gagal Koneksi Redis

```bash
# Uji koneksi
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# Diharapkan mengembalikan PONG
```

### 9.4 Elasticsearch Tidak Tersedia

```bash
# Uji koneksi
curl http://127.0.0.1:9200

# Fungsi pencarian otomatis fallback ke kueri LIKE, layanan tidak terganggu
```

### 9.5 Masalah Performa

```bash
# Periksa jumlah proses worker
php start.php status

# Lihat penggunaan memori
free -h

# Periksa kueri lambat database
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 10. Panduan Upgrade

```bash
# 1. Tarik kode terbaru
cd /opt/game-platform && git pull origin main

# 2. Perbarui dependensi
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. Jalankan migrasi baru (jika ada)
mysql -u game_platform -p game_platform < install/File-migrasi-baru.sql

# 4. Restart halus (tidak menghentikan layanan)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
