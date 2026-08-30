# game_platform — C端用户平台（Flutter Web）
<!-- lang-nav -->

Languages: **中文** · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

C端用户平台的 Web 前端，基于 Flutter 3.x，面向用户提供游戏聚合平台的完整体验：注册登录、游戏大厅、钱包、充值、提现、兑换、排行榜、优惠券、通知、聊天、好友与客服工单。

## 功能清单

| 模块 | 说明 |
|------|------|
| 登录注册 | 用户名密码 / OAuth / 2FA |
| 游戏大厅 | 游戏列表/分类/搜索 |
| 钱包 | 平台币/游戏币余额与流水 |
| 充值 | 选择支付方式，跳转网关支付 |
| 提现 | 申请提现、审核状态跟踪 |
| 兑换 | 平台币 ⇄ 游戏币实时兑换 |
| 排行榜 | 日/周/月/总榜 |
| 优惠券 | 领取与使用 |
| 通知 | 站内信（充值/提现/优惠券等） |
| 聊天 | WebSocket 实时私信 |
| 好友 | 好友系统 |
| 工单 | 客服工单创建与回复 |
| 个人资料 | 资料编辑/安全设置 |

## 环境要求

- Flutter SDK 3.x

## 安装运行

```bash
cd apps/flutter/platform

# 安装依赖
flutter pub get

# 开发运行（Chrome）
flutter run -d chrome

# 指定后端地址（默认 http://localhost:8788）
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8788

# 构建 Web 生产产物（输出到 build/web/）
flutter build web
```

## 使用说明

1. 先启动后端服务：`cd service && php start.php start -d`（默认端口 8788）
2. 注册账号并登录（支持用户名密码、OAuth、2FA）
3. 充值后即可用平台币玩游戏、兑换游戏币；游戏币可转回钱包提现
4. 管理后台见 `admin/` 目录（含 Flutter Web 前端 `admin/apps/flutter/`）
