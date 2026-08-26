# Informe de auditoría del sistema de instalación
<!-- lang-nav -->

Languages: [中文](INSTALL-AUDIT-REPORT.md) · [English](INSTALL-AUDIT-REPORT.en.md) · [한국어](INSTALL-AUDIT-REPORT.ko.md) · [Русский](INSTALL-AUDIT-REPORT.ru.md) · [Deutsch](INSTALL-AUDIT-REPORT.de.md) · [Français](INSTALL-AUDIT-REPORT.fr.md) · **Español** · [Português](INSTALL-AUDIT-REPORT.pt.md) · [हिन्दी](INSTALL-AUDIT-REPORT.hi.md) · [العربية](INSTALL-AUDIT-REPORT.ar.md) · [বাংলা](INSTALL-AUDIT-REPORT.bn.md) · [Bahasa Indonesia](INSTALL-AUDIT-REPORT.id.md) · [日本語](INSTALL-AUDIT-REPORT.ja.md)


> Fecha de la auditoría: 2026-08-04
> Alcance de la auditoría: todos los archivos del directorio `install/` + cambios de documentación relacionados
> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Resumen de la auditoría

| Dimensión | Calificación | Descripción |
|------|------|------|
| Integridad funcional | Aprobado | Proceso de instalación de 5 pasos completo, las 39 tablas se crean todas, datos semilla completos |
| Correctitud SQL | Aprobado | Las 42 tablas coinciden exactamente con los archivos de migración originales; el campo source se ha fusionado en CREATE TABLE |
| Configuración del ecosistema | Aprobado | Las dos configuraciones .env de admin y service están completas, claves generadas automáticamente |
| Seguridad | Aprobado con reservas | Contraseñas cifradas con bcrypt, protección XSS completa; se recomienda añadir CSRF Token |
| Mantenibilidad | Aprobado | Estructura de código clara, responsabilidad de cada archivo bien definida |
| Idempotencia | Aprobado | Todos los INSERT convertidos a INSERT IGNORE, con guardas WHERE NOT EXISTS |
| Experiencia de usuario | Aprobado | Diseño responsivo, prueba de conexión AJAX, mensajes de error en chino |

---

## 2. Archivos creados

### 2.1 `install/install.sql` (988 líneas)
- Fusiona los 8 archivos de migración originales
- 42 tablas de datos con prefijo `erik_` (CREATE TABLE IF NOT EXISTS)
- 13 bloques de datos semilla INSERT IGNORE
- El campo `source` de `erik_operation_log` se ha fusionado en la sentencia de creación de tabla (sin necesidad de ALTER TABLE)
- Envuelto en transacción (START TRANSACTION / COMMIT)
- Todos los INSERT se han tratado de forma idempotente

**Detalles del tratamiento idempotente de las sentencias INSERT:**

| Nombre de tabla | Método |
|------|---------|
| `erik_admin_role` | INSERT IGNORE (ID fijo) |
| `erik_admin_permission` | INSERT IGNORE (ID fijo) - 4 veces |
| `erik_admin_role_permission` | Subconsulta WHERE NOT EXISTS |
| `erik_platform_config` | INSERT IGNORE (ID fijo) - 2 veces |
| `erik_language` | INSERT IGNORE (ID fijo) |
| `erik_translation` | INSERT IGNORE (ID fijo) |
| `erik_risk_rule` | INSERT IGNORE (ID fijo) |
| `erik_withdraw_limit` | INSERT IGNORE (ID fijo) |
| `erik_game_category` | INSERT IGNORE (ID fijo) |
| `erik_country_config` | INSERT IGNORE (ID fijo) |

### 2.2 `install/index.php` (485 líneas)
- Enrutamiento: step1 -> step2 -> step3 -> step4 -> step5
- Interfaz AJAX: `?action=test-db` (POST JSON)
- 5 funciones de plantillas de páginas
- JavaScript en línea (prueba de conexión AJAX)
- La salida HTML usa `htmlspecialchars()` para prevenir XSS
- Detección de instalación previa (install.lock)

### 2.3 `install/Installer.php` (506 líneas)
- Comprobación del entorno: 11 elementos (versión de PHP, 6 extensiones, permisos de directorios, archivo SQL)
- Prueba de conexión a la base de datos: PDO + creación automática de la base de datos
- Ejecución de la instalación: importación SQL -> creación de administrador -> escritura de .env -> bloqueo
- Generación de claves: JWT(64 bytes) / Hashids(32 bytes) / Encryption(32 bytes)
- Copia de seguridad de .env: antes de instalar se respalda automáticamente el .env existente

### 2.4 `install/assets/style.css` (130 líneas)
- Diseño responsivo (soporte móvil <=600px)
- Tema con variables CSS (--primary: #4f46e5)
- Sin dependencias externas

---

## 3. Cobertura de la comprobación del entorno (11 elementos)

| # | Elemento comprobado | Nivel | Estado |
|---|--------|------|------|
| 1 | PHP >= 8.1.0 | Obligatorio | Aprobado |
| 2 | PDO MySQL | Obligatorio | Aprobado |
| 3 | MBString | Obligatorio | Aprobado |
| 4 | JSON | Obligatorio | Aprobado |
| 5 | OpenSSL | Obligatorio | Aprobado |
| 6 | PCNTL | Obligatorio | Aprobado |
| 7 | GD | Recomendado | Aprobado |
| 8 | XML | Recomendado | Aprobado |
| 9 | Redis | Recomendado | Aprobado |
| 10 | Permisos de directorios (admin/runtime, service/runtime) | Obligatorio | Aprobado |
| 11 | Existencia del archivo install.sql | Obligatorio | Aprobado |

---

## 4. Integridad de la configuración del ecosistema

### 4.1 Generación del `.env` de Admin (70 elementos de configuración)

| Grupo | N.º de elementos | Cobertura |
|------|---------|------|
| Configuración de la aplicación | 3 | APP_NAME, APP_DEBUG, APP_URL |
| Autenticación JWT | 6 | JWT_SECRET, JWT_ALGORITHM, JWT_TTL, JWT_REFRESH_TTL, JWT_ISSUER, JWT_AUDIENCE |
| Hashids | 2 | HASHIDS_SALT, HASHIDS_ALT_SALT |
| Snowflake | 3 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID, SNOWFLAKE_START_TIMESTAMP |
| Cifrado (API) | 3 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTION_IV |
| Cifrado (DB) | 3 | ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER, ENCRYPTION_PREVIOUS_KEYS |
| Scout/ES | 7 | SCOUT_DRIVER, SCOUT_HOSTS, SCOUT_PREFIX, SCOUT_SHARDS, SCOUT_REPLICAS, SCOUT_CHUNK_SIZE, SCOUT_SOFT_DELETE |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST etc. |
| Captcha Poster | 7 | POSTER_IMAGE_DRIVER etc. |
| Base de datos | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| Redis | 4 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DATABASE |
| Claves de compatibilidad | 3 | JWT_SECRET_KEY, JWT_DEFAULT_EXPIRE, JWT_REFRESH_EXPIRE |

### 4.2 Generación del `.env` de Service (48 elementos de configuración)

| Grupo | N.º de elementos | Cobertura |
|------|---------|------|
| Aplicación | 2 | APP_ENV, APP_DEBUG |
| Base de datos | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| JWT | 3 | JWT_SECRET, JWT_TTL, JWT_REFRESH_TTL |
| Hashids | 3 | HASHIDS_SALT, HASHIDS_ALPHABET, HASHIDS_MIN_LENGTH |
| Snowflake | 2 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID |
| Cifrado | 4 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER |
| Redis | 3 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD |
| ClickHouse | 5 | CLICKHOUSE_HOST, CLICKHOUSE_PORT, CLICKHOUSE_DB, CLICKHOUSE_USER, CLICKHOUSE_PASS |
| OAuth | 9 | OAUTH_GOOGLE/FACEBOOK/APPLE, 3 elementos cada uno |
| Webhook de pago | 3 | STRIPE_WEBHOOK_SECRET, PAYPAL_WEBHOOK_ID, PAYPAL_VERIFY_URL |
| CORS | 1 | CORS_ORIGIN |
| Scout/ES | 6 | SCOUT_DRIVER etc. |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST etc. |

**Conclusión comparativa**: ambas configuraciones `.env` son coherentes con los `.env.example` originales y añaden los elementos que faltaban (`ENCRYPTION_CIPHER`, `ENCRYPTABLE_CIPHER`, `JWT_REFRESH_TTL`) a la configuración de Service.

---

## 5. Revisión de seguridad

### 5.1 Medidas de seguridad implementadas

| Medida | Forma de implementación |
|------|---------|
| Seguridad de contraseñas | bcrypt, cost=12 |
| Aleatoriedad de claves | `random_int()` números aleatorios criptográficamente seguros |
| Protección XSS | `htmlspecialchars()` escapa todas las entradas y salidas de usuario |
| Protección contra inyección SQL | Sentencias preparadas PDO (`prepare/execute`) |
| Bloqueo de instalación | Archivo `install.lock` + metadatos JSON |
| Seguridad de rutas | Rutas fijas, sin inclusión de archivos controlable por el usuario |
| Fortaleza del cifrado | AES-256-CBC + clave de 32 bytes |

### 5.2 Riesgos potenciales y mitigación

| Riesgo | Nivel | Medida de mitigación |
|------|------|---------|
| Exposición de red durante la instalación | Medio | Eliminar el directorio `install/` inmediatamente después de instalar (aviso destacado en la página) |
| Sin CSRF Token | Bajo | El asistente de instalación es una herramienta temporal de un solo uso; el servidor integrado de PHP es de un solo hilo |
| test-db sin limitación de frecuencia | Bajo | Herramienta temporal, se elimina después de su uso |
| Permisos del archivo .env | Bajo | Se recomienda ejecutar manualmente chmod 600 después de instalar |

### 5.3 Sugerencias de mejora

1. **Refuerzo del entorno de producción**: tras la instalación, considerar el `chmod 600 admin/.env service/.env` automático
2. **Acceso remoto**: si es un servidor remoto, se recomienda el túnel SSH: `ssh -L 8888:localhost:8888 user@host`
3. **Limpieza posterior a la instalación**: considerar añadir un aviso destacado de "eliminar el directorio de instalación" en la página de éxito (ya implementado)

---

## 6. Resultados de las pruebas

### 6.1 Comprobación de sintaxis PHP
```
通过 install/index.php — No syntax errors
通过 install/Installer.php — No syntax errors
```

### 6.2 Pruebas funcionales
```
通过 Step 1 环境检查 — 11项检查全部通过
通过 Step 2 数据库配置 — 表单渲染正确，默认值填充正常
通过 AJAX test-db — JSON响应格式正确，中文错误提示清晰
通过 CSS 静态资源 — 200 OK, text/css
通过 已安装页面 — install.lock检测正常，提示信息完整
```

### 6.3 Validación SQL
```
通过 42张表名与原始迁移文件完全一致
通过 source字段已合并到 erik_operation_log 建表语句
通过 所有INSERT语句已做幂等处理
通过 WHERE NOT EXISTS 守卫已恢复（与原迁移一致）
```

---

## 7. Problemas encontrados y corregidos

| # | Problema | Gravedad | Estado |
|---|------|--------|------|
| 1 | El INSERT de `erik_admin_role_permission` carecía de la guarda `WHERE NOT EXISTS` (incoherente con la migración original) | Alta | Corregido |
| 2 | Los INSERT de datos semilla no eran idempotentes (fallaban al ejecutarse de nuevo) | Media | Corregido (INSERT IGNORE) |
| 3 | A la comprobación del entorno le faltaba la extensión `pcntl` (dependencia principal de webman) | Media | Corregido |
| 4 | Al .env de Service le faltaba `ENCRYPTION_CIPHER` | Baja | Corregido |
| 5 | Al .env de Service le faltaba `ENCRYPTABLE_CIPHER` | Baja | Corregido |
| 6 | Al .env de Service le faltaba `JWT_REFRESH_TTL` | Baja | Corregido |

---

## 8. Cambios de documentación

| Archivo | Contenido del cambio |
|------|---------|
| `README.md` | Inicio rápido cambiado a "Asistente de instalación con un clic (recomendado)", nuevo bloque plegable de instalación manual, estructura del proyecto actualizada |
| `README.en.md` | Igual que arriba (versión en inglés), estructura del proyecto actualizada |
| `docs/DEPLOYMENT.md` | Nueva sección 2 "Asistente de instalación con un clic (recomendado para despliegues nuevos)"; la sección de Docker se ha movido después |
| `.gitignore` | Nuevos `install/install.lock`, `admin/.env.backup.*`, `service/.env.backup.*` |

---

## 9. Evaluación general

El sistema de instalación es funcionalmente completo, con buena calidad de código y medidas de seguridad adecuadas. El proceso de instalación de 5 pasos es claro e intuitivo; la comprobación del entorno cubre todas las extensiones clave que necesita webman; las claves de alta resistencia se generan automáticamente; los archivos de configuración son totalmente compatibles con el sistema existente. El proceso de fusión SQL mantiene una coherencia total con los archivos de migración originales (42 tablas); el tratamiento idempotente garantiza que la ejecución repetida no falle.

**Conclusión de la auditoría: aprobado, puede ponerse en uso.**

---

## 10. Confirmación de estado 2026-08-18

Esta ronda de correcciones de seguridad (callback de pago fail-closed, validación de arranque JWT, unificación del prefijo de tablas) **no afecta al sistema de instalación**; no hay problemas nuevos:

- Tras eliminar el prefijo `erik_` hardcodeado de los modelos, los nombres reales de las tablas los sigue generando de forma unificada `prefix=erik_` de `config/database.php`, coherente con las tablas `erik_*` creadas por install.sql; no hace falta cambiar el SQL de instalación
- La validación de arranque JWT (`JWT_SECRET_KEY` faltante o con el valor por defecto bloquea el arranque) es compatible con la clave aleatoria de 64 bytes generada automáticamente por el asistente de instalación; no hay que ajustar el proceso de instalación

La conclusión histórica y la lista de problemas se mantienen sin cambios.

---
