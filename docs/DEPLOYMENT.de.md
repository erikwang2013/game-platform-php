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
# 1. Projekt klonen
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Installationsassistenten starten
php -S 0.0.0.0:8888 -t install/

# 3. Im Browser http://<Server-IP>:8888 öffnen
#    Assistenten durchlaufen: Umgebungsprüfung → Datenbankkonfiguration → Administratorkonto → automatische Installation

# 4. Abhängigkeiten installieren
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. Dienste starten (Standardports admin 8789 / service 8792, änderbar über APP_PORT in der jeweiligen .env)
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. Sicherheitsbereinigung
rm -rf install/

# 7. Zugriff auf das Verwaltungsbackend: http://<Server-IP>:8789 (Standardport)
```

Vom Installationsassistenten durchgeführte Schritte:
- PHP-Umgebungsprüfung (Version, Erweiterungen, Verzeichnisrechte)
- Ausführen des zusammengeführten SQL (`install/install.sql`), Erstellen von 78 Tabellen und Importieren der Seed-Daten
- Erstellen des Super-Admin-Kontos (bcrypt-verschlüsselt, verknüpft mit der super_admin-Rolle)
- Automatische Generierung der JWT-/Encryption-/Hashids-Schlüssel
- Schreiben von `admin/.env` und `service/.env`
- Erzeugen von `install/install.lock` zur Verhinderung einer erneuten Installation

---

## 3. Docker-Compose-Bereitstellung

### 3.1 Ein-Klick-Start

```bash
# 1. Projekt klonen
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Umgebung mit dem Ein-Klick-Installationsassistenten konfigurieren (oder .env-Dateien manuell konfigurieren)
#    Docker-Parameter wie Ports stehen in der .env im Projektstamm (Vorlage .env.example): cp .env.example .env
php -S 0.0.0.0:8888 -t install/
# Manuell: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. Alle Dienste bauen und starten
docker-compose up -d

# 4. Status prüfen
docker-compose ps

# 5. Logs ansehen
docker-compose logs -f
```

### 3.2 Dienstübersicht

| Dienst | Container-Name | Port | Beschreibung |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | Reverse-Proxy + statische Dateien |
| admin | game-platform-admin | 8789 | Verwaltungsbackend-API |
| service | game-platform-service | 8792 | C-End-Geschäfts-API |
| leaderboard-ws | game-platform-ws | 8790, 8791 | WebSocket-Rangliste/Chat |
| mysql | game-platform-mysql | 3306 | Hauptdatenbank |
| redis | game-platform-redis | 6379 | Cache/Ratenbegrenzung |
| elasticsearch | game-platform-es | 9200 | Volltextsuche |

> **Portkonfiguration**: Die Ports in der Tabelle sind Standardwerte und können alle in der `.env` im Projektstamm geändert werden (Vorlage `.env.example`; nach `cp .env.example .env` bearbeiten):
> `NGINX_HTTP_PORT`, `NGINX_HTTPS_PORT`, `ADMIN_PORT`, `SERVICE_PORT`, `LEADERBOARD_WS_PORT`, `CHAT_WS_PORT`, `MYSQL_PORT`, `REDIS_PORT`, `ES_PORT`.
> Die upstream-Ports in `nginx.conf.template` werden vom offiziellen Image per envsubst automatisch gerendert; die Nginx-Konfiguration muss nicht manuell angepasst werden.
> Bei Docker-Bereitstellungen folgen die öffentlichen Adressen (`APP_URL` / `SITE_URL`) standardmäßig automatisch `ADMIN_PORT` / `SERVICE_PORT` (Format `http://localhost:Port`); für eine eigene Domain oder HTTPS `APP_URL` / `SITE_URL` in der Root-`.env` setzen (überschreibt die gleichnamigen Einträge in `admin/.env` und `service/.env`). Bei Bare-Metal-Bereitstellungen (manuell) müssen die Adressen bei Portänderungen weiterhin selbst angepasst werden.

### 3.3 Datenbankinitialisierung

```bash
# Migrationsdateien werden beim ersten Start von MySQL automatisch ausgeführt
# Oder manuell ausführen:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 3.4 Datenpersistenz

Datenvolumes werden automatisch erstellt, keine manuelle Verwaltung erforderlich:

| Volume | Pfad | Inhalt |
|----|------|------|
| mysql_data | /var/lib/mysql | Datenbankdateien |
| redis_data | /data | Redis-Persistenz |
| es_data | /usr/share/elasticsearch/data | ES-Indizes |

Backup:
```bash
# MySQL-Backup
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game-platform | gzip > backup_$(date +%Y%m%d).sql.gz

# Wiederherstellung
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform
```

---

## 4. Manuelle Bereitstellung

### 4.1 PHP-Umgebungskonfiguration

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# OPcache aktivieren (in Produktion erforderlich)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 4.2 Abhängigkeiten installieren

```bash
cd /opt/game-platform

# Verwaltungsbackend
cd admin
cp .env.example .env
# .env bearbeiten: Datenbankverbindung, JWT_SECRET, HASHIDS_SALT usw.
composer install --no-dev --optimize-autoloader

# C-End-Geschäft
cd ../service
cp .env.example .env
# .env bearbeiten (Hinweis: SNOWFLAKE_WORKER_ID=2)
composer install --no-dev --optimize-autoloader
```

### 4.3 .env konfigurieren

**Wichtige Konfiguration für admin/.env:**
```ini
APP_ENV=production
APP_DEBUG=false
APP_PORT=8789  # webman HTTP-Listen-Port (muss mit APP_URL übereinstimmen)
APP_URL=http://localhost:8789  # externe Zugriffsadresse (u. a. baseUrl der API-Dokumentation)

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

**Wichtige Konfiguration für service/.env:**
```ini
# 与 admin 相同的数据库、Redis、ES 配置
APP_PORT=8792  # webman HTTP-Listen-Port
LEADERBOARD_WS_PORT=8790  # WebSocket-Rangliste (muss mit der vom Frontend verwendeten Adresse übereinstimmen)
CHAT_WS_PORT=8791  # Chat-WebSocket
SNOWFLAKE_WORKER_ID=2  # 必须与 admin 不同

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<从Google Cloud Console获取>
OAUTH_GOOGLE_CLIENT_SECRET=<密钥>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# 支付 Webhook
STRIPE_SECRET_KEY=<Stripe 密钥>
STRIPE_WEBHOOK_SECRET=<从Stripe Dashboard获取>
PAYPAL_WEBHOOK_ID=<从PayPal Developer获取>
PAYPAL_VERIFY_URL=<PayPal Webhook 验签地址>
PAYPAL_CLIENT_ID=<PayPal Client ID>
PAYPAL_CLIENT_SECRET=<PayPal Client Secret>
PAYPAL_MODE=sandbox  # sandbox / live
NOWPAYMENTS_API_KEY=<NOWPayments API 密钥>
NOWPAYMENTS_IPN_SECRET=<IPN 签名密钥>
NOWPAYMENTS_API_URL=https://api.nowpayments.io  # 默认地址
COINBASE_COMMERCE_API_KEY=<Coinbase Commerce API 密钥>
COINBASE_COMMERCE_WEBHOOK_SECRET=<Coinbase Commerce Webhook 密钥>
SKRILL_API_URL=https://pay.skrill.com
SKRILL_API_KEY=<Skrill API 密钥>
SKRILL_MERCHANT_ID=<Skrill 商户号>
SKRILL_SECRET_WORD=<回调验签密钥 md5sig>
NETELLER_API_URL=https://api.neteller.com
NETELLER_CLIENT_ID=<Neteller Client ID>
NETELLER_CLIENT_SECRET=<Neteller Client Secret>
NETELLER_SECRET=<回调验签密钥>
PAYSAFECARD_API_URL=https://api.paysafecard.com
PAYSAFECARD_API_KEY=<Paysafecard API 密钥>
PAYSAFECARD_SECRET=<回调验签密钥 X-Signature HMAC-SHA256>
PAYTM_MID=<Paytm 商户号>
PAYTM_KEY=<Paytm 密钥>
PAYTM_API_URL=https://securegw.paytm.in
PAYTM_WEBSITE=DEFAULT  # 正式 DEFAULT / staging WEBSTAGING
MERCADOPAGO_CLIENT_ID=<Mercado Pago Client ID>
MERCADOPAGO_CLIENT_SECRET=<Mercado Pago Client Secret>
MERCADOPAGO_WEBHOOK_SECRET=<Webhook 验签密钥 X-Signature>
MERCADOPAGO_API_URL=https://api.mercadopago.com
ASTROPAY_LOGIN=<AstroPay 登录名>
ASTROPAY_API_KEY=<AstroPay API 密钥>
ASTROPAY_SECRET=<回调验签密钥 MD5>
ASTROPAY_API_URL=https://api.astropaycard.com
PAYPAY_CLIENT_ID=<PayPay Client ID>
PAYPAY_CLIENT_SECRET=<PayPay Client Secret>
PAYPAY_SIGNING_KEY=<Webhook 验签密钥 PayPay-Signature>
PAYPAY_API_URL=https://api.paypay.ne.jp
KAKAOPAY_ADMIN_KEY=<KakaoPay Admin Key>
KAKAOPAY_CID=<KakaoPay 商户 CID>
KAKAOPAY_APPROVAL_URL=<付款后审批跳转 URL>
KAKAOPAY_API_URL=https://kapi.kakao.com
PAYMONGO_API_KEY=<PayMongo API 密钥>
PAYMONGO_WEBHOOK_SECRET=<Webhook 验签密钥 Paymongo-Signature>
PAYMONGO_API_URL=https://api.paymongo.com/v1
# M-Pesa / Paystack / Toss
MPESA_CONSUMER_KEY=<M-Pesa Consumer Key>
MPESA_CONSUMER_SECRET=<M-Pesa Consumer Secret>
MPESA_PASSKEY=<M-Pesa STK Push Passkey>
MPESA_SHORTCODE=<M-Pesa 短码>
MPESA_API_URL=https://api.safaricom.co.ke
PAYSTACK_SECRET_KEY=<Paystack Secret Key>
PAYSTACK_API_URL=https://api.paystack.co
TOSS_SECRET_KEY=<Toss Secret Key>
TOSS_API_URL=https://api.tosspayments.com
SITE_URL=https://your-domain.com  # 支付回调/跳转站点地址
```

### 4.4 Dienste starten

```bash
# Verwaltungsbackend (Standardport 8789, änderbar über APP_PORT in admin/.env)
cd /opt/game-platform/admin
php start.php start -d

# C-End-Geschäft (Standardport 8792, änderbar über APP_PORT in service/.env)
cd /opt/game-platform/service
php start.php start -d

# Verifizieren
curl http://localhost:8789/health
curl http://localhost:8792/health
```

### 4.5 Prozessverwaltung (Systemd)

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

## 5. Nginx-Reverse-Proxy

### 5.1 Konfigurationsdatei

`/etc/nginx/sites-available/game-platform` erstellen:

```nginx
# Ports sind Standardwerte (admin 8789 / service 8792 / ws 8790); bei geänderter .env bitte entsprechend anpassen
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

    # WebSocket-Rangliste (Standardport 8790, entspricht LEADERBOARD_WS_PORT in service/.env)
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

> Beim manuellen Deployment legen Sie die Build-Artefakte selbst in diese Verzeichnisse (vier C-End-Bäume: `apps/flutter/platform`, `apps/react`, `apps/angular`, `apps/harmonyos`; alle Konsolen-Frontends liegen unter `admin/apps/*` sowie im generischen Ablageplatz `admin/public`).
> Beim Docker-Deployment siehe die nginx-Volume-Mounts in `docker-compose.yml` und `nginx.conf.template` (dieselben Pfade, Container-Wurzel `/var/www/...`). HarmonyOS wird als `.hap` verteilt und läuft nicht über nginx.

Site aktivieren:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 5.2 SSL-Zertifikate

```bash
# Mit Certbot automatisch ein Let's-Encrypt-Zertifikat beziehen
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# Automatische Verlängerung (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 6. Geplante Aufgaben (Crontab)

```bash
# crontab bearbeiten
crontab -e

# Täglicher Statistik-Snapshot (täglich 1:00 Uhr)
0 1 * * * cd /opt/game-platform/admin && php start.php queue ComputeDailyStats

# Datenbank-Backup (täglich 2:00 Uhr)
0 2 * * * cd /opt/game-platform/admin/database/backup && bash backup.sh

# Automatische Verlängerung des SSL-Zertifikats
0 3 * * * certbot renew --quiet && systemctl reload nginx

# Aktualisierung des Ranglisten-Caches (stündlich)
0 * * * * cd /opt/game-platform/admin && php start.php queue RefreshLeaderboards
```

---

## 7. Überwachung

### 7.1 Prometheus-Metriken

Das Verwaltungsbackend stellt den `/metrics`-Endpunkt mit folgenden Metriken bereit:

| Metrik | Beschreibung |
|------|------|
| openadmin_http_requests_total | Gesamtzahl der Anfragen |
| openadmin_active_users | Anzahl aktiver Benutzer |
| openadmin_db_connection_status | Datenbankverbindung (0/1) |
| openadmin_redis_connection_status | Redis-Verbindung (0/1) |
| openadmin_memory_usage_bytes | Speichernutzung |

### 7.2 Gesundheitschecks

```bash
# Verwaltungsbackend
curl -f http://localhost:8789/health || echo "Admin DOWN"

# C-End-Geschäft
curl -f http://localhost:8792/health || echo "Service DOWN"

# Kann im Load Balancer oder Monitoring-System konfiguriert werden
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

## 8. Leistungsoptimierung

### 8.1 PHP-OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # 生产环境关闭文件检查
```

### 8.2 MySQL-Optimierung

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # 设为物理内存的 50-70%
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # 性能优先
max_connections = 200
query_cache_type = 0               # MySQL 8.0 已移除
```

### 8.3 Anzahl der Worker-Prozesse

```php
// config/process.php
'count' => cpu_count() * 2,  // 生产环境建议 2-4 倍 CPU 核心数
```

### 8.4 Redis-Cache-Strategie

| Cache-Schlüssel | TTL | Beschreibung |
|--------|-----|------|
| dashboard:data | 300s | Dashboard-Daten |
| i18n:translations | 3600s | Übersetzungstexte |
| leaderboard:{id} | 3600s | Rangliste |
| rate_limit:{ip}:{route} | 60s | Rate-Limit-Fenster |

---

## 9. Sicherheitshärtung

### 9.1 Schlüsselgenerierung

```bash
# Zufällige Schlüssel generieren
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
# Nur die notwendigen Ports öffnen
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# Interne Ports sollten nicht exponiert werden
# 8789 (admin), 8792 (service), 8790/8791 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# Oben stehen die Standardports; wurden die .env im Projektstamm bzw. die jeweiligen .env geändert, gelten die tatsächlichen Werte
# Nur über 127.0.0.1 erreichbar
```

### 9.3 Dateiberechtigungen

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 10. Fehlerbehebung

### 10.1 Dienst startet nicht

```bash
# Im Vordergrund ausführen, um Fehler zu sehen
cd /opt/game-platform/admin && php start.php start

# Portbelegung prüfen
ss -tlnp | grep -E '8789|8792'

# Logs prüfen
tail -f runtime/logs/webman-$(date +%F).log
```

### 10.2 Datenbankverbindung fehlgeschlagen

```bash
# Verbindung testen
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# .env-Konfiguration prüfen
grep DB_ admin/.env
```

### 10.3 Redis-Verbindung fehlgeschlagen

```bash
# Verbindung testen
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# PONG wird erwartet
```

### 10.4 Elasticsearch nicht verfügbar

```bash
# Verbindung testen
curl http://127.0.0.1:9200

# Die Suche fällt automatisch auf LIKE-Abfragen zurück, der Dienst wird nicht unterbrochen
```

### 10.5 Leistungsprobleme

```bash
# Anzahl der Worker-Prozesse prüfen
php start.php status

# Speicherverbrauch ansehen
free -h

# Langsame Datenbankabfragen prüfen
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 11. Upgrade-Anleitung

```bash
# 1. Neuesten Code ziehen
cd /opt/game-platform && git pull origin main

# 2. Abhängigkeiten aktualisieren
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. Neue Migrationen ausführen (falls vorhanden)
mysql -u game-platform -p game-platform < install/新迁移文件.sql

# 4. Sanfter Neustart (ohne Dienstunterbrechung)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
