# Diseño de layout estilo PC multiplataforma Flutter — Especificación
<!-- lang-nav -->

Languages: [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) · [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) · [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) · [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) · [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) · [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) · **Español** · [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) · [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) · [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) · [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) · [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) · [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)


Fecha: 2026-05-18

## Objetivo

Habilitar las plataformas de escritorio macOS y Windows, garantizando que todas las plataformas iOS (iPhone + iPad), macOS, Windows y Linux usen el layout de estilo panel de administración PC (barra lateral + barra superior + área de contenido), y que la versión móvil use un menú de cajón adaptativo.

## Estrategia de plataformas

| Plataforma | Estado | Descripción |
|------|------|------|
| Linux | Habilitada | Sin acción necesaria |
| macOS | Requiere habilitación | `flutter config --enable-macos-desktop` |
| Windows | Requiere habilitación | `flutter config --enable-windows-desktop` |
| iOS | Ya existente | Cubre tanto iPhone (layout móvil) como iPad (layout de escritorio) |
| Web | Ya existente | Sin acción necesaria |

El iPad no tiene un objetivo de plataforma independiente; alcanza el layout de escritorio a través del punto de ruptura responsivo que activa el nivel TABLET.

## Puntos de ruptura responsivos

| Punto de ruptura | Rango | Modo de layout |
|------|------|----------|
| PHONE | 0 - 767 | Menú de cajón (AppBar + Drawer) |
| TABLET | 768 - 1199 | Barra lateral plegable (plegada por defecto 64px) |
| DESKTOP | 1200 - 2460 | Barra lateral (expandida por defecto 240px) |

El ancho mínimo del iPad en vertical es 768px, por lo que activa TABLET y obtiene el layout de barra lateral.
Todos los iPhone tienen un ancho inferior a 768px, por lo que activan PHONE y obtienen el menú de cajón.

## Cambios de archivos

### 1. main.dart — Configuración de puntos de ruptura

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- El resto del código no cambia

### 2. admin_layout.dart — Conmutación de navegación responsiva

- `_isPhone`: activa el punto de ruptura PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer; el NavigationDrawer dentro del Drawer reutiliza los mismos elementos de menú que la barra lateral de escritorio
- `_buildDesktopLayout()`: el layout Row existente (barra lateral + barra superior + área de contenido)
- En TABLET la barra lateral está plegada por defecto; en DESKTOP está expandida por defecto

### 3. app_theme.dart — Completar el tema oscuro

- Extraer los estilos de componentes como constantes privadas `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Los temas claro y oscuro reutilizan el mismo conjunto de estilos de componentes
- El tema oscuro usa Material 3 + la misma seed + brillo dark
