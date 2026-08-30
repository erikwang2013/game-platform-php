# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · **English** · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

The shared `common\service\*` layer used by admin/ and service/, referencing the local source via a Composer path repository.

## Services

| Service | Description |
|------|------|
| DepositLogService | Deposit audit + revenue/conversion |
| GameDashboardService | Operations dashboard |
| ProbabilityService | Probability analysis |
| GamePlayLogService | Game behavior log writing |
| CircuitBreaker / Retry | Resilience mechanisms (circuit breaker/retry) |

Dependencies provided by the host: `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`.

## Installation

Package name `erik/platform-common`. Both admin/ and service/ already configure the path repository (`../packages/platform-common`) in composer.json, so it is installed automatically with `composer install`; you can also update it individually from admin/ or service/:

```bash
composer update erik/platform-common
```

If published to Packagist, you can also install it directly:

```bash
composer require erik/platform-common
```

## Usage

Namespace `common\` (PSR-4 → `src/`):

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```

## One-Click Installation

Installed automatically by the platform one-click installation wizard (`install/`): the wizard runs `composer install` for admin/ and service/, and the path repository dependency is installed automatically; no manual configuration is needed.

## Remaining Dual Copies

`app/model/*`, `app/common/*Service`, most `app/service/*`, and EventBus are still duplicated on both sides.
