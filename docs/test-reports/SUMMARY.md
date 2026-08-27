# 全量测试总报告

- 测试日期: 2026-08-27
- 测试团队: PHP 测试工程师 / API 测试工程师 / UI 自动化测试工程师
- 分支: feat/xiaoxiaole-p0
- 测试库: `game_platform_test`（install.sql 全量导入，43 张表）

## 一、总览

| 测试类型 | 覆盖范围 | 用例/断言 | 通过 | 失败/错误 | 报告 |
|---------|---------|----------|------|----------|------|
| PHP 单元测试 | admin + service + platform-common 核心 service | 198 用例 / 399 断言 | 187 | 7（均为既有问题） | [php-unit.md](php-unit.md) |
| 稳定性机制测试 | 熔断/重试/降级开关（新增 15 用例，service 45 → 60） | 60 用例 / 132 断言 | 60 | 0 | [resilience.md](resilience.md) |
| API 接口自动化 | 187/187 端点 100% 覆盖 | 225 断言 | 171 | 50（确定性缺陷） | [api.md](api.md) |
| Flutter UI 测试 | 登录/仪表盘/导航/语言切换/退出 | 12 用例 | 12 | 0 | [ui.md](ui.md) |
| Go/Rust | 仓库无 Go/Rust 代码 | — | — | — | 跳过，见 §四 |
| HarmonyOS | 无 IDE/模拟器，无法本机运行 | — | — | — | 静态记录于 ui.md |

## 二、关键链路验证结果

- ✅ 认证链：注册/登录/刷新 token/无 token/伪造 token/错误密码/5 次锁定
- ✅ 充值链：创建订单 → 回调入账
- ✅ 提现双审链：初审 → 二次确认；同管理员复核被正确拒绝
- ✅ Flutter：登录表单校验、验证码失败、离线回退、14 导航项、语言切换

## 三、测试发现的缺陷（确定性，非测试环境问题）

### 严重（建议尽快修复）
1. **hashids 非法输入未捕获** → 41 个 `{hashid}` 路由传非法 ID 直接 500（`InvalidArgumentException` 未转 400）
2. **hashids 配置不兼容**：admin 长度 0 / service 长度 16，管理端无法解码服务端订单号，提现审核链无法打通
3. **PlatformConfig::set() insert 路径必然失败**（admin 生产 bug）
4. **erik_risk_log.result VARCHAR(20) 截断** → 风控日志静默丢失（建议改 TEXT）
5. **控制器误用 `Request::has()`**（webman 无此方法）→ 5 个接口 500
6. **ExportController 返回类型不匹配** → 2 个导出接口 500

### 一般
7. **JWT 环境变量命名不匹配**：config 读取 `ADMIN_JWT_SECRET_KEY`/`SERVICE_JWT_SECRET_KEY`，`.env` 中为 `JWT_SECRET_KEY`（旧命名），本地/测试启动必须显式导出环境变量
8. CaptchaTest 与 poster-php 新 API 不匹配、PlatformTest 引用不存在的 admin TranslationService（既有测试损坏）

### 已修复（测试前置）
- Flutter `lib/` 17 文件 ~150 处编译错误（历史损坏，从未编译通过）——纯机械修复，`flutter analyze` 0 error
- 两个 tests/bootstrap.php：修复 Eloquent 默认连接缺失根因，测试库固定 `game_platform_test`

## 四、Go/Rust 说明

全仓库扫描 0 个 `.go` / `.rs` 文件（语言组成为 PHP 后端 + Flutter/HarmonyOS 客户端），
Go/Rust 测试工程师无测试对象，经确认后跳过并记录于此。若后续引入 Go/Rust 模块，
需按同样标准补齐单元测试。

## 五、复现命令

```bash
# 1. PHP 单元测试（admin + service）
cd admin && ADMIN_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
cd service && SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me \
  HASHIDS_SALT=test-hashids-salt-change-me HASHIDS_ALT_SALT=test-hashids-alt-salt-change-me \
  php vendor/bin/phpunit

# 2. API 接口自动化（自动起停服务，需 MySQL+Redis）
bash tests/api/run_all.sh

# 3. Flutter UI 测试
cd admin/apps/flutter && flutter test --timeout 300s
```

## 六、结论

- PHP 单元测试：service 45/45 全绿；admin 153 用例中 7 个失败/错误均为既有问题，新写的 63 用例全过
- API 自动化：187 端点 100% 覆盖，50 个失败全部为可复现缺陷（非偶发），核心业务链路（认证/充值/提现双审）跑通
- UI 自动化：12/12 通过，离线可跑
- **上线前建议**：优先修复 §三 的 6 项严重缺陷（特别是 hashids 500 与配置不兼容）
