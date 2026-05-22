# Flutter 多平台 PC 风格布局 — 设计规格

日期: 2026-05-18

## 目标

启用 macOS、Windows 桌面平台，确保 iOS (iPhone + iPad)、macOS、Windows、Linux 所有平台使用 PC 管理后台风格布局（侧边栏 + 顶栏 + 内容区），手机端使用抽屉菜单适配。

## 平台策略

| 平台 | 状态 | 说明 |
|------|------|------|
| Linux | 已启用 | 无需操作 |
| macOS | 需启用 | `flutter config --enable-macos-desktop` |
| Windows | 需启用 | `flutter config --enable-windows-desktop` |
| iOS | 已存在 | 同时覆盖 iPhone (手机布局) 和 iPad (桌面布局) |
| Web | 已存在 | 无需操作 |

iPad 无独立平台目标，通过响应式断点命中 TABLET 档实现桌面布局。

## 响应式断点

| 断点 | 范围 | 布局模式 |
|------|------|----------|
| PHONE | 0 - 767 | 抽屉菜单 (AppBar + Drawer) |
| TABLET | 768 - 1199 | 可折叠侧边栏 (默认折叠 64px) |
| DESKTOP | 1200 - 2460 | 侧边栏 (默认展开 240px) |

iPad 竖屏最小宽度 768px，命中 TABLET，获侧边栏布局。
iPhone 宽度均小于 768px，命中 PHONE，获抽屉菜单。

## 文件变更

### 1. main.dart — 断点配置

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- 其余代码不变

### 2. admin_layout.dart — 响应式导航切换

- `_isPhone`: 命中 PHONE 断点
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer，Drawer 内 NavigationDrawer 与桌面侧边栏复用相同菜单项
- `_buildDesktopLayout()`: 现有 Row 布局（侧边栏 + 顶栏 + 内容区）
- TABLET 下侧边栏默认折叠，DESKTOP 下默认展开

### 3. app_theme.dart — 暗色主题补齐

- 提取组件样式为私有常量 `_dataTableTheme`、`_cardTheme`、`_inputDecorationTheme`、`_dividerTheme`
- 亮色和暗色主题复用同一套组件样式
- 暗色主题补充使用 Material 3 + 相同 seed + dark 亮度
