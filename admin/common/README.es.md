# common/ — Biblioteca compartida de administración
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · **Español** · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

El directorio de código compartido del backend de administración (`admin/`). Los `common\service\*` se han extraído al paquete compartido **erik/platform-common** (`packages/platform-common`); no coloques clases PHP en este directorio, ya que harían sombra al autoload del paquete. Consulta `packages/platform-common/README.md`.

## Funcionalidades

| Categoría | Ubicación | Descripción |
|------|------|------|
| Modelos | `app\model\*` | Modelos de datos (usuarios/pedidos/juegos, etc.) |
| Servicios | `common\service\*` | Servicios de negocio compartidos (en el paquete erik/platform-common): DepositLogService (auditoría de depósitos + ingresos/conversión), GameDashboardService (panel de operaciones), ProbabilityService (análisis de probabilidades), GamePlayLogService (escritura de registros de actividad de juego) |
| Middleware | `app\middleware\*` | Cors / SecurityFilter / RateLimit / AdminAuth / AdminPermission / OperationLog |

## Instalación

Como parte del proyecto admin, las dependencias ya están declaradas en `admin/composer.json` (incluido el repositorio path `../packages/platform-common`) y se instalan automáticamente con `composer install`; no se necesita instalación por separado:

```bash
cd admin && composer install
```

## Uso

- El espacio de nombres `app\...` corresponde al código del propio proyecto admin, p. ej.: `use app\model\User;`
- El espacio de nombres `common\...` corresponde al paquete compartido erik/platform-common (PSR-4 → `src/`), p. ej.:

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```
