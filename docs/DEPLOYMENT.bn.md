# 部署文档
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

ইনস্টল উইজার্ড যা সম্পন্ন করে:
- PHP এনভায়রনমেন্ট চেক (সংস্করণ, এক্সটেনশন, ডিরেক্টরি পারমিশন)
- মিলিত SQL এক্সিকিউশন (`install/install.sql`), ৫২টি টেবিল তৈরি ও সিড ডেটা ইমপোর্ট
- সুপার অ্যাডমিন অ্যাকাউন্ট তৈরি (bcrypt এনক্রিপ্ট, super_admin রোলের সাথে সম্পর্কিত)
- অটো JWT/Encryption/Hashids সিক্রেট জেনারেশন
- `admin/.env` ও `service/.env` লেখা
- বারবার ইনস্টল রোধে `install/install.lock` তৈরি

---

## 3. Docker Compose ডিপ্লয়

### 3.1 এক-ক্লিক স্টার্ট

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

### 2.2 সার্ভিস তালিকা

| সার্ভিস | কন্টেইনারের নাম | পোর্ট | বিবরণ |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | রিভার্স প্রক্সি + স্ট্যাটিক ফাইল |
| admin | game-platform-admin | 8787 | অ্যাডমিন প্যানেল API |
| service | game-platform-service | 8788 | C-এন্ড ব্যবসা API |
| leaderboard-ws | game-platform-ws | 8789 | WebSocket লিডারবোর্ড |
| mysql | game-platform-mysql | 3306 | মূল ডেটাবেস |
| redis | game-platform-redis | 6379 | ক্যাশ/রেট লিমিট |
| elasticsearch | game-platform-es | 9200 | ফুল-টেক্সট সার্চ |

### 2.3 ডেটাবেস ইনিশিয়ালাইজেশন

```bash
# 迁移文件会在 MySQL 首次启动时自动执行
# 或手动执行:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 2.4 ডেটা পার্সিস্টেন্স

ডেটা ভলিউম অটো তৈরি হয়, ম্যানুয়াল ম্যানেজমেন্ট প্রয়োজন নেই:

| ভলিউম | পাথ | বিষয়বস্তু |
|----|------|------|
| mysql_data | /var/lib/mysql | ডেটাবেস ফাইল |
| redis_data | /data | Redis পার্সিস্টেন্স |
| es_data | /usr/share/elasticsearch/data | ES ইনডেক্স |

ব্যাকআপ:
```bash
# MySQL 备份
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game-platform | gzip > backup_$(date +%Y%m%d).sql.gz

# 恢复
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform
```

---

## 3. ম্যানুয়াল ডিপ্লয়

### 3.1 PHP এনভায়রনমেন্ট কনফিগ

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# 启用 OPcache（生产环境必须）
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 3.2 নির্ভরতা ইনস্টল

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

### 3.3 .env কনফিগ

**admin/.env গুরুত্বপূর্ণ কনফিগ:**
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

**service/.env গুরুত্বপূর্ণ কনফিগ:**
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
STRIPE_SECRET_KEY=<Stripe 密钥>
NOWPAYMENTS_API_KEY=<NOWPayments API 密钥>
NOWPAYMENTS_IPN_SECRET=<IPN 签名密钥>
NOWPAYMENTS_API_URL=https://api.nowpayments.io  # 默认地址
COINBASE_COMMERCE_API_KEY=<Coinbase Commerce API 密钥>
COINBASE_COMMERCE_WEBHOOK_SECRET=<Coinbase Commerce Webhook 密钥>
SITE_URL=https://your-domain.com  # 支付回调/跳转站点地址
```

### 3.4 সার্ভিস স্টার্ট

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

### 3.5 প্রসেস ম্যানেজমেন্ট (Systemd)

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

## 4. Nginx রিভার্স প্রক্সি

### 4.1 কনফিগ ফাইল

`/etc/nginx/sites-available/game-platform` তৈরি করুন:

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

সাইট সক্রিয়করণ:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 4.2 SSL সার্টিফিকেট

```bash
# 使用 Certbot 自动获取 Let's Encrypt 证书
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# 自动续期 (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 5. শিডিউলড টাস্ক (Crontab)

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

## 6. মনিটরিং

### 6.1 Prometheus মেট্রিক

অ্যাডমিন প্যানেল `/metrics` এন্ডপয়েন্ট প্রকাশ করে, নিচের মেট্রিকগুলো সহ:

| মেট্রিক | বিবরণ |
|------|------|
| openadmin_http_requests_total | মোট রিকোয়েস্ট সংখ্যা |
| openadmin_active_users | সক্রিয় ব্যবহারকারী সংখ্যা |
| openadmin_db_connection_status | ডেটাবেস সংযোগ (0/1) |
| openadmin_redis_connection_status | Redis সংযোগ (0/1) |
| openadmin_memory_usage_bytes | মেমরি ব্যবহার |

### 6.2 হেলথ চেক

```bash
# 管理后台
curl -f http://localhost:8787/health || echo "Admin DOWN"

# C端业务
curl -f http://localhost:8788/health || echo "Service DOWN"

# 可在负载均衡器或监控系统中配置
```

### 6.3 লগ

```
admin/runtime/logs/
├── stdout.log          # 标准输出
└── workerman.log       # Workerman 日志

service/runtime/logs/
├── stdout.log
└── workerman.log
```

---

## 7. পারফরম্যান্স অপ্টিমাইজেশন

### 7.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # 生产环境关闭文件检查
```

### 7.2 MySQL অপ্টিমাইজেশন

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # 设为物理内存的 50-70%
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # 性能优先
max_connections = 200
query_cache_type = 0               # MySQL 8.0 已移除
```

### 7.3 Worker প্রসেস সংখ্যা

```php
// config/process.php
'count' => cpu_count() * 2,  // 生产环境建议 2-4 倍 CPU 核心数
```

### 7.4 Redis ক্যাশ কৌশল

| ক্যাশ কী | TTL | বিবরণ |
|--------|-----|------|
| dashboard:data | 300s | ড্যাশবোর্ড ডেটা |
| i18n:translations | 3600s | অনুবাদ টেক্সট |
| leaderboard:{id} | 3600s | লিডারবোর্ড |
| rate_limit:{ip}:{route} | 60s | রেট লিমিট উইন্ডো |

---

## 8. নিরাপত্তা শক্তিশালীকরণ

### 8.1 সিক্রেট জেনারেশন

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

### 8.2 ফায়ারওয়াল

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

### 8.3 ফাইল পারমিশন

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 9. সমস্যা সমাধান

### 9.1 সার্ভিস স্টার্ট হচ্ছে না

```bash
# 前台运行查看错误
cd /opt/game-platform/admin && php start.php start

# 检查端口占用
ss -tlnp | grep -E '8787|8788'

# 检查日志
tail -f runtime/logs/workerman.log
```

### 9.2 ডেটাবেস সংযোগ ব্যর্থ

```bash
# 测试连接
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# 检查 .env 配置
grep DB_ admin/.env
```

### 9.3 Redis সংযোগ ব্যর্থ

```bash
# 测试连接
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# 预期返回 PONG
```

### 9.4 Elasticsearch অনুপলব্ধ

```bash
# 测试连接
curl http://127.0.0.1:9200

# 搜索功能会自动回退到 LIKE 查询，不会中断服务
```

### 9.5 পারফরম্যান্স সমস্যা

```bash
# 检查 worker 进程数
php start.php status

# 查看内存使用
free -h

# 检查数据库慢查询
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 10. আপগ্রেড গাইড

```bash
# 1. 拉取最新代码
cd /opt/game-platform && git pull origin main

# 2. 更新依赖
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. 执行新迁移（如有）
mysql -u game-platform -p game-platform < install/新迁移文件.sql

# 4. 平滑重启（不中断服务）
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
