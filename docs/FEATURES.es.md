# Documento de funcionalidades
<!-- lang-nav -->

Languages: [中文](FEATURES.md) · [English](FEATURES.en.md) · [한국어](FEATURES.ko.md) · [Русский](FEATURES.ru.md) · [Deutsch](FEATURES.de.md) · [Français](FEATURES.fr.md) · **Español** · [Português](FEATURES.pt.md) · [हिन्दी](FEATURES.hi.md) · [العربية](FEATURES.ar.md) · [বাংলা](FEATURES.bn.md) · [Bahasa Indonesia](FEATURES.id.md) · [日本語](FEATURES.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Resumen de funcionalidades

### Versión básica (MVP) — Completada

| Dominio | Función | Estado |
|----|------|------|
| Usuarios | Registro/inicio de sesión/JWT/captcha | Completada |
| Billetera | Saldo de moneda de plataforma/consulta de movimientos | Completada |
| Recarga | Creación de órdenes de recarga (Stripe 125+ APM locales, incl. Alipay/WeChat Pay APM / NOWPayments USDT TRC20·ERC20 / Coinbase USDC·BTC·ETH / PayPal) | Completada |
| Conversión | Moneda de plataforma ⇄ moneda de juego (tipo de cambio fijo + diferencia) | Completada |
| Retiro | Solicitud/consulta/interruptor global/revisión automática/revisión manual | Completada |
| Juegos | CRUD en el backend/gestión de monedas/lista C/detalle/inicio | Completada |
| Gestión | Gestión de juegos/revisión de retiros/gestión de usuarios/gestión de pagos/gestión de anuncios | Completada |
| Panel | Dashboard de la plataforma (DAU/movimientos/ingresos/rankings) | Completada |
| Exportación | Exportación Excel de usuarios/movimientos/retiros | Completada |
| Internacionalización | Cambio chino/inglés, tabla de traducciones, middleware de detección de idioma | Completada |
| Frontend | Panel de administración Flutter PC + plataforma de usuarios C (con i18n) | Completada |

### Versión estándar — Completada

| Dominio | Función | Estado |
|----|------|------|
| Usuarios | Inicio de sesión OAuth (Google/Facebook/Apple/Twitter/Microsoft/LinkedIn/GitHub) | Completada |
| Pagos | Callback automático de múltiples canales (Stripe incl. Alipay/WeChat Pay APM / PayPal / NOWPayments IPN / Coinbase Webhook) | Completada |
| Juegos | Gestión de servidores, seguimiento de registros de juego | Completada |
| Retiros | Límites escalonados KYC (default/verified/vip) + comisión | Completada |
| KYC | Solicitud y revisión de verificación de identidad | Completada |
| Control de riesgos | Lista negra de IP/alertas de importes grandes/detección de frecuencia/velocidad | Completada |
| Estadísticas | Instantáneas de estadísticas diarias (usuarios/recargas/retiros/conversiones/juegos) | Completada |
| Frontend | Admin: revisión KYC + logs de riesgos / Platform: OAuth + KYC + registros de juego | Completada |

### Versión completa — Completada

| Dominio | Función | Estado |
|----|------|------|
| Lobby de juegos | 10 categorías predefinidas, filtro por categoría, relación juego-categoría | Completada |
| Clasificaciones | Ranking diario/semanal/mensual/total, caché Redis, múltiples métricas | Completada |
| Cupones | Importe fijo + descuento porcentual, limitados por tiempo y cantidad, seguimiento de reclamo/uso | Completada |
| Configuración de países | 8 países predefinidos, métodos de pago/retiro diferenciados, importe mínimo de recarga | Completada |
| Estadísticas | Instantáneas diarias + seguimiento de ingresos de la plataforma | Completada |
| Búsqueda | Búsqueda de texto completo Elasticsearch (integrada a nivel de modelo) | Completada |

### Actualización a nivel producción — Completada

| Dominio | Función | Estado |
|----|------|------|
| OAuth | Intercambio real de tokens Google/Facebook/Apple | Completada |
| Pagos | Verificación de firma de callbacks (Webhook Stripe incl. Alipay/WeChat Pay APM, Webhook PayPal, NOWPayments IPN HMAC-SHA512, Coinbase HMAC-SHA256 base64) | Completada |
| Captcha | Captcha de clic poster-php | Completada |
| Notificaciones | Mensajes internos + email, notificaciones automáticas de recarga/retiro/KYC/cupón | Completada |
| 2FA | Google Authenticator TOTP + códigos de recuperación de respaldo | Completada |
| Recomendación | Código de recomendación, recompensa de registro, comisión por recarga | Completada |
| Búsqueda | API de búsqueda ES + sugerencias de juegos + fallback LIKE | Completada |
| Clasificaciones | Push en tiempo real por WebSocket (puerto 8789) | Completada |
| CDN | Integración de cinco proveedores (Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS carga + purga + precarga) | Completada |
| Administración CDN | Configuración de los cinco proveedores en el panel (credenciales cifradas/activación-desactivación/prueba de conexión HeadBucket), el servicio solo lee de la base de datos | Completada |
| Despliegue | Docker Compose 7 servicios + proxy inverso Nginx | Completada |
| Datos | Análisis de agregación en tiempo real MySQL + cálculo de probabilidad conjunta/condicional | Completada |
| HarmonyOS | admin 8 páginas; el lado C `apps/harmonyos/` ya implementa login/lobby/detalle/billetera/perfil (apunta a 8788) | Parcialmente completada (el proyecto compila; en dispositivo real hay que cambiar la IP) |
| Documentación de API | Documentación interactiva hg/apidoc | Completada |
| Instalación en un clic | Asistente de instalación en el navegador: crear admin, actualizar BD existente, install.lock evita reinstalación | Completada |
| Tolerancia a fallos | CircuitBreaker + Retry + interruptor de degradación feature.provider_mock | Completada |
| Métodos de pago | CRUD admin + visibilidad por país + rango de importes + restricción de moneda | Completada |
| CI | tag autoincremental al push + GitHub Release | Completada |

### Extensión de ecosistema (v2.0) — Recién completada

| Dominio | Función | Estado |
|----|------|------|
| Integración de juegos | Capa de abstracción GameProvider (Self/ThirdParty) + firma HMAC-SHA256 | Completada |
| Callback de juegos | Puerta de enlace Provider API (balance/bet/settle/refund) + middleware ProviderAuth | Completada |
| Sesiones de juego | Heartbeat Redis + timeout de 15 minutos con liquidación automática + GameSessionService | Completada |
| Sistema de tickets | Creación/respuesta en el lado C + gestión/asignación/cierre en el lado admin, 5 tipos de ticket | Completada |
| Verificación de email | Código de 6 dígitos, expiración Redis de 10 minutos, límite de reenvío de 60 segundos | Completada |
| Notificaciones push | PushService (FCM/APNs/华为推送) + modelo DeviceToken | Completada |
| Sistema VIP | 5 niveles (普通/白银/黄金/铂金/钻石) + puntos de experiencia + subida automática | Completada |
| Beneficios VIP | Descuento de conversión 2-15%, reducción de comisión de retiro 10-100%, bonificación de tipo de cambio 0.1-1.0% | Completada |
| Sistema de logros | 12 logros integrados; EventConsumer → detección basada en eventos de AchievementService y experiencia VIP | Completada |
| Sistema de amigos | Solicitud/aceptación/rechazo/eliminación/búsqueda, estados pending/accepted/blocked | Completada |
| Mensajes privados/chat | Mensajes privados REST + mensajes en tiempo real WebSocket (puerto 8790), solo entre amigos | Completada |
| Bus de eventos | Redis Pub/Sub; emit + EventConsumer consume logros/Webhook + INCR de metrics | Completada |
| Feature flags | FeatureFlag basado en DB; `inRollout`/`abTest` leen `feature.{name}_percent` con buckets crc32 | Completada |
| Análisis avanzado | Retención/D1-D30, embudo de conversión, ARPU/ARPPU, indicadores económicos de monedas de juego (agregación en tiempo real MySQL) | Completada |
| Webhook | Gestión de suscripciones + entrega de eventos Redis Pub/Sub, 7 eventos opcionales | Completada |
| Chat | Mensajes privados REST + mensajes en tiempo real WebSocket (puerto 8791), solo entre amigos | Completada |
| Torneos | Creación/list/detail/join, FeatureFlag, clasificaciones, límite de participantes | Completada |
| Comisión multinivel | Reparto de segundo nivel, modelo ReferralCommission, tasa de comisión configurable | Completada |
| Condiciones de cupones | Tres condiciones: min_deposit/first_user_only/game_id | Completada |
| Documentación SDK | Documentación de integración de Provider (ejemplos PHP/Go/Python + 4 endpoints de API) | Completada |
| Minijuego | Farm Match-3 P0 (motor de dominio + diseño de 4 niveles, pruebas unitarias TypeScript/Vite/Vitest) | Completada |

## 2. Funcionalidades del usuario C

### 2.1 Recorrido del usuario

```
注册 → 登录 → 邮箱/手机验证 → 浏览游戏大厅 → 进入游戏详情
                                           ↓
查看钱包 ← 玩游戏 ← 兑换游戏币 (VIP折扣) ← 充值平台币
    ↓
  提现 (VIP手续费减免) → 后台审核 → 到账
    ↓
好友系统 → 私信聊天 → 排行榜竞技 → 成就追踪
    ↓
工单支持
```

### 2.2 Interfaces de API

| Método | Ruta | Descripción | Autenticación |
|------|------|------|------|
| POST | /api/auth/register | Registro de usuario | No |
| POST | /api/auth/login | Inicio de sesión de usuario | No |
| POST | /api/auth/refresh | Refrescar Token | No |
| GET | /api/game/list | Lista de juegos | No |
| GET | /api/game/detail/{id} | Detalle de juego | No |
| GET | /api/announcement/list | Lista de anuncios | No |
| GET | /api/wallet/info | Saldo de la billetera | Sí |
| GET | /api/wallet/transactions | Registros de movimientos | Sí |
| POST | /api/deposit/create | Crear orden de recarga | Sí |
| GET | /api/payment/methods | Lista de métodos de pago (según país) | Sí |
| POST | /api/exchange/quote | Cotización de conversión (descuento VIP) | Sí |
| POST | /api/exchange/buy | Comprar moneda de juego | Sí |
| POST | /api/exchange/sell | Vender moneda de juego | Sí |
| POST | /api/withdraw/apply | Solicitud de retiro (reducción VIP) | Sí |
| POST | /api/game/launch | Iniciar juego | Sí |
| GET | /api/game/play-logs | Registros de juego | Sí |
| POST | /api/referral/apply | Usar código de recomendación | Sí |
| POST | /api/verify/send-email | Enviar código de verificación de email | Sí |
| POST | /api/verify/confirm-email | Confirmar email | Sí |
| GET | /api/ticket/list | Lista de tickets | Sí |
| POST | /api/ticket/create | Crear ticket | Sí |
| POST | /api/ticket/{id}/reply | Responder ticket | Sí |

## 3. Funcionalidades del panel de administración

### 3.1 Interfaces de API (nuevas)

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/dashboard/platform | Datos del dashboard de la plataforma |
| GET | /admin/analytics/overview | Resumen de la plataforma (agregación en tiempo real MySQL) |
| GET | /admin/analytics/game-ranking | Ranking de juegos |
| GET | /admin/analytics/dau-trend | Tendencia DAU |
| GET | /admin/analytics/hourly-trend | Tendencia por hora |
| GET | /admin/analytics/action-distribution | Distribución de acciones |
| GET | /admin/analytics/revenue | Análisis de ingresos |
| GET | /admin/analytics/conversion | Tasa de conversión de juegos |
| GET | /admin/analytics/probability | Probabilidad conjunta/condicional |
| GET | /admin/analytics/retention | Análisis de retención D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Embudo de conversión |
| GET | /admin/analytics/arpu | Tendencia ARPU/ARPPU |
| GET | /admin/analytics/economy | Indicadores económicos de monedas de juego |
| GET | /admin/game/list | Lista de juegos |
| POST | /admin/game/create | Crear juego (incluye provider_config) |
| PUT | /admin/game/{id} | Editar juego |
| GET | /admin/withdraw/orders | Lista de órdenes de retiro |
| PUT | /admin/withdraw/review | Revisar retiro |
| GET | /admin/ticket/list | Lista de tickets |
| GET | /admin/ticket/{id} | Detalle de ticket |
| POST | /admin/ticket/{id}/reply | Responder ticket |
| POST | /admin/ticket/{id}/close | Cerrar ticket |
| POST | /admin/ticket/{id}/assign | Asignar responsable |

## 4. Provider API (callback del proveedor de juegos)

| Método | Ruta | Descripción | Autenticación |
|------|------|------|------|
| POST | /api/provider/balance | Consultar saldo del usuario | HMAC-SHA256 |
| POST | /api/provider/bet | Notificar apuesta | HMAC-SHA256 |
| POST | /api/provider/settle | Notificar liquidación | HMAC-SHA256 |
| POST | /api/provider/refund | Notificar reembolso | HMAC-SHA256 |

Algoritmo de firma: `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)`
Cabeceras de solicitud: `X-Game-Id` + `X-Timestamp` + `X-Signature`
Ventana de tiempo: 5 minutos

## 5. Sistema VIP

| Nivel | EXP acumulada | Descuento de conversión | Reducción de comisión de retiro | Bonificación de tipo de cambio |
|------|---------|---------|-------------|---------|
| 普通 | 0 | 0% | 0% | Base |
| 白银 | 500 | 2% | 10% | +0.1% |
| 黄金 | 2,500 | 5% | 30% | +0.3% |
| 铂金 | 12,500 | 10% | 50% | +0.5% |
| 钻石 | 62,500 | 15% | 100% | +1.0% |

### Obtención de experiencia

| Acción | EXP |
|------|-----|
| Recargar 1 unidad | 10 |
| Inicio de sesión diario | 5 |
| Completar KYC | 50 |
| Invitar a un nuevo usuario | 100 |
| Logro alcanzado | 10-100 |

## 6. Lista de logros

| Logro | Condición | Puntos |
|------|------|------|
| First Deposit | Primera recarga | 20 |
| Century Club | 100 de recarga acumulada | 50 |
| High Roller | 1000 de recarga acumulada | 100 |
| Trader | Primera conversión | 20 |
| Day Trader | 100 conversiones acumuladas | 100 |
| Explorer | Jugar a 3 juegos | 30 |
| Adventurer | Jugar a 5 juegos | 50 |
| Conqueror | Jugar a 10 juegos | 100 |
| Weekly Warrior | Inicio de sesión 7 días consecutivos | 30 |
| Monthly Master | Inicio de sesión 30 días consecutivos | 100 |
| Connector | Invitar a 1 amigo | 30 |
| Influencer | Invitar a 10 amigos | 100 |

## 7. Lista de tablas de la base de datos

### Nuevas de la extensión de ecosistema (10 tablas)

| Nombre de tabla | Descripción | Características clave |
|------|------|---------|
| game_ticket | Tickets | Índice user_id+type+status, assigned_to |
| game_ticket_reply | Respuestas de tickets | Índice ticket_id, distinción por is_admin |
| game_device_token | Tokens de dispositivo | Índice único user_id+platform+token |
| game_vip_level | Definición de niveles VIP | Índice único level, beneficios JSON |
| game_user_vip | Registros VIP de usuarios | Índice único user_id, level+exp+total_exp |
| game_exp_log | Registros de experiencia | Índice combinado user_id+source |
| game_achievement | Definiciones de logros | Índice único key, condition_json JSON |
| game_user_achievement | Logros de usuarios | Índice único user_id+achievement_id |
| game_friend | Relaciones de amistad | Índice único user_id+friend_id |
| game_message | Mensajes privados | from_user_id+to_user_id / to_user_id+is_read |

### Cambios de estructura de tablas

| Nombre de tabla | Cambio |
|------|------|
| game_game | +provider_config (JSON) |
| game_game_play_log | +round_id, +bet_amount, +win_amount |

**Total: 43 tablas en install.sql** (las 10 de la extensión de ecosistema están en `install/`, no se han fusionado en install.sql). Los modelos no están compartidos: admin 46 / service 44, una copia en cada uno.

## 8. Cobertura de pruebas

| Archivo de pruebas | N.º de casos | Cobertura |
|---------|--------|---------|
| PlatformTest | 56 | Precisión bcmath/cálculo de conversión/comisión de retiro/límites/control de riesgos/cupones/KYC/i18n |
| BackendEnhancementTest | 23 | Servicio de cifrado/Hashids/Snowflake |
| CaptchaTest | 7 | Generación/validación de captcha |
| EncryptionServiceTest | 6 | Cifrado AES/desenmascarado |
| EnvConfigTest | 4 | Configuración de variables de entorno |
| HashidsServiceTest | 8 | Viaje de ida y vuelta de codificación/decodificación de IDs |
| SnowflakeServiceTest | 6 | Unicidad de la generación de IDs |

**Total: admin ~132 casos / 8 archivos; service 3 casos (WebhookUrlSafety + EventBusMessageFormat). service no se incluye en el bloqueo por fallo del CI.**

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
