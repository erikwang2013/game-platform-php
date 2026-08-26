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

# 3. 브라우저에서 http://<服务器IP>:8888 접속
#    마법사 완료: 환경 검사 → 데이터베이스 설정 → 관리자 계정 → 자동 설치

# 4. 의존성 설치
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. 서비스 시작
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. 보안 정리
rm -rf install/

# 7. 관리 백오피스 접속: http://<服务器IP>:8787
```

설치 마법사가 수행하는 작업:
- PHP 환경 검사 (버전, 확장, 디렉터리 권한)
- 병합 SQL(`install/install.sql`) 실행, 52장 테이블 생성 및 시드 데이터 가져오기
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
php -S 0.0.0.0:8888 -t install/
# 수동 방식: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. 모든 서비스 빌드 및 시작
docker-compose up -d

# 4. 상태 확인
docker-compose ps

# 5. 로그 확인
docker-compose logs -f
```

### 2.2 서비스 목록

| 서비스 | 컨테이너명 | 포트 | 설명 |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | 리버스 프록시 + 정적 파일 |
| admin | game-platform-admin | 8787 | 관리 백오피스 API |
| service | game-platform-service | 8788 | C단 비즈니스 API |
| leaderboard-ws | game-platform-ws | 8789 | WebSocket 리더보드 |
| mysql | game-platform-mysql | 3306 | 메인 데이터베이스 |
| redis | game-platform-redis | 6379 | 캐시/레이트 리밋 |
| elasticsearch | game-platform-es | 9200 | 전문 검색 |

### 2.3 데이터베이스 초기화

```bash
# 마이그레이션 파일은 MySQL 최초 기동 시 자동 실행됩니다
# 또는 수동 실행:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < admin/database/migrations/2026_05_16_000000_init_tables.sql
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < admin/database/migrations/2026_05_22_000003_platform_tables.sql
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < admin/database/migrations/2026_05_22_000004_i18n_tables.sql
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < admin/database/migrations/2026_05_22_000005_standard_tables.sql
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < admin/database/migrations/2026_05_22_000006_complete_tables.sql
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < admin/database/migrations/2026_05_22_000007_production_tables.sql
```

### 2.4 데이터 영속화

데이터 볼륨은 자동 생성되며 수동 관리가 필요 없습니다:

| 볼륨 | 경로 | 내용 |
|----|------|------|
| mysql_data | /var/lib/mysql | 데이터베이스 파일 |
| redis_data | /data | Redis 영속화 |
| es_data | /usr/share/elasticsearch/data | ES 인덱스 |

백업:
```bash
# MySQL 백업
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game_platform | gzip > backup_$(date +%Y%m%d).sql.gz

# 복원
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform
```

---

## 3. 수동 배포

### 3.1 PHP 환경 설정

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# OPcache 활성화 (프로덕션 필수)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 3.2 의존성 설치

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

### 3.3 .env 설정

**admin/.env 핵심 설정:**
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

**service/.env 핵심 설정:**
```ini
# admin과 동일한 데이터베이스, Redis, ES 설정
SNOWFLAKE_WORKER_ID=2  # admin과 반드시 달라야 함

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<从Google Cloud Console获取>
OAUTH_GOOGLE_CLIENT_SECRET=<密钥>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# 결제 Webhook
STRIPE_WEBHOOK_SECRET=<从Stripe Dashboard获取>
PAYPAL_WEBHOOK_ID=<从PayPal Developer获取>
```

### 3.4 서비스 시작

```bash
# 관리 백오피스 (포트 8787)
cd /opt/game-platform/admin
php start.php start -d

# C단 비즈니스 (포트 8788)
cd /opt/game-platform/service
php start.php start -d

# 검증
curl http://localhost:8787/health
curl http://localhost:8788/health
```

### 3.5 프로세스 관리 (Systemd)

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

## 4. Nginx 리버스 프록시

### 4.1 설정 파일

`/etc/nginx/sites-available/game-platform` 생성:

```nginx
server {
    listen 80;
    server_name your-domain.com;

    # 관리 백오피스 API
    location /admin/ {
        proxy_pass http://127.0.0.1:8787;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # C단 API
    location /api/ {
        proxy_pass http://127.0.0.1:8788;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket 리더보드
    location /ws/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # 헬스 체크
    location /health {
        proxy_pass http://127.0.0.1:8787;
    }

    # Prometheus 지표
    location /metrics {
        proxy_pass http://127.0.0.1:8787;
    }

    # 관리 백오피스 프론트엔드
    location /admin-panel {
        alias /opt/game-platform/admin/apps/flutter/build/web;
        try_files $uri $uri/ /admin-panel/index.html;
    }

    # C단 플랫폼 프론트엔드
    location / {
        root /opt/game-platform/apps/flutter/platform/build/web;
        try_files $uri $uri/ /index.html;
    }
}
```

사이트 활성화:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 4.2 SSL 인증서

```bash
# Certbot으로 Let's Encrypt 인증서 자동 발급
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# 자동 갱신 (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 5. 예약 작업 (Crontab)

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

## 6. 모니터링

### 6.1 Prometheus 지표

관리 백오피스가 `/metrics` 엔드포인트를 노출하며 다음 지표를 포함합니다:

| 지표 | 설명 |
|------|------|
| openadmin_http_requests_total | 요청 총수 |
| openadmin_active_users | 활성 사용자 수 |
| openadmin_db_connection_status | 데이터베이스 연결 (0/1) |
| openadmin_redis_connection_status | Redis 연결 (0/1) |
| openadmin_memory_usage_bytes | 메모리 사용량 |

### 6.2 헬스 체크

```bash
# 관리 백오피스
curl -f http://localhost:8787/health || echo "Admin DOWN"

# C단 비즈니스
curl -f http://localhost:8788/health || echo "Service DOWN"

# 로드 밸런서나 모니터링 시스템에서 설정 가능
```

### 6.3 로그

```
admin/runtime/logs/
├── stdout.log          # 표준 출력
└── workerman.log       # Workerman 로그

service/runtime/logs/
├── stdout.log
└── workerman.log
```

---

## 7. 성능 최적화

### 7.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # 프로덕션에서는 파일 검사 끄기
```

### 7.2 MySQL 최적화

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # 물리 메모리의 50-70%로 설정
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # 성능 우선
max_connections = 200
query_cache_type = 0               # MySQL 8.0에서 제거됨
```

### 7.3 Worker 프로세스 수

```php
// config/process.php
'count' => cpu_count() * 2,  // 프로덕션에서는 CPU 코어 수의 2-4배 권장
```

### 7.4 Redis 캐시 전략

| 캐시 키 | TTL | 설명 |
|--------|-----|------|
| dashboard:data | 300s | 대시보드 데이터 |
| i18n:translations | 3600s | 번역 텍스트 |
| leaderboard:{id} | 3600s | 리더보드 |
| rate_limit:{ip}:{route} | 60s | 레이트 리밋 창 |

---

## 8. 보안 강화

### 8.1 키 생성

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

### 8.2 방화벽

```bash
# 필요한 포트만 개방
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# 내부 포트는 노출하면 안 됨
# 8787 (admin), 8788 (service), 8789 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# 127.0.0.1로만 접근
```

### 8.3 파일 권한

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 9. 장애 진단

### 9.1 서비스가 시작되지 않음

```bash
# 포그라운드 실행으로 오류 확인
cd /opt/game-platform/admin && php start.php start

# 포트 점유 확인
ss -tlnp | grep -E '8787|8788'

# 로그 확인
tail -f runtime/logs/workerman.log
```

### 9.2 데이터베이스 연결 실패

```bash
# 연결 테스트
mysql -h 127.0.0.1 -u game_platform -p game_platform -e "SELECT 1"

# .env 설정 확인
grep DB_ admin/.env
```

### 9.3 Redis 연결 실패

```bash
# 연결 테스트
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# PONG이 반환되어야 함
```

### 9.4 Elasticsearch 사용 불가

```bash
# 연결 테스트
curl http://127.0.0.1:9200

# 검색 기능은 LIKE 쿼리로 자동 폴백되며 서비스는 중단되지 않음
```

### 9.5 성능 문제

```bash
# worker 프로세스 수 확인
php start.php status

# 메모리 사용량 확인
free -h

# 데이터베이스 슬로우 쿼리 확인
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 10. 업그레이드 가이드

```bash
# 1. 최신 코드 가져오기
cd /opt/game-platform && git pull origin main

# 2. 의존성 업데이트
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. 새 마이그레이션 실행 (있을 경우)
mysql -u game_platform -p game_platform < admin/database/migrations/新迁移文件.sql

# 4. 무중단 재시작 (서비스 중단 없음)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
