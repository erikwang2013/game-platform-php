# 部署文档
<!-- lang-nav -->

Languages: **中文** · [English](DEPLOYMENT.en.md) · [한국어](DEPLOYMENT.ko.md) · [Русский](DEPLOYMENT.ru.md) · [Deutsch](DEPLOYMENT.de.md) · [Français](DEPLOYMENT.fr.md) · [Español](DEPLOYMENT.es.md) · [Português](DEPLOYMENT.pt.md) · [हिन्दी](DEPLOYMENT.hi.md) · [العربية](DEPLOYMENT.ar.md) · [বাংলা](DEPLOYMENT.bn.md) · [Bahasa Indonesia](DEPLOYMENT.id.md) · [日本語](DEPLOYMENT.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 环境要求

| 组件 | 最低版本 | 推荐配置 |
|------|---------|---------|
| OS | Linux (Ubuntu 20.04+ / Debian 11+ / CentOS 8+) | Ubuntu 22.04 LTS |
| PHP | 8.3+ | 8.3+ (CLI, OPcache enabled) |
| PHP 扩展 | pdo, pdo_mysql, pcntl, redis, gd, mbstring, xml | 全部 |
| MySQL | 8.0+ | 8.0+ 主从复制 |
| Redis | 6.0+ | 7.x 哨兵模式 |
| Elasticsearch | 7.x+ | 8.x 单节点 |
| Nginx | 1.20+ | 反向代理 + gzip + SSL |
| Composer | 2.x | 最新稳定版 |
| Flutter SDK | 3.x+ | 最新稳定版 (仅构建前端时需要) |

---

## 2. 一键安装向导（推荐新部署）

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

# 5. 启动服务（默认端口 admin 8789 / service 8792，可在各自 .env 的 APP_PORT 修改）
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. 安全清理
rm -rf install/

# 7. 访问管理后台: http://<服务器IP>:8789（默认端口）
```

安装向导完成的操作：
- PHP 环境检查（版本、扩展、目录权限）
- 执行合并 SQL（`install/install.sql`），创建 78 张表并导入种子数据
- 创建超级管理员账户（bcrypt 加密，关联 super_admin 角色）
- 自动生成 JWT/Encryption/Hashids 密钥
- 写入 `admin/.env` 和 `service/.env`
- 生成 `install/install.lock` 防止重复安装

---

## 3. Docker Compose 部署

### 3.1 一键启动

```bash
# 1. 克隆项目
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. 使用一键安装向导配置环境（或手动配置 .env 文件）
#    端口等 Docker 参数在根目录 .env（模板 .env.example）: cp .env.example .env
php -S 0.0.0.0:8888 -t install/
# 手动方式: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. 构建并启动所有服务
docker-compose up -d

# 4. 查看状态
docker-compose ps

# 5. 查看日志
docker-compose logs -f
```

### 3.2 服务清单

| 服务 | 容器名 | 端口 | 说明 |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | 反向代理 + 静态文件 |
| admin | game-platform-admin | 8789 | 管理后台 API |
| service | game-platform-service | 8792 | C端业务 API |
| leaderboard-ws | game-platform-ws | 8790, 8791 | WebSocket 排行榜/聊天 |
| mysql | game-platform-mysql | 3306 | 主数据库 |
| redis | game-platform-redis | 6379 | 缓存/限流 |
| elasticsearch | game-platform-es | 9200 | 全文检索 |

> **端口配置**：上表为默认端口，全部可在项目根目录 `.env` 中修改（模板 `.env.example`，`cp .env.example .env` 后编辑）：
> `NGINX_HTTP_PORT`、`NGINX_HTTPS_PORT`、`ADMIN_PORT`、`SERVICE_PORT`、`LEADERBOARD_WS_PORT`、`CHAT_WS_PORT`、`MYSQL_PORT`、`REDIS_PORT`、`ES_PORT`。
> `nginx.conf.template` 的 upstream 端口由官方镜像 envsubst 自动渲染，无需手工改 Nginx 配置。
> 对外展示地址（`APP_URL` / `SITE_URL`）在 Docker 部署下默认自动跟随 `ADMIN_PORT` / `SERVICE_PORT`（形如 `http://localhost:端口号`）；自定义域名或 HTTPS 时在根 `.env` 设置 `APP_URL` / `SITE_URL`（会覆盖 `admin/.env`、`service/.env` 中的同名项）。裸机（手动）部署改端口时仍需自行同步地址。

### 3.3 数据库初始化

```bash
# 迁移文件会在 MySQL 首次启动时自动执行
# 或手动执行:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 3.4 数据持久化

数据卷自动创建，无需手动管理：

| 卷 | 路径 | 内容 |
|----|------|------|
| mysql_data | /var/lib/mysql | 数据库文件 |
| redis_data | /data | Redis 持久化 |
| es_data | /usr/share/elasticsearch/data | ES 索引 |

备份：
```bash
# MySQL 备份
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game-platform | gzip > backup_$(date +%Y%m%d).sql.gz

# 恢复
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform
```

---

## 4. 手动部署

### 4.1 PHP 环境配置

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# 启用 OPcache（生产环境必须）
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 4.2 安装依赖

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

### 4.3 配置 .env

**admin/.env 关键配置：**
```ini
APP_ENV=production
APP_DEBUG=false
APP_PORT=8789  # webman HTTP 监听端口（与 APP_URL 保持一致）
APP_URL=http://localhost:8789  # 对外访问地址（API 文档 baseUrl 等）

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

**service/.env 关键配置：**
```ini
# 与 admin 相同的数据库、Redis、ES 配置
APP_PORT=8792  # webman HTTP 监听端口
LEADERBOARD_WS_PORT=8790  # 排行榜 WebSocket 端口（与前端连接地址一致）
CHAT_WS_PORT=8791  # 聊天 WebSocket 端口
SNOWFLAKE_WORKER_ID=2  # 必须与 admin 不同

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<从Google Cloud Console获取>
OAUTH_GOOGLE_CLIENT_SECRET=<密钥>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# 支付 Webhook（toss / mpesa / paystack 接入中）
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
# 接入中
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

### 4.4 启动服务

```bash
# 管理后台 (默认端口 8789，admin/.env 的 APP_PORT 可改)
cd /opt/game-platform/admin
php start.php start -d

# C端业务 (默认端口 8792，service/.env 的 APP_PORT 可改)
cd /opt/game-platform/service
php start.php start -d

# 验证
curl http://localhost:8789/health
curl http://localhost:8792/health
```

### 4.5 进程管理（Systemd）

创建 `/etc/systemd/system/game-platform-admin.service`：

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

同样创建 `game-platform-service.service`（修改 WorkingDirectory 为 `/opt/game-platform/service`）。

```bash
systemctl daemon-reload
systemctl enable --now game-platform-admin game-platform-service
```

---

## 5. Nginx 反向代理

### 5.1 配置文件

创建 `/etc/nginx/sites-available/game-platform`：

```nginx
# 端口为默认值（admin 8789 / service 8792 / ws 8790）；如已修改 .env，请同步调整
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

    # WebSocket 排行榜（默认端口 8790，与 service/.env 的 LEADERBOARD_WS_PORT 一致）
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

> 手动部署时上面这些目录由你直接放构建产物（C 端四棵树：`apps/flutter/platform`、`apps/react`、`apps/angular`、`apps/harmonyos`；控制台前端统一挂在 `admin/apps/*`，另有通用投放位 `admin/public`）。
> Docker 部署见 `docker-compose.yml` 的 nginx 卷挂载与 `nginx.conf.template`（同一套路径，容器内根为 `/var/www/...`）。HarmonyOS 端分发 `.hap`，不经 nginx。

启用站点：
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 5.2 SSL 证书

```bash
# 使用 Certbot 自动获取 Let's Encrypt 证书
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# 自动续期 (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 6. 定时任务 (Crontab)

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

## 7. 监控

### 7.1 Prometheus 指标

管理后台暴露 `/metrics` 端点，包含以下指标：

| 指标 | 说明 |
|------|------|
| openadmin_http_requests_total | 请求总数 |
| openadmin_active_users | 活跃用户数 |
| openadmin_db_connection_status | 数据库连接 (0/1) |
| openadmin_redis_connection_status | Redis 连接 (0/1) |
| openadmin_memory_usage_bytes | 内存使用量 |

### 7.2 健康检查

```bash
# 管理后台
curl -f http://localhost:8789/health || echo "Admin DOWN"

# C端业务
curl -f http://localhost:8792/health || echo "Service DOWN"

# 可在负载均衡器或监控系统中配置
```

### 7.3 日志

```
admin/runtime/logs/
├── stdout.log          # 标准输出
└── webman-<date>.log   # Webman 日志

service/runtime/logs/
├── stdout.log
└── webman-<date>.log
```

---

## 8. 性能优化

### 8.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # 生产环境关闭文件检查
```

### 8.2 MySQL 优化

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # 设为物理内存的 50-70%
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # 性能优先
max_connections = 200
query_cache_type = 0               # MySQL 8.0 已移除
```

### 8.3 Worker 进程数

```php
// config/process.php
'count' => cpu_count() * 2,  // 生产环境建议 2-4 倍 CPU 核心数
```

### 8.4 Redis 缓存策略

| 缓存键 | TTL | 说明 |
|--------|-----|------|
| dashboard:data | 300s | 仪表盘数据 |
| i18n:translations | 3600s | 翻译文本 |
| leaderboard:{id} | 3600s | 排行榜 |
| rate_limit:{ip}:{route} | 60s | 限流窗口 |

---

## 9. 安全加固

### 9.1 密钥生成

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

### 9.2 防火墙

```bash
# 仅开放必要端口
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# 内部端口不应暴露
# 8789 (admin), 8792 (service), 8790/8791 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# 以上为默认端口；如修改过根 .env / 各自 .env，以实际配置为准
# 仅通过 127.0.0.1 访问
```

### 9.3 文件权限

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 10. 故障排查

### 10.1 服务无法启动

```bash
# 前台运行查看错误
cd /opt/game-platform/admin && php start.php start

# 检查端口占用
ss -tlnp | grep -E '8789|8792'

# 检查日志
tail -f runtime/logs/webman-$(date +%F).log
```

### 10.2 数据库连接失败

```bash
# 测试连接
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# 检查 .env 配置
grep DB_ admin/.env
```

### 10.3 Redis 连接失败

```bash
# 测试连接
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# 预期返回 PONG
```

### 10.4 Elasticsearch 不可用

```bash
# 测试连接
curl http://127.0.0.1:9200

# 搜索功能会自动回退到 LIKE 查询，不会中断服务
```

### 10.5 性能问题

```bash
# 检查 worker 进程数
php start.php status

# 查看内存使用
free -h

# 检查数据库慢查询
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 11. 升级指南

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
