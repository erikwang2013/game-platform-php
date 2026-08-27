# PHP 单元测试报告

- 日期: 2026-08-27
- 环境: PHP 8.3.7, MySQL 8.0（测试库 `game-platform_test`）, Redis 7
- 范围: admin / service / packages/platform-common 核心业务 service 类

## 运行命令

```bash
# admin（端口 8787）
cd admin && ADMIN_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit

# service（端口 8788）
cd service && SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me \
  HASHIDS_SALT=test-hashids-salt-change-me \
  HASHIDS_ALT_SALT=test-hashids-alt-salt-change-me \
  php vendor/bin/phpunit
```

> 必须导出上述密钥环境变量：`config/plugin/erikwang2013/jwt/jwt.php` 等配置读取的是
> `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY`，而 `.env` 中不存在同名变量。
> service 的 hashids 配置同理需要 `HASHIDS_SALT` / `HASHIDS_ALT_SALT`。

## 结果摘要

| 项目 | 测试数 | 断言数 | 通过 | 失败 | 错误 | 跳过 | 警告 | Risky |
|------|-------|-------|------|------|------|------|------|-------|
| admin | 153 | 305 | 142 | 1 | 6 | 4 | 5 | 1 |
| service | 45 | 94 | 45 | 0 | 0 | 0 | 0 | 0 |
| **合计** | **198** | **399** | **187** | **1** | **6** | **4** | **5** | **1** |

- admin 套件连续运行多次结果稳定（153 tests，6 errors + 1 failure 均为既有测试问题，见"发现的问题"）。
- service 套件连续运行两次均 `OK (45 tests, 94 assertions)`。
- 测试库 `game-platform_test` 拥有全部 43 张表（install.sql 全量导入）。

## 新增测试文件

### admin/tests/（本次新增 4 个文件，21 个用例，全部通过）

| 文件 | 用例数 | 覆盖内容 |
|------|-------|---------|
| `PayoutServiceTest.php` | 7 | 非 approved 状态拒绝、重试次数上限、无 batch_id 时 syncStatus 短路、PayPal 邮箱提取（json paypal_email / email 回退 / 纯字符串 / 非法抛错）、markCompleted 幂等 |
| `LeaderboardServiceTest.php` | 4 | earned 指标按用户分组聚合（DB fixtures）、disabled 排行榜返回空、Redis 缓存命中、clearCache |
| `NotificationServiceTest.php` | 3 | send() 持久化通知行、未知用户仍持久化、数据库不可用时静默失败 |
| `PlatformCommonTest.php` | 6 | ProbabilityService 纯函数：escapeValue 全类型、quoteTable 单段/双段、buildWhereClause 标量/IN/whereRaw、buildDistinctSetSql 精确 SQL、joint/conditional 无数据库返回 0 |

### service/tests/（本次新增 6 个文件，42 个用例，全部通过）

| 文件 | 用例数 | 覆盖内容 |
|------|-------|---------|
| `RiskServiceTest.php` | 9 | evaluateRule：IP 黑名单命中/未命中、金额异常边界（>= 阈值命中）、未知规则类型不匹配；check()：block/warn/passed 三态 + 高优先级规则优先命中 |
| `FeatureFlagTest.php` | 8 | isEnabled 默认 off、enable/disable 切换、灰度 0%/100%/50% 确定性分桶、all() 映射、缺失行 enable 抛错（已知缺陷） |
| `TranslationServiceTest.php` | 8 | setLocale/getLocale、4 种语言列表、trans 键格式校验、注入缓存翻译/回退 en-US/参数替换、clearCache |
| `VerificationServiceTest.php` | 6 | 邮件/SMS 验证码发送、冷却期拦截、验证通过后清除 key、错误验证码拒绝 |
| `PushServiceTest.php` | 3 | base64url 编码 URL-safe、无设备 token 静默返回 |
| `PayoutServiceTest.php` | 8 | 与 admin 相同的守卫 + 邮箱提取 + 幂等 + DB 可用时持久化 completed 状态 |

## 覆盖率估算

以核心 service 类为分母（未对 controller/model/中间件估算）：

| 类 | 覆盖率估算 | 说明 |
|----|-----------|------|
| `PayoutService`（admin+service） | ~80% | 守卫/邮箱提取/幂等/DB 持久化；execute/syncStatus 的真实 PayPal HTTP 调用未测（需 mock Guzzle） |
| `LeaderboardService` | ~75% | 两种 metric + Redis 路径；spent/play_count 分支由 DB 存在性间接覆盖 |
| `NotificationService` | ~60% | send() 全路径；sendEmail SMTP 分支仅走 log-only 模式 |
| `RiskService` | ~80% | 3 种规则类型 + check() 三态；frequency/velocity 需 mock 时间窗口未覆盖 |
| `FeatureFlag` | ~85% | 全公开 API；`enable()` insert 路径为已知缺陷 |
| `TranslationService` | ~75% | 纯逻辑全覆盖；DB/Redis 加载路径需真实数据 |
| `VerificationService` | ~90% | 全 Redis 路径（真 Redis 环境） |
| `PushService` | ~40% | base64url + no-op 路径；FCM/APNS/HarmonyOS 实际发送未测 |
| `common\service\ProbabilityService` | ~65% | SQL 构建全覆盖；joint/conditional 数值路径依赖 DB |
| `VipService` / `AchievementService` | ~10% | 依赖缺失表（见问题 #5），仅可测内部纯逻辑 |

整体估算：**核心 service 类平均覆盖率 ≈ 70%**。

## 发现的问题

1. **[严重] `support\bootstrap\Database::start` 未设置默认连接**
   `admin/support/bootstrap/Database.php` 与 `service/support/bootstrap/Database.php` 中
   `start()` 只 `addConnection()` 各连接（名为 `mysql`），从未调用
   `setDefaultConnection()`。任何未显式指定连接的 Eloquent 查询在真实运行中都会报
   `Database connection [default] not configured`。tests/bootstrap.php 已在 bootstrap 之后
   用裸 `Capsule\Manager` 统一重建（与 `support\Db` 共享 static 实例）规避。

2. **[严重] `PlatformConfig::set()` insert 路径必然失败（影响 `FeatureFlag::enable()`）**
   `updateOrCreate` 在无现存行时走 insert，模型 `$incrementing=false` 且未生成 id，
   `game-platform_config.id` 列无默认值 → `SQLSTATE 1364 Field 'id' doesn't have a default value`。
   已在 `FeatureFlagTest::enableOnMissingRowThrowsDueToMissingId` 中固化。
   影响：`FeatureFlag::enable()/disable()` 首次开启某功能开关必然抛错。

3. **[中] `game_risk_log.result` VARCHAR(20) 截断导致风控日志静默丢失**
   `RiskService::log()` 写入的消息（如 `IP 5.5.5.5 in blacklist` 22 字符、
   `Large amount 5000 detected` 25 字符）超过列长，insert 失败被 try/catch 吞掉。
   风控日志在真实环境中不会写入。建议 `result` 改 TEXT 或截断消息。

4. **[中] admin 套件既有测试 `CaptchaTest` 与 poster-php 库 API 不匹配（5 错误/失败）**
   `captcha_create('click')` 返回 `extra.texts`（含 text/order），而测试断言 `extra.targets`
   （含 x/y）。坐标只存于服务端存储，不随响应返回，故 `captcha_verify_correct_clicks_passes`
   亦无法用响应数据构造。非 Imagick 缺失（扩展已加载）。测试需按新 API 重写。

5. **[中] `PlatformTest` 引用不存在的 `app\service\TranslationService`（3 错误）**
   admin 项目没有该类（仅 service 项目有）。`tests/PlatformTest.php` 中 3 个
   translation 相关用例在 admin 下必然报 `Class not found`。应删除或改用 service 的
   `TranslationServiceTest`（本次已新增）。

6. **[低] 环境变量命名不一致**
   运行需导出 `ADMIN_JWT_SECRET_KEY`/`SERVICE_JWT_SECRET_KEY`，而 `.env` 使用
   `JWT_SECRET_KEY`。jwt 插件配置读取的是前者，导致不导出就无法启动测试/应用。

7. **[低] 缺失表：`game_user_vip`/`vip_level`/`exp_log`/`achievement`/`user_achievement`**
   install.sql 未包含这些表，`VipService`/`AchievementService` 的 DB 路径不可测试
   （本次仅测内部纯逻辑）。若这两个服务有真实使用场景，需要补建表。

8. **[低] 测试 bootstrap 中 plugin 配置未加载**
   `support\App::loadAllConfig(['route'])` 不会加载 `config/plugin/*/app.php`，
   导致 Encryptable 读不到插件密钥，回退环境变量（service 的 `.env` 密钥长度不足
   aes-256-gcm 需要的 32 字节）。已在 service bootstrap 中固定测试密钥
   （`ENCRYPTION_KEY=0123456789abcdef0123456789abcdef`）保证确定性。

## 基础设施改动

- `admin/tests/bootstrap.php`：bootstrap 后统一重建 Eloquent 连接（修复 #1），
  默认指向 `game-platform_test`（`DB_DATABASE_TEST` 可覆盖）。
- `service/tests/bootstrap.php`：由 3 行扩展为完整 bootstrap（autoload 文件、
  bootstrap 类、Eloquent 初始化），与 admin 相同处理；并固定测试加密密钥（#8）。
- 两套件均在 bootstrap 之后以裸 `Capsule\Manager` + `setDefaultConnection`
  显式建立连接，测试永不读写开发库。

## 未覆盖 / 后续建议

- `PayoutService::execute()/syncStatus()` 的真实 PayPal HTTP 调用（需 Guzzle mock 或
  sandbox 账号）。
- `RiskService` frequency/velocity 规则（依赖时间窗口 fixtures，可注入 `created_at`）。
- `PushService` FCM/APNS/HarmonyOS 实际推送（需 mock Guzzle）。
- 修复 #2 后补充 `FeatureFlag::enable()` 的 insert 路径正向用例。
