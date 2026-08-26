# ClickHouse-Installation und -Konfiguration
<!-- lang-nav -->

Languages: **中文** · [English](CLICKHOUSE_INSTALL.en.md) · [한국어](CLICKHOUSE_INSTALL.ko.md) · [Русский](CLICKHOUSE_INSTALL.ru.md) · [Deutsch](CLICKHOUSE_INSTALL.de.md) · [Français](CLICKHOUSE_INSTALL.fr.md) · [Español](CLICKHOUSE_INSTALL.es.md) · [Português](CLICKHOUSE_INSTALL.pt.md) · [हिन्दी](CLICKHOUSE_INSTALL.hi.md) · [العربية](CLICKHOUSE_INSTALL.ar.md) · [বাংলা](CLICKHOUSE_INSTALL.bn.md) · [Bahasa Indonesia](CLICKHOUSE_INSTALL.id.md) · [日本語](CLICKHOUSE_INSTALL.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Installation

```bash
# Docker
docker run -d --name clickhouse -p 8123:8123 -p 9000:9000 \
  -e CLICKHOUSE_USER=default -e CLICKHOUSE_DB=default \
  clickhouse/clickhouse-server:24-alpine

# Ubuntu/Debian
sudo apt install -y clickhouse-server clickhouse-client
sudo service clickhouse-server start
```

Verifikation: `clickhouse-client -q "SELECT version()"` oder `curl 'http://localhost:8123/?query=SELECT+1'`

## 2. Umgebungsvariablen

`service/.env` bearbeiten:

```env
CLICKHOUSE_HOST=127.0.0.1
CLICKHOUSE_PORT=8123
CLICKHOUSE_DB=default
CLICKHOUSE_USER=default
CLICKHOUSE_PASS=
```

## 3. PHP-Abhängigkeit

`composer.json` enthält bereits `erikwang2013/clickhouse-php: ^1.0`, die Konfiguration liegt in `config/plugin/erikwang2013/clickhouse-php/app.php`.

## 4. Tabellen anlegen

```bash
clickhouse-client < admin/database/migrations/clickhouse/000_init_tables.sql
```

## 5. Verifikation

```php
use Erikwang2013\ClickHouse\Webman\ClickHouseService;
$r = ClickHouseService::query('SELECT 1 AS ok');
// ['ok' => 1]
```

## 6. Ports

| Dienst | Port |
|------|------|
| ClickHouse HTTP | 8123 |
| admin/ | 8787 |
| service/ | 8788 |
