# Bereitstellungsdokument
<!-- lang-nav -->

Languages: **中文** · [English](DEPLOYMENT.en.md) · [한국어](DEPLOYMENT.ko.md) · [Русский](DEPLOYMENT.ru.md) · [Deutsch](DEPLOYMENT.de.md) · [Français](DEPLOYMENT.fr.md) · [Español](DEPLOYMENT.es.md) · [Português](DEPLOYMENT.pt.md) · [हिन्दी](DEPLOYMENT.hi.md) · [العربية](DEPLOYMENT.ar.md) · [বাংলা](DEPLOYMENT.bn.md) · [Bahasa Indonesia](DEPLOYMENT.id.md) · [日本語](DEPLOYMENT.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Umgebungsanforderungen

| Komponente | Mindestversion | Empfohlene Konfiguration |
|------|---------|---------|
| OS | Linux (Ubuntu 20.04+ / Debian 11+ / CentOS 8+) | Ubuntu 22.04 LTS |
| PHP | 8.3+ | 8.3+ (CLI, OPcache aktiviert) |
| PHP-Erweiterungen | pdo, pdo_mysql, pcntl, redis, gd, mbstring, xml | alle |
| MySQL | 8.0+ | 8.0+ Master-Slave-Replikation |
| Redis | 6.0+ | 7.x Sentinel-Modus |
| Elasticsearch | 7.x+ | 8.x Einzelknoten |
| Nginx | 1.20+ | Reverse-Proxy + gzip + SSL |
| Composer | 2.x | Neueste stabile Version |
| Flutter SDK | 3.x+ | Neueste stabile Version (nur zum Erstellen des Frontends erforderlich) |

---

## 2. Ein-Klick-Installationsassistent (empfohlen für neue Deployments)

```bash
# 1. 克隆项目
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. 启动安装向导
php -S 0.0.0.0:8888 -t install/

# 3. 浏览器打开 http://<服务器IP>:8888
#    按向导完成：环境检查 → 数据库配置 → 管理员账户 → 自动安装

# 4. 安装依赖
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. 启动服务
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. 安全清理
rm -rf install/

# 7. 访问管理后台: http://<服务器IP>:8787
```

Vom Installationsassistenten durchgeführte Schritte:
- PHP-Umgebungsprüfung (Version, Erweiterungen, Verzeichnisrechte)
- Ausführen des zusammengeführten SQL (`install/install.sql`), Erstellen von 52 Tabellen und Importieren der Seed-Daten
- Erstellen des Super-Admin-Kontos (bcrypt-verschlüsselt, verknüpft mit der super_admin-Rolle)
- Automatische Generierung der JWT-/Encryption-/Hashids-Schlüssel
- Schreiben von `admin/.env` und `service/.env`
- Erzeugen von `install/install.lock` zur Verhinderung einer erneuten Installation

---

## 3. Docker-Compose-Bereitstellung

### 3.1 Ein-Klick-Start

```bash
# 1. 克隆项目
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. 使用一键安装向导配置环境（或手动配置 .env 文件）
php -S 0.0.0.0:8888 -t install/
# 手动方式: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. 构建并启动所有服务
docker-compose up -d

# 4. 查看状态
docker-compose ps

# 5. 查看日志
docker-compose logs -f
```

### 2.2 Dienstübersicht

| Dienst | Container-Name | Port | Beschreibung |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | Reverse-Proxy + statische Dateien |
| admin | game-platform-admin | 8787 | Verwaltungsbackend-API |
| service | game-platform-service | 8788 | C-End-Geschäfts-API |
| leaderboard-ws | game-platform-ws | 8789 | WebSocket-Rangliste |
| mysql | game-platform-mysql | 3306 | Hauptdatenbank |
| redis | game-platform-redis | 6379 | Cache/Ratenbegrenzung |
| elasticsearch | game-platform-es | 9200 | Volltextsuche |

### 2.3 Datenbankinitialisierung

```bash
# 迁移文件会在 MySQL 首次启动时自动执行
# 或手动执行:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < install/install.sql
```

### 2.4 Datenpersistenz

Datenvolumes werden automatisch erstellt, keine manuelle Verwaltung erforderlich:

| Volume | Pfad | Inhalt |
|----|------|------|
| mysql_data | /var/lib/mysql | Datenbankdateien |
| redis_data | /data | Redis-Persistenz |
| es_data | /usr/share/elasticsearch/data | ES-Indizes |

Backup:
```bash
# MySQL 备份
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game_platform | gzip > backup_$(date +%Y%m%d).sql.gz

# 恢复
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform
```

---

## 3. Manuelle Bereitstellung

### 3.1 PHP-Umgebungskonfiguration

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# 启用 OPcache（生产环境必须）
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 3.2 Abhängigkeiten installieren

```bash
cd /opt/game-platform

# 管理后台
cd admin
cp .env.example .env
# 编辑 .env: 数据库连接、JWT_SECRET、HASHIDS_SALT 等
composer install --no-dev --optimize-autoloader

# C端业务
cd ../service
cp .env.example .env
# 编辑 .env (注意: SNOWFLAKE_WORKER_ID=2)
composer install --no-dev --optimize-autoloader
```

### 3.3 .env konfigurieren

**Wichtige Konfiguration für admin/.env:**
```ini
APP_ENV=production
APP_DEBUG=false

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=game_platform
DB_USERNAME=game_platform
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

**Wichtige Konfiguration für service/.env:**
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
```

### 3.4 Dienste starten

```bash
# 管理后台 (端口 8787)
cd /opt/game-platform/admin
php start.php start -d

# C端业务 (端口 8788)
cd /opt/game-platform/service
php start.php start -d

# 验证
curl http://localhost:8787/health
curl http://localhost:8788/health
```

### 3.5 Prozessverwaltung (Systemd)

`/etc/systemd/system/game-platform-admin.service` erstellen:

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

Ebenso `game-platform-service.service` erstellen (WorkingDirectory auf `/opt/game-platform/service` ändern).

```bash
systemctl daemon-reload
systemctl enable --now game-platform-admin game-platform-service
```

---

## 4. Nginx-Reverse-Proxy

### 4.1 Konfigurationsdatei

`/etc/nginx/sites-available/game-platform` erstellen:

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

Site aktivieren:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 4.2 SSL-Zertifikate

```bash
# 使用 Certbot 自动获取 Let's Encrypt 证书
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# 自动续期 (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 5. Geplante Aufgaben (Crontab)

```bash
# 编辑 crontab
crontab -e

# 日统计快照 (每天凌晨 1:00)
0 1 * * * cd /opt/game-platform/admin && php start.php queue ComputeDailyStats

# 数据库备份 (每天凌晨 2:00)
0 2 * * * cd /opt/game-platform/admin/database/backup && bash backup.sh

# SSL 证书自动续期
0 3 * * * certbot renew --quiet && systemctl reload nginx

# 排行榜缓存刷新 (每小时)
0 * * * * cd /opt/game-platform/admin && php start.php queue RefreshLeaderboards
```

---

## 6. Überwachung

### 6.1 Prometheus-Metriken

Das Verwaltungsbackend stellt den `/metrics`-Endpunkt mit folgenden Metriken bereit:

| Metrik | Beschreibung |
|------|------|
| openadmin_http_requests_total | Gesamtzahl der Anfragen |
| openadmin_active_users | Anzahl aktiver Benutzer |
| openadmin_db_connection_status | Datenbankverbindung (0/1) |
| openadmin_redis_connection_status | Redis-Verbindung (0/1) |
| openadmin_memory_usage_bytes | Speichernutzung |

### 6.2 Gesundheitschecks

```bash
# 管理后台
curl -f http://localhost:8787/health || echo "Admin DOWN"

# C端业务
curl -f http://localhost:8788/health || echo "Service DOWN"

# 可在负载均衡器或监控系统中配置
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

## 7. Leistungsoptimierung

### 7.1 PHP-OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # 生产环境关闭文件检查
```

### 7.2 MySQL-Optimierung

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # 设为物理内存的 50-70%
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # 性能优先
max_connections = 200
query_cache_type = 0               # MySQL 8.0 已移除
```

### 7.3 Anzahl der Worker-Prozesse

```php
// config/process.php
'count' => cpu_count() * 2,  // 生产环境建议 2-4 倍 CPU 核心数
```

### 7.4 Redis-Cache-Strategie

| Cache-Schlüssel | TTL | Beschreibung |
|--------|-----|------|
| dashboard:data | 300s | Dashboard-Daten |
| i18n:translations | 3600s | Übersetzungstexte |
| leaderboard:{id} | 3600s | Rangliste |
| rate_limit:{ip}:{route} | 60s | Rate-Limit-Fenster |

---

## 8. Sicherheitshärtung

### 8.1 Schlüsselgenerierung

```bash
# 生成随机密钥
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
# 仅开放必要端口
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# 内部端口不应暴露
# 8787 (admin), 8788 (service), 8789 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# 仅通过 127.0.0.1 访问
```

### 8.3 Dateiberechtigungen

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 9. Fehlerbehebung

### 9.1 Dienst startet nicht

```bash
# 前台运行查看错误
cd /opt/game-platform/admin && php start.php start

# 检查端口占用
ss -tlnp | grep -E '8787|8788'

# 检查日志
tail -f runtime/logs/workerman.log
```

### 9.2 Datenbankverbindung fehlgeschlagen

```bash
# 测试连接
mysql -h 127.0.0.1 -u game_platform -p game_platform -e "SELECT 1"

# 检查 .env 配置
grep DB_ admin/.env
```

### 9.3 Redis-Verbindung fehlgeschlagen

```bash
# 测试连接
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# 预期返回 PONG
```

### 9.4 Elasticsearch nicht verfügbar

```bash
# 测试连接
curl http://127.0.0.1:9200

# 搜索功能会自动回退到 LIKE 查询，不会中断服务
```

### 9.5 Leistungsprobleme

```bash
# 检查 worker 进程数
php start.php status

# 查看内存使用
free -h

# 检查数据库慢查询
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 10. Upgrade-Anleitung

```bash
# 1. 拉取最新代码
cd /opt/game-platform && git pull origin main

# 2. 更新依赖
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. 执行新迁移（如有）
mysql -u game_platform -p game_platform < install/新迁移文件.sql

# 4. 平滑重启（不中断服务）
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
