# Documento de diseño de arquitectura de seguridad
<!-- lang-nav -->

Languages: [中文](SECURITY.md) · [English](SECURITY.en.md) · [한국어](SECURITY.ko.md) · [Русский](SECURITY.ru.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · **Español** · [Português](SECURITY.pt.md) · [हिन्दी](SECURITY.hi.md) · [العربية](SECURITY.ar.md) · [বাংলা](SECURITY.bn.md) · [Bahasa Indonesia](SECURITY.id.md) · [日本語](SECURITY.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Panorama de defensa en profundidad

El sistema adopta un modelo de defensa en profundidad de 7 capas que filtra las solicitudes maliciosas de fuera hacia dentro, garantizando que si falla cualquier capa individual siempre queden líneas de defensa posteriores.

Toda la cadena de middleware se ejecuta en el siguiente orden (ver `config/middleware.php`):

```
请求 → Cors → SecurityFilter → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Capa | Middleware/mecanismo | Objetivo de protección |
|----|--------|---------|
| 1 | SecurityFilter | Intercepción de ataques XSS / inyección SQL / path traversal / inyección de comandos / CSRF |
| 2 | Cors | Seguridad CORS + inyección de cabeceras de respuesta de seguridad |
| 3 | RateLimit | Límite de tasa de ventana deslizante Redis, contra fuerza bruta |
| 4 | AdminAuth | Autenticación JWT + cierre de sesión con lista negra |
| 5 | AdminPermission | Autorización RBAC con granularidad method.path |
| 6 | OperationLog | Auditoría de operaciones + trazabilidad de origen |
| 7 | Cifrado de datos | Ofuscación de ID con Hashids + cifrado de BD con Encryptable + cifrado de transmisión con EncryptionService |

Las tres capas del frontend (Flutter) tienen validación de entrada independiente; el backend no confía en ellas y cada capa se defiende de forma autónoma.

---

## 2. Motor de detección de ataques

### 2.0 Restricción de métodos HTTP

SecurityFilter valida primero el método HTTP antes de todas las detecciones de ataques; solo se permiten los siguientes métodos estándar:

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

Los métodos no estándar (como TRACE, CONNECT, PATCH, métodos personalizados, etc.) devuelven directamente **405 Method Not Allowed** con un cuerpo HTML vacío, sin entrar en la detección de ataques ni en la lógica de negocio posterior.

Es la primera línea de defensa de la defensa en profundidad y bloquea eficazmente:
- Ataques de seguimiento entre sitios TRACE (XST)
- Abuso de túnel proxy CONNECT
- Sondas de métodos WebDAV no estándar
- Enumeración de métodos HTTP por escáneres automatizados

### 2.1 XSS (scripting entre sitios)

Todas las expresiones regulares provienen de `SecurityFilter::PATTERNS['XSS']` y se comparan sin distinguir mayúsculas.

| Patrón de detección | Expresión regular | Ataques que defiende |
|----------|------|-----------|
| Etiquetas de script | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` y variantes con espacios |
| Atributos de evento | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | Eventos en línea como `onclick="javascript:..."` |
| Pseudoprotocolo JS | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)`, etc. |
| XSS por Data URI | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...`, etc. |
| Inyección de plantillas | `\{\{.*?\}\}` | `{{constructor}}`, `{{7*7}}` e inyección de plantillas de servidor/Angular/Vue |

### 2.2 Inyección SQL

| Patrón de detección | Expresión regular | Ataques que defiende |
|----------|------|-----------|
| Consulta UNION | `\bUNION\s+(?:ALL\s+)?SELECT\b` | Exfiltración de tablas con `UNION SELECT`, `UNION ALL SELECT` |
| Inyección OR siempre verdadera | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| Destrucción de estructura de tablas | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| Llamadas a procedimientos almacenados | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | Ejecución de comandos mediante procedimientos almacenados extendidos de MSSQL |
| Sonda de metadatos | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | Sonda de estructura de BD en MySQL/PG/SQLite/MSSQL |
| Bypass con comentarios | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | Bypass de comentarios como `'-- OR SELECT`, `'# AND UPDATE` |

### 2.3 Path traversal

| Patrón de detección | Expresión regular | Ataques que defiende |
|----------|------|-----------|
| Retroceso de directorio | `\.\.[\/\\\\]{2,}` | Retrocesos de múltiples niveles como `../`, `..\`, `....//` |
| Sonda de archivos sensibles | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD`, etc. |
| Truncado con byte nulo | `%00` | Bypass de validación de extensión como `../../../etc/passwd%00.jpg` |

### 2.4 Inyección de comandos

| Patrón de detección | Expresión regular | Ataques que defiende |
|----------|------|-----------|
| Comandos por pipe/punto y coma | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| Sustitución con comillas invertidas | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| Sustitución $() | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| Descarga remota por pipe | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF (falsificación de solicitudes entre sitios)

La lógica de validación se implementa en `SecurityFilter::checkCsrf()`:

```php
// 仅 POST/PUT/DELETE 触发校验
// Origin 头和 Referer 均为空 → 放行（非浏览器客户端）
// Origin 非空 → 解析 Origin 域名与 Host 比对
```

Reglas de comparación:
- Eliminar el prefijo `www.` del Host y comparar exactamente con el dominio del Origin
- Si el Host es un dominio padre del Origin (p. ej. `Origin: app.example.com`, `Host: example.com` — se dispara `str_contains($originHost, '.' . $hostOnly)`), se permite
- Si no coincide exactamente ni es un subdominio → devuelve 403, se determina ataque CSRF

Nota: los clientes que no son navegadores (como curl sin Origin/Referer) se permiten directamente; la protección CSRF solo es efectiva en entornos de navegador.

### 2.6 Subida de archivos maliciosos

| Patrón de detección | Expresión regular | Ataques que defiende |
|----------|------|-----------|
| Suplantación con doble extensión | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` para eludir la lista blanca |
| Extensión PHP | `\.php\s*$/m` | Pasar rutas `.php` directamente en los parámetros de solicitud |

---

## 3. Escalada de ataques y lista negra de IP

SecurityFilter incluye un mecanismo de escalada de ataques para impedir que la misma IP escanee continuamente.

### Flujo de escalada

```
第 1 次扫描命中 → Redis INCR security_escalate:{ip} = 1, TTL=60s
第 2 次扫描命中 → INCR → 2
...
第 5 次扫描命中 → INCR → 5
    → 触发封禁: SETEX security_ban:{ip} 900 1
    → 清除计数器 DEL security_escalate:{ip}
    → 写入安全日志: [SECURITY] IP banned 15min
```

### Comportamiento durante el bloqueo

Cada solicitud comprueba primero `isBanned()` al entrar en SecurityFilter:

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

Las IP bloqueadas ven rechazadas directamente con 403 todas las solicitudes (incluidas las legítimas) durante 15 minutos, saltándose por completo la lógica de negocio posterior.

### Constantes de configuración

| Constante | Valor | Significado |
|------|-----|------|
| ESCALATE_LIMIT | 5 | Umbral de activaciones dentro de la ventana de 60 s |
| ESCALATE_WINDOW | 60 | Ventana del contador (segundos) |
| BAN_DURATION | 900 | Duración de la lista negra (segundos), es decir, 15 minutos |

### Registro de seguridad

Ubicación del archivo: `runtime/logs/security.log`

Ejemplo de formato de registro:
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/v1/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### Límite de tamaño del cuerpo de solicitud

`Content-Length > 10MB` devuelve directamente 413 Payload Too Large, contra ataques DoS con cuerpos de solicitud enormes.

### Validación de Content-Type

Las solicitudes POST/PUT **deben** declarar `Content-Type` como `application/json` o `application/x-www-form-urlencoded`; de lo contrario se devuelve 415 Unsupported Media Type. Las solicitudes de subida de archivos (con campo file) se saltan esta comprobación.

---

## 4. Cabeceras de seguridad de respuesta

Todas las cabeceras se inyectan en el middleware `Cors` mediante `$response->withHeaders()` y se añaden a cada respuesta.

| Cabecera | Valor | Función |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Permite CORS de cualquier origen (escenario de panel de administración en intranet) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Conjunto de métodos permitidos |
| Access-Control-Allow-Headers | `Authorization,Content-Type` | Cabeceras personalizadas permitidas |
| Access-Control-Max-Age | `86400` | Caché de solicitudes de preflight durante 24 horas |
| X-Content-Type-Options | `nosniff` | Prohíbe la detección MIME del navegador |
| X-Frame-Options | `DENY` | Prohíbe toda incrustación en iframe, contra clickjacking |
| X-XSS-Protection | `1; mode=block` | Activa el filtro XSS integrado del navegador y bloquea el renderizado de la página |
| Referrer-Policy | `strict-origin-when-cross-origin` | Mismo origen envía URL completa; entre orígenes solo envía el dominio |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Desactiva en todo el sitio las APIs de cámara/micrófono/geolocalización |

Las solicitudes de preflight OPTIONS devuelven directamente 204 con respuesta vacía, sin entrar en la cadena de middleware posterior.

### 4.2 Content-Security-Policy (CSP)

Se inyecta junto con las demás cabeceras de seguridad en el middleware Cors; proporciona defensa en profundidad limitando los orígenes de los recursos que el navegador puede cargar y ejecutar.

| Cabecera | Valor | Función |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Limita los orígenes de scripts/estilos/imágenes/conexiones/frames/formularios, etc. |
| X-Permitted-Cross-Domain-Policies | `none` | Prohíbe la carga de archivos de política entre dominios de Adobe Flash/PDF, etc. |

Puntos clave de la política CSP:
- `default-src 'self'`: por defecto solo se permiten recursos del mismo origen
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: permite scripts del mismo origen + scripts en línea (necesario para Flutter Web) + eval (necesario para depurar Flutter Web)
- `frame-ancestors 'none'`: prohíbe que cualquier página lo incruste en iframe; doble garantía con X-Frame-Options: DENY
- `base-uri 'self'`: limita la etiqueta `<base>` a apuntar solo al mismo origen
- `form-action 'self'`: limita los formularios a enviarse solo al mismo origen

---

## 5. Estrategia de límite de tasa

### Algoritmo

Ventana deslizante con Redis Sorted Set + script Lua atómico; operaciones clave:

```lua
-- 1. 清理窗口外的旧记录
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. 检查当前窗口计数
local count = redis.call('ZCARD', KEYS[1])
-- 3. 超限则返回 {0, count}，未超限则 ZADD 并返回 {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- 随机后缀避免同毫秒覆盖
redis.call('EXPIRE', KEYS[1], window + 10)
```

El script Lua se ejecuta en un único hilo del servidor Redis, lo que lo hace **naturalmente atómico** y elimina las condiciones de carrera TOCTOU (Time-of-check to Time-of-use).

### Configuración del límite de tasa

| Ruta | Límite | Ventana | Escenario |
|------|------|------|------|
| Predeterminado (todas las rutas) | 60 veces/minuto | 60s | API general |
| `/api/v1/auth/login` | 10 veces/minuto | 60s | Inicio de sesión (contra fuerza bruta) |
| `/api/v1/auth/register` | 5 veces/minuto | 60s | Registro (contra registro masivo) |

### Cabeceras de respuesta

Al dispararse el límite se devuelve HTTP 429 con cuerpo JSON:
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

Todas las respuestas (incluidas las normales) llevan las siguientes cabeceras:

| Cabecera | Descripción |
|----|------|
| X-RateLimit-Limit | Número máximo de solicitudes permitidas en la ventana actual |
| X-RateLimit-Remaining | Solicitudes restantes disponibles en la ventana actual |
| X-RateLimit-Reset | Marca de tiempo Unix del reinicio de la ventana |
| Retry-After | Solo se incluye al dispararse el límite; segundos recomendados de espera |

### Estrategia de degradación

Ante una anomalía de Redis (timeout de conexión, no disponible, etc.) se aplica **fail-closed**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    // Redis down: 限流不可用即拒绝，避免安全防线失效（登录/支付回调限流为空转）
    return json(['code' => 503, 'message' => '服务暂不可用，请稍后再试', 'data' => []])
        ->withStatus(503)->withHeaders(['Retry-After' => '5']);
}
```

El límite de tasa es la primera línea de defensa contra la fuerza bruta en el inicio de sesión y contra la reproducción en los callbacks de pago; ante un fallo de Redis es preferible rechazar la solicitud (503) que dejarla pasar.

### 5.4 Mecanismo de bloqueo de cuenta

La interfaz de inicio de sesión añade, además del límite de tasa, un mecanismo de **bloqueo de cuenta** contra la fuerza bruta dirigida a usuarios concretos.

**Flujo de bloqueo**:

```
登录失败 → Redis INCR account_lockout:{userId} TTL=900s
连续 5 次失败 → Redis SETEX account_locked:{userId} 900 1
            → 返回 429 "账号已被锁定，请15分钟后再试"
            → 清除计数器 DEL account_lockout:{userId}
```

**Comportamiento durante el bloqueo**:

Durante el bloqueo, todas las solicitudes de inicio de sesión devuelven directamente 429 sin validar la contraseña, bloqueando por completo los intentos de fuerza bruta.

**Constantes de configuración**:

| Constante | Valor | Significado |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Número máximo de fallos consecutivos |
| LOCKOUT_DURATION | 900 | Duración del bloqueo (segundos), es decir, 15 minutos |

Nota: el bloqueo de cuenta se basa en `userId`, no en la IP, por lo que cambiar de IP no permite eludirlo. Se combina con el límite de tasa por IP (10 veces/minuto) formando una doble protección:
- Nivel IP: límite de 10 veces/minuto bloquea la fuerza bruta distribuida
- Nivel cuenta: bloqueo tras 5 fallos bloquea la fuerza bruta dirigida

---

## 6. Autenticación y autorización

### 6.1 Autenticación JWT

Implementada en el middleware AdminAuth, montada en los grupos de rutas que requieren autenticación.

**Parámetros de configuración** (`config/plugin/erikwang2013/jwt/jwt`, inyectados desde `.env`):

| Parámetro | Valor | Descripción |
|------|-----|------|
| Algoritmo | HS256 | Firma simétrica HMAC-SHA256 |
| Clave | `JWT_SECRET_KEY` | Inyectada por variable de entorno; si falta o conserva el valor predeterminado **se rechaza el arranque** (fail-closed) |
| TTL access_token | 7200s (2h) | `JWT_TTL` |
| TTL refresh_token | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Emisor | `open-admin` | `JWT_ISSUER` |
| Audiencia | `open-admin` | `JWT_AUDIENCE` |

**Extracción del token**: se extrae de la cabecera `Authorization: Bearer <token>`; al quitar el prefijo `Bearer ` se obtiene el JWT original.

**Flujo de autenticación**:
1. Token vacío → 401 directo `{"code": 401, "message": "未登录"}`
2. Comprobar la lista negra de Redis `jwt_blacklist:{md5(token)}` → si está → 401 `Token已失效，请重新登录`
3. Decodificar JWT → fallo (caducado/firma no coincide) → 401 `Token已过期或无效`
4. Éxito → inyectar `$request->adminId` y `$request->adminUsername`

**Mecanismo de lista negra**: al cerrar sesión, se escribe `md5(token)` en Redis con TTL igual a la validez restante del JWT. Si Redis falla, la comprobación de la lista negra se omite (fail-open): el token de una sesión cerrada puede usarse brevemente, pero la validez corta del propio JWT (2h) actúa como protección de respaldo.

**Refresco del token**: `POST /api/v1/auth/refresh` valida el refresh token original (`token_type=refresh` y sin caducar/sin estar en la lista negra) antes de emitir el nuevo par, y valida que `sub` sea un ID de usuario válido — **ya no se emiten refresh tokens con sub=null**; si el refresco falla, se devuelve 401 directamente.

### 6.2 Límite de sesiones concurrentes

Para impedir que un token filtrado se use en varios dispositivos, el sistema limita el número de tokens válidos que puede mantener simultáneamente un mismo usuario.

**Lógica de la limitación**:

```
登录成功 → 签发新 Token
         → 查询当前用户有效 Token 数量: Redis SCARD user_tokens:{userId}
         → 若数量 >= 3（MAX_CONCURRENT_SESSIONS）:
            → 按创建时间升序排列，移除最旧的 Token:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → 将新 Token 加入集合: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Constantes de configuración**:

| Constante | Valor | Significado |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Número máximo de tokens concurrentes por usuario |

**Escenario de expulsión**: cuando el usuario inicia sesión en un cuarto dispositivo, el token del primer dispositivo se fuerza a la lista negra y las solicitudes posteriores devuelven 401 "Token已失效，请重新登录".

Al cerrar sesión, el token actual se elimina del conjunto. Cuando un token caduca de forma natural, la clave de Redis expira sola y los miembros del conjunto se reducen.

### 6.3 Modelo de permisos RBAC

Implementado en el middleware AdminPermission.

**Modelo de datos**: relación de tres niveles User -> Role -> Permission

- `game_admin_user` (tabla de usuarios)
- `game_admin_user_role` (tabla de relación usuario-rol)
- `game_admin_role` (tabla de roles)
- `game_admin_role_permission` (tabla de relación rol-permiso)
- `game_admin_permission` (tabla de permisos)

**Tipos de permiso**:
| type | Significado | Ejemplo |
|------|------|------|
| 1 | Permiso de menú | Controla la visibilidad de la navegación izquierda |
| 2 | Permiso de botón | Controla los botones de operación de la página (crear/editar/eliminar) |
| 3 | Permiso de API | Controla la llamada a interfaces del backend |

Formato del identificador de permiso de API: `{method}.{path}`

Por ejemplo:
- `post.admin/user` — crear usuario
- `put.admin/user` — editar usuario
- `delete.admin/user` — eliminar usuario
- `get.admin/user` — ver lista de usuarios

**Flujo de autorización**:
1. `$request->adminId` vacío (sin inicio de sesión) → 401 directo `{"code": 401, "message": "未登录"}`, sin dejar pasar
2. Obtener usuario → roles (se omiten los roles deshabilitados con `status=0`) → lista de permisos
3. Superadministrador (`slug = '*'`) → se permite directamente
4. Construir `strtolower(method) . '.' . trim(path, '/')` → comparar con la lista de permisos
5. Sin coincidencia → 403 `{"code": 403, "message": "无权限访问"}`

**Segunda confirmación**: BaseController ofrece el método `confirmPassword()`; las operaciones sensibles (eliminar usuarios, exportación de datos, etc.) exigen además la contraseña actual en la capa de controlador para evitar operaciones no autorizadas tras el secuestro de sesión.

### 6.4 Verificación de firma de callbacks de pago (fail-closed)

La verificación de firma de `POST /api/v1/payment/callback` (callbacks de recarga de Stripe/PayPal) es **fail-closed**: cualquier configuración ausente o anomalía de validación rechaza el callback:

| Escenario | Comportamiento |
|------|------|
| Stripe sin `STRIPE_WEBHOOK_SECRET` configurado | Rechazo (403), ya no se aceptan callbacks sin firma |
| Firma de Stripe ausente / fallo de verificación | Rechazo (403) |
| Timestamp de Stripe `t=` ausente o con diferencia **> ±5 minutos** con la hora del servidor | Rechazo (403), contra reproducción |
| PayPal sin `PAYPAL_WEBHOOK_ID` configurado | Rechazo (403) |
| Anomalía en la verificación de devolución de PayPal / no es SUCCESS | Rechazo (403) |
| Con `CALLBACK_TRUSTED_IPS` opcional configurado, la IP de origen no está en la lista blanca | Rechazo (403) |
| El provider del callback no coincide con el método de pago del pedido / el método de pago no existe | Rechazo (403) |

El abono del callback (actualización de estado + saldo + transacción) se completa dentro de una misma transacción de base de datos; si falla cualquier paso, se revierte todo, evitando abonos parciales.

---

## 7. Registros de auditoría

### 7.1 Registros de operaciones

El middleware OperationLog registra automáticamente las operaciones de las solicitudes POST / PUT / DELETE. Las solicitudes GET no se registran.

**Campos registrados**:

| Campo | Origen | Descripción |
|------|------|------|
| id | SnowflakeService::generate() | ID único global |
| user_id | `$request->adminId` | ID del operador; 0 si no hay inicio de sesión |
| action | `$request->method()` | Equivalente a method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Ruta de la solicitud |
| ip | `$request->getRealIp()` | IP real del cliente |
| source | detectSource() | Plataforma de origen del cliente |
| input | Cuerpo de la solicitud (JSON enmascarado) | Datos enviados por la operación |
| created_at | `date('Y-m-d H:i:s')` | Hora de la operación |

**Filtrado de campos sensibles**: se recorre recursivamente el cuerpo de la solicitud; los valores de los siguientes campos se sustituyen por `***`:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Detección de origen** (`detectSource()`), por prioridad:

1. Primero lee la cabecera personalizada `X-Client-Platform` (declaración explícita de los clientes nativos)
2. De lo contrario, deduce de la cadena User-Agent (orden de comprobación del método `detectSource()`):

| Plataforma | Palabra clave de UA |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Valor predeterminado de respaldo |

**Tolerancia a fallos**: una anomalía al escribir el registro no bloquea la solicitud de negocio (`catch (\Throwable)` se traga silenciosamente).

### 7.2 Registros de seguridad

**Ubicación del archivo**: `runtime/logs/security.log`

**Contenido registrado**:
- Registros de intercepción de ataques: categoría de ataque, IP, ruta, campo, origen, fragmento de payload (primeros 200 caracteres)
- Avisos de bloqueo de IP: IP bloqueada, número de activaciones

El registro usa los flags `FILE_APPEND | LOCK_EX` para garantizar escrituras concurrentes seguras.

---

## 8. Protección de datos

El sistema adopta una estrategia de protección de datos en tres capas, correspondientes a las tres fases del flujo de datos.

### 8.1 Capa de transmisión — EncryptionService

`EncryptionService` usa el paquete `erikwang2013/encryption` para cifrar/descifrar los campos sensibles de las solicitudes/respuestas de la API.

**Detalles técnicos**:
- Algoritmo: `aes-256-cbc-hmac` (con firma HMAC integrada contra manipulaciones)
- Clave: variable de entorno `ENCRYPTION_KEY`, alineada automáticamente a 32 bytes
- Uso: transmisión de campos como teléfono y número de DNI entre el cliente y la API

**Métodos de utilidad para enmascarar**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (nombre de usuario de más de 2 caracteres) o `a**@example.com`

### 8.2 Capa de almacenamiento — Cast Encryptable

El modelo `AdminUser` usa el cast Eloquent `Erikwang2013\Encryptable\Encryptable` para los siguientes campos:

- `email` → cast a Encryptable, cifrado/descifrado automático
- `phone` → cast a Encryptable, cifrado/descifrado automático
- `id_card` → cast a Encryptable, cifrado/descifrado automático

Al escribir en la base de datos se cifra automáticamente a texto cifrado; al leer se descifra automáticamente a texto plano. La columna de almacenamiento es `VARCHAR(500)` y el texto cifrado se guarda en base64.

**Sistema de claves**: usa `ENCRYPTABLE_KEY`, independiente del cifrado de la capa de transmisión (`ENCRYPTION_KEY`); si una clave se filtra, la otra capa no queda comprometida.

Rotación de claves: la variable de entorno `ENCRYPTION_PREVIOUS_KEYS` admite una lista de claves históricas (separadas por comas); al leer datos antiguos intenta descifrar con las claves históricas y al escribir vuelve a cifrar con la clave actual.

### 8.3 Capa de presentación — Ofuscación de ID y enmascarado

**Ofuscación de ID con Hashids**: `HashidsService` usa el paquete `erikwang2013/hashids`.

- Los ID BIGINT de la base de datos devueltos por la API externa se codifican como cadenas hash (p. ej. `xK3mN9qR2pL7wV8b`)
- El cliente envía la cadena hash en las solicitudes y el backend la decodifica automáticamente al ID original
- La sal `HASHIDS_SALT` se inyecta por variable de entorno; con sales distintas, los resultados de codificación/decodificación son completamente distintos
- Longitud mínima del hash: 16 caracteres, con un juego de caracteres alfanumérico de 62 símbolos
- BaseController ofrece los métodos de conveniencia `encodeId()`, `decodeId()`, `encodeIds()`

**Enmascarado en exportaciones**: al exportar Excel/PDF (ExportController), los campos sensibles se enmascaran uniformemente:
- Teléfono: `138****1234`
- Correo: `a***@example.com`
- DNI: completamente oculto como `********`

---

## 9. Gestión de claves

Todas las claves se inyectan mediante variables de entorno `.env`; los archivos de configuración las leen con `getenv()` y tienen valores predeterminados de respaldo (seguros solo en desarrollo).

| Variable de entorno | Uso | Paquete | Requisito de producción |
|----------|------|-----|---------|
| JWT_SECRET_KEY | Clave de firma JWT | erikwang2013/jwt-webman | Cadena aleatoria de 64+ caracteres; se rechaza el arranque si falta o conserva el valor predeterminado |
| JWT_ALGORITHM | Algoritmo de firma JWT | el mismo | Mantener HS256 |
| HASHIDS_SALT | Sal de codificación de ID | erikwang2013/hashids | Cadena aleatoria |
| SNOWFLAKE_DATACENTER_ID | ID del centro de datos (0-31) | erikwang2013/snowflake-php | Mantener el predeterminado en un único centro de datos |
| ENCRYPTION_KEY | Clave de cifrado de la capa de transmisión de API | erikwang2013/encryption | Cadena aleatoria de 32 bytes |
| ENCRYPTABLE_KEY | Clave de cifrado de la capa de almacenamiento de BD | erikwang2013/encryptable | Cadena aleatoria de 32 bytes, distinta de la clave de transmisión |

**Requisitos de seguridad**:
- El archivo `.env` está en `.gitignore`; está estrictamente prohibido subirlo al repositorio
- `.env.example` es una plantilla pública sin claves reales
- En producción **es obligatorio** sustituir todas las claves predeterminadas por cadenas aleatorias
- Se recomienda generar las claves con `openssl rand -base64 32`

### Aislamiento del almacenamiento de claves

| Capa | Clave de configuración | Variable de entorno de la clave |
|----|--------|-------------|
| Cifrado de transmisión | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Cifrado de almacenamiento | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| Ofuscación de ID | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| Firma JWT | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET_KEY` |

---

## 10. security.txt (RFC 9116)

El sistema ofrece en `/.well-known/security.txt` un endpoint con la información de contacto de seguridad estándar RFC 9116, para que los investigadores de seguridad encuentren rápidamente el canal de reporte al descubrir vulnerabilidades.

**Forma de acceso**:

```
GET /.well-known/security.txt
```

**Contenido de la respuesta**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Descripción de campos**:

| Campo | Descripción |
|------|------|
| Contact | Contacto para reportar vulnerabilidades de seguridad |
| Expires | Fecha de caducidad del archivo, debe renovarse periódicamente |
| Preferred-Languages | Idiomas de comunicación preferidos |
| Canonical | URL canónica de este archivo |
| Policy | Enlace a la política de seguridad / divulgación de vulnerabilidades |

Este endpoint no está sujeto a middlewares de límite de tasa ni autenticación; cualquiera puede acceder directamente.

---

## 11. Configuración de seguridad de Nginx

El proyecto ofrece `docs/nginx-security.conf` como configuración de referencia para reforzar la seguridad del proxy inverso Nginx en producción.

**Medidas de seguridad incluidas**:

| Elemento de configuración | Función |
|--------|------|
| `server_tokens off` | Oculta el número de versión de Nginx |
| `client_max_body_size 10m` | Limita el tamaño del cuerpo de la solicitud, en coordinación con SecurityFilter |
| `limit_req_zone` | Límite de frecuencia de solicitudes a nivel de Nginx |
| `limit_conn_zone` | Límite de conexiones concurrentes |
| Cabeceras de seguridad `add_header` | Añade a nivel de Nginx cabeceras de seguridad como X-XSS-Protection |
| `if ($request_method)` | Rechaza a nivel de Nginx los métodos HTTP no estándar |
| Configuración SSL/TLS | Configuración moderna TLS 1.2/1.3, desactiva suites de cifrado débiles |
| Ocultación de cabeceras del backend | `proxy_hide_header` elimina cabeceras sensibles como la versión de webman |

**Uso**: fusiona la configuración de `docs/nginx-security.conf` en el bloque server de tu Nginx, ajustándola al dominio y las rutas de certificados reales.

---

## 12. Modelo de amenazas

### 12.1 Amenazas protegidas

| Tipo de amenaza | Vector de ataque | Capas de defensa |
|----------|---------|---------|
| Abuso de métodos HTTP | Ataques XST con TRACE/TRACK, túnel proxy CONNECT, sondas de métodos WebDAV | Lista blanca de métodos de SecurityFilter con 405 (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| Fuerza bruta dirigida | Intentos repetidos de contraseña contra un usuario concreto | Bloqueo de cuenta (5 fallos bloquean 15 minutos) + RateLimit (login 10/min) + Captcha |
| Fuerza bruta | Intentos distribuidos de usuario/contraseña desde IPs distintas | RateLimit (login 10/min) + Captcha |
| XSS | `<script>`, onerror, javascript: | SecurityFilter (5 patrones) + cabecera X-XSS-Protection + CSP |
| Inyección SQL | UNION SELECT, OR 1=1, bypass de comentarios | SecurityFilter (6 patrones) + consultas parametrizadas de Eloquent ORM |
| CSRF | Sitios maliciosos que envían solicitudes en nombre del usuario | Validación Origin/Referer de SecurityFilter |
| Path traversal | `../../etc/passwd` | Patrones de path traversal de SecurityFilter + lista blanca de extensiones de UploadController |
| Inyección de comandos | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 patrones) |
| Secuestro de sesión | Robo del token JWT | JWT de validez corta (2h) + cierre de sesión con lista negra + segunda confirmación de contraseña en operaciones sensibles |
| Enumeración de ID | Recorrer ID numéricos para adivinar volúmenes de datos | Ofuscación de ID con Hashids a cadenas aleatorias |
| Fuga de datos | Extracción de BD / hombre en el medio / fuga de registros | Cifrado/enmascarado en tres capas + filtrado de campos sensibles de OperationLog |
| Ataques DoS | Cuerpos de solicitud enormes / solicitudes de alta frecuencia | Límite de cuerpo de 10 MB + RateLimit 60/min + lista negra de IP |
| Escalada de privilegios | Usuarios de bajo privilegio acceden a interfaces de administración | Autorización RBAC con granularidad method.path |
| Ataques de subida de archivos | Doble extensión shell.php.png | Detección de archivos maliciosos de SecurityFilter |

### 12.2 Limitaciones conocidas

| Limitación | Alcance del impacto | Medidas de mitigación |
|------|---------|---------|
| La protección CSRF solo funciona con navegadores | Los clientes que no son navegadores (curl, Postman, apps móviles) pueden omitir la comprobación Origin/Referer | Los clientes que no son navegadores no sufren CSRF de forma natural; se usa autenticación JWT en lugar de cookies |
| Con Redis no disponible: límite de tasa fail-closed (503), comprobación de lista negra fail-open | Durante el fallo se rechazan algunas solicitudes; los tokens de sesiones cerradas se pueden usar brevemente | Monitorizar y alertar sobre la disponibilidad de Redis; la validez corta del JWT actúa como respaldo |
| Sin motor WAF independiente | SecurityFilter usa coincidencia de expresiones regulares `@preg_match`, no es un motor de reglas WAF dedicado | En producción se recomienda Nginx ModSecurity o Cloudflare WAF por delante |
| JWT sin estado no puede invalidarse activamente | Un token no puede revocarse desde el servidor antes de su caducidad (salvo lista negra) | Lista negra + TTL corto de 2h reducen la ventana de riesgo |
| La lista negra de IP solo vive en memoria | La lista negra se pierde al reiniciar Redis | El bloqueo dura solo 15 minutos, el impacto es limitado |
| Los endpoints de administración no tienen límite de tasa especial | Las interfaces de administración comparten el límite predeterminado de 60/min | La frecuencia de operaciones de los administradores es naturalmente baja; no hace falta distinguir por ahora |
| `@preg_match` suprime errores | Ante una expresión regular malformada falla en silencio | Se podría monitorizar con `preg_last_error()`; no implementado actualmente |
