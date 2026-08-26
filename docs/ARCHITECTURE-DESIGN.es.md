# Documento de diseño de arquitectura
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE-DESIGN.md) · [English](ARCHITECTURE-DESIGN.en.md) · [한국어](ARCHITECTURE-DESIGN.ko.md) · [Русский](ARCHITECTURE-DESIGN.ru.md) · [Deutsch](ARCHITECTURE-DESIGN.de.md) · [Français](ARCHITECTURE-DESIGN.fr.md) · **Español** · [Português](ARCHITECTURE-DESIGN.pt.md) · [हिन्दी](ARCHITECTURE-DESIGN.hi.md) · [العربية](ARCHITECTURE-DESIGN.ar.md) · [বাংলা](ARCHITECTURE-DESIGN.bn.md) · [Bahasa Indonesia](ARCHITECTURE-DESIGN.id.md) · [日本語](ARCHITECTURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Objetivos de diseño

Construir una plataforma global, universal e internacional de agregación de juegos. Requisitos principales:

- Los usuarios pueden recargar, convertir en moneda de juego, jugar, ganar moneda de juego y retirar dinero en la plataforma
- La plataforma gestiona de forma unificada múltiples juegos (propios + de terceros); cada juego tiene su propia moneda y tipo de cambio
- El backend ofrece capacidades completas de revisión, interruptores y control de riesgos
- Soporte de operación global multilingüe, multi-moneda y con múltiples canales de pago

## 2. Selección de arquitectura

### 2.1 ¿Por qué monolito modular y no microservicios?

En la etapa actual se elige el monolito modular (Modular Monolith):

| Consideración | Monolito modular | Microservicios |
|------|----------|--------|
| Eficiencia de desarrollo | Llamadas dentro del mismo proceso, sin RPC | Requiere manejar latencia de red y serialización |
| Consistencia transaccional | Transacciones de base de datos locales | Transacciones distribuidas (complejas) |
| Complejidad operativa | Despliegue de un solo proceso | Orquestación de múltiples servicios, descubrimiento de servicios |
| Escalabilidad | En el futuro se puede dividir en microservicios por módulo | Soporte natural de escalado independiente |
| Tamaño del equipo | Adecuado para equipos pequeños (1-5 personas) | Adecuado para equipos múltiples en desarrollo paralelo |

**Decisión**: admin/ (panel de administración) y service/ (negocio del lado C) son dos instancias webman independientes que pueden desplegarse en la misma máquina (puertos distintos) o por separado. La capa compartida common/ elimina la duplicación de código mediante autoload PSR-4. Cuando el volumen de negocio crezca en el futuro, service/ se puede dividir en varios microservicios (servicio de usuarios, servicio de billetera, servicio de juegos).

### 2.2 ¿Por qué webman v2 y no PHP-FPM tradicional?

| Consideración | webman v2 | PHP-FPM (Laravel) |
|------|-----------|-------------------|
| Rendimiento | Residente en memoria, soporte de corrutinas | Carga todos los archivos en cada solicitud |
| Concurrencia | Decenas de miles de QPS en una sola máquina | Cientos de QPS en una sola máquina |
| Despliegue | Simple, un proceso con múltiples workers | Configuración compleja de Nginx + PHP-FPM |
| Ecosistema | Compatible con los componentes Illuminate de Laravel | Ecosistema completo |

**Decisión**: la plataforma de juegos necesita manejar callbacks de recarga, solicitudes de conversión y liquidaciones de juego de alta concurrencia; la memoria residente y la alta concurrencia de webman son más adecuadas. Además, es compatible con el ORM, Queue y otros componentes de Laravel, por lo que la eficiencia de desarrollo no es inferior a la de los frameworks tradicionales.

### 2.3 ¿Por qué estilo PC de Flutter Web?

- Un solo código compila a Web (PC), iOS, Android y HarmonyOS
- La biblioteca de componentes Material 3 es madura; el layout estilo PC de barra lateral + barra superior está listo para usar
- Capa de lógica de negocio compartida con el cliente HarmonyOS
- Evita mantener dos conjuntos de código frontend (React/Vue + Flutter)

## 3. Decisiones técnicas clave

### 3.1 Sistema de IDs

```
Snowflake genera BIGINT (único distribuido internamente)
    ↓
Hashids lo codifica como cadena corta (no se puede deducir el ID real externamente)
    ↓
En las solicitudes/respuestas de la API se transmite la cadena hashid
```

**Razones**:
- Snowflake es único globalmente, incrementa por tendencia (favorable para índices) y no expone el volumen de negocio
- Hashids impide que externamente se recorran los datos con IDs secuenciales y se especule sobre la escala

### 3.2 Precisión de las monedas

La moneda de plataforma y la moneda de juego usan uniformemente la precisión `DECIMAL(18,4)`. En el lado PHP se usa la familia de funciones `bcmath` (bcadd/bcsub/bcmul/bcdiv/bccomp) para todos los cálculos de importes.

**Razones**: los números de punto flotante (float/double) tienen errores de precisión, inaceptables en escenarios financieros. DECIMAL + bcmath garantiza cálculos exactos.

### 3.3 Bloqueo optimista de la billetera

```sql
UPDATE erik_user_wallet 
SET balance = balance + ?, version = version + 1 
WHERE user_id = ? AND version = ?
```

Si la actualización falla, se reintenta automáticamente (máximo 5 veces).

**Razones**:
- En la plataforma de juegos, la recarga, la conversión y el retiro pueden operar concurrentemente sobre la misma billetera
- El bloqueo pesimista (SELECT FOR UPDATE) tiene mal rendimiento bajo alta concurrencia
- En escenarios de baja tasa de conflictos, el bloqueo optimista rinde mucho mejor que el pesimista

### 3.4 Flujo de revisión de retiros

```
El usuario inicia un retiro
  ├─ Interruptor global apagado → Rechazado
  ├─ Importe < umbral de revisión automática → Aprobación automática
  └─ Importe >= umbral → Revisión manual → Aprobar/rechazar (al rechazar se devuelve la moneda de plataforma)
```

**Razones**:
- El interruptor global sirve para control de riesgos de emergencia (por ejemplo, al descubrir vulnerabilidades o tráfico anómalo)
- La aprobación automática de importes pequeños reduce el coste manual y mejora la experiencia del usuario
- La revisión manual de importes grandes previene el lavado de dinero y el fraude

### 3.5 Modelo de diferencia de conversión

Cada moneda de juego tiene un `exchange_rate` independiente (1 moneda de plataforma = X monedas de juego) y un `spread_pct` (% de comisión de la plataforma).

Al comprar: moneda de juego acreditada = moneda de plataforma × tipo de cambio × (1 - comisión%)
Al vender: moneda de plataforma acreditada = moneda de juego ÷ tipo de cambio × (1 - comisión%)

**Razones**:
- Los ingresos de la plataforma provienen de la diferencia de conversión, no de pagos dentro del juego
- Los tipos de cambio independientes soportan estrategias de precios distintas por juego
- El porcentaje de diferencia se puede ajustar con flexibilidad para una operación fina

## 4. Arquitectura de seguridad

Sobre la base de las 18 capas de defensa en profundidad existentes, se añaden capas de protección para la plataforma de juegos:

| Capa | Medida | Razón |
|------|------|------|
| Seguridad de concurrencia | Bloqueo optimista `version` en la billetera | Previene deducciones dobles / acreditaciones dobles |
| Seguridad de retiros | Interruptor global + umbral de importe + límites diarios/mensuales + verificación poster-php | Defensa multicapa, reduce el riesgo financiero |
| Seguridad de conversión | Separación entre cotización y ejecución; la cotización expira en 60s | Previene el arbitraje por fluctuación del tipo de cambio |
| Seguridad de juegos | Verificación de firma en callbacks de terceros + lista blanca de IP + defensa contra replay attack | Previene liquidaciones de juego falsificadas |
| Control de riesgos | Motor de reglas (lista negra de IP, alerta de importes grandes, frecuencia anómala) | Bloqueo en tiempo real de transacciones sospechosas |

## 5. Diseño de internacionalización

### 5.1 Detección de idioma

```
Llega la solicitud
  ↓
LanguageMiddleware (middleware global)
  ├── 1. Cabecera de solicitud X-Language
  ├── 2. Cabecera Accept-Language (zh → zh-CN, en → en-US)
  └── 3. Predeterminado en-US
  ↓
TranslationService::setLocale($locale)
  ↓
Función __() en el Controller o TranslationService::trans() para obtener el texto traducido
```

### 5.2 Almacenamiento de traducciones

- La tabla de base de datos `erik_translation` almacena todos los textos traducidos (group + key + lang_code + value)
- En la primera solicitud se carga todo desde la base de datos a Redis (key: `i18n:translations`, TTL: 1 hora)
- Las solicitudes posteriores leen directamente de Redis, con caché en memoria como aceleración
- El panel de administración puede ampliarse con una página de gestión de traducciones (implementada en la versión completa)

### 5.3 Nombrado de claves de traducción

Formato: `group.key`, por ejemplo `auth.login_success`, `wallet.insufficient_balance`

| Grupo | Dominio |
|------|------|
| auth | Relacionado con la autenticación |
| wallet | Relacionado con la billetera |
| exchange | Relacionado con la conversión |
| withdraw | Relacionado con el retiro |
| deposit | Relacionado con la recarga |
| game | Relacionado con los juegos |
| admin | Panel de administración |
| error | Mensajes de error |

### 5.4 Estrategia de fallback

- El idioma de la solicitud tiene traducción correspondiente → usarla
- El idioma de la solicitud no tiene traducción correspondiente → fallback a en-US
- Tampoco en en-US → devolver la clave original

### 5.5 i18n del frontend

- Flutter usa un `AppTranslations` propio + `LocaleController` (GetX)
- La preferencia de idioma se persiste en SharedPreferences
- Al cambiar de idioma, `Get.updateLocale()` dispara el re-render global de la UI
- La clase `StringResult` aprovecha `toString()` de Dart para una sintaxis de interpolación natural: `Text('${AppTranslations.t("key")}')`

## 6. Nuevo diseño de la versión estándar

### 6.1 Motor de control de riesgos

Antes de las operaciones críticas de fondos se ejecutan comprobaciones de reglas en varias capas:

```
Solicitud de recarga/retiro/conversión
  ↓
RiskService::check(userId, type, context)
  ├── Detección de lista negra de IP (ip_blacklist) → block
  ├── Detección de anomalía de importes grandes (amount_anomaly) → warn
  ├── Detección de frecuencia (frequency) → warn/block
  └── Detección de velocidad (velocity) → block
  ↓
passed → ejecución normal
warn   → registrar en log, continuar la ejecución
block  → rechazar la operación
```

Las reglas se almacenan en la tabla `erik_risk_rule`, configuradas como JSON, con umbrales y acciones ajustables dinámicamente.

### 6.2 Verificación de identidad KYC

Sistema de verificación de tres niveles:
- `default` — sin verificar, límites básicos
- `verified` — KYC aprobado, límites más altos + comisión reducida
- `vip` — nivel VIP, límites máximos + comisión cero

Flujo de verificación:
```
El usuario envía la información de identidad → status=pending
El administrador revisa → approve/reject
approve → el usuario asciende automáticamente al nivel verified
reject → el usuario puede volver a enviar
```

### 6.3 Inicio de sesión OAuth de terceros

Admite inicio de sesión con Google / Facebook / Apple:

```
El frontend pulsa el botón OAuth
  → GET /api/auth/oauth/{provider} → obtener URL de autorización
  → Redirigir a la página de autorización del tercero → el usuario acepta
  → Callback POST /api/auth/oauth/{provider}/callback
  → Si existe vinculación → inicio de sesión directo
  → Sin vinculación → registrar automáticamente un nuevo usuario + vincular + crear billetera
```

### 6.4 Callback de pago

```
El pago del tercero se completa → POST /api/payment/callback
  → Validación de lista blanca de providers (solo stripe/paypal)
  → Verificación de firma fail-closed (sin secret/webhook_id configurado, firma inválida o marca de tiempo fuera de ±300s: rechazar siempre)
  → Comparación bccomp entre el importe del callback y el de la orden (previene suplantación entre canales)
  → Actualizar el estado de la orden a confirmed (transaccional; si falla el ingreso, se revierte)
  → UserWallet::addBalance acredita
  → Registrar Transaction
  → Comprobación de riesgos RiskService::check
```

### 6.5 Límites de retiro escalonados

Se aplican límites y comisiones distintos según el nivel KYC del usuario:

| Nivel | Máx. por operación | Límite diario | Límite mensual | Comisión |
|------|---------|--------|--------|--------|
| default | 1,000 | 10,000 | 50,000 | 1.00% |
| verified | 5,000 | 50,000 | 200,000 | 0.50% |
| vip | 20,000 | 200,000 | 1,000,000 | 0.00% |

## 7. Diseño de escalabilidad

### 5.1 Escalado horizontal

admin/ y service/ soportan ambos múltiples procesos worker. Junto con el proxy inverso Nginx, se pueden desplegar varias máquinas para escalar horizontalmente:

```
Nginx (balanceo de carga)
  ├── admin-1 (:8787)
  ├── admin-2 (:8787)
  ├── service-1 (:8788)
  └── service-2 (:8788)
```

### 5.2 Ruta de división de módulos

Cuando un único service/ se convierte en el cuello de botella, dividir según la siguiente ruta:

```
service/ (monolito)
  → service-user/ (servicio de usuarios :8788)
  → service-wallet/ (servicio de billetera :8789)
  → service-game/ (servicio de juegos :8790)
  → service-payment/ (servicio de pagos :8791)
```

Criterios para decidir el momento de la división:
- El QPS de un módulo supera la capacidad de una sola máquina
- Algún módulo necesita un stack tecnológico o una estrategia de despliegue independiente
- El equipo crece hasta necesitar desarrollo paralelo de distintos módulos
