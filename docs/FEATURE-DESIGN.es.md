# Documento de diseño de funcionalidades
<!-- lang-nav -->

Languages: [中文](FEATURE-DESIGN.md) · [English](FEATURE-DESIGN.en.md) · [한국어](FEATURE-DESIGN.ko.md) · [Русский](FEATURE-DESIGN.ru.md) · [Deutsch](FEATURE-DESIGN.de.md) · [Français](FEATURE-DESIGN.fr.md) · **Español** · [Português](FEATURE-DESIGN.pt.md) · [हिन्दी](FEATURE-DESIGN.hi.md) · [العربية](FEATURE-DESIGN.ar.md) · [বাংলা](FEATURE-DESIGN.bn.md) · [Bahasa Indonesia](FEATURE-DESIGN.id.md) · [日本語](FEATURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Diseño del sistema de monedas

### 1.1 Modelo de monedas de tres capas

```
第1层: 法币 (USD / CNY / EUR / JPY ...)
       ↕ 充值/提现（按汇率兑换）
第2层: 平台币（统一，精度 decimal(18,4)）
       ↕ 兑换（含汇率 + 平台抽成差价）
第3层: 游戏币（每种游戏独立，独立汇率）
```

### 1.2 Moneda de plataforma

- Unidad de valoración unificada dentro de la plataforma
- Precisión: `DECIMAL(18,4)`, unidad mínima 0.0001
- Se obtiene recargando con moneda fiduciaria; se puede convertir a cualquier moneda de juego
- La moneda de juego también puede volver a convertirse a moneda de plataforma y luego retirarse como moneda fiduciaria
- La plataforma cobra la diferencia de conversión como fuente de ingresos

### 1.3 Moneda de juego

- Cada juego puede tener varias monedas (por ejemplo, monedas de oro, diamantes, puntos)
- Cada moneda configura de forma independiente su tipo de cambio frente a la moneda de plataforma (`exchange_rate`)
- Cada moneda configura de forma independiente el porcentaje de comisión de la plataforma (`spread_pct`)
- Admite configuración de límites mínimo/máximo de conversión (`min_exchange` / `max_exchange`)

### 1.4 Fórmulas de conversión

**Comprar moneda de juego (moneda de plataforma → moneda de juego):**
```
游戏币到账 = 平台币数量 × exchange_rate × (1 - spread_pct / 100)
```

**Vender moneda de juego (moneda de juego → moneda de plataforma):**
```
平台币到账 = 游戏币数量 ÷ exchange_rate × (1 - spread_pct / 100)
```

**Ejemplo:**
- exchange_rate = 100 (1 moneda de plataforma = 100 monedas de juego)
- spread_pct = 5% (la plataforma cobra un 5% de diferencia)
- El usuario compra con 10 monedas de plataforma: (10 × 100 × 0.95) = 950 monedas de juego
- El usuario vende 950 monedas de juego: (950 ÷ 100 × 0.95) = 9.025 monedas de plataforma
- Ingreso de la plataforma: 10 - 9.025 = 0.975 monedas de plataforma

## 2. Diseño de la billetera

### 2.1 Billetera de moneda de plataforma (game_user_wallet)

Se crea automáticamente al registrar al usuario; el saldo inicial es 0.

| Campo | Descripción |
|------|------|
| balance | Saldo disponible (recargable, retirable y convertible) |
| frozen_balance | Saldo congelado (reservado, por ejemplo, en retiros en curso) |
| total_earned | Ingresos acumulados |
| total_spent | Gastos acumulados |
| version | Número de versión de bloqueo optimista (se incrementa en cada actualización) |

### 2.2 Billetera de moneda de juego (game_user_game_wallet)

Única en las tres dimensiones usuario + juego + moneda. Se crea automáticamente en la primera conversión.

### 2.3 Seguridad de concurrencia

Se usa el bloqueo optimista para prevenir problemas de concurrencia:

```php
// 更新时检查版本号
$updated = Wallet::where('id', $wallet->id)
    ->where('version', $wallet->version)
    ->update([
        'balance' => bcadd($wallet->balance, $amount, 4),
        'version' => $wallet->version + 1,
    ]);

// 更新失败（版本号已变）→ 重试，最多5次
```

## 3. Diseño del sistema de retiros

### 3.1 Control multicapa

```
第1层: 全局提现开关
       ├─ 关闭 → 所有提现拒绝，用于紧急风控
       └─ 开启 → 进入第2层检查

第2层: 限额检查
       ├─ 单笔最低金额 (min_amount)
       ├─ 单笔最高金额 (max_amount)
       └─ 每日累计限额 (daily_limit)

第3层: 审核流程
       ├─ 金额 < 自动审核阈值 → 自动通过
       └─ 金额 >= 自动审核阈值 → 人工审核 → 通过/拒绝
```

### 3.2 Máquina de estados del retiro

```
pending (待审核)
  ├─→ approved (已通过) → completed (已完成)
  └─→ rejected (已拒绝) → 余额退回 + 退款流水
```

### 3.3 Control desde el panel de administración

- **Botón de interruptor global**: habilita/deshabilita con un clic todos los retiros de usuarios
- **Cola de revisión**: lista de pendientes de revisión ordenada por tiempo, con botones de aprobar/rechazar
- **Configuración de límites**: ajuste visual de cada parámetro de límite

## 4. Diseño de la recarga

### 4.1 Flujo de recarga

```
1. 用户选择支付方式和金额
2. 平台创建充值订单 (status=pending, 生成唯一 order_no)
3. 跳转第三方支付页面
4. 用户完成支付
5. 第三方回调通知平台 (POST /api/payment/callback)
6. 平台验签 → 更新订单 (status=confirmed)
7. 平台币到账 → 记录流水
```

### 4.2 Métodos de pago

| Tipo | Proveedor | Descripción |
|------|--------|------|
| Moneda fiduciaria | Stripe | Pago con tarjeta de crédito internacional |
| Moneda fiduciaria | PayPal | Billetera electrónica global |
| Moneda fiduciaria | Alipay | Alipay (internacional, vía Stripe Checkout APM) |
| Moneda fiduciaria | WeChat Pay | WeChat Pay (internacional, vía Stripe Checkout APM) |
| Criptomoneda | USDT-TRC20 | USDT en la cadena Tron |

La versión básica integra primero un único método de pago (por ejemplo, Stripe); la versión estándar amplía a todos los canales.

## 5. Diseño de integración de juegos

### 5.1 Juegos propios

Los juegos propios se integran directamente en la plataforma y comparten el sistema de usuarios y la billetera:

- El juego consulta el saldo de moneda de juego del usuario mediante la API interna
- La liquidación del juego descuenta/incrementa la moneda de juego mediante la API interna
- No se necesita verificación de firma adicional

### 5.2 Juegos de terceros

Los juegos de terceros se integran mediante SDK/API:

```
平台侧:
  1. 用户点击"进入游戏"
  2. 平台生成签名（user_id + timestamp + api_secret → HMAC-SHA256）
  3. 302跳转或iframe加载游戏URL（携带签名参数）

游戏侧:
  4. 验签 → 建立游戏会话
  5. 查询余额：GET /api/game/balance?user_id=...&sign=...
  6. 结算回调：POST /api/game/callback {user_id, amount, type, sign}
  7. 平台验签 → 更新余额 → 记录流水 → 返回结果
```

### 5.3 Algoritmo de firma

```
signature = HMAC-SHA256(
    secret = api_secret,
    message = user_id + "|" + timestamp + "|" + amount + "|" + nonce,
)
```

Condiciones de verificación:
- Firma correcta
- Marca de tiempo dentro de ±60s (defensa contra replay attack)
- nonce no usado (registrado en Redis, expira en 60s)
- La IP de la solicitud está en la lista blanca

## 6. Diseño de permisos

### 6.1 Roles predefinidos

| Rol | Alcance de permisos |
|------|---------|
| Superadministrador | * (todos los permisos) |
| Operador de juegos | Gestión de juegos, gestión de anuncios, dashboard |
| Revisión financiera | Revisión de retiros, gestión de pagos, consulta de movimientos |
| Atención al cliente | Consulta de usuarios del lado C, consulta de órdenes de recarga |

### 6.2 Granularidad de permisos

```
{method}.{path}

示例:
  get.admin/game/list      → 查看游戏列表
  post.admin/game/create   → 创建游戏
  put.admin/withdraw/review → 审核提现
  put.admin/withdraw/switch → 操作提现开关（仅超级管理员）
```

## 呼. Nuevo diseño de la versión estándar

### 8.1 Motor de control de riesgos

Cuatro tipos de reglas:
- `ip_blacklist` — coincidencia de lista negra de IP; si coincide, bloqueo directo
- `amount_anomaly` — detección de importes grandes por operación; alerta al superar el umbral
- `frequency` — detección de frecuencia de operaciones en una ventana de tiempo
- `velocity` — detección de asociación de múltiples cuentas en poco tiempo

Las reglas se ejecutan en orden descendente de priority; la primera regla que coincida decide el resultado (block > warn > log).

### 8.2 Inicio de sesión OAuth de terceros

Proveedores admitidos: Google, Facebook, Apple

Flujo:
1. El frontend solicita `GET /api/auth/oauth/{provider}` para obtener la URL de autorización
2. El usuario es redirigido al tercero y completa la autorización
3. El callback `POST /api/auth/oauth/{provider}/callback` llega con el código de autorización
4. El backend busca la vinculación existente → inicio de sesión directo; sin vinculación → registro automático + vinculación + creación de billetera

### 8.3 Sistema de límites KYC

| Nivel | Forma de obtención | Máx. por operación | Límite diario | Comisión |
|------|---------|---------|--------|--------|
| default | Registro por defecto | 1,000 | 10,000 | 1.00% |
| verified | KYC aprobado | 5,000 | 50,000 | 0.50% |
| vip | Otorgado por operación | 20,000 | 200,000 | 0.00% |

### 8.4 Servidores del juego

Cada juego puede configurar varios servidores (region: global/asia/eu/na). Estados del servidor: mantenimiento/normal/popular/nuevo.

### 8.5 Instantáneas de estadísticas diarias

El crontab ejecuta `ComputeDailyStats::run()` cada madrugada y calcula cinco métricas:
- Estadísticas de usuarios (nuevos/activos/acumulados)
- Estadísticas de recargas (número/importe total)
- Estadísticas de retiros (número/importe total)
- Estadísticas de conversiones (número/total de comisiones)
- Estadísticas de juegos (número de jugadores/número de sesiones)

## 9. Funcionalidades de nivel producción

### 9.1 Sistema de notificaciones

Tipos de notificación: system/deposit/withdraw/kyc/coupon/announcement

Escenarios de activación automática:
- Recarga acreditada → NotificationService::send()
- Retiro aprobado/rechazado → notificación automática
- KYC aprobado/rechazado → notificación automática
- Cupón reclamado → notificación automática
- Recompensa de recomendación acreditada → notificación automática

Soporta doble canal: mensajes internos + email (para el email se necesita configurar la variable de entorno MAIL_HOST).

### 9.2 Comisión por recomendación

```
用户A 生成推荐码 → 分享给用户B
用户B 注册时填写推荐码 → 双方各得注册奖励(signup_reward)
用户B 充值 → A 获得充值返佣(deposit_commission_pct%)
```

### 9.3 Autenticación de dos factores (2FA)

- Protocolo estándar TOTP (RFC 6238), compatible con Google Authenticator
- Flujo de habilitación: obtener clave → escanear QR para vincular → verificar TOTP → generar 8 códigos de recuperación de respaldo
- Segunda verificación en el inicio de sesión: POST /api/2fa/verify
- Soporta tolerancia de ±1 ventana de tiempo (30 segundos)

### 9.4 Integración OAuth real

| Proveedor | Endpoint de token | Endpoint de información de usuario |
|--------|----------|------------|
| Google | oauth2.googleapis.com/token | www.googleapis.com/oauth2/v3/userinfo |
| Facebook | graph.facebook.com/v18.0/oauth/access_token | graph.facebook.com/me |
| Apple | appleid.apple.com/auth/token | Decodificación del id_token JWT |

La configuración se hace mediante PlatformConfig o variables de entorno; si la solicitud falla, se vuelve automáticamente al modo mock.

### 9.5 Verificación de firma de webhooks de pago

- Stripe: verificación de firma HMAC-SHA256 (cabecera Stripe-Signature)
- PayPal: POST de vuelta al endpoint de verificación de PayPal
- Si la clave no está configurada, la verificación se omite automáticamente (modo desarrollo)

### 9.6 Clasificación en tiempo real por WebSocket

- Protocolo: WebSocket (ws://host:8789)
- Suscripción: {action: "subscribe", leaderboard_id: 123}
- Push: {type: "ranking_update", rankings: [...]}
- Soporta heartbeat ping/pong para mantener la conexión

## 7. Diseño de internacionalización

### 7.1 Idiomas admitidos

| Código | Nombre | Idioma nativo | Icono |
|------|------|--------|------|
| en-US | English | English | us |
| zh-CN | Chinese (Simplified) | 简体中文 | cn |
| ja-JP | Japanese | 日本語 | jp |
| ko-KR | Korean | 한국어 | kr |

### 7.2 Gestión de traducciones

- Las traducciones se organizan en formato `group.key` (por ejemplo, `auth.login_success`)
- Se almacenan en la tabla `game_translation` con caché Redis (TTL 1 hora)
- API: `GET /api/language/list` obtiene los idiomas disponibles; `POST /api/language/switch` cambia de idioma
- El frontend detecta automáticamente mediante la cabecera `X-Language` o `Accept-Language`
- Si falta la traducción, se vuelve a en-US; si tampoco está en en-US, se devuelve la clave original

### 7.3 Preferencia de idioma del usuario

- Al registrar, se configura automáticamente según el `Accept-Language` del navegador
- Tras iniciar sesión, se puede modificar el campo `language` con `PUT /api/user/profile`
- Al cambiar de idioma, el registro del usuario se actualiza sincronizadamente

## 8. Modelo de ingresos de la plataforma

| Fuente de ingresos | Cálculo | Descripción |
|---------|---------|------|
| Diferencia de conversión | spread_fee de cada conversión | Se cobra en ambas direcciones, compra y venta |
| Comisión de retiro | Importe del retiro × fee_pct | Implementado en la versión estándar |
| Reparto de juegos | Reparto de ingresos de juegos de terceros | Según lo acordado en el contrato |
| Diferencial de recarga | Diferencia de cambio fiduciario → moneda de plataforma | Diferencia entre el tipo fijado por la plataforma y el del mercado |
