# admin_app — 管理后台 Web 前端（Flutter）
<!-- lang-nav -->

Languages: **中文** · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

基于 Flutter 3.x 的管理后台 Web 前端，采用经典 PC 后台布局（侧边栏 + 顶栏 + 内容区），覆盖游戏平台运营所需的全部管理页面：仪表盘、用户、角色权限、游戏、支付、提现、VIP、成就、公告、CDN、风控、实名认证、操作日志等。

## 功能清单

| 模块 | 说明 |
|------|------|
| 仪表盘 | 平台运营数据总览 |
| 报表 | 数据报表汇总/日报/CSV 导出 |

| 登录 | 管理员登录（含 2FA） |
| 用户管理 | 平台用户查询与管理 |
| 平台用户 | 用户明细、状态与余额操作 |
| 角色权限 | 角色与权限分配 |
| 系统配置 | 平台参数配置 |
| 游戏管理 | 游戏列表、上下架与分类 |
| 支付管理 | 充值订单、支付方式与回调日志 |
| 提现管理 | 提现审核与打款 |
| VIP 管理 | VIP 等级与权益配置 |
| 成就管理 | 成就定义与进度查看 |
| 公告管理 | 公告发布与上下线 |
| CDN 管理 | CDN 五厂商配置与域名管理 |
| 风控管理 | 风控规则与拦截记录 |
| 实名认证 | 实名信息审核 |
| 操作日志 | 管理员操作审计日志 |
| 个人中心 | 管理员资料与安全设置 |

## 环境要求

- Flutter SDK 3.x

## 安装与运行

```bash
cd admin/apps/flutter

# 安装依赖
flutter pub get

# 开发运行（Chrome）
flutter run -d chrome

# 指定后端地址（默认 http://localhost:8787）
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8787

# 构建 Web 生产版本（输出到 build/web/）
flutter build web
```

## 使用说明

1. 先启动管理后台后端服务：`cd admin && php start.php start -d`（默认端口 8787）
2. 使用安装向导创建的管理员账号登录（支持 2FA）
3. 平台用户端前端在 `apps/flutter/platform/`，与后台共用同一后端服务（默认端口 8788）
