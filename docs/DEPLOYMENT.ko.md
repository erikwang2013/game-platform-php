# 배포 문서
<!-- lang-nav -->

Languages: [中文](DEPLOYMENT.md) · [English](DEPLOYMENT.en.md) · **한국어** · [Русский](DEPLOYMENT.ru.md) · [Deutsch](DEPLOYMENT.de.md) · [Français](DEPLOYMENT.fr.md) · [Español](DEPLOYMENT.es.md) · [Português](DEPLOYMENT.pt.md) · [हिन्दी](DEPLOYMENT.hi.md) · [العربية](DEPLOYMENT.ar.md) · [বাংলা](DEPLOYMENT.bn.md) · [Bahasa Indonesia](DEPLOYMENT.id.md) · [日本語](DEPLOYMENT.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 환경 요구 사항

| 컴포넌트 | 최소 버전 | 권장 구성 |
|------|---------|---------|
| OS | Linux (Ubuntu 20.04+ / Debian 11+ / CentOS 8+) | Ubuntu 22.04 LTS |
| PHP | 8.3+ | 8.3+ (CLI, OPcache enabled) |
| PHP 확장 | pdo, pdo_mysql, pcntl, redis, gd, mbstring, xml | 전체 |
| MySQL | 8.0+ | 8.0+ 주종 복제 |
| Redis | 6.0+ | 7.x 센티널 모드 |
| Elasticsearch | 7.x+ | 8.x 단일 노드 |
| Nginx | 1.20+ | 리버스 프록시 + gzip + SSL |
| Composer | 2.x | 최신 안정 버전 |
| Flutter SDK | 3.x+ | 최신 안정 버전 (프론트엔드 빌드 시에만 필요) |

---

## 2. 원클릭 설치 마법사 (신규 배포 권장)

```bash
# 1. 프로젝트 클론
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. 설치 마법사 시작
php -S 0.0.0.0:8888 -t install/

# 3. 브라우저에서 http://<server-IP>:8888 접속
#    마법사 완료: 환경 검사 → 데이터베이스 설정 → 관리자 계정 → 자동 설치

# 4. 의존성 설치
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. 서비스 시작 (기본 포트 admin 8789 / service 8792, 각 .env의 APP_PORT에서 변경 가능)
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. 보안 정리
rm -rf install/

# 7. 관리 백오피스 접속: http://<server-IP>:8789 (기본 포트)
```

설치 마법사가 수행하는 작업:
- PHP 환경 검사 (버전, 확장, 디렉터리 권한)
- 병합 SQL(`install/install.sql`) 실행, 78장 테이블 생성 및 시드 데이터 가져오기
- 슈퍼 관리자 계정 생성 (bcrypt 암호화, super_admin 역할 연결)
- JWT/Encryption/Hashids 키 자동 생성
- `admin/.env`와 `service/.env` 작성
- `install/install.lock` 생성으로 중복 설치 방지

---

## 3. Docker Compose 배포

### 3.1 원클릭 시작

```bash
# 1. 프로젝트 클론
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. 원클릭 설치 마법사로 환경 설정 (또는 .env 파일 수동 설정)
#    포트 등 Docker 파라미터는 루트 디렉터리 .env에 있습니다 (템플릿 .env.example): cp .env.example .env
php -S 0.0.0.0:8888 -t install/
# 수동 방식: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. 모든 서비스 빌드 및 시작
docker-compose up -d

# 4. 상태 확인
docker-compose ps

# 5. 로그 확인
docker-compose logs -f
```

### 3.2 서비스 목록

| 서비스 | 컨테이너명 | 포트 | 설명 |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | 리버스 프록시 + 정적 파일 |
| admin | game-platform-admin | 8789 | 관리 백오피스 API |
| service | game-platform-service | 8792 | C단 비즈니스 API |
| leaderboard-ws | game-platform-ws | 8790, 8791 | WebSocket 리더보드/채팅 |
| mysql | game-platform-mysql | 3306 | 메인 데이터베이스 |
| redis | game-platform-redis | 6379 | 캐시/레이트 리밋 |
| elasticsearch | game-platform-es | 9200 | 전문 검색 |

> **포트 구성**: 위 표는 기본 포트이며, 모두 프로젝트 루트의 `.env`에서 변경할 수 있습니다 (템플릿 `.env.example`, `cp .env.example .env` 후 편집):
> `NGINX_HTTP_PORT`, `NGINX_HTTPS_PORT`, `ADMIN_PORT`, `SERVICE_PORT`, `LEADERBOARD_WS_PORT`, `CHAT_WS_PORT`, `MYSQL_PORT`, `REDIS_PORT`, `ES_PORT`.
> `nginx.conf.template`의 upstream 포트는 공식 이미지의 envsubst로 자동 렌더링되므로 Nginx 설정을 수동으로 수정할 필요가 없습니다.
> Docker 배포에서는 외부 접속 주소(`APP_URL` / `SITE_URL`)가 기본적으로 `ADMIN_PORT` / `SERVICE_PORT`를 자동으로 따릅니다(`http://localhost:포트` 형식). 사용자 도메인이나 HTTPS를 사용하는 경우 루트 `.env`에서 `APP_URL` / `SITE_URL`을 설정하세요(`admin/.env`·`service/.env`의 동일 항목을 덮어씁니다). 베어메탈(수동) 배포에서 포트를 변경할 때는 주소도 직접 수정해야 합니다.

### 3.3 데이터베이스 초기화

```bash
# 마이그레이션 파일은 MySQL 최초 기동 시 자동 실행됩니다
# 또는 수동 실행:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 3.4 데이터 영속화

데이터 볼륨은 자동 생성되며 수동 관리가 필요 없습니다:

| 볼륨 | 경로 | 내용 |
|----|------|------|
| mysql_data | /var/lib/mysql | 데이터베이스 파일 |
| redis_data | /data | Redis 영속화 |
| es_data | /usr/share/elasticsearch/data | ES 인덱스 |

백업:
```bash
# MySQL 백업
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game-platform | gzip > backup_$(date +%Y%m%d).sql.gz

# 복원
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform
```

---

## 4. 수동 배포

### 4.1 PHP 환경 설정

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# OPcache 활성화 (프로덕션 필수)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 4.2 의존성 설치

```bash
cd /opt/game-platform

# 관리 백오피스
cd admin
cp .env.example .env
# .env 편집: 데이터베이스 연결, JWT_SECRET, HASHIDS_SALT 등
composer install --no-dev --optimize-autoloader

# C단 비즈니스
cd ../service
cp .env.example .env
# .env 편집 (주의: SNOWFLAKE_WORKER_ID=2)
composer install --no-dev --optimize-autoloader
```

### 4.3 .env 설정

**admin/.env 핵심 설정:**
```ini
APP_ENV=production
APP_DEBUG=false
APP_PORT=8789  # webman HTTP 리슨 포트 (APP_URL과 일치)
APP_URL=http://localhost:8789  # 외부 접속 주소 (API 문서 baseUrl 등)

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

**service/.env 핵심 설정:**
```ini
# admin과 동일한 데이터베이스, Redis, ES 설정
APP_PORT=8792
LEADERBOARD_WS_PORT=8790  # 리더보드 WebSocket
CHAT_WS_PORT=8791  # 채팅 WebSocket
SNOWFLAKE_WORKER_ID=2  # admin과 반드시 달라야 함

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<Google Cloud Console에서 획득>
OAUTH_GOOGLE_CLIENT_SECRET=<비밀 키>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# 결제 Webhook
STRIPE_SECRET_KEY=<Stripe 비밀 키>
STRIPE_WEBHOOK_SECRET=<Stripe Dashboard에서 획득>
PAYPAL_WEBHOOK_ID=<PayPal Developer에서 획득>
PAYPAL_VERIFY_URL=<PayPal Webhook 검증 주소>
PAYPAL_CLIENT_ID=<PayPal Client ID>
PAYPAL_CLIENT_SECRET=<PayPal Client Secret>
PAYPAL_MODE=sandbox  # sandbox / live
NOWPAYMENTS_API_KEY=<NOWPayments API 키>
NOWPAYMENTS_IPN_SECRET=<IPN 서명 키>
NOWPAYMENTS_API_URL=https://api.nowpayments.io  # 기본 주소
COINBASE_COMMERCE_API_KEY=<Coinbase Commerce API 키>
COINBASE_COMMERCE_WEBHOOK_SECRET=<Coinbase Commerce Webhook 키>
SKRILL_API_URL=https://pay.skrill.com
SKRILL_API_KEY=<Skrill API 키>
SKRILL_MERCHANT_ID=<Skrill 가맹점 번호>
SKRILL_SECRET_WORD=<콜백 서명 검증 키 md5sig>
NETELLER_API_URL=https://api.neteller.com
NETELLER_CLIENT_ID=<Neteller Client ID>
NETELLER_CLIENT_SECRET=<Neteller Client Secret>
NETELLER_SECRET=<콜백 서명 검증 키>
PAYSAFECARD_API_URL=https://api.paysafecard.com
PAYSAFECARD_API_KEY=<Paysafecard API 키>
PAYSAFECARD_SECRET=<콜백 서명 검증 키 X-Signature HMAC-SHA256>
PAYTM_MID=<Paytm 가맹점 번호>
PAYTM_KEY=<Paytm 키>
PAYTM_API_URL=https://securegw.paytm.in
PAYTM_WEBSITE=DEFAULT  # 운영 DEFAULT / 스테이징 WEBSTAGING
MERCADOPAGO_CLIENT_ID=<Mercado Pago Client ID>
MERCADOPAGO_CLIENT_SECRET=<Mercado Pago Client Secret>
MERCADOPAGO_WEBHOOK_SECRET=<Webhook 서명 검증 키 X-Signature>
MERCADOPAGO_API_URL=https://api.mercadopago.com
ASTROPAY_LOGIN=<AstroPay 로그인 이름>
ASTROPAY_API_KEY=<AstroPay API 키>
ASTROPAY_SECRET=<콜백 서명 검증 키 MD5>
ASTROPAY_API_URL=https://api.astropaycard.com
PAYPAY_CLIENT_ID=<PayPay Client ID>
PAYPAY_CLIENT_SECRET=<PayPay Client Secret>
PAYPAY_SIGNING_KEY=<Webhook 서명 검증 키 PayPay-Signature>
PAYPAY_API_URL=https://api.paypay.ne.jp
KAKAOPAY_ADMIN_KEY=<KakaoPay Admin Key>
KAKAOPAY_CID=<KakaoPay 가맹점 CID>
KAKAOPAY_APPROVAL_URL=<결제 후 승인 리다이렉트 URL>
KAKAOPAY_API_URL=https://kapi.kakao.com
PAYMONGO_API_KEY=<PayMongo API 키>
PAYMONGO_WEBHOOK_SECRET=<Webhook 서명 검증 키 Paymongo-Signature>
PAYMONGO_API_URL=https://api.paymongo.com/v1
# M-Pesa / Paystack / Toss
MPESA_CONSUMER_KEY=<M-Pesa Consumer Key>
MPESA_CONSUMER_SECRET=<M-Pesa Consumer Secret>
MPESA_PASSKEY=<M-Pesa STK Push Passkey>
MPESA_SHORTCODE=<M-Pesa 단축 코드>
MPESA_API_URL=https://api.safaricom.co.ke
PAYSTACK_SECRET_KEY=<Paystack Secret Key>
PAYSTACK_API_URL=https://api.paystack.co
TOSS_SECRET_KEY=<Toss Secret Key>
TOSS_API_URL=https://api.tosspayments.com
SITE_URL=https://your-domain.com  # 결제 콜백/리다이렉트 사이트 주소
```

### 4.4 서비스 시작

```bash
# 관리 백오피스 (기본 포트 8789, admin/.env의 APP_PORT로 변경 가능)
cd /opt/game-platform/admin
php start.php start -d

# C단 비즈니스 (기본 포트 8792, service/.env의 APP_PORT로 변경 가능)
cd /opt/game-platform/service
php start.php start -d

# 검증
curl http://localhost:8789/health
curl http://localhost:8792/health
```

### 4.5 프로세스 관리 (Systemd)

`/etc/systemd/system/game-platform-admin.service` 생성:

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

마찬가지로 `game-platform-service.service` 생성 (WorkingDirectory를 `/opt/game-platform/service`로 수정).

```bash
systemctl daemon-reload
systemctl enable --now game-platform-admin game-platform-service
```

---

## 5. Nginx 리버스 프록시

### 5.1 설정 파일

`/etc/nginx/sites-available/game-platform` 생성:

```nginx
# 포트는 기본값입니다 (admin 8789 / service 8792 / ws 8790). .env를 수정했다면 함께 조정하세요
server {
    listen 80;
    server_name your-domain.com;

    # nginx 自身发出的 301（如目录补斜杠 /admin-panel → /admin-panel/）改用相对
    # Location，客户端按当前 host:port 解析；默认绝对跳转会退回 listen 端口，
    # 非 80 端口部署（如 8080）时会跳错端口。
    absolute_redirect off;

    # 관리 백오피스 API
    location /admin/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # C단 API
    location /api/ {
        proxy_pass http://127.0.0.1:8792;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket 리더보드 (기본 포트 8790, service/.env의 LEADERBOARD_WS_PORT와 일치)
    location /ws/ {
        proxy_pass http://127.0.0.1:8790;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # 헬스 체크
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

> 수동 배포 시에는 이 디렉터리에 빌드 산출물을 직접 넣습니다 (C단 4개 트리: `apps/flutter/platform`, `apps/react`, `apps/angular`, `apps/harmonyos`; 관리 콘솔 프런트엔드는 `admin/apps/*`와 범용 배치 위치 `admin/public`에 모두 마운트됩니다).
> Docker 배포는 `docker-compose.yml`의 nginx 볼륨 마운트와 `nginx.conf.template`을 참고하세요 (동일한 경로 구성, 컨테이너 내부 루트는 `/var/www/...`). HarmonyOS는 `.hap`로 배포되며 nginx를 거치지 않습니다.

사이트 활성화:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 5.2 SSL 인증서

```bash
# Certbot으로 Let's Encrypt 인증서 자동 발급
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# 자동 갱신 (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 6. 예약 작업 (Crontab)

```bash
# crontab 편집
crontab -e

# 일별 통계 스냅샷 (매일 새벽 1:00)
0 1 * * * cd /opt/game-platform/admin && php start.php queue ComputeDailyStats

# 데이터베이스 백업 (매일 새벽 2:00)
0 2 * * * cd /opt/game-platform/admin/database/backup && bash backup.sh

# SSL 인증서 자동 갱신
0 3 * * * certbot renew --quiet && systemctl reload nginx

# 리더보드 캐시 갱신 (매시간)
0 * * * * cd /opt/game-platform/admin && php start.php queue RefreshLeaderboards
```

---

## 7. 모니터링

### 7.1 Prometheus 지표

관리 백오피스가 `/metrics` 엔드포인트를 노출하며 다음 지표를 포함합니다:

| 지표 | 설명 |
|------|------|
| openadmin_http_requests_total | 요청 총수 |
| openadmin_active_users | 활성 사용자 수 |
| openadmin_db_connection_status | 데이터베이스 연결 (0/1) |
| openadmin_redis_connection_status | Redis 연결 (0/1) |
| openadmin_memory_usage_bytes | 메모리 사용량 |

### 7.2 헬스 체크

```bash
# 관리 백오피스
curl -f http://localhost:8789/health || echo "Admin DOWN"

# C단 비즈니스
curl -f http://localhost:8792/health || echo "Service DOWN"

# 로드 밸런서나 모니터링 시스템에서 설정 가능
```

### 7.3 로그

```
admin/runtime/logs/
├── stdout.log          # 표준 출력
└── webman-<date>.log   # Webman 로그

service/runtime/logs/
├── stdout.log
└── webman-<date>.log
```

---

## 8. 성능 최적화

### 8.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # 프로덕션에서는 파일 검사 끄기
```

### 8.2 MySQL 최적화

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # 물리 메모리의 50-70%로 설정
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # 성능 우선
max_connections = 200
query_cache_type = 0               # MySQL 8.0에서 제거됨
```

### 8.3 Worker 프로세스 수

```php
// config/process.php
'count' => cpu_count() * 2,  // 프로덕션에서는 CPU 코어 수의 2-4배 권장
```

### 8.4 Redis 캐시 전략

| 캐시 키 | TTL | 설명 |
|--------|-----|------|
| dashboard:data | 300s | 대시보드 데이터 |
| i18n:translations | 3600s | 번역 텍스트 |
| leaderboard:{id} | 3600s | 리더보드 |
| rate_limit:{ip}:{route} | 60s | 레이트 리밋 창 |

---

## 9. 보안 강화

### 9.1 키 생성

```bash
# 랜덤 키 생성
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

### 9.2 방화벽

```bash
# 필요한 포트만 개방
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# 내부 포트는 노출하면 안 됨
# 8789 (admin), 8792 (service), 8790/8791 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# 위는 기본 포트입니다. 루트 .env / 각 .env를 수정했다면 실제 설정을 기준으로 하세요
# 127.0.0.1로만 접근
```

### 9.3 파일 권한

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 10. 장애 진단

### 10.1 서비스가 시작되지 않음

```bash
# 포그라운드 실행으로 오류 확인
cd /opt/game-platform/admin && php start.php start

# 포트 점유 확인
ss -tlnp | grep -E '8789|8792'

# 로그 확인
tail -f runtime/logs/webman-$(date +%F).log
```

### 10.2 데이터베이스 연결 실패

```bash
# 연결 테스트
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# .env 설정 확인
grep DB_ admin/.env
```

### 10.3 Redis 연결 실패

```bash
# 연결 테스트
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# PONG이 반환되어야 함
```

### 10.4 Elasticsearch 사용 불가

```bash
# 연결 테스트
curl http://127.0.0.1:9200

# 검색 기능은 LIKE 쿼리로 자동 폴백되며 서비스는 중단되지 않음
```

### 10.5 성능 문제

```bash
# worker 프로세스 수 확인
php start.php status

# 메모리 사용량 확인
free -h

# 데이터베이스 슬로우 쿼리 확인
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 11. 업그레이드 가이드

```bash
# 1. 최신 코드 가져오기
cd /opt/game-platform && git pull origin main

# 2. 의존성 업데이트
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. 새 마이그레이션 실행 (있을 경우)
mysql -u game-platform -p game-platform < install/新迁移文件.sql

# 4. 무중단 재시작 (서비스 중단 없음)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
