# ClickHouse स्थापना और कॉन्फ़िगरेशन
<!-- lang-nav -->

Languages: [中文](CLICKHOUSE_INSTALL.md) · [English](CLICKHOUSE_INSTALL.en.md) · [한국어](CLICKHOUSE_INSTALL.ko.md) · [Русский](CLICKHOUSE_INSTALL.ru.md) · [Deutsch](CLICKHOUSE_INSTALL.de.md) · [Français](CLICKHOUSE_INSTALL.fr.md) · [Español](CLICKHOUSE_INSTALL.es.md) · [Português](CLICKHOUSE_INSTALL.pt.md) · **हिन्दी** · [العربية](CLICKHOUSE_INSTALL.ar.md) · [বাংলা](CLICKHOUSE_INSTALL.bn.md) · [Bahasa Indonesia](CLICKHOUSE_INSTALL.id.md) · [日本語](CLICKHOUSE_INSTALL.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. स्थापना

```bash
# Docker
docker run -d --name clickhouse -p 8123:8123 -p 9000:9000 \
  -e CLICKHOUSE_USER=default -e CLICKHOUSE_DB=default \
  clickhouse/clickhouse-server:24-alpine

# Ubuntu/Debian
sudo apt install -y clickhouse-server clickhouse-client
sudo service clickhouse-server start
```

सत्यापन: `clickhouse-client -q "SELECT version()"` या `curl 'http://localhost:8123/?query=SELECT+1'`

## 2. पर्यावरण चर

`service/.env` संपादित करें:

```env
CLICKHOUSE_HOST=127.0.0.1
CLICKHOUSE_PORT=8123
CLICKHOUSE_DB=default
CLICKHOUSE_USER=default
CLICKHOUSE_PASS=
```

## 3. PHP निर्भरता

`composer.json` में पहले से `erikwang2013/clickhouse-php: ^1.0` शामिल है, कॉन्फ़िग `config/plugin/erikwang2013/clickhouse-php/app.php` में स्थित है।

## 4. तालिका निर्माण

```bash
clickhouse-client < install/clickhouse.sql
```

## 5. सत्यापन

```php
use Erikwang2013\ClickHouse\Webman\ClickHouseService;
$r = ClickHouseService::query('SELECT 1 AS ok');
// ['ok' => 1]
```

## 6. पोर्ट

| सेवा | पोर्ट |
|------|------|
| ClickHouse HTTP | 8123 |
| admin/ | 8787 |
| service/ | 8788 |
