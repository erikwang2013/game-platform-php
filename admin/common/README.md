# common/ — 管理后台公共库
<!-- lang-nav -->

Languages: **中文** · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

管理后台（admin/）的公共代码目录。`common\service\*` 已抽取到共享包 **erik/platform-common**（`packages/platform-common`），本目录不再放置 PHP 类，避免遮蔽包的自动加载。详见 `packages/platform-common/README.md`。

## 功能说明

| 类别 | 位置 | 说明 |
|------|------|------|
| 模型 | `app\model\*` | 数据模型（用户/订单/游戏等） |
| 服务 | `common\service\*` | 共享业务服务（在 erik/platform-common 包中）：DepositLogService（充值审计+营收/转化）、GameDashboardService（运营仪表盘）、ProbabilityService（概率分析）、GamePlayLogService（游戏行为日志写入） |
| 中间件 | `app\middleware\*` | Cors / SecurityFilter / RateLimit / AdminAuth / AdminPermission / OperationLog |

## 安装

作为 admin 项目的一部分，依赖已在 `admin/composer.json` 中声明（含 path 仓库 `../packages/platform-common`），`composer install` 时自动安装，无需单独安装：

```bash
cd admin && composer install
```

## 使用说明

- `app\...` 命名空间对应 admin 项目自身代码，例如：`use app\model\User;`
- `common\...` 命名空间对应共享包 erik/platform-common（PSR-4 → `src/`），例如：

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```
