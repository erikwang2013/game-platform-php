# Panel de administración abierto — Documento de diseño
<!-- lang-nav -->

Languages: [中文](DESIGN.md) · [English](DESIGN.en.md) · [한국어](DESIGN.ko.md) · [Русский](DESIGN.ru.md) · [Deutsch](DESIGN.de.md) · [Français](DESIGN.fr.md) · **Español** · [Português](DESIGN.pt.md) · [हिन्दी](DESIGN.hi.md) · [العربية](DESIGN.ar.md) · [বাংলা](DESIGN.bn.md) · [Bahasa Indonesia](DESIGN.id.md) · [日本語](DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Para los diagramas Mermaid detallados consulta [ARCHITECTURE.es.md](ARCHITECTURE.es.md) (se renderizan automáticamente en GitHub/GitLab/VS Code).

## 1. Arquitectura del sistema

> **Lista de funciones**: autenticación (login/register/refresh/logout + bloqueo de cuenta + límite de sesiones) | panel (caché Redis) | CRUD de usuarios + lote + importación | roles y permisos (RBAC) | configuración del sistema | auditoría de operaciones (8 orígenes de plataforma) | archivos (subida + exportación + enmascarado) | seguridad (18 capas de defensa) | operación y mantenimiento (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Arquitectura del backend

### 2.1 Diseño por capas

| Capa | Directorio | Responsabilidad |
|---|------|------|
| Rutas | `config/route.php` | Mapeo de URL a controladores, enlace de middleware, rutas versionadas |
| Middleware | `app/middleware/` | Intercepción de ataques (SecurityFilter), límite de tasa (RateLimit), autenticación (JWT), autorización (RBAC) |
| Controladores | 30: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs/Metrics/Analytics/Game/Payment/Withdraw... (panel de administración) + Captcha/Auth (API v1) | Validación de parámetros de solicitud, lógica de negocio, formato de respuesta |
| Servicios de negocio | `common/service/` | Análisis de datos: GameDashboardService (resumen/ranking/tendencias), DepositLogService (ingresos/conversión), ProbabilityService (probabilidad conjunta/condicional, constructor SQL); ante fallo de BD devuelve datos vacíos en lugar de errores |
| Modelos de datos | `app/model/` | Mapeo ORM, relaciones, cifrado/descifrado de campos |
| Utilidades comunes | `app/common/` | Servicios Hashids, Snowflake, Encryption |

### 2.2 Ciclo de vida de las solicitudes

```
客户端请求
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 匹配
  │
  ▼
中间件链:
  SecurityFilter ──────► HTTP方法检查 → 405 (仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截 (403)
  ▼
  RateLimit ───────────► Redis 滑动窗口限流
  │ (失败返回 429 + Retry-After 头)
  ▼
  AdminAuth ──────────► JWT 验证，注入 $request->adminId
  │ (失败返回 401)
  ▼
  AdminPermission ────► RBAC 权限校验（Redis 60s 缓存）
  │ (失败返回 403)
  ▼
  OperationLog ───────► 操作日志记录 (POST/PUT/DELETE)，自动检测来源端
  │
  ▼
Controller::method()
  │
  ├─► 参数验证 (validator)
  ├─► 敏感操作确认 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 操作 (自动 encryptable 加解密)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 Ciclo de vida de los ID

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Sistema de cifrado de datos

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-128-ECB，独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. Diseño de la base de datos

### 3.1 Relaciones ER

```
game_admin_user ──┬── game_admin_user_role ──┬── game_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    game_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    game_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           game_operation_log
             (操作日志)

game_system_config (系统配置) — 独立表
```

### 3.2 Estructura de las tablas principales

| Nombre de tabla | N.º de campos | Descripción |
|------|-------|------|
| `game_admin_user` | 14 | Usuarios de administración; phone/email/id_card almacenados cifrados, soporta borrado lógico |
| `game_admin_role` | 7 | Roles; slug único |
| `game_admin_permission` | 10 | Árbol de permisos (parent_id auto-referenciado), type: 1=menú 2=botón 3=API |
| `game_admin_user_role` | 2 | Tabla intermedia muchos-a-muchos usuario-rol |
| `game_admin_role_permission` | 2 | Tabla intermedia muchos-a-muchos rol-permiso |
| `game_system_config` | 8 | Configuración clave-valor; group+key únicos combinados |
| `game_operation_log` | 9 | Registro de auditoría de operaciones (incluye source de origen) |

### 3.3 Estándar de claves primarias

- Tipo: `BIGINT UNSIGNED NOT NULL`
- Característica: **no autoincremental**, generada en la capa de aplicación con el algoritmo Snowflake
- Ventajas: único globalmente, amigable con entornos distribuidos, incremento tendencial favorable a los índices, no expone el volumen de negocio
- Configuración: datacenter_id(0-31) + worker_id(0-31), soporta 1024 nodos concurrentes

## 4. Diseño de API

### 4.1 Estándar de URL

```
公开接口:  /api/v1/captcha/{generate|verify}
           /api/v1/auth/{login|register|refresh}

管理端:   /admin/v1/{resource}[/{hashid}]
          /admin/v1/export/{excel|pdf}

资源路由:
  GET    /admin/v1/user          → 列表
  POST   /admin/v1/user          → 创建
  GET    /admin/v1/user/{hashid} → 详情
  PUT    /admin/v1/user/{hashid} → 更新
  DELETE /admin/v1/user/{hashid} → 删除（需密码确认）

系统配置:  /admin/v1/config[/{hashid}]
操作日志:  /admin/v1/log
个人中心:  /admin/v1/profile[/password|/logout]
导入:     /admin/v1/import/users
上传:     /admin/v1/upload
批量:     /admin/v1/user/batch/{destroy|status}
文档:     /api/docs     (OpenAPI 3.0)
健康:     /health
```

### 4.2 Estrategia de versión de API

La versión va en el prefijo de la ruta URL (por defecto `v1`); no se usa cabecera de solicitud:

| Mecanismo | Descripción |
|------|------|
| Versión predeterminada | Por defecto `v1`, determinada por el prefijo del grupo de rutas |
| Enrutamiento | Los prefijos de grupo de rutas `/api/v1`, `/admin/v1` mapean la versión al espacio de controladores; la función auxiliar `v()` resuelve la clase del controlador según la versión |
| Directorios | Los controladores se organizan por versión: `app/api/{version}/controller/` |

Ejemplo de extensión — añadir una API v2:
1. Crea `app/api/v2/controller/AuthController.php`
2. Registra un grupo de rutas `/api/v2` y enlaza el controlador
3. Pasa la versión explícitamente a `v()`: `v('AuthController', 'login', 'v2')`

```bash
# Usar v1
curl http://host/api/v1/auth/login

# Usar v2
curl http://host/api/v2/auth/login
```

### 4.3 Estrategia de límite de tasa

Basado en algoritmo de ventana deslizante con Redis Sorted Set, ejecutado con script Lua atómico:

| Interfaz | Límite |
|------|------|
| Predeterminado | 60 veces/minuto/IP/ruta |
| POST /api/v1/auth/login | 10 veces/minuto |
| POST /api/v1/auth/register | 5 veces/minuto |

Al superar el límite se devuelve 429; las cabeceras de respuesta incluyen X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 Respuesta unificada

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Significado | Escenario de activación |
|------|------|---------|
| 0 | Éxito | Respuesta normal |
| 400 | Error de parámetros | Formato de solicitud incorrecto |
| 401 | No autenticado | Token ausente/caducado/inválido |
| 403 | Sin permiso | El rol del usuario no incluye el permiso requerido |
| 404 | No existe | Recurso no encontrado |
| 422 | Fallo de validación | Los parámetros del formulario no cumplen las reglas / fallo de confirmación de contraseña |
| 500 | Error del servidor | Excepción inesperada |

### 4.5 Flujo de autenticación (con captcha de clic)

```
客户端                               服务端
  │                                    │
  │  ① POST /api/v1/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② 用户点击图中文字位置              │
  │                                    │
  │  ③ POST /api/v1/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/v1/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 Modelo de permisos (RBAC)

```
  用户 ──┬── 角色 ──┬── 权限
  User     Role      Permission
                 │
                 ├── type=1: 菜单 (控制侧边栏可见)
                 ├── type=2: 按钮 (控制页面内操作)
                 └── type=3: API  (控制接口访问)

  权限标识格式: {method}.{path}
  例: get.admin/user  post.admin/user  delete.admin/user
  超级管理员标识: * (跳过所有权限检查)
```

### 4.7 Segunda confirmación de operaciones sensibles

Las operaciones sensibles como eliminar usuarios, roles o permisos requieren enviar la contraseña del usuario actual en el cuerpo de la solicitud para verificar la identidad:

```
客户端                           服务端
  │                                │
  │  DELETE /admin/v1/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 密码错误返回 422
  │                                │ → 密码正确继续执行
  │◄── 200 { code: 0 }           │
```

El frontend muestra un diálogo de confirmación antes de ejecutar la operación de borrado y envía la solicitud tras recoger la contraseña del usuario.

### 4.8 Gestión de métodos de pago

El módulo de gestión de métodos de pago (`PaymentController` + Flutter `payment_page.dart`) proporciona 5 endpoints, todos requieren autenticación JWT + RBAC:

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/v1/payment/method/list | Lista (ascendente por sort) |
| POST | /admin/v1/payment/method/toggle | Activar/desactivar |
| POST | /admin/v1/payment/method/create | Crear |
| PUT | /admin/v1/payment/method/{hashid} | Actualizar (solo campos enviados) |
| DELETE | /admin/v1/payment/method/{hashid} | Eliminar (422 si existen pedidos pendientes) |

- **Lista blanca de provider**: `stripe` / `nowpayments` / `coinbase`
- **Campos**: name / type (fiat|crypto) / provider / status / sort / countries[] (visibilidad por país, vacío = global) / currency / min_amount / max_amount / config (JSON, almacenado cifrado)
- **Protección de borrado**: el borrado devuelve 422 mientras existan pedidos con status=pending
- **Frontend**: Flutter `payment_page.dart` — lista + diálogo crear/editar + toggle activar/desactivar

## 5. Diseño del frontend

### 5.1 Panel de administración Flutter Web

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

Características: barra lateral plegable, doble tema Material 3, tabla de datos de alta densidad, diálogos emergentes, interacciones por hover del ratón

### 5.2 Móvil HarmonyOS

Rutas de páginas:

| Página | Ruta | Descripción |
|------|------|------|
| LoginPage | `pages/LoginPage` | Inicio de sesión con usuario/contraseña + captcha de clic |
| DashboardPage | `pages/DashboardPage` | Tarjetas de estadísticas + operaciones recientes |
| UserListPage | `pages/UserListPage` | Lista de usuarios, búsqueda + refresco por arrastre + carga al desplazar |
| UserDetailPage | `pages/UserDetailPage` | Crear/editar/ver/eliminar (confirmación AlertDialog) |
| ProfilePage | `pages/ProfilePage` | Centro personal, cierre de sesión (confirmación AlertDialog) |

Flujo de datos: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Diseño de seguridad

### 6.1 Defensa en profundidad

| Capa | Medida |
|------|------|
| Restricción de métodos | Lista blanca de métodos HTTP de SecurityFilter; solo GET/POST/PUT/DELETE/OPTIONS/HEAD; métodos no estándar devuelven 405 |
| Intercepción de ataques | Middleware SecurityFilter: detección e intercepción de XSS/inyección SQL/path traversal/inyección de comandos/CSRF |
| Verificación humana | Captcha de clic (Click Captcha), validación obligatoria en inicio de sesión/registro |
| Bloqueo de cuenta | 5 inicios de sesión fallidos consecutivos bloquean la cuenta 15 minutos; durante el bloqueo se devuelve 429 |
| Límite de sesiones | Máximo 3 tokens concurrentes por usuario; al superarlo, el más antiguo va a la lista negra automáticamente |
| Límite de tasa | Middleware RateLimit, ventana deslizante Redis, atómico con Lua |
| CSP | Cabecera Content-Security-Policy restringe el origen de los recursos, contra XSS e inyección de datos |
| Confirmación de operaciones | Las operaciones sensibles como borrados requieren la segunda confirmación con la contraseña del usuario actual |
| Transmisión | HTTPS + JWT Bearer Token |
| ID de interfaz | Cifrados con Hashids, el ID real no se puede deducir desde el exterior |
| Cuerpo de solicitud | Cifrado AES-256-CBC de campos sensibles |
| Base de datos | Claves primarias BIGINT (no expone el incremento) |
| Base de datos | Campos sensibles almacenados cifrados con AES-128-ECB |
| Autenticación | JWT HS256, caducidad de 2 h + refresh token |
| Autorización | RBAC, control de permisos con granularidad method.path |
| Auditoría | OperationLog registra todas las operaciones (incluye detección automática del origen `source`) |

### 6.2 Gestión de claves

```
JWT_SECRET          → 环境变量注入，64位随机字符串
HASHIDS_SALT        → 唯一盐值，泄漏后需全局更换
ENCRYPTION_KEY      → API 传输加密密钥，32字节
ENCRYPTABLE_KEY     → DB 存储加密密钥，与传输密钥独立
SCOUT_HOSTS         → ES 地址，内网部署
```

### 6.3 Protección de datos sensibles

| Escenario | Campo | Medida |
|------|------|------|
| Mostrar en listas | phone | Enmascarado: 138****1234 |
| Mostrar en listas | email | Enmascarado: a***@example.com |
| Ver detalle | phone/email | Requiere interfaz de descifrado |
| Exportar Excel | phone/email | Exportación enmascarada |
| Exportar PDF | Todos los campos | Enmascarado + marca de agua de copyright no removible |
| Almacenamiento | phone/email/id_card | Cifrado a texto cifrado con encryptable |

## 7. Diseño de exportación

### 7.1 Exportación Excel

```
请求: POST /admin/v1/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 Exportación PDF

```
请求: POST /admin/v1/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. Arquitectura de despliegue

### 8.1 Topología recomendada

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                    静态文件: Flutter Web build/
```

### 8.2 Docker Compose (recomendado para producción)

El `docker-compose.yml` de la raíz del proyecto orquesta todos los servicios de la topología anterior:

| Servicio | Imagen/construcción | Puerto | Descripción |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Proxy inverso + archivos estáticos + Gzip |
| `app` | construido con el `Dockerfile` local | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Base de datos principal, volúmenes de datos persistentes |
| `redis` | redis:7-alpine | 6379 | Caché / límite de tasa / captcha |
| `elasticsearch` | elasticsearch:8.x | 9200 | Búsqueda de texto completo |

Antes de arrancar, sustituye las claves `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY`, etc. de `docker-compose.yml` por cadenas aleatorias.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

La integración continua de GitHub Actions se define en `.github/workflows/ci.yml`:
- Comprobación de sintaxis PHP (`php -l`)
- Pruebas unitarias PHPUnit
- Análisis estático Flutter (`flutter analyze`)

### 8.4 Copia de seguridad de base de datos

`database/backup/backup.sh` — copia de seguridad mysqldump + gzip, limpieza automática de copias antiguas de hace más de 30 días.
`database/backup/restore.sh` — selección interactiva y restauración de copias.

### 8.5 Monitorización

El endpoint `GET /metrics` (`MetricsController`) expone 5 métricas gauge en formato texto de Prometheus: total de solicitudes HTTP, usuarios activos, estado de conexión de base de datos/Redis, uso de memoria.

### 8.6 Requisitos del entorno

| Componente | Versión mínima | Configuración recomendada |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ con OPcache habilitado |
| MySQL | 8.0+ | 8.0+ con replicación maestro-esclavo |
| Elasticsearch | 7.x | 8.x clúster de 3 nodos |
| Redis | 6.x | 7.x modo centinela |
| Nginx | 1.20+ | Proxy inverso + gzip + SSL |
| Flutter SDK | 3.41+ | Última versión estable |
| HarmonyOS | API 12 | DevEco Studio 5.x |
