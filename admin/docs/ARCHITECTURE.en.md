# Architecture Diagrams and Business Logic Diagrams
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE.md) · **English** · [한국어](ARCHITECTURE.ko.md) · [Русский](ARCHITECTURE.ru.md) · [Deutsch](ARCHITECTURE.de.md) · [Français](ARCHITECTURE.fr.md) · [Español](ARCHITECTURE.es.md) · [Português](ARCHITECTURE.pt.md) · [हिन्दी](ARCHITECTURE.hi.md) · [العربية](ARCHITECTURE.ar.md) · [বাংলা](ARCHITECTURE.bn.md) · [Bahasa Indonesia](ARCHITECTURE.id.md) · [日本語](ARCHITECTURE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> The Mermaid diagrams below render automatically in GitHub / GitLab / VS Code. For other environments, use the [Mermaid Live Editor](https://mermaid.live/).

---

## 1. System Topology Architecture

```mermaid
flowchart TB
    subgraph "客户端层"
        A1["Flutter Web<br/>PC 管理后台<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>手机/平板客户端"]
    end

    subgraph "网关/边缘层 (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>反向代理 + HTTPS + Gzip<br/>静态文件服务"]
    end

    subgraph "应用层 (webman v2)"
        C1["AdminAuth 中间件<br/>JWT 验证"]
        C2["AdminPermission 中间件<br/>RBAC 权限校验"]
        C3["管理端 Controller<br/>Dashboard / User / Role / Permission / Payment"]
        C4["公开 Controller v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "存储层"
        D1[("MySQL 8.0<br/>主存储<br/>表前缀 game_")]
        D2[("Elasticsearch<br/>全文检索<br/>索引前缀 game_")]
        D3[("Redis<br/>Session / 缓存<br/>Captcha 存储")]
    end

    subgraph "外部"
        E1["DevEco Studio<br/>HarmonyOS 构建"]
        E2["Flutter SDK<br/>Web 构建"]
    end

    A1 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    A2 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    B1 --> C1
    C1 --> C2
    C2 --> C3
    B1 --> C4
    C3 --> C5
    C4 --> C5
    C3 --> D1
    C4 --> D1
    C3 --> D2
    C4 --> D2
    C1 --> D3

    style A1 fill:#1677FF,color:#fff
    style A2 fill:#1677FF,color:#fff
    style B1 fill:#722ED1,color:#fff
    style C1 fill:#FA8C16,color:#fff
    style C2 fill:#FA8C16,color:#fff
    style C3 fill:#52C41A,color:#fff
    style C4 fill:#52C41A,color:#fff
    style C5 fill:#52C41A,color:#fff
    style D1 fill:#1890FF,color:#fff
    style D2 fill:#1890FF,color:#fff
    style D3 fill:#1890FF,color:#fff
```

---

## 2. Backend Layered Architecture

```mermaid
flowchart TD
    subgraph "路由层 Route Layer"
        R1["config/route.php<br/>URL → Controller 映射"]
    end

    subgraph "中间件层 Middleware Layer"
        M_RL["RateLimit<br/>Redis 滑动窗口限流<br/>X-RateLimit 响应头"]
        M_SF["SecurityFilter<br/>攻击检测拦截<br/>XSS/SQL注入/路径遍历/CSRF"]
        M1["AdminAuth<br/>JWT Token 校验<br/>注入 adminId"]
        M2["AdminPermission<br/>RBAC 鉴权<br/>method.path 匹配<br/>Redis 60s 缓存权限"]
    end

    subgraph "控制器层 Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + 搜索 + 分页"]
        CT3["RoleController<br/>CRUD + 权限同步"]
        CT4["PermissionController<br/>CRUD + 树构建"]
        CT5["DashboardController<br/>统计/趋势/分布"]
        CT6["ExportController<br/>Excel/PDF 导出"]
        CT7["CaptchaController<br/>验证码生成/校验"]
        CT8["AuthController<br/>登录/注册/刷新"]
        CT9["AnalyticsController<br/>12 个数据分析端点<br/>总览/排行/概率/留存/漏斗/ARPU"]
    end

    subgraph "服务层 Service Layer"
        S1["HashidsService<br/>ID 编解码"]
        S2["SnowflakeService<br/>全局唯一 ID 生成"]
        S3["EncryptionService<br/>加解密 + 脱敏"]
        S4["GameDashboardService<br/>总览/排行/DAU/小时/行为分布<br/>MySQL 实时聚合，DB 故障返回空数据"]
        S5["DepositLogService<br/>营收总览/游戏转化率<br/>confirmed 订单统计"]
        S6["ProbabilityService<br/>联合/条件概率<br/>SQL 构建器（转义/引用/IN）"]
    end

    subgraph "模型层 Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "驱动层 Driver Layer"
        D1["MySQL PDO"]
        D2["Elasticsearch HTTP"]
        D3["Redis"]
    end

    R1 --> M_SF --> M_RL --> M1
    M1 --> M2
    M2 --> CT2 & CT3 & CT4 & CT5 & CT6 & CT9
    M_RL --> CT7 & CT8
    CT1 -.->|extends| CT2 & CT3 & CT4 & CT5 & CT6 & CT9
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 & CT9 --> S1 & S2 & S3
    CT9 --> S4 & S5 & S6
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 & CT9 --> MD1 & MD2 & MD3 & MD4 & MD5
    MD1 & MD2 & MD3 & MD4 & MD5 --> D1
    S4 & S5 & S6 --> D1
    MD1 --> D2
    CT7 --> D3

    style R1 fill:#722ED1,color:#fff
    style M_SF fill:#FF4D4F,color:#fff
    style M_RL fill:#EB2F96,color:#fff
    style M1 fill:#FA8C16,color:#fff
    style M2 fill:#FA8C16,color:#fff
    style CT1 fill:#1677FF,color:#fff
    style CT9 fill:#1677FF,color:#fff
    style S4 fill:#13C2C2,color:#fff
    style S5 fill:#13C2C2,color:#fff
    style S6 fill:#13C2C2,color:#fff
```

---

## 3. Request Lifecycle

```mermaid
sequenceDiagram
    participant C as 客户端
    participant N as Nginx
    participant MW_SF as SecurityFilter
    participant MW_RL as RateLimit
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL
    participant OPLOG as OperationLog

    C->>N: HTTPS 请求<br/>POST /admin/v1/*
    N->>MW_SF: 转发

    alt 非标准 HTTP 方法 (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else 方法合法 (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: 方法白名单检查通过
    end

    alt 攻击检测触发
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: 通过

    alt 限流触发
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW1: 通过

    alt Token 缺失或无效
        MW1-->>C: 401 Unauthorized
    else Token 有效
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt 无权限
        MW2-->>C: 403 Forbidden
    else 有权限
        MW2->>CTL: 进入控制器
    end

    CTL->>CTL: 参数验证 (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt 敏感操作 (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt 密码错误
            CTL-->>C: 422 密码验证失败
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast 自动解密
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: 构建响应 JSON
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: 记录操作日志 (POST/PUT/DELETE)
```

---

## 4. Authentication and Captcha Flow

```mermaid
sequenceDiagram
    participant U as 用户
    participant CL as 客户端
    participant SV as 服务端
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === 第一步: 获取验证码 ===
    CL->>SV: POST /api/v1/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: 生成 300×200 背景图
    CAP->>CAP: 随机放置 N 个中文目标
    CAP->>CAP: 生成 key, 存储 targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === 第二步: 用户点击 ===
    CL->>CL: 渲染验证码图片
    CL->>CL: 提示 "请按顺序点击: 树 → 鸟 → 花"
    U->>CL: 依次点击图中文字位置
    CL->>CL: 收集 clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === 第三步: 登录 ===
    CL->>SV: POST /api/v1/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt 验证码错误
        CAP-->>SV: false
        SV-->>CL: 422 验证码错误
    else 验证码正确
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt 凭证错误
            SV-->>CL: 401 用户名或密码错误
        else 凭证正确
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === 后续请求 ===
    CL->>SV: GET /admin/v1/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC Permission Model

```mermaid
flowchart LR
    subgraph "用户 User"
        U1["admin<br/>(超级管理员)"]
        U2["editor<br/>(编辑)"]
        U3["viewer<br/>(只读)"]
    end

    subgraph "角色 Role"
        R1["super_admin<br/>权限标识: *"]
        R2["editor<br/>权限标识: get.*, post.*"]
        R3["viewer<br/>权限标识: get.*"]
    end

    subgraph "权限 Permission (树)"
        P1["dashboard<br/>type=1 菜单"]
        P2["user<br/>type=1 菜单"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 按钮"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (全权限)"| P1 & P2 & P3 & P4 & P5 & P6
    R2 --> P1 & P2 & P3 & P4
    R3 --> P1 & P3

    P2 --> P3 & P4 & P5
    P1 --> P6

    style U1 fill:#1677FF,color:#fff
    style R1 fill:#FA8C16,color:#fff
    style P1 fill:#52C41A,color:#fff
```

```mermaid
flowchart TD
    subgraph "权限类型"
        T1["type=1 菜单<br/>控制侧边栏显示/隐藏"]
        T2["type=2 按钮<br/>控制页面操作按钮"]
        T3["type=3 API<br/>控制接口访问"]
    end

    subgraph "权限标识格式"
        F1["{method}.{path}<br/>例: get.admin/user<br/>例: post.admin/user<br/>例: delete.admin/role"]
    end

    subgraph "判定流程"
        J1["提取 Token → adminId"]
        J2["查找用户角色"]
        J3["收集所有权限 slug"]
        J4["构造 method.path"]
        J5{"匹配?"}
        J6["放行"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"是 / slug=*"| J6
        J5 -->|否| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. ID Full Lifecycle

```mermaid
flowchart LR
    subgraph "1. 生成"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>例: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. 存储"
        S1["MySQL game_* 表<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["敏感字段<br/>encryptable cast<br/>AES-128-ECB 加密"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. 传输"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid 字符串<br/>例: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. 反向解码"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Data Encryption Layers

```mermaid
flowchart TB
    subgraph "传输层加密 (encryption)"
        E1["客户端发送敏感数据"]
        E2["AES-256-CBC 加密"]
        E3["API 传输密文"]
        E4["服务端解密处理"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "存储层加密 (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["写入: 自动加密"]
        D3["MySQL VARCHAR(500)<br/>存储密文"]
        D4["读取: 自动解密"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "展示层脱敏 (mask)"
        M1["phone: 138****1234"]
        M2["email: a***@example.com"]
        M3["id_card: ********"]
        D4 --> M1 & M2 & M3
    end

    E4 --> D1

    style E2 fill:#1677FF,color:#fff
    style D2 fill:#FA8C16,color:#fff
    style M1 fill:#52C41A,color:#fff
```

---

## 8. Database ER Relationships

```mermaid
erDiagram
    game_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "加密"
        VARCHAR phone "加密"
        VARCHAR id_card "加密"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "软删除"
    }

    game_admin_role {
        BIGINT id PK "Snowflake"
        VARCHAR name
        VARCHAR slug UK
        VARCHAR description
        TINYINT status
        DATETIME created_at
        DATETIME updated_at
    }

    game_admin_permission {
        BIGINT id PK "Snowflake"
        BIGINT parent_id FK "自引用"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1菜单2按钮3API"
        VARCHAR icon
        VARCHAR path
        INT sort
        DATETIME created_at
        DATETIME updated_at
    }

    game_admin_user_role {
        BIGINT user_id PK_FK
        BIGINT role_id PK_FK
    }

    game_admin_role_permission {
        BIGINT role_id PK_FK
        BIGINT permission_id PK_FK
    }

    game_operation_log {
        BIGINT id PK "Snowflake"
        BIGINT user_id FK
        VARCHAR action
        VARCHAR method
        VARCHAR path
        VARCHAR ip
        VARCHAR source "来源端"
        TEXT input "脱敏"
        DATETIME created_at
    }

    game_system_config {
        BIGINT id PK "Snowflake"
        VARCHAR group
        VARCHAR key
        TEXT value
        VARCHAR type
        VARCHAR description
        DATETIME created_at
        DATETIME updated_at
    }

    game_admin_user ||--o{ game_admin_user_role : "user_id"
    game_admin_role ||--o{ game_admin_user_role : "role_id"
    game_admin_role ||--o{ game_admin_role_permission : "role_id"
    game_admin_permission ||--o{ game_admin_role_permission : "permission_id"
    game_admin_user ||--o{ game_operation_log : "user_id"
    game_admin_permission ||--o{ game_admin_permission : "parent_id"
```

---

## 9. Export Business Flow

```mermaid
sequenceDiagram
    participant C as 客户端
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as 文件系统

    Note over C,FS: === Excel 导出 ===
    C->>CTL: POST /admin/v1/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: 数据
    CTL->>CTL: 解密敏感字段
    CTL->>CTL: 脱敏处理 (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet 构建<br/>表头蓝底白字<br/>数据行细边框<br/>冻结首行<br/>自动筛选
    CTL->>FS: 写入 runtime/tmp/export_*.xlsx
    CTL-->>C: 文件下载

    Note over C,FS: === PDF 导出 ===
    C->>CTL: POST /admin/v1/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>页头: 标题+版权+时间<br/>内容: 表格或卡片<br/>页脚: 不可移除版权
    CTL->>CTL: Dompdf 渲染 A4 横向
    CTL->>FS: 写入 runtime/tmp/export_*.pdf
    CTL-->>C: 文件下载
```

---

## 10. Flutter Web Component Tree

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["登录表单<br/>用户名/密码/验证码"]
    LF --> CAPTCHA["点击验证码组件<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>点击标记 Circle"]

    DB --> SIDEBAR["侧边栏 NavigationDrawer<br/>可折叠 64px / 240px<br/>仪表盘/用户/角色/配置/日志/支付"]
    DB --> HEADER["顶栏 56px<br/>折叠按钮 + 用户菜单<br/>退出登录 AlertDialog"]
    DB --> CONTENT["内容区"]
    CONTENT --> DASH["DashboardPage<br/>统计卡片 GridView<br/>趋势折线图 LineChart<br/>分布饼图 PieChart<br/>最近操作 ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS Page Routing

```mermaid
flowchart LR
    EA["EntryAbility<br/>启动"]
    EA -->|"无 Token"| LP["LoginPage<br/>登录页"]
    EA -->|"有 Token"| DP["DashboardPage<br/>仪表盘"]

    LP -->|"登录成功<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>用户列表"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>个人中心"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>用户详情/新增/编辑"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"退出登录<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Security Defense-in-Depth Overview

```mermaid
flowchart TB
    subgraph "第1层: 人机验证"
        L1["点击验证码<br/>Click Captcha<br/>登录/注册强制"]
    end

    subgraph "第2层: 操作确认"
        L2["密码二次确认<br/>confirmPassword()<br/>DELETE 操作必须"]
    end

    subgraph "第3层: 传输安全"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "第4层: 身份认证"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "第5层: 权限鉴权"
        L5["RBAC<br/>method.path 粒度<br/>超级管理员 * "]
    end

    subgraph "第6层: 数据保护"
        L6["接口 ID: Hashids 加密<br/>请求体: Encryption 加密<br/>存储层: Encryptable 加密<br/>导出: 脱敏+版权"]
    end

    subgraph "第7层: 审计追溯"
        L7["OperationLog<br/>记录所有操作<br/>用户/IP/时间/来源端/参数"]
    end

    L1 --> L2 --> L3 --> L4 --> L5 --> L6 --> L7

    style L1 fill:#1677FF,color:#fff
    style L2 fill:#1677FF,color:#fff
    style L3 fill:#FA8C16,color:#fff
    style L4 fill:#FA8C16,color:#fff
    style L5 fill:#52C41A,color:#fff
    style L6 fill:#722ED1,color:#fff
    style L7 fill:#FF4D4F,color:#fff
```

---

## 13. Deployment Topology

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web 服务器"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["静态文件<br/>Flutter Web build/"]
    end

    subgraph "应用服务器 (可横向扩展)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "数据层"
        MYSQL["MySQL 8.0<br/>主从复制<br/>game_ 前缀"]
        ES["Elasticsearch 8.x<br/>3 节点集群<br/>game_ 前缀"]
        REDIS["Redis 7.x<br/>哨兵模式<br/>poster:captcha:*"]
    end

    subgraph "监控"
        MON["Grafana<br/>+ Prometheus"]
    end

    DNS --> NGX
    NGX --> STA
    NGX --> WM1 & WM2 & WM3
    WM1 & WM2 & WM3 --> MYSQL
    WM1 & WM2 & WM3 --> ES
    WM1 & WM2 & WM3 --> REDIS
    WM1 & WM2 & WM3 --> MON

    style NGX fill:#722ED1,color:#fff
    style WM1 fill:#1677FF,color:#fff
    style WM2 fill:#1677FF,color:#fff
    style WM3 fill:#1677FF,color:#fff
    style MYSQL fill:#1890FF,color:#fff
    style ES fill:#1890FF,color:#fff
    style REDIS fill:#1890FF,color:#fff
```
