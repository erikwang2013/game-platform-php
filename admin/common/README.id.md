# common/ — Pustaka Bersama Admin
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · **Bahasa Indonesia** · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Direktori kode bersama backend admin (`admin/`). `common\service\*` telah dipindahkan ke paket bersama **erik/platform-common** (`packages/platform-common`); jangan letakkan kelas PHP di direktori ini karena akan menutupi autoload paket. Detail: `packages/platform-common/README.md`.

## Fitur

| Kategori | Lokasi | Deskripsi |
|------|------|------|
| Model | `app\model\*` | Model data (pengguna/pesanan/game, dll.) |
| Layanan | `common\service\*` | Layanan bisnis bersama (dalam paket erik/platform-common): DepositLogService (audit deposit + pendapatan/konversi), GameDashboardService (dasbor operasional), ProbabilityService (analisis probabilitas), GamePlayLogService (penulisan log aktivitas game) |
| Middleware | `app\middleware\*` | Cors / SecurityFilter / RateLimit / AdminAuth / AdminPermission / OperationLog |

## Instalasi

Sebagai bagian dari proyek admin, dependensi sudah dideklarasikan di `admin/composer.json` (termasuk repositori path `../packages/platform-common`) dan terinstal otomatis melalui `composer install`; tidak perlu instalasi terpisah:

```bash
cd admin && composer install
```

## Penggunaan

- Namespace `app\...` merujuk pada kode proyek admin itu sendiri, misalnya: `use app\model\User;`
- Namespace `common\...` merujuk pada paket bersama erik/platform-common (PSR-4 → `src/`), misalnya:

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```
