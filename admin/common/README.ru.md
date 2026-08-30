# common/ — Общая библиотека админки
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · **Русский** · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Каталог общего кода админки (admin/). `common\service\*` вынесены в общий пакет **erik/platform-common** (`packages/platform-common`); не размещайте PHP-классы в этом каталоге — они перекроют автозагрузку пакета. Подробнее: `packages/platform-common/README.md`.

## Возможности

| Категория | Расположение | Описание |
|------|------|------|
| Модели | `app\model\*` | Модели данных (пользователи/заказы/игры и т.д.) |
| Сервисы | `common\service\*` | Общие бизнес-сервисы (в пакете erik/platform-common): DepositLogService (аудит пополнений + выручка/конверсия), GameDashboardService (операционный дашборд), ProbabilityService (анализ вероятностей), GamePlayLogService (запись журналов игровых действий) |
| Middleware | `app\middleware\*` | Cors / SecurityFilter / RateLimit / AdminAuth / AdminPermission / OperationLog |

## Установка

Как часть проекта admin, зависимости уже объявлены в `admin/composer.json` (включая path-репозиторий `../packages/platform-common`) и устанавливаются автоматически через `composer install`; отдельная установка не требуется:

```bash
cd admin && composer install
```

## Использование

- Пространство имён `app\...` соответствует коду самого проекта admin, например: `use app\model\User;`
- Пространство имён `common\...` соответствует общему пакету erik/platform-common (PSR-4 → `src/`), например:

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```
