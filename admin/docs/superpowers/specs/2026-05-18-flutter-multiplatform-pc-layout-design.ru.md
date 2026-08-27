# Мультиплатформенный макет Flutter в стиле PC — дизайн-спецификация
<!-- lang-nav -->

Languages: **中文** · [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) · [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) · [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) · [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) · [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) · [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) · [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) · [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) · [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) · [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) · [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) · [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)


Дата: 2026-05-18

## Цель

Включить поддержку настольных платформ macOS и Windows, обеспечить на всех платформах — iOS (iPhone + iPad), macOS, Windows, Linux — использование PC-стиля макета админ-панели (боковая панель + верхняя панель + область контента), а на мобильных устройствах — адаптацию с помощью выдвижного меню.

## Стратегия по платформам

| Платформа | Статус | Описание |
|------|------|------|
| Linux | Уже включена | Действий не требуется |
| macOS | Требуется включить | `flutter config --enable-macos-desktop` |
| Windows | Требуется включить | `flutter config --enable-windows-desktop` |
| iOS | Уже есть | Покрывает и iPhone (мобильная раскладка), и iPad (настольная раскладка) |
| Web | Уже есть | Действий не требуется |

У iPad нет отдельной цели платформы — настольная раскладка достигается попаданием в диапазон TABLET через адаптивные контрольные точки.

## Адаптивные контрольные точки

| Точка | Диапазон | Режим раскладки |
|------|------|----------|
| PHONE | 0 - 767 | Выдвижное меню (AppBar + Drawer) |
| TABLET | 768 - 1199 | Сворачиваемая боковая панель (по умолчанию свёрнута, 64px) |
| DESKTOP | 1200 - 2460 | Боковая панель (по умолчанию развёрнута, 240px) |

Минимальная ширина iPad в портретной ориентации — 768px, попадает в TABLET, получает раскладку с боковой панелью.
Ширина iPhone всегда меньше 768px, попадает в PHONE, получает выдвижное меню.

## Изменения файлов

### 1. main.dart — конфигурация контрольных точек

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- Остальной код не меняется

### 2. admin_layout.dart — адаптивное переключение навигации

- `_isPhone`: попадание в точку PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, NavigationDrawer внутри Drawer переиспользует те же пункты меню, что и настольная боковая панель
- `_buildDesktopLayout()`: существующая Row-раскладка (боковая панель + верхняя панель + область контента)
- В TABLET боковая панель по умолчанию свёрнута, в DESKTOP — развёрнута

### 3. app_theme.dart — дополнение тёмной темы

- Стили компонентов выносятся в приватные константы `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Светлая и тёмная темы переиспользуют один и тот же набор стилей компонентов
- Тёмная тема дополняется на базе Material 3 + того же seed + яркости dark
