# ClickHouse 安装与配置
<!-- lang-nav -->

Languages: [中文](CLICKHOUSE_INSTALL.md) · [English](CLICKHOUSE_INSTALL.en.md) · [한국어](CLICKHOUSE_INSTALL.ko.md) · [Русский](CLICKHOUSE_INSTALL.ru.md) · [Deutsch](CLICKHOUSE_INSTALL.de.md) · [Français](CLICKHOUSE_INSTALL.fr.md) · [Español](CLICKHOUSE_INSTALL.es.md) · [Português](CLICKHOUSE_INSTALL.pt.md) · [हिन्दी](CLICKHOUSE_INSTALL.hi.md) · [العربية](CLICKHOUSE_INSTALL.ar.md) · **বাংলা** · [Bahasa Indonesia](CLICKHOUSE_INSTALL.id.md) · [日本語](CLICKHOUSE_INSTALL.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. ইনস্টলেশন

```bash
# Docker
docker run -d --name clickhouse -p 8123:8123 -p 9000:9000 \
  -e CLICKHOUSE_USER=default -e CLICKHOUSE_DB=default \
  clickhouse/clickhouse-server:24-alpine

# Ubuntu/Debian
sudo apt install -y clickhouse-server clickhouse-client
sudo service clickhouse-server start
```

ভেরিফিকেশন: `clickhouse-client -q "SELECT version()"` অথবা `curl 'http://localhost:8123/?query=SELECT+1'`

## 2. এনভায়রনমেন্ট ভেরিয়েবল

`service/.env` সম্পাদনা করুন:

```env
CLICKHOUSE_HOST=127.0.0.1
CLICKHOUSE_PORT=8123
CLICKHOUSE_DB=default
CLICKHOUSE_USER=default
CLICKHOUSE_PASS=
```

## 3. PHP নির্ভরতা

`composer.json`-এ `erikwang2013/clickhouse-php: ^1.0` অন্তর্ভুক্ত আছে, কনফিগ `config/plugin/erikwang2013/clickhouse-php/app.php`-তে অবস্থিত।

## 4. টেবিল তৈরি

```bash
clickhouse-client < install/clickhouse.sql
```

## 5. ভেরিফিকেশন

```php
use Erikwang2013\ClickHouse\Webman\ClickHouseService;
$r = ClickHouseService::query('SELECT 1 AS ok');
// ['ok' => 1]
```

## 6. পোর্ট

| সার্ভিস | পোর্ট |
|------|------|
| ClickHouse HTTP | 8123 |
| admin/ | 8787 |
| service/ | 8788 |
