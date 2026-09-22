# Deployment Guide
<!-- lang-nav -->

Languages: [中文](DEPLOYMENT.md) · **English** · [한국어](DEPLOYMENT.ko.md) · [Русский](DEPLOYMENT.ru.md) · [Deutsch](DEPLOYMENT.de.md) · [Français](DEPLOYMENT.fr.md) · [Español](DEPLOYMENT.es.md) · [Português](DEPLOYMENT.pt.md) · [हिन्दी](DEPLOYMENT.hi.md) · [العربية](DEPLOYMENT.ar.md) · [বাংলা](DEPLOYMENT.bn.md) · [Bahasa Indonesia](DEPLOYMENT.id.md) · [日本語](DEPLOYMENT.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Environment Requirements

| Component | Minimum Version | Recommended Configuration |
|------|---------|---------|
| OS | Linux (Ubuntu 20.04+ / Debian 11+ / CentOS 8+) | Ubuntu 22.04 LTS |
| PHP | 8.3+ | 8.3+ (CLI, OPcache enabled) |
| PHP extensions | pdo, pdo_mysql, pcntl, redis, gd, mbstring, xml | All |
| MySQL | 8.0+ | 8.0+ master-slave replication |
| Redis | 6.0+ | 7.x sentinel mode |
| Elasticsearch | 7.x+ | 8.x single node |
| Nginx | 1.20+ | Reverse proxy + gzip + SSL |
| Composer | 2.x | Latest stable |
| Flutter SDK | 3.x+ | Latest stable (only needed to build the frontend) |

---

## 2. One-Click Install Wizard (recommended for new deployments)

```bash
# 1. Clone the project
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Start the install wizard
php -S 0.0.0.0:8888 -t install/

# 3. Open http://<server-IP>:8888 in a browser
#    Complete the wizard: environment check → database config → admin account → auto install

# 4. Install dependencies
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. Start the services (default ports: admin 8789 / service 8792; change APP_PORT in each .env)
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. Security cleanup
rm -rf install/

# 7. Access the admin backend: http://<server-IP>:8789 (default port)
```

What the install wizard does:
- PHP environment check (version, extensions, directory permissions)
- Executes the merged SQL (`install/install.sql`), creating 78 tables and importing seed data
- Creates the super admin account (bcrypt-encrypted, associated with the super_admin role)
- Auto-generates JWT/Encryption/Hashids keys
- Writes `admin/.env` and `service/.env`
- Generates `install/install.lock` to prevent reinstallation

---

## 3. Docker Compose Deployment

### 3.1 One-Click Start

```bash
# 1. Clone the project
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Configure the environment with the one-click install wizard (or configure .env files manually)
#    Docker parameters such as ports live in the root .env (template .env.example): cp .env.example .env
php -S 0.0.0.0:8888 -t install/
# Manual: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. Build and start all services
docker-compose up -d

# 4. Check status
docker-compose ps

# 5. View logs
docker-compose logs -f
```

### 3.2 Service List

| Service | Container Name | Port | Description |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | Reverse proxy + static files |
| admin | game-platform-admin | 8789 | Admin backend API |
| service | game-platform-service | 8792 | C-end business API |
| leaderboard-ws | game-platform-ws | 8790, 8791 | WebSocket leaderboard/chat |
| mysql | game-platform-mysql | 3306 | Main database |
| redis | game-platform-redis | 6379 | Cache/rate limiting |
| elasticsearch | game-platform-es | 9200 | Full-text search |

> **Port configuration**: the table above lists the default ports; all of them can be changed in the project root `.env` (template `.env.example`; run `cp .env.example .env` and edit):
> `NGINX_HTTP_PORT`, `NGINX_HTTPS_PORT`, `ADMIN_PORT`, `SERVICE_PORT`, `LEADERBOARD_WS_PORT`, `CHAT_WS_PORT`, `MYSQL_PORT`, `REDIS_PORT`, `ES_PORT`.
> The upstream ports in `nginx.conf.template` are rendered automatically by the official image's envsubst — no manual Nginx config changes needed.
> In Docker deployments the public addresses (`APP_URL` / `SITE_URL`) follow `ADMIN_PORT` / `SERVICE_PORT` automatically by default (as `http://localhost:port`); for a custom domain or HTTPS, set `APP_URL` / `SITE_URL` in the root `.env` (this overrides the same keys in `admin/.env` and `service/.env`). For bare-metal (manual) deployments, still update the addresses yourself when changing ports.

### 3.3 Database Initialization

```bash
# Migration files run automatically on first MySQL startup
# Or run manually:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 3.4 Data Persistence

Data volumes are created automatically; no manual management needed:

| Volume | Path | Contents |
|----|------|------|
| mysql_data | /var/lib/mysql | Database files |
| redis_data | /data | Redis persistence |
| es_data | /usr/share/elasticsearch/data | ES indexes |

Backup:
```bash
# MySQL backup
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game-platform | gzip > backup_$(date +%Y%m%d).sql.gz

# Restore
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform
```

---

## 4. Manual Deployment

### 4.1 PHP Environment Configuration

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# Enable OPcache (mandatory in production)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 4.2 Install Dependencies

```bash
cd /opt/game-platform

# Admin backend
cd admin
cp .env.example .env
# Edit .env: database connection, JWT_SECRET, HASHIDS_SALT, etc.
composer install --no-dev --optimize-autoloader

# C-end service
cd ../service
cp .env.example .env
# Edit .env (note: SNOWFLAKE_WORKER_ID=2)
composer install --no-dev --optimize-autoloader
```

### 4.3 Configure .env

**Key admin/.env config:**
```ini
APP_ENV=production
APP_DEBUG=false
APP_PORT=8789  # webman HTTP listen port (keep in sync with APP_URL)
APP_URL=http://localhost:8789  # public access URL (API docs baseUrl, etc.)

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=game-platform
DB_USERNAME=game-platform
DB_PASSWORD=<强密码>

JWT_SECRET=<64位随机字符串>
JWT_TTL=7200

HASHIDS_SALT=<随机盐值>
HASHIDS_MIN_LENGTH=16

SNOWFLAKE_DATACENTER_ID=1
SNOWFLAKE_WORKER_ID=1

ENCRYPTION_KEY=<32字节随机密钥>
ENCRYPTABLE_KEY=<随机AES密钥>

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=<Redis密码>

SCOUT_HOSTS=127.0.0.1:9200
```

**Key service/.env config:**
```ini
# Same database, Redis and ES configuration as admin
APP_PORT=8792  # webman HTTP listen port
LEADERBOARD_WS_PORT=8790  # leaderboard WebSocket port (must match the frontend connection address)
CHAT_WS_PORT=8791  # chat WebSocket port
SNOWFLAKE_WORKER_ID=2  # must differ from admin

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<obtained from Google Cloud Console>
OAUTH_GOOGLE_CLIENT_SECRET=<secret key>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# Payment Webhook
STRIPE_SECRET_KEY=<Stripe secret key>
STRIPE_WEBHOOK_SECRET=<obtained from Stripe Dashboard>
PAYPAL_WEBHOOK_ID=<obtained from PayPal Developer>
PAYPAL_VERIFY_URL=<PayPal Webhook signature verification address>
PAYPAL_CLIENT_ID=<PayPal Client ID>
PAYPAL_CLIENT_SECRET=<PayPal Client Secret>
PAYPAL_MODE=sandbox  # sandbox / live
NOWPAYMENTS_API_KEY=<NOWPayments API key>
NOWPAYMENTS_IPN_SECRET=<IPN signing key>
NOWPAYMENTS_API_URL=https://api.nowpayments.io  # default address
COINBASE_COMMERCE_API_KEY=<Coinbase Commerce API key>
COINBASE_COMMERCE_WEBHOOK_SECRET=<Coinbase Commerce Webhook key>
SKRILL_API_URL=https://pay.skrill.com
SKRILL_API_KEY=<Skrill API key>
SKRILL_MERCHANT_ID=<Skrill merchant ID>
SKRILL_SECRET_WORD=<callback signature verification key md5sig>
NETELLER_API_URL=https://api.neteller.com
NETELLER_CLIENT_ID=<Neteller Client ID>
NETELLER_CLIENT_SECRET=<Neteller Client Secret>
NETELLER_SECRET=<callback signature verification key>
PAYSAFECARD_API_URL=https://api.paysafecard.com
PAYSAFECARD_API_KEY=<Paysafecard API key>
PAYSAFECARD_SECRET=<callback signature verification key X-Signature HMAC-SHA256>
PAYTM_MID=<Paytm merchant ID>
PAYTM_KEY=<Paytm key>
PAYTM_API_URL=https://securegw.paytm.in
PAYTM_WEBSITE=DEFAULT  # production DEFAULT / staging WEBSTAGING
MERCADOPAGO_CLIENT_ID=<Mercado Pago Client ID>
MERCADOPAGO_CLIENT_SECRET=<Mercado Pago Client Secret>
MERCADOPAGO_WEBHOOK_SECRET=<Webhook signature verification key X-Signature>
MERCADOPAGO_API_URL=https://api.mercadopago.com
ASTROPAY_LOGIN=<AstroPay login name>
ASTROPAY_API_KEY=<AstroPay API key>
ASTROPAY_SECRET=<callback signature verification key MD5>
ASTROPAY_API_URL=https://api.astropaycard.com
PAYPAY_CLIENT_ID=<PayPay Client ID>
PAYPAY_CLIENT_SECRET=<PayPay Client Secret>
PAYPAY_SIGNING_KEY=<Webhook signature verification key PayPay-Signature>
PAYPAY_API_URL=https://api.paypay.ne.jp
KAKAOPAY_ADMIN_KEY=<KakaoPay Admin Key>
KAKAOPAY_CID=<KakaoPay merchant CID>
KAKAOPAY_APPROVAL_URL=<approval redirect URL after payment>
KAKAOPAY_API_URL=https://kapi.kakao.com
PAYMONGO_API_KEY=<PayMongo API key>
PAYMONGO_WEBHOOK_SECRET=<Webhook signature verification key Paymongo-Signature>
PAYMONGO_API_URL=https://api.paymongo.com/v1
# M-Pesa / Paystack / Toss
MPESA_CONSUMER_KEY=<M-Pesa Consumer Key>
MPESA_CONSUMER_SECRET=<M-Pesa Consumer Secret>
MPESA_PASSKEY=<M-Pesa STK Push Passkey>
MPESA_SHORTCODE=<M-Pesa short code>
MPESA_API_URL=https://api.safaricom.co.ke
PAYSTACK_SECRET_KEY=<Paystack Secret Key>
PAYSTACK_API_URL=https://api.paystack.co
TOSS_SECRET_KEY=<Toss Secret Key>
TOSS_API_URL=https://api.tosspayments.com
SITE_URL=https://your-domain.com  # payment callback/redirect site URL
```

### 4.4 Start the Services

```bash
# Admin backend (default port 8789; change APP_PORT in admin/.env)
cd /opt/game-platform/admin
php start.php start -d

# C-end service (default port 8792; change APP_PORT in service/.env)
cd /opt/game-platform/service
php start.php start -d

# Verify
curl http://localhost:8789/health
curl http://localhost:8792/health
```

### 4.5 Process Management (Systemd)

Create `/etc/systemd/system/game-platform-admin.service`:

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

Create `game-platform-service.service` similarly (change WorkingDirectory to `/opt/game-platform/service`).

```bash
systemctl daemon-reload
systemctl enable --now game-platform-admin game-platform-service
```

---

## 5. Nginx Reverse Proxy

### 5.1 Configuration File

Create `/etc/nginx/sites-available/game-platform`:

```nginx
# Ports are defaults (admin 8789 / service 8792 / ws 8790); if you changed .env, adjust these accordingly
server {
    listen 80;
    server_name your-domain.com;

    # nginx 自身发出的 301（如目录补斜杠 /admin-panel → /admin-panel/）改用相对
    # Location，客户端按当前 host:port 解析；默认绝对跳转会退回 listen 端口，
    # 非 80 端口部署（如 8080）时会跳错端口。
    absolute_redirect off;

    # 管理后台 API
    location /admin/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # C端 API
    location /api/ {
        proxy_pass http://127.0.0.1:8792;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket leaderboard (default port 8790, matching LEADERBOARD_WS_PORT in service/.env)
    location /ws/ {
        proxy_pass http://127.0.0.1:8790;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # 健康检查
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

> For manual deployments, place the build artifacts in these directories yourself (four C-end trees: `apps/flutter/platform`, `apps/react`, `apps/angular`, `apps/harmonyos`; all console frontends are mounted under `admin/apps/*` plus the generic drop-in slot `admin/public`).
> For Docker deployments see the nginx volume mounts in `docker-compose.yml` and `nginx.conf.template` (same paths, container root `/var/www/...`). HarmonyOS ships as a `.hap` and is not served through nginx.

Enable the site:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 5.2 SSL Certificates

```bash
# Use Certbot to obtain a Let's Encrypt certificate automatically
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# Auto-renewal (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 6. Scheduled Tasks (Crontab)

```bash
# Edit crontab
crontab -e

# Daily stats snapshot (1:00 AM daily)
0 1 * * * cd /opt/game-platform/admin && php start.php queue ComputeDailyStats

# Database backup (2:00 AM daily)
0 2 * * * cd /opt/game-platform/admin/database/backup && bash backup.sh

# SSL certificate auto-renewal
0 3 * * * certbot renew --quiet && systemctl reload nginx

# Leaderboard cache refresh (hourly)
0 * * * * cd /opt/game-platform/admin && php start.php queue RefreshLeaderboards
```

---

## 7. Monitoring

### 7.1 Prometheus Metrics

The admin backend exposes the `/metrics` endpoint with the following metrics:

| Metric | Description |
|------|------|
| openadmin_http_requests_total | Total requests |
| openadmin_active_users | Active users |
| openadmin_db_connection_status | Database connection (0/1) |
| openadmin_redis_connection_status | Redis connection (0/1) |
| openadmin_memory_usage_bytes | Memory usage |

### 7.2 Health Checks

```bash
# Admin backend
curl -f http://localhost:8789/health || echo "Admin DOWN"

# C-end service
curl -f http://localhost:8792/health || echo "Service DOWN"

# Can be configured in a load balancer or monitoring system
```

### 7.3 Logs

```
admin/runtime/logs/
├── stdout.log          # 标准输出
└── webman-<date>.log   # Webman 日志

service/runtime/logs/
├── stdout.log
└── webman-<date>.log
```

---

## 8. Performance Tuning

### 8.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # 生产环境关闭文件检查
```

### 8.2 MySQL Tuning

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # 设为物理内存的 50-70%
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # 性能优先
max_connections = 200
query_cache_type = 0               # MySQL 8.0 已移除
```

### 8.3 Worker Process Count

```php
// config/process.php
'count' => cpu_count() * 2,  // 生产环境建议 2-4 倍 CPU 核心数
```

### 8.4 Redis Cache Strategy

| Cache Key | TTL | Description |
|--------|-----|------|
| dashboard:data | 300s | Dashboard data |
| i18n:translations | 3600s | Translation texts |
| leaderboard:{id} | 3600s | Leaderboard |
| rate_limit:{ip}:{route} | 60s | Rate limit window |

---

## 9. Security Hardening

### 9.1 Key Generation

```bash
# Generate random keys
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
# Only open the necessary ports
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# Internal ports should not be exposed
# 8789 (admin), 8792 (service), 8790/8791 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# The above are default ports; if you changed the root .env or the individual .env files, use the actual values
# Only accessible via 127.0.0.1
```

### 9.3 File Permissions

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 10. Troubleshooting

### 10.1 Service Fails to Start

```bash
# Run in the foreground to see errors
cd /opt/game-platform/admin && php start.php start

# Check port usage
ss -tlnp | grep -E '8789|8792'

# Check logs
tail -f runtime/logs/webman-$(date +%F).log
```

### 10.2 Database Connection Failure

```bash
# Test the connection
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# Check .env config
grep DB_ admin/.env
```

### 10.3 Redis Connection Failure

```bash
# Test the connection
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# Expect PONG
```

### 10.4 Elasticsearch Unavailable

```bash
# Test the connection
curl http://127.0.0.1:9200

# Search automatically falls back to LIKE queries; service is not interrupted
```

### 10.5 Performance Issues

```bash
# Check worker process count
php start.php status

# View memory usage
free -h

# Check database slow queries
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 11. Upgrade Guide

```bash
# 1. Pull the latest code
cd /opt/game-platform && git pull origin main

# 2. Update dependencies
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. Run new migrations (if any)
mysql -u game-platform -p game-platform < install/新迁移文件.sql

# 4. Graceful restart (no service interruption)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
