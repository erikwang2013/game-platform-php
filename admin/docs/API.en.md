# API Reference
<!-- lang-nav -->

Languages: [中文](API.md) · **English** · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Overview

The open admin dashboard (open-admin) is built on webman v2 and provides RESTful JSON APIs. All admin endpoints require JWT authentication and RBAC permission validation; public endpoints are mounted under the `/api/v1` prefix and admin endpoints under the `/admin/v1` prefix; the version is carried by the URL path rather than a request header.

- **Base URL**: `http://localhost:8787`
- **API version**: encoded in the URL path — public endpoints under `/api/v1`, admin endpoints under `/admin/v1`; no version request header is used, a future v2 would register as an `/api/v2` group

> **Endpoint overview**: auth(5) | dashboard(1) | users(7) | roles(4) | permissions(4) | config(4) | logs(1) | profile(3) | import/export(3) | upload(1) | operations(4: health/metrics/docs/security.txt) | 37 endpoints total
- **Authentication**: `Authorization: Bearer <token>` (JWT)
- **Response format**: `{ "code": 0, "message": "success", "data": {...} }`
- **Docs endpoint**: `GET /api/docs` returns the OpenAPI 3.0 JSON spec

### Request Requirements

- Only `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` methods are allowed; other HTTP methods (e.g. TRACE, CONNECT, PATCH) return 405
- All `POST` / `PUT` requests must set `Content-Type: application/json` (except file uploads), otherwise 415 is returned
- Request body size must not exceed 10MB, otherwise 413 is returned
- The security filter scans all request input for XSS, SQL injection, path traversal, and command injection; hits return 403
- 5 consecutive failed logins trigger an account lockout (15 minutes); login requests during the lockout return 429
- A single user can hold at most 3 valid tokens concurrently; the oldest token is automatically blacklisted when exceeded

## 2. Error Codes

| code | Meaning | Trigger scenario |
|------|------|---------|
| 0 | Success | |
| 400 | Request parameter error | Incorrect request format |
| 401 | Not authenticated | Token missing / expired / blacklisted |
| 403 | No permission / security block | Insufficient RBAC permissions / SecurityFilter hit |
| 404 | Resource not found | The query/update/delete target does not exist |
| 405 | Method not allowed | Only GET/POST/PUT/DELETE/OPTIONS/HEAD allowed, non-standard methods rejected outright |
| 413 | Request body too large | Content-Length exceeds 10MB |
| 415 | Unsupported media type | POST/PUT request Content-Type is not JSON and not a file upload |
| 422 | Parameter validation failed | Required field missing, wrong format, business validation failed |
| 429 | Too many requests | RateLimit triggered / account lockout (15-min lock after 5 consecutive failed logins) |
| 500 | Internal server error | |

## 3. Public Endpoints

All public endpoints are mounted under the `/api/v1` prefix, and admin endpoints under the `/admin/v1` prefix; the version is determined by the route-group prefix ; no version request header is used. Example public controller: `app\api\v1\controller\AuthController`.

### 3.1 Health Check

```
GET /health
```

- **Auth**: none
- **Rate limit**: none

**Example response**:
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

`database`, `redis`, `elasticsearch` values: `"ok"` | `"unavailable"`. `elasticsearch` returns `"unavailable"` when ES is unreachable; if the cluster health is not green/yellow, the actual status value is returned (e.g. `"red"`).

### 3.2 API Documentation

```
GET /api/docs
```

- **Auth**: none
- **Rate limit**: global default (60/min)
- **Response**: OpenAPI 3.0.3 JSON spec with all endpoint definitions, parameters, and schemas

### 3.3 Generate Click Captcha

```
POST /api/v1/captcha/generate
```

- **Auth**: none
- **Rate limit**: global default (60/min)

**Request body**:
```json
{
  "difficulty": "medium"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| difficulty | string | No | `easy` / `medium` / `hard`, defaults to `medium` |

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| key | string | Captcha identifier, returned when verifying |
| image | string | base64-encoded PNG image |
| extra.targets[].order | int | Click order |
| extra.targets[].text | string | Click target prompt text |

### 3.4 Verify Click Captcha

```
POST /api/v1/captcha/verify
```

- **Auth**: none
- **Rate limit**: global default (60/min)

**Request body**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| key | string | Yes | Captcha key, returned by generate |
| clicks | array{object} | Yes | Array of click coordinates, each element contains `x` (int) and `y` (int) |

**Example response**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

On verification failure `code` is 422, `message` is `"验证失败，请重试"`, and `data.valid` is `false`.

### 3.5 Login

```
POST /api/v1/auth/login
```

- **Auth**: none
- **Rate limit**: 10/min (per IP + path)

**Request body**:
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

| Field | Type | Required | Validation rules | Description |
|------|------|------|---------|------|
| username | string | Yes | min:3, max:50 | Username |
| password | string | Yes | min:6, max:32 | Password |
| captcha_key | string | Yes | | Captcha key |
| clicks | array{object} | Yes | min:2 | Array of click coordinates |

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| access_token | string | JWT access token |
| refresh_token | string | JWT refresh token |
| expires_in | int | Access token validity (seconds), default 7200 |
| user.id | string | hashid-encrypted user ID |
| user.username | string | Username |
| user.real_name | string | Real name |

**Possible errors**:
- 422: parameter validation failed (missing required fields, wrong format)
- 422: captcha incorrect, please retry
- 401: username or password incorrect
- 403: account disabled
- 429: account locked, try again in 15 minutes (triggered by 5 consecutive failed logins)

### 3.6 Register

```
POST /api/v1/auth/register
```

- **Auth**: none
- **Rate limit**: 5/min (per IP + path)

**Request body**:
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

| Field | Type | Required | Validation rules | Description |
|------|------|------|---------|------|
| username | string | Yes | min:3, max:50 | Username (unique) |
| password | string | Yes | min:6, max:32 | Password (stored bcrypt-hashed) |
| real_name | string | Yes | max:50 | Real name |
| captcha_key | string | Yes | | Captcha key |
| clicks | array{object} | Yes | min:2 | Array of click coordinates |

**Example response**:
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

JWT tokens are returned directly on successful registration, and the user status defaults to enabled (status=1).

### 3.7 Refresh Token

```
POST /api/v1/auth/refresh
```

- **Auth**: none
- **Rate limit**: global default (60/min)

**Request body**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| refresh_token | string | Yes | The refresh_token obtained at login/registration |

**Example response**:
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

A successful refresh returns both a new access_token and refresh_token, and the old tokens are automatically invalidated. The user's last login time and IP are updated on refresh.

**Possible errors**:
- 422: missing refresh token
- 401: refresh token invalid or expired

### 3.8 Prometheus Metrics

```
GET /metrics
```

- **Auth**: none
- **Rate limit**: none
- **Response format**: Prometheus text format (`text/plain; version=0.0.4`)

Public Prometheus metrics endpoint for scraping by Grafana/Prometheus.

**Example response**:
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

| Metric | Type | Description |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Cumulative HTTP request total |
| `openadmin_active_users` | gauge | Current active users (logged in within 24 hours) |
| `openadmin_db_connection_status` | gauge | Database connection status, 1=ok, 0=error |
| `openadmin_redis_connection_status` | gauge | Redis connection status, 1=ok, 0=error |
| `openadmin_memory_usage_bytes` | gauge | Current PHP process memory usage (bytes) |

## 4. Dashboard

All admin endpoints are mounted under the `/admin/v1` prefix and pass through three middlewares: `AdminAuth` (JWT authentication), `AdminPermission` (RBAC permission validation), and `OperationLog` (operation recording).

### 4.1 Dashboard Data

```
GET /admin/v1/dashboard
```

- **Auth**: JWT + RBAC
- **Cache**: Redis 5 minutes

**Example response**:
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

| stats field | Type | Description |
|------|------|------|
| label | string | Metric name |
| value | string | Metric value (string type) |
| icon | string | Material icon name |
| color | string | Card color value |
| trend | float? | Day-over-day growth rate (percentage), only "user total" has this field |

| trends field | Type | Description |
|------|------|------|
| dates | array{string} | Date sequence for the last 30 days |
| series | array{object} | Trend line data, each entry contains name (name), data (value array), color (color) |

## 5. User Management

All `id` values returned by user management endpoints are hashid-encrypted strings. The password field is excluded from responses. Phone numbers and emails are masked in list endpoints and returned in plaintext in detail endpoints (database-encrypted fields are auto-decrypted by the Encryptable trait).

### 5.1 User List

```
GET /admin/v1/user
```

- **Auth**: JWT + RBAC

**Query parameters**:

| Parameter | Type | Required | Default | Description |
|------|------|------|------|------|
| page | int | No | 1 | Page number |
| limit | int | No | 15 | Items per page |
| keyword | string | No | | Search keyword, matches username and real name |
| status | int | No | | Status filter, 0=disabled, 1=enabled |

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| id | string | hashid-encrypted user ID |
| username | string | Username |
| real_name | string | Real name |
| phone | string | Masked phone number (`138****5678` format) |
| email | string | Masked email (`a***@example.com` format) |
| status | int | 1=enabled, 0=disabled |
| last_login_at | string | Last login time (datetime) |
| created_at | string | Creation time (datetime) |

### 5.2 Create User

```
POST /admin/v1/user
```

- **Auth**: JWT + RBAC

**Request body**:
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

| Field | Type | Required | Validation rules | Description |
|------|------|------|---------|------|
| username | string | Yes | min:3, max:50 | Username (unique) |
| password | string | Yes | min:6, max:32 | Password (bcrypt stored) |
| real_name | string | Yes | max:50 | Real name |
| phone | string | No | | Phone number (Encryptable encrypted storage) |
| email | string | No | | Email (Encryptable encrypted storage) |
| status | int | No | in:0,1 | Status, defaults to 1 (enabled) |

**Example response**:
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

**Possible errors**:
- 422: username already exists
- 422: parameter validation failed (missing required fields)

### 5.3 User Detail

```
GET /admin/v1/user/{id}
```

- **Auth**: JWT + RBAC
- **Path parameter**: `{id}` is the hashid-encrypted user ID

**Example response**:
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

In the detail endpoint, `phone` and `email` are returned in plaintext (stored encrypted in the database, auto-decrypted by the Encryptable cast), not masked. `password` and `id_card` are never included in responses.

**Possible errors**:
- 404: user does not exist

### 5.4 Update User

```
PUT /admin/v1/user/{id}
```

- **Auth**: JWT + RBAC
- **Path parameter**: `{id}` is the hashid-encrypted user ID

**Request body**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| real_name | string | No | Real name, keeps original value if not passed |
| password | string | No | New password, unchanged if empty string or not passed |
| phone | string | No | Phone number |
| email | string | No | Email |
| status | int | No | 0=disabled, 1=enabled |

**Example response**:
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

**Possible errors**:
- 404: user does not exist

### 5.5 Delete User

```
DELETE /admin/v1/user/{id}
```

- **Auth**: JWT + RBAC
- **Path parameter**: `{id}` is the hashid-encrypted user ID
- **Sensitive operation**: requires password re-confirmation

**Request body**:
```json
{
  "password": "admin_password"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| password | string | Yes | Current logged-in user's password (re-confirmation) |

**Example response**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Performs a soft delete (Eloquent SoftDeletes); the record is marked with deleted_at rather than physically removed.

**Possible errors**:
- 404: user does not exist
- 422: sensitive operations require password confirmation (password empty)
- 422: password verification failed (password mismatch)

### 5.6 Batch Delete Users

```
POST /admin/v1/user/batch/destroy
```

- **Auth**: JWT + RBAC
- **Sensitive operation**: requires password re-confirmation

**Request body**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| ids | array{string} | Yes | Array of hashid-encrypted user IDs |
| password | string | Yes | Current logged-in user's password (re-confirmation) |

**Example response**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Performs a soft delete; `data.count` is the actual number deleted.

**Possible errors**:
- 422: please select users to delete (ids empty)
- 422: invalid ID (hashid decode failed)
- 422: password verification failed

### 5.7 Batch Enable/Disable Users

```
POST /admin/v1/user/batch/status
```

- **Auth**: JWT + RBAC

**Request body**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| ids | array{string} | Yes | Array of hashid-encrypted user IDs |
| status | int | Yes | 0=disabled, 1=enabled |

**Example response**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

The message changes dynamically based on the status value, to `"批量启用成功"` or `"批量禁用成功"`.

**Possible errors**:
- 422: please select users (ids empty)
- 422: invalid status value (status is not 0 or 1)

## 6. Role Management

### 6.1 Role List

```
GET /admin/v1/role
```

- **Auth**: JWT + RBAC

**Query parameters**:

| Parameter | Type | Required | Default | Description |
|------|------|------|------|------|
| page | int | No | 1 | Page number |
| limit | int | No | 15 | Items per page |

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| id | string | hashid-encrypted role ID |
| name | string | Role name |
| slug | string | Role identifier (unique, used for permission checks) |
| description | string | Role description |
| status | int | 1=enabled, 0=disabled |
| users_count | int | Number of users with this role |

### 6.2 Create Role

```
POST /admin/v1/role
```

- **Auth**: JWT + RBAC

**Request body**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Field | Type | Required | Validation rules | Description |
|------|------|------|---------|------|
| name | string | Yes | max:50 | Role name |
| slug | string | Yes | max:50 | Role identifier |
| description | string | No | | Role description, defaults to empty string |
| status | int | No | | Status, defaults to 1 |
| permission_ids | array{int} | No | | Array of permission IDs (raw INT IDs, not hashids) |

**Example response**:
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

### 6.3 Update Role

```
PUT /admin/v1/role/{id}
```

- **Auth**: JWT + RBAC

**Request body**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| name | string | No | Role name |
| description | string | No | Description |
| status | int | No | 0=disabled, 1=enabled |
| permission_ids | array{int} | No | Array of permission IDs; when passed, syncs (overwrites) the role's permissions |

**Example response**:
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

### 6.4 Delete Role

```
DELETE /admin/v1/role/{id}
```

- **Auth**: JWT + RBAC
- **Sensitive operation**: requires password re-confirmation

**Request body**:
```json
{
  "password": "admin_password"
}
```

**Example response**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

On deletion, the role's associations with all permissions and users are automatically removed, then the role record is physically deleted.

## 7. Permission Management

Permissions use a tree structure (parent_id self-reference) and are divided into three types. The list endpoint returns the complete permission tree.

### 7.1 Permission Tree

```
GET /admin/v1/permission
```

- **Auth**: JWT + RBAC

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| id | string | hashid encrypted |
| parent_id | string | Parent permission hashid, "0" means root node |
| name | string | Permission name |
| slug | string | Permission identifier (route/button identifier) |
| type | int | 1=menu, 2=button, 3=API |
| icon | string | Menu icon (Material icon name) |
| path | string | Frontend route path |
| sort | int | Sort value (ascending) |
| children | array? | Child permission list (recursive), omitted when there are no children |

### 7.2 Create Permission

```
POST /admin/v1/permission
```

- **Auth**: JWT + RBAC

**Request body**:
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

| Field | Type | Required | Validation rules | Description |
|------|------|------|---------|------|
| parent_id | int | No | | Parent permission ID (raw INT type), defaults to 0 |
| name | string | Yes | max:50 | Permission name |
| slug | string | Yes | max:100 | Permission identifier |
| type | int | Yes | in:1,2,3 | 1=menu, 2=button, 3=API |
| icon | string | No | | Menu icon, defaults to empty |
| path | string | No | | Frontend route path, defaults to empty |
| sort | int | No | | Sort value, defaults to 0 |

**Example response**:
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

### 7.3 Update Permission

```
PUT /admin/v1/permission/{id}
```

- **Auth**: JWT + RBAC

**Request body**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| name | string | No | Permission name |
| icon | string | No | Icon |
| path | string | No | Route path |
| sort | int | No | Sort value |

### 7.4 Delete Permission

```
DELETE /admin/v1/permission/{id}
```

- **Auth**: JWT + RBAC
- **Sensitive operation**: requires password re-confirmation

**Request body**:
```json
{
  "password": "admin_password"
}
```

**Example response**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

On deletion, all child permissions are cascaded and deleted (records with `parent_id` = the current permission ID), and the association with all roles is removed.

## 8. System Config

System configs are uniquely identified by the `group` + `key` combination.

### 8.1 Config List

```
GET /admin/v1/config
```

- **Auth**: JWT + RBAC

**Query parameters**:

| Parameter | Type | Required | Default | Description |
|------|------|------|------|------|
| page | int | No | 1 | Page number |
| limit | int | No | 15 | Items per page |
| group | string | No | | Filter by config group |

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| id | string | hashid |
| group | string | Config group (e.g. `system`, `email`, `storage`) |
| key | string | Config key |
| value | string | Config value |
| type | string | Value type hint (`string`, `integer`, `boolean`, `json`, etc.) |
| description | string | Config description |

### 8.2 Create Config

```
POST /admin/v1/config
```

- **Auth**: JWT + RBAC

**Request body**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Field | Type | Required | Validation rules | Description |
|------|------|------|---------|------|
| group | string | Yes | max:100 | Config group |
| key | string | Yes | max:100 | Config key (unique within a group) |
| value | string | Yes | | Config value |
| type | string | No | | Value type, defaults to `string` |
| description | string | No | | Config description, defaults to empty |

**Example response**:
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

**Possible errors**:
- 422: config item already exists (same group + key)

### 8.3 Update Config

```
PUT /admin/v1/config/{id}
```

- **Auth**: JWT + RBAC

**Request body**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| value | string | No | Updated config value |
| type | string | No | Updated value type |
| description | string | No | Updated description text |

### 8.4 Delete Config

```
DELETE /admin/v1/config/{id}
```

- **Auth**: JWT + RBAC
- **Sensitive operation**: requires password re-confirmation

**Request body**:
```json
{
  "password": "admin_password"
}
```

Physically deletes the config record.

## 9. Operation Logs

Operation logs are read-only; the `OperationLog` middleware automatically writes a record on every POST/PUT/DELETE request, storing `user_id`, `action`, `method`, `path`, `ip`, `source`, and `input`.

### 9.1 Operation Log List

```
GET /admin/v1/log
```

- **Auth**: JWT + RBAC

**Query parameters**:

| Parameter | Type | Required | Default | Description |
|------|------|------|------|------|
| page | int | No | 1 | Page number |
| limit | int | No | 15 | Items per page |
| user_id | int | No | | Exact filter by user ID (raw INT type) |
| action | string | No | | Exact filter by action |
| path | string | No | | Fuzzy filter by request path |
| start_date | string | No | | Start date (Y-m-d format) |
| end_date | string | No | | End date (Y-m-d format) |

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| id | string | hashid |
| user_name | string | Operating username (via user relation; unauthenticated operations show "System") |
| action | string | Action description |
| method | string | HTTP method (POST/PUT/DELETE) |
| path | string | Request path |
| ip | string | Client IP |
| source | string | Request source |
| input | string | Request parameter JSON string (excluding files) |
| created_at | string | Operation time (datetime) |

## 10. Profile

Profile endpoints only require JWT authentication (no RBAC permission validation — the `AdminPermission` middleware should whitelist them).

### 10.1 Update Profile

```
PUT /admin/v1/profile
```

- **Auth**: JWT

**Request body**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| real_name | string | No | Real name |
| phone | string | No | Phone number (Encryptable encrypted storage) |
| email | string | No | Email (Encryptable encrypted storage) |

**Example response**:
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

`phone` and `email` are returned in plaintext in the response; `password` and `id_card` are stripped out.

### 10.2 Change Password

```
PUT /admin/v1/profile/password
```

- **Auth**: JWT

**Request body**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Field | Type | Required | Validation rules | Description |
|------|------|------|---------|------|
| old_password | string | Yes | | Current password |
| new_password | string | Yes | min:6, max:32 | New password |

**Example response**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Possible errors**:
- 422: please provide old and new passwords
- 422: old password incorrect
- 422: new password must be 6-32 characters

### 10.3 Logout

```
POST /admin/v1/profile/logout
```

- **Auth**: JWT

**Request body**: none (no requestBody, the token is read from the Authorization header)

**Example response**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Logout logic: decode the JWT to get the remaining validity (exp - now), write the md5 hash of the token to the Redis blacklist `jwt_blacklist:{md5}` with TTL = remaining validity. Blacklisted tokens are blocked by the `AdminAuth` middleware, returning 401.

Returns 401 when there is no token. When the token is expired/invalid (decode throws an exception), logout is still treated as successful.

## 11. Import/Export

### 11.1 Export Excel

```
POST /admin/v1/export/excel
```

- **Auth**: JWT + RBAC
- **Response type**: file download (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Request body**:
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

| Field | Type | Required | Default | Description |
|------|------|------|------|------|
| table | string | No | `admin_user` | Table to export. Supported: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | No | | Array of column field names to export; exports all columns of the table when empty |
| conditions | object | No | `{}` | Filter conditions, key-value pairs; values used in WHERE when non-empty |
| title | string | No | `数据导出` | Excel title (shown as the Sheet name) |

**Supported tables and columns**:

| table | Available columns |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Sensitive fields `phone`, `email`, `id_card` are automatically masked on export. Data is capped at 10000 rows. The first Excel row is frozen and auto-filter is enabled.

### 11.2 Export PDF

```
POST /admin/v1/export/pdf
```

- **Auth**: JWT + RBAC
- **Response type**: file download (`application/pdf`, A4 landscape)

**Request body**:
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

Or table mode:
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

| Field | Type | Required | Default | Description |
|------|------|------|------|------|
| type | string | No | `table` | Export type: `table` / `dashboard` |
| title | string | No | `数据导出` | PDF title |
| data | object | No | `{}` | Export data |

When `type=dashboard`, `data` must include a `stats` array (rendered as cards); when `type=table`, `data` must include `columns` and `rows` arrays.

The PDF template includes copyright info and the export timestamp.

### 11.3 Import Users (Excel)

```
POST /admin/v1/import/users
```

- **Auth**: JWT + RBAC
- **Request type**: `multipart/form-data` (file upload)

**Form fields**:

| Field | Type | Required | Description |
|------|------|------|------|
| file | file | Yes | `.xlsx` or `.xls` format |

**Excel column requirements**:

| Column | Required | Description |
|------|------|------|
| username | Yes | Username (unique) |
| password | Yes | Password (bcrypt hashed storage) |
| real_name | Yes | Real name |
| phone | No | Phone number |
| email | No | Email |
| status | No | Status, defaults to 1 |

Row 1 is the column header (case-insensitive); data starts at row 2.

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| total | int | Total rows (excluding header row) |
| success | int | Successfully imported count |
| failed | int | Failed count |
| errors | array | Failure details, each entry contains row (Excel row number) and reason (failure reason) |

## 12. File Upload

```
POST /admin/v1/upload
```

- **Auth**: JWT + RBAC
- **Request type**: `multipart/form-data`

**Form fields**:

| Field | Type | Required | Description |
|------|------|------|------|
| file | file | Yes | File to upload |

**Allowed file types**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Max file size**: 10MB

**Example response**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Files are stored in date-based directories under `public/upload/{Y-m-d}/`, with filenames of `md5(uniqid) + original extension`. `url` is a relative path relative to the site root.

**Possible errors**:
- 422: please select a file (none uploaded)
- 422: unsupported file type
- 422: file size must not exceed 10MB
- 500: file upload failed (invalid file)

## 13. Response Headers

All endpoints (injected at the global middleware layer) include the following response headers:

| Header | Description |
|----|------|
| `X-RateLimit-Limit` | Rate limit cap (count) |
| `X-RateLimit-Remaining` | Remaining request count |
| `X-RateLimit-Reset` | Rate limit window reset timestamp |
| `Retry-After` | Only returned when rate limited, suggested wait seconds |
| `X-Content-Type-Options` | `nosniff` (webman default, prevents MIME sniffing) |
| `X-Frame-Options` | `DENY` (provided by webman's CORS middleware/base config) |

Rate limit details:
- Default global limit: 60/min / IP+path
- Login endpoint `/api/v1/auth/login`: 10/min
- Register endpoint `/api/v1/auth/register`: 5/min
- Uses the Redis atomic sliding window algorithm (Lua ZSET) to avoid TOCTOU races
- Fails closed when Redis is unavailable: returns 503 (`Retry-After: 5`), requests are not let through

## 14. Data Analytics

All endpoints require authentication (`AdminAuth` + `AdminPermission`), MySQL real-time aggregation, 12 in total:

| Method | Path | Description |
|------|------|------|
| GET | /admin/v1/analytics/overview | Platform overview (today/last 7 days) |
| GET | /admin/v1/analytics/game-ranking | Game rankings (?days=7) |
| GET | /admin/v1/analytics/dau-trend | DAU trend (?days=30) |
| GET | /admin/v1/analytics/hourly-trend | Hourly trend |
| GET | /admin/v1/analytics/action-distribution | Action distribution |
| GET | /admin/v1/analytics/revenue | Revenue analysis |
| GET | /admin/v1/analytics/conversion | Game conversion rate |
| GET | /admin/v1/analytics/probability | Joint/conditional probability |
| GET | /admin/v1/analytics/retention | Retention analysis D1/D3/D7/D30 |
| GET | /admin/v1/analytics/funnel | Conversion funnel |
| GET | /admin/v1/analytics/arpu | ARPU/ARPPU trend |
| GET | /admin/v1/analytics/economy | Game currency economy metrics |

## 15. Ticket Management

All endpoints require authentication (`AdminAuth` + `AdminPermission`), 5 in total:

| Method | Path | Description |
|------|------|------|
| GET | /admin/v1/ticket/list | Ticket list (?page=&limit=&status=&type=) |
| GET | /admin/v1/ticket/{hashid} | Ticket detail (incl. replies) |
| POST | /admin/v1/ticket/{hashid}/reply | Reply to ticket |
| POST | /admin/v1/ticket/{hashid}/close | Close ticket |
| POST | /admin/v1/ticket/{hashid}/assign | Assign handler (admin_id) |

## 16. Authentication Flow

The complete authentication sequence:

```
1. Client requests POST /api/v1/captcha/generate
    ↓
   Server returns: key + base64 image + click target prompts

2. User clicks the target positions in the image, frontend/client collects click coordinates

3. Client requests POST /api/v1/auth/login
   (headers: Content-Type: application/json)
   body: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   Server:
   a. Parameter validation → 422
   b. Captcha validation → 422
   c. User credential validation → 401
   d. Account status check → 403
   e. Issue JWT (access + refresh) → 200
   f. Update last_login_at / last_login_ip
    ↓
   Client saves: access_token, refresh_token, expires_in

4. Subsequent requests carry the JWT
   header: Authorization: Bearer <access_token>
    ↓
   AdminAuth middleware:
   a. Extract Bearer token
   b. Check blacklist (Redis jwt_blacklist:{md5}) → 401
   c. Decode JWT, validate expiry → 401
   d. Set $request->adminId = sub field
    ↓
   AdminPermission middleware:
   a. Not logged in (adminId empty) → 401
   b. Resolve permission identifier for the resource route
   c. Query user roles → role permissions, match
   d. No permission → 403
    ↓
   Controller processes the request
    ↓
   Response + X-RateLimit-* headers

5. Refresh before Access Token expiry
   Client requests POST /api/v1/auth/refresh
   body: { refresh_token: "..." }
    ↓
   Server decodes refresh_token → issues new access + refresh
    ↓
   Client updates local tokens

6. Logout
   Client requests POST /admin/v1/profile/logout
   header: Authorization: Bearer <access_token>
    ↓
   Server:
   a. Decode JWT to get remaining TTL
   b. Write Redis blacklist: jwt_blacklist:{md5(token)} = 1, TTL = remaining validity
   c. Return success
```

### JWT Structure

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, default TTL 7200 seconds (controlled by the JWT config `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, default TTL 1209600 seconds (controlled by the JWT config `refresh_expire`, i.e. 14 days)

### Security Management

- Passwords are stored hashed with `PASSWORD_BCRYPT`
- Sensitive fields (phone, email, id_card) are transparently encrypted/decrypted at the database layer via `erikwang2013/encryptable`
- API-layer IDs are encrypted with `erikwang2013/hashids` for transport, avoiding exposure of raw snowflake ID sequences
- SecurityFilter globally scans for XSS, SQL injection, path traversal, and command injection; the same IP hitting 5 times/60s gets a temporary 15-minute blacklist
- Sensitive operations (deleting users, roles, permissions, configs) require password re-confirmation from the current logged-in user
- Concurrent session limit: max 3 valid tokens per user; when a 4th device logs in, the oldest token is forcibly blacklisted
- Account lockout: 5 consecutive failed logins trigger a 15-minute account lockout, returning 429 during the lockout

## 15. Deployment & Operations

### Docker Compose

A `docker-compose.yml` is provided at the project root, orchestrating 5 services (Nginx, webman app, MySQL, Redis, Elasticsearch). PHP is built via the `Dockerfile` (based on `php:8.3-cli`, with OPcache enabled).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` defines the GitHub Actions continuous integration pipeline:
- `php -l` syntax check
- PHPUnit unit tests
- `flutter analyze` static analysis

### Database Backup

The `database/backup/` directory provides backup and restore scripts:
- `backup.sh` — mysqldump + gzip compressed backup, auto-cleans backup files older than 30 days
- `restore.sh` — interactive restore, lists existing backups for the user to choose from

### Nginx Security Config

For production deployment, see `docs/nginx-security.conf` for reverse-proxy security hardening configuration.

## 16. Data Analytics

The analytics endpoints are provided by `AnalyticsController`, all based on MySQL real-time aggregation (`game_game_play_log` game behavior logs / `game_deposit_order` deposit orders); on database failure, empty data is returned instead of 500. Unless otherwise noted, JWT + RBAC authentication is required, and the response is uniformly wrapped as `{ "code": 0, "message": "success", "data": ... }`.

### 16.1 Platform Overview

```
GET /admin/v1/analytics/overview
```

**Response**: `today` / `week` each contain `dau` (active users), `revenue` (confirmed deposit total, string), `new_users` (new users).

### 16.2 Game Rankings

```
GET /admin/v1/analytics/game-ranking?days=7
```

**Response**: top 10 by game play count in descending order, each entry contains `game_id` (hashid), `name`, `plays`, `players`.

### 16.3 DAU Trend

```
GET /admin/v1/analytics/dau-trend?days=30
```

**Response**: `{ "date": active_count, ... }`, missing dates are padded with 0.

### 16.4 Hourly Trend

```
GET /admin/v1/analytics/hourly-trend?game_id=<hashid>
```

**Response**: `{ "0": count, ... "23": count }` across 24 hourly slots; when `game_id` is empty, all games are aggregated.

### 16.5 Action Distribution

```
GET /admin/v1/analytics/action-distribution?game_id=<hashid>&hours=24
```

**Response**: `{ "start": n, "end": n, "earn": n, "spend": n }` counts for four action types; `hours` capped at 168.

### 16.6 Revenue Overview

```
GET /admin/v1/analytics/revenue?days=7
```

**Response**: `{ "total": "total", "trend": { "date": "daily amount", ... } }`, only `status=confirmed` orders are counted.

### 16.7 Game Conversion Rate

```
GET /admin/v1/analytics/conversion?days=30
```

**Response**: each game contains `game_id` (hashid), `game_name`, `players` (deduplicated player count), `depositors` (deduplicated depositor count), `conversion_rate` (deposit conversion rate, 0~1).

### 16.8 Joint Probability

```
GET /admin/v1/analytics/probability?game_a=<hashid>&game_b=<hashid>
```

**Response**: `{ "joint": { "joint_probability": 0.12, "confidence": 0.3 } }` — the Jaccard coefficient (players of both games / union of players) and confidence (shared players / game A players).

### 16.9 Retention Analysis

```
GET /admin/v1/analytics/retention?days=30
```

**Response**: `{ "D1": "8.5%", "D3": "...", "D7": "...", "D30": "..." }` — next-day/3-day/7-day/30-day retention rates grouped by registration date.

### 16.10 Conversion Funnel

```
GET /admin/v1/analytics/funnel?days=30
```

**Response**: the four steps register → first deposit → first exchange → first game, with `step`, `count`, `rate` (percentage relative to registrations).

### 16.11 ARPU/ARPPU Trend

```
GET /admin/v1/analytics/arpu?days=30
```

**Response**: `{ "dates": [...], "arpu": [...], "arppu": [...] }` — daily revenue per user (ARPU) and revenue per paying user (ARPPU).

### 16.12 Game Economy Metrics

```
GET /admin/v1/analytics/economy
```

**Response**: a `currencies` array, each entry contains `game_name`, `currency`, `symbol`, `total_minted` (total minted), `total_burned` (total burned), `circulation` (in circulation), `inflation_rate` (inflation rate), computed with bcmath high precision.

## 17. Payment Management

Payment method management is provided by `PaymentController`; all 5 endpoints require JWT + RBAC authentication. `provider` whitelist: `stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash`. `config` is a JSON string of payment configuration (stored encrypted in the database).

| Method | Path | Description |
|------|------|------|
| GET | /admin/v1/payment/method/list | List payment methods (ascending by sort) |
| POST | /admin/v1/payment/method/toggle | Enable/disable a payment method |
| POST | /admin/v1/payment/method/create | Create a payment method |
| PUT | /admin/v1/payment/method/{hashid} | Update a payment method |
| DELETE | /admin/v1/payment/method/{hashid} | Delete a payment method (rejected if pending orders exist) |

### 17.1 List Payment Methods

```
GET /admin/v1/payment/method/list
```

- **Authentication**: JWT + RBAC

**Response example**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "name": "Stripe Credit Card",
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

| Field | Type | Description |
|------|------|------|
| id | string | Payment method ID (hashid encoded) |
| name | string | Payment method name |
| type | string | `fiat` (fiat currency) / `crypto` (cryptocurrency) |
| provider | string | Gateway provider: `stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash` |
| status | int | 1=enabled, 0=disabled |
| sort | int | Sort order (ascending) |
| countries | array{string} | Visible country code array (empty array = visible globally) |
| currency | string | Currency (e.g. USD/USDT), empty = no restriction |
| min_amount / max_amount | string | Amount range (string preserves precision), 0 = no limit |
| config | string? | Payment config JSON (encrypted; null if not set) |

### 17.2 Enable/Disable Payment Method

```
POST /admin/v1/payment/method/toggle
```

**Request body**:
```json
{
  "id": "a1b2c3d4",
  "status": 1
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| id | string | Yes | Payment method ID (hashid encoded) |
| status | int | Yes | 0=disabled, 1=enabled |

**Possible errors**:
- 422: validation failed (id/status missing or status not 0/1)
- 404: payment method not found

### 17.3 Create Payment Method

```
POST /admin/v1/payment/method/create
```

**Request body**:
```json
{
  "name": "USDT Cryptocurrency",
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

| Field | Type | Required | Validation | Description |
|------|------|------|---------|------|
| name | string | Yes | max:50 | Payment method name |
| type | string | Yes | in:fiat,crypto | Type: fiat/crypto |
| provider | string | Yes | in:stripe,paypal,nowpayments,coinbase,skrill,neteller,paysafecard,paytm,mercadopago,astropay,paypay,kakaopay,gcash | Gateway provider whitelist |
| status | int | Yes | in:0,1 | Status |
| sort | int | No | integer,min:0 | Sort order, default 0 |
| countries | array{string} | No | max:2 | Visible country codes, empty = global |
| currency | string | No | max:10 | Currency, default empty |
| min_amount / max_amount | string | No | numeric,min:0 | Amount range, default "0" |
| config | string | No | | Payment config JSON (encrypted), empty string stored as NULL |

**Response example**:
```json
{
  "code": 0,
  "message": "created successfully",
  "data": { "id": "e5f6g7h8" }
}
```

**Possible errors**:
- 422: validation failed

### 17.4 Update Payment Method

```
PUT /admin/v1/payment/method/{hashid}
```

- **Path parameter**: `{hashid}` is the hashid-encoded payment method ID
- **Request body**: same as create (17.3), all fields optional, only provided fields are updated

**Possible errors**:
- 404: payment method not found
- 422: validation failed

### 17.5 Delete Payment Method

```
DELETE /admin/v1/payment/method/{hashid}
```

- **Path parameter**: `{hashid}` is the hashid-encoded payment method ID

**Possible errors**:
- 404: payment method not found
- 422: pending deposit orders (status=pending) exist, cannot delete
