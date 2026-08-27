# وثيقة النشر
<!-- lang-nav -->

Languages: **中文** · [English](DEPLOYMENT.en.md) · [한국어](DEPLOYMENT.ko.md) · [Русский](DEPLOYMENT.ru.md) · [Deutsch](DEPLOYMENT.de.md) · [Français](DEPLOYMENT.fr.md) · [Español](DEPLOYMENT.es.md) · [Português](DEPLOYMENT.pt.md) · [हिन्दी](DEPLOYMENT.hi.md) · [العربية](DEPLOYMENT.ar.md) · [বাংলা](DEPLOYMENT.bn.md) · [Bahasa Indonesia](DEPLOYMENT.id.md) · [日本語](DEPLOYMENT.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. متطلبات البيئة

| المكوّن | الحد الأدنى للإصدار | الإعداد الموصى به |
|------|---------|---------|
| نظام التشغيل | Linux (Ubuntu 20.04+ / Debian 11+ / CentOS 8+) | Ubuntu 22.04 LTS |
| PHP | 8.3+ | 8.3+ (CLI، تفعيل OPcache) |
| إضافات PHP | pdo, pdo_mysql, pcntl, redis, gd, mbstring, xml | جميعها |
| MySQL | 8.0+ | 8.0+ نسخ متماثل رئيسي-تابع |
| Redis | 6.0+ | 7.x وضع الحارس |
| Elasticsearch | 7.x+ | 8.x عقدة واحدة |
| Nginx | 1.20+ | وكيل عكسي + gzip + SSL |
| Composer | 2.x | أحدث إصدار مستقر |
| Flutter SDK | 3.x+ | أحدث إصدار مستقر (مطلوب فقط عند بناء الواجهة الأمامية) |

---

## 2. معالج التثبيت بنقرة واحدة (موصى به للنشر الجديد)

```bash
# 1. استنساخ المشروع
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. تشغيل معالج التثبيت
php -S 0.0.0.0:8888 -t install/

# 3. افتح http://<服务器IP>:8888 في المتصفح
#    أكمل عبر المعالج: فحص البيئة → إعداد قاعدة البيانات → حساب المشرف → التثبيت التلقائي

# 4. تثبيت التبعيات
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. تشغيل الخدمات
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. التنظيف الأمني
rm -rf install/

# 7. الوصول إلى لوحة الإدارة: http://<服务器IP>:8787
```

العمليات التي يكملها معالج التثبيت:
- فحص بيئة PHP (الإصدار والإضافات وأذونات الدلائل)
- تنفيذ SQL المدمج (`install/install.sql`)، إنشاء 52 جدولًا واستيراد بيانات البذور
- إنشاء حساب المشرف الفائق (تشفير bcrypt، مرتبط بدور super_admin)
- توليد مفاتيح JWT/Encryption/Hashids تلقائيًا
- الكتابة إلى `admin/.env` و`service/.env`
- توليد `install/install.lock` لمنع إعادة التثبيت

---

## 3. النشر عبر Docker Compose

### 3.1 تشغيل بنقرة واحدة

```bash
# 1. استنساخ المشروع
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. استخدام معالج التثبيت لتكوين البيئة (أو تكوين ملف .env يدويًا)
php -S 0.0.0.0:8888 -t install/
# الطريقة اليدوية: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. بناء وتشغيل جميع الخدمات
docker-compose up -d

# 4. عرض الحالة
docker-compose ps

# 5. عرض السجلات
docker-compose logs -f
```

### 2.2 قائمة الخدمات

| الخدمة | اسم الحاوية | المنفذ | الوصف |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | وكيل عكسي + ملفات ثابتة |
| admin | game-platform-admin | 8787 | واجهات لوحة الإدارة |
| service | game-platform-service | 8788 | واجهات أعمال الطرف C |
| leaderboard-ws | game-platform-ws | 8789 | WebSocket لوحة المتصدرين |
| mysql | game-platform-mysql | 3306 | قاعدة البيانات الرئيسية |
| redis | game-platform-redis | 6379 | تخزين مؤقت/تقييد |
| elasticsearch | game-platform-es | 9200 | بحث نصي كامل |

### 2.3 تهيئة قاعدة البيانات

```bash
# تُنفَّذ ملفات الترحيل تلقائيًا عند أول إقلاع لـ MySQL
# أو تنفيذها يدويًا:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 2.4 استمرارية البيانات

تُنشأ وحدات التخزين تلقائيًا، دون حاجة لإدارة يدوية:

| الوحدة | المسار | المحتوى |
|----|------|------|
| mysql_data | /var/lib/mysql | ملفات قاعدة البيانات |
| redis_data | /data | استمرارية Redis |
| es_data | /usr/share/elasticsearch/data | فهارس ES |

النسخ الاحتياطي:
```bash
# نسخ MySQL الاحتياطي
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game-platform | gzip > backup_$(date +%Y%m%d).sql.gz

# الاستعادة
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform
```

---

## 3. النشر اليدوي

### 3.1 إعداد بيئة PHP

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# تفعيل OPcache (إلزامي في الإنتاج)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 3.2 تثبيت التبعيات

```bash
cd /opt/game-platform

# لوحة الإدارة
cd admin
cp .env.example .env
# حرّر .env: اتصال قاعدة البيانات وJWT_SECRET وHASHIDS_SALT وغيرها
composer install --no-dev --optimize-autoloader

# أعمال الطرف C
cd ../service
cp .env.example .env
# حرّر .env (لاحظ: SNOWFLAKE_WORKER_ID=2)
composer install --no-dev --optimize-autoloader
```

### 3.3 إعداد .env

**الإعدادات الرئيسية لـ admin/.env:**
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

**الإعدادات الرئيسية لـ service/.env:**
```ini
# نفس إعدادات قاعدة البيانات وRedis وES الموجودة في admin
SNOWFLAKE_WORKER_ID=2  # يجب أن يختلف عن admin

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<从Google Cloud Console获取>
OAUTH_GOOGLE_CLIENT_SECRET=<密钥>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# Webhook الدفع
STRIPE_WEBHOOK_SECRET=<从Stripe Dashboard获取>
PAYPAL_WEBHOOK_ID=<从PayPal Developer获取>
```

### 3.4 تشغيل الخدمات

```bash
# لوحة الإدارة (المنفذ 8787)
cd /opt/game-platform/admin
php start.php start -d

# أعمال الطرف C (المنفذ 8788)
cd /opt/game-platform/service
php start.php start -d

# التحقق
curl http://localhost:8787/health
curl http://localhost:8788/health
```

### 3.5 إدارة العمليات (Systemd)

أنشئ `/etc/systemd/system/game-platform-admin.service`:

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

أنشئ بالمثل `game-platform-service.service` (عدّل WorkingDirectory إلى `/opt/game-platform/service`).

```bash
systemctl daemon-reload
systemctl enable --now game-platform-admin game-platform-service
```

---

## 4. وكيل Nginx العكسي

### 4.1 ملف الإعداد

أنشئ `/etc/nginx/sites-available/game-platform`:

```nginx
server {
    listen 80;
    server_name your-domain.com;

    # واجهات لوحة الإدارة
    location /admin/ {
        proxy_pass http://127.0.0.1:8787;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # واجهات الطرف C
    location /api/ {
        proxy_pass http://127.0.0.1:8788;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket لوحة المتصدرين
    location /ws/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # فحص الصحة
    location /health {
        proxy_pass http://127.0.0.1:8787;
    }

    # مؤشرات Prometheus
    location /metrics {
        proxy_pass http://127.0.0.1:8787;
    }

    # واجهة لوحة الإدارة الأمامية
    location /admin-panel {
        alias /opt/game-platform/admin/apps/flutter/build/web;
        try_files $uri $uri/ /admin-panel/index.html;
    }

    # واجهة منصة الطرف C الأمامية
    location / {
        root /opt/game-platform/apps/flutter/platform/build/web;
        try_files $uri $uri/ /index.html;
    }
}
```

تفعيل الموقع:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 4.2 شهادات SSL

```bash
# الحصول تلقائيًا على شهادة Let's Encrypt عبر Certbot
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# التجديد التلقائي (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 5. المهام المجدولة (Crontab)

```bash
# حرّر crontab
crontab -e

# لقطة الإحصائيات اليومية (كل يوم الساعة 1:00 صباحًا)
0 1 * * * cd /opt/game-platform/admin && php start.php queue ComputeDailyStats

# النسخ الاحتياطي لقاعدة البيانات (كل يوم الساعة 2:00 صباحًا)
0 2 * * * cd /opt/game-platform/admin/database/backup && bash backup.sh

# التجديد التلقائي لشهادات SSL
0 3 * * * certbot renew --quiet && systemctl reload nginx

# تحديث تخزين لوحات المتصدرين المؤقت (كل ساعة)
0 * * * * cd /opt/game-platform/admin && php start.php queue RefreshLeaderboards
```

---

## 6. المراقبة

### 6.1 مؤشرات Prometheus

تعرض لوحة الإدارة نقطة النهاية `/metrics`، وتشمل المؤشرات التالية:

| المؤشر | الوصف |
|------|------|
| openadmin_http_requests_total | إجمالي عدد الطلبات |
| openadmin_active_users | عدد المستخدمين النشطين |
| openadmin_db_connection_status | اتصال قاعدة البيانات (0/1) |
| openadmin_redis_connection_status | اتصال Redis (0/1) |
| openadmin_memory_usage_bytes | حجم استخدام الذاكرة |

### 6.2 فحص الصحة

```bash
# لوحة الإدارة
curl -f http://localhost:8787/health || echo "Admin DOWN"

# أعمال الطرف C
curl -f http://localhost:8788/health || echo "Service DOWN"

# يمكن إعداده في موازن الحمل أو نظام المراقبة
```

### 6.3 السجلات

```
admin/runtime/logs/
├── stdout.log          # الإخراج القياسي
└── workerman.log       # سجلات Workerman

service/runtime/logs/
├── stdout.log
└── workerman.log
```

---

## 7. تحسين الأداء

### 7.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # إيقاف فحص الملفات في بيئة الإنتاج
```

### 7.2 تحسين MySQL

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # اضبط على 50-70% من الذاكرة الفعلية
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # الأولوية للأداء
max_connections = 200
query_cache_type = 0               # أُزيل في MySQL 8.0
```

### 7.3 عدد عمليات Worker

```php
// config/process.php
'count' => cpu_count() * 2,  // يُنصح بـ 2-4 أضعاف عدد أنوية CPU في الإنتاج
```

### 7.4 استراتيجية التخزين المؤقت في Redis

| مفتاح التخزين المؤقت | TTL | الوصف |
|--------|-----|------|
| dashboard:data | 300s | بيانات لوحة التحكم |
| i18n:translations | 3600s | نصوص الترجمة |
| leaderboard:{id} | 3600s | لوحات المتصدرين |
| rate_limit:{ip}:{route} | 60s | نافذة حد المعدل |

---

## 8. تقوية الأمان

### 8.1 توليد المفاتيح

```bash
# توليد مفاتيح عشوائية
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

### 8.2 جدار الحماية

```bash
# افتح المنافذ الضرورية فقط
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# لا ينبغي كشف المنافذ الداخلية
# 8787 (admin), 8788 (service), 8789 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# تُوصَل عبر 127.0.0.1 فقط
```

### 8.3 أذونات الملفات

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 9. استكشاف الأخطاء وإصلاحها

### 9.1 تعذّر تشغيل الخدمة

```bash
# التشغيل في المقدمة لعرض الخطأ
cd /opt/game-platform/admin && php start.php start

# فحص احتلال المنفذ
ss -tlnp | grep -E '8787|8788'

# فحص السجلات
tail -f runtime/logs/workerman.log
```

### 9.2 فشل اتصال قاعدة البيانات

```bash
# اختبار الاتصال
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# فحص إعداد .env
grep DB_ admin/.env
```

### 9.3 فشل اتصال Redis

```bash
# اختبار الاتصال
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# المتوقع إرجاع PONG
```

### 9.4 Elasticsearch غير متاح

```bash
# اختبار الاتصال
curl http://127.0.0.1:9200

# تعود وظيفة البحث تلقائيًا إلى استعلام LIKE، دون انقطاع الخدمة
```

### 9.5 مشاكل الأداء

```bash
# فحص عدد عمليات worker
php start.php status

# عرض استخدام الذاكرة
free -h

# فحص الاستعلامات البطيئة في قاعدة البيانات
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 10. دليل الترقية

```bash
# 1. سحب أحدث الكود
cd /opt/game-platform && git pull origin main

# 2. تحديث التبعيات
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. تنفيذ الترحيلات الجديدة (إن وجدت)
mysql -u game-platform -p game-platform < install/新迁移文件.sql

# 4. إعادة التشغيل السلس (دون انقطاع الخدمة)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
