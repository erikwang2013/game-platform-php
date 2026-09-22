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

# 3. افتح http://<server-IP>:8888 في المتصفح
#    أكمل عبر المعالج: فحص البيئة → إعداد قاعدة البيانات → حساب المشرف → التثبيت التلقائي

# 4. تثبيت التبعيات
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. تشغيل الخدمات (المنفذ الافتراضي admin 8789 / service 8792، يمكن التعديل عبر APP_PORT في ملف .env الخاص بكل منهما)
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. التنظيف الأمني
rm -rf install/

# 7. الوصول إلى لوحة الإدارة: http://<server-IP>:8789 (المنفذ الافتراضي)
```

العمليات التي يكملها معالج التثبيت:
- فحص بيئة PHP (الإصدار والإضافات وأذونات الدلائل)
- تنفيذ SQL المدمج (`install/install.sql`)، إنشاء 78 جدولًا واستيراد بيانات البذور
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
#    معاملات Docker مثل المنافذ موجودة في ملف .env بالدليل الجذر (القالب .env.example): cp .env.example .env
php -S 0.0.0.0:8888 -t install/
# الطريقة اليدوية: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. بناء وتشغيل جميع الخدمات
docker-compose up -d

# 4. عرض الحالة
docker-compose ps

# 5. عرض السجلات
docker-compose logs -f
```

### 3.2 قائمة الخدمات

| الخدمة | اسم الحاوية | المنفذ | الوصف |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | وكيل عكسي + ملفات ثابتة |
| admin | game-platform-admin | 8789 | واجهات لوحة الإدارة |
| service | game-platform-service | 8792 | واجهات أعمال الطرف C |
| leaderboard-ws | game-platform-ws | 8790, 8791 | WebSocket لوحة المتصدرين/الدردشة |
| mysql | game-platform-mysql | 3306 | قاعدة البيانات الرئيسية |
| redis | game-platform-redis | 6379 | تخزين مؤقت/تقييد |
| elasticsearch | game-platform-es | 9200 | بحث نصي كامل |

> **إعداد المنافذ**: الجدول أعلاه يعرض المنافذ الافتراضية، ويمكن تعديلها جميعًا في ملف `.env` بالدليل الجذر للمشروع (القالب `.env.example`، بعد `cp .env.example .env` قم بالتحرير):
> `NGINX_HTTP_PORT`، `NGINX_HTTPS_PORT`، `ADMIN_PORT`، `SERVICE_PORT`، `LEADERBOARD_WS_PORT`، `CHAT_WS_PORT`، `MYSQL_PORT`، `REDIS_PORT`، `ES_PORT`.
> منافذ upstream في `nginx.conf.template` يُرندرها envsubst في الصورة الرسمية تلقائيًا، دون حاجة لتعديل إعداد Nginx يدويًا.
> في نشر Docker، تتبع العناوين العامة (`APP_URL` / `SITE_URL`) افتراضيًا `ADMIN_PORT` / `SERVICE_PORT` تلقائيًا (بصيغة `http://localhost:المنفذ`)؛ لاستخدام نطاق مخصص أو HTTPS، عيّن `APP_URL` / `SITE_URL` في ملف `.env` الجذري (يستبدل المفتاحين نفسهما في `admin/.env` و`service/.env`). في النشر اليدوي (bare-metal)، لا يزال يتعين تحديث العناوين بنفسك عند تغيير المنافذ.

### 3.3 تهيئة قاعدة البيانات

```bash
# تُنفَّذ ملفات الترحيل تلقائيًا عند أول إقلاع لـ MySQL
# أو تنفيذها يدويًا:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 3.4 استمرارية البيانات

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

## 4. النشر اليدوي

### 4.1 إعداد بيئة PHP

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# تفعيل OPcache (إلزامي في الإنتاج)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 4.2 تثبيت التبعيات

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

### 4.3 إعداد .env

**الإعدادات الرئيسية لـ admin/.env:**
```ini
APP_ENV=production
APP_DEBUG=false
APP_PORT=8789  # منفذ استماع webman HTTP (متوافق مع APP_URL)
APP_URL=http://localhost:8789  # عنوان الوصول الخارجي (baseUrl لتوثيق API وغيره)

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
APP_PORT=8792
LEADERBOARD_WS_PORT=8790  # WebSocket لوحة المتصدرين
CHAT_WS_PORT=8791  # WebSocket الدردشة
SNOWFLAKE_WORKER_ID=2  # يجب أن يختلف عن admin

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<从Google Cloud Console获取>
OAUTH_GOOGLE_CLIENT_SECRET=<密钥>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# Webhook الدفع (toss / mpesa / paystack قيد الإضافة)
STRIPE_SECRET_KEY=<مفتاح Stripe>
STRIPE_WEBHOOK_SECRET=<من Stripe Dashboard>
PAYPAL_WEBHOOK_ID=<من PayPal Developer>
PAYPAL_VERIFY_URL=<عنوان التحقق من Webhook PayPal>
PAYPAL_CLIENT_ID=<معرف عميل PayPal>
PAYPAL_CLIENT_SECRET=<سر عميل PayPal>
PAYPAL_MODE=sandbox  # sandbox / live
NOWPAYMENTS_API_KEY=<مفتاح NOWPayments API>
NOWPAYMENTS_IPN_SECRET=<مفتاح توقيع IPN>
NOWPAYMENTS_API_URL=https://api.nowpayments.io  # العنوان الافتراضي
COINBASE_COMMERCE_API_KEY=<مفتاح Coinbase Commerce API>
COINBASE_COMMERCE_WEBHOOK_SECRET=<مفتاح Webhook Coinbase Commerce>
SKRILL_API_URL=https://pay.skrill.com
SKRILL_API_KEY=<مفتاح Skrill API>
SKRILL_MERCHANT_ID=<معرف تاجر Skrill>
SKRILL_SECRET_WORD=<مفتاح التحقق من التوقيع md5sig>
NETELLER_API_URL=https://api.neteller.com
NETELLER_CLIENT_ID=<معرف عميل Neteller>
NETELLER_CLIENT_SECRET=<سر عميل Neteller>
NETELLER_SECRET=<مفتاح التحقق من التوقيع>
PAYSAFECARD_API_URL=https://api.paysafecard.com
PAYSAFECARD_API_KEY=<مفتاح Paysafecard API>
PAYSAFECARD_SECRET=<مفتاح التحقق من التوقيع X-Signature HMAC-SHA256>
PAYTM_MID=<معرف تاجر Paytm>
PAYTM_KEY=<مفتاح Paytm>
PAYTM_API_URL=https://securegw.paytm.in
PAYTM_WEBSITE=DEFAULT  # DEFAULT للإنتاج / WEBSTAGING للتجربة
MERCADOPAGO_CLIENT_ID=<معرف عميل Mercado Pago>
MERCADOPAGO_CLIENT_SECRET=<سر عميل Mercado Pago>
MERCADOPAGO_WEBHOOK_SECRET=<مفتاح التحقق من Webhook X-Signature>
MERCADOPAGO_API_URL=https://api.mercadopago.com
ASTROPAY_LOGIN=<اسم دخول AstroPay>
ASTROPAY_API_KEY=<مفتاح AstroPay API>
ASTROPAY_SECRET=<مفتاح التحقق من التوقيع MD5>
ASTROPAY_API_URL=https://api.astropaycard.com
PAYPAY_CLIENT_ID=<معرف عميل PayPay>
PAYPAY_CLIENT_SECRET=<سر عميل PayPay>
PAYPAY_SIGNING_KEY=<مفتاح التحقق من Webhook PayPay-Signature>
PAYPAY_API_URL=https://api.paypay.ne.jp
KAKAOPAY_ADMIN_KEY=<مفتاح إدارة KakaoPay>
KAKAOPAY_CID=<معرف تاجر KakaoPay>
KAKAOPAY_APPROVAL_URL=<عنوان القفز بعد الدفع>
KAKAOPAY_API_URL=https://kapi.kakao.com
PAYMONGO_API_KEY=<مفتاح PayMongo API>
PAYMONGO_WEBHOOK_SECRET=<مفتاح التحقق من Webhook Paymongo-Signature>
PAYMONGO_API_URL=https://api.paymongo.com/v1
# قيد الإضافة
MPESA_CONSUMER_KEY=<مفتاح مستهلك M-Pesa>
MPESA_CONSUMER_SECRET=<سر مستهلك M-Pesa>
MPESA_PASSKEY=<مفتاح مرور STK Push M-Pesa>
MPESA_SHORTCODE=<الرمز القصير M-Pesa>
MPESA_API_URL=https://api.safaricom.co.ke
PAYSTACK_SECRET_KEY=<مفتاح Paystack السري>
PAYSTACK_API_URL=https://api.paystack.co
TOSS_SECRET_KEY=<مفتاح Toss السري>
TOSS_API_URL=https://api.tosspayments.com
SITE_URL=https://your-domain.com  # عنوان الموقع للاستدعاء/إعادة التوجيه
```

### 4.4 تشغيل الخدمات

```bash
# لوحة الإدارة (المنفذ الافتراضي 8789، يمكن تغييره عبر APP_PORT في admin/.env)
cd /opt/game-platform/admin
php start.php start -d

# أعمال الطرف C (المنفذ الافتراضي 8792، يمكن تغييره عبر APP_PORT في service/.env)
cd /opt/game-platform/service
php start.php start -d

# التحقق
curl http://localhost:8789/health
curl http://localhost:8792/health
```

### 4.5 إدارة العمليات (Systemd)

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

## 5. وكيل Nginx العكسي

### 5.1 ملف الإعداد

أنشئ `/etc/nginx/sites-available/game-platform`:

```nginx
# المنافذ هي القيم الافتراضية (admin 8789 / service 8792 / ws 8790). إذا عدّلت .env فاضبطها بما يتوافق
server {
    listen 80;
    server_name your-domain.com;

    # nginx 自身发出的 301（如目录补斜杠 /admin-panel → /admin-panel/）改用相对
    # Location，客户端按当前 host:port 解析；默认绝对跳转会退回 listen 端口，
    # 非 80 端口部署（如 8080）时会跳错端口。
    absolute_redirect off;

    # واجهات لوحة الإدارة
    location /admin/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # واجهات الطرف C
    location /api/ {
        proxy_pass http://127.0.0.1:8792;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket لوحة المتصدرين (المنفذ الافتراضي 8790، متوافق مع LEADERBOARD_WS_PORT في service/.env)
    location /ws/ {
        proxy_pass http://127.0.0.1:8790;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # فحص الصحة
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

> في النشر اليدوي تضع أنت ملفات البناء في هذه المجلدات (أشجار الطرف C الأربع: `apps/flutter/platform` و`apps/react` و`apps/angular` و`apps/harmonyos`؛ وتُركَّب كل واجهات لوحة الإدارة تحت `admin/apps/*` إضافةً إلى الموضع العام `admin/public`).
> لنشر Docker راجع ربط أحجام nginx في `docker-compose.yml` و`nginx.conf.template` (المسارات نفسها، وجذر الحاوية `/var/www/...`). يُوزَّع HarmonyOS كملف `.hap` ولا يمر عبر nginx.

تفعيل الموقع:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 5.2 شهادات SSL

```bash
# الحصول تلقائيًا على شهادة Let's Encrypt عبر Certbot
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# التجديد التلقائي (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 6. المهام المجدولة (Crontab)

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

## 7. المراقبة

### 7.1 مؤشرات Prometheus

تعرض لوحة الإدارة نقطة النهاية `/metrics`، وتشمل المؤشرات التالية:

| المؤشر | الوصف |
|------|------|
| openadmin_http_requests_total | إجمالي عدد الطلبات |
| openadmin_active_users | عدد المستخدمين النشطين |
| openadmin_db_connection_status | اتصال قاعدة البيانات (0/1) |
| openadmin_redis_connection_status | اتصال Redis (0/1) |
| openadmin_memory_usage_bytes | حجم استخدام الذاكرة |

### 7.2 فحص الصحة

```bash
# لوحة الإدارة
curl -f http://localhost:8789/health || echo "Admin DOWN"

# أعمال الطرف C
curl -f http://localhost:8792/health || echo "Service DOWN"

# يمكن إعداده في موازن الحمل أو نظام المراقبة
```

### 7.3 السجلات

```
admin/runtime/logs/
├── stdout.log          # الإخراج القياسي
└── webman-<date>.log   # سجلات Webman

service/runtime/logs/
├── stdout.log
└── webman-<date>.log
```

---

## 8. تحسين الأداء

### 8.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # إيقاف فحص الملفات في بيئة الإنتاج
```

### 8.2 تحسين MySQL

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # اضبط على 50-70% من الذاكرة الفعلية
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # الأولوية للأداء
max_connections = 200
query_cache_type = 0               # أُزيل في MySQL 8.0
```

### 8.3 عدد عمليات Worker

```php
// config/process.php
'count' => cpu_count() * 2,  // يُنصح بـ 2-4 أضعاف عدد أنوية CPU في الإنتاج
```

### 8.4 استراتيجية التخزين المؤقت في Redis

| مفتاح التخزين المؤقت | TTL | الوصف |
|--------|-----|------|
| dashboard:data | 300s | بيانات لوحة التحكم |
| i18n:translations | 3600s | نصوص الترجمة |
| leaderboard:{id} | 3600s | لوحات المتصدرين |
| rate_limit:{ip}:{route} | 60s | نافذة حد المعدل |

---

## 9. تقوية الأمان

### 9.1 توليد المفاتيح

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

### 9.2 جدار الحماية

```bash
# افتح المنافذ الضرورية فقط
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# لا ينبغي كشف المنافذ الداخلية
# 8789 (admin), 8792 (service), 8790/8791 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# ما سبق منافذ افتراضية. إذا عدّلت .env الجذر أو ملفات .env الخاصة، فاعتمد على الإعداد الفعلي
# تُوصَل عبر 127.0.0.1 فقط
```

### 9.3 أذونات الملفات

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 10. استكشاف الأخطاء وإصلاحها

### 10.1 تعذّر تشغيل الخدمة

```bash
# التشغيل في المقدمة لعرض الخطأ
cd /opt/game-platform/admin && php start.php start

# فحص احتلال المنفذ
ss -tlnp | grep -E '8789|8792'

# فحص السجلات
tail -f runtime/logs/webman-$(date +%F).log
```

### 10.2 فشل اتصال قاعدة البيانات

```bash
# اختبار الاتصال
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# فحص إعداد .env
grep DB_ admin/.env
```

### 10.3 فشل اتصال Redis

```bash
# اختبار الاتصال
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# المتوقع إرجاع PONG
```

### 10.4 Elasticsearch غير متاح

```bash
# اختبار الاتصال
curl http://127.0.0.1:9200

# تعود وظيفة البحث تلقائيًا إلى استعلام LIKE، دون انقطاع الخدمة
```

### 10.5 مشاكل الأداء

```bash
# فحص عدد عمليات worker
php start.php status

# عرض استخدام الذاكرة
free -h

# فحص الاستعلامات البطيئة في قاعدة البيانات
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 11. دليل الترقية

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
