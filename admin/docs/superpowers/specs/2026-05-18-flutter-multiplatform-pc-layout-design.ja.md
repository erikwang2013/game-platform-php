# Flutter 多平台 PC スタイルレイアウト — 設計仕様
<!-- lang-nav -->

Languages: **中文** · [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) · [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) · [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) · [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) · [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) · [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) · [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) · [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) · [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) · [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) · [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) · [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)


日付: 2026-05-18

## 目標

macOS・Windows デスクトッププラットフォームを有効化し、iOS (iPhone + iPad)、macOS、Windows、Linux の全プラットフォームで PC 管理バックエンドスタイルのレイアウト（サイドバー + トップバー + コンテンツ領域）を確保し、モバイル端末ではドロワーメニューで対応する。

## プラットフォーム戦略

| プラットフォーム | 状態 | 説明 |
|------|------|------|
| Linux | 有効済み | 操作不要 |
| macOS | 有効化が必要 | `flutter config --enable-macos-desktop` |
| Windows | 有効化が必要 | `flutter config --enable-windows-desktop` |
| iOS | 既存 | iPhone (モバイルレイアウト) と iPad (デスクトップレイアウト) を同時にカバー |
| Web | 既存 | 操作不要 |

iPad は独立したプラットフォームターゲットを持たず、レスポンシブブレークポイントで TABLET にヒットさせてデスクトップレイアウトを実現する。

## レスポンシブブレークポイント

| ブレークポイント | 範囲 | レイアウトモード |
|------|------|----------|
| PHONE | 0 - 767 | ドロワーメニュー (AppBar + Drawer) |
| TABLET | 768 - 1199 | 折りたたみ可能なサイドバー (デフォルト折りたたみ 64px) |
| DESKTOP | 1200 - 2460 | サイドバー (デフォルト展開 240px) |

iPad 縦画面の最小幅は 768px で TABLET にヒットし、サイドバーレイアウトになる。
iPhone の幅はすべて 768px 未満で PHONE にヒットし、ドロワーメニューになる。

## ファイル変更

### 1. main.dart — ブレークポイント設定

- `PHONE`: 0-767、`TABLET`: 768-1199、`DESKTOP`: 1200-2460
- その他のコードは変更なし

### 2. admin_layout.dart — レスポンシブナビゲーション切り替え

- `_isPhone`: PHONE ブレークポイントにヒット
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer。Drawer 内の NavigationDrawer はデスクトップサイドバーと同じメニュー項目を再利用
- `_buildDesktopLayout()`: 既存の Row レイアウト（サイドバー + トップバー + コンテンツ領域）
- TABLET ではサイドバーがデフォルトで折りたたまれ、DESKTOP ではデフォルトで展開される

### 3. app_theme.dart — ダークテーマ補完

- コンポーネントスタイルをプライベート定数 `_dataTableTheme`、`_cardTheme`、`_inputDecorationTheme`、`_dividerTheme` に抽出
- ライトテーマとダークテーマで同一のコンポーネントスタイルを再利用
- ダークテーマは Material 3 + 同じ seed + dark 明度を使用して補完
