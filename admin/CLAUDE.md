# 开放管理后台 (open-admin)

基于 webman v2 + Flutter 的全栈管理后台系统。

## 版权声明

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **不可修改、不可移除、不可逆。** 所有新建文件必须包含上述版权声明作为文件头注释。

## 功能清单

| 域 | 功能 |
|----|------|
| 认证 | 登录/注册/刷新/登出 + 验证码 + 账号锁定 + 会话限制 |
| 仪表盘 | 实时统计/趋势/分布/日志（Redis 5m 缓存）|
| 数据分析 | 12 个端点：总览/排行/DAU/小时/行为分布/营收/转化/概率/留存/漏斗/ARPU/经济指标（MySQL 实时聚合）|
| 工单 | 列表/详情/回复/关闭/指派（5 个端点，AdminAuth + AdminPermission）|
| 用户 | CRUD + 批量删除/启禁用 + Excel 导入 |
| 角色权限 | CRUD + 权限树 + RBAC method.path 鉴权 |
| 系统配置 | 键值对 CRUD |
| CDN 管理 | 五厂商 CRUD + 启停 + 连通测试（凭据加密存储，service 纯 DB 读取）|
| 操作审计 | 日志查询 + 8 平台来源端自动检测 |
| 文件 | 上传 + Excel/PDF 导出（敏感数据脱敏）|
| 安全 | 18 层纵深防御（XSS/SQL注入/CSRF/限流/CSP...）|
| 运维 | 健康检查/Prometheus 指标/API 文档/security.txt + Docker + CI/CD |

## 技术栈

### 后端
- PHP 8.3+, webman v2 (workerman/webman)
- 数据库: MySQL 8.0+，表前缀 `game_`
- 主键: BIGINT 非自增，由 `erikwang2013/snowflake-php` 生成
- API 层 ID 加解密: `erikwang2013/hashids`
- JWT 认证: `erikwang2013/jwt-webman`
- API 敏感数据加解密: `erikwang2013/encryption`
- 数据库敏感字段加解密: `erikwang2013/encryptable`
- ES 同步与查询: `erikwang2013/webman-scout`
- 国家旗帜: `erikwang2013/season`

### 前端
- Flutter 3.x，源码目录 `apps/flutter/`
- Web 端按 PC 管理后台风格设计（非移动端 App 风格）
- 支持客户端和管理员端
- HarmonyOS ArkTS，源码目录 `apps/harmonyos/`

## 项目结构

```
open-admin/
├── app/
│   ├── admin/controller/       # 管理端控制器 (30 个)
│   │   ├── BaseController.php      # 基础控制器
│   │   ├── DashboardController.php # 仪表盘（Redis 缓存）
│   │   ├── AnalyticsController.php # 数据分析（12 个端点）
│   │   ├── UserController.php      # 用户 CRUD + 批量操作
│   │   ├── RoleController.php      # 角色 CRUD
│   │   ├── PermissionController.php# 权限 CRUD
│   │   ├── ConfigController.php    # 系统配置 CRUD
│   │   ├── LogController.php       # 操作日志查询
│   │   ├── ProfileController.php   # 个人中心 + 登出
│   │   ├── ExportController.php    # Excel/PDF 导出
│   │   ├── ImportController.php    # Excel 导入用户
│   │   ├── UploadController.php    # 文件上传
│   │   ├── HealthController.php    # 健康检查
│   │   ├── DocsController.php      # OpenAPI 文档
│   │   └── MetricsController.php   # Prometheus 监控指标
│   ├── api/v1/controller/      # API v1 控制器（版本头控制）
│   │   ├── CaptchaController.php
│   │   └── AuthController.php
│   ├── common/                 # 公共工具类
│   │   ├── HashidsService.php
│   │   ├── SnowflakeService.php
│   │   └── EncryptionService.php
│   ├── middleware/             # 中间件（7 个）
│   │   ├── Cors.php            # 跨域（全局）
│   │   ├── SecurityFilter.php  # 攻击拦截（全局：XSS/SQL注入/路径遍历/命令注入/CSRF）
│   │   ├── RateLimit.php       # Redis 限流（全局，Lua 原子化）
│   │   ├── ApiVersion.php      # API 版本校验
│   │   ├── AdminAuth.php       # JWT 认证 + 黑名单
│   │   ├── AdminPermission.php # RBAC 权限校验（Redis 60s 缓存）
│   │   └── OperationLog.php    # 操作日志自动记录（含来源端检测）
│   ├── model/                  # 数据模型
│   ├── queue/                  # 队列任务
│   └── process/                # 进程 (Http, Monitor)
├── common/                     # 业务服务（数据分析）
│   └── service/
│       ├── GameDashboardService.php  # 总览/排行/DAU/小时/行为分布
│       ├── DepositLogService.php     # 营收总览/游戏转化率
│       └── ProbabilityService.php    # 联合/条件概率（SQL 构建器）
├── apps/
│   ├── flutter/                # Flutter Web 管理后台
│   │   └── lib/app/
│   │       ├── pages/          # 6 个完整页面
│   │       │   ├── dashboard/  # 仪表盘
│   │       │   ├── login/      # 登录
│   │       │   ├── user/       # 用户管理
│   │       │   ├── role/       # 角色权限
│   │       │   ├── config/     # 系统配置
│   │       │   ├── log/        # 操作日志
│   │       │   └── profile/    # 个人中心
│   │       ├── services/       # ApiService + AuthService
│   │       ├── layouts/        # 响应式布局
│   │       └── theme/          # Material 3 主题
│   └── harmonyos/              # HarmonyOS 客户端
├── config/                     # 配置文件
│   ├── route.php               # 路由 + API 版本策略
│   └── middleware.php           # 全局中间件注册
├── database/
│   └── backup/                 # 数据库备份脚本
│       ├── backup.sh           # mysqldump+gzip，30天保留
│       └── restore.sh          # 交互式恢复
├── docs/                       # 文档
│   ├── ARCHITECTURE.md         # Mermaid 架构图
│   ├── DESIGN.md               # 设计文档
│   ├── SECURITY.md             # 安全架构设计
│   ├── API.md                  # API 参考文档
│   ├── nginx-security.conf     # Nginx 安全参考配置
│   ├── diagrams/               # 分解架构图
│   └── superpowers/            # 规范与计划
│       ├── specs/              # 设计规范
│       └── plans/              # 实现计划
├── public/                     # 公共入口
├── runtime/                    # 运行时文件
├── tests/                      # 测试
├── vendor/                     # Composer 依赖
├── CLAUDE.md                   # 本文件
├── README.md                   # 中文说明
├── README_EN.md                # 英文说明
├── .env                        # 环境变量（不纳入版本控制）
├── .env.example                # 环境变量模板
├── .env.docker                 # Docker 环境变量
├── composer.json               # PHP 依赖
├── Dockerfile                  # Docker 构建
├── docker-compose.yml          # Docker 编排
└── .github/
    └── workflows/
        └── ci.yml              # CI/CD 流水线（PHP语法+PHPUnit+Flutter analyze）
```

## 中间件执行链

```
全局:  Cors → SecurityFilter(方法检查→405) → RateLimit → {路由中间件}
/admin: Cors → SecurityFilter(方法检查→405) → RateLimit → AdminAuth → AdminPermission → OperationLog → Controller
/api:   Cors → SecurityFilter(方法检查→405) → RateLimit → ApiVersion → Controller
/health: Cors → SecurityFilter(方法检查→405) → RateLimit → Controller
```

## 安全增强

- **HTTP 方法限制**：SecurityFilter 仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD，非标准方法返回 405
- **CSP 头**：Content-Security-Policy + X-Permitted-Cross-Domain-Policies 注入所有响应
- **账号锁定**：连续 5 次登录失败，账号锁定 15 分钟
- **并发会话限制**：同一用户最多 3 个有效 Token，超出时最旧 Token 加入黑名单
- **security.txt**：`/.well-known/security.txt` RFC 9116 端点
- **Nginx 安全配置**：`docs/nginx-security.conf` 反向代理安全加固参考

## API 版本策略

版本通过请求头 `API-Version` 控制（默认 `v1`），不在 URL 中体现：

```bash
curl -H "API-Version: v1" http://localhost:8787/api/auth/login
```

新增版本只需创建 `app/api/{version}/controller/` 目录并注册到 `ApiVersion` 中间件。

## 限流策略

Redis 滑动窗口（Lua 原子化），默认 60 次/分钟/IP/路由：
- 登录: 10 次/分钟
- 注册: 5 次/分钟
- 响应头: `X-RateLimit-Limit/Remaining/Reset`，超限附加 `Retry-After`

## 代码规范

### PHP
- 全局函数/类引用不加前置 `\`，使用 `use` 导入
- 配置文件必须包含中文注释说明每个配置项的含义
- 所有新建 `.php` 文件头必须包含版权声明

### 数据库
- 表前缀: `game_`
- 主键 `id`: BIGINT 类型，非自增，由 snowflake 生成
- 敏感字段使用 `erikwang2013/encryptable` trait 自动加解密
- 迁移文件使用 SQL 格式

### Flutter
- Web 端布局使用 PC 管理后台风格（侧边栏 + 顶栏 + 内容区）
- 使用 GetX 状态管理，`ApiService` 单例（Dio + JWT 拦截器）
- Token 持久化使用 `shared_preferences`
- 响应式断点: 移动端 (< 768px) 与桌面端 (>= 768px)

### HarmonyOS
- 使用 `@ohos.net.http` 原生 HTTP 客户端
- Token 无感刷新：401 时自动调用 `/api/auth/refresh`
- 刷新失败自动重定向登录页

## 部署

### Docker Compose（推荐生产环境）

项目根目录 `docker-compose.yml` 编排 5 个服务：

| 服务 | 说明 |
|------|------|
| `nginx` | Nginx 反向代理（80/443），静态文件服务 |
| `app` | webman PHP 8.3 应用，`Dockerfile` 构建（含 OPcache） |
| `mysql` | MySQL 8.0，数据卷持久化 |
| `redis` | Redis 7 Alpine，缓存/限流/Session |
| `elasticsearch` | Elasticsearch 8.x，全文检索 |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` 定义 GitHub Actions 流水线：

- PHP 语法检查 (`php -l`)
- PHPUnit 单元测试
- Flutter 静态分析 (`flutter analyze`)

### 数据库备份

`database/backup/backup.sh` — mysqldump + gzip，自动清理 30 天前旧备份。
`database/backup/restore.sh` — 交互式恢复，列出可用备份供选择。

### 监控

`GET /metrics` 端点（`MetricsController`）输出 Prometheus text format，包含 5 个 gauge 指标：
- `openadmin_http_requests_total` — 请求总数
- `openadmin_active_users` — 活跃用户数
- `openadmin_db_connection_status` — 数据库连接状态 (0/1)
- `openadmin_redis_connection_status` — Redis 连接状态 (0/1)
- `openadmin_memory_usage_bytes` — 内存使用量
