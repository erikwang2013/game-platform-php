# 架构设计文档

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 设计目标

构建全球通用、国际化的游戏聚合平台。核心需求：

- 用户可在平台充值、兑换游戏币、玩游戏、赚取游戏币、提现
- 平台统一管理多种游戏（自研 + 第三方），每种游戏有独立游戏币和汇率
- 后台提供完整的审核、开关、风控能力
- 支持多语言、多币种、多支付渠道的全球化运营

## 2. 架构选型

### 2.1 为什么选模块化单体而非微服务？

当前阶段选择模块化单体（Modular Monolith）：

| 考量 | 模块化单体 | 微服务 |
|------|----------|--------|
| 开发效率 | 同一进程内调用，无需 RPC | 需处理网络延迟、序列化 |
| 事务一致性 | 本地数据库事务 | 分布式事务（复杂） |
| 运维复杂度 | 单进程部署 | 多服务编排、服务发现 |
| 扩展性 | 未来可按模块拆分为微服务 | 天然支持独立扩缩容 |
| 团队规模 | 适合小团队 (1-5人) | 适合多团队并行开发 |

**决策**：admin/（管理后台）和 service/（C端业务）是两个独立 webman 实例，可同机部署（不同端口）也可分开部署。共享层 common/ 通过 PSR-4 autoload 消除代码重复。未来业务量增长后，service/ 可拆分为多个微服务（用户服务、钱包服务、游戏服务）。

### 2.2 为什么选 webman v2 而非传统 PHP-FPM？

| 考量 | webman v2 | PHP-FPM (Laravel) |
|------|-----------|-------------------|
| 性能 | 常驻内存，协程支持 | 每次请求加载全部文件 |
| 并发 | 单机数万 QPS | 单机数百 QPS |
| 部署 | 简单，单进程多 worker | Nginx + PHP-FPM 配置复杂 |
| 生态 | 兼容 Laravel Illuminate 组件 | 完整生态 |

**决策**：游戏平台需要处理高并发的充值回调、兑换请求、游戏结算，webman 的常驻内存和高并发能力更适合。同时兼容 Laravel 的 ORM、Queue 等组件，开发效率不输传统框架。

### 2.3 为什么用 Flutter Web PC 风格？

- 一套代码可同时编译 Web (PC)、iOS、Android、HarmonyOS
- Material 3 组件库成熟，PC 风格侧边栏+顶栏布局开箱即用
- 与 HarmonyOS 客户端共享业务逻辑层
- 避免维护 React/Vue + Flutter 两套前端代码

## 3. 关键技术决策

### 3.1 ID 体系

```
Snowflake 生成 BIGINT（内部分布式唯一）
    ↓
Hashids 编码为短字符串（对外不可逆推真实ID）
    ↓
API 请求/响应中传输 hashid 字符串
```

**理由**：
- Snowflake 全局唯一、趋势递增利于索引、不暴露业务量
- Hashids 防止外部通过自增 ID 遍历数据、推测规模

### 3.2 币种精度

平台币和游戏币统一使用 `DECIMAL(18,4)` 精度，PHP 侧使用 `bcmath` 函数族（bcadd/bcsub/bcmul/bcdiv/bccomp）进行所有金额计算。

**理由**：浮点数（float/double）存在精度误差，在金融场景下不可接受。DECIMAL + bcmath 保证精确计算。

### 3.3 钱包乐观锁

```sql
UPDATE erik_user_wallet 
SET balance = balance + ?, version = version + 1 
WHERE user_id = ? AND version = ?
```

更新失败自动重试（最多5次）。

**理由**：
- 游戏平台充值、兑换、提现都可能并发操作同一钱包
- 悲观锁（SELECT FOR UPDATE）在高并发下性能差
- 乐观锁在冲突率低的场景下性能远优于悲观锁

### 3.4 提现审核流程

```
用户发起提现
  ├─ 全局开关关闭 → 拒绝
  ├─ 金额 < 自动审核阈值 → 自动通过
  └─ 金额 >= 阈值 → 人工审核 → 通过/拒绝（拒绝退回平台币）
```

**理由**：
- 全局开关用于紧急风控（如发现漏洞、异常流量）
- 小额自动通过减少人工成本，提升用户体验
- 大额人工审核防止洗钱和欺诈

### 3.5 兑换差价模型

每种游戏币有独立 `exchange_rate`（1平台币 = X游戏币）和 `spread_pct`（平台抽成%）。

买入时：游戏币到账 = 平台币 × 汇率 × (1 - 抽成%)
卖出时：平台币到账 = 游戏币 ÷ 汇率 × (1 - 抽成%)

**理由**：
- 平台收益来源于兑换差价，而非游戏内付费
- 独立汇率支持不同游戏的定价策略
- 差价比例可灵活调整，实现精细化运营

## 4. 安全架构

在现有 18 层纵深防御基础上，针对游戏平台新增保护层：

| 层面 | 措施 | 原因 |
|------|------|------|
| 并发安全 | 钱包 version 乐观锁 | 防止重复扣款/重复到账 |
| 提现安全 | 全局开关 + 金额阈值 + 日/月限额 + poster-php 验证 | 多层防控，降低资金风险 |
| 兑换安全 | 询价与成交分离，询价60s过期 | 防止汇率波动导致的套利 |
| 游戏安全 | 第三方回调签名验签 + IP白名单 + replay attack 防御 | 防止伪造游戏结算 |
| 风控 | 规则引擎（IP黑名单、大额预警、频率异常） | 实时阻断可疑交易 |

## 5. 国际化设计

### 5.1 语言检测

```
请求进入
  ↓
LanguageMiddleware（全局中间件）
  ├── 1. X-Language 请求头
  ├── 2. Accept-Language 头（zh → zh-CN, en → en-US）
  └── 3. 默认 en-US
  ↓
TranslationService::setLocale($locale)
  ↓
Controller 中 __() 函数或 TranslationService::trans() 获取翻译文本
```

### 5.2 翻译存储

- 数据库表 `erik_translation` 存储所有翻译文本（group + key + lang_code + value）
- 首次请求从数据库全量加载到 Redis（key: `i18n:translations`，TTL: 1小时）
- 后续请求直接从 Redis 读取，内存缓存加速
- 管理后台可扩展翻译管理页面（完整版实现）

### 5.3 翻译键命名

格式：`group.key` 如 `auth.login_success`、`wallet.insufficient_balance`

| 分组 | 域 |
|------|------|
| auth | 认证相关 |
| wallet | 钱包相关 |
| exchange | 兑换相关 |
| withdraw | 提现相关 |
| deposit | 充值相关 |
| game | 游戏相关 |
| admin | 管理后台 |
| error | 错误信息 |

### 5.4 回退策略

- 请求语言有对应翻译 → 使用
- 请求语言无对应翻译 → 回退到 en-US
- en-US 也无 → 返回原始 key

### 5.5 前端 i18n

- Flutter 使用自建 `AppTranslations` + `LocaleController`（GetX）
- 语言偏好持久化到 SharedPreferences
- 切换语言时通过 `Get.updateLocale()` 触发全局 UI 重渲染
- `StringResult` 类利用 Dart 的 `toString()` 实现自然内联语法：`Text('${AppTranslations.t("key")}')`

## 6. 标准版新增设计

### 6.1 风控引擎

在关键资金操作前执行多层规则检查：

```
充值/提现/兑换请求
  ↓
RiskService::check(userId, type, context)
  ├── IP 黑名单检测 (ip_blacklist) → block
  ├── 大额异常检测 (amount_anomaly) → warn
  ├── 频率检测 (frequency) → warn/block
  └── 速度检测 (velocity) → block
  ↓
passed → 正常执行
warn   → 记录日志，继续执行
block  → 拒绝操作
```

规则存储在 `erik_risk_rule` 表，配置为 JSON，可动态调整阈值和动作。

### 6.2 KYC 实名认证

三级认证体系：
- `default` — 未认证，基础限额
- `verified` — KYC 审核通过，提高限额+降低手续费
- `vip` — VIP 等级，最高限额+零手续费

认证流程：
```
用户提交证件信息 → status=pending
管理员审核 → approve/reject
approve → 用户自动升级为 verified 等级
reject → 用户可重新提交
```

### 6.3 OAuth 第三方登录

支持 Google / Facebook / Apple 登录：

```
前端点击 OAuth 按钮
  → GET /api/auth/oauth/{provider} → 获取授权URL
  → 跳转第三方授权页 → 用户同意
  → 回调 POST /api/auth/oauth/{provider}/callback
  → 查找已有绑定 → 直接登录
  → 无绑定 → 自动注册新用户 + 绑定 + 创建钱包
```

### 6.4 支付回调

```
第三方支付完成 → POST /api/payment/callback
  → provider 白名单校验（仅 stripe/paypal）
  → 验签 fail-closed（未配 secret/webhook_id、验签失败、时间戳超 ±300s 一律拒绝）
  → 回调金额与订单金额 bccomp 核对（防跨渠道冒用）
  → 更新订单状态 confirmed（事务化，入账失败回滚）
  → UserWallet::addBalance 到账
  → 记录 Transaction
  → RiskService::check 风控检查
```

### 6.5 阶梯提现限额

按用户 KYC 等级应用不同的限额和手续费：

| 等级 | 单笔上限 | 日限额 | 月限额 | 手续费 |
|------|---------|--------|--------|--------|
| default | 1,000 | 10,000 | 50,000 | 1.00% |
| verified | 5,000 | 50,000 | 200,000 | 0.50% |
| vip | 20,000 | 200,000 | 1,000,000 | 0.00% |

## 7. 扩展性设计

### 5.1 水平扩展

admin/ 和 service/ 均支持多 worker 进程。配合 Nginx 反向代理，可部署多台机器实现水平扩展：

```
Nginx (负载均衡)
  ├── admin-1 (:8787)
  ├── admin-2 (:8787)
  ├── service-1 (:8788)
  └── service-2 (:8788)
```

### 5.2 模块拆分路径

当单一 service/ 成为瓶颈时，按以下路径拆分：

```
service/ (单体)
  → service-user/ (用户服务 :8788)
  → service-wallet/ (钱包服务 :8789)
  → service-game/ (游戏服务 :8790)
  → service-payment/ (支付服务 :8791)
```

拆分时机判断标准：
- 单个模块 QPS 超过单机承载能力
- 某个模块需要独立的技术栈或部署策略
- 团队规模扩大到需要并行开发不同模块
