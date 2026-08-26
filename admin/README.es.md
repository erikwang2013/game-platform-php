# Panel de administración abierto (open-admin)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · **Español** · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)


Sistema de panel de administración full-stack basado en webman v2 + Flutter.

> [English version](README.en.md) | [Diagrama de arquitectura](docs/ARCHITECTURE.es.md) | [Documento de diseño](docs/DESIGN.es.md) | [Arquitectura de seguridad](docs/SECURITY.es.md) | [Referencia de API](docs/API.es.md)

## Lista de funciones

| Dominio de negocio | Función | Descripción |
|--------|------|------|
| 🔐 Autenticación | Inicio de sesión/registro/refresco de token/cierre de sesión | Captcha de clic + JWT + lista negra |
| | Bloqueo de cuenta | 5 intentos fallidos bloquean 15 minutos |
| | Límite de sesiones concurrentes | Máximo 3 tokens válidos por usuario |
| 📊 Panel | Estadísticas en tiempo real/gráfico de tendencias/gráfico de distribución/operaciones recientes | Caché Redis de 5 minutos |
| 📈 Análisis de datos | 12 endpoints: resumen/ranking/DAU/horario/distribución de comportamiento/ingresos/conversión/probabilidad/retención/embudo/ARPU/indicadores económicos | Agregación en tiempo real en MySQL, devuelve datos vacíos si la BD falla |
| 👥 Gestión de usuarios | CRUD + borrado masivo/habilitar-deshabilitar | Borrado lógico + segunda confirmación de contraseña |
| | Importación masiva de Excel | Validación fila por fila + informe de errores |
| 🔒 Roles y permisos | CRUD de roles + árbol de permisos | Autorización RBAC con granularidad method.path |
| ⚙ Configuración del sistema | CRUD de pares clave-valor | Gestión por grupos |
| 📋 Auditoría de operaciones | Consulta de registros + detección de origen | Reconocimiento automático de 8 plataformas |
| 📁 Gestión de archivos | Subida/exportación Excel/exportación PDF | Enmascarado automático de datos sensibles |
| 🛡 Protección de seguridad | 18 capas de defensa en profundidad | XSS/inyección SQL/path traversal/inyección de comandos/CSRF/límite de tasa/CSP... |
| 🏥 Operación y mantenimiento | Comprobación de salud/metrics/documentación de API/security.txt | Prometheus + OpenAPI 3.0 |

## Stack tecnológico

| Capa | Tecnología | Descripción |
|---|------|------|
| Framework backend | webman v2 (workerman) | Framework PHP de alto rendimiento con procesos residentes |
| Versión de PHP | 8.3+ | |
| Base de datos | MySQL 8.0+ | Prefijo de tabla `erik_`, claves primarias BIGINT no autoincrementales |
| Motor de búsqueda | Elasticsearch | Sincronización y consulta mediante `webman-scout` |
| Frontend de administración | Flutter 3.x | El lado web usa estilo de panel de administración PC (`apps/flutter/`) |
| Móvil | HarmonyOS ArkTS | Cliente nativo HarmonyOS (`apps/harmonyos/`), compatible con teléfono/tableta/2en1 |

## Dependencias principales

| Paquete | Uso |
|---|------|
| `erikwang2013/snowflake-php` | Generación de claves primarias BIGINT globalmente únicas con algoritmo Snowflake |
| `erikwang2013/hashids` | Cifrado/descifrado de ID en la capa API, oculta los ID reales de la base de datos |
| `erikwang2013/jwt-webman` | Emisión y verificación de tokens de autenticación JWT |
| `erikwang2013/encryption` | Cifrado/descifrado de datos sensibles en la capa de transmisión de interfaces |
| `erikwang2013/encryptable` | Cifrado/descifrado automático de campos sensibles en la capa de almacenamiento |
| `erikwang2013/webman-scout` | Sincronización de datos y búsqueda de texto completo en Elasticsearch |
| `erikwang2013/season` | Datos de banderas de países |
| `erikwang2013/poster-php` | Generación y verificación de captcha de clic + generación de pósteres |
| `phpoffice/phpspreadsheet` | Exportación de Excel |
| `barryvdh/laravel-dompdf` | Exportación de PDF (basado en Dompdf) |

## Estructura del proyecto

```
open-admin/
├── app/
│   ├── admin/controller/       # Controladores del panel de administración
│   │   ├── DashboardController.php # Panel (caché Redis)
│   │   ├── UserController.php      # CRUD de usuarios + operaciones masivas
│   │   ├── RoleController.php      # CRUD de roles
│   │   ├── PermissionController.php# CRUD de permisos
│   │   ├── ConfigController.php    # CRUD de configuración del sistema
│   │   ├── LogController.php       # Consulta de registros de operaciones
│   │   ├── ProfileController.php   # Centro personal + cierre de sesión
│   │   ├── ExportController.php    # Exportación Excel/PDF
│   │   ├── ImportController.php    # Importación de usuarios por Excel
│   │   ├── UploadController.php    # Subida de archivos
│   │   ├── HealthController.php    # Comprobación de salud
│   │   ├── DocsController.php      # Documentación OpenAPI
│   │   └── BaseController.php      # Controlador base
│   ├── api/
│   │   └── v1/controller/          # Controladores API v1 (la versión se controla por la cabecera API-Version)
│   │       ├── CaptchaController.php # Captcha de clic
│   │       └── AuthController.php    # Inicio de sesión/registro/refresco de token
│   ├── common/                 # Clases de utilidad comunes
│   │   ├── HashidsService.php  # Codificación/decodificación de ID
│   │   ├── SnowflakeService.php# Generación de ID Snowflake
│   │   └── EncryptionService.php # Cifrado/descifrado de datos + enmascarado
│   ├── middleware/             # Middleware
│   │   ├── Cors.php            # CORS
│   │   ├── SecurityFilter.php  # Intercepción de detección de ataques (restricción de métodos HTTP/XSS/inyección SQL/path traversal/inyección de comandos/CSRF)
│   │   ├── RateLimit.php       # Límite de tasa Redis (ventana deslizante + cabeceras de respuesta)
│   │   ├── ApiVersion.php      # Validación de versión de API
│   │   ├── AdminAuth.php       # Autenticación JWT + lista negra
│   │   ├── AdminPermission.php # Validación de permisos RBAC
│   │   └── OperationLog.php    # Registro automático de operaciones (incluye detección de origen)
│   └── model/                  # Modelos de datos
├── apps/
│   ├── flutter/                # Panel de administración Flutter Web (estilo PC)
│   │   └── lib/app/
│   │       ├── pages/          # 5 páginas completas (panel/usuarios/roles/configuración/registros/centro personal)
│   │       ├── services/       # ApiService (interceptor JWT) + AuthService (persistencia de Token)
│   │       └── layouts/        # Diseño de panel responsive (barra lateral + barra superior + área de contenido)
│   └── harmonyos/              # Cliente nativo HarmonyOS (refresco transparente de Token)
├── config/                     # Archivos de configuración (con comentarios en chino)
│   ├── route.php               # Rutas + estrategia de versión de API
│   ├── middleware.php           # Registro de middleware global
│   └── ...                     # Configuraciones de componentes
├── database/migrations/        # Archivos de migración SQL (incluyen datos semilla de permisos)
├── public/                     # Punto de entrada público
├── runtime/                    # Archivos en tiempo de ejecución
└── vendor/                     # Dependencias Composer
```

## Requisitos del entorno

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (solo necesario para desarrollo frontend)
- Elasticsearch >= 7.x (opcional, necesario para la búsqueda)

## Inicio rápido

### 1. Instalar dependencias

```bash
composer install
```

### 2. Configurar variables de entorno

Copia y modifica las variables de entorno (opcional; si no se configuran, se usan los valores predeterminados de `config/*.php`):

```bash
cp .env.example .env
```

Elementos de configuración clave:

| Variable de entorno | Descripción | Valor predeterminado |
|---------|------|--------|
| `JWT_SECRET` | Clave de firma JWT | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Sal de Hashids | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | Clave de cifrado de API | Valor predeterminado de 32 bytes |
| `SNOWFLAKE_DATACENTER_ID` | ID del centro de datos (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID del nodo de trabajo (0-31) | `1` |
| `SCOUT_HOSTS` | Dirección de ES | `http://localhost:9200` |

**En producción es imprescindible cambiar todas las claves por cadenas aleatorias.**

### 3. Inicializar la base de datos

Ejecuta los archivos SQL de `database/migrations/` en orden:

```bash
# Crear tablas
mysql -u root -p < database/migrations/2026_05_16_000000_init_tables.sql
# Sembrar datos de permisos
mysql -u root -p < database/migrations/2026_05_20_000001_seed_permissions.sql
```

### 4. Iniciar el servicio

```bash
php start.php start
```

Por defecto escucha en `http://0.0.0.0:8787`.

### 5. Iniciar el frontend (opcional)

**Panel de administración Flutter (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Lado web (estilo de panel de administración PC)
```

**Cliente HarmonyOS (teléfono):**

Usa DevEco Studio para abrir el directorio `apps/harmonyos/` y ejecutar en un dispositivo real o emulador.

### 6. Despliegue con un clic mediante Docker Compose (recomendado para producción)

El proyecto incluye una solución completa de orquestación Docker con 5 servicios: Nginx, PHP (aplicación webman), MySQL, Redis, Elasticsearch.

```bash
# 1. Configurar variables de entorno de Docker
cp .env.docker .env

# 2. Iniciar todos los servicios
docker-compose up -d

# 3. Inicializar la base de datos (ejecutar dentro del contenedor de la aplicación)
docker-compose exec app mysql -h mysql -u root -p < database/migrations/2026_05_16_000000_init_tables.sql
docker-compose exec app mysql -h mysql -u root -p < database/migrations/2026_05_20_000001_seed_permissions.sql

# 4. Acceso
# http://localhost:8787  (webman)
# http://localhost:8080  (proxy inverso Nginx)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, basado en `php:8.3-cli`
- `docker-compose.yml`: orquestación de 5 servicios, aislamiento de red, persistencia de volúmenes de datos
- `.env.docker`: variables de entorno específicas para el entorno Docker


## Estándares de base de datos

- **Prefijo de tabla**: `erik_`
- **Clave primaria**: todas las tablas usan `id BIGINT UNSIGNED NOT NULL` como clave primaria, **AUTO_INCREMENT prohibido**
- **Generación de ID**: los ID de clave primaria los genera `SnowflakeService::generate()` en la capa de aplicación, únicos de forma distribuida
- **Campos obligatorios**: toda tabla debe contener `id`, `created_at`, `updated_at`
- **Borrado lógico**: las tablas que necesiten borrado lógico añaden `deleted_at DATETIME DEFAULT NULL`
- **Campos sensibles**: teléfono, correo, número de DNI, etc. se cifran/descifran automáticamente con el plugin `encryptable`; los campos de base de datos usan `VARCHAR(500)` para almacenar el texto cifrado

## Estándares de API

### Formato de respuesta unificado

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Códigos de error de negocio

| Código de error | Significado | Descripción |
|-------|------|------|
| `0` | Éxito | |
| `400` | Error de parámetros de solicitud | |
| `401` | No autenticado (token inválido o caducado) | |
| `403` | Sin permiso / intercepción de seguridad | Fallo de autorización RBAC / detección de ataques de SecurityFilter |
| `404` | Recurso no encontrado | |
| `422` | Fallo de validación de parámetros | |
| `413` | Cuerpo de solicitud demasiado grande | Disparado por SecurityFilter, supera 10 MB |
| `405` | Método de solicitud no permitido | Disparado por SecurityFilter, solo permite GET/POST/PUT/DELETE/OPTIONS/HEAD |
| `415` | Tipo de medio no soportado | Disparado por SecurityFilter, Content-Type no es JSON |
| `429` | Demasiadas solicitudes | Disparado por RateLimit / bloqueo de cuenta (5 inicios de sesión fallidos bloquean 15 minutos) |
| `500` | Error interno del servidor | |

### Tratamiento de ID

- **ID en solicitudes/respuestas**: cifrados como cadenas con hashids, no se expone el ID real de la base de datos
- **Rutas de interfaz**: `GET /admin/user/{hashid}` — el `{id}` de la ruta es una cadena hashid
- **Almacenamiento en base de datos**: valor original BIGINT, generado por snowflake

### Versión de API

La versión de API se controla mediante cabecera de solicitud, **no aparece en la URL**:

```http
API-Version: v1
```

- Si no se envía versión, se usa `v1` por defecto
- Las versiones no soportadas devuelven `400 Bad Request`
- Para añadir una versión solo hay que crear el directorio `app/api/{version}/controller/` y registrar la nueva versión en el middleware

### Límite de tasa

Basado en algoritmo de ventana deslizante de Redis, por defecto 60 veces/minuto/IP/ruta. Las interfaces sensibles son más estrictas:
- Inicio de sesión: 10 veces/minuto
- Registro: 5 veces/minuto

Las cabeceras de respuesta incluyen `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. Al superar el límite se devuelve 429 con `Retry-After`.

### Arquitectura de middleware

El middleware global afecta a todas las solicitudes y se ejecuta en orden:

```
Cors（preprocesamiento CORS + cabeceras de respuesta）
  → SecurityFilter（restricción de métodos HTTP/tamaño del cuerpo/validación Content-Type/XSS/inyección SQL/path traversal/inyección de comandos/CSRF）
  → RateLimit（límite de tasa de ventana deslizante Redis + bloqueo de cuenta: 5 inicios de sesión fallidos bloquean 15 minutos）
  → ApiVersion（validación de versión de API, grupo de rutas /api）
  → AdminAuth（autenticación JWT + lista negra, grupo de rutas /admin）
  → AdminPermission（autorización RBAC, grupo de rutas /admin）
  → OperationLog（registro automático de POST/PUT/DELETE, incluye detección de origen, grupo de rutas /admin）
```

`/health` y `/api/docs` son endpoints públicos y solo pasan por `Cors → SecurityFilter → RateLimit`.

Mejoras de seguridad:
- **Bloqueo de cuenta**: 5 inicios de sesión fallidos consecutivos bloquean la cuenta automáticamente 15 minutos; durante el bloqueo el inicio de sesión devuelve 429
- **Límite de sesiones concurrentes**: máximo 3 tokens válidos por usuario; al superarlo, el token más antiguo se añade automáticamente a la lista negra
- **security.txt**: `GET /.well-known/security.txt` proporciona información de contacto de seguridad estándar RFC 9116
- **Configuración de seguridad de Nginx**: consulta `docs/nginx-security.conf` para un ejemplo completo de refuerzo de seguridad del proxy inverso

### Autenticación

El inicio de sesión y el registro requieren pasar primero el **captcha de clic**:

1. El cliente solicita `POST /api/captcha/generate` para obtener la imagen del captcha (PNG base64) y la lista de objetivos de texto
2. El usuario hace clic en las posiciones del texto correspondiente de la imagen en orden y recoge las coordenadas `[{x, y}, ...]`
3. Al iniciar sesión se envían también `captcha_key` y `clicks`; el servidor valida primero el captcha y después las credenciales

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

Las interfaces posteriores del panel de administración requieren autenticación JWT:

```http
Authorization: Bearer <token>
```

Tras iniciar sesión correctamente se devuelve access_token, con validez de 2 horas; también se devuelve refresh_token, con validez de 14 días.

Al cerrar sesión, el Token se añade a la lista negra de Redis y no se puede reutilizar mientras esté vigente. POST /admin/profile/logout

### Segunda confirmación de operaciones sensibles

Las operaciones sensibles como eliminar usuarios, roles o permisos requieren enviar la `password` del usuario autenticado actualmente en el cuerpo de la solicitud para confirmar la identidad por segunda vez:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## Lista de API

> Todas las interfaces `/api/*` requieren la cabecera `API-Version: v1` (si no se envía, se usa v1 por defecto).

### Interfaces públicas

| Método | Ruta | Descripción |
|-----|------|------|
| `GET` | `/health` | Comprobación de salud (estado de DB/Redis/ES) |
| `GET` | `/api/docs` | Documento de especificación OpenAPI 3.0 |
| `POST` | `/api/captcha/generate` | Generar captcha de clic |
| `POST` | `/api/captcha/verify` | Validar captcha de clic |
| `POST` | `/api/auth/login` | Inicio de sesión (requiere captcha) |
| `POST` | `/api/auth/register` | Registro (requiere captcha) |
| `POST` | `/api/auth/refresh` | Refrescar token |
| `GET` | `/metrics` | Métricas de monitorización Prometheus |

### Interfaces del panel de administración (requieren JWT + RBAC)

| Método | Ruta | Descripción |
|-----|------|------|
| `GET` | `/admin/dashboard` | Datos del panel (caché Redis de 5 minutos) |
| `GET` | `/admin/user` | Lista de usuarios (paginación + búsqueda) |
| `POST` | `/admin/user` | Crear usuario |
| `GET` | `/admin/user/{id}` | Detalle del usuario |
| `PUT` | `/admin/user/{id}` | Actualizar usuario |
| `DELETE` | `/admin/user/{id}` | Eliminar usuario (borrado lógico, requiere confirmación de contraseña) |
| `POST` | `/admin/user/batch/destroy` | Eliminar usuarios en lote (requiere confirmación de contraseña) |
| `POST` | `/admin/user/batch/status` | Habilitar/deshabilitar usuarios en lote |
| `GET` | `/admin/role` | Lista de roles |
| `POST` | `/admin/role` | Crear rol |
| `PUT` | `/admin/role/{id}` | Actualizar rol |
| `DELETE` | `/admin/role/{id}` | Eliminar rol (requiere confirmación de contraseña) |
| `GET` | `/admin/permission` | Árbol de permisos |
| `POST` | `/admin/permission` | Crear permiso |
| `PUT` | `/admin/permission/{id}` | Actualizar permiso |
| `DELETE` | `/admin/permission/{id}` | Eliminar permiso (subpermisos en cascada, requiere confirmación de contraseña) |
| `GET` | `/admin/config` | Lista de configuración del sistema |
| `POST` | `/admin/config` | Crear elemento de configuración |
| `PUT` | `/admin/config/{id}` | Actualizar elemento de configuración |
| `DELETE` | `/admin/config/{id}` | Eliminar elemento de configuración (requiere confirmación de contraseña) |
| `GET` | `/admin/log` | Registros de operaciones (paginación + filtros) |
| `PUT` | `/admin/profile` | Actualizar información personal |
| `PUT` | `/admin/profile/password` | Cambiar contraseña |
| `POST` | `/admin/profile/logout` | Cerrar sesión (lista negra JWT) |
| `POST` | `/admin/export/excel` | Exportar Excel |
| `POST` | `/admin/export/pdf` | Exportar PDF |
| `POST` | `/admin/import/users` | Importar usuarios por Excel |
| `POST` | `/admin/upload` | Subida de archivos (imágenes/documentos, máximo 10 MB) |

## Notas sobre el frontend

### Panel de administración Flutter (estilo PC)

- **Diseño**: barra lateral (plegable 64px/240px) + barra superior + área de contenido, tres puntos de ruptura responsive (teléfono/tableta/escritorio)
- **Páginas**: inicio de sesión, panel, gestión de usuarios, roles y permisos, configuración del sistema, registros de operaciones, centro personal
- **Gestión de estado**: GetX (`ApiService` singleton + persistencia de Token con `AuthService`)
- **Panel**: tarjetas de estadísticas, gráfico de líneas de tendencia (fl_chart), gráfico circular, registros de operaciones recientes
- **Exportación**: Excel/PDF, el PDF incluye información de copyright no eliminable
- **Operaciones masivas**: borrado masivo con selección múltiple, habilitar/deshabilitar en lote
- **Tema**: doble tema Material 3 claro/oscuro

### Móvil HarmonyOS

- **Páginas**: inicio de sesión, panel, lista/detalle de usuarios, centro personal
- **Autenticación**: JWT Bearer + refresco transparente automático del Token ante 401; si el refresco falla, redirección automática a la página de inicio de sesión
- **Almacenamiento**: el Token se gestiona mediante AppStorage

## Estándares de desarrollo

- Las funciones/clases globales se referencian sin `\` inicial; se importan uniformemente con `use`
- Todos los archivos PHP deben incluir la declaración de copyright al inicio
- Todos los archivos de configuración deben incluir comentarios explicativos en chino
- Las claves primarias de base de datos deben generarse con snowflake en la capa de aplicación; prohibido el autoincremento
- Todos los ID de parámetros y respuestas de la capa API deben cifrarse/descifrarse con hashids
- El middleware AdminPermission usa caché Redis para los permisos del usuario (TTL=60s), eliminando el cuello de botella de consultas N+1

## Despliegue

### Docker Compose (recomendado)

La raíz del proyecto incluye `docker-compose.yml`, con orquestación de 5 servicios:

| Servicio | Imagen | Puerto |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | construido con el `Dockerfile` local | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

La imagen de PHP se construye con el `Dockerfile`, imagen base `php:8.3-cli`, con OPcache habilitado.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

Pipeline de integración continua de GitHub Actions: `.github/workflows/ci.yml`

- Comprobación de sintaxis PHP (`php -l`)
- Pruebas unitarias PHPUnit
- Análisis estático Flutter (`flutter analyze`)

### Copia de seguridad de base de datos

Directorio `database/backup/`:

- `backup.sh` — copia de seguridad mysqldump + gzip, limpieza automática de copias antiguas de hace más de 30 días
- `restore.sh` — restauración interactiva, muestra las copias disponibles para elegir

### Configuración de seguridad de Nginx

Para despliegues en producción consulta `docs/nginx-security.conf` para el refuerzo de seguridad del proxy inverso.

## El código abierto no es fácil, tu apoyo es bienvenido

| WeChat | Alipay |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
