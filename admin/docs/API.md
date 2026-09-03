# API 参考文档
<!-- lang-nav -->

Languages: **中文** · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 概述

开放管理后台 (open-admin) 基于 webman v2 构建，提供 RESTful JSON API。所有管理端接口需要 JWT 认证与 RBAC 权限校验，公开接口挂载在 `/api/v1` 前缀下，管理端接口挂载在 `/admin/v1` 前缀下，版本由 URL 路径决定而非请求头。

- **基础 URL**: `http://localhost:8787`
- **API 版本**: 版本号置于 URL 路径——公开端点 `/api/v1`、管理端点 `/admin/v1`，不使用版本请求头；未来的 v2 将注册为 `/api/v2` 组

> **端点总览**: 认证(5) | 仪表盘(1) | 用户(7) | 角色(4) | 权限(4) | 配置(4) | 日志(1) | 个人中心(3) | 导入导出(3) | 上传(1) | 运维(4: health/metrics/docs/security.txt) | 共 37 端点
- **认证**: `Authorization: Bearer <token>`（JWT）
- **响应格式**: `{ "code": 0, "message": "success", "data": {...} }`
- **文档端点**: `GET /api/docs` 返回 OpenAPI 3.0 JSON 规范

### 请求要求

- 仅允许 `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` 方法，使用其他 HTTP 方法（如 TRACE、CONNECT、PATCH）会返回 405
- 所有 `POST` / `PUT` 请求必须设置 `Content-Type: application/json`（文件上传除外），否则返回 415
- 请求体大小不得超过 10MB，否则返回 413
- 安全过滤器对所有请求输入进行 XSS、SQL 注入、路径遍历、命令注入扫描，命中返回 403
- 连续 5 次登录失败将触发账号锁定（15 分钟），锁定期间登录请求返回 429
- 同一用户最多同时持有 3 个有效 Token，超出时最旧 Token 自动加入黑名单

## 2. 错误码

| code | 含义 | 触发场景 |
|------|------|---------|
| 0 | 成功 | |
| 400 | 请求参数错误 | 请求格式不正确 |
| 401 | 未认证 | Token 缺失 / 过期 / 已在黑名单 |
| 403 | 无权限 / 安全拦截 | RBAC 权限不足 / SecurityFilter 命中 |
| 404 | 资源不存在 | 查询/更新/删除的目标不存在 |
| 405 | 请求方法不允许 | 仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD，非标准方法直接拒绝 |
| 413 | 请求体过大 | Content-Length 超过 10MB |
| 415 | 不支持的媒体类型 | POST/PUT 请求 Content-Type 非 JSON 且非文件上传 |
| 422 | 参数验证失败 | 必填字段缺失、格式不符、业务校验不通过 |
| 429 | 请求过于频繁 | RateLimit 触发 / 账号锁定（连续5次登录失败锁定15分钟） |
| 500 | 服务器内部错误 | |

## 3. 公开端点

所有公开端点挂载在 `/api/v1` 前缀下，管理端点挂载在 `/admin/v1` 前缀下；版本由路由组前缀决定，不使用版本请求头。示例公开控制器：`app\api\v1\controller\AuthController`。

### 3.1 健康检查

```
GET /health
```

- **认证**: 无需
- **限流**: 无

**响应示例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.1",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

`database`、`redis`、`elasticsearch` 取值: `"ok"` | `"unavailable"`。`elasticsearch` 在 ES 不可达时返回 `"unavailable"`，集群健康状态为非 green/yellow 则返回实际的 status 值（如 `"red"`）。

### 3.2 API 文档

```
GET /api/docs
```

- **认证**: 无需
- **限流**: 全局默认 (60次/分钟)
- **响应**: OpenAPI 3.0.3 JSON 规范，包含所有端点定义、参数和 Schema

### 3.3 生成点击验证码

```
POST /api/v1/captcha/generate
```

- **认证**: 无需
- **限流**: 全局默认 (60次/分钟)

**请求体**:
```json
{
  "difficulty": "medium"
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| difficulty | string | 否 | `easy` / `medium` / `hard`，默认 `medium` |

**响应示例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| key | string | 验证码标识，校验时回传 |
| image | string | base64 编码的 PNG 图片 |
| extra.targets[].order | int | 点击顺序 |
| extra.targets[].text | string | 点击目标提示文字 |

### 3.4 校验点击验证码

```
POST /api/v1/captcha/verify
```

- **认证**: 无需
- **限流**: 全局默认 (60次/分钟)

**请求体**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| key | string | 是 | 验证码 key，由 generate 返回 |
| clicks | array{object} | 是 | 点击坐标数组，每个元素含 `x`（int）和 `y`（int） |

**响应示例**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

验证失败时 `code` 为 422，`message` 为 `"验证失败，请重试"`，`data.valid` 为 `false`。

### 3.5 登录

```
POST /api/v1/auth/login
```

- **认证**: 无需
- **限流**: 10 次/分钟（按 IP + 路径）

**请求体**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| 字段 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| username | string | 是 | min:3, max:50 | 用户名 |
| password | string | 是 | min:6, max:32 | 密码 |
| captcha_key | string | 是 | | 验证码 key |
| clicks | array{object} | 是 | min:2 | 点击坐标数组 |

**响应示例**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| access_token | string | JWT 访问令牌 |
| refresh_token | string | JWT 刷新令牌 |
| expires_in | int | 访问令牌有效期（秒），默认 7200 |
| user.id | string | hashid 加密的用户 ID |
| user.username | string | 用户名 |
| user.real_name | string | 真实姓名 |

**可能的错误**:
- 422: 参数验证失败（缺少必填字段、格式不符）
- 422: 验证码错误，请重试
- 401: 用户名或密码错误
- 403: 账号已被禁用
- 429: 账号已被锁定，请15分钟后再试（连续5次登录失败触发）

### 3.6 注册

```
POST /api/v1/auth/register
```

- **认证**: 无需
- **限流**: 5 次/分钟（按 IP + 路径）

**请求体**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| 字段 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| username | string | 是 | min:3, max:50 | 用户名（唯一） |
| password | string | 是 | min:6, max:32 | 密码（bcrypt 哈希存储） |
| real_name | string | 是 | max:50 | 真实姓名 |
| captcha_key | string | 是 | | 验证码 key |
| clicks | array{object} | 是 | min:2 | 点击坐标数组 |

**响应示例**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

注册成功后直接返回 JWT 令牌，用户状态默认启用（status=1）。

### 3.7 刷新令牌

```
POST /api/v1/auth/refresh
```

- **认证**: 无需
- **限流**: 全局默认 (60次/分钟)

**请求体**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| refresh_token | string | 是 | 登录/注册时获取的 refresh_token |

**响应示例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

刷新成功会同时返回新的 access_token 和 refresh_token，旧令牌自动失效。刷新时会更新用户最后登录时间和 IP。

**可能的错误**:
- 422: 缺少刷新令牌
- 401: 刷新令牌无效或已过期

### 3.8 Prometheus 监控指标

```
GET /metrics
```

- **认证**: 无需
- **限流**: 无
- **响应格式**: Prometheus text format (`text/plain; version=0.0.4`)

公开 Prometheus 监控指标端点，供 Grafana/Prometheus 抓取。

**响应示例**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| 指标名 | 类型 | 说明 |
|------|------|------|
| `openadmin_http_requests_total` | gauge | 累计 HTTP 请求总数 |
| `openadmin_active_users` | gauge | 当前活跃用户数（24小时内登录） |
| `openadmin_db_connection_status` | gauge | 数据库连接状态，1=正常, 0=异常 |
| `openadmin_redis_connection_status` | gauge | Redis 连接状态，1=正常, 0=异常 |
| `openadmin_memory_usage_bytes` | gauge | PHP 进程当前内存使用量（bytes） |

## 4. 仪表盘

所有管理端接口挂载在 `/admin/v1` 前缀下，经过 `AdminAuth`（JWT 认证）、`AdminPermission`（RBAC 权限校验）、`OperationLog`（操作记录）三个中间件。

### 4.1 仪表盘数据

```
GET /admin/v1/dashboard
```

- **认证**: JWT + RBAC
- **缓存**: Redis 5 分钟

**响应示例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/v1/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| stats 字段 | 类型 | 说明 |
|------|------|------|
| label | string | 指标名称 |
| value | string | 指标数值（字符串类型） |
| icon | string | Material 图标名 |
| color | string | 卡片色值 |
| trend | float? | 日环比增长率（百分比），仅"用户总数"有此字段 |

| trends 字段 | 类型 | 说明 |
|------|------|------|
| dates | array{string} | 最近 30 天日期序列 |
| series | array{object} | 趋势线数据，每条含 name（名称）、data（数值数组）、color（颜色） |

## 5. 用户管理

所有用户管理接口返回的 `id` 均为 hashid 加密字符串。密码字段已在响应中排除。手机号和邮箱在列表接口中脱敏展示，在详情接口中返回明文（数据库加密字段由 Encryptable trait 自动解密）。

### 5.1 用户列表

```
GET /admin/v1/user
```

- **认证**: JWT + RBAC

**查询参数**:

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|------|------|
| page | int | 否 | 1 | 页码 |
| limit | int | 否 | 15 | 每页条数 |
| keyword | string | 否 | | 搜索关键词，匹配用户名和真实姓名 |
| status | int | 否 | | 状态筛选，0=禁用，1=启用 |

**响应示例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| id | string | hashid 加密的用户 ID |
| username | string | 用户名 |
| real_name | string | 真实姓名 |
| phone | string | 脱敏手机号（`138****5678` 格式） |
| email | string | 脱敏邮箱（`a***@example.com` 格式） |
| status | int | 1=启用, 0=禁用 |
| last_login_at | string | 最后登录时间 (datetime) |
| created_at | string | 创建时间 (datetime) |

### 5.2 创建用户

```
POST /admin/v1/user
```

- **认证**: JWT + RBAC

**请求体**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| 字段 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| username | string | 是 | min:3, max:50 | 用户名（唯一） |
| password | string | 是 | min:6, max:32 | 密码（bcrypt 存储） |
| real_name | string | 是 | max:50 | 真实姓名 |
| phone | string | 否 | | 手机号（Encryptable 加密存储） |
| email | string | 否 | | 邮箱（Encryptable 加密存储） |
| status | int | 否 | in:0,1 | 状态，默认 1（启用） |

**响应示例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**可能的错误**:
- 422: 用户名已存在
- 422: 参数验证失败（必填字段缺失）

### 5.3 用户详情

```
GET /admin/v1/user/{id}
```

- **认证**: JWT + RBAC
- **路径参数**: `{id}` 为 hashid 加密的用户 ID

**响应示例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

详情接口中 `phone` 和 `email` 返回明文（数据库中为加密存储，Encryptable cast 自动解密），不脱敏。`password` 和 `id_card` 始终不在响应中。

**可能的错误**:
- 404: 用户不存在

### 5.4 更新用户

```
PUT /admin/v1/user/{id}
```

- **认证**: JWT + RBAC
- **路径参数**: `{id}` 为 hashid 加密的用户 ID

**请求体**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| real_name | string | 否 | 真实姓名，不传保持原值 |
| password | string | 否 | 新密码，为空字符串或不传则不修改 |
| phone | string | 否 | 手机号 |
| email | string | 否 | 邮箱 |
| status | int | 否 | 0=禁用, 1=启用 |

**响应示例**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**可能的错误**:
- 404: 用户不存在

### 5.5 删除用户

```
DELETE /admin/v1/user/{id}
```

- **认证**: JWT + RBAC
- **路径参数**: `{id}` 为 hashid 加密的用户 ID
- **敏感操作**: 需要密码二次确认

**请求体**:
```json
{
  "password": "admin_password"
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| password | string | 是 | 当前登录用户密码（二次确认） |

**响应示例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

执行软删除（Eloquent SoftDeletes），数据标记 deleted_at 而不物理删除。

**可能的错误**:
- 404: 用户不存在
- 422: 敏感操作需要输入密码确认（password 为空）
- 422: 密码验证失败（密码不匹配）

### 5.6 批量删除用户

```
POST /admin/v1/user/batch/destroy
```

- **认证**: JWT + RBAC
- **敏感操作**: 需要密码二次确认

**请求体**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| ids | array{string} | 是 | hashid 加密的用户 ID 数组 |
| password | string | 是 | 当前登录用户密码（二次确认） |

**响应示例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

执行软删除，`data.count` 为实际删除数量。

**可能的错误**:
- 422: 请选择要删除的用户（ids 为空）
- 422: 无效的 ID（hashid 解码失败）
- 422: 密码验证失败

### 5.7 批量启用/禁用用户

```
POST /admin/v1/user/batch/status
```

- **认证**: JWT + RBAC

**请求体**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| ids | array{string} | 是 | hashid 加密的用户 ID 数组 |
| status | int | 是 | 0=禁用, 1=启用 |

**响应示例**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message 根据 status 值动态变化为 `"批量启用成功"` 或 `"批量禁用成功"`。

**可能的错误**:
- 422: 请选择用户（ids 为空）
- 422: 状态值无效（status 不是 0 或 1）

## 6. 角色管理

### 6.1 角色列表

```
GET /admin/v1/role
```

- **认证**: JWT + RBAC

**查询参数**:

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|------|------|
| page | int | 否 | 1 | 页码 |
| limit | int | 否 | 15 | 每页条数 |

**响应示例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| id | string | hashid 加密的角色 ID |
| name | string | 角色名称 |
| slug | string | 角色标识（唯一，用于权限判断） |
| description | string | 角色描述 |
| status | int | 1=启用, 0=禁用 |
| users_count | int | 拥有该角色的用户数量 |

### 6.2 创建角色

```
POST /admin/v1/role
```

- **认证**: JWT + RBAC

**请求体**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| 字段 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| name | string | 是 | max:50 | 角色名称 |
| slug | string | 是 | max:50 | 角色标识 |
| description | string | 否 | | 角色描述，默认空字符串 |
| status | int | 否 | | 状态，默认 1 |
| permission_ids | array{int} | 否 | | 权限 ID 数组（原始 INT ID，非 hashid） |

**响应示例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 更新角色

```
PUT /admin/v1/role/{id}
```

- **认证**: JWT + RBAC

**请求体**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 否 | 角色名称 |
| description | string | 否 | 描述 |
| status | int | 否 | 0=禁用, 1=启用 |
| permission_ids | array{int} | 否 | 权限 ID 数组，传入则同步（覆盖）角色权限 |

**响应示例**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 删除角色

```
DELETE /admin/v1/role/{id}
```

- **认证**: JWT + RBAC
- **敏感操作**: 需要密码二次确认

**请求体**:
```json
{
  "password": "admin_password"
}
```

**响应示例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

删除时自动解除角色与所有权限、用户的关联关系，然后物理删除角色记录。

## 7. 权限管理

权限采用树形结构（parent_id 自关联），分为三种类型。列表接口返回完整权限树。

### 7.1 权限树

```
GET /admin/v1/permission
```

- **认证**: JWT + RBAC

**响应示例**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/v1/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/v1/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| id | string | hashid 加密 |
| parent_id | string | 父权限 hashid，"0" 表示根节点 |
| name | string | 权限名称 |
| slug | string | 权限标识（路由/按钮标识） |
| type | int | 1=菜单, 2=按钮, 3=接口 |
| icon | string | 菜单图标（Material 图标名） |
| path | string | 前端路由路径 |
| sort | int | 排序值（升序） |
| children | array? | 子权限列表（递归），无子节点时不包含此字段 |

### 7.2 创建权限

```
POST /admin/v1/permission
```

- **认证**: JWT + RBAC

**请求体**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/v1/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| 字段 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| parent_id | int | 否 | | 父权限 ID（原始 INT 类型），默认 0 |
| name | string | 是 | max:50 | 权限名称 |
| slug | string | 是 | max:100 | 权限标识 |
| type | int | 是 | in:1,2,3 | 1=菜单, 2=按钮, 3=接口 |
| icon | string | 否 | | 菜单图标，默认空 |
| path | string | 否 | | 前端路由路径，默认空 |
| sort | int | 否 | | 排序值，默认 0 |

**响应示例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/v1/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 更新权限

```
PUT /admin/v1/permission/{id}
```

- **认证**: JWT + RBAC

**请求体**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 否 | 权限名称 |
| icon | string | 否 | 图标 |
| path | string | 否 | 路由路径 |
| sort | int | 否 | 排序值 |

### 7.4 删除权限

```
DELETE /admin/v1/permission/{id}
```

- **认证**: JWT + RBAC
- **敏感操作**: 需要密码二次确认

**请求体**:
```json
{
  "password": "admin_password"
}
```

**响应示例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

删除时级联删除所有子权限（`parent_id` = 当前权限 ID 的记录），同时解除与所有角色的关联。

## 8. 系统配置

系统配置以 `group` + `key` 组合唯一。

### 8.1 配置列表

```
GET /admin/v1/config
```

- **认证**: JWT + RBAC

**查询参数**:

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|------|------|
| page | int | 否 | 1 | 页码 |
| limit | int | 否 | 15 | 每页条数 |
| group | string | 否 | | 按配置分组筛选 |

**响应示例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| id | string | hashid |
| group | string | 配置分组（如 `system`、`email`、`storage`） |
| key | string | 配置键 |
| value | string | 配置值 |
| type | string | 值类型提示（`string`、`integer`、`boolean`、`json` 等） |
| description | string | 配置说明 |

### 8.2 创建配置

```
POST /admin/v1/config
```

- **认证**: JWT + RBAC

**请求体**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| 字段 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| group | string | 是 | max:100 | 配置分组 |
| key | string | 是 | max:100 | 配置键（同分组内唯一） |
| value | string | 是 | | 配置值 |
| type | string | 否 | | 值类型，默认 `string` |
| description | string | 否 | | 配置说明，默认空 |

**响应示例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**可能的错误**:
- 422: 配置项已存在（同 group + key）

### 8.3 更新配置

```
PUT /admin/v1/config/{id}
```

- **认证**: JWT + RBAC

**请求体**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| value | string | 否 | 更新配置值 |
| type | string | 否 | 更新值类型 |
| description | string | 否 | 更新说明文字 |

### 8.4 删除配置

```
DELETE /admin/v1/config/{id}
```

- **认证**: JWT + RBAC
- **敏感操作**: 需要密码二次确认

**请求体**:
```json
{
  "password": "admin_password"
}
```

物理删除配置记录。

## 9. 操作日志

操作日志为只读接口，由 `OperationLog` 中间件在每次 POST/PUT/DELETE 请求时自动写入，存储字段包括 `user_id`、`action`、`method`、`path`、`ip`、`source`、`input`。

### 9.1 操作日志列表

```
GET /admin/v1/log
```

- **认证**: JWT + RBAC

**查询参数**:

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|------|------|
| page | int | 否 | 1 | 页码 |
| limit | int | 否 | 15 | 每页条数 |
| user_id | int | 否 | | 按用户 ID 精确筛选（原始 INT 类型） |
| action | string | 否 | | 按操作动作精确筛选 |
| path | string | 否 | | 按请求路径模糊筛选 |
| start_date | string | 否 | | 开始日期 (Y-m-d 格式) |
| end_date | string | 否 | | 结束日期 (Y-m-d 格式) |

**响应示例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/v1/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| id | string | hashid |
| user_name | string | 操作用户名（通过 user 关联获取，未登录操作显示"系统"） |
| action | string | 操作动作描述 |
| method | string | HTTP 方法（POST/PUT/DELETE） |
| path | string | 请求路径 |
| ip | string | 客户端 IP |
| source | string | 请求来源 |
| input | string | 请求参数 JSON 字符串（不包含文件） |
| created_at | string | 操作时间 (datetime) |

## 10. 个人中心

个人中心接口仅需 JWT 认证（不需要 RBAC 权限校验——`AdminPermission` 中间件应将其加入白名单）。

### 10.1 更新个人信息

```
PUT /admin/v1/profile
```

- **认证**: JWT

**请求体**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| real_name | string | 否 | 真实姓名 |
| phone | string | 否 | 手机号（Encryptable 加密存储） |
| email | string | 否 | 邮箱（Encryptable 加密存储） |

**响应示例**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

响应中 `phone` 和 `email` 返回明文，`password` 和 `id_card` 已剔除。

### 10.2 修改密码

```
PUT /admin/v1/profile/password
```

- **认证**: JWT

**请求体**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| 字段 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| old_password | string | 是 | | 当前密码 |
| new_password | string | 是 | min:6, max:32 | 新密码 |

**响应示例**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**可能的错误**:
- 422: 请填写旧密码和新密码
- 422: 旧密码错误
- 422: 新密码长度 6-32 位

### 10.3 登出

```
POST /admin/v1/profile/logout
```

- **认证**: JWT

**请求体**: 无（无 requestBody，从 Authorization 头读取 token）

**响应示例**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

登出逻辑：解码 JWT 获取剩余有效期 (exp - now)，将该 token 的 md5 哈希写入 Redis 黑名单 `jwt_blacklist:{md5}`，TTL = 剩余有效期。黑名单中的 token 在 `AdminAuth` 中间件中被拦截，返回 401。

无 token 时返回 401。token 已过期/无效时（解码抛出异常）仍视为登出成功。

## 11. 导入导出

### 11.1 导出 Excel

```
POST /admin/v1/export/excel
```

- **认证**: JWT + RBAC
- **响应类型**: 文件下载（`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`）

**请求体**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| 字段 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|------|------|
| table | string | 否 | `admin_user` | 导出表名。支持: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | 否 | | 导出列字段名数组，为空则导出该表全部列 |
| conditions | object | 否 | `{}` | 筛选条件，key-value 对，值不为空时用于 WHERE |
| title | string | 否 | `数据导出` | Excel 标题（显示为 Sheet 名称） |

**支持的表和列**:

| table | 可用列 |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

敏感字段 `phone`、`email`、`id_card` 在导出时自动脱敏处理。数据上限 10000 行。Excel 首行冻结、自动筛选。

### 11.2 导出 PDF

```
POST /admin/v1/export/pdf
```

- **认证**: JWT + RBAC
- **响应类型**: 文件下载（`application/pdf`，A4 横向）

**请求体**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

或者表格模式:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| 字段 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|------|------|
| type | string | 否 | `table` | 导出类型：`table` / `dashboard` |
| title | string | 否 | `数据导出` | PDF 标题 |
| data | object | 否 | `{}` | 导出数据 |

`type=dashboard` 时 `data` 需包含 `stats` 数组（卡片形式渲染）；`type=table` 时 `data` 需包含 `columns` 和 `rows` 数组。

PDF 模板包含版权信息和导出时间戳。

### 11.3 导入用户 (Excel)

```
POST /admin/v1/import/users
```

- **认证**: JWT + RBAC
- **请求类型**: `multipart/form-data`（文件上传）

**表单字段**:

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| file | file | 是 | `.xlsx` 或 `.xls` 格式 |

**Excel 列要求**:

| 列名 | 必填 | 说明 |
|------|------|------|
| username | 是 | 用户名（唯一） |
| password | 是 | 密码（bcrypt 哈希存储） |
| real_name | 是 | 真实姓名 |
| phone | 否 | 手机号 |
| email | 否 | 邮箱 |
| status | 否 | 状态，默认 1 |

第 1 行为列标题（大小写不敏感），第 2 行起为数据。

**响应示例**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| total | int | 总行数（不含标题行） |
| success | int | 成功导入数 |
| failed | int | 失败条数 |
| errors | array | 失败详情，每条含 row（Excel 行号）和 reason（失败原因） |

## 12. 文件上传

```
POST /admin/v1/upload
```

- **认证**: JWT + RBAC
- **请求类型**: `multipart/form-data`

**表单字段**:

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| file | file | 是 | 上传文件 |

**允许的文件类型**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**最大文件大小**: 10MB

**响应示例**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

文件按日期分目录存储于 `public/upload/{Y-m-d}/`，文件名为 `md5(uniqid) + 原始扩展名`。`url` 为相对于站点根路径的相对路径。

**可能的错误**:
- 422: 请选择文件（未上传）
- 422: 不支持的文件类型
- 422: 文件大小不能超过 10MB
- 500: 文件上传失败（文件无效）

## 13. 响应头

所有接口（全局中间件层注入）均包含以下响应头：

| 头 | 说明 |
|----|------|
| `X-RateLimit-Limit` | 限流上限（次数） |
| `X-RateLimit-Remaining` | 剩余请求次数 |
| `X-RateLimit-Reset` | 限流窗口重置时间戳 |
| `Retry-After` | 仅限流触发时返回，建议等待秒数 |
| `X-Content-Type-Options` | `nosniff`（由 webman 默认，禁止 MIME 嗅探） |
| `X-Frame-Options` | `DENY`（由 webman 的 CORS 中间件/基础配置提供） |

限流详情:
- 默认全局限制: 60 次/分钟 / IP+路径
- 登录端点 `/api/v1/auth/login`: 10 次/分钟
- 注册端点 `/api/v1/auth/register`: 5 次/分钟
- 使用 Redis 原子化滑动窗口算法（Lua ZSET），避免 TOCTOU 竞态
- Redis 不可用时 fail-closed：返回 503（`Retry-After: 5`），不放行请求

## 14. 数据分析 (Analytics)

全部端点需认证（`AdminAuth` + `AdminPermission`），MySQL 实时聚合，共 12 个：

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /admin/v1/analytics/overview | 平台总览（今日/近7天） |
| GET | /admin/v1/analytics/game-ranking | 游戏排行（?days=7） |
| GET | /admin/v1/analytics/dau-trend | DAU 趋势（?days=30） |
| GET | /admin/v1/analytics/hourly-trend | 小时趋势 |
| GET | /admin/v1/analytics/action-distribution | 行为分布 |
| GET | /admin/v1/analytics/revenue | 营收分析 |
| GET | /admin/v1/analytics/conversion | 游戏转化率 |
| GET | /admin/v1/analytics/probability | 联合/条件概率 |
| GET | /admin/v1/analytics/retention | 留存分析 D1/D3/D7/D30 |
| GET | /admin/v1/analytics/funnel | 转化漏斗 |
| GET | /admin/v1/analytics/arpu | ARPU/ARPPU 趋势 |
| GET | /admin/v1/analytics/economy | 游戏币种经济指标 |

## 15. 工单管理 (Ticket)

全部端点需认证（`AdminAuth` + `AdminPermission`），共 5 个：

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /admin/v1/ticket/list | 工单列表（?page=&limit=&status=&type=） |
| GET | /admin/v1/ticket/{hashid} | 工单详情（含回复） |
| POST | /admin/v1/ticket/{hashid}/reply | 回复工单 |
| POST | /admin/v1/ticket/{hashid}/close | 关闭工单 |
| POST | /admin/v1/ticket/{hashid}/assign | 指定处理人（admin_id） |

## 16. 认证流程

完整的认证时序：

```
1. 客户端请求 POST /api/v1/captcha/generate
    ↓
   服务端返回: key + base64 图片 + 点击目标提示
   
2. 用户点击图片目标位置，前/客户端收集点击坐标
   
3. 客户端请求 POST /api/v1/auth/login
   (请求头: Content-Type: application/json)
   请求体: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   服务端:
   a. 参数校验 → 422
   b. 校验验证码 → 422
   c. 校验用户凭证 → 401
   d. 检查账号状态 → 403
   e. 签发 JWT (access + refresh) → 200
   f. 更新 last_login_at / last_login_ip
    ↓
   客户端保存: access_token, refresh_token, expires_in

4. 后续请求携带 JWT
   请求头: Authorization: Bearer <access_token>
    ↓
   AdminAuth 中间件:
   a. 提取 Bearer token
   b. 检查黑名单 (Redis jwt_blacklist:{md5}) → 401
   c. 解码 JWT，校验过期 → 401
   d. 设置 $request->adminId = sub 字段
    ↓
   AdminPermission 中间件:
   a. 未登录（adminId 为空）→ 401
   b. 对资源路由解析权限标识
   c. 查询用户角色 → 角色权限，进行匹配
   d. 无权限 → 403
    ↓
   Controller 处理请求
    ↓
   Response + X-RateLimit-* 头

5. Access Token 过期前刷新
   客户端请求 POST /api/v1/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

6. 登出
   客户端请求 POST /admin/v1/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### JWT 结构

- **access_token**: `{ sub: <user_id>, username: "<name>" }`，默认 TTL 7200 秒（由 JWT 配置 `default_expire` 控制）
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`，默认 TTL 1209600 秒（由 JWT 配置 `refresh_expire` 控制，即 14 天）

### 安全管理

- 密码以 `PASSWORD_BCRYPT` 哈希存储
- 敏感字段（phone, email, id_card）使用 `erikwang2013/encryptable` 在数据库层透明加解密
- API 层 ID 使用 `erikwang2013/hashids` 加密传输，避免暴露原始 snowflake ID 序列
- SecurityFilter 全局扫描 XSS、SQL 注入、路径遍历、命令注入，同 IP 5次/60秒临时黑名单 15 分钟
- 敏感操作（删除用户、角色、权限、配置）需要当前登录用户密码二次确认
- 并发会话限制：同一用户最多 3 个有效 Token，第 4 个设备登录时最旧 Token 被强制加入黑名单
- 账号锁定：连续 5 次登录失败触发 15 分钟账号锁定，锁定期间返回 429

## 15. 部署运维

### Docker Compose

项目根目录提供 `docker-compose.yml`，编排 5 个服务（Nginx、webman app、MySQL、Redis、Elasticsearch）。PHP 通过 `Dockerfile` 构建（基于 `php:8.3-cli`，启用 OPcache）。

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` 定义了 GitHub Actions 持续集成流水线：
- `php -l` 语法检查
- PHPUnit 单元测试
- `flutter analyze` 静态分析

### 数据库备份

`database/backup/` 目录提供备份与恢复脚本：
- `backup.sh` — mysqldump + gzip 压缩备份，自动清理 30 天前的旧备份文件
- `restore.sh` — 交互式恢复，列出现有备份供用户选择

### Nginx 安全配置

生产环境部署请参考 `docs/nginx-security.conf` 进行反向代理安全加固配置。

## 16. 数据分析 (Analytics)

数据分析接口由 `AnalyticsController` 提供，全部基于 MySQL 实时聚合（`game_game_play_log` 游戏行为日志 / `game_deposit_order` 充值订单），数据库故障时返回空数据而非 500。除特别说明外均需 JWT + RBAC 认证，响应包装格式统一为 `{ "code": 0, "message": "success", "data": ... }`。

### 16.1 平台总览

```
GET /admin/v1/analytics/overview
```

**响应**: `today` / `week` 各含 `dau`（活跃用户数）、`revenue`（已确认充值总额，字符串）、`new_users`（新增用户数）。

### 16.2 游戏排行

```
GET /admin/v1/analytics/game-ranking?days=7
```

**响应**: 按游戏行为次数降序取前 10，每项含 `game_id`（hashid）、`name`、`plays`、`players`。

### 16.3 DAU 趋势

```
GET /admin/v1/analytics/dau-trend?days=30
```

**响应**: `{ "日期": 活跃数, ... }`，缺失日期补 0。

### 16.4 小时趋势

```
GET /admin/v1/analytics/hourly-trend?game_id=<hashid>
```

**响应**: `{ "0": 次数, ... "23": 次数 }` 24 个整点槽位；`game_id` 为空时统计全部游戏。

### 16.5 行为分布

```
GET /admin/v1/analytics/action-distribution?game_id=<hashid>&hours=24
```

**响应**: `{ "start": n, "end": n, "earn": n, "spend": n }` 四类行为计数；`hours` 上限 168。

### 16.6 营收总览

```
GET /admin/v1/analytics/revenue?days=7
```

**响应**: `{ "total": "总额", "trend": { "日期": "当日额", ... } }`，仅统计 `status=confirmed` 订单。

### 16.7 游戏转化率

```
GET /admin/v1/analytics/conversion?days=30
```

**响应**: 每款游戏含 `game_id`（hashid）、`game_name`、`players`（去重玩家数）、`depositors`（去重充值人数）、`conversion_rate`（充值转化率，0~1）。

### 16.8 联合概率

```
GET /admin/v1/analytics/probability?game_a=<hashid>&game_b=<hashid>
```

**响应**: `{ "joint": { "joint_probability": 0.12, "confidence": 0.3 } }` — Jaccard 系数（两游戏共同玩家 / 并集玩家）与置信度（共同玩家 / A 游戏玩家）。

### 16.9 留存分析

```
GET /admin/v1/analytics/retention?days=30
```

**响应**: `{ "D1": "8.5%", "D3": "...", "D7": "...", "D30": "..." }` 按注册日分群的次日/3日/7日/30日留存率。

### 16.10 转化漏斗

```
GET /admin/v1/analytics/funnel?days=30
```

**响应**: 注册 → 首充 → 首次兑换 → 首次游戏 四个步骤的 `step`、`count`、`rate`（相对注册数百分比）。

### 16.11 ARPU/ARPPU 趋势

```
GET /admin/v1/analytics/arpu?days=30
```

**响应**: `{ "dates": [...], "arpu": [...], "arppu": [...] }` 每日人均营收（ARPU）与付费用户人均营收（ARPPU）。

### 16.12 游戏经济指标

```
GET /admin/v1/analytics/economy
```

**响应**: `currencies` 数组，每项含 `game_name`、`currency`、`symbol`、`total_minted`（铸币总量）、`total_burned`（销毁总量）、`circulation`（流通量）、`inflation_rate`（通胀率），使用 bcmath 高精度计算。

## 17. 支付管理 (Payment)

支付方式管理由 `PaymentController` 提供，5 个端点均需 JWT + RBAC 认证。`provider` 白名单：`stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash`。`config` 为支付配置 JSON 字符串（数据库加密存储）。

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /admin/v1/payment/method/list | 支付方式列表（按 sort 升序） |
| POST | /admin/v1/payment/method/toggle | 启禁用支付方式 |
| POST | /admin/v1/payment/method/create | 创建支付方式 |
| PUT | /admin/v1/payment/method/{hashid} | 更新支付方式 |
| DELETE | /admin/v1/payment/method/{hashid} | 删除支付方式（存在 pending 订单时拒绝） |

### 17.1 支付方式列表

```
GET /admin/v1/payment/method/list
```

- **认证**: JWT + RBAC

**响应示例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "name": "Stripe 信用卡",
        "type": "fiat",
        "provider": "stripe",
        "status": 1,
        "sort": 1,
        "countries": ["US", "SG"],
        "currency": "USD",
        "min_amount": "10",
        "max_amount": "5000",
        "config": null,
        "created_at": "2026-08-29 10:00:00",
        "updated_at": "2026-08-29 10:00:00"
      }
    ]
  }
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| id | string | hashid 加密的支付方式 ID |
| name | string | 支付方式名称 |
| type | string | `fiat`（法币）/ `crypto`（加密货币） |
| provider | string | 网关提供商：`stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash` |
| status | int | 1=启用, 0=禁用 |
| sort | int | 排序值（升序） |
| countries | array{string} | 可见国家码数组（空数组=全球可见） |
| currency | string | 币种（如 USD/USDT），空=不限 |
| min_amount / max_amount | string | 金额区间（字符串保留精度），0=不限制 |
| config | string? | 支付配置 JSON（加密存储，未设置时为 null） |

### 17.2 启禁用支付方式

```
POST /admin/v1/payment/method/toggle
```

**请求体**:
```json
{
  "id": "a1b2c3d4",
  "status": 1
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | string | 是 | 支付方式 ID（hashid 编码） |
| status | int | 是 | 0=禁用, 1=启用 |

**可能的错误**:
- 422: 参数验证失败（id/status 缺失或 status 非 0/1）
- 404: 支付方式不存在

### 17.3 创建支付方式

```
POST /admin/v1/payment/method/create
```

**请求体**:
```json
{
  "name": "USDT 加密货币",
  "type": "crypto",
  "provider": "nowpayments",
  "status": 1,
  "sort": 2,
  "countries": [],
  "currency": "USDT",
  "min_amount": "10",
  "max_amount": "10000",
  "config": "{\"api_key\":\"...\"}"
}
```

| 字段 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| name | string | 是 | max:50 | 支付方式名称 |
| type | string | 是 | in:fiat,crypto | 类型：法币/加密货币 |
| provider | string | 是 | in:stripe,paypal,nowpayments,coinbase,skrill,neteller,paysafecard,paytm,mercadopago,astropay,paypay,kakaopay,gcash | 网关提供商白名单 |
| status | int | 是 | in:0,1 | 状态 |
| sort | int | 否 | integer,min:0 | 排序值，默认 0 |
| countries | array{string} | 否 | max:2 | 可见国家码数组，空=全球 |
| currency | string | 否 | max:10 | 币种，默认空 |
| min_amount / max_amount | string | 否 | numeric,min:0 | 金额区间，默认 "0" |
| config | string | 否 | | 支付配置 JSON（加密存储），空字符串存 NULL |

**响应示例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": { "id": "e5f6g7h8" }
}
```

**可能的错误**:
- 422: 参数验证失败

### 17.4 更新支付方式

```
PUT /admin/v1/payment/method/{hashid}
```

- **路径参数**: `{hashid}` 为 hashid 加密的支付方式 ID
- **请求体**: 同创建（17.3），所有字段可选，仅更新传入字段

**可能的错误**:
- 404: 支付方式不存在
- 422: 参数验证失败

### 17.5 删除支付方式

```
DELETE /admin/v1/payment/method/{hashid}
```

- **路径参数**: `{hashid}` 为 hashid 加密的支付方式 ID

**可能的错误**:
- 404: 支付方式不存在
- 422: 存在待支付订单（status=pending），无法删除
