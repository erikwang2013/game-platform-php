# Instalación y configuración de ClickHouse
<!-- lang-nav -->

Languages: [中文](CLICKHOUSE_INSTALL.md) · [English](CLICKHOUSE_INSTALL.en.md) · [한국어](CLICKHOUSE_INSTALL.ko.md) · [Русский](CLICKHOUSE_INSTALL.ru.md) · [Deutsch](CLICKHOUSE_INSTALL.de.md) · [Français](CLICKHOUSE_INSTALL.fr.md) · **Español** · [Português](CLICKHOUSE_INSTALL.pt.md) · [हिन्दी](CLICKHOUSE_INSTALL.hi.md) · [العربية](CLICKHOUSE_INSTALL.ar.md) · [বাংলা](CLICKHOUSE_INSTALL.bn.md) · [Bahasa Indonesia](CLICKHOUSE_INSTALL.id.md) · [日本語](CLICKHOUSE_INSTALL.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Instalación

```bash
# Docker
docker run -d --name clickhouse -p 8123:8123 -p 9000:9000 \
  -e CLICKHOUSE_USER=default -e CLICKHOUSE_DB=default \
  clickhouse/clickhouse-server:24-alpine

# Ubuntu/Debian
sudo apt install -y clickhouse-server clickhouse-client
sudo service clickhouse-server start
```

Verificación: `clickhouse-client -q "SELECT version()"` o `curl 'http://localhost:8123/?query=SELECT+1'`

## 2. Variables de entorno

Editar `service/.env`:

```env
CLICKHOUSE_HOST=127.0.0.1
CLICKHOUSE_PORT=8123
CLICKHOUSE_DB=default
CLICKHOUSE_USER=default
CLICKHOUSE_PASS=
```

## 3. Dependencia PHP

`composer.json` ya incluye `erikwang2013/clickhouse-php: ^1.0`; la configuración está en `config/plugin/erikwang2013/clickhouse-php/app.php`.

## 4. Crear tablas

```bash
clickhouse-client < install/clickhouse.sql
```

## 5. Verificación

```php
use Erikwang2013\ClickHouse\Webman\ClickHouseService;
$r = ClickHouseService::query('SELECT 1 AS ok');
// ['ok' => 1]
```

## 6. Puertos

| Servicio | Puerto |
|------|------|
| HTTP de ClickHouse | 8123 |
| admin/ | 8787 |
| service/ | 8788 |
