# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · **Español** · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)


Comparte `common\service\*`, referenciado por admin/ y service/ mediante un repositorio de rutas de Composer.

## Servicios

- DepositLogService — auditoría de recargas + ingresos/conversión
- GameDashboardService — dashboard de operaciones
- ProbabilityService — análisis de probabilidad
- GamePlayLogService — escritura de logs de comportamiento de juego

Depende de que el host proporcione `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`.

## Integración

```bash
cd admin && composer update erik/platform-common
cd ../service && composer update erik/platform-common
```

## Dobles copias restantes

app/model/*, app/common/*Service, la mayoría de app/service/*, EventBus siguen copiados en ambos lados.
