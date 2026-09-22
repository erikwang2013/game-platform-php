# ডিপ্লয়মেন্ট ডকুমেন্ট
<!-- lang-nav -->

Languages: [中文](DEPLOYMENT.md) · [English](DEPLOYMENT.en.md) · [한국어](DEPLOYMENT.ko.md) · [Русский](DEPLOYMENT.ru.md) · [Deutsch](DEPLOYMENT.de.md) · [Français](DEPLOYMENT.fr.md) · [Español](DEPLOYMENT.es.md) · [Português](DEPLOYMENT.pt.md) · [हिन्दी](DEPLOYMENT.hi.md) · [العربية](DEPLOYMENT.ar.md) · **বাংলা** · [Bahasa Indonesia](DEPLOYMENT.id.md) · [日本語](DEPLOYMENT.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. এনভায়রনমেন্ট প্রয়োজনীয়তা

| কম্পোনেন্ট | ন্যূনতম সংস্করণ | প্রস্তাবিত কনফিগ |
|------|---------|---------|
| OS | Linux (Ubuntu 20.04+ / Debian 11+ / CentOS 8+) | Ubuntu 22.04 LTS |
| PHP | 8.3+ | 8.3+ (CLI, OPcache enabled) |
| PHP এক্সটেনশন | pdo, pdo_mysql, pcntl, redis, gd, mbstring, xml | সবগুলো |
| MySQL | 8.0+ | 8.0+ মাস্টার-স্লেভ রেপ্লিকেশন |
| Redis | 6.0+ | 7.x সেন্টিনেল মোড |
| Elasticsearch | 7.x+ | 8.x একক নোড |
| Nginx | 1.20+ | রিভার্স প্রক্সি + gzip + SSL |
| Composer | 2.x | সর্বশেষ স্টেবল |
| Flutter SDK | 3.x+ | সর্বশেষ স্টেবল (শুধুমাত্র ফ্রন্টএন্ড বিল্ডে প্রয়োজন) |

---

## 2. এক-ক্লিক ইনস্টল উইজার্ড (নতুন ডিপ্লয়ের জন্য প্রস্তাবিত)

```bash
# 1. প্রজেক্ট ক্লোন করুন
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. ইনস্টলেশন উইজার্ড চালু করুন
php -S 0.0.0.0:8888 -t install/

# 3. ব্রাউজারে http://<server-IP>:8888 খুলুন
#    উইজার্ড অনুসরণ করুন: এনভায়রনমেন্ট চেক → ডেটাবেস কনফিগারেশন → অ্যাডমিন অ্যাকাউন্ট → স্বয়ংক্রিয় ইনস্টলেশন

# 4. নির্ভরতা ইনস্টল করুন
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. সার্ভিস চালু করুন (ডিফল্ট পোর্ট admin 8789 / service 8792, সংশ্লিষ্ট .env-এর APP_PORT দিয়ে পরিবর্তন করা যায়)
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. নিরাপত্তা পরিষ্কার
rm -rf install/

# 7. অ্যাডমিন প্যানেলে প্রবেশ: http://<server-IP>:8789 (ডিফল্ট পোর্ট)
```

ইনস্টল উইজার্ড যা সম্পন্ন করে:
- PHP এনভায়রনমেন্ট চেক (সংস্করণ, এক্সটেনশন, ডিরেক্টরি পারমিশন)
- মিলিত SQL এক্সিকিউশন (`install/install.sql`), ৭৮টি টেবিল তৈরি ও সিড ডেটা ইমপোর্ট
- সুপার অ্যাডমিন অ্যাকাউন্ট তৈরি (bcrypt এনক্রিপ্ট, super_admin রোলের সাথে সম্পর্কিত)
- অটো JWT/Encryption/Hashids সিক্রেট জেনারেশন
- `admin/.env` ও `service/.env` লেখা
- বারবার ইনস্টল রোধে `install/install.lock` তৈরি

---

## 3. Docker Compose ডিপ্লয়

### 3.1 এক-ক্লিক স্টার্ট

```bash
# 1. প্রজেক্ট ক্লোন করুন
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. ওয়ান-ক্লিক ইনস্টলেশন উইজার্ড দিয়ে এনভায়রনমেন্ট কনফিগার করুন (অথবা .env ফাইল ম্যানুয়ালি কনফিগার করুন)
#    পোর্টের মতো Docker প্যারামিটার রুট ডিরেক্টরির .env-তে থাকে (টেমপ্লেট .env.example): cp .env.example .env
php -S 0.0.0.0:8888 -t install/
# ম্যানুয়াল পদ্ধতি: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. সব সার্ভিস বিল্ড ও চালু করুন
docker-compose up -d

# 4. অবস্থা দেখুন
docker-compose ps

# 5. লগ দেখুন
docker-compose logs -f
```

### 3.2 সার্ভিস তালিকা

| সার্ভিস | কন্টেইনারের নাম | পোর্ট | বিবরণ |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | রিভার্স প্রক্সি + স্ট্যাটিক ফাইল |
| admin | game-platform-admin | 8789 | অ্যাডমিন প্যানেল API |
| service | game-platform-service | 8792 | C-এন্ড ব্যবসা API |
| leaderboard-ws | game-platform-ws | 8790, 8791 | WebSocket লিডারবোর্ড/চ্যাট |
| mysql | game-platform-mysql | 3306 | মূল ডেটাবেস |
| redis | game-platform-redis | 6379 | ক্যাশ/রেট লিমিট |
| elasticsearch | game-platform-es | 9200 | ফুল-টেক্সট সার্চ |

> **পোর্ট কনফিগারেশন**: উপরের টেবিলটি ডিফল্ট পোর্ট দেখায়, সবগুলো প্রজেক্টের রুট ডিরেক্টরির `.env`-এ পরিবর্তন করা যায় (টেমপ্লেট `.env.example`, `cp .env.example .env` করে সম্পাদনা করুন):
> `NGINX_HTTP_PORT`, `NGINX_HTTPS_PORT`, `ADMIN_PORT`, `SERVICE_PORT`, `LEADERBOARD_WS_PORT`, `CHAT_WS_PORT`, `MYSQL_PORT`, `REDIS_PORT`, `ES_PORT`.
> `nginx.conf.template`-এর upstream পোর্ট অফিসিয়াল ইমেজের envsubst দিয়ে স্বয়ংক্রিয়ভাবে রেন্ডার হয়, ম্যানুয়ালি Nginx কনফিগ পরিবর্তনের প্রয়োজন নেই।
> Docker ডিপ্লয়ে, পাবলিক ঠিকানা (`APP_URL` / `SITE_URL`) ডিফল্টভাবে `ADMIN_PORT` / `SERVICE_PORT` অনুসরণ করে (ফরম্যাট `http://localhost:পোর্ট`); কাস্টম ডোমেইন বা HTTPS-এর জন্য রুট `.env`-এ `APP_URL` / `SITE_URL` সেট করুন (এটি `admin/.env` ও `service/.env`-এর একই কী ওভাররাইড করে)। বেয়ার-মেটাল (ম্যানুয়াল) ডিপ্লয়ে পোর্ট পরিবর্তন করলে ঠিকানা নিজে থেকে আপডেট করতে হবে।

### 3.3 ডেটাবেস ইনিশিয়ালাইজেশন

```bash
# মাইগ্রেশন ফাইল MySQL প্রথমবার চালু হলে স্বয়ংক্রিয়ভাবে 실행됩니다
# অথবা ম্যানুয়ালি চালান:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 3.4 ডেটা পার্সিস্টেন্স

ডেটা ভলিউম অটো তৈরি হয়, ম্যানুয়াল ম্যানেজমেন্ট প্রয়োজন নেই:

| ভলিউম | পাথ | বিষয়বস্তু |
|----|------|------|
| mysql_data | /var/lib/mysql | ডেটাবেস ফাইল |
| redis_data | /data | Redis পার্সিস্টেন্স |
| es_data | /usr/share/elasticsearch/data | ES ইনডেক্স |

ব্যাকআপ:
```bash
# MySQL ব্যাকআপ
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game-platform | gzip > backup_$(date +%Y%m%d).sql.gz

# রিস্টোর
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform
```

---

## 4. ম্যানুয়াল ডিপ্লয়

### 4.1 PHP এনভায়রনমেন্ট কনফিগ

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# OPcache চালু করুন (প্রোডাকশনে আবশ্যক)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 4.2 নির্ভরতা ইনস্টল

```bash
cd /opt/game-platform

# অ্যাডমিন প্যানেল
cd admin
cp .env.example .env
# .env সম্পাদনা করুন: ডেটাবেস সংযোগ, JWT_SECRET, HASHIDS_SALT ইত্যাদি
composer install --no-dev --optimize-autoloader

# C-সাইড ব্যবসা
cd ../service
cp .env.example .env
# .env সম্পাদনা করুন (দ্রষ্টব্য: SNOWFLAKE_WORKER_ID=2)
composer install --no-dev --optimize-autoloader
```

### 4.3 .env কনফিগ

**admin/.env গুরুত্বপূর্ণ কনফিগ:**
```ini
APP_ENV=production
APP_DEBUG=false
APP_PORT=8789  # webman HTTP লিসেন পোর্ট (APP_URL-এর সাথে সামঞ্জস্যপূর্ণ রাখুন)
APP_URL=http://localhost:8789  # বাহ্যিক অ্যাক্সেস ঠিকানা (API ডকুমেন্টেশন baseUrl ইত্যাদি)

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

**service/.env গুরুত্বপূর্ণ কনফিগ:**
```ini
# 与 admin 相同的数据库、Redis、ES 配置
APP_PORT=8792
LEADERBOARD_WS_PORT=8790  # লিডারবোর্ড WebSocket পোর্ট (ফ্রন্টএন্ডের সংযোগ ঠিকানার সাথে সামঞ্জস্যপূর্ণ)
CHAT_WS_PORT=8791  # চ্যাট WebSocket পোর্ট
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

### 4.4 সার্ভিস স্টার্ট

```bash
# অ্যাডমিন প্যানেল (ডিফল্ট পোর্ট 8789, admin/.env-এর APP_PORT পরিবর্তনযোগ্য)
cd /opt/game-platform/admin
php start.php start -d

# C-সাইড ব্যবসা (ডিফল্ট পোর্ট 8792, service/.env-এর APP_PORT পরিবর্তনযোগ্য)
cd /opt/game-platform/service
php start.php start -d

# যাচাই
curl http://localhost:8789/health
curl http://localhost:8792/health
```

### 4.5 প্রসেস ম্যানেজমেন্ট (Systemd)

`/etc/systemd/system/game-platform-admin.service` তৈরি করুন:

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

একইভাবে `game-platform-service.service` তৈরি করুন (WorkingDirectory `/opt/game-platform/service`-এ পরিবর্তন)।

```bash
systemctl daemon-reload
systemctl enable --now game-platform-admin game-platform-service
```

---

## 5. Nginx রিভার্স প্রক্সি

### 5.1 কনফিগ ফাইল

`/etc/nginx/sites-available/game-platform` তৈরি করুন:

```nginx
# পোর্টগুলো ডিফল্ট মান (admin 8789 / service 8792 / ws 8790); .env পরিবর্তন করা থাকলে সেগুলোও সমন্বয় করুন
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

    # WebSocket লিডারবোর্ড (ডিফল্ট পোর্ট 8790, service/.env-এর LEADERBOARD_WS_PORT-এর সাথে সামঞ্জস্যপূর্ণ)
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

> ম্যানুয়াল ডিপ্লয়মেন্টে এই ডিরেক্টরিগুলোতে বিল্ড আর্টিফ্যাক্ট আপনি নিজেই রাখবেন (C-প্রান্তের চারটি ট্রি: `apps/flutter/platform`, `apps/react`, `apps/angular`, `apps/harmonyos`; সব কনসোল ফ্রন্টএন্ড `admin/apps/*` এবং সাধারণ প্লেসমেন্ট স্লট `admin/public`-এ মাউন্ট করা হয়)।
> Docker ডিপ্লয়মেন্টের জন্য `docker-compose.yml`-এর nginx ভলিউম মাউন্ট ও `nginx.conf.template` দেখুন (একই পাথ, কন্টেইনারের ভিতরে রুট `/var/www/...`)। HarmonyOS `.hap` হিসেবে বিতরণ হয় এবং nginx দিয়ে যায় না।

সাইট সক্রিয়করণ:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 5.2 SSL সার্টিফিকেট

```bash
# Certbot দিয়ে স্বয়ংক্রিয়ভাবে Let's Encrypt সার্টিফিকেট পান
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# স্বয়ংক্রিয় নবায়ন (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 6. শিডিউলড টাস্ক (Crontab)

```bash
# crontab সম্পাদনা করুন
crontab -e

# দৈনিক পরিসংখ্যান স্ন্যাপশট (প্রতিদিন রাত 1:00)
0 1 * * * cd /opt/game-platform/admin && php start.php queue ComputeDailyStats

# ডেটাবেস ব্যাকআপ (প্রতিদিন রাত 2:00)
0 2 * * * cd /opt/game-platform/admin/database/backup && bash backup.sh

# SSL সার্টিফিকেট স্বয়ংক্রিয় নবায়ন
0 3 * * * certbot renew --quiet && systemctl reload nginx

# লিডারবোর্ড ক্যাশ রিফ্রেশ (প্রতি ঘণ্টায়)
0 * * * * cd /opt/game-platform/admin && php start.php queue RefreshLeaderboards
```

---

## 7. মনিটরিং

### 7.1 Prometheus মেট্রিক

অ্যাডমিন প্যানেল `/metrics` এন্ডপয়েন্ট প্রকাশ করে, নিচের মেট্রিকগুলো সহ:

| মেট্রিক | বিবরণ |
|------|------|
| openadmin_http_requests_total | মোট রিকোয়েস্ট সংখ্যা |
| openadmin_active_users | সক্রিয় ব্যবহারকারী সংখ্যা |
| openadmin_db_connection_status | ডেটাবেস সংযোগ (0/1) |
| openadmin_redis_connection_status | Redis সংযোগ (0/1) |
| openadmin_memory_usage_bytes | মেমরি ব্যবহার |

### 7.2 হেলথ চেক

```bash
# অ্যাডমিন প্যানেল
curl -f http://localhost:8789/health || echo "Admin DOWN"

# C-সাইড ব্যবসা
curl -f http://localhost:8792/health || echo "Service DOWN"

# লোড ব্যালেন্সার বা মনিটরিং সিস্টেমে কনফিগার করা যায়
```

### 7.3 লগ

```
admin/runtime/logs/
├── stdout.log          # 标准输出
└── webman-<date>.log   # Webman 日志

service/runtime/logs/
├── stdout.log
└── webman-<date>.log
```

---

## 8. পারফরম্যান্স অপ্টিমাইজেশন

### 8.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # 生产环境关闭文件检查
```

### 8.2 MySQL অপ্টিমাইজেশন

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # 设为物理内存的 50-70%
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # 性能优先
max_connections = 200
query_cache_type = 0               # MySQL 8.0 已移除
```

### 8.3 Worker প্রসেস সংখ্যা

```php
// config/process.php
'count' => cpu_count() * 2,  // 生产环境建议 2-4 倍 CPU 核心数
```

### 8.4 Redis ক্যাশ কৌশল

| ক্যাশ কী | TTL | বিবরণ |
|--------|-----|------|
| dashboard:data | 300s | ড্যাশবোর্ড ডেটা |
| i18n:translations | 3600s | অনুবাদ টেক্সট |
| leaderboard:{id} | 3600s | লিডারবোর্ড |
| rate_limit:{ip}:{route} | 60s | রেট লিমিট উইন্ডো |

---

## 9. নিরাপত্তা শক্তিশালীকরণ

### 9.1 সিক্রেট জেনারেশন

```bash
# র্যান্ডম কী তৈরি করুন
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

### 9.2 ফায়ারওয়াল

```bash
# শুধুমাত্র প্রয়োজনীয় পোর্ট খুলুন
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# অভ্যন্তরীণ পোর্ট এক্সপোজ করা উচিত নয়
# 8789 (admin), 8792 (service), 8790/8791 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# উপরের পোর্টগুলো ডিফল্ট; রুট .env / সংশ্লিষ্ট .env পরিবর্তন করা থাকলে প্রকৃত কনফিগ মান্য হবে
# শুধুমাত্র 127.0.0.1 দিয়ে অ্যাক্সেস
```

### 9.3 ফাইল পারমিশন

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 10. সমস্যা সমাধান

### 10.1 সার্ভিস স্টার্ট হচ্ছে না

```bash
# ত্রুটি দেখতে ফোরগ্রাউন্ডে চালান
cd /opt/game-platform/admin && php start.php start

# পোর্ট ব্যবহার পরীক্ষা করুন
ss -tlnp | grep -E '8789|8792'

# লগ পরীক্ষা করুন
tail -f runtime/logs/webman-$(date +%F).log
```

### 10.2 ডেটাবেস সংযোগ ব্যর্থ

```bash
# সংযোগ পরীক্ষা করুন
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# .env কনফিগ পরীক্ষা করুন
grep DB_ admin/.env
```

### 10.3 Redis সংযোগ ব্যর্থ

```bash
# সংযোগ পরীক্ষা করুন
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# PONG প্রত্যাশিত
```

### 10.4 Elasticsearch অনুপলব্ধ

```bash
# সংযোগ পরীক্ষা করুন
curl http://127.0.0.1:9200

# সার্চ স্বয়ংক্রিয়ভাবে LIKE কুয়েরিতে ফলব্যাক করে, সার্ভিস বন্ধ হয় না
```

### 10.5 পারফরম্যান্স সমস্যা

```bash
# worker প্রসেস সংখ্যা পরীক্ষা করুন
php start.php status

# মেমরি ব্যবহার দেখুন
free -h

# ডেটাবেস ধীর কুয়েরি পরীক্ষা করুন
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 11. আপগ্রেড গাইড

```bash
# 1. সর্বশেষ কোড টানুন
cd /opt/game-platform && git pull origin main

# 2. নির্ভরতা আপডেট করুন
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. নতুন মাইগ্রেশন চালান (যদি থাকে)
mysql -u game-platform -p game-platform < install/新迁移文件.sql

# 4. স্মুথ রিস্টার্ট (সার্ভিস বন্ধ না করে)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
