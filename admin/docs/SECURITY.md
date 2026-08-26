# 安全架构设计文档
<!-- lang-nav -->

Languages: **中文** · [English](SECURITY.en.md) · [한국어](SECURITY.ko.md) · [Русский](SECURITY.ru.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Español](SECURITY.es.md) · [Português](SECURITY.pt.md) · [हिन्दी](SECURITY.hi.md) · [العربية](SECURITY.ar.md) · [বাংলা](SECURITY.bn.md) · [Bahasa Indonesia](SECURITY.id.md) · [日本語](SECURITY.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 纵深防御全景

系统采用 7 层纵深防御模型，从外到内层层过滤恶意请求，确保任意单层失效时仍有后续防线兜底。

整个中间件链按以下顺序执行（见 `config/middleware.php`）：

```
请求 → Cors → SecurityFilter → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| 层 | 中间件/机制 | 防护目标 |
|----|--------|---------|
| 1 | SecurityFilter | XSS / SQL 注入 / 路径遍历 / 命令注入 / CSRF 攻击拦截 |
| 2 | Cors | 跨域安全 + 响应安全头注入 |
| 3 | RateLimit | Redis 滑动窗口限流，防暴力破解 |
| 4 | AdminAuth | JWT 认证 + 黑名单登出 |
| 5 | AdminPermission | RBAC method.path 粒度鉴权 |
| 6 | OperationLog | 操作审计 + 来源端追踪 |
| 7 | 数据加密 | Hashids ID 混淆 + Encryptable DB 加密 + EncryptionService 传输加密 |

前端三层（Flutter）另有独立的输入校验，后端不做信任，每一层独立防御。

---

## 2. 攻击检测引擎

### 2.0 HTTP 方法限制

SecurityFilter 在所有攻击检测之前首先校验 HTTP 方法，仅允许以下标准方法：

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

非标准方法（如 TRACE、CONNECT、PATCH、自定义方法等）直接返回 **405 Method Not Allowed**，响应体为空 HTML，不进入后续攻击检测或业务逻辑。

这是纵深防御的第一道防线，有效阻止：
- TRACE 跨站追踪攻击（XST）
- CONNECT 隧道代理滥用
- 非标准 WebDAV 方法探测
- 自动化扫描器的 HTTP 方法枚举

### 2.1 XSS 跨站脚本

所有正则来自 `SecurityFilter::PATTERNS['XSS']`，大小写不敏感匹配。

| 检测模式 | 正则 | 防御的攻击 |
|----------|------|-----------|
| 脚本标签 | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` 等带空格变体 |
| 事件属性 | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | `onclick="javascript:..."` 等内联事件 |
| JS 伪协议 | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` 等 |
| Data URI XSS | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` 等 |
| 模板注入 | `\{\{.*?\}\}` | `{{constructor}}`, `{{7*7}}` 等服务端/Angular/Vue 模板注入 |

### 2.2 SQL 注入

| 检测模式 | 正则 | 防御的攻击 |
|----------|------|-----------|
| UNION 联合查询 | `\bUNION\s+(?:ALL\s+)?SELECT\b` | `UNION SELECT`, `UNION ALL SELECT` 脱裤 |
| OR 恒真注入 | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| 表结构破坏 | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| 存储过程调用 | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | MSSQL 扩展存储过程命令执行 |
| 元数据探测 | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | MySQL/PG/SQLite/MSSQL 数据库结构探测 |
| 注释绕过 | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | `'-- OR SELECT`, `'# AND UPDATE` 注释绕过 |

### 2.3 路径遍历

| 检测模式 | 正则 | 防御的攻击 |
|----------|------|-----------|
| 目录回溯 | `\.\.[\/\\\\]{2,}` | `../`, `..\`, `....//` 多级目录回溯 |
| 敏感文件探测 | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` 等 |
| 空字节截断 | `%00` | `../../../etc/passwd%00.jpg` 绕过扩展名校验 |

### 2.4 命令注入

| 检测模式 | 正则 | 防御的攻击 |
|----------|------|-----------|
| 管道/分号命令 | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| 反引号替换 | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| $() 替换 | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| 远程下载管道 | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF 跨站请求伪造

校验逻辑在 `SecurityFilter::checkCsrf()` 中实现：

```php
// 仅 POST/PUT/DELETE 触发校验
// Origin 头和 Referer 均为空 → 放行（非浏览器客户端）
// Origin 非空 → 解析 Origin 域名与 Host 比对
```

比对规则：
- 移除 Host 的 `www.` 前缀后与 Origin 的域名精确比较
- 若 Host 是 Origin 的父域名（如 `Origin: app.example.com`, `Host: example.com` — 触发 `str_contains($originHost, '.' . $hostOnly)`），放行
- 既不精确匹配也不是子域名 → 返回 403，判定为 CSRF 攻击

注意：非浏览器客户端（如 curl 不带 Origin/Referer）会直接放行，CSRF 保护仅对浏览器环境有效。

### 2.6 恶意文件上传

| 检测模式 | 正则 | 防御的攻击 |
|----------|------|-----------|
| 双扩展名伪装 | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` 绕过白名单 |
| PHP 扩展名 | `\.php\s*$/m` | 请求参数中直接传递 `.php` 路径 |

---

## 3. 攻击升级与 IP 黑名单

SecurityFilter 内置攻击升级机制，防止同一 IP 持续扫描攻击。

### 升级流程

```
第 1 次扫描命中 → Redis INCR security_escalate:{ip} = 1, TTL=60s
第 2 次扫描命中 → INCR → 2
...
第 5 次扫描命中 → INCR → 5
    → 触发封禁: SETEX security_ban:{ip} 900 1
    → 清除计数器 DEL security_escalate:{ip}
    → 写入安全日志: [SECURITY] IP banned 15min
```

### 封禁期间行为

每个请求进入 SecurityFilter 时首先检查 `isBanned()`：

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

被封 IP 在 15 分钟内所有请求（包括合法请求）直接返回 403，完全跳过后续业务逻辑。

### 配置常量

| 常量 | 值 | 含义 |
|------|-----|------|
| ESCALATE_LIMIT | 5 | 60 秒窗口内触发次数阈值 |
| ESCALATE_WINDOW | 60 | 计数器窗口（秒） |
| BAN_DURATION | 900 | 黑名单持续时间（秒），即 15 分钟 |

### 安全日志

文件位置：`runtime/logs/security.log`

日志格式示例：
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### 请求体大小限制

`Content-Length > 10MB` 直接返回 413 Payload Too Large，防 DoS 超大请求体攻击。

### Content-Type 校验

POST/PUT 请求**必须**声明 `Content-Type` 为 `application/json` 或 `application/x-www-form-urlencoded`，否则返回 415 Unsupported Media Type。文件上传请求（带 file 字段）跳过此检查。

---

## 4. 响应安全头

所有头在 `Cors` 中间件中注入，通过 `$response->withHeaders()` 追加到每个响应。

| 头 | 值 | 作用 |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | 允许任意源跨域（内网管理后台场景） |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | 允许的方法集合 |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | 允许的自定义头 |
| Access-Control-Max-Age | `86400` | 预检请求缓存 24 小时 |
| X-Content-Type-Options | `nosniff` | 禁止浏览器 MIME 嗅探 |
| X-Frame-Options | `DENY` | 禁止所有 iframe 嵌入，防点击劫持 |
| X-XSS-Protection | `1; mode=block` | 启用浏览器内置 XSS 过滤器并拦截页面渲染 |
| Referrer-Policy | `strict-origin-when-cross-origin` | 同源发完整 URL，跨域仅发域名 |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | 全站禁用摄像头/麦克风/定位 API |

OPTIONS 预检请求直接返回 204 空响应，不进入后续中间件链。

### 4.2 Content-Security-Policy (CSP)

与其他安全头一起在 Cors 中间件中注入，提供深度防御，限制浏览器可加载和执行的资源来源。

| 头 | 值 | 作用 |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | 限制脚本/样式/图片/连接/框架/表单等资源来源 |
| X-Permitted-Cross-Domain-Policies | `none` | 禁止 Adobe Flash/PDF 等跨域策略文件加载 |

CSP 策略要点：
- `default-src 'self'`：默认仅允许同源资源
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`：允许同源脚本 + 内联脚本（Flutter Web 必需）+ eval（Flutter Web 调试必需）
- `frame-ancestors 'none'`：禁止被任何页面 iframe 嵌入，与 X-Frame-Options: DENY 双保险
- `base-uri 'self'`：限制 `<base>` 标签只能指向同源
- `form-action 'self'`：限制表单只能提交到同源

---

## 5. 限流策略

### 算法

Redis Sorted Set 滑动窗口 + Lua 原子化脚本，关键操作：

```lua
-- 1. 清理窗口外的旧记录
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. 检查当前窗口计数
local count = redis.call('ZCARD', KEYS[1])
-- 3. 超限则返回 {0, count}，未超限则 ZADD 并返回 {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- 随机后缀避免同毫秒覆盖
redis.call('EXPIRE', KEYS[1], window + 10)
```

Lua 脚本在 Redis 服务端单线程执行，**天然原子化**，消除 TOCTOU（Time-of-check to Time-of-use）竞态条件。

### 限流配置

| 路由 | 限制 | 窗口 | 场景 |
|------|------|------|------|
| 默认（所有路由） | 60 次/分钟 | 60s | 通用 API |
| `/api/auth/login` | 10 次/分钟 | 60s | 登录（防暴力破解） |
| `/api/auth/register` | 5 次/分钟 | 60s | 注册（防批量注册） |

### 响应头

触发限流时返回 HTTP 429 及 JSON body：
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

所有响应（含正常响应）携带以下头：

| 头 | 说明 |
|----|------|
| X-RateLimit-Limit | 当前窗口允许的最大请求数 |
| X-RateLimit-Remaining | 当前窗口剩余可用请求数 |
| X-RateLimit-Reset | 窗口重置的 Unix 时间戳 |
| Retry-After | 仅限流时携带，建议等待秒数 |

### 降级策略

Redis 异常（连接超时、不可用等）时 **fail-closed**：

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    // Redis down: 限流不可用即拒绝，避免安全防线失效（登录/支付回调限流为空转）
    return json(['code' => 503, 'message' => '服务暂不可用，请稍后再试', 'data' => []])
        ->withStatus(503)->withHeaders(['Retry-After' => '5']);
}
```

限流是登录防暴力破解、支付回调防重放的第一道安全防线，Redis 故障时宁可拒绝请求（503），也不放行。

### 5.4 账号锁定机制

登录接口在速率限制的基础上，额外增加了**账号锁定**机制，防止针对特定用户的定向暴力破解。

**锁定流程**：

```
登录失败 → Redis INCR account_lockout:{userId} TTL=900s
连续 5 次失败 → Redis SETEX account_locked:{userId} 900 1
            → 返回 429 "账号已被锁定，请15分钟后再试"
            → 清除计数器 DEL account_lockout:{userId}
```

**锁定期间行为**：

锁定期间所有登录请求直接返回 429，不进行密码校验，完全阻止暴力破解尝试。

**配置常量**：

| 常量 | 值 | 含义 |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | 最大连续失败次数 |
| LOCKOUT_DURATION | 900 | 锁定持续时间（秒），即 15 分钟 |

注意：账号锁定基于 `userId` 而非 IP，因此攻击者更换 IP 无法绕过锁定。与 IP 限流（10次/分钟）叠加形成双重防护：
- IP 层面：10 次/分钟限流阻止分布式暴力破解
- 账号层面：5 次失败锁定阻止定向暴力破解

---

## 6. 认证与鉴权

### 6.1 JWT 认证

AdminAuth 中间件实现，挂载在需要认证的路由组上。

**参数配置**（`config/plugin/erikwang2013/jwt/jwt`，由 `.env` 注入）：

| 参数 | 值 | 说明 |
|------|-----|------|
| 算法 | HS256 | HMAC-SHA256 对称签名 |
| 密钥 | `JWT_SECRET_KEY` | 环境变量注入，缺失或仍为默认值时**拒绝启动**（fail-closed） |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_TTL` |
| 签发者 | `open-admin` | `JWT_ISSUER` |
| 受众 | `open-admin` | `JWT_AUDIENCE` |

**Token 提取**：从 `Authorization: Bearer <token>` 头提取，strip `Bearer ` 前缀得到原始 JWT。

**认证流程**：
1. 空 token → 直接 401 `{"code": 401, "message": "未登录"}`
2. 检查 Redis 黑名单 `jwt_blacklist:{md5(token)}` → 命中 → 401 `Token已失效，请重新登录`
3. JWT decode → 失败（过期/签名不匹配） → 401 `Token已过期或无效`
4. 成功 → 注入 `$request->adminId` 和 `$request->adminUsername`

**黑名单机制**：用户登出时，将 `md5(token)` 写入 Redis，TTL 设为 JWT 剩余有效期。Redis 故障时黑名单检查被跳过（fail-open），此时已登出的 Token 仍可短期使用，但 JWT 本身的短期有效期（2h）作为兜底保护。

**Token 刷新**：`POST /api/auth/refresh` 校验原 refresh token（`token_type=refresh` 且未过期/未拉黑）后才轮换签发，并校验 `sub` 必须为有效用户 ID —— **不再签发 sub=null 的 refresh token**，刷新失败直接返回 401。

### 6.2 并发会话限制

为防止 Token 泄露后被多设备滥用，系统限制同一用户同时持有的有效 Token 数量。

**限制逻辑**：

```
登录成功 → 签发新 Token
         → 查询当前用户有效 Token 数量: Redis SCARD user_tokens:{userId}
         → 若数量 >= 3（MAX_CONCURRENT_SESSIONS）:
            → 按创建时间升序排列，移除最旧的 Token:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → 将新 Token 加入集合: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**配置常量**：

| 常量 | 值 | 含义 |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | 同一用户最大并发 Token 数 |

**被挤下线场景**：当用户在第 4 台设备登录时，第 1 台设备的 Token 被强制加入黑名单，后续请求返回 401 "Token已失效，请重新登录"。

登出时，当前 Token 从集合中移除。Token 自然过期时，Redis key 自动失效，集合成员随之减少。

### 6.3 RBAC 权限模型

AdminPermission 中间件实现。

**数据模型**：User -> Role -> Permission 三层关联

- `erik_admin_user` (用户表)
- `erik_admin_user_role` (用户-角色关联表)
- `erik_admin_role` (角色表)
- `erik_admin_role_permission` (角色-权限关联表)
- `erik_admin_permission` (权限表)

**权限类型**：
| type | 含义 | 示例 |
|------|------|------|
| 1 | 菜单权限 | 控制左侧导航可见性 |
| 2 | 按钮权限 | 控制页面内操作按钮 (新增/编辑/删除) |
| 3 | API 权限 | 控制后端接口调用 |

API 权限标识格式：`{method}.{path}`

例如：
- `post.admin/user` — 创建用户
- `put.admin/user` — 编辑用户
- `delete.admin/user` — 删除用户
- `get.admin/user` — 查看用户列表

**鉴权流程**：
1. `$request->adminId` 为空（未登录）→ 直接 401 `{"code": 401, "message": "未登录"}`，不再放行
2. 获取用户 → 角色（跳过 `status=0` 的禁用角色）→ 权限列表
3. 超级管理员（`slug = '*'`）→ 直接放行
4. 构造 `strtolower(method) . '.' . trim(path, '/')` → 对比权限列表
5. 匹配失败 → 403 `{"code": 403, "message": "无权限访问"}`

**二次确认**：BaseController 提供 `confirmPassword()` 方法，敏感操作（删除用户、数据导出等）在 Controller 层额外要求输入当前密码，防止会话劫持后被未授权操作。

### 6.4 支付回调验签（fail-closed）

`POST /api/payment/callback`（Stripe/PayPal 充值回调）验签采用 **fail-closed**，任何配置缺失或校验异常均拒绝回调：

| 场景 | 行为 |
|------|------|
| Stripe 未配置 `STRIPE_WEBHOOK_SECRET` | 拒绝（403），不再接受无签名回调 |
| Stripe 签名缺失 / 验签失败 | 拒绝（403） |
| Stripe 时间戳 `t=` 缺失或与服务器时间差 **> ±5 分钟** | 拒绝（403），防重放 |
| PayPal 未配置 `PAYPAL_WEBHOOK_ID` | 拒绝（403） |
| PayPal 回查验证异常 / 非 SUCCESS | 拒绝（403） |
| 可选 `CALLBACK_TRUSTED_IPS` 配置后来源 IP 不在白名单 | 拒绝（403） |
| 回调 provider 与订单支付方式不一致 / 支付方式不存在 | 拒绝（403） |

回调入账（状态更新 + 余额 + 流水）在同一数据库事务内完成，任一步失败整体回滚，防止半入账。

---

## 7. 审计日志

### 7.1 操作日志

OperationLog 中间件对 POST / PUT / DELETE 请求自动记录操作日志。GET 请求不记录。

**记录字段**：

| 字段 | 来源 | 说明 |
|------|------|------|
| id | SnowflakeService::generate() | 全局唯一 ID |
| user_id | `$request->adminId` | 操作者 ID，未登录为 0 |
| action | `$request->method()` | 等同于 method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | 请求路径 |
| ip | `$request->getRealIp()` | 客户端真实 IP |
| source | detectSource() | 客户端来源平台 |
| input | 请求 body（脱敏后 JSON） | 操作提交数据 |
| created_at | `date('Y-m-d H:i:s')` | 操作时间 |

**敏感字段过滤**：递归遍历请求体，以下字段的值替换为 `***`：

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**来源端检测**（`detectSource()`）：按优先级：

1. 优先读取 `X-Client-Platform` 自定义头（原生客户端显式声明）
2. 降级到 User-Agent 字符串推断（`detectSource()` 方法检测顺序）：

| 平台 | UA 关键字 |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | 兜底默认值 |

**容错**：日志写入异常不阻塞业务请求（`catch (\Throwable)` 静默吞掉）。

### 7.2 安全日志

**文件位置**：`runtime/logs/security.log`

**记录内容**：
- 攻击拦截日志：攻击类别、IP、路径、字段、来源、payload 片段（前 200 字符）
- IP 封禁通知：被封 IP、触发次数

日志权限为 `FILE_APPEND | LOCK_EX`，确保并发安全写入。

---

## 8. 数据保护

系统采用三层数据保护策略，对应数据流转的三个阶段。

### 8.1 传输层 — EncryptionService

`EncryptionService` 使用 `erikwang2013/encryption` 包，对 API 请求/响应中的敏感字段进行加解密。

**技术细节**：
- 算法：`aes-256-cbc-hmac`（自带 HMAC 签名防篡改）
- 密钥：`ENCRYPTION_KEY` 环境变量，自动对齐到 32 字节
- 用于：客户端与 API 之间传输手机号、身份证号等字段

**脱敏工具方法**：
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com`（用户名超 2 字符）或 `a**@example.com`

### 8.2 存储层 — Encryptable Cast

`AdminUser` 模型使用 `Erikwang2013\Encryptable\Encryptable` Eloquent cast，对应字段：

- `email` → cast 为 Encryptable，自动加解密
- `phone` → cast 为 Encryptable，自动加解密  
- `id_card` → cast 为 Encryptable，自动加解密

写入数据库时自动加密为密文，读取时自动解密为明文。数据库存储列类型为 `VARCHAR(500)`，密文以 base64 形式存储。

**密钥体系**：与传输层加密（`ENCRYPTION_KEY`）独立使用 `ENCRYPTABLE_KEY`，一个密钥泄露不会导致另一层失效。

密钥轮换：`ENCRYPTION_PREVIOUS_KEYS` 环境变量支持历史密钥列表（逗号分隔），读取旧数据时尝试历史密钥解密，写回时使用当前密钥重新加密。

### 8.3 展示层 — ID 混淆与脱敏

**Hashids ID 混淆**：`HashidsService` 使用 `erikwang2013/hashids` 包。

- 对外 API 返回的数据库 BIGINT ID 编码为 hash 字符串（如 `xK3mN9qR2pL7wV8b`）
- 客户端请求时传入 hash 字符串，后台自动解码为原始 ID
- 盐值 `HASHIDS_SALT` 环境变量注入，盐值不同则编解码结果完全不同
- hash 最小长度 16 位，使用 62 位字母数字字符集
- BaseController 提供 `encodeId()`, `decodeId()`, `encodeIds()` 便捷方法

**导出脱敏**：Excel/PDF 导出时（ExportController），敏感字段统一脱敏：
- 手机号：`138****1234`
- 邮箱：`a***@example.com`
- 身份证：完全遮盖为 `********`

---

## 9. 密钥管理

所有密钥通过 `.env` 环境变量注入，配置文件使用 `getenv()` 读取并内置兜底默认值（仅开发环境安全）。

| 环境变量 | 用途 | 包 | 生产要求 |
|----------|------|-----|---------|
| JWT_SECRET_KEY | JWT 签名密钥 | erikwang2013/jwt-webman | 64+ 字符随机字符串；缺失或默认值拒绝启动 |
| JWT_ALGORITHM | JWT 签名算法 | 同上 | 保持 HS256 |
| HASHIDS_SALT | ID 编码盐值 | erikwang2013/hashids | 随机字符串 |
| SNOWFLAKE_DATACENTER_ID | 数据中心 ID (0-31) | erikwang2013/snowflake-php | 单机房保持默认 |
| ENCRYPTION_KEY | API 传输层加密密钥 | erikwang2013/encryption | 32 字节随机字符串 |
| ENCRYPTABLE_KEY | DB 存储层加密密钥 | erikwang2013/encryptable | 32 字节随机字符串，与传输密钥不同 |

**安全要求**：
- `.env` 文件已加入 `.gitignore`，严禁提交到版本库
- `.env.example` 是公开模板文件，不包含真实密钥
- 生产环境**必须**更换所有默认密钥为随机字符串
- 建议使用 `openssl rand -base64 32` 生成密钥

### 密钥存储隔离

| 层 | 配置键 | 密钥环境变量 |
|----|--------|-------------|
| 传输加密 | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| 存储加密 | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| ID 混淆 | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| JWT 签名 | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET_KEY` |

---

## 10. security.txt (RFC 9116)

系统在 `/.well-known/security.txt` 提供符合 RFC 9116 标准的安全联系信息端点，方便安全研究人员在发现漏洞时快速找到报告渠道。

**访问方式**：

```
GET /.well-known/security.txt
```

**响应内容**：

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**字段说明**：

| 字段 | 说明 |
|------|------|
| Contact | 安全漏洞报告联系方式 |
| Expires | 文件过期时间，需定期更新 |
| Preferred-Languages | 首选沟通语言 |
| Canonical | 此文件的规范 URL |
| Policy | 安全策略/漏洞披露政策链接 |

该端点不受限流、认证等中间件限制，任何人都可直接访问。

---

## 11. Nginx 安全配置

项目提供 `docs/nginx-security.conf` 作为生产环境 Nginx 反向代理的安全加固参考配置。

**包含的安全措施**：

| 配置项 | 作用 |
|--------|------|
| `server_tokens off` | 隐藏 Nginx 版本号 |
| `client_max_body_size 10m` | 限制请求体大小，与 SecurityFilter 协同 |
| `limit_req_zone` | Nginx 层面的请求频率限制 |
| `limit_conn_zone` | 并发连接数限制 |
| `add_header` 安全头 | 在 Nginx 层面追加 X-XSS-Protection 等安全头 |
| `if ($request_method)` | Nginx 层面拒绝非标准 HTTP 方法 |
| SSL/TLS 配置 | 现代 TLS 1.2/1.3 配置，禁用弱加密套件 |
| 隐藏后端头 | `proxy_hide_header` 移除 webman 版本等敏感头 |

**使用方式**：将 `docs/nginx-security.conf` 中的配置合并到您的 Nginx server 块中，根据实际域名和证书路径调整。

---

## 12. 威胁模型

### 12.1 已防护威胁

| 威胁类型 | 攻击向量 | 防御层次 |
|----------|---------|---------|
| HTTP 方法滥用 | TRACE/TRACK XST 攻击、CONNECT 隧道代理、WebDAV 方法探测 | SecurityFilter 405 方法白名单 (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| 定向暴力破解 | 针对特定用户反复尝试密码 | 账号锁定 (5次失败锁定15分钟) + RateLimit (登录 10/min) + Captcha |
| 暴力破解 | 分布式 IP 反复尝试用户名/密码 | RateLimit (登录 10/min) + Captcha |
| XSS 跨站脚本 | `<script>`, onerror, javascript: | SecurityFilter (5 种模式) + X-XSS-Protection 响应头 + CSP |
| SQL 注入 | UNION SELECT, OR 1=1, 注释绕过 | SecurityFilter (6 种模式) + Eloquent ORM 参数化查询 |
| CSRF 跨站请求伪造 | 恶意网站代发请求 | SecurityFilter Origin/Referer 校验 |
| 路径遍历 | `../../etc/passwd` | SecurityFilter 路径遍历模式 + UploadController 扩展名白名单 |
| 命令注入 | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 种模式) |
| 会话劫持 | 窃取 JWT Token | JWT 短期有效 (2h) + 黑名单登出 + 敏感操作二次密码确认 |
| ID 枚举 | 遍历数字 ID 猜测数据量 | Hashids 混淆为随机字符串 |
| 数据泄露 | DB 拖库 / 中间人 / 日志泄露 | 三层加密/脱敏 + OperationLog 敏感字段过滤 |
| DoS 攻击 | 超大请求体 / 高频请求 | 请求体 10MB 限制 + RateLimit 60/min + IP 黑名单 |
| 权限提升 | 低权限用户访问管理接口 | RBAC method.path 粒度鉴权 |
| 文件上传攻击 | shell.php.png 双扩展名 | SecurityFilter 恶意文件检测 |

### 12.2 已知局限

| 局限 | 影响范围 | 缓解措施 |
|------|---------|---------|
| CSRF 保护仅对浏览器有效 | 非浏览器客户端（curl, Postman, 移动 App）可跳过 Origin/Referer 检查 | 非浏览器客户端天然不受 CSRF 攻击；依赖 JWT 认证替代 Cookie |
| Redis 不可用时限流 fail-closed（503）、黑名单检查 fail-open | 限流期间部分请求被拒绝；已登出 Token 短期可用 | 监控 Redis 可用性告警；JWT 短期有效期作为兜底 |
| 无独立 WAF 引擎 | SecurityFilter 使用 `@preg_match` 正则匹配，非专用 WAF 规则引擎 | 生产环境建议前置 Nginx ModSecurity 或 Cloudflare WAF |
| JWT 无状态无法主动失效 | Token 未过期前无法从服务端主动吊销（除黑名单外） | 黑名单 + 短期 2h TTL 降低风险窗口 |
| IP 黑名单仅内存存储 | Redis 重启后黑名单丢失 | Ban 时长仅 15 分钟，影响有限 |
| 管理员端点无特殊限流 | 管理员接口与普通接口共用 60/min 默认限制 | 管理员操作频率天然低，暂无需区分 |
| `@preg_match` 抑制错误 | 畸形正则输入时静默失效 | `preg_last_error()` 可加监控，当前未实现 |
