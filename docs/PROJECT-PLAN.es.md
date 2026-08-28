# Plan integral del proyecto (Project Plan)
<!-- lang-nav -->

Languages: [中文](PROJECT-PLAN.md) · [English](PROJECT-PLAN.en.md) · [한국어](PROJECT-PLAN.ko.md) · [Русский](PROJECT-PLAN.ru.md) · [Deutsch](PROJECT-PLAN.de.md) · [Français](PROJECT-PLAN.fr.md) · **Español** · [Português](PROJECT-PLAN.pt.md) · [हिन्दी](PROJECT-PLAN.hi.md) · [العربية](PROJECT-PLAN.ar.md) · [বাংলা](PROJECT-PLAN.bn.md) · [Bahasa Indonesia](PROJECT-PLAN.id.md) · [日本語](PROJECT-PLAN.ja.md)


> Fecha de generación: 2026-08-16 · Basado en inventario de solo lectura del equipo de 6 personas (researcher/architect/backend-dev/frontend-dev/tester/reviewer) + verificación práctica de hallazgos clave
> Cobertura: resumen del estado / problemas y riesgos / hoja de ruta P0-P1-P2 / corrección de documentación / puertas de calidad

---

## 一、Estado actual del proyecto

**Plataforma global de agregación de juegos** — PHP 8.3 + webman v2, monorepo de dos aplicaciones:
`admin/`(8787 panel de administración) + `service/`(8788 lado C) + `apps/`(Flutter + HarmonyOS) + `install/`(asistente de instalación, 43 tablas).

| Dimensión | Volumen medido |
|------|---------|
| Controladores | admin 32 + service 30 = 62 |
| Endpoints de API | ~149 (admin 103 / service 88, incluye callbacks Webhook/Provider) |
| Modelos de datos | admin 46 / service 44, **copiados duplicados** entre admin/service (sin capa compartida) |
| Pruebas | 132 casos / 8 archivos (proyecto admin); el proyecto service tiene **cero pruebas** |
| Versión | v1.1 (2026-08-07): plugin Redis, servicio de análisis, degradación Redis, correcciones de pruebas |

Capacidades implementadas: JWT+RBAC, bloqueo optimista de billetera, recargas (verificación de firma Stripe/PayPal/NowPayments/Coinbase), diferencial de conversión, revisión de retiros + pago PayPal, CRUD de juegos/gestión de proveedores (HMAC), cupones/VIP/logros/tickets/comisión por recomendación/2FA/social (amigos/chat WS)/torneos/Webhook/push (FCM/APNs/华为)/i18n bilingüe.

---

## 二、Problemas y riesgos (verificados empíricamente)

### CRITICAL — Seguridad de fondos

| # | Problema | Ubicación |
|---|------|------|
| C1 | El `provider` del callback de pago lo envía el cliente; cuando no es stripe/paypal, **se omite por completo la verificación de firma** y un callback falsificado acredita directamente | service/.../PaymentController.php:36-42 |
| C2 | Verificación fail-open: `STRIPE_WEBHOOK_SECRET` no configurado → `return true`; cualquier excepción de PayPal → `return true`. Cadena de ataque: crear orden propia → callback falsificado → recarga infinita | PaymentController.php:167, 225 |
| C3 | `JWT_SECRET_KEY` sin valor recurre a la clave hardcodeada pública `open-admin-jwt-secret-change-in-production`; en producción sin env se puede falsificar el Token de administrador | admin+service config/plugin/erikwang2013/jwt/jwt.php:12 |

### HIGH — Correctitud/consistencia

| # | Problema | Ubicación |
|---|------|------|
| H1 | Las 12 funciones del servicio de análisis AnalyticsController están implementadas pero **sin rutas**, todas 404 y son código muerto; VERSIONS.md las declara entregadas | admin/config/route.php (0 analytics) |
| H2 | Bus de eventos desconectado: emit tiene 4 llamadas (game.played/withdraw.completed/exchange.completed/referral.applied), pero `subscribe()` no tiene ningún proceso registrado; los eventos publicados se pierden; los motores VIP/logros/notificaciones quedan en el aire | admin+service app/event/EventBus.php |
| H3 | common/ y model/ están duplicados y han divergido (DepositLogService con dos contenidos distintos, User.php inconsistente); una corrección puntual se convierte en trabajo doble. **common/service ya se extrajo** a `packages/platform-common` (erik/platform-common, el antiguo common-php se fusionó); model y los wrappers de app/common siguen duplicados | admin/common vs service/common → packages/platform-common |
| H4 | ~~El lado C de HarmonyOS `apps/harmonyos/` es un directorio vacío, 0 páginas vs las 5 páginas que afirma VERSIONS.md~~ — Ya implementado (2026-08-18: 5 páginas implementadas en `apps/harmonyos/`) | apps/harmonyos/ |
| H5 | El callback de Stripe no valida la tolerancia de la marca de tiempo `t=` (replay), y el importe acreditado no se compara con el importe real pagado en la pasarela | PaymentController.php:191-194 |
| H6 | El id_token de Apple solo decodifica el payload en base64, sin verificar firma ni aud/iss/exp, riesgo de confusión de identidad entre aplicaciones | OAuthController.php:376-380 |

### MEDIUM — Fiabilidad/defectos de implementación

| # | Problema |
|---|------|
| M1 | Doble defecto de 2FA: `/api/2fa/verify` es público sin bloqueo de intentos por usuario (oráculo de fuerza bruta); TOTP usa la cadena Base32 directamente como clave HMAC (sin decodificar), no coincide con Authenticator → **el 2FA es realmente inutilizable** |
| M2 | La revisión/pago de retiros es check-then-act sin actualización atómica de estado; la concurrencia puede pagar dos veces; no hay doble revisión |
| M3 | La URL de callback del Webhook solo se valida con filter_var; puede apuntar a IPs internas (SSRF), y dispatch hace POST a cualquier URL |
| M4 | Los límites diarios/mensuales de retiro son "consultar y luego insertar" no atómicos; la concurrencia puede superar los límites |
| M5 | Fallo de Redis fail-open sin abstracción unificada: la invalidación de la lista negra JWT falla, la limitación falla silenciosamente; brechas de degradación: PayoutService::getAccessToken, ChatWebSocket brpop, acceso a estado OAuth |
| M6 | ClickHouse sin uso: el cálculo de probabilidad es en realidad COUNT(DISTINCT) en tiempo real de MySQL + JOIN de subconsultas, riesgo O(n²) con tablas grandes; composer depende de él sin capacidad |
| M7 | Cola a medias: admin/app/queue tiene ComputeDailyStats + 3 tareas ES, pero webman/queue no está instalado, process.php no lo registra, todo sin llamadores |
| M8 | Código muerto: los servicios Vip/Achievement/Notification/FeatureFlag sin llamadores; DepositLogService::log() implementación vacía; modelo Test residual; el algoritmo de retención con un único cohort es una estimación burda |

### LOW
- Los retiros se pueden pagar a cualquier correo de PayPal sin exigir 2FA/KYC; las notas de revisión entran en el texto de la notificación (superficie XSS)
- Documentación fuera de sincronía con la realidad: install.sql 43 tablas vs las 52 que la documentación llegó a escribir; docker-compose 7 servicios vs los 8 que FEATURES.md llegó a escribir; "Shared Model 34" no es cierto (admin 46 / service 44, una copia cada uno, sin capa compartida). CHANGELOG ya lo complementa, ver `docs/CHANGELOG.md`.

### Elementos aprobados (la revisión de seguridad confirma que no hay problemas)
El bloqueo optimista de billetera con actualización condicional de versión es correcto; la idempotencia de callbacks con actualización condicional `where status=pending` es correcta; todo ORM sin concatenación SQL directa; .env no está en git; todas las rutas de admin tienen AdminAuth+RBAC con denegación por defecto; la validación del estado OAuth con consumo único es correcta.

> **Estado de corrección 2026-08-18**: C1/C2/C3/H1/H5/H6 corregidos; H2 bus de eventos: `process.php` ya registra `event-consumer` y la clase consumidora `EventConsumer` está implementada, emit tiene consumidores; M1 Base32 + bloqueo por usuario corregido; M2 atomicidad del estado de retiro + doble revisión opcional hecha; M3 SSRF de Webhook bloqueado; M4 bloqueo de usuario en Redis en la solicitud de retiro hecho; M5 parcialmente completado (RateLimit fail-closed); P2-19 métricas de negocio + FeatureFlag con lanzamiento gradual implementados. La lista de problemas se conserva como conclusión histórica de la auditoría.

---

## 三、Hoja de ruta

### P0 — Seguridad de fondos + correctitud (primero, bloquea el lanzamiento)

1. **Callback de pago fail-closed**: lista blanca de providers (solo stripe/paypal/nowpayments/coinbase) + falta de clave debe rechazar con 500 + cualquier excepción de PayPal se rechaza (C1/C2) — ✅ Completado (2026-08-18: lista blanca de providers + validación de suplantación entre canales + validación opcional de IP de origen + acreditación del callback transaccional)
2. **Validación de JWT al arranque**: sin `JWT_SECRET_KEY` en env se rechaza el arranque (C3) — ✅ Completado (2026-08-18: se rechaza el arranque si `JWT_SECRET_KEY` falta o tiene el valor por defecto `open-admin-jwt-secret-change-in-production`, coherente en admin/service)
3. **Montar rutas del servicio de análisis**: registrar las 12 rutas de analytics + puntos de permiso, cumplir lo prometido en VERSIONS.md (H1) — ✅ Completado (2026-08-18: admin/config/route.php registra las 12 rutas `/admin/analytics/*`)
4. **Conectar el bus de eventos**: registrar un proceso de suscripción residente para consumir, o pasar a llamada directa síncrona; persistir eventos + reintentar fallos (H2) — ✅ Completado (2026-08-18: emit/consume ya hacen INCR de contadores Redis; `service/config/process.php` registra `event-consumer`, `service/app/process/EventConsumer.php` consume eventos)
5. **Verificación de firma del id_token de Apple**: validación JWKS + aud/iss/exp (H6) — ✅ Completado (2026-08-18: JWKS RS256 + refresco de kid + aud/iss/exp)
6. **Replay y comparación de importes de Stripe**: tolerancia de marca de tiempo + comparación con el importe de la pasarela (H5) — ✅ Completado (2026-08-18: marca de tiempo `t=` ±300s anti-replay + comparación de importes con precisión bccomp + falta de secret/webhook_id o error de verificación siempre se rechaza)

### P1 — Fiabilidad + consistencia

7. **Deduplicación de la capa compartida**: extraer common/model a un repo de rutas composer (o enlace simbólico), eliminar la doble copia divergente (H3) — 🔶 Parcialmente completado (2026-08-18: `common/service` se extrajo a un único `packages/platform-common` / repo de rutas `erik/platform-common` (el antiguo `common-php` se fusionó), referenciado por admin+service; model y los wrappers `app/common` ligados al host siguen duplicados, ver `packages/platform-common/DUAL_MODELS.md`)
8. **Encapsulado unificado de degradación de Redis**: explicitar la política de fallo + alertar sin silenciar; añadir respaldos de PayoutService/OAuth/ChatWebSocket (M5) — 🔶 Parcialmente completado (RateLimit fail-closed implementado: con fallo de Redis la limitación rechaza en lugar de dejar pasar silenciosamente; lo demás sin hacer)
9. **Conexión de webman/queue**: para entrega de eventos y webhooks (reintento de consumo, cola de mensajes muertos), habilitar o eliminar las tareas ComputeDailyStats/ES (M7) — ⬜ Sin hacer
10. **Corrección de 2FA**: decodificar Base32 + verify con estado de sesión y bloqueo de intentos por usuario (M1) — ✅ Completado (2026-08-18: HMAC tras decodificar Base32 RFC 4648; `/api/2fa/verify` bloquea tras 5 fallos durante 15 minutos, con fallo de Redis fail-closed)
11. **Atomicidad de retiros**: actualización condicional de revisión/pago + doble revisión; límites con Lua de Redis/restricción única (M2/M4) — 🔶 Parcialmente completado (2026-08-18: pending→approved/rejected, approved→processing con UPDATE condicional; doble revisión opcional `withdraw.require_dual_review`; bloqueo de usuario en Redis en la solicitud. Sin límites Lua/restricción única)
12. **Bloqueo de SSRF de Webhook**: rechazar IPs internas/reservadas (M3) — ✅ Completado (2026-08-18: `isSafeWebhookUrl()` solo https público)
13. **ClickHouse, una de dos**: integración real o eliminar la dependencia + revisar la documentación (M6) — ⬜ Sin hacer
14. **Limpieza de código muerto**: conectar o eliminar Vip/Achievement/Notification/FeatureFlag; borrar el modelo Test; auditar DepositLog en base de datos (M8) — 🔶 Parcialmente completado (2026-08-18: modelo Test eliminado, auditoría DepositLog en base de datos; Vip/FeatureFlag/Notification ya tienen llamadores; AchievementService ya lo llama EventConsumer)
15. **Pruebas de service + puerta de CI**: pruebas de integración de verificación de callbacks/flujo de retiros/degradación Redis/cálculo de probabilidad/concurrencia de bloqueo optimista; que el fallo de phpunit bloquee; incluir service en el CI (actualmente `|| echo warning` permite fallos) — 🔶 Parcialmente completado (service ya tiene WebhookUrlSafety / EventBusMessageFormat; ya incluido en el job de CI `phpunit-service` con bloqueo por fallo)

**Completado adicionalmente en esta ronda (2026-08-18) (fuera de la numeración original)**:
- **Corrección de prefijo de tablas**: los 52 modelos eliminan el prefijo `game_` hardcodeado, eliminando el doble prefijo `game_game_`; el prefijo de DB lo proporciona de forma unificada `prefix=game_` de config/database.php, sin cambios en install.sql
- **Reescritura de refresh token**: reescrita la lógica de refresco de tokens del AuthController de service
- **Trasplante de la versión service de DepositLogService**: completado service/common/service/DepositLogService.php (eliminada una de las duplicaciones admin/service divergentes)

### P2 — Observabilidad / Extensión / Experiencia

16. **Lado C de HarmonyOS** implementado desde cero con 5 páginas (login/lobby/detalle/billetera/perfil) (H4) — ✅ Completado (2026-08-18: las 5 páginas de `apps/harmonyos/entry/src/main/ets/pages/` están en el repositorio)
17. **Completar el frontend**: página de verificación 2FA, entradas de cupones/clasificaciones/notificaciones, UI de búsqueda ES; unificar las fuentes de rutas main.dart/app_pages.dart; callbacks reales de OAuth; capa de transmisión AES en el frontend
18. **Migrar el cálculo de probabilidad a ClickHouse** o tablas de estadísticas materializadas en MySQL + caché; recalcular la retención por cohort real
19. **Métricas de negocio de Prometheus** (tasa de entrega/consumo de eventos, profundidad de cola) + middleware de división AB en escalera (reutilizando FeatureFlag) — 🔶 Parcialmente completado (2026-08-18: `GET /metrics` con retiros pendientes de revisión/recargas confirmadas de hoy/contadores emit·consume de eventos; FeatureFlag `inRollout`/`abTest` con buckets crc32. La profundidad de cola no está hecha)
20. **Cierre del bucle de datos de WebSocket**: confirmar la persistencia de clasificaciones/chat
21. **Alineación de documentación**: corregir el número de tablas/servicios/descripción de la capa compartida, alinear la documentación de API con la implementación, completar CHANGELOG — ✅ Completado (2026-08-18: ver `docs/CHANGELOG.md`, FEATURES/VERSIONS/PROJECT-PLAN/informe de auditoría §10)

---

## 四、Puertas de calidad (colaboración en equipo)

- Cada cambio de código: todas las pruebas de admin `vendor/bin/phpunit` deben pasar (quitar el `|| echo warning`)
- Las rutas sensibles nuevas (pago/retiro/autenticación) deben incluir pruebas
- Al modificar common/model hay que sincronizar ambos lados admin+service (hasta que la capa compartida esté implementada)
- Puntos clave recomendados por el informe de revisión: firma de ProviderAuth, cifrado AES, SQL escrito a mano de ProbabilityService

## 五、Equipo

El equipo de game-platform (6 miembros: researcher/architect/backend-dev/frontend-dev/tester/reviewer) está listo y puede ejecutar P0 directamente.
