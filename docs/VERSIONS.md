# 版本对比

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 总览

| | 基础版 (Lite) | 标准版 (Standard) | 完整版 (Full) |
|------|------|------|------|
| 数据表 | 19 | 29 | 42 |
| API 端点 | 38 | 54 | 129 |
| 后端控制器 | 14 | 22 | 48 |
| 共享 Model | 14 | 24 | 34 |
| 共享 Service | 1 | 2 | 5 |
| Admin 前端页面 | 11 | 13 | 15 |
| Platform 前端页面 | 8 | 10 | 10 |
| HarmonyOS 页面 | 2 | 2 | 5 |
| Docker 服务 | - | - | 7 |
| 测试用例 | 60 | 60 | 116 |

---

## 用户认证

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 用户名密码注册/登录 | ✓ | ✓ | ✓ |
| JWT Token (2h+14d) | ✓ | ✓ | ✓ |
| 点击验证码 | stub | stub | ✓ poster-php |
| 账号锁定 (5次/15分钟) | ✓ | ✓ | ✓ |
| 会话限制 (3并发) | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ 真实对接 |
| 2FA TOTP 双因素认证 | - | - | ✓ |
| GDPR 数据导出/注销 | - | - | ✓ |

---

## 钱包与资金

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 平台币钱包 | ✓ | ✓ | ✓ |
| 钱包乐观锁 | ✓ | ✓ | ✓ |
| 流水记录 | ✓ | ✓ | ✓ |
| 游戏币钱包 | ✓ | ✓ | ✓ |
| 充值订单创建 | ✓ | ✓ | ✓ |
| 充值回调自动到账 | - | ✓ 手动 | ✓ Stripe/PayPal验签 |
| 兑换询价/买入/卖出 | ✓ | ✓ | ✓ |
| 兑换差价收益 | ✓ | ✓ | ✓ |
| 提现申请 | ✓ | ✓ | ✓ |
| 全局提现开关 | ✓ | ✓ | ✓ |
| 提现审核 | ✓ 手动 | ✓ 手动 | ✓ 批量+手动 |
| KYC阶梯限额 | - | ✓ 3级 | ✓ |
| 提现手续费 | - | - | ✓ |
| PDF收据 | - | - | ✓ |

---

## 游戏管理

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 游戏 CRUD | ✓ | ✓ | ✓ |
| 游戏币种管理 | ✓ | ✓ | ✓ |
| C端游戏列表/详情 | ✓ | ✓ | ✓ |
| 游戏启动 | ✓ | ✓ | ✓ |
| 游戏分类 (10类) | - | - | ✓ |
| 分类筛选 | - | - | ✓ |
| 游戏区服管理 | - | ✓ | ✓ |
| 游戏记录追踪 | - | ✓ | ✓ |
| ES 全文搜索 | - | - | ✓ |
| 搜索建议 | - | - | ✓ |
| 第三方游戏 SDK | - | - | - |

---

## 运营工具

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 公告管理 | ✓ | ✓ | ✓ |
| 仪表盘 | ✓ 管理后台 | ✓ 管理后台 | ✓ 管理+平台 |
| Excel 导出 | ✓ | ✓ | ✓ |
| PDF 导出 | ✓ | ✓ | ✓ |
| 仪表盘真实图表 | - | - | ✓ fl_chart |
| 优惠券系统 | - | - | ✓ |
| 排行榜 (日/周/月/总) | - | - | ✓ Redis缓存 |
| WebSocket 实时排行榜 | - | - | ✓ 端口8789 |
| 通知系统 (站内+邮件) | - | - | ✓ |
| 推荐返利 | - | - | ✓ |
| 日统计快照 | - | ✓ | ✓ |
| 平台收益追踪 | - | - | ✓ |

---

## 安全合规

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 18层纵深防御 | ✓ | ✓ | ✓ |
| RBAC 权限控制 | ✓ | ✓ | ✓ |
| 操作审计日志 | ✓ | ✓ | ✓ |
| 8平台来源端检测 | ✓ | ✓ | ✓ |
| Redis滑动窗口限流 | ✓ | ✓ | ✓ |
| KYC 实名认证 | - | ✓ | ✓ |
| 风控引擎 (4规则) | - | ✓ | ✓ |
| 支付回调验签 | - | - | ✓ |

---

## 国际化

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 多语言支持 | 中/英文 | 4语言 | 4语言 |
| 翻译表+缓存 | ✓ | ✓ | ✓ |
| 语言自动检测 | ✓ | ✓ | ✓ |
| 国家差异化配置 | - | - | ✓ 8国 |

---

## 部署运维

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| webman 独立部署 | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ 7服务 |
| Nginx 反向代理 | - | - | ✓ |
| Crontab 定时任务 | - | ✓ | ✓ |
| Prometheus 监控 | ✓ | ✓ | ✓ |
| 健康检查 | ✓ | ✓ | ✓ |
| hg/apidoc 在线文档 | - | - | ✓ 41控制器 |

---

## 客户端

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| Flutter Web PC 管理后台 | ✓ 5页 | ✓ 11页 | ✓ 15页 |
| Flutter Web PC 用户平台 | ✓ 5页 | ✓ 8页 | ✓ 10页 |
| HarmonyOS 客户端 | - | ✓ 登录+仪表盘 | ✓ 5页 |

---

## 数据库表

### 基础版 (19张)
```
管理后台 (7):  erik_admin_user, erik_admin_role, erik_admin_permission,
               erik_admin_user_role, erik_admin_role_permission,
               erik_operation_log, erik_system_config

平台核心 (12): erik_user, erik_user_wallet, erik_user_game_wallet,
               erik_game, erik_game_currency, erik_deposit_order,
               erik_withdraw_order, erik_exchange_record, erik_transaction,
               erik_payment_method, erik_announcement, erik_platform_config
```

### 标准版新增 (10张)
```
erik_user_identity, erik_user_oauth, erik_user_payment_account,
erik_user_session, erik_game_server, erik_game_play_log,
erik_withdraw_limit, erik_risk_rule, erik_risk_log, erik_stat_daily
```

### 完整版新增 (13张)
```
erik_game_category, erik_game_category_rel, erik_leaderboard,
erik_coupon, erik_user_coupon, erik_language, erik_translation,
erik_country_config, erik_platform_revenue,
erik_notification, erik_referral, erik_referral_reward, erik_user_2fa
```

---

## API 端点

| 模块 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 认证 | 3 | 3 | 7 (+OAuth×2 +2FA×5) |
| 钱包 | 2 | 2 | 3 (+充值回调) |
| 兑换 | 4 | 4 | 4 |
| 提现 | 2 | 2 | 8 (+批量+限额+审核) |
| 游戏 | 3 | 4 | 7 (+区服+记录+搜索) |
| 用户 | 2 | 2 | 7 (+KYC+GDPR+隐私) |
| 管理后台 | 18 | 25 | 79 |
| 运营工具 | - | - | 30 (+排行榜+优惠券+通知+推荐) |
| 国际化 | 2 | 2 | 4 (+国家配置) |
| **总计** | **38** | **54** | **129** |
