# service/ — API del servicio de plataforma de usuarios (lado C)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · **Español** · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

El servicio API de la plataforma de usuarios (lado C) es un backend PHP de alto rendimiento basado en webman v2 (Workerman) que ofrece a los usuarios todas las capacidades de la plataforma de agregación de juegos: registro e inicio de sesión, billetera, depósito, retiro, cambio, juegos, clasificaciones, cupones, tickets de soporte, VIP, logros, funciones sociales y anuncios.

## Funcionalidades

| Módulo | Descripción |
|------|------|
| Usuarios | Registro/inicio de sesión (usuario+contraseña + OAuth de 7 plataformas + 2FA TOTP), perfil |
| Billetera | Billetera de monedas de plataforma (bloqueo optimista) + billetera de monedas de juego + historial de transacciones |
| Depósito | 13 pasarelas de pago (Stripe/PayPal/NowPayments/Coinbase, etc.) con verificación de firma de callbacks y acreditación automática |
| Retiro | Solicitud → revisión → pago, límites escalonados por KYC |
| Cambio | Cotización en tiempo real moneda de plataforma ⇄ moneda de juego, descuentos VIP y bonos de tipo de cambio |
| Juegos | Lista/categorías/búsqueda de juegos, historial de partidas, callbacks de liquidación de Provider |
| Clasificaciones | Diaria/semanal/mensual/total + push WebSocket en tiempo real |
| Cupones | Importe fijo + descuento porcentual, limitados por tiempo y cantidad |
| Tickets | Creación/respuesta de tickets de soporte por el usuario |
| VIP | 5 niveles de fidelidad, acumulación de experiencia, descuentos en cambios |
| Logros | 12 logros integrados, detección basada en eventos |
| Social | Sistema de amigos + mensajes privados WebSocket en tiempo real |
| Anuncios | Anuncios in-app + notificaciones/correo |

## Stack tecnológico

- PHP 8.3+ / webman v2 (workerman/webman)
- MySQL 8.0+ (prefijo de tabla `game_`, claves primarias BIGINT sin autoincremento)
- Redis (Sesión / Caché / Límite de peticiones)
- ClickHouse (análisis OLAP / cálculo de probabilidades)
- Elasticsearch (búsqueda de texto completo)
- Autenticación JWT + firma HMAC-SHA256 de Provider

## Estructura del proyecto

```
service/
├── app/
│   ├── api/v1/controller/  # Controladores API lado C (35)
│   ├── middleware/         # Middleware (Cors/SecurityFilter/RateLimit/ApiVersion/UserAuth/ProviderAuth)
│   ├── model/              # Modelos de datos
│   ├── service/            # Servicios de negocio (VIP/clasificaciones/riesgo/notificaciones, etc.)
│   ├── event/              # Bus de eventos (EventBus Redis Pub/Sub)
│   ├── provider/           # Capa de Provider de juegos
│   └── payment/            # Pasarelas de pago
├── common/                 # Servicios compartidos (implementados en el paquete erik/platform-common)
├── config/                 # Archivos de configuración
├── public/                 # Entrada web
├── tests/                  # Pruebas PHPUnit
├── start.php               # Entrada de arranque
└── composer.json
```

## Instalación en un clic

Recomendado: el asistente de instalación en un clic de la raíz del proyecto (ejecutar desde la raíz):

```bash
# 1. Iniciar el asistente de instalación
php -S 0.0.0.0:8888 -t install/

# 2. Abrir http://localhost:8888 en el navegador
#    Seguir el asistente: comprobación del entorno → configuración de BBDD → cuenta de administrador → instalación automática
```

O iniciar todo con Docker Compose (raíz del proyecto):

```bash
docker compose up -d
```

## Instalación manual

```bash
# 1. Instalar dependencias
cd service && composer install

# 2. Configurar variables de entorno
cp .env.example .env
# Editar .env: conexión a BBDD, claves JWT, etc.

# 3. Iniciar el servicio (puerto por defecto 8788)
php start.php start        # primer plano
php start.php start -d     # segundo plano (demonio)
```

## Uso

- Referencia de API: `docs/API.md` (referencia completa)
- Documentación en línea: http://localhost:8788/apidoc/ (documentación interactiva hg/apidoc)
- Comprobación de salud: `GET http://localhost:8788/health`
- Frontend lado C: `apps/flutter/platform/` (plataforma de usuario Flutter Web)
- Backend admin: `admin/` (backend admin y frontend `admin/apps/flutter/`)

## Pruebas

```bash
cd service
SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
```
