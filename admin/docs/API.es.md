# Documento de referencia de API
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · **Español** · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Resumen

El panel de administración abierto (open-admin), construido sobre webman v2, ofrece una API JSON RESTful. Todas las interfaces del panel de administración requieren autenticación JWT y validación de permisos RBAC; las interfaces públicas se enrutan a controladores versionados mediante la cabecera de versión de API.

- **URL base**: `http://localhost:8787`
- **Versión de API**: controlada por la cabecera `API-Version: v1` (por defecto v1 si falta)

> **Resumen de endpoints**: autenticación(5) | panel(1) | usuarios(7) | roles(4) | permisos(4) | configuración(4) | registros(1) | centro personal(3) | importación/exportación(3) | subida(1) | operación y mantenimiento(4: health/metrics/docs/security.txt) | 37 endpoints en total
- **Autenticación**: `Authorization: Bearer <token>` (JWT)
- **Formato de respuesta**: `{ "code": 0, "message": "success", "data": {...} }`
- **Endpoint de documentación**: `GET /api/docs` devuelve la especificación OpenAPI 3.0 JSON

### Requisitos de las solicitudes

- Solo se permiten los métodos `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD`; el uso de otros métodos HTTP (como TRACE, CONNECT, PATCH) devuelve 405
- Todas las solicitudes `POST` / `PUT` deben establecer `Content-Type: application/json` (excepto las subidas de archivos); de lo contrario se devuelve 415
- El tamaño del cuerpo de la solicitud no puede superar 10 MB; de lo contrario se devuelve 413
- El filtro de seguridad escanea todas las entradas de las solicitudes contra XSS, inyección SQL, path traversal e inyección de comandos; al detectar algo devuelve 403
- 5 inicios de sesión fallidos consecutivos disparan el bloqueo de cuenta (15 minutos); durante el bloqueo, las solicitudes de inicio de sesión devuelven 429
- Un mismo usuario puede mantener como máximo 3 tokens válidos simultáneamente; al superarlo, el token más antiguo se añade automáticamente a la lista negra

## 2. Códigos de error

| code | Significado | Escenario de activación |
|------|------|---------|
| 0 | Éxito | |
| 400 | Error de parámetros de solicitud | Formato de solicitud incorrecto |
| 401 | No autenticado | Token ausente / caducado / en lista negra |
| 403 | Sin permiso / intercepción de seguridad | Permisos RBAC insuficientes / SecurityFilter activado |
| 404 | Recurso no encontrado | El objetivo de consulta/actualización/eliminación no existe |
| 405 | Método de solicitud no permitido | Solo se permiten GET/POST/PUT/DELETE/OPTIONS/HEAD; los métodos no estándar se rechazan directamente |
| 413 | Cuerpo de solicitud demasiado grande | Content-Length supera 10 MB |
| 415 | Tipo de medio no soportado | En solicitudes POST/PUT el Content-Type no es JSON y no es subida de archivos |
| 422 | Fallo de validación de parámetros | Faltan campos obligatorios, formato incorrecto, validación de negocio no superada |
| 429 | Demasiadas solicitudes | RateLimit activado / bloqueo de cuenta (5 inicios de sesión fallidos consecutivos bloquean 15 minutos) |
| 500 | Error interno del servidor | |

## 3. Endpoints públicos

Todos los endpoints públicos se montan bajo el grupo `/api` y se distribuyen mediante el middleware `ApiVersion` según la cabecera `API-Version` al controlador versionado correspondiente (p. ej. `app\api\v1\controller\AuthController`).

### 3.1 Comprobación de salud

```
GET /health
```

- **Autenticación**: no requiere
- **Límite de tasa**: sin límite

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.1",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

Valores de `database`, `redis`, `elasticsearch`: `"ok"` | `"unavailable"`. `elasticsearch` devuelve `"unavailable"` cuando ES es inalcanzable; si el estado de salud del clúster no es green/yellow, devuelve el valor de status real (p. ej. `"red"`).

### 3.2 Documentación de API

```
GET /api/docs
```

- **Autenticación**: no requiere
- **Límite de tasa**: predeterminado global (60 veces/minuto)
- **Respuesta**: especificación OpenAPI 3.0.3 JSON, incluye definiciones de todos los endpoints, parámetros y esquemas

### 3.3 Generar captcha de clic

```
POST /api/captcha/generate
```

- **Autenticación**: no requiere
- **Cabecera**: `API-Version: v1` (obligatoria)
- **Límite de tasa**: predeterminado global (60 veces/minuto)

**Cuerpo de la solicitud**:
```json
{
  "difficulty": "medium"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| difficulty | string | No | `easy` / `medium` / `hard`, por defecto `medium` |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| Campo | Tipo | Descripción |
|------|------|------|
| key | string | Identificador del captcha, se devuelve al validar |
| image | string | Imagen PNG codificada en base64 |
| extra.targets[].order | int | Orden de clic |
| extra.targets[].text | string | Texto de la pista del objetivo de clic |

### 3.4 Validar captcha de clic

```
POST /api/captcha/verify
```

- **Autenticación**: no requiere
- **Cabecera**: `API-Version: v1` (obligatoria)
- **Límite de tasa**: predeterminado global (60 veces/minuto)

**Cuerpo de la solicitud**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| key | string | Sí | Clave del captcha, devuelta por generate |
| clicks | array{object} | Sí | Array de coordenadas de clic, cada elemento contiene `x` (int) e `y` (int) |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

Si la validación falla, `code` es 422, `message` es `"验证失败，请重试"` y `data.valid` es `false`.

### 3.5 Inicio de sesión

```
POST /api/auth/login
```

- **Autenticación**: no requiere
- **Cabecera**: `API-Version: v1` (obligatoria)
- **Límite de tasa**: 10 veces/minuto (por IP + ruta)

**Cuerpo de la solicitud**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| username | string | Sí | min:3, max:50 | Nombre de usuario |
| password | string | Sí | min:6, max:32 | Contraseña |
| captcha_key | string | Sí | | Clave del captcha |
| clicks | array{object} | Sí | min:2 | Array de coordenadas de clic |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| Campo | Tipo | Descripción |
|------|------|------|
| access_token | string | Token de acceso JWT |
| refresh_token | string | Token de refresco JWT |
| expires_in | int | Validez del token de acceso (segundos), por defecto 7200 |
| user.id | string | ID de usuario cifrado con hashid |
| user.username | string | Nombre de usuario |
| user.real_name | string | Nombre real |

**Posibles errores**:
- 422: fallo de validación de parámetros (faltan campos obligatorios, formato incorrecto)
- 422: captcha incorrecto, inténtalo de nuevo
- 401: nombre de usuario o contraseña incorrectos
- 403: la cuenta está deshabilitada
- 429: la cuenta está bloqueada, inténtalo de nuevo en 15 minutos (disparado por 5 inicios de sesión fallidos consecutivos)

### 3.6 Registro

```
POST /api/auth/register
```

- **Autenticación**: no requiere
- **Cabecera**: `API-Version: v1` (obligatoria)
- **Límite de tasa**: 5 veces/minuto (por IP + ruta)

**Cuerpo de la solicitud**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| username | string | Sí | min:3, max:50 | Nombre de usuario (único) |
| password | string | Sí | min:6, max:32 | Contraseña (almacenada como hash bcrypt) |
| real_name | string | Sí | max:50 | Nombre real |
| captcha_key | string | Sí | | Clave del captcha |
| clicks | array{object} | Sí | min:2 | Array de coordenadas de clic |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

Tras un registro exitoso se devuelven directamente los tokens JWT; el estado del usuario es habilitado por defecto (status=1).

### 3.7 Refrescar token

```
POST /api/auth/refresh
```

- **Autenticación**: no requiere
- **Cabecera**: `API-Version: v1` (obligatoria)
- **Límite de tasa**: predeterminado global (60 veces/minuto)

**Cuerpo de la solicitud**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| refresh_token | string | Sí | refresh_token obtenido al iniciar sesión/registrarse |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

Al refrescar con éxito se devuelven simultáneamente un nuevo access_token y refresh_token; el token antiguo se invalida automáticamente. El refresco actualiza la hora del último inicio de sesión y la IP del usuario.

**Posibles errores**:
- 422: falta el token de refresco
- 401: token de refresco inválido o caducado

### 3.8 Métricas de monitorización Prometheus

```
GET /metrics
```

- **Autenticación**: no requiere
- **Límite de tasa**: sin límite
- **Formato de respuesta**: Prometheus text format (`text/plain; version=0.0.4`)

Endpoint público de métricas de monitorización Prometheus, para que lo scrapeen Grafana/Prometheus.

**Ejemplo de respuesta**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| Nombre de métrica | Tipo | Descripción |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Número total acumulado de solicitudes HTTP |
| `openadmin_active_users` | gauge | Usuarios activos actuales (con inicio de sesión en las últimas 24 horas) |
| `openadmin_db_connection_status` | gauge | Estado de conexión a la base de datos, 1=normal, 0=anómalo |
| `openadmin_redis_connection_status` | gauge | Estado de conexión a Redis, 1=normal, 0=anómalo |
| `openadmin_memory_usage_bytes` | gauge | Uso de memoria actual del proceso PHP (bytes) |

## 4. Panel

Todas las interfaces del panel de administración se montan bajo el grupo `/admin` y pasan por tres middlewares: `AdminAuth` (autenticación JWT), `AdminPermission` (validación de permisos RBAC) y `OperationLog` (registro de operaciones).

### 4.1 Datos del panel

```
GET /admin/dashboard
```

- **Autenticación**: JWT + RBAC
- **Caché**: Redis 5 minutos

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| Campo de stats | Tipo | Descripción |
|------|------|------|
| label | string | Nombre de la métrica |
| value | string | Valor de la métrica (tipo cadena) |
| icon | string | Nombre del icono Material |
| color | string | Valor de color de la tarjeta |
| trend | float? | Tasa de crecimiento diario (porcentaje); solo «total de usuarios» tiene este campo |

| Campo de trends | Tipo | Descripción |
|------|------|------|
| dates | array{string} | Secuencia de fechas de los últimos 30 días |
| series | array{object} | Datos de líneas de tendencia; cada una contiene name (nombre), data (array de valores), color (color) |

## 5. Gestión de usuarios

Todos los `id` devueltos por las interfaces de gestión de usuarios son cadenas cifradas con hashid. El campo de contraseña ya está excluido de las respuestas. El teléfono y el correo se muestran enmascarados en las interfaces de lista y en claro en las de detalle (los campos cifrados de la base de datos se descifran automáticamente mediante el trait Encryptable).

### 5.1 Lista de usuarios

```
GET /admin/user
```

- **Autenticación**: JWT + RBAC

**Parámetros de consulta**:

| Parámetro | Tipo | Obligatorio | Valor predeterminado | Descripción |
|------|------|------|------|------|
| page | int | No | 1 | Número de página |
| limit | int | No | 15 | Elementos por página |
| keyword | string | No | | Palabra clave de búsqueda, coincide con nombre de usuario y nombre real |
| status | int | No | | Filtro de estado, 0=deshabilitado, 1=habilitado |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | ID de usuario cifrado con hashid |
| username | string | Nombre de usuario |
| real_name | string | Nombre real |
| phone | string | Teléfono enmascarado (formato `138****5678`) |
| email | string | Correo enmascarado (formato `a***@example.com`) |
| status | int | 1=habilitado, 0=deshabilitado |
| last_login_at | string | Hora del último inicio de sesión (datetime) |
| created_at | string | Hora de creación (datetime) |

### 5.2 Crear usuario

```
POST /admin/user
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| username | string | Sí | min:3, max:50 | Nombre de usuario (único) |
| password | string | Sí | min:6, max:32 | Contraseña (almacenada con bcrypt) |
| real_name | string | Sí | max:50 | Nombre real |
| phone | string | No | | Teléfono (almacenado cifrado con Encryptable) |
| email | string | No | | Correo (almacenado cifrado con Encryptable) |
| status | int | No | in:0,1 | Estado, por defecto 1 (habilitado) |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**Posibles errores**:
- 422: el nombre de usuario ya existe
- 422: fallo de validación de parámetros (faltan campos obligatorios)

### 5.3 Detalle del usuario

```
GET /admin/user/{id}
```

- **Autenticación**: JWT + RBAC
- **Parámetro de ruta**: `{id}` es el ID de usuario cifrado con hashid

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

En la interfaz de detalle, `phone` y `email` se devuelven en claro (en la base de datos están cifrados; el cast Encryptable los descifra automáticamente), sin enmascarar. `password` e `id_card` nunca aparecen en las respuestas.

**Posibles errores**:
- 404: el usuario no existe

### 5.4 Actualizar usuario

```
PUT /admin/user/{id}
```

- **Autenticación**: JWT + RBAC
- **Parámetro de ruta**: `{id}` es el ID de usuario cifrado con hashid

**Cuerpo de la solicitud**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| real_name | string | No | Nombre real; si no se envía, conserva el valor original |
| password | string | No | Nueva contraseña; si es cadena vacía o no se envía, no se modifica |
| phone | string | No | Teléfono |
| email | string | No | Correo |
| status | int | No | 0=deshabilitado, 1=habilitado |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**Posibles errores**:
- 404: el usuario no existe

### 5.5 Eliminar usuario

```
DELETE /admin/user/{id}
```

- **Autenticación**: JWT + RBAC
- **Parámetro de ruta**: `{id}` es el ID de usuario cifrado con hashid
- **Operación sensible**: requiere segunda confirmación de contraseña

**Cuerpo de la solicitud**:
```json
{
  "password": "admin_password"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| password | string | Sí | Contraseña del usuario autenticado actualmente (segunda confirmación) |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Se realiza un borrado lógico (Eloquent SoftDeletes); los datos se marcan con deleted_at sin eliminación física.

**Posibles errores**:
- 404: el usuario no existe
- 422: las operaciones sensibles requieren confirmación de contraseña (password vacía)
- 422: fallo de validación de contraseña (las contraseñas no coinciden)

### 5.6 Eliminar usuarios en lote

```
POST /admin/user/batch/destroy
```

- **Autenticación**: JWT + RBAC
- **Operación sensible**: requiere segunda confirmación de contraseña

**Cuerpo de la solicitud**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| ids | array{string} | Sí | Array de ID de usuario cifrados con hashid |
| password | string | Sí | Contraseña del usuario autenticado actualmente (segunda confirmación) |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Se realiza un borrado lógico; `data.count` es el número realmente eliminado.

**Posibles errores**:
- 422: selecciona los usuarios a eliminar (ids vacío)
- 422: ID inválido (fallo de decodificación de hashid)
- 422: fallo de validación de contraseña

### 5.7 Habilitar/deshabilitar usuarios en lote

```
POST /admin/user/batch/status
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| ids | array{string} | Sí | Array de ID de usuario cifrados con hashid |
| status | int | Sí | 0=deshabilitado, 1=habilitado |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

El message varía dinámicamente según el valor de status: `"批量启用成功"` o `"批量禁用成功"`.

**Posibles errores**:
- 422: selecciona usuarios (ids vacío)
- 422: valor de estado inválido (status no es 0 ni 1)

## 6. Gestión de roles

### 6.1 Lista de roles

```
GET /admin/role
```

- **Autenticación**: JWT + RBAC

**Parámetros de consulta**:

| Parámetro | Tipo | Obligatorio | Valor predeterminado | Descripción |
|------|------|------|------|------|
| page | int | No | 1 | Número de página |
| limit | int | No | 15 | Elementos por página |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | ID de rol cifrado con hashid |
| name | string | Nombre del rol |
| slug | string | Identificador del rol (único, usado para la comprobación de permisos) |
| description | string | Descripción del rol |
| status | int | 1=habilitado, 0=deshabilitado |
| users_count | int | Número de usuarios con ese rol |

### 6.2 Crear rol

```
POST /admin/role
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| name | string | Sí | max:50 | Nombre del rol |
| slug | string | Sí | max:50 | Identificador del rol |
| description | string | No | | Descripción del rol, por defecto cadena vacía |
| status | int | No | | Estado, por defecto 1 |
| permission_ids | array{int} | No | | Array de ID de permisos (ID INT originales, no hashid) |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 Actualizar rol

```
PUT /admin/role/{id}
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| name | string | No | Nombre del rol |
| description | string | No | Descripción |
| status | int | No | 0=deshabilitado, 1=habilitado |
| permission_ids | array{int} | No | Array de ID de permisos; si se envía, sincroniza (sobrescribe) los permisos del rol |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 Eliminar rol

```
DELETE /admin/role/{id}
```

- **Autenticación**: JWT + RBAC
- **Operación sensible**: requiere segunda confirmación de contraseña

**Cuerpo de la solicitud**:
```json
{
  "password": "admin_password"
}
```

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Al eliminar se desvinculan automáticamente las relaciones del rol con todos los permisos y usuarios, y después se elimina físicamente el registro del rol.

## 7. Gestión de permisos

Los permisos usan estructura de árbol (parent_id auto-referenciado) y se dividen en tres tipos. La interfaz de lista devuelve el árbol de permisos completo.

### 7.1 Árbol de permisos

```
GET /admin/permission
```

- **Autenticación**: JWT + RBAC

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | Cifrado con hashid |
| parent_id | string | Hashid del permiso padre; «0» indica nodo raíz |
| name | string | Nombre del permiso |
| slug | string | Identificador del permiso (identificador de ruta/botón) |
| type | int | 1=menú, 2=botón, 3=interfaz |
| icon | string | Icono del menú (nombre de icono Material) |
| path | string | Ruta de enrutamiento del frontend |
| sort | int | Valor de orden (ascendente) |
| children | array? | Lista de subpermisos (recursiva); no se incluye si no hay nodos hijos |

### 7.2 Crear permiso

```
POST /admin/permission
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| parent_id | int | No | | ID del permiso padre (tipo INT original), por defecto 0 |
| name | string | Sí | max:50 | Nombre del permiso |
| slug | string | Sí | max:100 | Identificador del permiso |
| type | int | Sí | in:1,2,3 | 1=menú, 2=botón, 3=interfaz |
| icon | string | No | | Icono del menú, por defecto vacío |
| path | string | No | | Ruta de enrutamiento del frontend, por defecto vacía |
| sort | int | No | | Valor de orden, por defecto 0 |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 Actualizar permiso

```
PUT /admin/permission/{id}
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| name | string | No | Nombre del permiso |
| icon | string | No | Icono |
| path | string | No | Ruta de enrutamiento |
| sort | int | No | Valor de orden |

### 7.4 Eliminar permiso

```
DELETE /admin/permission/{id}
```

- **Autenticación**: JWT + RBAC
- **Operación sensible**: requiere segunda confirmación de contraseña

**Cuerpo de la solicitud**:
```json
{
  "password": "admin_password"
}
```

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Al eliminar se borran en cascada todos los subpermisos (registros con `parent_id` = ID del permiso actual) y se desvinculan las relaciones con todos los roles.

## 8. Configuración del sistema

La configuración del sistema es única por la combinación `group` + `key`.

### 8.1 Lista de configuración

```
GET /admin/config
```

- **Autenticación**: JWT + RBAC

**Parámetros de consulta**:

| Parámetro | Tipo | Obligatorio | Valor predeterminado | Descripción |
|------|------|------|------|------|
| page | int | No | 1 | Número de página |
| limit | int | No | 15 | Elementos por página |
| group | string | No | | Filtrar por grupo de configuración |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | hashid |
| group | string | Grupo de configuración (p. ej. `system`, `email`, `storage`) |
| key | string | Clave de configuración |
| value | string | Valor de configuración |
| type | string | Indicador de tipo de valor (`string`, `integer`, `boolean`, `json`, etc.) |
| description | string | Descripción de la configuración |

### 8.2 Crear configuración

```
POST /admin/config
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| group | string | Sí | max:100 | Grupo de configuración |
| key | string | Sí | max:100 | Clave de configuración (única dentro del grupo) |
| value | string | Sí | | Valor de configuración |
| type | string | No | | Tipo de valor, por defecto `string` |
| description | string | No | | Descripción de la configuración, por defecto vacía |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**Posibles errores**:
- 422: el elemento de configuración ya existe (mismo group + key)

### 8.3 Actualizar configuración

```
PUT /admin/config/{id}
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| value | string | No | Actualizar el valor de configuración |
| type | string | No | Actualizar el tipo de valor |
| description | string | No | Actualizar el texto de descripción |

### 8.4 Eliminar configuración

```
DELETE /admin/config/{id}
```

- **Autenticación**: JWT + RBAC
- **Operación sensible**: requiere segunda confirmación de contraseña

**Cuerpo de la solicitud**:
```json
{
  "password": "admin_password"
}
```

Eliminación física del registro de configuración.

## 9. Registros de operaciones

Los registros de operaciones son interfaces de solo lectura; el middleware `OperationLog` los escribe automáticamente en cada solicitud POST/PUT/DELETE. Los campos almacenados incluyen `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Lista de registros de operaciones

```
GET /admin/log
```

- **Autenticación**: JWT + RBAC

**Parámetros de consulta**:

| Parámetro | Tipo | Obligatorio | Valor predeterminado | Descripción |
|------|------|------|------|------|
| page | int | No | 1 | Número de página |
| limit | int | No | 15 | Elementos por página |
| user_id | int | No | | Filtro exacto por ID de usuario (tipo INT original) |
| action | string | No | | Filtro exacto por acción |
| path | string | No | | Filtro difuso por ruta de solicitud |
| start_date | string | No | | Fecha de inicio (formato Y-m-d) |
| end_date | string | No | | Fecha de fin (formato Y-m-d) |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | hashid |
| user_name | string | Nombre de usuario de la operación (obtenido mediante la relación user; las operaciones sin inicio de sesión muestran «Sistema») |
| action | string | Descripción de la acción |
| method | string | Método HTTP (POST/PUT/DELETE) |
| path | string | Ruta de la solicitud |
| ip | string | IP del cliente |
| source | string | Origen de la solicitud |
| input | string | Cadena JSON de los parámetros de la solicitud (sin archivos) |
| created_at | string | Hora de la operación (datetime) |

## 10. Centro personal

Las interfaces del centro personal solo requieren autenticación JWT (no requieren validación RBAC — el middleware `AdminPermission` debe añadirlas a su lista blanca).

### 10.1 Actualizar información personal

```
PUT /admin/profile
```

- **Autenticación**: JWT

**Cuerpo de la solicitud**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| real_name | string | No | Nombre real |
| phone | string | No | Teléfono (almacenado cifrado con Encryptable) |
| email | string | No | Correo (almacenado cifrado con Encryptable) |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

En la respuesta, `phone` y `email` se devuelven en claro; `password` e `id_card` están excluidos.

### 10.2 Cambiar contraseña

```
PUT /admin/profile/password
```

- **Autenticación**: JWT

**Cuerpo de la solicitud**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| old_password | string | Sí | | Contraseña actual |
| new_password | string | Sí | min:6, max:32 | Nueva contraseña |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Posibles errores**:
- 422: rellena la contraseña antigua y la nueva
- 422: la contraseña antigua es incorrecta
- 422: la nueva contraseña debe tener entre 6 y 32 caracteres

### 10.3 Cerrar sesión

```
POST /admin/profile/logout
```

- **Autenticación**: JWT

**Cuerpo de la solicitud**: ninguno (sin requestBody; el token se lee de la cabecera Authorization)

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Lógica de cierre de sesión: decodifica el JWT para obtener la validez restante (exp - now), escribe el hash md5 de ese token en la lista negra de Redis `jwt_blacklist:{md5}` con TTL = validez restante. Los tokens de la lista negra son interceptados por el middleware `AdminAuth` y devuelven 401.

Sin token se devuelve 401. Si el token está caducado o es inválido (la decodificación lanza una excepción), se considera cierre de sesión exitoso.

## 11. Importación y exportación

### 11.1 Exportar Excel

```
POST /admin/export/excel
```

- **Autenticación**: JWT + RBAC
- **Tipo de respuesta**: descarga de archivo (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Cuerpo de la solicitud**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| Campo | Tipo | Obligatorio | Valor predeterminado | Descripción |
|------|------|------|------|------|
| table | string | No | `admin_user` | Tabla a exportar. Soportadas: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | No | | Array de nombres de columnas a exportar; si está vacío, se exportan todas las columnas de la tabla |
| conditions | object | No | `{}` | Condiciones de filtro, pares clave-valor; los valores no vacíos se usan en el WHERE |
| title | string | No | `数据导出` | Título del Excel (se muestra como nombre de la hoja) |

**Tablas y columnas soportadas**:

| table | Columnas disponibles |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Los campos sensibles `phone`, `email` e `id_card` se enmascaran automáticamente al exportar. Límite de datos: 10000 filas. La primera fila del Excel está congelada y con autofiltro.

### 11.2 Exportar PDF

```
POST /admin/export/pdf
```

- **Autenticación**: JWT + RBAC
- **Tipo de respuesta**: descarga de archivo (`application/pdf`, A4 horizontal)

**Cuerpo de la solicitud**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

O modo tabla:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| Campo | Tipo | Obligatorio | Valor predeterminado | Descripción |
|------|------|------|------|------|
| type | string | No | `table` | Tipo de exportación: `table` / `dashboard` |
| title | string | No | `数据导出` | Título del PDF |
| data | object | No | `{}` | Datos de exportación |

Con `type=dashboard`, `data` debe contener el array `stats` (se renderiza como tarjetas); con `type=table`, `data` debe contener los arrays `columns` y `rows`.

La plantilla PDF incluye información de copyright y una marca de tiempo de exportación.

### 11.3 Importar usuarios (Excel)

```
POST /admin/import/users
```

- **Autenticación**: JWT + RBAC
- **Tipo de solicitud**: `multipart/form-data` (subida de archivo)

**Campos del formulario**:

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| file | file | Sí | Formato `.xlsx` o `.xls` |

**Requisitos de columnas del Excel**:

| Nombre de columna | Obligatorio | Descripción |
|------|------|------|
| username | Sí | Nombre de usuario (único) |
| password | Sí | Contraseña (almacenada como hash bcrypt) |
| real_name | Sí | Nombre real |
| phone | No | Teléfono |
| email | No | Correo |
| status | No | Estado, por defecto 1 |

La fila 1 es el título de columnas (no distingue mayúsculas); los datos empiezan en la fila 2.

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| Campo | Tipo | Descripción |
|------|------|------|
| total | int | Número total de filas (sin incluir la fila de título) |
| success | int | Número de importaciones exitosas |
| failed | int | Número de filas fallidas |
| errors | array | Detalle de los fallos; cada elemento contiene row (número de fila del Excel) y reason (motivo del fallo) |

## 12. Subida de archivos

```
POST /admin/upload
```

- **Autenticación**: JWT + RBAC
- **Tipo de solicitud**: `multipart/form-data`

**Campos del formulario**:

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| file | file | Sí | Archivo a subir |

**Tipos de archivo permitidos**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Tamaño máximo de archivo**: 10 MB

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Los archivos se almacenan por fecha en directorios `public/upload/{Y-m-d}/`; el nombre del archivo es `md5(uniqid) + extensión original`. `url` es una ruta relativa a la raíz del sitio.

**Posibles errores**:
- 422: selecciona un archivo (no se subió ninguno)
- 422: tipo de archivo no soportado
- 422: el tamaño del archivo no puede superar 10 MB
- 500: fallo de subida del archivo (archivo inválido)

## 13. Cabeceras de respuesta

Todas las interfaces (inyectadas en la capa de middleware global) incluyen las siguientes cabeceras de respuesta:

| Cabecera | Descripción |
|----|------|
| `X-RateLimit-Limit` | Límite de tasa (número de veces) |
| `X-RateLimit-Remaining` | Número de solicitudes restantes |
| `X-RateLimit-Reset` | Marca de tiempo de reinicio de la ventana de límite |
| `Retry-After` | Solo se devuelve cuando se dispara el límite; segundos recomendados de espera |
| `X-Content-Type-Options` | `nosniff` (predeterminado de webman, prohíbe la detección de MIME) |
| `X-Frame-Options` | `DENY` (proporcionado por el middleware CORS/la configuración base de webman) |

Detalles del límite de tasa:
- Límite global predeterminado: 60 veces/minuto / IP+ruta
- Endpoint de inicio de sesión `/api/auth/login`: 10 veces/minuto
- Endpoint de registro `/api/auth/register`: 5 veces/minuto
- Usa algoritmo de ventana deslizante atómico de Redis (Lua ZSET) para evitar condiciones de carrera TOCTOU
- Fail-closed si Redis no está disponible: devuelve 503 (`Retry-After: 5`), no deja pasar las solicitudes

## 14. Análisis de datos (Analytics)

Todos los endpoints requieren autenticación (`AdminAuth` + `AdminPermission`), agregación en tiempo real en MySQL, 12 en total:

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/analytics/overview | Resumen de la plataforma (hoy/últimos 7 días) |
| GET | /admin/analytics/game-ranking | Ranking de juegos (?days=7) |
| GET | /admin/analytics/dau-trend | Tendencia DAU (?days=30) |
| GET | /admin/analytics/hourly-trend | Tendencia por horas |
| GET | /admin/analytics/action-distribution | Distribución de comportamientos |
| GET | /admin/analytics/revenue | Análisis de ingresos |
| GET | /admin/analytics/conversion | Tasa de conversión de juegos |
| GET | /admin/analytics/probability | Probabilidad conjunta/condicional |
| GET | /admin/analytics/retention | Análisis de retención D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Embudo de conversión |
| GET | /admin/analytics/arpu | Tendencia ARPU/ARPPU |
| GET | /admin/analytics/economy | Indicadores económicos de las monedas de juego |

## 15. Gestión de tickets (Ticket)

Todos los endpoints requieren autenticación (`AdminAuth` + `AdminPermission`), 5 en total:

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/ticket/list | Lista de tickets (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | Detalle del ticket (incluye respuestas) |
| POST | /admin/ticket/{hashid}/reply | Responder al ticket |
| POST | /admin/ticket/{hashid}/close | Cerrar el ticket |
| POST | /admin/ticket/{hashid}/assign | Asignar responsable (admin_id) |

## 16. Flujo de autenticación

Secuencia completa de autenticación:

```
1. 客户端请求 POST /api/captcha/generate
   (请求头: API-Version: v1)
    ↓
   服务端返回: key + base64 图片 + 点击目标提示
   
2. 用户点击图片目标位置，前/客户端收集点击坐标
   
3. 客户端请求 POST /api/auth/login
   (请求头: API-Version: v1, Content-Type: application/json)
   请求体: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   服务端:
   a. 参数校验 → 422
   b. 校验验证码 → 422
   c. 校验用户凭证 → 401
   d. 检查账号状态 → 403
   e. 签发 JWT (access + refresh) → 200
   f. 更新 last_login_at / last_login_ip
    ↓
   客户端保存: access_token, refresh_token, expires_in

4. 后续请求携带 JWT
   请求头: Authorization: Bearer <access_token>
    ↓
   AdminAuth 中间件:
   a. 提取 Bearer token
   b. 检查黑名单 (Redis jwt_blacklist:{md5}) → 401
   c. 解码 JWT，校验过期 → 401
   d. 设置 $request->adminId = sub 字段
    ↓
   AdminPermission 中间件:
   a. 未登录（adminId 为空）→ 401
   b. 对资源路由解析权限标识
   c. 查询用户角色 → 角色权限，进行匹配
   d. 无权限 → 403
    ↓
   Controller 处理请求
    ↓
   Response + X-RateLimit-* 头

5. Access Token 过期前刷新
   客户端请求 POST /api/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

6. 登出
   客户端请求 POST /admin/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### Estructura del JWT

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, TTL predeterminado 7200 segundos (controlado por la configuración JWT `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, TTL predeterminado 1209600 segundos (controlado por la configuración JWT `refresh_expire`, es decir, 14 días)

### Gestión de seguridad

- Las contraseñas se almacenan con hash `PASSWORD_BCRYPT`
- Los campos sensibles (phone, email, id_card) se cifran/descifran de forma transparente en la capa de base de datos con `erikwang2013/encryptable`
- Los ID de la capa API se transmiten cifrados con `erikwang2013/hashids` para evitar exponer la secuencia de ID originales de snowflake
- SecurityFilter escanea globalmente XSS, inyección SQL, path traversal e inyección de comandos; la misma IP 5 veces/60 segundos entra en lista negra temporal de 15 minutos
- Las operaciones sensibles (eliminar usuarios, roles, permisos, configuración) requieren la segunda confirmación con la contraseña del usuario autenticado actualmente
- Límite de sesiones concurrentes: máximo 3 tokens válidos por usuario; al iniciar sesión un cuarto dispositivo, el token más antiguo se fuerza a la lista negra
- Bloqueo de cuenta: 5 inicios de sesión fallidos consecutivos disparan un bloqueo de 15 minutos; durante el bloqueo se devuelve 429

## 15. Despliegue y operación

### Docker Compose

La raíz del proyecto incluye `docker-compose.yml`, con orquestación de 5 servicios (Nginx, aplicación webman, MySQL, Redis, Elasticsearch). PHP se construye con el `Dockerfile` (basado en `php:8.3-cli`, con OPcache habilitado).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` define el pipeline de integración continua de GitHub Actions:
- Comprobación de sintaxis `php -l`
- Pruebas unitarias PHPUnit
- Análisis estático `flutter analyze`

### Copia de seguridad de base de datos

El directorio `database/backup/` ofrece scripts de copia de seguridad y restauración:
- `backup.sh` — copia de seguridad comprimida mysqldump + gzip, limpieza automática de copias antiguas de hace más de 30 días
- `restore.sh` — restauración interactiva, muestra las copias disponibles para elegir

### Configuración de seguridad de Nginx

Para despliegues en producción, consulta `docs/nginx-security.conf` para el refuerzo de seguridad del proxy inverso.

## 16. Análisis de datos (Analytics)

Las interfaces de análisis de datos las proporciona `AnalyticsController` y se basan todas en agregación en tiempo real en MySQL (`game_game_play_log` registros de comportamiento de juego / `game_deposit_order` pedidos de recarga); ante un fallo de la base de datos devuelven datos vacíos en lugar de 500. Salvo indicación contraria, todas requieren autenticación JWT + RBAC y el formato de respuesta envuelto es uniforme: `{ "code": 0, "message": "success", "data": ... }`.

### 16.1 Resumen de la plataforma

```
GET /admin/analytics/overview
```

**Respuesta**: `today` / `week` contienen cada uno `dau` (número de usuarios activos), `revenue` (total de recargas confirmadas, cadena) y `new_users` (número de nuevos usuarios).

### 16.2 Ranking de juegos

```
GET /admin/analytics/game-ranking?days=7
```

**Respuesta**: las 10 primeras posiciones en orden descendente por número de comportamientos de juego; cada elemento contiene `game_id` (hashid), `name`, `plays`, `players`.

### 16.3 Tendencia DAU

```
GET /admin/analytics/dau-trend?days=30
```

**Respuesta**: `{ "日期": 活跃数, ... }`; las fechas ausentes se rellenan con 0.

### 16.4 Tendencia por horas

```
GET /admin/analytics/hourly-trend?game_id=<hashid>
```

**Respuesta**: `{ "0": 次数, ... "23": 次数 }` 24 franjas horarias; si `game_id` está vacío, se cuentan todos los juegos.

### 16.5 Distribución de comportamientos

```
GET /admin/analytics/action-distribution?game_id=<hashid>&hours=24
```

**Respuesta**: `{ "start": n, "end": n, "earn": n, "spend": n }` recuento de cuatro tipos de comportamiento; `hours` tiene un máximo de 168.

### 16.6 Resumen de ingresos

```
GET /admin/analytics/revenue?days=7
```

**Respuesta**: `{ "total": "总额", "trend": { "日期": "当日额", ... } }`; solo se cuentan los pedidos con `status=confirmed`.

### 16.7 Tasa de conversión de juegos

```
GET /admin/analytics/conversion?days=30
```

**Respuesta**: cada juego contiene `game_id` (hashid), `game_name`, `players` (número de jugadores únicos), `depositors` (número de personas con recargas únicas), `conversion_rate` (tasa de conversión de recargas, 0~1).

### 16.8 Probabilidad conjunta

```
GET /admin/analytics/probability?game_a=<hashid>&game_b=<hashid>
```

**Respuesta**: `{ "joint": { "joint_probability": 0.12, "confidence": 0.3 } }` — coeficiente de Jaccard (jugadores comunes de ambos juegos / jugadores de la unión) y confianza (jugadores comunes / jugadores del juego A).

### 16.9 Análisis de retención

```
GET /admin/analytics/retention?days=30
```

**Respuesta**: `{ "D1": "8.5%", "D3": "...", "D7": "...", "D30": "..." }` tasa de retención al día siguiente/3/7/30 días, agrupada por día de registro.

### 16.10 Embudo de conversión

```
GET /admin/analytics/funnel?days=30
```

**Respuesta**: los cuatro pasos registro → primera recarga → primera conversión → primer juego, con `step`, `count`, `rate` (porcentaje relativo al número de registros).

### 16.11 Tendencia ARPU/ARPPU

```
GET /admin/analytics/arpu?days=30
```

**Respuesta**: `{ "dates": [...], "arpu": [...], "arppu": [...] }` ingresos diarios por usuario (ARPU) e ingresos por usuario de pago (ARPPU).

### 16.12 Indicadores económicos de los juegos

```
GET /admin/analytics/economy
```

**Respuesta**: array `currencies`; cada elemento contiene `game_name`, `currency`, `symbol`, `total_minted` (total acuñado), `total_burned` (total quemado), `circulation` (en circulación), `inflation_rate` (tasa de inflación), calculado con aritmética de alta precisión bcmath.
