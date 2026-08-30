# admin_app — Frontend web del panel de administración (Flutter)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · **Español** · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

El frontend web del panel de administración, basado en Flutter 3.x, con el diseño clásico de un back-office para PC (barra lateral + barra superior + área de contenido). Cubre todas las páginas de gestión necesarias para operar la plataforma de juegos: panel, usuarios, roles y permisos, juegos, pagos, retiros, VIP, logros, anuncios, CDN, control de riesgos, verificación de identidad, registros de operaciones, etc.

## Lista de funciones

| Módulo | Descripción |
|------|------|
| Panel | Resumen de los datos de operación |
| Informes | Resumen de informes/diario/exportación CSV |

| Inicio de sesión | Login del administrador (con 2FA) |
| Gestión de usuarios | Búsqueda y gestión de usuarios |
| Usuarios de la plataforma | Detalles, estado y operaciones de saldo |
| Roles y permisos | Asignación de roles y permisos |
| Configuración del sistema | Configuración de parámetros de la plataforma |
| Gestión de juegos | Lista, publicación/parada y categorías de juegos |
| Gestión de pagos | Depósitos, métodos de pago y registros de callback |
| Gestión de retiros | Revisión y pago de retiros |
| Gestión VIP | Configuración de niveles y beneficios VIP |
| Gestión de logros | Definiciones de logros y progreso |
| Gestión de anuncios | Publicación y retirada de anuncios |
| Gestión CDN | Configuración de proveedores CDN y dominios |
| Control de riesgos | Reglas de riesgo y registros de bloqueo |
| Verificación de identidad | Revisión de datos de nombre real |
| Registro de operaciones | Auditoría de acciones del administrador |
| Perfil | Perfil del administrador y ajustes de seguridad |

## Requisitos

- Flutter SDK 3.x

## Instalación y ejecución

```bash
cd admin/apps/flutter

# Instalar dependencias
flutter pub get

# Ejecutar en desarrollo (Chrome)
flutter run -d chrome

# Especificar la dirección del backend (por defecto http://localhost:8787)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8787

# Build web de producción (salida en build/web/)
flutter build web
```

## Uso

1. Inicie primero el servicio backend del panel: `cd admin && php start.php start -d` (puerto por defecto 8787)
2. Inicie sesión con la cuenta de administrador creada por el asistente de instalación (se admite 2FA)
3. El frontend del usuario está en `apps/flutter/platform/` y comparte el mismo servicio backend (puerto por defecto 8788)
