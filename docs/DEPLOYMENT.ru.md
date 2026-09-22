# Документ развёртывания
<!-- lang-nav -->

Languages: **中文** · [English](DEPLOYMENT.en.md) · [한국어](DEPLOYMENT.ko.md) · [Русский](DEPLOYMENT.ru.md) · [Deutsch](DEPLOYMENT.de.md) · [Français](DEPLOYMENT.fr.md) · [Español](DEPLOYMENT.es.md) · [Português](DEPLOYMENT.pt.md) · [हिन्दी](DEPLOYMENT.hi.md) · [العربية](DEPLOYMENT.ar.md) · [বাংলা](DEPLOYMENT.bn.md) · [Bahasa Indonesia](DEPLOYMENT.id.md) · [日本語](DEPLOYMENT.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Требования к окружению

| Компонент | Минимальная версия | Рекомендуемая конфигурация |
|------|---------|---------|
| ОС | Linux (Ubuntu 20.04+ / Debian 11+ / CentOS 8+) | Ubuntu 22.04 LTS |
| PHP | 8.3+ | 8.3+ (CLI, OPcache включён) |
| Расширения PHP | pdo, pdo_mysql, pcntl, redis, gd, mbstring, xml | все |
| MySQL | 8.0+ | 8.0+ мастер-реплика |
| Redis | 6.0+ | 7.x режим сентинелей |
| Elasticsearch | 7.x+ | 8.x один узел |
| Nginx | 1.20+ | обратный прокси + gzip + SSL |
| Composer | 2.x | последняя стабильная версия |
| Flutter SDK | 3.x+ | последняя стабильная версия (нужен только для сборки фронтенда) |

---

## 2. Мастер установки в один клик (рекомендуется для новых развёртываний)

```bash
# 1. Клонировать проект
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Запустить мастер установки
php -S 0.0.0.0:8888 -t install/

# 3. Открыть http://<IP-сервера>:8888 в браузере
#    Пройти мастер: проверка окружения → настройка базы данных → аккаунт администратора → автоустановка

# 4. Установить зависимости
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. Запуск сервисов (порты по умолчанию: admin 8789 / service 8792, изменяются через APP_PORT в соответствующем .env)
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. Очистка безопасности
rm -rf install/

# 7. Доступ к админ-панели: http://<IP-сервера>:8789 (порт по умолчанию)
```

Что выполняет мастер установки:
- Проверка окружения PHP (версия, расширения, права на каталоги)
- Выполнение объединённого SQL (`install/install.sql`) — создание 78 таблиц и импорт стартовых данных
- Создание учётной записи супер-администратора (bcrypt-шифрование, привязка к роли super_admin)
- Автоматическая генерация ключей JWT/Encryption/Hashids
- Запись `admin/.env` и `service/.env`
- Создание `install/install.lock` для предотвращения повторной установки

---

## 3. Развёртывание через Docker Compose

### 3.1 Запуск в один клик

```bash
# 1. Клонировать проект
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Настроить окружение с помощью мастера установки в один клик (или настроить файлы .env вручную)
#    Параметры Docker, такие как порты, находятся в .env в корне проекта (шаблон .env.example): cp .env.example .env
php -S 0.0.0.0:8888 -t install/
# Вручную: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. Собрать и запустить все сервисы
docker-compose up -d

# 4. Проверить статус
docker-compose ps

# 5. Просмотреть логи
docker-compose logs -f
```

### 3.2 Список сервисов

| Сервис | Имя контейнера | Порт | Описание |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | обратный прокси + статические файлы |
| admin | game-platform-admin | 8789 | API админ-панели |
| service | game-platform-service | 8792 | API C-стороннего бизнеса |
| leaderboard-ws | game-platform-ws | 8790, 8791 | WebSocket-рейтинг/чат |
| mysql | game-platform-mysql | 3306 | основная база данных |
| redis | game-platform-redis | 6379 | кэш/лимиты |
| elasticsearch | game-platform-es | 9200 | полнотекстовый поиск |

> **Настройка портов**: Порты в таблице — значения по умолчанию; все они изменяются в `.env` в корне проекта (шаблон `.env.example`; отредактируйте после `cp .env.example .env`):
> `NGINX_HTTP_PORT`, `NGINX_HTTPS_PORT`, `ADMIN_PORT`, `SERVICE_PORT`, `LEADERBOARD_WS_PORT`, `CHAT_WS_PORT`, `MYSQL_PORT`, `REDIS_PORT`, `ES_PORT`.
> Порты upstream в `nginx.conf.template` автоматически подставляются официальным образом через envsubst — вручную править конфигурацию Nginx не нужно.
> При развёртывании через Docker публичные адреса (`APP_URL` / `SITE_URL`) по умолчанию автоматически следуют за `ADMIN_PORT` / `SERVICE_PORT` (в формате `http://localhost:порт`); для собственного домена или HTTPS задайте `APP_URL` / `SITE_URL` в корневом `.env` (переопределяет одноимённые ключи в `admin/.env` и `service/.env`). При ручном (bare-metal) развёртывании при смене портов адреса по-прежнему нужно обновлять самостоятельно.

### 3.3 Инициализация базы данных

```bash
# Файлы миграций выполняются автоматически при первом запуске MySQL
# Или выполнить вручную:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 3.4 Персистентность данных

Тома данных создаются автоматически, ручное управление не требуется:

| Том | Путь | Содержимое |
|----|------|------|
| mysql_data | /var/lib/mysql | файлы базы данных |
| redis_data | /data | персистентность Redis |
| es_data | /usr/share/elasticsearch/data | индексы ES |

Резервное копирование:
```bash
# Резервное копирование MySQL
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game-platform | gzip > backup_$(date +%Y%m%d).sql.gz

# Восстановление
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform
```

---

## 4. Ручное развёртывание

### 4.1 Настройка окружения PHP

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# Включить OPcache (обязательно в production)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 4.2 Установка зависимостей

```bash
cd /opt/game-platform

# Админ-панель
cd admin
cp .env.example .env
# Отредактировать .env: подключение к БД, JWT_SECRET, HASHIDS_SALT и т. д.
composer install --no-dev --optimize-autoloader

# C-сторонний бизнес
cd ../service
cp .env.example .env
# Отредактировать .env (внимание: SNOWFLAKE_WORKER_ID=2)
composer install --no-dev --optimize-autoloader
```

### 4.3 Настройка .env

**Ключевые параметры admin/.env:**
```ini
APP_ENV=production
APP_DEBUG=false
APP_PORT=8789  # порт прослушивания HTTP webman (должен совпадать с APP_URL)
APP_URL=http://localhost:8789  # внешний адрес доступа (baseUrl документации API и т. п.)

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

**Ключевые параметры service/.env:**
```ini
# Те же настройки базы данных, Redis и ES, что и в admin
APP_PORT=8792  # порт прослушивания HTTP webman
LEADERBOARD_WS_PORT=8790  # WebSocket рейтинга (должен совпадать с адресом подключения фронтенда)
CHAT_WS_PORT=8791  # WebSocket чата
SNOWFLAKE_WORKER_ID=2  # должен отличаться от admin

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<получено из Google Cloud Console>
OAUTH_GOOGLE_CLIENT_SECRET=<секретный ключ>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# Вебхук платежа
STRIPE_SECRET_KEY=<секретный ключ Stripe>
STRIPE_WEBHOOK_SECRET=<получено из Stripe Dashboard>
PAYPAL_WEBHOOK_ID=<получено из PayPal Developer>
PAYPAL_VERIFY_URL=<адрес проверки подписи Webhook PayPal>
PAYPAL_CLIENT_ID=<PayPal Client ID>
PAYPAL_CLIENT_SECRET=<PayPal Client Secret>
PAYPAL_MODE=sandbox  # sandbox / live
NOWPAYMENTS_API_KEY=<ключ API NOWPayments>
NOWPAYMENTS_IPN_SECRET=<ключ подписи IPN>
NOWPAYMENTS_API_URL=https://api.nowpayments.io  # адрес по умолчанию
COINBASE_COMMERCE_API_KEY=<ключ API Coinbase Commerce>
COINBASE_COMMERCE_WEBHOOK_SECRET=<ключ Webhook Coinbase Commerce>
SKRILL_API_URL=https://pay.skrill.com
SKRILL_API_KEY=<ключ API Skrill>
SKRILL_MERCHANT_ID=<ID продавца Skrill>
SKRILL_SECRET_WORD=<ключ проверки подписи callback md5sig>
NETELLER_API_URL=https://api.neteller.com
NETELLER_CLIENT_ID=<Neteller Client ID>
NETELLER_CLIENT_SECRET=<Neteller Client Secret>
NETELLER_SECRET=<ключ проверки подписи callback>
PAYSAFECARD_API_URL=https://api.paysafecard.com
PAYSAFECARD_API_KEY=<ключ API Paysafecard>
PAYSAFECARD_SECRET=<ключ проверки подписи callback X-Signature HMAC-SHA256>
PAYTM_MID=<ID продавца Paytm>
PAYTM_KEY=<ключ Paytm>
PAYTM_API_URL=https://securegw.paytm.in
PAYTM_WEBSITE=DEFAULT  # продакшн DEFAULT / staging WEBSTAGING
MERCADOPAGO_CLIENT_ID=<Mercado Pago Client ID>
MERCADOPAGO_CLIENT_SECRET=<Mercado Pago Client Secret>
MERCADOPAGO_WEBHOOK_SECRET=<ключ проверки подписи Webhook X-Signature>
MERCADOPAGO_API_URL=https://api.mercadopago.com
ASTROPAY_LOGIN=<логин AstroPay>
ASTROPAY_API_KEY=<ключ API AstroPay>
ASTROPAY_SECRET=<ключ проверки подписи callback MD5>
ASTROPAY_API_URL=https://api.astropaycard.com
PAYPAY_CLIENT_ID=<PayPay Client ID>
PAYPAY_CLIENT_SECRET=<PayPay Client Secret>
PAYPAY_SIGNING_KEY=<ключ проверки подписи Webhook PayPay-Signature>
PAYPAY_API_URL=https://api.paypay.ne.jp
KAKAOPAY_ADMIN_KEY=<KakaoPay Admin Key>
KAKAOPAY_CID=<CID продавца KakaoPay>
KAKAOPAY_APPROVAL_URL=<URL перенаправления на одобрение после оплаты>
KAKAOPAY_API_URL=https://kapi.kakao.com
PAYMONGO_API_KEY=<ключ API PayMongo>
PAYMONGO_WEBHOOK_SECRET=<ключ проверки подписи Webhook Paymongo-Signature>
PAYMONGO_API_URL=https://api.paymongo.com/v1
# M-Pesa / Paystack / Toss
MPESA_CONSUMER_KEY=<M-Pesa Consumer Key>
MPESA_CONSUMER_SECRET=<M-Pesa Consumer Secret>
MPESA_PASSKEY=<M-Pesa STK Push Passkey>
MPESA_SHORTCODE=<короткий код M-Pesa>
MPESA_API_URL=https://api.safaricom.co.ke
PAYSTACK_SECRET_KEY=<Paystack Secret Key>
PAYSTACK_API_URL=https://api.paystack.co
TOSS_SECRET_KEY=<Toss Secret Key>
TOSS_API_URL=https://api.tosspayments.com
SITE_URL=https://your-domain.com  # URL сайта для callback/перенаправлений платежей
```

### 4.4 Запуск сервисов

```bash
# Админ-панель (порт по умолчанию 8789, изменяется через APP_PORT в admin/.env)
cd /opt/game-platform/admin
php start.php start -d

# C-сторонний бизнес (порт по умолчанию 8792, изменяется через APP_PORT в service/.env)
cd /opt/game-platform/service
php start.php start -d

# Проверка
curl http://localhost:8789/health
curl http://localhost:8792/health
```

### 4.5 Управление процессами (Systemd)

Создайте `/etc/systemd/system/game-platform-admin.service`:

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

Аналогично создайте `game-platform-service.service` (измените WorkingDirectory на `/opt/game-platform/service`).

```bash
systemctl daemon-reload
systemctl enable --now game-platform-admin game-platform-service
```

---

## 5. Обратный прокси Nginx

### 5.1 Файл конфигурации

Создайте `/etc/nginx/sites-available/game-platform`:

```nginx
# Порты — значения по умолчанию (admin 8789 / service 8792 / ws 8790); при изменении .env скорректируйте их здесь
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

    # WebSocket-рейтинг (порт по умолчанию 8790, совпадает с LEADERBOARD_WS_PORT в service/.env)
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

> При ручном развёртывании артефакты сборки вы кладёте в эти каталоги сами (четыре дерева клиента C: `apps/flutter/platform`, `apps/react`, `apps/angular`, `apps/harmonyos`; все консольные фронтенды монтируются в `admin/apps/*` плюс общий слот `admin/public`).
> Для Docker см. монтирование томов nginx в `docker-compose.yml` и `nginx.conf.template` (те же пути, корень в контейнере `/var/www/...`). HarmonyOS поставляется как `.hap` и не идёт через nginx.

Активация сайта:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 5.2 SSL-сертификат

```bash
# Автоматически получить сертификат Let's Encrypt с помощью Certbot
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# Автопродление (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 6. Планировщик задач (Crontab)

```bash
# Отредактировать crontab
crontab -e

# Ежедневный снимок статистики (каждый день в 1:00)
0 1 * * * cd /opt/game-platform/admin && php start.php queue ComputeDailyStats

# Резервное копирование БД (каждый день в 2:00)
0 2 * * * cd /opt/game-platform/admin/database/backup && bash backup.sh

# Автопродление SSL-сертификата
0 3 * * * certbot renew --quiet && systemctl reload nginx

# Обновление кэша рейтинга (каждый час)
0 * * * * cd /opt/game-platform/admin && php start.php queue RefreshLeaderboards
```

---

## 7. Мониторинг

### 7.1 Метрики Prometheus

Админ-панель отдаёт эндпоинт `/metrics` со следующими метриками:

| Метрика | Описание |
|------|------|
| openadmin_http_requests_total | общее число запросов |
| openadmin_active_users | число активных пользователей |
| openadmin_db_connection_status | подключение к БД (0/1) |
| openadmin_redis_connection_status | подключение к Redis (0/1) |
| openadmin_memory_usage_bytes | использование памяти |

### 7.2 Проверка работоспособности

```bash
# Админ-панель
curl -f http://localhost:8789/health || echo "Admin DOWN"

# C-сторонний бизнес
curl -f http://localhost:8792/health || echo "Service DOWN"

# Настраивается в балансировщике нагрузки или системе мониторинга
```

### 7.3 Журналы

```
admin/runtime/logs/
├── stdout.log          # 标准输出
└── webman-<date>.log   # Webman 日志

service/runtime/logs/
├── stdout.log
└── webman-<date>.log
```

---

## 8. Оптимизация производительности

### 8.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # 生产环境关闭文件检查
```

### 8.2 Оптимизация MySQL

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # 设为物理内存的 50-70%
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # 性能优先
max_connections = 200
query_cache_type = 0               # MySQL 8.0 已移除
```

### 8.3 Число worker-процессов

```php
// config/process.php
'count' => cpu_count() * 2,  // 生产环境建议 2-4 倍 CPU 核心数
```

### 8.4 Стратегия кэширования Redis

| Ключ кэша | TTL | Описание |
|--------|-----|------|
| dashboard:data | 300s | данные дашборда |
| i18n:translations | 3600s | тексты переводов |
| leaderboard:{id} | 3600s | рейтинги |
| rate_limit:{ip}:{route} | 60s | окно ограничения частоты |

---

## 9. Укрепление безопасности

### 9.1 Генерация ключей

```bash
# Сгенерировать случайные ключи
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

### 9.2 Брандмауэр

```bash
# Открыть только необходимые порты
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# Внутренние порты не должны быть доступны извне
# 8789 (admin), 8792 (service), 8790/8791 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# Выше указаны порты по умолчанию; если .env в корне или соответствующие .env изменены, ориентируйтесь на фактические значения
# Доступ только через 127.0.0.1
```

### 9.3 Права на файлы

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 10. Поиск и устранение неисправностей

### 10.1 Сервис не запускается

```bash
# Запустить в foreground, чтобы увидеть ошибки
cd /opt/game-platform/admin && php start.php start

# Проверить занятость портов
ss -tlnp | grep -E '8789|8792'

# Проверить логи
tail -f runtime/logs/webman-$(date +%F).log
```

### 10.2 Ошибка подключения к базе данных

```bash
# Проверить подключение
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# Проверить конфигурацию .env
grep DB_ admin/.env
```

### 10.3 Ошибка подключения к Redis

```bash
# Проверить подключение
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# Ожидается PONG
```

### 10.4 Elasticsearch недоступен

```bash
# Проверить подключение
curl http://127.0.0.1:9200

# Поиск автоматически переключается на LIKE-запросы, сервис не прерывается
```

### 10.5 Проблемы производительности

```bash
# Проверить число worker-процессов
php start.php status

# Посмотреть использование памяти
free -h

# Проверить медленные запросы БД
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 11. Руководство по обновлению

```bash
# 1. Получить последний код
cd /opt/game-platform && git pull origin main

# 2. Обновить зависимости
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. Выполнить новые миграции (если есть)
mysql -u game-platform -p game-platform < install/新迁移文件.sql

# 4. Плавный перезапуск (без прерывания сервиса)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
