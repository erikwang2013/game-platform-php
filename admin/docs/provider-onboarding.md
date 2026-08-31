# Provider 接入规范

> Copyright (c) 2026 erik <erik@erik.xyz — https://erik.xyz

把「怎么接一个新支付网关」「怎么接一个新游戏」写成可照着执行的清单，接入成本从「读源码」降到「照清单打勾」。本文档只描述**当前代码的真实行为**，所有契约均可直接对读源码验证（`service/app/payment/`、`service/app/provider/`、`service/app/middleware/ProviderAuth.php`、`service/app/api/v1/controller/`）。

## 1. 现有扩展模式总结

| 层 | 入口 | 策略 | 失败语义 |
|---|---|---|---|
| 支付 | `GatewayFactory::resolve(string $provider)` | `match` 硬编码 16 分支 + `PaymentGatewayInterface` | 未知 provider 抛 `InvalidArgumentException` |
| 游戏 | `ProviderFactory::create(Game)` / `createById(int)` | `match` `game.type`（`self` / `third_party`）+ `GameProvider` 抽象类 | 未知 type 抛 `InvalidArgumentException` |

**已知短板与规避**（接入时注意，不需要现在修）：

- `match` 新增分支必须改工厂代码，不是 SPI 自动发现。可接受——16 个网关不值得引入反射/扫描机制；新增时按第 4 节清单改即可。
- 游戏 type 目前只有 `self` / `third_party` 两种。接入内嵌 H5 游戏需要第三种 type（`embedded`），属于 M5 多游戏聚合的范围，见 [2026-08-31-medium-priority-extensions-plan.md](superpowers/plans/2026-08-31-medium-priority-extensions-plan.md) M5 章节；在此之前内嵌游戏按 `third_party` 处理。
- `self` 与 `embedded` 共用 Provider 时入口区分在 `ProviderController`（SDK 签名 vs 内部调用），不在 Provider 类内。

### 横切能力（所有 Provider 自动享有，接入时直接复用）

| 能力 | 用法 | 说明 |
|---|---|---|
| 熔断 | `CircuitBreaker::call('provider:'.$name, fn)`（`common\CircuitBreaker`，来自 `packages/platform-common`） | 连接失败/5xx/超时计入熔断；key 按 provider 名隔离 |
| 重试分类 | `Retry::isRetryable($e)`（`common\Retry`） | 可重试异常重新抛出计入熔断，不可重试直接返回失败 |
| 本地 mock | `FeatureFlag::isEnabled('provider_mock')` | 开 → 返回 `['success' => true]`，不发真实请求，用于本地联调与 CI |

以上三者已内建在 `ThirdPartyProvider::request()` 中，自建子类只需沿用 `$this->request()` 或复制该模式。

## 2. 接口约定

### 2.1 支付网关 `PaymentGatewayInterface`（`service/app/payment/PaymentGatewayInterface.php`）

```php
interface PaymentGatewayInterface
{
    public function createPayment(DepositOrder $order, PaymentMethod $method): array;
    public function verifyCallback(Request $request): array;
}
```

**`createPayment(DepositOrder $order, PaymentMethod $method)`**

- 输入：充值订单 + 支付方式（`PaymentMethod` 含 `config` JSON，如 `apm_types`）。
- 输出：`['checkout_url' => string, 'transaction_id' => string]`。
- 失败：抛异常（配置缺失抛 `RuntimeException`，接口异常抛 `GuzzleException` 包装）。

**`verifyCallback(Request $request)`**

- 输入：已验签的原始回调报文（签名校验由 `PaymentController` 完成，网关类只解析）。
- 输出：`['valid' => bool, 'order_no' => string, 'transaction_id' => string, 'amount' => string, 'status' => string]`。
- `status` 取值：`success` = 已支付入账；`failed` = 支付失败；`ignored` = 网关事件无需处理（如 `charge:created`），控制器直接回 200 防网关重试。
- 事件型网关（Stripe）对非目标事件返回 `valid=true` + `status=ignored` + 空 `order_no`。

**Webhook 入口**：单一路由 `POST /api/payment/callback?provider=<vendor>`。新增网关必须：

1. 加入 `PaymentController::ALLOWED_PROVIDERS` 白名单（`service/app/api/v1/controller/PaymentController.php:39`），否则 403。
2. 在 `PaymentController::callback()` 中加对应验签分支（见 2.3）。
3. 配置缺失一律 **fail-closed**：密钥未配置即拒绝回调（参照 `verifyStripeSignature` / `verifyNowPaymentsSignature` 的「未配置密钥时拒绝一切回调」注释）。

**配置来源**：环境变量（`getenv`），约定 `<VENDOR>_*` 命名，如 `STRIPE_SECRET_KEY`、`PAYTM_MID`、`PAYTM_KEY`、`NOWPAYMENTS_IPN_SECRET`、`COINBASE_COMMERCE_WEBHOOK_SECRET`、`CALLBACK_TRUSTED_IPS`。不新增 env 文件、不走 `PlatformConfig`（当前 16 个网关全部是 env 读取）。

### 2.2 游戏 Provider `GameProvider`（`service/app/provider/GameProvider.php`）

抽象方法 6 个：

```php
abstract public function getBalance(int $userId, int $gameId, int $currencyId): string;
abstract public function bet(int $userId, int $gameId, int $currencyId, string $sessionId, string $amount, string $roundId, array $meta = []): array;
abstract public function settle(int $userId, int $gameId, int $currencyId, string $sessionId, string $amount, string $roundId, array $meta = []): array;
abstract public function refund(int $userId, int $gameId, int $currencyId, string $sessionId, string $amount, string $roundId, string $reason): array;
abstract public function rollback(int $userId, int $gameId, int $currencyId, string $sessionId, string $roundId): array;
abstract public function verifySignature(array $payload, string $signature): bool;
```

**返回数组字段约定**（`ProviderController` 与 C 端都依赖这些字段，缺一不可）：

| 字段 | 类型 | 含义 | 出现场景 |
|---|---|---|---|
| `success` | bool | 操作是否成功 | 所有方法 |
| `transaction_id` | string | 交易 ID（通常 = `roundId`） | 所有方法 |
| `balance_after` | string | 操作后余额（8 位小数字符串） | 所有方法 |
| `win_amount` | string | 结算赢额 | `settle` / `refund` |
| `already_processed` | bool | 该 round 已处理过（幂等命中） | `settle` / `refund` 重放 |
| `error` | string | 失败原因 | 失败时 |

- `getBalance` 返回**纯字符串余额**，不是数组。
- 金额一律字符串、8 位小数精度（`bc*` 函数运算），禁止 float。
- `self` 类型实现参考 `SelfProvider`：`bet` 余额不足返回 `success=false` + `error`；`settle` 通过 `WalletService::mutate` 信用入账，账户缺失时隐式建户。

**幂等约定（接入第三方游戏必须严格遵守）**：

- **`round_id` 是唯一幂等键**。
- `settle` / `refund` 对同一 `round_id` 重复调用必须返回 `already_processed=true` 且**不重复入账**。`SelfProvider` 已按此实现（`GamePlayLog` 中同 `round_id` 已有 `settle`/`refund` 行即跳过入账，`service/app/provider/SelfProvider.php:52`）。
- 注意：`bet` 无幂等保护（下注即扣款，重放会重复扣款）。第三方游戏必须在自身侧保证 bet 的 `round_id` 去重。
- `refund` 语义 = 负向结算，可委托 `settle` 实现（`SelfProvider` 即如此）。

**签名协议（HMAC-SHA256）**：

- 签名串：`{game_id}:{timestamp}:{METHOD}:{path}:{bodyJson}`
- `bodyJson` = `json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)`
- 密钥 = `game.api_secret`
- 请求头：`X-Game-Id` / `X-Timestamp` / `X-Signature`
- 出站签名：`GameProvider::signRequest($method, $path, $body)`（`GameProvider.php:33`）
- 入站验签：`ProviderAuth` 中间件（`service/app/middleware/ProviderAuth.php`）+ `ThirdPartyProvider::verifySignature()`

**游戏回调入口**：`POST /api/provider/balance|bet|settle|refund`，全部走 `ProviderAuth` 中间件：

1. 三个头缺一 → 401。
2. `X-Game-Id` 对应的游戏不存在或 `status != 1` → 401。
3. **时间戳窗口：`abs(time() - X-Timestamp) > 300` 秒 → 401「Request expired」**（防重放，已实现）。
4. HMAC 不匹配 → 401「Invalid signature」。
5. 通过后 `$request->game` / `$request->gameId` 注入控制器。

### 2.3 支付 webhook 验签现状与新增要求

| 网关 | 验签方式 | 时间戳窗口 |
|---|---|---|
| stripe | `Stripe-Signature` header，HMAC-SHA256 over `t={ts}.{body}`，密钥 `STRIPE_WEBHOOK_SECRET` | 有，±300s（`verifyStripeSignature`） |
| paypal | 回调 PayPal `verify-webhook-signature` API 验签，需 `PAYPAL_WEBHOOK_ID` | 网关侧 |
| nowpayments | `X-NowPayments-Sig` HMAC-SHA512 over raw body，密钥 `NOWPAYMENTS_IPN_SECRET` | 无（依赖网关签名 + 幂等） |
| coinbase | `X-CC-Webhook-Signature` HMAC-SHA256 over raw body，密钥 `COINBASE_COMMERCE_WEBHOOK_SECRET` | 无 |
| mpesa | **无签名**，必须配置 `CALLBACK_TRUSTED_IPS` 来源 IP 白名单才允许该渠道，否则 fail-closed 403 | 无（依赖来源 IP + 幂等） |
| paytm | 回调 NVP checksum 验签（`verifyNvp`） | 无 |
| toss | 服务端回查 + 金额比对 | 无 |
| 其余 | 依赖网关侧签名 / 可信来源 IP + 金额比对 + 幂等 | 无 |

**新增支付网关的必做检查项**：回调报文必须携带可校验的防重放要素——优先实现网关签名协议内的时间戳窗口（参照 Stripe 的 ±300s 检查）；协议不提供时间戳的，必须在接入文档中明确「依赖网关签名 + 订单幂等键防重放」并落实到校验代码（`already processed` 分支已存在，`PaymentController.php:122`）。

## 3. 测试策略

- **本地/CI mock**：`FeatureFlag::isEnabled('provider_mock')` 开启后，`ThirdPartyProvider` 不发真实请求、返回 `['success' => true]`；验证 `ProviderController` 全链路无需真实厂商。
- **回调失败语义**：游戏回调验签失败一律 401（ProviderAuth），支付 webhook 验签失败一律 403（PaymentController），**均不写任何单据、不改任何余额**。
- **合同测试（必须随接入提交）**：每个新网关/新游戏 Provider 提交一份 contract test，断言 `bet` / `settle` / `refund` / `rollback` / `getBalance` 返回结构字段齐全（上表 6 个字段逐个断言），`settle` 重放断言 `already_processed=true` 且余额不变。

## 4. 接入新支付网关 Step-by-Step

1. [ ] 建 `service/app/payment/<Vendor>Gateway.php`，实现 `PaymentGatewayInterface`，类名 PascalCase；`createPayment` 返回 `checkout_url` + `transaction_id`，`verifyCallback` 返回 `{valid, order_no, transaction_id, amount, status}`。
2. [ ] 配置用环境变量，命名 `<VENDOR>_*`（参照 `STRIPE_SECRET_KEY` / `PAYTM_MID`）；配置缺失 fail-closed。
3. [ ] 在 `GatewayFactory::resolve()` 的 `match` 加一行：`'<vendor>' => new <Vendor>Gateway(),`（唯一必改的存量文件，1 行）。
4. [ ] 加 `PaymentMethod` 记录（管理端支付方式列表，`provider` 字段与 `<vendor>` 一致）+ 将 `<vendor>` 加入 `PaymentController::ALLOWED_PROVIDERS` 白名单。
5. [ ] 在 `PaymentController::callback()` 加验签分支（网关自带签名协议则实现 `verify<Vendor>Signature()`，参照 Stripe 的 ±300s 时间戳检查；协议无时间戳则落实防重放要素说明）+ 实现/复用「回调 provider 与订单支付方式一致」与金额比对（均已存在，勿绕过）。
6. [ ] 提交合同测试 + `provider_mock` 联调通过；有签名的渠道用真实回调报文验签后再上线。

**共 6 步，改动存量文件：`GatewayFactory`（1 行）+ `PaymentController`（白名单 1 行 + 验签分支）。**

## 5. 接入新游戏 Provider Step-by-Step

1. [ ] 建 `game_game` 记录：`type='third_party'`、`api_endpoint`、`api_secret`、`provider_config`（JSON，厂商特有字段如 `notify_url`、`region`）。
2. [ ] 协议是标准 HTTP 回调（字段名/签名与本规范一致）→ 直接用 `ThirdPartyProvider`，**零新代码**，跳过 3。
3. [ ] 协议非标准（字段名不同、签名算法不同、需要轮询而非回调）→ 建 `service/app/provider/<Vendor>Provider.php` 继承 `GameProvider`，只重写差异方法（通常只重写 `verifySignature` 与请求封装），沿用 `$this->request()` 的熔断/重试/mock 模式。
4. [ ] 游戏类型不属于 self/third_party（如内嵌 H5、Unity SDK）→ 属于 M5 范围，先按 `third_party` 接入并注释 TODO，不要自行扩 type。
5. [ ] 本地开 `provider_mock` 验证 `ProviderController` 全链路（balance→bet→settle→refund），确认 `GamePlayLog` 落行后再切真实 endpoint。
6. [ ] 提交 contract test（6 方法字段断言 + `settle` 重放幂等断言）+ 联调对账单。

**只新增 1 个 Provider 类，不改存量文件。**

## 6. 验签工具

`scripts/verify-signature.php` 独立可运行（无需框架），实现本规范签名协议，可生成签名、验证签名、检测篡改与过期时间戳：

```bash
# 生成签名（打印可直接使用的请求头）
php scripts/verify-signature.php sign --game-id 1001 --secret your_api_secret \
  --method POST --path /api/provider/settle --body '{"user_id":1,"amount":"10.00000000","round_id":"r-001"}'

# 验证签名（可加 --timestamp 指定时间戳；exit 0=有效, 1=无效）
php scripts/verify-signature.php verify --game-id 1001 --secret your_api_secret \
  --method POST --path /api/provider/settle --body '{"user_id":1,"amount":"10.00000000","round_id":"r-001"}' \
  --timestamp 1750000000 --signature <hex>

# 自检（篡改 / 过期时间戳均返回非零）
php scripts/verify-signature.php
```

**curl 调用示例**（游戏侧调用平台）：

```bash
TS=$(date +%s)
SIG=$(php scripts/verify-signature.php sign --game-id 1001 --secret your_api_secret \
  --method POST --path /api/provider/settle \
  --body '{"user_id":1,"currency_id":1,"session_id":"s-9","amount":"10.00000000","round_id":"r-001"}' \
  --timestamp "$TS" | sed -n 's/^X-Signature: //p')

curl -X POST https://your-platform.com/api/provider/settle \
  -H "X-Game-Id: 1001" -H "X-Timestamp: $TS" -H "X-Signature: $SIG" \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"currency_id":1,"session_id":"s-9","amount":"10.00000000","round_id":"r-001"}'
```

**签名串格式（务必与平台实现一致）**：

```
{game_id}:{timestamp}:{METHOD}:{path}:{bodyJson}
```

- `METHOD` 大写；`path` 含路径不含 query（如 `/api/provider/settle`）；`bodyJson` 用 `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` 编码。
- 验签侧平台使用原始 body 字符串（`rawBody`），若发送方 json_encode 参数与 `signRequest` 相同（同一 flags），两侧结果一致；字段顺序不同会导致签名不同——按 `signRequest` 的编码方式组装。
- 时间戳窗口 ±300 秒，超出即拒绝。

## 7. 验收自查

- [ ] 覆盖两套工厂（GatewayFactory / ProviderFactory）、两个接口（PaymentGatewayInterface / GameProvider）、签名协议、幂等约定、熔断/重试/mock 三个横切能力。
- [ ] 照第 4 节接入一个假网关：改动仅限 `GatewayFactory` 1 行 + `PaymentController` 白名单与验签分支。
- [ ] 照第 5 节接入一个假游戏（非标准签名）：只新增 1 个 Provider 类。
- [ ] `scripts/verify-signature.php` 独立运行，能验出篡改与过期时间戳（自检模式返回非零）。
- [ ] 每个步骤可勾选，无「参考源码自行实现」类模糊表述。
