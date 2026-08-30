# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · **Español** · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

La capa compartida `common\service\*`, utilizada por admin/ y service/, que referencia el código fuente local mediante un repositorio de rutas de Composer.

## Servicios

| Servicio | Descripción |
|------|------|
| DepositLogService | Auditoría de recargas + ingresos/conversión |
| GameDashboardService | Dashboard de operaciones |
| ProbabilityService | Análisis de probabilidad |
| GamePlayLogService | Escritura de logs de comportamiento de juego |
| CircuitBreaker / Retry | Mecanismos de resiliencia (interruptor/reintento) |

Depende de que el host proporcione `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`.

## Instalación

Nombre del paquete: `erik/platform-common`. Tanto admin/ como service/ ya configuran el repositorio path (`../packages/platform-common`) en composer.json, por lo que se instala automáticamente con `composer install`; también puedes actualizarlo por separado desde admin/ o service/:

```bash
composer update erik/platform-common
```

Si se publica en Packagist, también se puede instalar directamente:

```bash
composer require erik/platform-common
```

## Uso

Espacio de nombres `common\` (PSR-4 → `src/`):

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```

## Instalación en un clic

Se instala automáticamente con el asistente de instalación en un clic de la plataforma (`install/`): el asistente ejecuta `composer install` para admin/ y service/, y la dependencia del repositorio path se instala automáticamente; no se requiere configuración manual.

## Dobles copias restantes

`app/model/*`, `app/common/*Service`, la mayoría de `app/service/*` y EventBus siguen copiados en ambos lados.
