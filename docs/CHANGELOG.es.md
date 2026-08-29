# Changelog
<!-- lang-nav -->

Languages: [中文](CHANGELOG.md) · [English](CHANGELOG.en.md) · [한국어](CHANGELOG.ko.md) · [Русский](CHANGELOG.ru.md) · [Deutsch](CHANGELOG.de.md) · [Français](CHANGELOG.fr.md) · **Español** · [Português](CHANGELOG.pt.md) · [हिन्दी](CHANGELOG.hi.md) · [العربية](CHANGELOG.ar.md) · [বাংলা](CHANGELOG.bn.md) · [Bahasa Indonesia](CHANGELOG.id.md) · [日本語](CHANGELOG.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Registro de cambios legible para humanos. PHP no importa este archivo. Corresponde a P2-21 de PROJECT-PLAN.

## [1.1] — 2026-08-07

- Integración del plugin de Redis, servicios de análisis, degradación de Redis y correcciones de pruebas.

## [1.1] security / ops — 2026-08-18

### Seguridad

- Callback de pago: lista blanca de providers (stripe/paypal), verificación de firma fail-closed, comprobación de importes, ingreso transaccional, marca de tiempo de Stripe ±300s anti-replay.
- JWT: rechaza el arranque si `JWT_SECRET_KEY` / `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY` faltan o tienen el valor por defecto.
- Apple id_token: verificación JWKS (RS256) + aud/iss/exp.
- Webhook: solo URLs públicas https; rechaza direcciones internas/reservadas (SSRF).
- 2FA: el HMAC de TOTP usa la clave decodificada en Base32 RFC 4648; `/api/2fa/verify` bloquea por usuario tras fallos (5 veces / 15 minutos, fail-closed si Redis falla).
- Retiros: la actualización condicional de estado de revisión/pago es atómica; doble revisión opcional (`withdraw.require_dual_review`); bloqueo de usuario en Redis en el lado de solicitud para impedir exceder los límites de forma concurrente.
- Limitación: fail-closed si Redis falla.

### Disponibilidad

- Montaje de las 12 rutas `/admin/analytics/*` del servicio de análisis de admin.
- Los modelos eliminan el prefijo `game_` hardcodeado; DepositLog audit se guarda en base de datos; eliminado el model de Test.

### Observabilidad

- `GET /metrics` añade retiros pendientes de revisión, recargas confirmadas de hoy (consulta COUNT con caché Redis de 30s), contadores de emit/consume de eventos, `memory_usage` e `info version=1.1`.
- FeatureFlag: `inRollout` / `abTest` leen `feature.{name}_percent` con buckets de crc32.
- EventBus `emit` / `consume` hace INCR sobre `metrics:event_emit_total` / `metrics:event_consume_total` en Redis.

### Cliente / compartido (completado el mismo día)

- Flutter Platform: tabla de rutas `app_pages.dart`; añade configuración/verificación 2FA, cupones, clasificaciones, notificaciones y página de callback OAuth; la entrada del lobby ya está conectada a la navegación.
- Lado C HarmonyOS: `apps/harmonyos/` cinco páginas (login/lobby/detalle/billetera/perfil), `BASE_URL` por defecto apunta al service `8788`.
- Capa compartida: `packages/platform-common` (path repo `erik/platform-common`) extrae DepositLog / GameDashboard / Probability / GamePlayLog; los models siguen duplicados.
- ClickHouse: dependencia de composer eliminada; el análisis sigue la agregación en tiempo real con MySQL.
- CI: admin / service ejecutan phpunit en jobs separados; el fallo bloquea.

### Brechas pendientes

- Los **models** de admin/service siguen duplicados (solo parte de `common/service` está en el paquete path).
- `webman/queue` no está conectado; probabilidad/retención aún no migradas a OLAP.
- PARTE de PROJECT-PLAN / VERSIONS / informes de auditoría puede seguir desfasada con respecto a este CHANGELOG; este archivo y el disco son la referencia.

## [1.1] resilience — 2026-08-27

### Estabilidad

- Capa compartida: añadidos `CircuitBreaker` (estado en Redis, umbral 5 / ventana 30 s, fail-open si Redis no está disponible) y `Retry` (backoff exponencial, solo excepciones de red, máx. 5 intentos), en `packages/platform-common/src/`.
- Interruptor de degradación `feature.provider_mock`: PushService (FCM/APNs/HarmonyOS), PayoutService (PayPal), ThirdPartyProvider hacen cortocircuito cuando `on`, sin llamadas de red reales.
- Corregidos 11 defectos de tipo de `getenv($name, '')` (TypeError con strict_types); comprobación mock de PushService movida a try/catch.
- Nuevas pruebas: CircuitBreakerTest / RetryTest / ResilienceMockTest; suite service 45 → 60 casos, todos en verde (informe: [test-reports/resilience.md](test-reports/resilience.md)).

## [1.1] payments — 2026-08-29

- Pasarelas de pago múltiples: Stripe Checkout / NOWPayments (USDT TRC20+ERC20) / Coinbase Commerce (USDC) + Alipay/WeChat Pay (Stripe Checkout APM).
- CRUD de métodos de pago en el panel + visibilidad por país + rangos de importe; las órdenes de recarga rellenan checkout_url / expires_at al crearse.
- Nueva migración install/migrations/2026_08_29_multi_payment.sql (debe ejecutarse).

## [1.1] features — 2026-08-29

- Minijuego Farm Match-3 P0: motor de dominio + diseño de 4 niveles + pruebas unitarias Vitest (`game/xiaoxiaole/`).
- Asistente de instalación en un clic: crear admin en el navegador, actualizar bases existentes (corrige HY093 desajuste de parámetros vinculados, Unknown column 'countries'), install.lock evita reinstalación.
- CI: tag incremental automático al push + publicación de GitHub Release.
- Infraestructura: base de datos renombrada a game-platform, prefijo de tabla `game_` unificado.
- Sincronización de documentos: FEATURES.md completado en 13 idiomas para resiliencia (interruptores circuit-breaker/retry/degradation), CRUD de métodos de pago en el panel, minijuego, instalación en un clic, líneas CI (correspondientes a las entradas [1.1] resilience / payments anteriores).
