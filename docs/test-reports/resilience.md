# 稳定性机制测试报告（熔断 / 重试 / 降级开关）

- 日期：2026-08-27
- 被测实现：`packages/platform-common/src/CircuitBreaker.php`、`packages/platform-common/src/Retry.php`、`service/app/service/FeatureFlag.php` + 三处 mock 接入
- 测试文件：
  - `service/tests/CircuitBreakerTest.php`（5 用例）
  - `service/tests/RetryTest.php`（6 用例）
  - `service/tests/ResilienceMockTest.php`（4 用例）

## 运行命令

```bash
cd service && SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me HASHIDS_SALT=test-hashids-salt-change-me HASHIDS_ALT_SALT=test-hashids-alt-salt-change-me php vendor/bin/phpunit
```

## 结果

| 项目 | 数值 |
|------|------|
| 用例总数 | 60（原有 45 + 新增 15） |
| 通过 | 60 |
| 失败 / 错误 | 0 / 0 |
| 跳过 | 0 |
| 断言数 | 132 |

三次运行结果一致（60/60 通过），测试写入的 Redis keys（`cb:test:*`）与 `platform_config`/`withdraw_order`/`device_token` 测试行均清理干净（tearDown 删除），不影响其他测试。

## 覆盖行为

**CircuitBreakerTest**（真实 Redis，key 命名空间 `cb:test:*`）
- 连续失败达阈值（opts `failure_threshold`）→ 后续调用抛 `CircuitOpenException` 快速失败
- 成功调用删除失败计数 → 计数重置、电路保持 closed
- `open_window` 冷却过后半开探测，成功调用清除 `opened_at`/`failures` 恢复 closed
- 业务异常（非 ConnectException/ServerException/超时类）不计入失败计数、不触发 open
- 自定义阈值生效（阈值 4 时第 4 次失败才 open）

**RetryTest**（纯逻辑）
- 可重试异常（ConnectException/ServerException/`timed out`/`curl error 28`）按指数退避重试至成功（失败 2 次 → 共调用 3 次）
- 不可重试异常立即直抛（调用次数 = 1，同一异常实例）
- `maxAttempts` 上限钳制到 5（请求 10 次实际只跑 5 次）
- `maxAttempts=1` 只跑一次

**ResilienceMockTest**（feature.provider_mock 降级开关）
- `provider_mock=on`：`PushService::send` 短路返回（不查询 token、不发网络请求）；`PayoutService::execute` 短路成功（返回 `mock-{order_no}` 批次号、订单标记 completed，不触碰 PayPal/凭证）
- `provider_mock=off`：恢复原行为（Push 走原路径；Payout 进入真实路径，在无凭证处失败而非短路成功）

## 发现的问题（未改业务代码，仅记录）

1. ~~[P1] `getenv($name, '')` 第二参类型缺陷~~ **已修复**：`service/app/service/PayoutService.php`、`admin/app/service/PayoutService.php` 与 `service/app/service/PushService.php` 共 11 处 `getenv($name, '')` 全部改为 `getenv($name)`（`$local_only` 保持默认 false，行为等价）。修复后真实 PayPal 路径按设计抛 `RuntimeException('PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET must be configured')`，mock=off 测试断言随之更新（`catch (\RuntimeException)` + `assertStringContainsString('must be configured')`）。

2. ~~[P2] PushService::send 的 mock 检查在 try/catch 之外~~ **已修复**：mock 检查移入 try 块内，MySQL 不可用时 `FeatureFlag::isEnabled()` 抛异常被外层 catch 吞掉，符合「Push failure must not block main flow」承诺。

3. **[环境] service/.env 的 `DB_PASSWORD=root` 与本机免密 root 冲突**：所有 DB 测试连不上测试库。已在 `service/tests/bootstrap.php` 测试连接初始化处强制空密码（仅测试环境，不影响业务运行）。建议对齐 .env 或机器配置。注意：`.env` 经 `Dotenv::createUnsafeMutable` 加载会覆盖 shell 环境变量，无法用命令行覆盖。

4. **[既有已知缺陷] `FeatureFlag::enable()`/`PlatformConfig::set()` 在目标行不存在时走 insert 路径，因模型未生成 id 抛错**（FeatureFlagTest 已记录）。mock 测试通过预置 `provider_mock` 行（显式 id）绕过。

## 未直接测试项

- **CircuitBreaker Redis 不可用 fail-open**：无干净的注入点（`support\Redis` 为静态门面），通过代码审查确认 `redis()` 助手捕获所有异常返回 false 实现 fail-open。
- **ThirdPartyProvider::request mock 分支**：`request()` 为私有方法且构造依赖 Game 模型与 HTTP 客户端，mock 接入验证以 PushService/PayoutService 为准；代码审查确认其 mock 分支存在（`provider_mock=on` 返回 `['success' => true]`）。
- **重试退避实际睡眠时长**：以调用次数断言重试行为，`baseDelayMs=1` 提速；退避公式 `200 * 2^(attempt-1)`（200/400/800ms）经代码审查确认。
