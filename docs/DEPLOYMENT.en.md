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

# 5. Start the services
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. Security cleanup
rm -rf install/

# 7. Access the admin backend: http://<server-IP>:8787
```

What the install wizard does:
- PHP environment check (version, extensions, directory permissions)
- Executes the merged SQL (`install/install.sql`), creating 52 tables and importing seed data
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
php -S 0.0.0.0:8888 -t install/
# Manual: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. Build and start all services
docker-compose up -d

# 4. Check status
docker-compose ps

# 5. View logs
docker-compose logs -f
```

### 2.2 Service List

| Service | Container Name | Port | Description |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | Reverse proxy + static files |
| admin | game-platform-admin | 8787 | Admin backend API |
| service | game-platform-service | 8788 | C-end business API |
| leaderboard-ws | game-platform-ws | 8789 | WebSocket leaderboard |
| mysql | game-platform-mysql | 3306 | Main database |
| redis | game-platform-redis | 6379 | Cache/rate limiting |
| elasticsearch | game-platform-es | 9200 | Full-text search |

### 2.3 Database Initialization

```bash
# Migration files run automatically on first MySQL startup
# Or run manually:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 2.4 Data Persistence

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

## 3. Manual Deployment

### 3.1 PHP Environment Configuration

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# Enable OPcache (mandatory in production)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 3.2 Install Dependencies

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

### 3.3 Configure .env

**Key admin/.env config:**
```ini
APP_ENV=production
APP_DEBUG=false

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
# 与 admin 相同的数据库、Redis、ES 配置
SNOWFLAKE_WORKER_ID=2  # 必须与 admin 不同

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<从Google Cloud Console获取>
OAUTH_GOOGLE_CLIENT_SECRET=<密钥>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# 支付 Webhook
STRIPE_WEBHOOK_SECRET=<从Stripe Dashboard获取>
PAYPAL_WEBHOOK_ID=<从PayPal Developer获取>
STRIPE_SECRET_KEY=<Stripe 密钥>
NOWPAYMENTS_API_KEY=<NOWPayments API 密钥>
NOWPAYMENTS_IPN_SECRET=<IPN 签名密钥>
NOWPAYMENTS_API_URL=https://api.nowpayments.io  # 默认地址
COINBASE_COMMERCE_API_KEY=<Coinbase Commerce API 密钥>
COINBASE_COMMERCE_WEBHOOK_SECRET=<Coinbase Commerce Webhook 密钥>
SITE_URL=https://your-domain.com  # 支付回调/跳转站点地址
```

### 3.4 Start the Services

```bash
# Admin backend (port 8787)
cd /opt/game-platform/admin
php start.php start -d

# C-end service (port 8788)
cd /opt/game-platform/service
php start.php start -d

# Verify
curl http://localhost:8787/health
curl http://localhost:8788/health
```

### 3.5 Process Management (Systemd)

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

## 4. Nginx Reverse Proxy

### 4.1 Configuration File

Create `/etc/nginx/sites-available/game-platform`:

```nginx
server {
    listen 80;
    server_name your-domain.com;

    # 管理后台 API
    location /admin/ {
        proxy_pass http://127.0.0.1:8787;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # C端 API
    location /api/ {
        proxy_pass http://127.0.0.1:8788;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket 排行榜
    location /ws/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # 健康检查
    location /health {
        proxy_pass http://127.0.0.1:8787;
    }

    # Prometheus 指标
    location /metrics {
        proxy_pass http://127.0.0.1:8787;
    }

    # 管理后台前端
    location /admin-panel {
        alias /opt/game-platform/admin/apps/flutter/build/web;
        try_files $uri $uri/ /admin-panel/index.html;
    }

    # C端平台前端
    location / {
        root /opt/game-platform/apps/flutter/platform/build/web;
        try_files $uri $uri/ /index.html;
    }
}
```

Enable the site:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 4.2 SSL Certificates

```bash
# Use Certbot to obtain a Let's Encrypt certificate automatically
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# Auto-renewal (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 5. Scheduled Tasks (Crontab)

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

## 6. Monitoring

### 6.1 Prometheus Metrics

The admin backend exposes the `/metrics` endpoint with the following metrics:

| Metric | Description |
|------|------|
| openadmin_http_requests_total | Total requests |
| openadmin_active_users | Active users |
| openadmin_db_connection_status | Database connection (0/1) |
| openadmin_redis_connection_status | Redis connection (0/1) |
| openadmin_memory_usage_bytes | Memory usage |

### 6.2 Health Checks

```bash
# Admin backend
curl -f http://localhost:8787/health || echo "Admin DOWN"

# C-end service
curl -f http://localhost:8788/health || echo "Service DOWN"

# Can be configured in a load balancer or monitoring system
```

### 6.3 Logs

```
admin/runtime/logs/
├── stdout.log          # 标准输出
└── workerman.log       # Workerman 日志

service/runtime/logs/
├── stdout.log
└── workerman.log
```

---

## 7. Performance Tuning

### 7.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # 生产环境关闭文件检查
```

### 7.2 MySQL Tuning

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # 设为物理内存的 50-70%
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # 性能优先
max_connections = 200
query_cache_type = 0               # MySQL 8.0 已移除
```

### 7.3 Worker Process Count

```php
// config/process.php
'count' => cpu_count() * 2,  // 生产环境建议 2-4 倍 CPU 核心数
```

### 7.4 Redis Cache Strategy

| Cache Key | TTL | Description |
|--------|-----|------|
| dashboard:data | 300s | Dashboard data |
| i18n:translations | 3600s | Translation texts |
| leaderboard:{id} | 3600s | Leaderboard |
| rate_limit:{ip}:{route} | 60s | Rate limit window |

---

## 8. Security Hardening

### 8.1 Key Generation

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

### 8.2 Firewall

```bash
# Only open the necessary ports
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# Internal ports should not be exposed
# 8787 (admin), 8788 (service), 8789 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# Only accessible via 127.0.0.1
```

### 8.3 File Permissions

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 9. Troubleshooting

### 9.1 Service Fails to Start

```bash
# Run in the foreground to see errors
cd /opt/game-platform/admin && php start.php start

# Check port usage
ss -tlnp | grep -E '8787|8788'

# Check logs
tail -f runtime/logs/workerman.log
```

### 9.2 Database Connection Failure

```bash
# Test the connection
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# Check .env config
grep DB_ admin/.env
```

### 9.3 Redis Connection Failure

```bash
# Test the connection
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# Expect PONG
```

### 9.4 Elasticsearch Unavailable

```bash
# Test the connection
curl http://127.0.0.1:9200

# Search automatically falls back to LIKE queries; service is not interrupted
```

### 9.5 Performance Issues

```bash
# Check worker process count
php start.php status

# View memory usage
free -h

# Check database slow queries
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 10. Upgrade Guide

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
