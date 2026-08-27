# 开放管理后台 — 设计文档
<!-- lang-nav -->

Languages: [中文](DESIGN.md) · [English](DESIGN.en.md) · [한국어](DESIGN.ko.md) · [Русский](DESIGN.ru.md) · [Deutsch](DESIGN.de.md) · [Français](DESIGN.fr.md) · [Español](DESIGN.es.md) · [Português](DESIGN.pt.md) · [हिन्दी](DESIGN.hi.md) · [العربية](DESIGN.ar.md) · **বাংলা** · [Bahasa Indonesia](DESIGN.id.md) · [日本語](DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> বিস্তারিত Mermaid আর্কিটেকচার ডায়াগ্রামের জন্য [ARCHITECTURE.md](ARCHITECTURE.bn.md) দেখুন (GitHub/GitLab/VS Code-এ স্বয়ংক্রিয় রেন্ডার হয়)।

## 1. সিস্টেম আর্কিটেকচার

> **ফিচার তালিকা**: অথেনটিকেশন(login/register/refresh/logout + অ্যাকাউন্ট লক + সেশন সীমা) | ড্যাশবোর্ড(Redis ক্যাশ) | ইউজার CRUD+ব্যাচ+ইমপোর্ট | রোল পারমিশন(RBAC) | সিস্টেম কনফিগ | অপারেশন অডিট(৮ প্ল্যাটফর্ম সোর্স) | ফাইল(আপলোড+এক্সপোর্ট+মাস্কিং) | নিরাপত্তা(১৮ লেয়ার ডিফেন্স) | অপারেশন(health/metrics/docs/Docker/CI)

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

## 2. ব্যাকএন্ড আর্কিটেকচার

### 2.1 লেয়ারড ডিজাইন

| লেয়ার | ডিরেক্টরি | দায়িত্ব |
|---|------|------|
| রাউটিং | `config/route.php` | URL থেকে কন্ট্রোলার ম্যাপিং, মিডলওয়্যার বাইন্ডিং, ভার্সনযুক্ত রাউটিং |
| মিডলওয়্যার | `app/middleware/` | অ্যাটাক ব্লক (SecurityFilter), রেট লিমিট (RateLimit), অথেনটিকেশন (JWT), অথোরাইজেশন (RBAC), API ভার্সন (ApiVersion) |
| কন্ট্রোলার | ৩০টি: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs/Metrics/Analytics/Game/Payment/Withdraw... (প্রশাসনিক) + Captcha/Auth (API v1) | রিকোয়েস্ট প্যারামিটার ভ্যালিডেশন, ব্যবসায়িক লজিক কল, রেসপন্স ফরম্যাটিং |
| ব্যবসায়িক সার্ভিস | `common/service/` | ডেটা অ্যানালাইসিস: GameDashboardService (ওভারভিউ/র্যাঙ্কিং/ট্রেন্ড), DepositLogService (রেভিনিউ/কনভার্সন), ProbabilityService (জয়েন্ট/কন্ডিশনাল প্রোবাবিলিটি, SQL বিল্ডার); DB ব্যর্থ হলে এরর নয় বরং খালি ডেটা ফেরত |
| ডেটা মডেল | `app/model/` | ORM ম্যাপিং, রিলেশন, ফিল্ড এনক্রিপশন/ডিক্রিপশন |
| কমন ইউটিলিটি | `app/common/` | Hashids, Snowflake, Encryption সার্ভিস |

### 2.2 রিকোয়েস্ট লাইফসাইকেল

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

### 2.3 ID লাইফসাইকেল

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 ডেটা এনক্রিপশন সিস্টেম

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-128-ECB，独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. ডেটাবেস ডিজাইন

### 3.1 ER সম্পর্ক

```
game_admin_user ──┬── game_admin_user_role ──┬── game_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    game_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    game_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           game_operation_log
             (操作日志)

game_system_config (系统配置) — 独立表
```

### 3.2 মূল টেবিল স্ট্রাকচার

| টেবিল নাম | ফিল্ড সংখ্যা | বিবরণ |
|------|-------|------|
| `game_admin_user` | 14 | প্রশাসনিক ইউজার, phone/email/id_card এনক্রিপ্টেড স্টোর, সফট ডিলিট সাপোর্ট |
| `game_admin_role` | 7 | রোল, slug ইউনিক |
| `game_admin_permission` | 10 | পারমিশন ট্রি (parent_id সেলফ-রেফারেন্স), type: 1=মেনু 2=বাটন 3=API |
| `game_admin_user_role` | 2 | ইউজার-রোল ম্যানি-টু-ম্যানি ইন্টারমিডিয়েট টেবিল |
| `game_admin_role_permission` | 2 | রোল-পারমিশন ম্যানি-টু-ম্যানি ইন্টারমিডিয়েট টেবিল |
| `game_system_config` | 8 | কী-ভ্যালু কনফিগ, group+key যৌথ ইউনিক |
| `game_operation_log` | 9 | অপারেশন অডিট লগ (source সোর্স সহ) |

### 3.3 প্রাইমারি কী স্ট্যান্ডার্ড

- টাইপ: `BIGINT UNSIGNED NOT NULL`
- বৈশিষ্ট্য: **নন-অটোইনক্রিমেন্ট**, Snowflake অ্যালগরিদম দিয়ে অ্যাপ্লিকেশন লেয়ারে তৈরি
- সুবিধা: গ্লোবাল ইউনিক, ডিস্ট্রিবিউটেড-ফ্রেন্ডলি, ট্রেন্ডিং ইনক্রিমেন্ট ইন্ডেক্সের জন্য ভালো, ব্যবসায়িক ভলিউম প্রকাশ করে না
- কনফিগ: datacenter_id(0-31) + worker_id(0-31), ১০২৪টি নোড কনকারেন্ট সাপোর্ট

## 4. API ডিজাইন

### 4.1 URL স্ট্যান্ডার্ড

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

### 4.2 API ভার্সন কৌশল

API ভার্সন রিকোয়েস্ট হেডার দিয়ে নিয়ন্ত্রিত, **URL পাথে প্রকাশ পায় না**:

```http
API-Version: v1
```

| মেকানিজম | বিবরণ |
|------|------|
| ডিফল্ট ভার্সন | `API-Version` হেডার না থাকলে ডিফল্ট `v1` |
| ভেরিফিকেশন | `ApiVersion` মিডলওয়্যার ভেরিফাই করে, অসমর্থিত ভার্সনে 400 ফেরত |
| রাউটিং | `v()` হেল্পার ফাংশন ভার্সন অনুযায়ী কন্ট্রোলার ক্লাস ডাইনামিক্যালি রেজলভ করে |
| ডিরেক্টরি | কন্ট্রোলার ভার্সন অনুযায়ী সংগঠিত: `app/api/{version}/controller/` |

এক্সটেনশন উদাহরণ — নতুন v2 API যোগ:
1. `app/api/v2/controller/AuthController.php` তৈরি করুন
2. `ApiVersion` মিডলওয়্যারের `SUPPORTED` কনস্ট্যান্টে `'v2'` যোগ করুন
3. রাউট ডেফিনিশন পরিবর্তনের প্রয়োজন নেই

```bash
# 使用 v1
curl -H "API-Version: v1" /api/auth/login

# 使用 v2
curl -H "API-Version: v2" /api/auth/login

# 不传，默认 v1
curl /api/auth/login
```

### 4.3 রেট লিমিট কৌশল

Redis Sorted Set স্লাইডিং উইন্ডো অ্যালগরিদম ভিত্তিক, অ্যাটমিক Lua স্ক্রিপ্টে কার্যকর:

| ইন্টারফেস | সীমা |
|------|------|
| ডিফল্ট | ৬০ বার/মিনিট/IP/রুট |
| POST /api/auth/login | ১০ বার/মিনিট |
| POST /api/auth/register | ৫ বার/মিনিট |

সীমা অতিক্রম করলে 429 ফেরত, রেসপন্স হেডারে X-RateLimit-Limit / Remaining / Reset / Retry-After থাকে।

### 4.4 ইউনিফাইড রেসপন্স

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | অর্থ | ট্রিগার পরিস্থিতি |
|------|------|---------|
| 0 | সফল | স্বাভাবিক রেসপন্স |
| 400 | প্যারামিটার ত্রুটি | রিকোয়েস্ট ফরম্যাট সঠিক নয় |
| 401 | অথেনটিকেটেড নয় | Token অনুপস্থিত/মেয়াদোত্তীর্ণ/অবৈধ |
| 403 | কোনো অনুমতি নেই | ইউজার রোলে প্রয়োজনীয় পারমিশন নেই |
| 404 | নেই | রিসোর্স পাওয়া যায়নি |
| 422 | ভ্যালিডেশন ব্যর্থ | ফর্ম প্যারামিটার নিয়ম মানছে না / পাসওয়ার্ড নিশ্চিতকরণ ব্যর্থ |
| 500 | সার্ভার ত্রুটি | অপ্রত্যাশিত এক্সেপশন |

### 4.5 অথেনটিকেশন ফ্লো (ক্লিক ক্যাপচা সহ)

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

### 4.6 পারমিশন মডেল (RBAC)

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

### 4.7 সংবেদনশীল অপারেশনের দ্বিতীয় নিশ্চিতকরণ

ইউজার, রোল, পারমিশন ডিলিটের মতো সংবেদনশীল অপারেশনে, পরিচয় পুনরায় নিশ্চিত করতে রিকোয়েস্ট বডিতে বর্তমান ইউজারের পাসওয়ার্ড পাঠাতে হয়:

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

ফ্রন্টএন্ড ডিলিট অপারেশন ট্রিগার করার আগে কনফার্মেশন ডায়ালগ দেখায়, ইউজারের পাসওয়ার্ড সংগ্রহ করে রিকোয়েস্ট পাঠায়।

## 5. ফ্রন্টএন্ড ডিজাইন

### 5.1 Flutter Web প্রশাসনিক প্যানেল

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

বৈশিষ্ট্য: সাইডবার ভাঁজযোগ্য, Material 3 ডুয়াল থিম, ডেটা টেবিল হাই-ডেনসিটি, পপআপ Dialog, মাউস হোভার ইন্টারঅ্যাকশন

### 5.2 HarmonyOS মোবাইল

পেজ রাউটিং:

| পেজ | রাউট | বিবরণ |
|------|------|------|
| LoginPage | `pages/LoginPage` | ইউজারনেম পাসওয়ার্ড + ক্লিক ক্যাপচা লগইন |
| DashboardPage | `pages/DashboardPage` | স্ট্যাটিস্টিক কার্ড + সাম্প্রতিক অপারেশন |
| UserListPage | `pages/UserListPage` | ইউজার তালিকা, সার্চ + ড্রপ-ডাউন রিফ্রেশ + উপরে স্ক্রল লোড |
| UserDetailPage | `pages/UserDetailPage` | তৈরি/সম্পাদনা/দেখা/ডিলিট (AlertDialog কনফার্মেশন) |
| ProfilePage | `pages/ProfilePage` | ব্যক্তিগত সেন্টার, লগআউট (AlertDialog কনফার্মেশন) |

ডেটা ফ্লো: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. নিরাপত্তা ডিজাইন

### 6.1 ডিফেন্স-ইন-ডেপথ

| স্তর | ব্যবস্থা |
|------|------|
| মেথড সীমা | SecurityFilter HTTP মেথড হোয়াইটলিস্ট, শুধুমাত্র GET/POST/PUT/DELETE/OPTIONS/HEAD অনুমোদিত, নন-স্ট্যান্ডার্ড মেথডে 405 |
| অ্যাটাক ব্লক | SecurityFilter মিডলওয়্যার, XSS/SQL ইনজেকশন/পাথ ট্রাভার্সাল/কমান্ড ইনজেকশন/CSRF ডিটেকশন ও ব্লক |
| হিউম্যান ভেরিফিকেশন | ক্লিক ক্যাপচা (Click Captcha), লগইন/রেজিস্ট্রেশনে বাধ্যতামূলক ভেরিফিকেশন |
| অ্যাকাউন্ট লক | টানা ৫ বার লগইন ব্যর্থ হলে অ্যাকাউন্ট ১৫ মিনিট লক, লক থাকা অবস্থায় 429 |
| সেশন সীমা | একই ইউজারের সর্বোচ্চ ৩টি কনকারেন্ট Token, এর বেশি হলে সবচেয়ে পুরনো Token স্বয়ংক্রিয় ব্ল্যাকলিস্ট |
| রেট লিমিট | RateLimit মিডলওয়্যার, Redis স্লাইডিং উইন্ডো, Lua অ্যাটমিক |
| CSP | Content-Security-Policy হেডার রিসোর্স সোর্স সীমাবদ্ধ করে, XSS ও ডেটা ইনজেকশন রোধ |
| অপারেশন কনফার্মেশন | ডিলিটের মতো সংবেদনশীল অপারেশনে বর্তমান ইউজারের পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ |
| ট্রান্সপোর্ট | HTTPS + JWT Bearer Token |
| ইন্টারফেস ID | Hashids এনক্রিপশন, বাইরে থেকে প্রকৃত ID রিভার্স করা অসম্ভব |
| রিকোয়েস্ট বডি | AES-256-CBC সংবেদনশীল ফিল্ড এনক্রিপশন |
| ডেটাবেস | BIGINT প্রাইমারি কী (অটোইনক্রিমেন্ট ভলিউম প্রকাশ করে না) |
| ডেটাবেস | AES-128-ECB সংবেদনশীল ফিল্ড এনক্রিপ্টেড স্টোর |
| অথেনটিকেশন | JWT HS256, 2h মেয়াদ + refresh token |
| অথোরাইজেশন | RBAC, method.path গ্রানুলারিটি পারমিশন কন্ট্রোল |
| অডিট | OperationLog সব অপারেশন রেকর্ড করে (source সোর্স স্বয়ংক্রিয় ডিটেকশন সহ) |

### 6.2 কী ম্যানেজমেন্ট

```
JWT_SECRET          → 环境变量注入，64位随机字符串
HASHIDS_SALT        → 唯一盐值，泄漏后需全局更换
ENCRYPTION_KEY      → API 传输加密密钥，32字节
ENCRYPTABLE_KEY     → DB 存储加密密钥，与传输密钥独立
SCOUT_HOSTS         → ES 地址，内网部署
```

### 6.3 সংবেদনশীল ডেটা সুরক্ষা

| পরিস্থিতি | ফিল্ড | ব্যবস্থা |
|------|------|------|
| তালিকা প্রদর্শন | phone | মাস্ক: 138****1234 |
| তালিকা প্রদর্শন | email | মাস্ক: a***@example.com |
| ডিটেইল দেখা | phone/email | ডিক্রিপ্টেড ইন্টারফেস প্রয়োজন |
| Excel এক্সপোর্ট | phone/email | মাস্ক করে এক্সপোর্ট |
| PDF এক্সপোর্ট | সব ফিল্ড | মাস্ক + অপসারণযোগ্য নয় এমন কপিরাইট ওয়াটারমার্ক |
| স্টোর | phone/email/id_card | encryptable দিয়ে সাইফারটেক্সট এনক্রিপ্ট |

## 7. এক্সপোর্ট ডিজাইন

### 7.1 Excel এক্সপোর্ট

```
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 PDF এক্সপোর্ট

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. ডিপ্লয়মেন্ট আর্কিটেকচার

### 8.1 সুপারিশকৃত টপোলজি

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                    静态文件: Flutter Web build/
```

### 8.2 Docker Compose (প্রোডাকশনে সুপারিশকৃত)

প্রজেক্ট রুটের `docker-compose.yml` উপরের টপোলজির সব সার্ভিস অর্কেস্ট্রেট করে:

| সার্ভিস | ইমেজ/বিল্ড | পোর্ট | বিবরণ |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | রিভার্স প্রক্সি + স্ট্যাটিক ফাইল + Gzip |
| `app` | লোকাল `Dockerfile` বিল্ড | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | মূল ডেটাবেস, ডেটা ভলিউম পারসিস্টেন্স |
| `redis` | redis:7-alpine | 6379 | ক্যাশ / রেট লিমিট / ক্যাপচা |
| `elasticsearch` | elasticsearch:8.x | 9200 | ফুল-টেক্সট সার্চ |

শুরু করার আগে `docker-compose.yml`-এ `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY` ইত্যাদি কী এলোমেলো স্ট্রিংয়ে পরিবর্তন করুন।

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

GitHub Actions কন্টিনিউয়াস ইন্টিগ্রেশন `.github/workflows/ci.yml`-এ সংজ্ঞায়িত:
- PHP সিনট্যাক্স চেক (`php -l`)
- PHPUnit ইউনিট টেস্ট
- Flutter স্ট্যাটিক অ্যানালাইসিস (`flutter analyze`)

### 8.4 ডেটাবেস ব্যাকআপ

`database/backup/backup.sh` — mysqldump + gzip ব্যাকআপ, ৩০ দিন আগের পুরনো ব্যাকআপ স্বয়ংক্রিয় পরিষ্কার।
`database/backup/restore.sh` — ইন্টারঅ্যাকটিভভাবে নির্বাচন করে ব্যাকআপ রিস্টোর।

### 8.5 মনিটরিং

`GET /metrics` এন্ডপয়েন্ট (`MetricsController`) Prometheus text format-এ ৫টি gauge মেট্রিক প্রকাশ করে: HTTP রিকোয়েস্ট মোট সংখ্যা, সক্রিয় ইউজার সংখ্যা, ডেটাবেস/Redis সংযোগ অবস্থা, মেমরি ব্যবহার।

### 8.6 পরিবেশের প্রয়োজনীয়তা

| কম্পোনেন্ট | ন্যূনতম ভার্সন | সুপারিশকৃত কনফিগ |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ OPcache enabled |
| MySQL | 8.0+ | 8.0+ মাস্টার-স্লেভ রেপ্লিকেশন |
| Elasticsearch | 7.x | 8.x ৩ নোড ক্লাস্টার |
| Redis | 6.x | 7.x সেন্টিনেল মোড |
| Nginx | 1.20+ | রিভার্স প্রক্সি + gzip + SSL |
| Flutter SDK | 3.41+ | সর্বশেষ স্টেবল ভার্সন |
| HarmonyOS | API 12 | DevEco Studio 5.x |
