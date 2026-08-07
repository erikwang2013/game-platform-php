# 开放管理后台 — 设计文档

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 详细的 Mermaid 架构图请参阅 [ARCHITECTURE.md](ARCHITECTURE.md)（GitHub/GitLab/VS Code 可自动渲染）。

## 1. 系统架构

> **功能清单**：认证(login/register/refresh/logout + 账号锁定 + 会话限制) | 仪表盘(Redis缓存) | 用户CRUD+批量+导入 | 角色权限(RBAC) | 系统配置 | 操作审计(8平台来源端) | 文件(上传+导出+脱敏) | 安全(18层防御) | 运维(health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. 后端架构

### 2.1 分层设计

| 层 | 目录 | 职责 |
|---|------|------|
| 路由 | `config/route.php` | URL 到控制器的映射，中间件绑定，版本化路由 |
| 中间件 | `app/middleware/` | 攻击拦截(SecurityFilter)、限流(RateLimit)、认证(JWT)、授权(RBAC)、API版本(ApiVersion) |
| 控制器 | 30 个：Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs/Metrics/Analytics/Game/Payment/Withdraw... (管理端) + Captcha/Auth (API v1) | 请求参数校验、调用业务逻辑、响应格式化 |
| 业务服务 | `common/service/` | 数据分析：GameDashboardService（总览/排行/趋势）、DepositLogService（营收/转化）、ProbabilityService（联合/条件概率，SQL 构建器）；DB 故障时返回空数据而非报错 |
| 数据模型 | `app/model/` | ORM 映射、关联关系、字段加解密 |
| 公共工具 | `app/common/` | Hashids、Snowflake、Encryption 服务 |

### 2.2 请求生命周期

```
客户端请求
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 匹配
  │
  ▼
中间件链:
  SecurityFilter ──────► HTTP方法检查 → 405 (仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截 (403)
  ▼
  RateLimit ───────────► Redis 滑动窗口限流
  │ (失败返回 429 + Retry-After 头)
  ▼
  ApiVersion ─────────► API-Version 头校验，注入 $request->apiVersion
  │ (失败返回 400)
  ▼
  AdminAuth ──────────► JWT 验证，注入 $request->adminId
  │ (失败返回 401)
  ▼
  AdminPermission ────► RBAC 权限校验（Redis 60s 缓存）
  │ (失败返回 403)
  ▼
  OperationLog ───────► 操作日志记录 (POST/PUT/DELETE)，自动检测来源端
  │
  ▼
Controller::method()
  │
  ├─► 参数验证 (validator)
  ├─► 敏感操作确认 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 操作 (自动 encryptable 加解密)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 ID 生命周期

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 数据加密体系

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-128-ECB，独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. 数据库设计

### 3.1 ER 关系

```
erik_admin_user ──┬── erik_admin_user_role ──┬── erik_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    erik_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    erik_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           erik_operation_log
             (操作日志)

erik_system_config (系统配置) — 独立表
```

### 3.2 核心表结构

| 表名 | 字段数 | 说明 |
|------|-------|------|
| `erik_admin_user` | 14 | 管理用户，phone/email/id_card 加密存储，支持软删除 |
| `erik_admin_role` | 7 | 角色，slug 唯一 |
| `erik_admin_permission` | 10 | 权限树（parent_id 自引用），type: 1=菜单 2=按钮 3=API |
| `erik_admin_user_role` | 2 | 用户-角色多对多中间表 |
| `erik_admin_role_permission` | 2 | 角色-权限多对多中间表 |
| `erik_system_config` | 8 | 键值对配置，group+key 联合唯一 |
| `erik_operation_log` | 9 | 操作审计日志（含 source 来源端） |

### 3.3 主键规范

- 类型: `BIGINT UNSIGNED NOT NULL`
- 特性: **非自增**，由 Snowflake 算法在应用层生成
- 优势: 全局唯一、分布式友好、趋势递增利于索引、不暴露业务量
- 配置: datacenter_id(0-31) + worker_id(0-31)，支持 1024 个节点并发

## 4. API 设计

### 4.1 URL 规范

```
公开接口:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

管理端:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

资源路由:
  GET    /admin/user          → 列表
  POST   /admin/user          → 创建
  GET    /admin/user/{hashid} → 详情
  PUT    /admin/user/{hashid} → 更新
  DELETE /admin/user/{hashid} → 删除（需密码确认）

系统配置:  /admin/config[/{hashid}]
操作日志:  /admin/log
个人中心:  /admin/profile[/password|/logout]
导入:     /admin/import/users
上传:     /admin/upload
批量:     /admin/user/batch/{destroy|status}
文档:     /api/docs     (OpenAPI 3.0)
健康:     /health
```

### 4.2 API 版本策略

API 版本通过请求头控制，**不在 URL 路径中体现**：

```http
API-Version: v1
```

| 机制 | 说明 |
|------|------|
| 默认版本 | 未携带 `API-Version` 头时默认 `v1` |
| 校验 | `ApiVersion` 中间件校验，不支持的版本返回 400 |
| 路由 | `v()` 辅助函数根据版本动态解析控制器类 |
| 目录 | 控制器按版本组织: `app/api/{version}/controller/` |

扩展示例——新增 v2 API：
1. 创建 `app/api/v2/controller/AuthController.php`
2. `ApiVersion` 中间件 `SUPPORTED` 常量添加 `'v2'`
3. 路由定义无需修改

```bash
# 使用 v1
curl -H "API-Version: v1" /api/auth/login

# 使用 v2
curl -H "API-Version: v2" /api/auth/login

# 不传，默认 v1
curl /api/auth/login
```

### 4.3 限流策略

基于 Redis Sorted Set 滑动窗口算法，原子化 Lua 脚本执行：

| 接口 | 限制 |
|------|------|
| 默认 | 60 次/分钟/IP/路由 |
| POST /api/auth/login | 10 次/分钟 |
| POST /api/auth/register | 5 次/分钟 |

超限返回 429，响应头包含 X-RateLimit-Limit / Remaining / Reset / Retry-After。

### 4.4 统一响应

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | 含义 | 触发场景 |
|------|------|---------|
| 0 | 成功 | 正常响应 |
| 400 | 参数错误 | 请求格式不正确 |
| 401 | 未认证 | Token 缺失/过期/无效 |
| 403 | 无权限 | 用户角色不包含所需权限 |
| 404 | 不存在 | 资源未找到 |
| 422 | 验证失败 | 表单参数不符合规则 / 密码确认失败 |
| 500 | 服务端错误 | 未预期异常 |

### 4.5 认证流程（含点击验证码）

```
客户端                               服务端
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② 用户点击图中文字位置              │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 权限模型 (RBAC)

```
  用户 ──┬── 角色 ──┬── 权限
  User     Role      Permission
                 │
                 ├── type=1: 菜单 (控制侧边栏可见)
                 ├── type=2: 按钮 (控制页面内操作)
                 └── type=3: API  (控制接口访问)

  权限标识格式: {method}.{path}
  例: get.admin/user  post.admin/user  delete.admin/user
  超级管理员标识: * (跳过所有权限检查)
```

### 4.7 敏感操作二次确认

删除用户、角色、权限等敏感操作，需要在请求体中传入当前用户密码进行身份复核：

```
客户端                           服务端
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 密码错误返回 422
  │                                │ → 密码正确继续执行
  │◄── 200 { code: 0 }           │
```

前端在触发删除操作前弹出确认对话框，收集用户密码后发送请求。

## 5. 前端设计

### 5.1 Flutter Web 管理后台

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

特性: 侧边栏可折叠、Material 3 双主题、数据表格高密度、弹窗 Dialog、鼠标悬停交互

### 5.2 HarmonyOS 移动端

页面路由:

| 页面 | 路由 | 说明 |
|------|------|------|
| LoginPage | `pages/LoginPage` | 用户名密码 + 点击验证码登录 |
| DashboardPage | `pages/DashboardPage` | 统计卡片 + 最近操作 |
| UserListPage | `pages/UserListPage` | 用户列表，搜索 + 下拉刷新 + 上滑加载 |
| UserDetailPage | `pages/UserDetailPage` | 新增/编辑/查看/删除（AlertDialog 确认） |
| ProfilePage | `pages/ProfilePage` | 个人中心，退出登录（AlertDialog 确认） |

数据流: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. 安全设计

### 6.1 纵深防御

| 层面 | 措施 |
|------|------|
| 方法限制 | SecurityFilter HTTP 方法白名单，仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD，非标准方法返回 405 |
| 攻击拦截 | SecurityFilter 中间件，XSS/SQL注入/路径遍历/命令注入/CSRF 检测拦截 |
| 人机验证 | 点击验证码（Click Captcha），登录/注册强制校验 |
| 账号锁定 | 连续 5 次登录失败锁定账号 15 分钟，锁定期间返回 429 |
| 会话限制 | 同一用户最多 3 个并发 Token，超出时最旧 Token 自动黑名单 |
| 限流 | RateLimit 中间件，Redis 滑动窗口，Lua 原子化 |
| CSP | Content-Security-Policy 头限制资源来源，防 XSS 与数据注入 |
| 操作确认 | 删除等敏感操作需输入当前用户密码二次确认 |
| 传输 | HTTPS + JWT Bearer Token |
| 接口ID | Hashids 加密，外部不可逆推真实 ID |
| 请求体 | AES-256-CBC 敏感字段加密 |
| 数据库 | BIGINT 主键（不暴露自增量） |
| 数据库 | AES-128-ECB 敏感字段加密存储 |
| 认证 | JWT HS256，2h 过期 + refresh token |
| 授权 | RBAC，method.path 粒度权限控制 |
| 审计 | OperationLog 记录所有操作（含来源端 source 自动检测） |

### 6.2 密钥管理

```
JWT_SECRET          → 环境变量注入，64位随机字符串
HASHIDS_SALT        → 唯一盐值，泄漏后需全局更换
ENCRYPTION_KEY      → API 传输加密密钥，32字节
ENCRYPTABLE_KEY     → DB 存储加密密钥，与传输密钥独立
SCOUT_HOSTS         → ES 地址，内网部署
```

### 6.3 敏感数据保护

| 场景 | 字段 | 措施 |
|------|------|------|
| 列表展示 | phone | 脱敏: 138****1234 |
| 列表展示 | email | 脱敏: a***@example.com |
| 详情查看 | phone/email | 需解密接口 |
| 导出Excel | phone/email | 脱敏后导出 |
| 导出PDF | 全字段 | 脱敏 + 不可移除版权水印 |
| 存储 | phone/email/id_card | encryptable 加密为密文 |

## 7. 导出设计

### 7.1 Excel 导出

```
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 PDF 导出

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. 部署架构

### 8.1 推荐拓扑

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                    静态文件: Flutter Web build/
```

### 8.2 Docker Compose（推荐生产环境）

项目根目录的 `docker-compose.yml` 编排了上述拓扑的全部服务：

| 服务 | 镜像/构建 | 端口 | 说明 |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | 反向代理 + 静态文件 + Gzip |
| `app` | 本地 `Dockerfile` 构建 | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | 主数据库，数据卷持久化 |
| `redis` | redis:7-alpine | 6379 | 缓存 / 限流 / 验证码 |
| `elasticsearch` | elasticsearch:8.x | 9200 | 全文检索 |

启动前将 `docker-compose.yml` 中的 `JWT_SECRET`、`HASHIDS_SALT`、`ENCRYPTION_KEY` 等密钥替换为随机字符串。

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

GitHub Actions 持续集成定义在 `.github/workflows/ci.yml`：
- PHP 语法检查 (`php -l`)
- PHPUnit 单元测试
- Flutter 静态分析 (`flutter analyze`)

### 8.4 数据库备份

`database/backup/backup.sh` — mysqldump + gzip 备份，自动清理 30 天前旧备份。
`database/backup/restore.sh` — 交互式选择并恢复备份。

### 8.5 监控

`GET /metrics` 端点（`MetricsController`）以 Prometheus text format 暴露 5 个 gauge 指标：HTTP 请求总数、活跃用户数、数据库/Redis 连接状态、内存使用量。

### 8.6 环境要求

| 组件 | 最低版本 | 推荐配置 |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ OPcache enabled |
| MySQL | 8.0+ | 8.0+ 主从复制 |
| Elasticsearch | 7.x | 8.x 3节点集群 |
| Redis | 6.x | 7.x 哨兵模式 |
| Nginx | 1.20+ | 反向代理 + gzip + SSL |
| Flutter SDK | 3.41+ | 最新稳定版 |
| HarmonyOS | API 12 | DevEco Studio 5.x |
