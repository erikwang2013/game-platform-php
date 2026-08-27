# Flutter 멀티 플랫폼 PC 스타일 레이아웃 — 설계 명세
<!-- lang-nav -->

Languages: [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) · [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) · **한국어** · [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) · [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) · [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) · [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) · [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) · [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) · [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) · [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) · [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) · [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)


날짜: 2026-05-18

## 목표

macOS, Windows 데스크톱 플랫폼을 활성화하고 iOS (iPhone + iPad), macOS, Windows, Linux 모든 플랫폼에서 PC 관리 백오피스 스타일 레이아웃(사이드바 + 상단 바 + 콘텐츠 영역)을 사용하도록 하며, 모바일에서는 드로어 메뉴로 대응합니다.

## 플랫폼 전략

| 플랫폼 | 상태 | 설명 |
|------|------|------|
| Linux | 활성화됨 | 조치 불필요 |
| macOS | 활성화 필요 | `flutter config --enable-macos-desktop` |
| Windows | 활성화 필요 | `flutter config --enable-windows-desktop` |
| iOS | 이미 존재 | iPhone (모바일 레이아웃)과 iPad (데스크톱 레이아웃) 모두 포함 |
| Web | 이미 존재 | 조치 불필요 |

iPad에는 별도의 플랫폼 타겟이 없으며, 반응형 브레이크포인트의 TABLET 구간을 통해 데스크톱 레이아웃을 구현합니다.

## 반응형 브레이크포인트

| 브레이크포인트 | 범위 | 레이아웃 모드 |
|------|------|----------|
| PHONE | 0 - 767 | 드로어 메뉴 (AppBar + Drawer) |
| TABLET | 768 - 1199 | 접이식 사이드바 (기본 접힘 64px) |
| DESKTOP | 1200 - 2460 | 사이드바 (기본 펼침 240px) |

iPad 세로 최소 너비 768px로 TABLET 구간에 해당하여 사이드바 레이아웃을 얻습니다.
iPhone 너비는 모두 768px 미만이므로 PHONE 구간에 해당하여 드로어 메뉴를 사용합니다.

## 파일 변경 사항

### 1. main.dart — 브레이크포인트 설정

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- 나머지 코드는 변경 없음

### 2. admin_layout.dart — 반응형 내비게이션 전환

- `_isPhone`: PHONE 브레이크포인트 해당 여부
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, Drawer 내 NavigationDrawer와 데스크톱 사이드바가 동일한 메뉴 항목을 재사용
- `_buildDesktopLayout()`: 기존 Row 레이아웃 (사이드바 + 상단 바 + 콘텐츠 영역)
- TABLET에서는 사이드바가 기본적으로 접히고, DESKTOP에서는 기본적으로 펼쳐짐

### 3. app_theme.dart — 다크 테마 보완

- 컴포넌트 스타일을 비공개 상수 `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`로 추출
- 라이트/다크 테마가 동일한 컴포넌트 스타일 세트를 재사용
- 다크 테마는 Material 3 + 동일 seed + dark 밝기를 사용
