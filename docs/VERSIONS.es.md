# Comparación de versiones
<!-- lang-nav -->

Languages: [中文](VERSIONS.md) · [English](VERSIONS.en.md) · [한국어](VERSIONS.ko.md) · [Русский](VERSIONS.ru.md) · [Deutsch](VERSIONS.de.md) · [Français](VERSIONS.fr.md) · **Español** · [Português](VERSIONS.pt.md) · [हिन्दी](VERSIONS.hi.md) · [العربية](VERSIONS.ar.md) · [বাংলা](VERSIONS.bn.md) · [Bahasa Indonesia](VERSIONS.id.md) · [日本語](VERSIONS.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Resumen

| | Edición básica (Lite) | Edición estándar (Standard) | Edición completa (Full) |
|------|------|------|------|
| Tablas de datos (install.sql) | 19 | 29 | **43** (no los 52 que llegó a decir la documentación) |
| Endpoints de API | 38 | 54 | ~149 (admin+service, incluye Webhook/Provider) |
| Controladores backend | 14 | 22 | admin 32 + service 30 |
| Modelos de datos | No compartidos | No compartidos | **admin 46 / service 44, una copia cada uno, sin capa compartida** |
| Service compartido | Sin capa compartida | Sin capa compartida | `packages/platform-common`, un único paquete compartido |
| Páginas del frontend de Admin | 11 | 13 | 15 |
| Páginas del frontend de Platform | 8 | 10 | 10 |
| HarmonyOS (admin) | - | Login + dashboard | **8 páginas** `admin/apps/harmonyos/` |
| HarmonyOS (lado C) | - | - | **5 páginas** `apps/harmonyos/` (login/lobby de juegos/detalle/billetera/perfil) |
| Servicios Docker | - | - | **7** (nginx/admin/service/leaderboard-ws/mysql/redis/elasticsearch) |
| Casos de prueba | 60 | 60 | admin ~132; service 3 |

---

## Autenticación de usuarios

| Función | Edición básica | Edición estándar | Edición completa |
|------|--------|--------|--------|
| Registro/inicio de sesión con nombre de usuario y contraseña | ✓ | ✓ | ✓ |
| Token JWT (2h+14d) | ✓ | ✓ | ✓ |
| Captcha de clic | stub | stub | ✓ poster-php |
| Bloqueo de cuenta (5 veces/15 minutos) | ✓ | ✓ | ✓ |
| Límite de sesiones (3 concurrentes) | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ 7 plataformas (incluye X/MS/LinkedIn/GitHub) |
| Autenticación de dos factores TOTP 2FA | - | - | ✓ |
| Exportación/eliminación de datos GDPR | - | - | ✓ |

---

## Billetera y fondos

| Función | Edición básica | Edición estándar | Edición completa |
|------|--------|--------|--------|
| Billetera de moneda de plataforma | ✓ | ✓ | ✓ |
| Bloqueo optimista de billetera | ✓ | ✓ | ✓ |
| Registro de movimientos | ✓ | ✓ | ✓ |
| Billetera de moneda de juego | ✓ | ✓ | ✓ |
| Creación de órdenes de recarga (rellena checkout_url/expires_at al crearse) | ✓ | ✓ | ✓ |
| Acreditación automática por callback de recarga | - | ✓ manual | ✓ verificación de firma Stripe (incl. Alipay/WeChat Pay)/PayPal/NowPayments IPN/Coinbase webhook |
| Cotización/compra/venta de conversión | ✓ | ✓ | ✓ |
| Ingreso por diferencial de conversión | ✓ | ✓ | ✓ |
| Solicitud de retiro | ✓ | ✓ | ✓ |
| Interruptor global de retiros | ✓ | ✓ | ✓ |
| Revisión de retiros | ✓ manual | ✓ manual | ✓ por lotes + manual |
| Límites escalonados KYC | - | ✓ 3 niveles | ✓ |
| Comisión de retiro | - | - | ✓ |
| Recibo PDF | - | - | ✓ |

---

## Gestión de juegos

| Función | Edición básica | Edición estándar | Edición completa |
|------|--------|--------|--------|
| CRUD de juegos | ✓ | ✓ | ✓ |
| Gestión de monedas de juego | ✓ | ✓ | ✓ |
| Lista/detalle de juegos en el lado C | ✓ | ✓ | ✓ |
| Inicio de juegos | ✓ | ✓ | ✓ |
| Categorías de juegos (10 tipos) | - | - | ✓ |
| Filtro por categoría | - | - | ✓ |
| Gestión de servidores de juego | - | ✓ | ✓ |
| Seguimiento de registros de juego | - | ✓ | ✓ |
| Búsqueda de texto completo ES | - | - | ✓ |
| Sugerencias de búsqueda | - | - | ✓ |
| SDK de Provider de juegos de terceros | - | - | ✓ HMAC-SHA256 |

---

## Herramientas de operación

| Función | Edición básica | Edición estándar | Edición completa |
|------|--------|--------|--------|
| Gestión de anuncios | ✓ | ✓ | ✓ |
| Dashboard | ✓ panel de admin | ✓ panel de admin | ✓ admin + platform |
| Exportación Excel | ✓ | ✓ | ✓ |
| Exportación PDF | ✓ | ✓ | ✓ |
| Gráficos reales del dashboard | - | - | ✓ fl_chart |
| Sistema de cupones | - | - | ✓ |
| Clasificaciones (día/semana/mes/total) | - | - | ✓ caché Redis |
| Clasificación en tiempo real por WebSocket | - | - | ✓ puerto 8789 |
| Sistema de notificaciones (internas + email) | - | - | ✓ |
| Comisión por recomendación | - | - | ✓ |
| Instantáneas de estadísticas diarias | - | ✓ | ✓ |
| Informes de datos (resumen/diario/CSV) | - | - | ✓ |
| Estadísticas de la plataforma (lado C) | - | - | ✓ |
| Seguimiento de ingresos de la plataforma | - | - | ✓ |

---

## Seguridad y cumplimiento

| Función | Edición básica | Edición estándar | Edición completa |
|------|--------|--------|--------|
| Defensa en profundidad de 18 capas | ✓ | ✓ | ✓ |
| Control de permisos RBAC | ✓ | ✓ | ✓ |
| Registro de auditoría de operaciones | ✓ | ✓ | ✓ |
| Detección de origen en 8 plataformas | ✓ | ✓ | ✓ |
| Limitación por ventana deslizante de Redis | ✓ | ✓ | ✓ |
| Verificación de identidad KYC | - | ✓ | ✓ |
| Motor de control de riesgos (4 reglas) | - | ✓ | ✓ |
| Verificación de firma de callbacks de pago | - | - | ✓ |

---

## Internacionalización

| Función | Edición básica | Edición estándar | Edición completa |
|------|--------|--------|--------|
| Soporte multilingüe | chino/inglés | 4 idiomas | 4 idiomas |
| Tabla de traducciones + caché | ✓ | ✓ | ✓ |
| Detección automática de idioma | ✓ | ✓ | ✓ |
| Configuración diferenciada por país | - | - | ✓ 8 países |

---

## Despliegue y operación

| Función | Edición básica | Edición estándar | Edición completa |
|------|--------|--------|--------|
| Despliegue independiente de webman | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ 7 servicios |
| Proxy inverso Nginx | - | - | ✓ |
| CDN | - | - | ✓ Integración de 5 proveedores + configuración en admin/activar-desactivar/prueba de conectividad (credenciales cifradas, service lee solo de la DB) |
| Tareas programadas Crontab | - | ✓ | ✓ |
| Monitorización Prometheus | ✓ | ✓ | ✓ `/metrics` gauges de negocio + contadores de eventos |
| Verificación de salud | ✓ | ✓ | ✓ |
| Documentación en línea hg/apidoc | - | - | ✓ 41 controladores |

---

## Clientes

| Función | Edición básica | Edición estándar | Edición completa |
|------|--------|--------|--------|
| Panel de administración Flutter Web PC | ✓ 5 páginas | ✓ 11 páginas | ✓ 15 páginas |
| Plataforma de usuario Flutter Web PC | ✓ 5 páginas | ✓ 8 páginas | ✓ 10 páginas |
| HarmonyOS admin | - | ✓ login + dashboard | ✓ 8 páginas `admin/apps/harmonyos/` |
| HarmonyOS lado C | - | - | ✓ 5 páginas `apps/harmonyos/` |

---

## Tablas de la base de datos

### Edición básica (19 tablas)
```
Panel de administración (7):  game_admin_user, game_admin_role, game_admin_permission,
               game_admin_user_role, game_admin_role_permission,
               game_operation_log, game_system_config

Núcleo de la plataforma (12): game_user, game_user_wallet, game_user_game_wallet,
               game_game, game_game_currency, game_deposit_order,
               game_withdraw_order, game_exchange_record, game_transaction,
               game_payment_method, game_announcement, game-platform_config
```

### Nuevas de la edición estándar (10 tablas)
```
game_user_identity, game_user_oauth, game_user_payment_account,
game_user_session, game_game_server, game_game_play_log,
game_withdraw_limit, game_risk_rule, game_risk_log, game_stat_daily
```

### Nuevas de la edición completa (13 tablas)
```
game_game_category, game_game_category_rel, game_leaderboard,
game_coupon, game_user_coupon, game_language, game_translation,
game_country_config, game-platform_revenue,
game_notification, game_referral, game_referral_reward, game_user_2fa
```

---

## Endpoints de API

| Módulo | Edición básica | Edición estándar | Edición completa |
|------|--------|--------|--------|
| Autenticación | 3 | 3 | 7 (+OAuth×2 +2FA×5) |
| Billetera | 2 | 2 | 3 (+callback de recarga) |
| Conversión | 4 | 4 | 4 |
| Retiros | 2 | 2 | 8 (+por lotes+límites+revisión) |
| Juegos | 3 | 4 | 7 (+servidores+registros+búsqueda) |
| Usuarios | 2 | 2 | 7 (+KYC+GDPR+privacidad) |
| Panel de administración | 18 | 25 | 79 |
| Herramientas de operación | - | - | 30 (+clasificaciones+cupones+notificaciones+recomendación) |
| Internacionalización | 2 | 2 | 4 (+configuración por país) |
| **Total** | **38** | **54** | **129** |

---

## Extensión de ecosistema (v2.0) — Nuevas funciones

| Función | Descripción |
|------|------|
| Capa de abstracción GameProvider | SelfProvider (transacción DB) + ThirdPartyProvider (HTTP+firma) |
| Puerta de enlace de la Provider API | callbacks balance/bet/settle/refund + middleware ProviderAuth |
| Sistema de tickets | creación/respuesta en el lado C + gestión/asignación/cierre en el panel admin |
| Verificación de email | código de 6 dígitos, expiración en Redis de 10 minutos, límite de reenvío de 60 segundos |
| Notificaciones push | PushService (FCM/APNs/华为推送) |
| Sistema VIP | 5 niveles, acumulación de experiencia, subida automática, descuento de conversión, reducción de retiro, bonificación de tipo de cambio |
| Sistema de logros | 12 logros integrados, detección basada en eventos, seguimiento de progreso |
| Sistema de amigos | solicitud/aceptación/rechazo/eliminación/búsqueda |
| Mensajes privados/chat | REST + mensajes en tiempo real WebSocket (puerto 8790) |
| Bus de eventos | Redis Pub/Sub; emit INCR `metrics:event_*`; proceso de consumo `EventConsumer` implementado |
| Feature flags | FeatureFlag basado en DB; `inRollout`/`abTest` leen `feature.{name}_percent` |
| Webhook | - | - | ✓ 7 tipos de eventos + entrega Pub/Sub |
| Chat | - | - | ✓ REST+WebSocket :8791 |
| Sistema de torneos | - | - | ✓ FeatureFlag+tournament |
| Condiciones de cupones | - | - | ✓ min_deposit/first_user/game_id |
| Comisión multinivel | - | - | ✓ reparto de segundo nivel |
| Documentación SDK | - | - | ✓ PHP/Go/Python |
| Análisis avanzado | retención/D1-D30, embudo de conversión, ARPU/ARPPU |

### Nuevas tablas de datos (10 tablas)
```
game_ticket, game_ticket_reply, game_device_token,
game_vip_level, game_user_vip, game_exp_log,
game_achievement, game_user_achievement,
game_friend, game_message
```

### Nuevos endpoints de la Provider API (4)
```
POST /api/provider/balance  — Consultar saldo
POST /api/provider/bet      — Notificar apuesta
POST /api/provider/settle   — Notificar liquidación
POST /api/provider/refund   — Notificar reembolso
```

### Nuevos endpoints del lado C (8)
```
POST /api/verify/send-email    — Enviar código de verificación de email
POST /api/verify/confirm-email — Confirmar email
GET  /api/ticket/list             — Lista de tickets
POST /api/ticket/create           — Crear ticket
GET  /api/ticket/{id}             — Detalle de ticket
POST /api/ticket/{id}/reply       — Responder ticket
GET  /api/user/vip-status         — Estado VIP
GET  /api/user/achievements       — Lista de logros
```

### Nuevos endpoints del panel de administración (6)
```
GET  /admin/ticket/list          — Lista de tickets
GET  /admin/ticket/{id}          — Detalle de ticket
POST /admin/ticket/{id}/reply    — Responder ticket
POST /admin/ticket/{id}/close    — Cerrar ticket
POST /admin/ticket/{id}/assign   — Designar responsable
GET  /admin/analytics/retention  — Análisis de retención
GET  /admin/analytics/funnel     — Embudo de conversión
GET  /admin/analytics/arpu       — Tendencia ARPU
GET  /admin/analytics/economy    — Indicadores económicos
```
