# 项目全面规划 (Project Plan)

> 生成日期: 2026-08-16 · 基于 6 人团队 (researcher/architect/backend-dev/frontend-dev/tester/reviewer) 只读盘点 + 关键论断实测验证
> 覆盖: 现状总结 / 问题与风险 / P0-P1-P2 路线图 / 文档修复 / 质量门

---

## 一、项目现状

**全球游戏聚合平台** — PHP 8.3 + webman v2，双应用 monorepo：
`admin/`(8787 管理后台) + `service/`(8788 C端) + `apps/`(Flutter + HarmonyOS) + `install/`(安装向导 43 表)。

| 维度 | 实测规模 |
|------|---------|
| 控制器 | admin 32 + service 30 = 62 |
| API 端点 | ~149 (admin 103 / service 88，含 Webhook/Provider 回调) |
| 数据模型 | admin 46 / service 44，admin/service **重复复制** (无共享层) |
| 测试 | 132 用例 / 8 文件 (admin 项目)，service 项目 **零测试** |
| 版本 | v1.1 (2026-08-07)：Redis 插件、分析服务、Redis 降级、测试修复 |

已实现能力：JWT+RBAC、钱包乐观锁、充值(Stripe/PayPal 验签)、兑换差价、提现审核+PayPal 打款、游戏 CRUD/Provider 网关(HMAC)、优惠券/VIP/成就/工单/推荐返佣/2FA/社交(好友/聊天 WS)/赛事/Webhook/推送(FCM/APNs/华为)/i18n 双语。

---

## 二、问题与风险（已实测验证）

### CRITICAL — 资金安全

| # | 问题 | 位置 |
|---|------|------|
| C1 | 支付回调 `provider` 由客户端传入，非 stripe/paypal 时 **完全跳过验签**，伪造回调直接入账 | service/.../PaymentController.php:36-42 |
| C2 | 验签 fail-open：`STRIPE_WEBHOOK_SECRET` 未配置 → `return true`；PayPal 任何异常 → `return true`。攻击链：自建充值单→伪造回调→无限充值 | PaymentController.php:167, 225 |
| C3 | `JWT_SECRET_KEY` 缺省回退公开硬编码密钥 `open-admin-jwt-secret-change-in-production`，生产未配 env 可伪造管理员 Token | admin+service config/plugin/erikwang2013/jwt/jwt.php:12 |

### HIGH — 正确性/一致性

| # | 问题 | 位置 |
|---|------|------|
| H1 | 分析服务 AnalyticsController 12 方法全实现但 **零路由**，全部 404 死代码，VERSIONS.md 却宣称已交付 | admin/config/route.php (0 处 analytics) |
| H2 | 事件总线断链：emit 有 4 处调用(game.played/withdraw.completed/exchange.completed/referral.applied)，`subscribe()` 无任何进程注册，事件发布即丢失；VIP/成就/通知引擎全部悬空 | admin+service app/event/EventBus.php |
| H3 | common/ 与 model/ 双份复制且已漂移（DepositLogService 两份内容不同、User.php 不一致），单点修复变双份工作。**common/service 已抽出** `packages/platform-common`（erik/platform-common，原 common-php 已并入）；model 与 app/common 包装仍双份 | admin/common vs service/common → packages/platform-common |
| H4 | ~~HarmonyOS C端 `apps/harmonyos/` 为空目录，0 页 vs VERSIONS.md 声称 5 页~~ — 已落地（2026-08-18：5 页实现在 `apps/harmonyos/`） | apps/harmonyos/ |
| H5 | Stripe 回调未校验 `t=` 时间戳容差（可重放），且入账金额未与网关实际支付金额核对 | PaymentController.php:191-194 |
| H6 | Apple id_token 仅 base64 解码 payload，未验签、未校验 aud/iss/exp，跨应用身份混淆风险 | OAuthController.php:376-380 |

### MEDIUM — 可靠性/实现缺陷

| # | 问题 |
|---|------|
| M1 | 2FA 缺陷双响：`/api/2fa/verify` 公开无逐用户尝试锁定（暴力 oracle）；TOTP 用 Base32 字符串直接作 HMAC 密钥（未解码），与 Authenticator 不匹配 → **2FA 实际不可用** |
| M2 | 提现审核/打款为 check-then-act 无原子状态更新，并发可重复打款；无双重审核 |
| M3 | Webhook 回调 URL 仅 filter_var 校验，可指内网 IP（SSRF），dispatch 向任意 URL POST |
| M4 | 提现日/月限额"先查后插"非原子，并发可突破限额 |
| M5 | Redis 故障 fail-open 无统一抽象：JWT 黑名单注销失效、限流静默失效；降级缺口：PayoutService::getAccessToken、ChatWebSocket brpop、OAuth state 存取 |
| M6 | ClickHouse 零使用：概率计算实为 MySQL 实时 COUNT(DISTINCT)+子查询 JOIN，大表 O(n²) 风险；composer 占依赖无能力 |
| M7 | 队列半成品：admin/app/queue 有 ComputeDailyStats + 3 个 ES 任务，但 webman/queue 未安装、process.php 无注册，全部无调用者 |
| M8 | 死代码：Vip/Achievement/Notification/FeatureFlag 服务零调用者；DepositLogService::log() 空实现；Test model 残留；留存算法单 cohort 推算粗糙 |

### LOW
- 提现无 2FA/KYC 强制即可打款至任意 PayPal 邮箱；审核备注进通知文案（XSS 面）
- 文档与实际不符：install.sql 43 表 vs 文档曾写 52；docker-compose 7 服务 vs FEATURES.md 曾写 8；"共享 Model 34" 不实（admin 46 / service 44 各一份，无共享层）。CHANGELOG 已补，见 `docs/CHANGELOG.md`。

### 通过项（安全审查确认无问题）
钱包乐观锁+版本条件更新正确；回调幂等 `where status=pending` 条件更新正确；全 ORM 无直接拼 SQL；.env 未入 git；admin 全路由挂 AdminAuth+RBAC 默认拒绝；OAuth state 校验+单次消费正确。

> **2026-08-18 修复状态**：C1/C2/C3/H1/H5/H6 已修复；H2 事件总线：`process.php` 已登记 `event-consumer` 且消费类 `EventConsumer` 已落地，emit 有消费者；M1 Base32 + 逐用户锁定已修；M2 提现状态原子化 + 可选双重审核已做；M3 Webhook SSRF 已阻断；M4 提现申请 Redis 用户锁已做；M5 部分完成（RateLimit fail-closed）；P2-19 业务指标 + FeatureFlag 灰度已落地。问题清单保留为历史审计结论。

---

## 三、路线图

### P0 — 资金安全 + 正确性（先做，阻断上线）

1. **支付回调 fail-closed**：provider 白名单（仅 stripe/paypal）+ 密钥缺失必须 500 拒绝 + PayPal 异常必拒（C1/C2） — ✅ 已完成（2026-08-18：provider 白名单 + 跨渠道冒用校验 + 来源 IP 可选校验 + 回调入账事务化）
2. **JWT 启动校验**：env 无 `JWT_SECRET_KEY` 拒绝启动（C3） — ✅ 已完成（2026-08-18：JWT_SECRET_KEY 缺失或为默认值 `open-admin-jwt-secret-change-in-production` 时拒绝启动，admin/service 一致）
3. **分析服务挂路由**：注册 analytics 12 路由 + 权限点，修复 VERSIONS.md 承诺（H1） — ✅ 已完成（2026-08-18：admin/config/route.php 注册 12 条 `/admin/analytics/*` 路由）
4. **事件总线打通**：注册常驻订阅进程消费，或改同步直调；事件落库 + 失败重试（H2） — ✅ 已完成（2026-08-18：emit/consume 已 INCR Redis 计数；`service/config/process.php` 登记 `event-consumer`，`service/app/process/EventConsumer.php` 消费事件）
5. **Apple id_token 验签**：JWKS 验证 + aud/iss/exp（H6） — ✅ 已完成（2026-08-18：RS256 JWKS + kid 刷新 + aud/iss/exp）
6. **Stripe 重放与金额核对**：时间戳容差 + 与网关金额比对（H5） — ✅ 已完成（2026-08-18：t= 时间戳 ±300s 防重放 + bccomp 精度金额核对 + 未配置 secret/webhook_id 或验签异常一律拒绝）

### P1 — 可靠性 + 一致性

7. **共享层去重**：common/model 抽 composer path repo（或符号链接），消除双份漂移（H3） — 🔶 部分完成（2026-08-18：`common/service` 已抽出单一 `packages/platform-common` / `erik/platform-common` path repo（原 `common-php` 已并入），admin+service 引用；model 与 host-bound `app/common` 包装仍双份，见 `packages/platform-common/DUAL_MODELS.md`）
8. **统一 Redis 降级封装**：fail 策略显式化 + 告警不静默；补 PayoutService/OAuth/ChatWebSocket 兜底（M5） — 🔶 部分完成（RateLimit fail-closed 已落地：Redis 故障时限流拒绝而非静默放行；其余未做）
9. **webman/queue 接线**：承载事件与 webhook 投递（消费重试、死信），ComputeDailyStats/ES 任务启用或删除（M7） — ⬜ 未做
10. **2FA 修复**：Base32 解码 + verify 加登录态与逐用户尝试锁定（M1） — ✅ 已完成（2026-08-18：RFC 4648 Base32 解码后 HMAC；`/api/2fa/verify` 5 次失败锁定 15 分钟，Redis 故障 fail-closed）
11. **提现原子化**：审核/打款条件更新 + 双重审核；限额 Redis Lua/唯一约束（M2/M4） — 🔶 部分完成（2026-08-18：pending→approved/rejected、approved→processing 条件 UPDATE；可选双重审核 `withdraw.require_dual_review`；申请侧 Redis 用户锁。无 Lua 限额/唯一约束）
12. **Webhook SSRF 阻断**：拒绝内网/保留地址（M3） — ✅ 已完成（2026-08-18：`isSafeWebhookUrl()` 仅 https 公网）
13. **ClickHouse 二选一**：真实接入或摘除依赖 + 修订文档（M6） — ⬜ 未做
14. **死代码清理**：接线或删除 Vip/Achievement/Notification/FeatureFlag；删 Test model；DepositLog 审计落库（M8） — 🔶 部分完成（2026-08-18：Test model 已删，DepositLog 审计落库；Vip/FeatureFlag/Notification 已有调用方；AchievementService 已由 EventConsumer 调用）
15. **service 测试 + CI 门禁**：回调验签/提现流/Redis 降级/概率计算/乐观锁并发集成测试；phpunit 失败阻断；service 纳入 CI（当前 `|| echo warning` 允许失败） — 🔶 部分完成（service 已有 WebhookUrlSafety / EventBusMessageFormat；已纳入 CI `phpunit-service` job 失败阻断）

**本轮（2026-08-18）额外已完成（不在原编号内）**：
- **表前缀修复**：52 模型去除硬编码 `erik_` 前缀，消除 `erik_erik_` 双重前缀；DB 前缀统一由 config/database.php `prefix=erik_` 提供，install.sql 无需变更
- **refresh token 重写**：service AuthController 刷新令牌逻辑重写
- **DepositLogService service 版移植**：service/common/service/DepositLogService.php 补齐（消除 admin/service 双份漂移之一）

### P2 — 可观测 / 扩展 / 体验

16. **HarmonyOS C端** 从零实现 5 页（登录/大厅/详情/钱包/个人）（H4） — ✅ 已完成（2026-08-18：`apps/harmonyos/entry/src/main/ets/pages/` 5 页在库）
17. **前端补全**：2FA 验证页、优惠券/排行榜/通知入口、ES 搜索 UI；合并 main.dart/app_pages.dart 路由源；OAuth 真实回调；前端 AES 传输层
18. **概率计算迁 ClickHouse** 或 MySQL 物化统计表 + 缓存；留存按真实 cohort 重算
19. **Prometheus 业务指标**（事件投递/消费率、队列深度）+ 灰度 AB 分流中间件（复用 FeatureFlag） — 🔶 部分完成（2026-08-18：`GET /metrics` 待审核提现/今日确认充值/事件 emit·consume 计数；FeatureFlag `inRollout`/`abTest` crc32 分桶。队列深度未做）
20. **WebSocket 数据链路闭环**：排行榜/聊天持久化确认
21. **文档对齐**：表数/服务数/共享层描述修正、API 文档与实现对齐、补 CHANGELOG — ✅ 已完成（2026-08-18：见 `docs/CHANGELOG.md`、FEATURES/VERSIONS/PROJECT-PLAN/审计报告 §十）

---

## 四、质量门（团队协作）

- 每次代码变更：admin 全量测试 `vendor/bin/phpunit` 必须通过（去掉 `|| echo warning`）
- 新增敏感路径（支付/提现/认证）必须附带测试
- 改动 common/model 时 admin+service 双侧同步（共享层落地前）
- 审查报告建议重点：ProviderAuth 签名、AES 加密、ProbabilityService 手写 SQL

## 五、团队

game-platform 团队（6 成员：researcher/architect/backend-dev/frontend-dev/tester/reviewer）已就绪，可直接执行 P0。
