# ClickHouse 설치와 설정
<!-- lang-nav -->

Languages: [中文](CLICKHOUSE_INSTALL.md) · [English](CLICKHOUSE_INSTALL.en.md) · **한국어** · [Русский](CLICKHOUSE_INSTALL.ru.md) · [Deutsch](CLICKHOUSE_INSTALL.de.md) · [Français](CLICKHOUSE_INSTALL.fr.md) · [Español](CLICKHOUSE_INSTALL.es.md) · [Português](CLICKHOUSE_INSTALL.pt.md) · [हिन्दी](CLICKHOUSE_INSTALL.hi.md) · [العربية](CLICKHOUSE_INSTALL.ar.md) · [বাংলা](CLICKHOUSE_INSTALL.bn.md) · [Bahasa Indonesia](CLICKHOUSE_INSTALL.id.md) · [日本語](CLICKHOUSE_INSTALL.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 설치

```bash
# Docker
docker run -d --name clickhouse -p 8123:8123 -p 9000:9000 \
  -e CLICKHOUSE_USER=default -e CLICKHOUSE_DB=default \
  clickhouse/clickhouse-server:24-alpine

# Ubuntu/Debian
sudo apt install -y clickhouse-server clickhouse-client
sudo service clickhouse-server start
```

검증: `clickhouse-client -q "SELECT version()"` 또는 `curl 'http://localhost:8123/?query=SELECT+1'`

## 2. 환경 변수

`service/.env` 편집:

```env
CLICKHOUSE_HOST=127.0.0.1
CLICKHOUSE_PORT=8123
CLICKHOUSE_DB=default
CLICKHOUSE_USER=default
CLICKHOUSE_PASS=
```

## 3. PHP 의존성

`composer.json`에 `erikwang2013/clickhouse-php: ^1.0`이 포함되어 있으며, 설정은 `config/plugin/erikwang2013/clickhouse-php/app.php`에 있습니다.

## 4. 테이블 생성

```bash
clickhouse-client < install/clickhouse.sql
```

## 5. 검증

```php
use Erikwang2013\ClickHouse\Webman\ClickHouseService;
$r = ClickHouseService::query('SELECT 1 AS ok');
// ['ok' => 1]
```

## 6. 포트

| 서비스 | 포트 |
|------|------|
| ClickHouse HTTP | 8123 |
| admin/ | 8787 |
| service/ | 8788 |
