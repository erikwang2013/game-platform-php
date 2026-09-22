# Documento de despliegue
<!-- lang-nav -->

Languages: [中文](DEPLOYMENT.md) · [English](DEPLOYMENT.en.md) · [한국어](DEPLOYMENT.ko.md) · [Русский](DEPLOYMENT.ru.md) · [Deutsch](DEPLOYMENT.de.md) · [Français](DEPLOYMENT.fr.md) · **Español** · [Português](DEPLOYMENT.pt.md) · [हिन्दी](DEPLOYMENT.hi.md) · [العربية](DEPLOYMENT.ar.md) · [বাংলা](DEPLOYMENT.bn.md) · [Bahasa Indonesia](DEPLOYMENT.id.md) · [日本語](DEPLOYMENT.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Requisitos del entorno

| Componente | Versión mínima | Configuración recomendada |
|------|---------|---------|
| SO | Linux (Ubuntu 20.04+ / Debian 11+ / CentOS 8+) | Ubuntu 22.04 LTS |
| PHP | 8.3+ | 8.3+ (CLI, OPcache habilitado) |
| Extensiones PHP | pdo, pdo_mysql, pcntl, redis, gd, mbstring, xml | Todas |
| MySQL | 8.0+ | 8.0+ con replicación maestro-esclavo |
| Redis | 6.0+ | 7.x en modo centinela |
| Elasticsearch | 7.x+ | 8.x nodo único |
| Nginx | 1.20+ | Proxy inverso + gzip + SSL |
| Composer | 2.x | Última versión estable |
| Flutter SDK | 3.x+ | Última versión estable (solo necesario para construir el frontend) |

---

## 2. Asistente de instalación con un clic (recomendado para despliegues nuevos)

```bash
# 1. Clonar el proyecto
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Iniciar el asistente de instalación
php -S 0.0.0.0:8888 -t install/

# 3. Abrir http://<IP del servidor>:8888 en el navegador
#    Completar el asistente: comprobación del entorno → configuración de la base de datos → cuenta de administrador → instalación automática

# 4. Instalar las dependencias
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. Iniciar los servicios (puertos por defecto admin 8789 / service 8792, modificables con APP_PORT en el .env correspondiente)
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. Limpieza de seguridad
rm -rf install/

# 7. Acceder al panel de administración: http://<IP del servidor>:8789 (puerto por defecto)
```

Operaciones realizadas por el asistente de instalación:
- Comprobación del entorno PHP (versión, extensiones, permisos de directorios)
- Ejecución del SQL combinado (`install/install.sql`), crea 78 tablas e importa los datos semilla
- Creación de la cuenta de superadministrador (cifrado bcrypt, asociada al rol super_admin)
- Generación automática de las claves JWT/Encryption/Hashids
- Escritura de `admin/.env` y `service/.env`
- Generación de `install/install.lock` para impedir la reinstalación

---

## 3. Despliegue con Docker Compose

### 3.1 Arranque con un clic

```bash
# 1. Clonar el proyecto
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Configurar el entorno con el asistente de instalación en un clic (o configurar manualmente los archivos .env)
#    Los parámetros de Docker como los puertos están en el .env de la raíz del proyecto (plantilla .env.example): cp .env.example .env
php -S 0.0.0.0:8888 -t install/
# Manual: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. Construir e iniciar todos los servicios
docker-compose up -d

# 4. Ver el estado
docker-compose ps

# 5. Ver los registros
docker-compose logs -f
```

### 3.2 Lista de servicios

| Servicio | Nombre del contenedor | Puerto | Descripción |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | Proxy inverso + archivos estáticos |
| admin | game-platform-admin | 8789 | API del panel de administración |
| service | game-platform-service | 8792 | API de negocio del lado C |
| leaderboard-ws | game-platform-ws | 8790, 8791 | Clasificación WebSocket/Chat |
| mysql | game-platform-mysql | 3306 | Base de datos principal |
| redis | game-platform-redis | 6379 | Caché/limitación |
| elasticsearch | game-platform-es | 9200 | Búsqueda de texto completo |

> **Configuración de puertos**: Los puertos de la tabla son valores por defecto y todos pueden modificarse en el `.env` de la raíz del proyecto (plantilla `.env.example`; editar tras `cp .env.example .env`):
> `NGINX_HTTP_PORT`, `NGINX_HTTPS_PORT`, `ADMIN_PORT`, `SERVICE_PORT`, `LEADERBOARD_WS_PORT`, `CHAT_WS_PORT`, `MYSQL_PORT`, `REDIS_PORT`, `ES_PORT`.
> Los puertos upstream de `nginx.conf.template` se renderizan automáticamente mediante el envsubst de la imagen oficial; no hace falta editar manualmente la configuración de Nginx.
> En despliegues Docker, las direcciones públicas (`APP_URL` / `SITE_URL`) siguen automáticamente `ADMIN_PORT` / `SERVICE_PORT` de forma predeterminada (formato `http://localhost:puerto`); para un dominio personalizado o HTTPS, configure `APP_URL` / `SITE_URL` en el `.env` raíz (sobrescribe las mismas claves en `admin/.env` y `service/.env`). En despliegues bare-metal (manuales), actualice las direcciones usted mismo al cambiar los puertos.

### 3.3 Inicialización de la base de datos

```bash
# Los archivos de migración se ejecutan automáticamente en el primer arranque de MySQL
# O ejecutar manualmente:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 3.4 Persistencia de datos

Los volúmenes de datos se crean automáticamente; no requieren gestión manual:

| Volumen | Ruta | Contenido |
|----|------|------|
| mysql_data | /var/lib/mysql | Archivos de la base de datos |
| redis_data | /data | Persistencia de Redis |
| es_data | /usr/share/elasticsearch/data | Índices ES |

Copias de seguridad:
```bash
# Copia de seguridad de MySQL
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game-platform | gzip > backup_$(date +%Y%m%d).sql.gz

# Restauración
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform
```

---

## 4. Despliegue manual

### 4.1 Configuración del entorno PHP

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# Activar OPcache (obligatorio en producción)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 4.2 Instalación de dependencias

```bash
cd /opt/game-platform

# Panel de administración
cd admin
cp .env.example .env
# Editar .env: conexión a la base de datos, JWT_SECRET, HASHIDS_SALT, etc.
composer install --no-dev --optimize-autoloader

# Negocio del lado C
cd ../service
cp .env.example .env
# Editar .env (atención: SNOWFLAKE_WORKER_ID=2)
composer install --no-dev --optimize-autoloader
```

### 4.3 Configuración de .env

**Configuración clave de admin/.env:**
```ini
APP_ENV=production
APP_DEBUG=false
APP_PORT=8789  # puerto de escucha HTTP de webman (debe coincidir con APP_URL)
APP_URL=http://localhost:8789  # dirección de acceso externa (baseUrl de la documentación de API, etc.)

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=game-platform
DB_USERNAME=game-platform
DB_PASSWORD=<强密码>

JWT_SECRET=<64位随机字符串>
JWT_TTL=7200

HASHIDS_SALT=<随机盐值>
HASHIDS_MIN_LENGTH=16

SNOWFLAKE_DATACENTER_ID=1
SNOWFLAKE_WORKER_ID=1

ENCRYPTION_KEY=<32字节随机密钥>
ENCRYPTABLE_KEY=<随机AES密钥>

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=<Redis密码>

SCOUT_HOSTS=127.0.0.1:9200
```

**Configuración clave de service/.env:**
```ini
# Misma configuración de base de datos, Redis y ES que admin
APP_PORT=8792  # puerto de escucha HTTP de webman
LEADERBOARD_WS_PORT=8790  # WebSocket de clasificación (debe coincidir con la dirección de conexión del frontend)
CHAT_WS_PORT=8791  # WebSocket de chat
SNOWFLAKE_WORKER_ID=2  # debe ser diferente de admin

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<obtenido de Google Cloud Console>
OAUTH_GOOGLE_CLIENT_SECRET=<clave secreta>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# Webhook de pago
STRIPE_SECRET_KEY=<clave secreta de Stripe>
STRIPE_WEBHOOK_SECRET=<obtenido del Stripe Dashboard>
PAYPAL_WEBHOOK_ID=<obtenido de PayPal Developer>
PAYPAL_VERIFY_URL=<dirección de verificación de firma del Webhook de PayPal>
PAYPAL_CLIENT_ID=<PayPal Client ID>
PAYPAL_CLIENT_SECRET=<PayPal Client Secret>
PAYPAL_MODE=sandbox  # sandbox / live
NOWPAYMENTS_API_KEY=<clave API de NOWPayments>
NOWPAYMENTS_IPN_SECRET=<clave de firma IPN>
NOWPAYMENTS_API_URL=https://api.nowpayments.io  # dirección predeterminada
COINBASE_COMMERCE_API_KEY=<clave API de Coinbase Commerce>
COINBASE_COMMERCE_WEBHOOK_SECRET=<clave de Webhook de Coinbase Commerce>
SKRILL_API_URL=https://pay.skrill.com
SKRILL_API_KEY=<clave API de Skrill>
SKRILL_MERCHANT_ID=<ID de comerciante de Skrill>
SKRILL_SECRET_WORD=<clave de verificación de firma de callback md5sig>
NETELLER_API_URL=https://api.neteller.com
NETELLER_CLIENT_ID=<Neteller Client ID>
NETELLER_CLIENT_SECRET=<Neteller Client Secret>
NETELLER_SECRET=<clave de verificación de firma de callback>
PAYSAFECARD_API_URL=https://api.paysafecard.com
PAYSAFECARD_API_KEY=<clave API de Paysafecard>
PAYSAFECARD_SECRET=<clave de verificación de firma de callback X-Signature HMAC-SHA256>
PAYTM_MID=<ID de comerciante de Paytm>
PAYTM_KEY=<clave de Paytm>
PAYTM_API_URL=https://securegw.paytm.in
PAYTM_WEBSITE=DEFAULT  # producción DEFAULT / staging WEBSTAGING
MERCADOPAGO_CLIENT_ID=<Mercado Pago Client ID>
MERCADOPAGO_CLIENT_SECRET=<Mercado Pago Client Secret>
MERCADOPAGO_WEBHOOK_SECRET=<clave de verificación de firma del Webhook X-Signature>
MERCADOPAGO_API_URL=https://api.mercadopago.com
ASTROPAY_LOGIN=<nombre de inicio de sesión de AstroPay>
ASTROPAY_API_KEY=<clave API de AstroPay>
ASTROPAY_SECRET=<clave de verificación de firma de callback MD5>
ASTROPAY_API_URL=https://api.astropaycard.com
PAYPAY_CLIENT_ID=<PayPay Client ID>
PAYPAY_CLIENT_SECRET=<PayPay Client Secret>
PAYPAY_SIGNING_KEY=<clave de verificación de firma del Webhook PayPay-Signature>
PAYPAY_API_URL=https://api.paypay.ne.jp
KAKAOPAY_ADMIN_KEY=<KakaoPay Admin Key>
KAKAOPAY_CID=<CID de comerciante de KakaoPay>
KAKAOPAY_APPROVAL_URL=<URL de redirección de aprobación tras el pago>
KAKAOPAY_API_URL=https://kapi.kakao.com
PAYMONGO_API_KEY=<clave API de PayMongo>
PAYMONGO_WEBHOOK_SECRET=<clave de verificación de firma del Webhook Paymongo-Signature>
PAYMONGO_API_URL=https://api.paymongo.com/v1
# M-Pesa / Paystack / Toss
MPESA_CONSUMER_KEY=<M-Pesa Consumer Key>
MPESA_CONSUMER_SECRET=<M-Pesa Consumer Secret>
MPESA_PASSKEY=<M-Pesa STK Push Passkey>
MPESA_SHORTCODE=<código corto M-Pesa>
MPESA_API_URL=https://api.safaricom.co.ke
PAYSTACK_SECRET_KEY=<Paystack Secret Key>
PAYSTACK_API_URL=https://api.paystack.co
TOSS_SECRET_KEY=<Toss Secret Key>
TOSS_API_URL=https://api.tosspayments.com
SITE_URL=https://your-domain.com  # URL del sitio para callbacks/redirecciones de pago
```

### 4.4 Arranque de los servicios

```bash
# Panel de administración (puerto por defecto 8789, modificable vía APP_PORT en admin/.env)
cd /opt/game-platform/admin
php start.php start -d

# Negocio del lado C (puerto por defecto 8792, modificable vía APP_PORT en service/.env)
cd /opt/game-platform/service
php start.php start -d

# Verificación
curl http://localhost:8789/health
curl http://localhost:8792/health
```

### 4.5 Gestión de procesos (Systemd)

Crear `/etc/systemd/system/game-platform-admin.service`:

```ini
[Unit]
Description=Game Platform Admin
After=network.target mysql.service redis.service

[Service]
Type=forking
User=www-data
Group=www-data
WorkingDirectory=/opt/game-platform/admin
ExecStart=/usr/bin/php start.php start -d
ExecStop=/usr/bin/php start.php stop
ExecReload=/usr/bin/php start.php reload
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Crear igualmente `game-platform-service.service` (cambiando WorkingDirectory a `/opt/game-platform/service`).

```bash
systemctl daemon-reload
systemctl enable --now game-platform-admin game-platform-service
```

---

## 5. Proxy inverso Nginx

### 5.1 Archivo de configuración

Crear `/etc/nginx/sites-available/game-platform`:

```nginx
# Puertos con valores por defecto (admin 8789 / service 8792 / ws 8790); si ha modificado el .env, ajústelos también
server {
    listen 80;
    server_name your-domain.com;

    # nginx 自身发出的 301（如目录补斜杠 /admin-panel → /admin-panel/）改用相对
    # Location，客户端按当前 host:port 解析；默认绝对跳转会退回 listen 端口，
    # 非 80 端口部署（如 8080）时会跳错端口。
    absolute_redirect off;

    # 管理后台 API
    location /admin/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # C端 API
    location /api/ {
        proxy_pass http://127.0.0.1:8792;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Clasificación WebSocket (puerto por defecto 8790, coincide con LEADERBOARD_WS_PORT de service/.env)
    location /ws/ {
        proxy_pass http://127.0.0.1:8790;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # 健康检查
    location /health {
        proxy_pass http://127.0.0.1:8789;
    }

    # Prometheus 指标
    location /metrics {
        proxy_pass http://127.0.0.1:8789;
    }

    # ================================================================
    # 静态前端。两套前端定位不同：
    #   apps/*         = C 端玩家端（调 /api/ → service）
    #   admin/apps/*   = 管理台（调 /admin/ → admin）
    # 各产物需先构建；React/Angular 必须带子路径前缀构建，否则资源 404：
    #   apps/react            npm run build                （已含 --base=/app-react/）
    #   apps/angular          npm run build                （已含 --base-href=/app-angular/）
    #   admin/apps/react      npm run build                （已含 --base=/admin-react/）
    #   admin/apps/angular    npm run build                （已含 --base-href=/admin-angular/）
    #   admin/apps/flutter    flutter build web --base-href=/admin-flutter/
    #   apps/flutter/platform flutter build web            （挂在根路径）
    # try_files 末项是【内部重定向】，目标 index.html 不存在时会重新匹配同一 location
    # 形成重定向环，nginx 报 500 而非 404。规避方式按 location 类型二选一：
    #   root  型 → 末项追加 =404，把它降级为文件存在性判断；
    #   alias 型 → 追加 =404 会让兜底不再经 alias 解析，已构建的 SPA 深链接也会 404，
    #              所以保留原样，另加 location = 精确匹配兜底 URI（精确匹配优先，
    #              不会再回到前缀 location，环不成立）。
    # alias 的结尾斜杠必须与 location 的结尾斜杠一致（location /x 配 alias .../x，
    # location /x/ 配 alias .../x/）。错配时 /x../<路径> 会越级解析到上级目录，可读
    # 取 docroot 之外的任意文件，且 nginx -t 完全查不出来。
    # ================================================================

    # C 端主入口 — Flutter Web
    location / {
        root /opt/game-platform/apps/flutter/platform/build/web;
        try_files $uri $uri/ /index.html =404;
    }

    # C 端 React / Angular Web（URL 前缀与产物目录名不同，用 alias 直接指向产物）
    location /app-react/ {
        alias /opt/game-platform/apps/react/dist/;
        try_files $uri $uri/ /app-react/index.html;
    }
    location = /app-react/index.html {
        alias /opt/game-platform/apps/react/dist/index.html;
    }

    location /app-angular/ {
        alias /opt/game-platform/apps/angular/dist/game-client-angular/browser/;
        try_files $uri $uri/ /app-angular/index.html;
    }
    location = /app-angular/index.html {
        alias /opt/game-platform/apps/angular/dist/game-client-angular/browser/index.html;
    }

    # 管理台 — 通用投放位：把任一控制台产物拷进 admin/public 即可
    # 注意：location 不以 / 结尾时 alias 也【不能】以 / 结尾，否则 /admin-panel../.env
    # 会解析到上级目录（admin/.env）造成任意文件读取；nginx -t 查不出这类错配。
    location /admin-panel {
        alias /opt/game-platform/admin/public;
        try_files $uri $uri/ /admin-panel/index.html;
    }
    location = /admin-panel/index.html {
        alias /opt/game-platform/admin/public/index.html;
    }

    # 管理台 React / Angular / Flutter
    location /admin-react/ {
        alias /opt/game-platform/admin/apps/react/dist/;
        try_files $uri $uri/ /admin-react/index.html;
    }
    location = /admin-react/index.html {
        alias /opt/game-platform/admin/apps/react/dist/index.html;
    }

    location /admin-angular/ {
        alias /opt/game-platform/admin/apps/angular/dist/game-admin-angular/browser/;
        try_files $uri $uri/ /admin-angular/index.html;
    }
    location = /admin-angular/index.html {
        alias /opt/game-platform/admin/apps/angular/dist/game-admin-angular/browser/index.html;
    }

    location /admin-flutter/ {
        alias /opt/game-platform/admin/apps/flutter/build/web/;
        try_files $uri $uri/ /admin-flutter/index.html;
    }
    location = /admin-flutter/index.html {
        alias /opt/game-platform/admin/apps/flutter/build/web/index.html;
    }
}
```

> En despliegue manual usted coloca los artefactos compilados en esos directorios (cuatro árboles de cliente C: `apps/flutter/platform`, `apps/react`, `apps/angular`, `apps/harmonyos`; los frontends de consola se montan todos bajo `admin/apps/*` y el punto genérico `admin/public`).
> Para Docker, consulte los montajes de volúmenes nginx en `docker-compose.yml` y `nginx.conf.template` (las mismas rutas, raíz del contenedor `/var/www/...`). HarmonyOS se distribuye como `.hap` y no pasa por nginx.

Habilitar el sitio:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 5.2 Certificado SSL

```bash
# Obtener automáticamente un certificado Let's Encrypt con Certbot
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# Renovación automática (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 6. Tareas programadas (Crontab)

```bash
# Editar el crontab
crontab -e

# Instantánea de estadísticas diarias (todos los días a la 1:00)
0 1 * * * cd /opt/game-platform/admin && php start.php queue ComputeDailyStats

# Copia de seguridad de la base de datos (todos los días a las 2:00)
0 2 * * * cd /opt/game-platform/admin/database/backup && bash backup.sh

# Renovación automática del certificado SSL
0 3 * * * certbot renew --quiet && systemctl reload nginx

# Actualización de la caché de clasificación (cada hora)
0 * * * * cd /opt/game-platform/admin && php start.php queue RefreshLeaderboards
```

---

## 7. Monitorización

### 7.1 Métricas de Prometheus

El panel de administración expone el endpoint `/metrics`, con las siguientes métricas:

| Métrica | Descripción |
|------|------|
| openadmin_http_requests_total | Total de solicitudes |
| openadmin_active_users | Usuarios activos |
| openadmin_db_connection_status | Conexión a la base de datos (0/1) |
| openadmin_redis_connection_status | Conexión a Redis (0/1) |
| openadmin_memory_usage_bytes | Uso de memoria |

### 7.2 Verificaciones de salud

```bash
# Panel de administración
curl -f http://localhost:8789/health || echo "Admin DOWN"

# Negocio del lado C
curl -f http://localhost:8792/health || echo "Service DOWN"

# Configurable en el balanceador de carga o en el sistema de monitorización
```

### 7.3 Registros

```
admin/runtime/logs/
├── stdout.log          # 标准输出
└── webman-<date>.log   # Webman 日志

service/runtime/logs/
├── stdout.log
└── webman-<date>.log
```

---

## 8. Optimización del rendimiento

### 8.1 OPcache de PHP

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # 生产环境关闭文件检查
```

### 8.2 Optimización de MySQL

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # 设为物理内存的 50-70%
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # 性能优先
max_connections = 200
query_cache_type = 0               # MySQL 8.0 已移除
```

### 8.3 Número de procesos worker

```php
// config/process.php
'count' => cpu_count() * 2,  // 生产环境建议 2-4 倍 CPU 核心数
```

### 8.4 Estrategia de caché Redis

| Clave de caché | TTL | Descripción |
|--------|-----|------|
| dashboard:data | 300s | Datos del dashboard |
| i18n:translations | 3600s | Textos de traducción |
| leaderboard:{id} | 3600s | Clasificaciones |
| rate_limit:{ip}:{route} | 60s | Ventana de limitación |

---

## 9. Refuerzo de seguridad

### 9.1 Generación de claves

```bash
# Generar claves aleatorias
JWT_SECRET=$(openssl rand -hex 32)
HASHIDS_SALT=$(openssl rand -hex 16)
ENCRYPTION_KEY=$(openssl rand -hex 16)
ENCRYPTABLE_KEY=$(openssl rand -hex 16)
REDIS_PASSWORD=$(openssl rand -hex 16)
DB_PASSWORD=$(openssl rand -hex 16)

echo "JWT_SECRET=$JWT_SECRET"
echo "HASHIDS_SALT=$HASHIDS_SALT"
echo "ENCRYPTION_KEY=$ENCRYPTION_KEY"
echo "ENCRYPTABLE_KEY=$ENCRYPTABLE_KEY"
```

### 9.2 Cortafuegos

```bash
# Abrir solo los puertos necesarios
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# Los puertos internos no deben exponerse
# 8789 (admin), 8792 (service), 8790/8791 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# Los anteriores son los puertos por defecto; si ha modificado el .env de la raíz o los .env respectivos, prevalecen los valores reales
# Solo accesible vía 127.0.0.1
```

### 9.3 Permisos de archivos

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 10. Solución de problemas

### 10.1 El servicio no arranca

```bash
# Ejecutar en primer plano para ver los errores
cd /opt/game-platform/admin && php start.php start

# Comprobar el uso de puertos
ss -tlnp | grep -E '8789|8792'

# Comprobar los registros
tail -f runtime/logs/webman-$(date +%F).log
```

### 10.2 Fallo de conexión a la base de datos

```bash
# Probar la conexión
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# Comprobar la configuración de .env
grep DB_ admin/.env
```

### 10.3 Fallo de conexión a Redis

```bash
# Probar la conexión
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# Se espera PONG
```

### 10.4 Elasticsearch no disponible

```bash
# Probar la conexión
curl http://127.0.0.1:9200

# La búsqueda recurre automáticamente a consultas LIKE, el servicio no se interrumpe
```

### 10.5 Problemas de rendimiento

```bash
# Comprobar el número de procesos worker
php start.php status

# Ver el uso de memoria
free -h

# Comprobar las consultas lentas de la base de datos
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 11. Guía de actualización

```bash
# 1. Obtener el código más reciente
cd /opt/game-platform && git pull origin main

# 2. Actualizar las dependencias
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. Ejecutar las nuevas migraciones (si las hay)
mysql -u game-platform -p game-platform < install/新迁移文件.sql

# 4. Reinicio suave (sin interrumpir el servicio)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
