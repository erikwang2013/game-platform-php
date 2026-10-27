# common/ — Admin Shared Library

Languages: [中文](README.md) · **English**

> Copyright (c) 2026 erik erik@erik.xyz — https://erik.xyz

The shared code directory of the admin backend (`admin/`). `common\service\*` has been extracted into the shared package **erik/platform-common** (`packages/platform-common`); do not place PHP classes in this directory, as they would shadow the package autoload. See `packages/platform-common/README.md`.

## Features

| Category | Location | Description |
|------|------|------|
| Models | `app\model\*` | Data models (users/orders/games, etc.) |
| Services | `common\service\*` | Shared business services (in the erik/platform-common package): DepositLogService (deposit audit + revenue/conversion), GameDashboardService (operations dashboard), ProbabilityService (probability analysis), GamePlayLogService (game play log writes) |
| Middleware | `app\middleware\*` | Cors / SecurityFilter / RateLimit / AdminAuth / AdminPermission / OperationLog |

## Installation

As part of the admin project, dependencies are already declared in `admin/composer.json` (including the path repository `../packages/platform-common`) and are installed automatically by `composer install`; no separate installation is needed:

```bash
cd admin && composer install
```

## Usage

- The `app\...` namespace maps to the admin project's own code, e.g.: `use app\model\User;`
- The `common\...` namespace maps to the shared package erik/platform-common (PSR-4 → `src/`), e.g.:

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```
