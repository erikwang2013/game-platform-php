# erik/platform-common
<!-- lang-nav -->

Languages: **中文** · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

admin/ 与 service/ 共享的 `common\service\*` 公共层，通过 Composer path 仓库引用本地源码。

## 服务

| 服务 | 说明 |
|------|------|
| DepositLogService | 充值审计 + 营收/转化 |
| GameDashboardService | 运营仪表盘 |
| ProbabilityService | 概率分析 |
| GamePlayLogService | 游戏行为日志写入 |
| CircuitBreaker / Retry | 稳定性机制（熔断/重试） |

依赖宿主提供 `app\model\*`、`app\common\SnowflakeService`、`support\Db`、`support\Log`。

## 安装

包名 `erik/platform-common`。admin/ 与 service/ 均已在 composer.json 中配置 path 仓库（`../packages/platform-common`），随 `composer install` 自动安装；也可在 admin/ 或 service/ 下单独更新：

```bash
composer update erik/platform-common
```

若发布到 Packagist，也可直接：

```bash
composer require erik/platform-common
```

## 使用说明

命名空间 `common\`（PSR-4 → `src/`）：

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```

## 一键安装

随平台一键安装向导（`install/`）自动完成：向导执行 admin/ 与 service/ 的 `composer install`，path 仓库依赖自动安装，无需手动配置。

## 剩余双份

`app/model/*`、`app/common/*Service`、多数 `app/service/*`、EventBus 仍双侧复制。
