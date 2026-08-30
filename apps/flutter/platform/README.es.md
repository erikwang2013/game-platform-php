# game_platform — Plataforma de usuarios (Flutter Web)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · **Español** · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

El frontend web de la plataforma de usuarios (lado C), basado en Flutter 3.x, ofrece a los usuarios la experiencia completa de la plataforma de agregación de juegos: registro e inicio de sesión, lobby de juegos, billetera, depósito, retiro, cambio, clasificaciones, cupones, notificaciones, chat, amigos y tickets de soporte.

## Funcionalidades

| Módulo | Descripción |
|------|------|
| Inicio de sesión/registro | Usuario+contraseña / OAuth / 2FA |
| Lobby de juegos | Lista/categorías/búsqueda de juegos |
| Billetera | Saldos y transacciones de monedas de plataforma/juego |
| Depósito | Elegir método de pago, redirigir al pago de la pasarela |
| Retiro | Solicitar retiro, seguimiento del estado |
| Cambio | Cambio en tiempo real moneda de plataforma ⇄ moneda de juego |
| Clasificaciones | Diaria/semanal/mensual/total |
| Cupones | Obtener y usar |
| Notificaciones | Mensajes in-app (depósito/retiro/cupones, etc.) |
| Chat | Mensajes WebSocket en tiempo real |
| Amigos | Sistema de amigos |
| Tickets | Crear y responder tickets de soporte |
| Perfil | Edición de perfil/ajustes de seguridad |

## Requisitos

- Flutter SDK 3.x

## Instalación y ejecución

```bash
cd apps/flutter/platform

# Instalar dependencias
flutter pub get

# Ejecutar en desarrollo (Chrome)
flutter run -d chrome

# Especificar la dirección del backend (por defecto http://localhost:8788)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8788

# Compilar web para producción (salida en build/web/)
flutter build web
```

## Uso

1. Inicia primero el backend: `cd service && php start.php start -d` (puerto por defecto 8788)
2. Registra una cuenta e inicia sesión (se admiten usuario+contraseña, OAuth y 2FA)
3. Tras depositar, juega con monedas de plataforma y cámbialas por monedas de juego; las monedas de juego se pueden convertir de nuevo a la billetera para retirar
4. El backend admin está en el directorio `admin/` (incluido el frontend Flutter Web `admin/apps/flutter/`)
