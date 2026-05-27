# ClickHouse 安装与配置指南

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 环境要求

- ClickHouse 24.x+
- PHP 8.3+，`erikwang2013/clickhouse-php` (v1.x) 已安装

## 2. 安装 ClickHouse

### Docker（推荐）

```bash
docker run -d \
  --name clickhouse \
  -p 8123:8123 -p 9000:9000 \
  -e CLICKHOUSE_USER=default \
  -e CLICKHOUSE_PASSWORD= \
  -e CLICKHOUSE_DB=default \
  clickhouse/clickhouse-server:24-alpine
```

Docker Compose 一键启动：

```bash
cd admin && docker-compose up -d clickhouse
```

### 手动安装 (Ubuntu/Debian)

```bash
sudo apt-key adv --keyserver keyserver.ubuntu.com --recv 8919F6BD2B48D754
echo "deb https://packages.clickhouse.com/deb stable main" | sudo tee /etc/apt/sources.list.d/clickhouse.list
sudo apt update && sudo apt install clickhouse-server clickhouse-client
sudo service clickhouse-server start
```

### 验证

```bash
clickhouse-client -q "SELECT version()"
curl 'http://localhost:8123/?query=SELECT+1'
```

## 3. 配置 .env

编辑 `service/.env`：

```env
CLICKHOUSE_HOST=127.0.0.1
CLICKHOUSE_PORT=8123
CLICKHOUSE_DB=default
CLICKHOUSE_USER=default
CLICKHOUSE_PASS=
```

## 4. PHP 依赖

```bash
cd service && composer install
```

配置位于 `config/plugin/erikwang2013/clickhouse-php/app.php`，自动读取环境变量。

## 5. 数据表迁移

```bash
# 行为日志表 + 物化视图
clickhouse-client < admin/database/migrations/clickhouse/2026_05_27_000005_play_log.sql

# 充值交易表
clickhouse-client < admin/database/migrations/clickhouse/2026_05_27_000006_deposit_log.sql
```

验证：

```bash
clickhouse-client -q "SHOW TABLES"
# erik_deposit_log / erik_game_play_log / erik_game_play_log_hourly
# erik_game_play_log_hourly_mv / erik_transaction_log
```

## 6. WebSocket 配置（可选）

WebSocket 进程已注册在 `config/process.php`，监听 `:8789`。如需关闭，注释 `websocket` 配置项。

## 7. 验证集成

```php
use Erikwang2013\ClickHouse\Webman\ClickHouseService;
$result = ClickHouseService::query('SELECT 1 AS ok');
print_r($result->first()); // ['ok' => 1]
```

## 8. 端口表

| 服务 | 端口 | 用途 |
|------|------|------|
| ClickHouse HTTP | 8123 | 查询 / Schema 管理 |
| ClickHouse TCP | 9000 | Native 协议写入 |
| WebSocket | 8789 | 实时推送 |
| Admin API | 8787 | 管理后台 + AnalyticsController |
| Service API | 8788 | C 端业务 |
