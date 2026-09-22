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

# 5. सेवाएँ शुरू करें (डिफ़ॉल्ट पोर्ट admin 8789 / service 8792, संबंधित .env के APP_PORT से बदला जा सकता है)
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. सुरक्षा सफाई
rm -rf install/

# 7. प्रशासन कंसोल तक पहुँचें: http://<सर्वरIP>:8789 (डिफ़ॉल्ट पोर्ट)
```

स्थापना विज़ार्ड द्वारा पूर्ण किए गए कार्य:
- PHP पर्यावरण जाँच (संस्करण, एक्सटेंशन, निर्देशिका अनुमतियाँ)
- संयुक्त SQL (`install/install.sql`) निष्पादित करें, 78 तालिकाएँ बनाएं और सीड डेटा आयात करें
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
#    पोर्ट जैसे Docker पैरामीटर रूट डायरेक्टरी के .env में हैं (टेम्पलेट .env.example): cp .env.example .env
php -S 0.0.0.0:8888 -t install/
# मैन्युअल विधि: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. सभी सेवाएँ बनाएं और शुरू करें
docker-compose up -d

# 4. स्थिति देखें
docker-compose ps

# 5. लॉग देखें
docker-compose logs -f
```

### 3.2 सेवा सूची

| सेवा | कंटेनर नाम | पोर्ट | विवरण |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | रिवर्स प्रॉक्सी + स्थिर फ़ाइलें |
| admin | game-platform-admin | 8789 | प्रशासन कंसोल API |
| service | game-platform-service | 8792 | C-छोर व्यवसाय API |
| leaderboard-ws | game-platform-ws | 8790, 8791 | WebSocket लीडरबोर्ड/चैट |
| mysql | game-platform-mysql | 3306 | मुख्य डेटाबेस |
| redis | game-platform-redis | 6379 | कैश/दर सीमा |
| elasticsearch | game-platform-es | 9200 | पूर्ण-पाठ खोज |

> **पोर्ट कॉन्फ़िगरेशन**: ऊपर की तालिका डिफ़ॉल्ट पोर्ट दिखाती है, सभी को प्रोजेक्ट की रूट डायरेक्टरी के `.env` में बदला जा सकता है (टेम्पलेट `.env.example`, `cp .env.example .env` के बाद संपादित करें):
> `NGINX_HTTP_PORT`, `NGINX_HTTPS_PORT`, `ADMIN_PORT`, `SERVICE_PORT`, `LEADERBOARD_WS_PORT`, `CHAT_WS_PORT`, `MYSQL_PORT`, `REDIS_PORT`, `ES_PORT`.
> `nginx.conf.template` के upstream पोर्ट आधिकारिक इमेज के envsubst द्वारा स्वचालित रूप से रेंडर होते हैं, Nginx कॉन्फ़िग मैन्युअल रूप से बदलने की आवश्यकता नहीं।
> Docker परिनियोजन में, सार्वजनिक पते (`APP_URL` / `SITE_URL`) डिफ़ॉल्ट रूप से `ADMIN_PORT` / `SERVICE_PORT` का स्वतः अनुसरण करते हैं (प्रारूप `http://localhost:पोर्ट`); कस्टम डोमेन या HTTPS के लिए रूट `.env` में `APP_URL` / `SITE_URL` सेट करें (यह `admin/.env` और `service/.env` की समान कुंजियों को ओवरराइड करता है)। बेयर-मेटल (मैनुअल) परिनियोजन में पोर्ट बदलते समय पते स्वयं अपडेट करने होंगे।

### 3.3 डेटाबेस आरंभीकरण

```bash
# माइग्रेशन फ़ाइलें MySQL के पहले प्रारंभ पर स्वचालित रूप से निष्पादित होती हैं
# या मैन्युअल निष्पादन:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 3.4 डेटा स्थायीकरण

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

## 4. मैन्युअल परिनियोजन

### 4.1 PHP पर्यावरण कॉन्फ़िगरेशन

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# OPcache सक्षम करें (उत्पादन में अनिवार्य)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 4.2 निर्भरताएँ स्थापित करें

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

### 4.3 .env कॉन्फ़िगरेशन

**admin/.env मुख्य कॉन्फ़िग:**
```ini
APP_ENV=production
APP_DEBUG=false
APP_PORT=8789  # webman HTTP लिसन पोर्ट (APP_URL के साथ संगत रखें)
APP_URL=http://localhost:8789  # बाहरी पहुँच पता (API दस्तावेज़ baseUrl आदि)

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
APP_PORT=8792
LEADERBOARD_WS_PORT=8790  # लीडरबोर्ड WebSocket
CHAT_WS_PORT=8791  # चैट WebSocket
SNOWFLAKE_WORKER_ID=2  # admin से भिन्न होना अनिवार्य

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<Google Cloud Console से प्राप्त>
OAUTH_GOOGLE_CLIENT_SECRET=<कुंजी>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# भुगतान Webhook (toss / mpesa / paystack जल्द आ रहे हैं)
STRIPE_SECRET_KEY=<Stripe गुप्त कुंजी>
STRIPE_WEBHOOK_SECRET=<Stripe Dashboard से प्राप्त>
PAYPAL_WEBHOOK_ID=<PayPal Developer से प्राप्त>
PAYPAL_VERIFY_URL=<PayPal Webhook सत्यापन पता>
PAYPAL_CLIENT_ID=<PayPal Client ID>
PAYPAL_CLIENT_SECRET=<PayPal Client Secret>
PAYPAL_MODE=sandbox  # sandbox / live
NOWPAYMENTS_API_KEY=<NOWPayments API कुंजी>
NOWPAYMENTS_IPN_SECRET=<IPN हस्ताक्षर कुंजी>
NOWPAYMENTS_API_URL=https://api.nowpayments.io  # डिफ़ॉल्ट पता
COINBASE_COMMERCE_API_KEY=<Coinbase Commerce API कुंजी>
COINBASE_COMMERCE_WEBHOOK_SECRET=<Coinbase Commerce Webhook कुंजी>
SKRILL_API_URL=https://pay.skrill.com
SKRILL_API_KEY=<Skrill API कुंजी>
SKRILL_MERCHANT_ID=<Skrill व्यापारी आईडी>
SKRILL_SECRET_WORD=<कॉलबैक हस्ताक्षर सत्यापन कुंजी md5sig>
NETELLER_API_URL=https://api.neteller.com
NETELLER_CLIENT_ID=<Neteller Client ID>
NETELLER_CLIENT_SECRET=<Neteller Client Secret>
NETELLER_SECRET=<कॉलबैक हस्ताक्षर सत्यापन कुंजी>
PAYSAFECARD_API_URL=https://api.paysafecard.com
PAYSAFECARD_API_KEY=<Paysafecard API कुंजी>
PAYSAFECARD_SECRET=<कॉलबैक हस्ताक्षर सत्यापन कुंजी X-Signature HMAC-SHA256>
PAYTM_MID=<Paytm व्यापारी आईडी>
PAYTM_KEY=<Paytm कुंजी>
PAYTM_API_URL=https://securegw.paytm.in
PAYTM_WEBSITE=DEFAULT  # प्रोडक्शन DEFAULT / स्टेजिंग WEBSTAGING
MERCADOPAGO_CLIENT_ID=<Mercado Pago Client ID>
MERCADOPAGO_CLIENT_SECRET=<Mercado Pago Client Secret>
MERCADOPAGO_WEBHOOK_SECRET=<Webhook हस्ताक्षर सत्यापन कुंजी X-Signature>
MERCADOPAGO_API_URL=https://api.mercadopago.com
ASTROPAY_LOGIN=<AstroPay लॉगिन नाम>
ASTROPAY_API_KEY=<AstroPay API कुंजी>
ASTROPAY_SECRET=<कॉलबैक हस्ताक्षर सत्यापन कुंजी MD5>
ASTROPAY_API_URL=https://api.astropaycard.com
PAYPAY_CLIENT_ID=<PayPay Client ID>
PAYPAY_CLIENT_SECRET=<PayPay Client Secret>
PAYPAY_SIGNING_KEY=<Webhook हस्ताक्षर सत्यापन कुंजी PayPay-Signature>
PAYPAY_API_URL=https://api.paypay.ne.jp
KAKAOPAY_ADMIN_KEY=<KakaoPay Admin Key>
KAKAOPAY_CID=<KakaoPay व्यापारी CID>
KAKAOPAY_APPROVAL_URL=<भुगतान के बाद स्वीकृति रीडायरेक्ट URL>
KAKAOPAY_API_URL=https://kapi.kakao.com
PAYMONGO_API_KEY=<PayMongo API कुंजी>
PAYMONGO_WEBHOOK_SECRET=<Webhook हस्ताक्षर सत्यापन कुंजी Paymongo-Signature>
PAYMONGO_API_URL=https://api.paymongo.com/v1
# जल्द आ रहे हैं
MPESA_CONSUMER_KEY=<M-Pesa Consumer Key>
MPESA_CONSUMER_SECRET=<M-Pesa Consumer Secret>
MPESA_PASSKEY=<M-Pesa STK Push Passkey>
MPESA_SHORTCODE=<M-Pesa शॉर्ट कोड>
MPESA_API_URL=https://api.safaricom.co.ke
PAYSTACK_SECRET_KEY=<Paystack Secret Key>
PAYSTACK_API_URL=https://api.paystack.co
TOSS_SECRET_KEY=<Toss Secret Key>
TOSS_API_URL=https://api.tosspayments.com
SITE_URL=https://your-domain.com  # भुगतान कॉलबैक/रीडायरेक्ट साइट URL
```

### 4.4 सेवाएँ शुरू करें

```bash
# प्रशासन कंसोल (डिफ़ॉल्ट पोर्ट 8789, admin/.env का APP_PORT बदला जा सकता है)
cd /opt/game-platform/admin
php start.php start -d

# C-छोर व्यवसाय (डिफ़ॉल्ट पोर्ट 8792, service/.env का APP_PORT बदला जा सकता है)
cd /opt/game-platform/service
php start.php start -d

# सत्यापन
curl http://localhost:8789/health
curl http://localhost:8792/health
```

### 4.5 प्रक्रिया प्रबंधन (Systemd)

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

## 5. Nginx रिवर्स प्रॉक्सी

### 5.1 कॉन्फ़िग फ़ाइल

`/etc/nginx/sites-available/game-platform` बनाएं:

```nginx
# पोर्ट डिफ़ॉल्ट मान हैं (admin 8789 / service 8792 / ws 8790)। यदि .env बदला गया है तो तदनुसार समायोजित करें
server {
    listen 80;
    server_name your-domain.com;

    # nginx 自身发出的 301（如目录补斜杠 /admin-panel → /admin-panel/）改用相对
    # Location，客户端按当前 host:port 解析；默认绝对跳转会退回 listen 端口，
    # 非 80 端口部署（如 8080）时会跳错端口。
    absolute_redirect off;

    # प्रशासन कंसोल API
    location /admin/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # C-छोर API
    location /api/ {
        proxy_pass http://127.0.0.1:8792;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket लीडरबोर्ड (डिफ़ॉल्ट पोर्ट 8790, service/.env के LEADERBOARD_WS_PORT के अनुरूप)
    location /ws/ {
        proxy_pass http://127.0.0.1:8790;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # स्वास्थ्य जाँच
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

> मैनुअल डिप्लॉयमेंट में ये बिल्ड आर्टिफ़ैक्ट आप स्वयं इन निर्देशिकाओं में रखते हैं (C-छोर के चार ट्री: `apps/flutter/platform`, `apps/react`, `apps/angular`, `apps/harmonyos`; सभी कंसोल फ्रंटएंड `admin/apps/*` तथा सामान्य प्लेसमेंट स्थान `admin/public` पर माउंट होते हैं)।
> Docker डिप्लॉयमेंट के लिए `docker-compose.yml` के nginx वॉल्यूम माउंट और `nginx.conf.template` देखें (समान पथ, कंटेनर के भीतर रूट `/var/www/...`)। HarmonyOS `.hap` के रूप में वितरित होता है और nginx से नहीं जाता।

साइट सक्षम करें:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 5.2 SSL प्रमाणपत्र

```bash
# Certbot से Let's Encrypt प्रमाणपत्र स्वचालित रूप से प्राप्त करें
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# स्वचालिक नवीनीकरण (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 6. निर्धारित कार्य (Crontab)

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

## 7. निगरानी

### 7.1 Prometheus मीट्रिक्स

प्रशासन कंसोल `/metrics` एंडपॉइंट उजागर करता है, निम्न मीट्रिक्स शामिल:

| मीट्रिक | विवरण |
|------|------|
| openadmin_http_requests_total | अनुरोधों की कुल संख्या |
| openadmin_active_users | सक्रिय उपयोगकर्ताओं की संख्या |
| openadmin_db_connection_status | डेटाबेस कनेक्शन (0/1) |
| openadmin_redis_connection_status | Redis कनेक्शन (0/1) |
| openadmin_memory_usage_bytes | मेमोरी उपयोग |

### 7.2 स्वास्थ्य जाँच

```bash
# प्रशासन कंसोल
curl -f http://localhost:8789/health || echo "Admin DOWN"

# C-छोर व्यवसाय
curl -f http://localhost:8792/health || echo "Service DOWN"

# लोड बैलेंसर या निगरानी प्रणाली में कॉन्फ़िगर किया जा सकता है
```

### 7.3 लॉग

```
admin/runtime/logs/
├── stdout.log          # मानक आउटपुट
└── webman-<date>.log   # Webman लॉग

service/runtime/logs/
├── stdout.log
└── webman-<date>.log
```

---

## 8. प्रदर्शन अनुकूलन

### 8.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # उत्पादन में फ़ाइल जाँच बंद करें
```

### 8.2 MySQL अनुकूलन

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # भौतिक मेमोरी का 50-70% निर्धारित करें
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # प्रदर्शन प्राथमिकता
max_connections = 200
query_cache_type = 0               # MySQL 8.0 में हटा दिया गया
```

### 8.3 Worker प्रक्रिया संख्या

```php
// config/process.php
'count' => cpu_count() * 2,  // उत्पादन में CPU कोर की 2-4 गुना अनुशंसित
```

### 8.4 Redis कैश रणनीति

| कैश कुंजी | TTL | विवरण |
|--------|-----|------|
| dashboard:data | 300s | डैशबोर्ड डेटा |
| i18n:translations | 3600s | अनुवाद पाठ |
| leaderboard:{id} | 3600s | लीडरबोर्ड |
| rate_limit:{ip}:{route} | 60s | दर सीमा विंडो |

---

## 9. सुरक्षा सुदृढ़ीकरण

### 9.1 कुंजी उत्पादन

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

### 9.2 फ़ायरवॉल

```bash
# केवल आवश्यक पोर्ट खोलें
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# आंतरिक पोर्ट उजागर नहीं होने चाहिए
# 8789 (admin), 8792 (service), 8790/8791 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# उपरोक्त डिफ़ॉल्ट पोर्ट हैं। यदि रूट .env / संबंधित .env बदला गया है, तो वास्तविक कॉन्फ़िग मान्य होगा
# केवल 127.0.0.1 के माध्यम से पहुँच
```

### 9.3 फ़ाइल अनुमतियाँ

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 10. समस्या निवारण

### 10.1 सेवा प्रारंभ नहीं हो सकती

```bash
# त्रुटि देखने के लिए फोरग्राउंड में चलाएं
cd /opt/game-platform/admin && php start.php start

# पोर्ट अधिग्रहण जाँचें
ss -tlnp | grep -E '8789|8792'

# लॉग जाँचें
tail -f runtime/logs/webman-$(date +%F).log
```

### 10.2 डेटाबेस कनेक्शन विफल

```bash
# कनेक्शन परीक्षण
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# .env कॉन्फ़िग जाँचें
grep DB_ admin/.env
```

### 10.3 Redis कनेक्शन विफल

```bash
# कनेक्शन परीक्षण
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# अपेक्षित रिटर्न PONG
```

### 10.4 Elasticsearch अनुपलब्ध

```bash
# कनेक्शन परीक्षण
curl http://127.0.0.1:9200

# खोज फ़ंक्शन स्वचालित रूप से LIKE क्वेरी पर रोलबैक होता है, सेवा बाधित नहीं होती
```

### 10.5 प्रदर्शन समस्याएँ

```bash
# worker प्रक्रिया संख्या जाँचें
php start.php status

# मेमोरी उपयोग देखें
free -h

# डेटाबेस धीमी क्वेरी जाँचें
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 11. उन्नयन मार्गदर्शिका

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
