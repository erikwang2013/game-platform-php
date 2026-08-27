# परिनियोजन दस्तावेज़
<!-- lang-nav -->

Languages: [中文](DEPLOYMENT.md) · [English](DEPLOYMENT.en.md) · [한국어](DEPLOYMENT.ko.md) · [Русский](DEPLOYMENT.ru.md) · [Deutsch](DEPLOYMENT.de.md) · [Français](DEPLOYMENT.fr.md) · [Español](DEPLOYMENT.es.md) · [Português](DEPLOYMENT.pt.md) · **हिन्दी** · [العربية](DEPLOYMENT.ar.md) · [বাংলা](DEPLOYMENT.bn.md) · [Bahasa Indonesia](DEPLOYMENT.id.md) · [日本語](DEPLOYMENT.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. पर्यावरण आवश्यकताएँ

| घटक | न्यूनतम संस्करण | अनुशंसित कॉन्फ़िग |
|------|---------|---------|
| OS | Linux (Ubuntu 20.04+ / Debian 11+ / CentOS 8+) | Ubuntu 22.04 LTS |
| PHP | 8.3+ | 8.3+ (CLI, OPcache सक्षम) |
| PHP एक्सटेंशन | pdo, pdo_mysql, pcntl, redis, gd, mbstring, xml | सभी |
| MySQL | 8.0+ | 8.0+ मुख्य-दास प्रतिकृति |
| Redis | 6.0+ | 7.x सेंटिनल मोड |
| Elasticsearch | 7.x+ | 8.x एकल नोड |
| Nginx | 1.20+ | रिवर्स प्रॉक्सी + gzip + SSL |
| Composer | 2.x | नवीनतम स्थिर संस्करण |
| Flutter SDK | 3.x+ | नवीनतम स्थिर संस्करण (केवल फ्रंटएंड निर्माण के लिए) |

---

## 2. एक-क्लिक स्थापना विज़ार्ड (नई तैनाती के लिए अनुशंसित)

```bash
# 1. प्रोजेक्ट क्लोन करें
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. स्थापना विज़ार्ड शुरू करें
php -S 0.0.0.0:8888 -t install/

# 3. ब्राउज़र में http://<सर्वरIP>:8888 खोलें
#    विज़ार्ड के अनुसार पूरा करें: पर्यावरण जाँच → डेटाबेस कॉन्फ़िग → प्रशासक खाता → स्वचालित स्थापना

# 4. निर्भरताएँ स्थापित करें
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. सेवाएँ शुरू करें
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. सुरक्षा सफाई
rm -rf install/

# 7. प्रशासन कंसोल तक पहुँचें: http://<सर्वरIP>:8787
```

स्थापना विज़ार्ड द्वारा पूर्ण किए गए कार्य:
- PHP पर्यावरण जाँच (संस्करण, एक्सटेंशन, निर्देशिका अनुमतियाँ)
- संयुक्त SQL (`install/install.sql`) निष्पादित करें, 52 तालिकाएँ बनाएं और सीड डेटा आयात करें
- सुपर एडमिन खाता बनाएं (bcrypt एन्क्रिप्टेड, super_admin भूमिका से संबद्ध)
- JWT/Encryption/Hashids कुंजियाँ स्वचालित रूप से उत्पन्न करें
- `admin/.env` और `service/.env` लिखें
- दोहराई स्थापना रोकने के लिए `install/install.lock` बनाएं

---

## 3. Docker Compose परिनियोजन

### 3.1 एक-क्लिक प्रारंभ

```bash
# 1. प्रोजेक्ट क्लोन करें
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. एक-क्लिक स्थापना विज़ार्ड से पर्यावरण कॉन्फ़िगर करें (या .env फ़ाइलें मैन्युअल कॉन्फ़िगर करें)
php -S 0.0.0.0:8888 -t install/
# मैन्युअल विधि: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. सभी सेवाएँ बनाएं और शुरू करें
docker-compose up -d

# 4. स्थिति देखें
docker-compose ps

# 5. लॉग देखें
docker-compose logs -f
```

### 2.2 सेवा सूची

| सेवा | कंटेनर नाम | पोर्ट | विवरण |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | रिवर्स प्रॉक्सी + स्थिर फ़ाइलें |
| admin | game-platform-admin | 8787 | प्रशासन कंसोल API |
| service | game-platform-service | 8788 | C-छोर व्यवसाय API |
| leaderboard-ws | game-platform-ws | 8789 | WebSocket लीडरबोर्ड |
| mysql | game-platform-mysql | 3306 | मुख्य डेटाबेस |
| redis | game-platform-redis | 6379 | कैश/दर सीमा |
| elasticsearch | game-platform-es | 9200 | पूर्ण-पाठ खोज |

### 2.3 डेटाबेस आरंभीकरण

```bash
# माइग्रेशन फ़ाइलें MySQL के पहले प्रारंभ पर स्वचालित रूप से निष्पादित होती हैं
# या मैन्युअल निष्पादन:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 2.4 डेटा स्थायीकरण

डेटा वॉल्यूम स्वचालित रूप से बनते हैं, मैन्युअल प्रबंधन की आवश्यकता नहीं:

| वॉल्यूम | पथ | सामग्री |
|----|------|------|
| mysql_data | /var/lib/mysql | डेटाबेस फ़ाइलें |
| redis_data | /data | Redis स्थायीकरण |
| es_data | /usr/share/elasticsearch/data | ES इंडेक्स |

बैकअप:
```bash
# MySQL बैकअप
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game-platform | gzip > backup_$(date +%Y%m%d).sql.gz

# पुनर्स्थापना
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform
```

---

## 3. मैन्युअल परिनियोजन

### 3.1 PHP पर्यावरण कॉन्फ़िगरेशन

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# OPcache सक्षम करें (उत्पादन में अनिवार्य)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 3.2 निर्भरताएँ स्थापित करें

```bash
cd /opt/game-platform

# प्रशासन कंसोल
cd admin
cp .env.example .env
# .env संपादित करें: डेटाबेस कनेक्शन, JWT_SECRET, HASHIDS_SALT आदि
composer install --no-dev --optimize-autoloader

# C-छोर व्यवसाय
cd ../service
cp .env.example .env
# .env संपादित करें (ध्यान दें: SNOWFLAKE_WORKER_ID=2)
composer install --no-dev --optimize-autoloader
```

### 3.3 .env कॉन्फ़िगरेशन

**admin/.env मुख्य कॉन्फ़िग:**
```ini
APP_ENV=production
APP_DEBUG=false

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=game-platform
DB_USERNAME=game-platform
DB_PASSWORD=<मजबूत पासवर्ड>

JWT_SECRET=<64 अक्षर यादृच्छिक स्ट्रिंग>
JWT_TTL=7200

HASHIDS_SALT=<यादृच्छिक नमक मान>
HASHIDS_MIN_LENGTH=16

SNOWFLAKE_DATACENTER_ID=1
SNOWFLAKE_WORKER_ID=1

ENCRYPTION_KEY=<32 बाइट यादृच्छिक कुंजी>
ENCRYPTABLE_KEY=<यादृच्छिक AES कुंजी>

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=<Redis पासवर्ड>

SCOUT_HOSTS=127.0.0.1:9200
```

**service/.env मुख्य कॉन्फ़िग:**
```ini
# admin के समान डेटाबेस, Redis, ES कॉन्फ़िग
SNOWFLAKE_WORKER_ID=2  # admin से भिन्न होना अनिवार्य

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<Google Cloud Console से प्राप्त>
OAUTH_GOOGLE_CLIENT_SECRET=<कुंजी>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# भुगतान Webhook
STRIPE_WEBHOOK_SECRET=<Stripe Dashboard से प्राप्त>
PAYPAL_WEBHOOK_ID=<PayPal Developer से प्राप्त>
```

### 3.4 सेवाएँ शुरू करें

```bash
# प्रशासन कंसोल (पोर्ट 8787)
cd /opt/game-platform/admin
php start.php start -d

# C-छोर व्यवसाय (पोर्ट 8788)
cd /opt/game-platform/service
php start.php start -d

# सत्यापन
curl http://localhost:8787/health
curl http://localhost:8788/health
```

### 3.5 प्रक्रिया प्रबंधन (Systemd)

`/etc/systemd/system/game-platform-admin.service` बनाएं:

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

इसी तरह `game-platform-service.service` बनाएं (WorkingDirectory को `/opt/game-platform/service` में बदलें)।

```bash
systemctl daemon-reload
systemctl enable --now game-platform-admin game-platform-service
```

---

## 4. Nginx रिवर्स प्रॉक्सी

### 4.1 कॉन्फ़िग फ़ाइल

`/etc/nginx/sites-available/game-platform` बनाएं:

```nginx
server {
    listen 80;
    server_name your-domain.com;

    # प्रशासन कंसोल API
    location /admin/ {
        proxy_pass http://127.0.0.1:8787;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # C-छोर API
    location /api/ {
        proxy_pass http://127.0.0.1:8788;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket लीडरबोर्ड
    location /ws/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # स्वास्थ्य जाँच
    location /health {
        proxy_pass http://127.0.0.1:8787;
    }

    # Prometheus मीट्रिक्स
    location /metrics {
        proxy_pass http://127.0.0.1:8787;
    }

    # प्रशासन कंसोल फ्रंटएंड
    location /admin-panel {
        alias /opt/game-platform/admin/apps/flutter/build/web;
        try_files $uri $uri/ /admin-panel/index.html;
    }

    # C-छोर प्लेटफ़ॉर्म फ्रंटएंड
    location / {
        root /opt/game-platform/apps/flutter/platform/build/web;
        try_files $uri $uri/ /index.html;
    }
}
```

साइट सक्षम करें:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 4.2 SSL प्रमाणपत्र

```bash
# Certbot से Let's Encrypt प्रमाणपत्र स्वचालित रूप से प्राप्त करें
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# स्वचालिक नवीनीकरण (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 5. निर्धारित कार्य (Crontab)

```bash
# crontab संपादित करें
crontab -e

# दैनिक सांख्यिकी स्नैपशॉट (प्रतिदिन सुबह 1:00)
0 1 * * * cd /opt/game-platform/admin && php start.php queue ComputeDailyStats

# डेटाबेस बैकअप (प्रतिदिन सुबह 2:00)
0 2 * * * cd /opt/game-platform/admin/database/backup && bash backup.sh

# SSL प्रमाणपत्र स्वचालिक नवीनीकरण
0 3 * * * certbot renew --quiet && systemctl reload nginx

# लीडरबोर्ड कैश रिफ्रेश (प्रति घंटा)
0 * * * * cd /opt/game-platform/admin && php start.php queue RefreshLeaderboards
```

---

## 6. निगरानी

### 6.1 Prometheus मीट्रिक्स

प्रशासन कंसोल `/metrics` एंडपॉइंट उजागर करता है, निम्न मीट्रिक्स शामिल:

| मीट्रिक | विवरण |
|------|------|
| openadmin_http_requests_total | अनुरोधों की कुल संख्या |
| openadmin_active_users | सक्रिय उपयोगकर्ताओं की संख्या |
| openadmin_db_connection_status | डेटाबेस कनेक्शन (0/1) |
| openadmin_redis_connection_status | Redis कनेक्शन (0/1) |
| openadmin_memory_usage_bytes | मेमोरी उपयोग |

### 6.2 स्वास्थ्य जाँच

```bash
# प्रशासन कंसोल
curl -f http://localhost:8787/health || echo "Admin DOWN"

# C-छोर व्यवसाय
curl -f http://localhost:8788/health || echo "Service DOWN"

# लोड बैलेंसर या निगरानी प्रणाली में कॉन्फ़िगर किया जा सकता है
```

### 6.3 लॉग

```
admin/runtime/logs/
├── stdout.log          # मानक आउटपुट
└── workerman.log       # Workerman लॉग

service/runtime/logs/
├── stdout.log
└── workerman.log
```

---

## 7. प्रदर्शन अनुकूलन

### 7.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # उत्पादन में फ़ाइल जाँच बंद करें
```

### 7.2 MySQL अनुकूलन

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # भौतिक मेमोरी का 50-70% निर्धारित करें
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # प्रदर्शन प्राथमिकता
max_connections = 200
query_cache_type = 0               # MySQL 8.0 में हटा दिया गया
```

### 7.3 Worker प्रक्रिया संख्या

```php
// config/process.php
'count' => cpu_count() * 2,  // उत्पादन में CPU कोर की 2-4 गुना अनुशंसित
```

### 7.4 Redis कैश रणनीति

| कैश कुंजी | TTL | विवरण |
|--------|-----|------|
| dashboard:data | 300s | डैशबोर्ड डेटा |
| i18n:translations | 3600s | अनुवाद पाठ |
| leaderboard:{id} | 3600s | लीडरबोर्ड |
| rate_limit:{ip}:{route} | 60s | दर सीमा विंडो |

---

## 8. सुरक्षा सुदृढ़ीकरण

### 8.1 कुंजी उत्पादन

```bash
# यादृच्छिक कुंजियाँ उत्पन्न करें
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

### 8.2 फ़ायरवॉल

```bash
# केवल आवश्यक पोर्ट खोलें
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# आंतरिक पोर्ट उजागर नहीं होने चाहिए
# 8787 (admin), 8788 (service), 8789 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# केवल 127.0.0.1 के माध्यम से पहुँच
```

### 8.3 फ़ाइल अनुमतियाँ

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 9. समस्या निवारण

### 9.1 सेवा प्रारंभ नहीं हो सकती

```bash
# त्रुटि देखने के लिए फोरग्राउंड में चलाएं
cd /opt/game-platform/admin && php start.php start

# पोर्ट अधिग्रहण जाँचें
ss -tlnp | grep -E '8787|8788'

# लॉग जाँचें
tail -f runtime/logs/workerman.log
```

### 9.2 डेटाबेस कनेक्शन विफल

```bash
# कनेक्शन परीक्षण
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# .env कॉन्फ़िग जाँचें
grep DB_ admin/.env
```

### 9.3 Redis कनेक्शन विफल

```bash
# कनेक्शन परीक्षण
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# अपेक्षित रिटर्न PONG
```

### 9.4 Elasticsearch अनुपलब्ध

```bash
# कनेक्शन परीक्षण
curl http://127.0.0.1:9200

# खोज फ़ंक्शन स्वचालित रूप से LIKE क्वेरी पर रोलबैक होता है, सेवा बाधित नहीं होती
```

### 9.5 प्रदर्शन समस्याएँ

```bash
# worker प्रक्रिया संख्या जाँचें
php start.php status

# मेमोरी उपयोग देखें
free -h

# डेटाबेस धीमी क्वेरी जाँचें
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 10. उन्नयन मार्गदर्शिका

```bash
# 1. नवीनतम कोड खींचें
cd /opt/game-platform && git pull origin main

# 2. निर्भरताएँ अपडेट करें
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. नए माइग्रेशन निष्पादित करें (यदि कोई हों)
mysql -u game-platform -p game-platform < install/新迁移文件.sql

# 4. सुचारू पुनः प्रारंभ (सेवा बाधित नहीं होती)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
