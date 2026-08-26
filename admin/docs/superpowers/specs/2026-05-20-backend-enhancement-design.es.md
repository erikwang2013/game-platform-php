# Subproyecto A: Mejora del backend — Especificación de diseño
<!-- lang-nav -->

Languages: [中文](2026-05-20-backend-enhancement-design.md) · [English](2026-05-20-backend-enhancement-design.en.md) · [한국어](2026-05-20-backend-enhancement-design.ko.md) · [Русский](2026-05-20-backend-enhancement-design.ru.md) · [Deutsch](2026-05-20-backend-enhancement-design.de.md) · [Français](2026-05-20-backend-enhancement-design.fr.md) · **Español** · [Português](2026-05-20-backend-enhancement-design.pt.md) · [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) · [العربية](2026-05-20-backend-enhancement-design.ar.md) · [বাংলা](2026-05-20-backend-enhancement-design.bn.md) · [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) · [日本語](2026-05-20-backend-enhancement-design.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Alcance

Esta es una mejora del backend, con 15 puntos de funcionalidad en total, que involucra 9 archivos nuevos + 4 archivos modificados.

---

## Lista de archivos nuevos/modificados

```
app/middleware/
├── OperationLog.php          # Nuevo: registro automático de operaciones
├── Cors.php                  # Nuevo: CORS
└── RateLimit.php             # Nuevo: limitación de velocidad Redis
app/admin/controller/
├── ConfigController.php      # Nuevo: CRUD de configuración del sistema
├── LogController.php         # Nuevo: consulta de registros de operaciones
├── ProfileController.php     # Nuevo: centro personal (incluye cierre de sesión)
├── UploadController.php      # Nuevo: carga de archivos
├── ImportController.php      # Nuevo: importación de usuarios por Excel
└── HealthController.php      # Nuevo: verificación de salud
app/model/
├── AdminUser.php             # Modificado: añade SoftDeletes + trait Searchable
└── OperationLog.php          # Modificado: añade public $timestamps = false
app/middleware/
└── AdminAuth.php             # Modificado: verificación de lista negra JWT
app/admin/controller/
├── DashboardController.php   # Modificado: estadísticas en tiempo real desde la base de datos
└── UserController.php        # Modificado: nuevas acciones por lotes
config/
└── route.php                 # Modificado: nuevas rutas + middleware
```

---

## 1. Middleware

### 1.1 Middleware CORS

**Archivo**: `app/middleware/Cors.php`

- Las solicitudes de preflight OPTIONS devuelven directamente 204
- Las solicitudes que no son preflight añaden `Access-Control-Allow-Origin: *` a las cabeceras de respuesta
- Cabeceras permitidas: `Authorization, Content-Type, API-Version`
- Caché máxima: 86400 segundos

Montaje: middleware global (`config/middleware.php`)

### 1.2 Middleware de limitación de velocidad

**Archivo**: `app/middleware/RateLimit.php`

- Almacenamiento: ventana deslizante de Redis Sorted Set
- Predeterminado: 60 veces/minuto/IP/ruta
- Interfaces sensibles:
  - `/api/auth/login`: 10 veces/minuto
  - `/api/auth/register`: 5 veces/minuto
- Al superar el límite devuelve `429 Too Many Requests`

Montaje: middleware global (`config/middleware.php`), después de Cors y antes de ApiVersion

### 1.3 Middleware de registro de operaciones

**Archivo**: `app/middleware/OperationLog.php`

- Solo registra POST/PUT/DELETE
- Campos registrados: user_id, action, method, path, ip, input(JSON)
- Se escribe de forma asíncrona después de devolver la respuesta (no bloquea)

Montaje: grupo de rutas `/admin`, después de AdminPermission

### 1.4 Cadena de ejecución del middleware global

```
Todas las solicitudes:
  Cors → RateLimit → ApiVersion → {Middleware de ruta} → Controller

Solicitudes /admin/*:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Cierre de sesión (lista negra JWT)

**Archivo**: `app/middleware/AdminAuth.php` (modificado)

**Principio**: JWT es sin estado por sí mismo; al cerrar sesión se añade el token a la lista negra de Redis, y AdminAuth verifica primero la lista negra al validar.

**Reforma de AdminAuth**:
- Nuevo al inicio de `process()`: comprobar desde el conjunto `jwt_blacklist` de Redis si el token actual está en la lista negra
- Si está en la lista negra, devolver 401

**Ruta de cierre de sesión** (bajo el centro personal):

| Método | Ruta | Descripción |
|------|------|------|
| `POST` | `/admin/profile/logout` | Añade el token Bearer actual a la lista negra de Redis, TTL=tiempo restante de validez del token |

**Lógica de logout**:
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Nuevos controladores y reformas existentes

### 2.1 CRUD de configuración del sistema (`ConfigController`)

Hereda de `BaseController`.

| Método | Ruta | Descripción |
|------|------|------|
| `index()` | GET `/admin/config` | Lista paginada, filtrable por `group`, paginación `page`/`limit` |
| `store()` | POST `/admin/config` | Crea un elemento de configuración, obligatorio: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Actualiza value/type/description del elemento de configuración |
| `destroy()` | DELETE `/admin/config/{id}` | Elimina el elemento de configuración, requiere `confirmPassword()` |

### 2.2 Consulta de registros de operaciones (`LogController`)

Hereda de `BaseController`.

| Método | Ruta | Descripción |
|------|------|------|
| `index()` | GET `/admin/log` | Lista paginada, con filtros: user_id, action, path, created_at (rango) |

No se proporciona alta, baja ni modificación; los registros los graba automáticamente el middleware.

### 2.3 Centro personal (`ProfileController`)

Hereda de `BaseController`. Opera sobre el usuario actualmente conectado (`$request->adminId`).

| Método | Ruta | Descripción |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Actualiza real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Cambia la contraseña, requiere old_password, new_password, new_password_confirmation |

### 2.4 Carga de archivos (`UploadController`)

Hereda de `BaseController`.

| Método | Ruta | Descripción |
|------|------|------|
| `upload()` | POST `/admin/upload` | Recibe un archivo, admite image/jpeg/png/gif/pdf/xlsx/docx |

- Máximo 10MB
- Ruta de almacenamiento: `public/upload/{date}/{hash}.{ext}`
- Devuelve: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Datos reales del dashboard

**Archivo**: `app/admin/controller/DashboardController.php` (modificado)

Cambiar los datos simulados hardcodeados actuales por estadísticas en tiempo real desde la base de datos:

| Métrica | Fuente | Descripción |
|------|------|------|
| Total de usuarios | `AdminUser::count()` | Sin borrados suaves |
| Nuevos hoy | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Total de roles | `AdminRole::count()` | |
| Total de permisos | `AdminPermission::count()` | |
| Datos de tendencia | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Nuevos por día en los últimos 7 días |
| Datos de distribución | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Distribución por estado |
| Operaciones recientes | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | Últimas 10 operaciones registradas |

### 2.6 Operaciones por lotes de usuarios

**Archivo**: `app/admin/controller/UserController.php` (modificado, nuevos métodos)

| Método | Ruta | Descripción |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Borrado masivo, cuerpo de solicitud `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Habilitar/deshabilitar en masa, cuerpo de solicitud `{ ids: [hashid, ...], status: 1|0 }` |

- Cada id se convierte primero con `decodeId()` a BIGINT
- `batchDestroy()` debe pasar la verificación `confirmPassword()`

### 2.7 Importación de datos

**Archivo**: `app/admin/controller/ImportController.php` (nuevo)

| Método | Ruta | Descripción |
|------|------|------|
| `users()` | POST `/admin/import/users` | Sube un archivo Excel, crea usuarios en masa |

Flujo:
1. Recibe el archivo `.xlsx`
2. Parseo con PhpSpreadsheet, columnas esperadas: `username, password, real_name, phone, email, status`
3. Validación + creación fila por fila (ID generado por snowflake, contraseña bcrypt, phone/email cifrados con encryption)
4. Devuelve el resultado: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Verificación de salud

**Archivo**: `app/admin/controller/HealthController.php` (nuevo)

`GET /health` (sin autenticación, no se cuenta en el registro de operaciones):

Devuelve el estado de conexión de cada componente:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- Cuando falla la detección de un componente, el valor del campo correspondiente es una cadena con la descripción del error
- La ruta no lleva el prefijo `/admin`, se registra por separado a nivel global

---

## 3. Correcciones de modelos

### 3.1 Marca de tiempo de OperationLog

**Archivo**: `app/model/OperationLog.php` (modificado)

La tabla `erik_operation_log` solo tiene la columna `created_at` (sin `updated_at`). El `save()` predeterminado de Eloquent intentará escribir `updated_at`, lo que provoca un error SQL.

Corrección: `public $timestamps = false;` + especificar `created_at` manualmente al escribir.

### 3.2 Reforma del modelo AdminUser

- Añadir el trait `Searchable`
- Implementar `toSearchableArray()`: devuelve username, real_name
- `UserController::index()` usa `AdminUser::search($kw)->get()` en lugar de MySQL LIKE cuando detecta una palabra clave

ES necesita crear primero el índice, se puede hacer con el comando de Scout:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Cambios de rutas

Nuevas rutas en `config/route.php`:

```php
// /admin 路由组内新增:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// 健康检查（全局路由，非 /admin 组内）
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// 中间件:
/admin 组中间件追加 app\middleware\OperationLog::class
```

Registro del middleware global en `config/middleware.php`:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Códigos de error adicionales

| code | Significado | Escenario de activación |
|------|------|---------|
| 429 | Demasiadas solicitudes | Se activa RateLimit |

---

## 6. Fuera del alcance de esta iteración

- Sistema de notificaciones (requiere cola de mensajes + infraestructura de push en el frontend)
- Páginas de frontend Flutter (subproyecto B)
- Refresco de token HarmonyOS (subproyecto C)
