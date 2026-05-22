# Deployment Guide

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| OS | Linux (Ubuntu 20.04+ / Debian 11+ / CentOS 8+) | Ubuntu 22.04 LTS |
| PHP | 8.3+ | 8.3+ (CLI, OPcache enabled) |
| PHP Extensions | pdo, pdo_mysql, pcntl, redis, gd, mbstring, xml | All |
| MySQL | 8.0+ | 8.0+ master-slave |
| Redis | 6.0+ | 7.x sentinel |
| Elasticsearch | 7.x+ | 8.x single node |
| Nginx | 1.20+ | Reverse proxy + gzip + SSL |
| Composer | 2.x | Latest stable |
| Flutter SDK | 3.x+ | Latest stable (frontend build only) |

---

## 2. Docker Compose (Recommended)

### 2.1 Quick Start

```bash
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

cp admin/.env.example admin/.env
cp service/.env.example service/.env
# Edit .env files with your database password, JWT secret, etc.

docker-compose up -d
docker-compose ps
docker-compose logs -f
```

### 2.2 Services

| Service | Container | Port | Description |
|---------|-----------|------|-------------|
| nginx | game-platform-nginx | 80, 443 | Reverse proxy + static files |
| admin | game-platform-admin | 8787 | Admin backend API |
| service | game-platform-service | 8788 | User-facing API |
| leaderboard-ws | game-platform-ws | 8789 | WebSocket leaderboard |
| mysql | game-platform-mysql | 3306 | Primary database |
| redis | game-platform-redis | 6379 | Cache / rate limiting |
| elasticsearch | game-platform-es | 9200 | Full-text search |

### 2.3 Database Init

```bash
# Run migrations (auto-executed on first MySQL start)
for f in admin/database/migrations/*.sql; do
  docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < $f
done
```

### 2.4 Backups

```bash
# MySQL backup
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game_platform | gzip > backup_$(date +%Y%m%d).sql.gz

# Restore
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform
```

---

## 3. Manual Deployment

### 3.1 Install Dependencies

```bash
# Ubuntu/Debian
apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# Enable OPcache
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 3.2 Install Composer Dependencies

```bash
cd /opt/game-platform/admin
cp .env.example .env && composer install --no-dev --optimize-autoloader

cd /opt/game-platform/service
cp .env.example .env && composer install --no-dev --optimize-autoloader
```

### 3.3 .env Configuration

**Key settings for admin/.env:**
```ini
APP_ENV=production
APP_DEBUG=false
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=game_platform
DB_USERNAME=game_platform
DB_PASSWORD=<strong-password>
JWT_SECRET=<64-char-random-string>
HASHIDS_SALT=<random-salt>
ENCRYPTION_KEY=<32-byte-random-key>
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

**Key settings for service/.env:**
```ini
SNOWFLAKE_WORKER_ID=2  # Must differ from admin
OAUTH_GOOGLE_CLIENT_ID=<from Google Cloud Console>
OAUTH_GOOGLE_CLIENT_SECRET=<secret>
STRIPE_WEBHOOK_SECRET=<from Stripe Dashboard>
PAYPAL_WEBHOOK_ID=<from PayPal Developer>
```

### 3.4 Start Services

```bash
cd /opt/game-platform/admin && php start.php start -d
cd /opt/game-platform/service && php start.php start -d

# Verify
curl http://localhost:8787/health
curl http://localhost:8788/health
```

### 3.5 Systemd Service

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

[Install]
WantedBy=multi-user.target
```

Create similar `game-platform-service.service` for the service directory.

```bash
systemctl daemon-reload
systemctl enable --now game-platform-admin game-platform-service
```

---

## 4. Nginx Reverse Proxy

```nginx
server {
    listen 80;
    server_name your-domain.com;

    location /admin/ {
        proxy_pass http://127.0.0.1:8787;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    location /api/ {
        proxy_pass http://127.0.0.1:8788;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    location /ws/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }

    location / {
        root /opt/game-platform/apps/flutter/platform/build/web;
        try_files $uri $uri/ /index.html;
    }
}
```

### SSL with Certbot

```bash
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com
# Auto-renewal: 0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 5. Crontab

```bash
# Daily stats (1:00 AM daily)
0 1 * * * cd /opt/game-platform/admin && php start.php queue ComputeDailyStats

# Database backup (2:00 AM daily)
0 2 * * * cd /opt/game-platform/admin/database/backup && bash backup.sh

# SSL renewal (3:00 AM daily)
0 3 * * * certbot renew --quiet && systemctl reload nginx

# Leaderboard cache refresh (hourly)
0 * * * * cd /opt/game-platform/admin && php start.php queue RefreshLeaderboards
```

---

## 6. Monitoring

### Prometheus Metrics at `/metrics`

| Metric | Description |
|--------|-------------|
| openadmin_http_requests_total | Total requests |
| openadmin_active_users | Active users |
| openadmin_db_connection_status | DB connection (0/1) |
| openadmin_redis_connection_status | Redis connection (0/1) |
| openadmin_memory_usage_bytes | Memory usage |

### Health Checks

```bash
curl -f http://localhost:8787/health || alert "Admin DOWN"
curl -f http://localhost:8788/health || alert "Service DOWN"
```

---

## 7. Performance Tuning

### PHP OPcache
```ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.validate_timestamps=0
```

### MySQL
```ini
innodb_buffer_pool_size = 2G
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2
max_connections = 200
```

### Worker Processes
```php
'count' => cpu_count() * 2,  // 2-4x CPU cores for production
```

---

## 8. Security Hardening

### Generate Random Keys

```bash
JWT_SECRET=$(openssl rand -hex 32)
HASHIDS_SALT=$(openssl rand -hex 16)
ENCRYPTION_KEY=$(openssl rand -hex 16)
DB_PASSWORD=$(openssl rand -hex 16)
echo "JWT_SECRET=$JWT_SECRET"
echo "HASHIDS_SALT=$HASHIDS_SALT"
echo "ENCRYPTION_KEY=$ENCRYPTION_KEY"
```

### Firewall

```bash
ufw default deny incoming
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

### File Permissions

```bash
chown -R www-data:www-data /opt/game-platform
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 9. Troubleshooting

### Service won't start

```bash
cd /opt/game-platform/admin && php start.php start  # Foreground for errors
ss -tlnp | grep -E '8787|8788'                      # Check port usage
tail -f runtime/logs/workerman.log                   # Check logs
```

### Database connection failure

```bash
mysql -h 127.0.0.1 -u game_platform -p -e "SELECT 1"
grep DB_ admin/.env
```

### Redis connection failure

```bash
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping  # Expect PONG
```

### ES unavailable

Search falls back to LIKE queries automatically — no service interruption.

---

## 10. Upgrade Guide

```bash
cd /opt/game-platform && git pull origin main
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# Run new migrations
mysql -u game_platform -p game_platform < admin/database/migrations/NEW_FILE.sql

# Graceful restart (zero downtime)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
