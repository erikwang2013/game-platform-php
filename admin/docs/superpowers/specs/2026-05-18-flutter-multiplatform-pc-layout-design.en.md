# Flutter Multiplatform PC-Style Layout — Design Spec
<!-- lang-nav -->

Languages: [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) · **English** · [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) · [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) · [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) · [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) · [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) · [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) · [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) · [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) · [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) · [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) · [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)


Date: 2026-05-18

## Goal

Enable macOS and Windows desktop platforms, ensuring that iOS (iPhone + iPad), macOS, Windows, and Linux all use the PC admin-backend layout style (sidebar + top bar + content area), with drawer menu adaptation on phones.

## Platform Strategy

| Platform | Status | Description |
|------|------|------|
| Linux | Enabled | No action needed |
| macOS | Needs enabling | `flutter config --enable-macos-desktop` |
| Windows | Needs enabling | `flutter config --enable-windows-desktop` |
| iOS | Already exists | Covers both iPhone (phone layout) and iPad (desktop layout) |
| Web | Already exists | No action needed |

iPad has no dedicated platform target; it achieves the desktop layout by hitting the TABLET responsive breakpoint.

## Responsive Breakpoints

| Breakpoint | Range | Layout Mode |
|------|------|----------|
| PHONE | 0 - 767 | Drawer menu (AppBar + Drawer) |
| TABLET | 768 - 1199 | Collapsible sidebar (collapsed by default at 64px) |
| DESKTOP | 1200 - 2460 | Sidebar (expanded by default at 240px) |

iPad's minimum portrait width is 768px, hitting TABLET and getting the sidebar layout.
All iPhone widths are below 768px, hitting PHONE and getting the drawer menu.

## File Changes

### 1. main.dart — Breakpoint configuration

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- Rest of the code unchanged

### 2. admin_layout.dart — Responsive navigation switching

- `_isPhone`: hits the PHONE breakpoint
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer; the NavigationDrawer inside the Drawer reuses the same menu items as the desktop sidebar
- `_buildDesktopLayout()`: existing Row layout (sidebar + top bar + content area)
- Under TABLET the sidebar is collapsed by default; under DESKTOP it is expanded by default

### 3. app_theme.dart — Dark theme completion

- Extract component styles into private constants `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Light and dark themes reuse the same set of component styles
- Dark theme additionally uses Material 3 + the same seed + dark brightness
