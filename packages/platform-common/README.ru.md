# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · **Русский** · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Общий слой `common\service\*`, используемый admin/ и service/, ссылается на локальный исходный код через Composer path-репозиторий.

## Сервисы

| Сервис | Описание |
|------|------|
| DepositLogService | Аудит пополнений + доход/конверсия |
| GameDashboardService | Операционный дашборд |
| ProbabilityService | Анализ вероятностей |
| GamePlayLogService | Запись журнала игрового поведения |
| CircuitBreaker / Retry | Механизмы устойчивости (прерыватель/повтор) |

Зависит от предоставляемых хостом `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`.

## Установка

Название пакета — `erik/platform-common`. И admin/, и service/ уже настроили path-репозиторий (`../packages/platform-common`) в composer.json, поэтому он устанавливается автоматически через `composer install`; также можно обновить отдельно из admin/ или service/:

```bash
composer update erik/platform-common
```

Если пакет опубликован в Packagist, можно установить напрямую:

```bash
composer require erik/platform-common
```

## Использование

Пространство имён `common\` (PSR-4 → `src/`):

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```

## Установка в один клик

Устанавливается автоматически мастером установки платформы (`install/`): мастер выполняет `composer install` для admin/ и service/, и зависимость path-репозитория устанавливается автоматически; ручная настройка не требуется.

## Остающиеся дубликаты

`app/model/*`, `app/common/*Service`, большинство `app/service/*` и EventBus по-прежнему продублированы в обеих частях.
