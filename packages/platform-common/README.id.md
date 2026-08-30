# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · **Bahasa Indonesia** · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Lapisan bersama `common\service\*` yang digunakan oleh admin/ dan service/, merujuk sumber lokal melalui path repository Composer.

## Layanan

| Layanan | Deskripsi |
|------|------|
| DepositLogService | Audit deposit + pendapatan/konversi |
| GameDashboardService | Dasbor operasional |
| ProbabilityService | Analisis probabilitas |
| GamePlayLogService | Penulisan log perilaku game |
| CircuitBreaker / Retry | Mekanisme ketahanan (pemutus/percobaan ulang) |

Dependensi host menyediakan `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`.

## Instalasi

Nama paket `erik/platform-common`. Baik admin/ maupun service/ telah mengonfigurasi path repository (`../packages/platform-common`) di composer.json, sehingga terinstal otomatis melalui `composer install`; pembaruan terpisah dari admin/ atau service/ juga dimungkinkan:

```bash
composer update erik/platform-common
```

Jika dipublikasikan ke Packagist, dapat juga diinstal langsung:

```bash
composer require erik/platform-common
```

## Penggunaan

Namespace `common\` (PSR-4 → `src/`):

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```

## Instalasi Sekali Klik

Diselesaikan otomatis oleh wizard instalasi sekali klik platform (`install/`): wizard menjalankan `composer install` untuk admin/ dan service/, dependensi path repository terinstal otomatis; tidak diperlukan konfigurasi manual.

## Sisa Salinan Ganda

`app/model/*`, `app/common/*Service`, mayoritas `app/service/*`, dan EventBus masih disalin dua sisi.
