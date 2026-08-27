# Flutter-Multiplattform-PC-Layout — Designspezifikation
<!-- lang-nav -->

Languages: **中文** · [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) · [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) · [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) · [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) · [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) · [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) · [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) · [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) · [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) · [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) · [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) · [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)


Datum: 2026-05-18

## Ziel

macOS- und Windows-Desktop-Plattformen aktivieren und sicherstellen, dass iOS (iPhone + iPad), macOS, Windows und Linux auf allen Plattformen das PC-Verwaltungslayout (Sidebar + Topbar + Inhaltsbereich) verwenden, während die Mobile-Endgeräte über ein Drawer-Menü angepasst werden.

## Plattformstrategie

| Plattform | Status | Beschreibung |
|------|------|------|
| Linux | Aktiviert | Kein Handlungsbedarf |
| macOS | Zu aktivieren | `flutter config --enable-macos-desktop` |
| Windows | Zu aktivieren | `flutter config --enable-windows-desktop` |
| iOS | Vorhanden | Abdeckt sowohl iPhone (Mobile-Layout) als auch iPad (Desktop-Layout) |
| Web | Vorhanden | Kein Handlungsbedarf |

Das iPad hat kein eigenes Plattformziel; es erreicht das Desktop-Layout über den responsiven TABLET-Breakpoint.

## Responsive Breakpoints

| Breakpoint | Bereich | Layoutmodus |
|------|------|----------|
| PHONE | 0 - 767 | Drawer-Menü (AppBar + Drawer) |
| TABLET | 768 - 1199 | Einklappbare Sidebar (standardmäßig 64px eingeklappt) |
| DESKTOP | 1200 - 2460 | Sidebar (standardmäßig 240px aufgeklappt) |

Die minimale iPad-Hochformatbreite beträgt 768px und trifft den TABLET-Breakpoint, wodurch das Sidebar-Layout angewendet wird.
Die iPhone-Breite liegt immer unter 768px und trifft den PHONE-Breakpoint, wodurch das Drawer-Menü angewendet wird.

## Dateiänderungen

### 1. main.dart — Breakpoint-Konfiguration

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- Der übrige Code bleibt unverändert

### 2. admin_layout.dart — Responsive Navigationsumschaltung

- `_isPhone`: trifft den PHONE-Breakpoint
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer; der NavigationDrawer im Drawer verwendet dieselben Menüpunkte wie die Desktop-Sidebar
- `_buildDesktopLayout()`: bestehendes Row-Layout (Sidebar + Topbar + Inhaltsbereich)
- Unter TABLET ist die Sidebar standardmäßig eingeklappt, unter DESKTOP standardmäßig aufgeklappt

### 3. app_theme.dart — Vervollständigung des dunklen Themes

- Komponentenstile als private Konstanten `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme` extrahieren
- Helles und dunkles Theme verwenden dieselben Komponentenstile
- Dunkles Theme mit Material 3 + gleichem seed + dunkler Helligkeit ergänzen
