# Desain Tata Letak Gaya PC Multiplatform Flutter — Spesifikasi Desain
<!-- lang-nav -->

Languages: [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) · [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) · [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) · [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) · [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) · [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) · [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) · [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) · [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) · [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) · [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) · **Bahasa Indonesia** · [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)


Tanggal: 2026-05-18

## Tujuan

Mengaktifkan platform desktop macOS dan Windows, memastikan semua platform iOS (iPhone + iPad), macOS, Windows, Linux menggunakan tata letak gaya backend administrasi PC (sidebar + top bar + area konten), sedangkan perangkat seluler menggunakan adaptasi menu drawer.

## Strategi Platform

| Platform | Status | Keterangan |
|------|------|------|
| Linux | Sudah diaktifkan | Tidak perlu tindakan |
| macOS | Perlu diaktifkan | `flutter config --enable-macos-desktop` |
| Windows | Perlu diaktifkan | `flutter config --enable-windows-desktop` |
| iOS | Sudah ada | Mencakup iPhone (tata letak ponsel) dan iPad (tata letak desktop) |
| Web | Sudah ada | Tidak perlu tindakan |

iPad tidak memiliki target platform independen, dicapai dengan titik henti responsif yang masuk kategori TABLET untuk tata letak desktop.

## Titik Henti Responsif

| Titik henti | Rentang | Mode tata letak |
|------|------|----------|
| PHONE | 0 - 767 | Menu drawer (AppBar + Drawer) |
| TABLET | 768 - 1199 | Sidebar dapat dilipat (default terlipat 64px) |
| DESKTOP | 1200 - 2460 | Sidebar (default terbuka 240px) |

Lebar minimum iPad portrait adalah 768px, masuk kategori TABLET, mendapatkan tata letak sidebar.
Lebar iPhone semuanya kurang dari 768px, masuk kategori PHONE, mendapatkan menu drawer.

## Perubahan File

### 1. main.dart — Konfigurasi titik henti

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- Kode lainnya tidak berubah

### 2. admin_layout.dart — Peralihan navigasi responsif

- `_isPhone`: memenuhi titik henti PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, NavigationDrawer di dalam Drawer menggunakan item menu yang sama dengan sidebar desktop
- `_buildDesktopLayout()`: tata letak Row yang ada (sidebar + top bar + area konten)
- Sidebar default terlipat pada TABLET, default terbuka pada DESKTOP

### 3. app_theme.dart — Pelengkapan tema gelap

- Mengekstrak gaya komponen menjadi konstanta privat `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Tema terang dan tema gelap menggunakan kumpulan gaya komponen yang sama
- Tema gelap dilengkapi dengan Material 3 + seed yang sama + kecerahan dark
