# আর্কিটেকচার ডায়াগ্রাম ও বিজনেস লজিক ডায়াগ্রাম
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE.md) · [English](ARCHITECTURE.en.md) · [한국어](ARCHITECTURE.ko.md) · [Русский](ARCHITECTURE.ru.md) · [Deutsch](ARCHITECTURE.de.md) · [Français](ARCHITECTURE.fr.md) · [Español](ARCHITECTURE.es.md) · [Português](ARCHITECTURE.pt.md) · [हिन्दी](ARCHITECTURE.hi.md) · [العربية](ARCHITECTURE.ar.md) · **বাংলা** · [Bahasa Indonesia](ARCHITECTURE.id.md) · [日本語](ARCHITECTURE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> নিচের Mermaid চার্টগুলো GitHub / GitLab / VS Code-এ স্বয়ংক্রিয়ভাবে রেন্ডার হয়। অন্যান্য পরিবেশে দেখতে [Mermaid Live Editor](https://mermaid.live/) ব্যবহার করুন।

---

## 1. সিস্টেম টপোলজি আর্কিটেকচার

```mermaid
flowchart TB
    subgraph "ক্লায়েন্ট লেয়ার"
        A1["Flutter Web<br/>PC অ্যাডমিন প্যানেল<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>মোবাইল/ট্যাবলেট ক্লায়েন্ট"]
    end

    subgraph "গেটওয়ে/এজ লেয়ার (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>রিভার্স প্রক্সি + HTTPS + Gzip<br/>স্ট্যাটিক ফাইল সার্ভিস"]
    end

    subgraph "অ্যাপ্লিকেশন লেয়ার (webman v2)"
        C1["AdminAuth মিডলওয়্যার<br/>JWT যাচাই"]
        C2["AdminPermission মিডলওয়্যার<br/>RBAC পারমিশন চেক"]
        C3["অ্যাডমিন Controller<br/>Dashboard / User / Role / Permission / Payment"]
        C4["পাবলিক Controller v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "স্টোরেজ লেয়ার"
        D1[("MySQL 8.0<br/>মূল স্টোরেজ<br/>টেবিল প্রিফিক্স game_")]
        D2[("Elasticsearch<br/>ফুলটেক্সট সার্চ<br/>ইনডেক্স প্রিফিক্স game_")]
        D3[("Redis<br/>Session / ক্যাশ<br/>Captcha স্টোরেজ")]
    end

    subgraph "এক্সটার্নাল"
        E1["DevEco Studio<br/>HarmonyOS বিল্ড"]
        E2["Flutter SDK<br/>Web বিল্ড"]
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

## 2. ব্যাকএন্ড লেয়ারড আর্কিটেকচার

```mermaid
flowchart TD
    subgraph "রাউট লেয়ার Route Layer"
        R1["config/route.php<br/>URL → Controller ম্যাপিং"]
    end

    subgraph "মিডলওয়্যার লেয়ার Middleware Layer"
        M_RL["RateLimit<br/>Redis স্লাইডিং উইন্ডো রেট লিমিট<br/>X-RateLimit রেসপন্স হেডার"]
        M_SF["SecurityFilter<br/>অ্যাটাক ডিটেকশন ও ব্লকিং<br/>XSS/SQL ইনজেকশন/পাথ ট্রাভার্সাল/CSRF"]
        M1["AdminAuth<br/>JWT Token যাচাই<br/>adminId ইনজেক্ট"]
        M2["AdminPermission<br/>RBAC পারমিশন চেক<br/>method.path ম্যাচিং<br/>Redis 60s পারমিশন ক্যাশ"]
    end

    subgraph "কন্ট্রোলার লেয়ার Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + সার্চ + পেজিনেশন"]
        CT3["RoleController<br/>CRUD + পারমিশন সিঙ্ক"]
        CT4["PermissionController<br/>CRUD + ট্রি নির্মাণ"]
        CT5["DashboardController<br/>পরিসংখ্যান/ট্রেন্ড/ডিস্ট্রিবিউশন"]
        CT6["ExportController<br/>Excel/PDF এক্সপোর্ট"]
        CT7["CaptchaController<br/>ক্যাপচা জেনারেশন/যাচাই"]
        CT8["AuthController<br/>লগইন/রেজিস্ট্রেশন/রিফ্রেশ"]
        CT9["AnalyticsController<br/>১২টি ডেটা বিশ্লেষণ এন্ডপয়েন্ট<br/>ওভারভিউ/র্যাংকিং/প্রোবাবিলিটি/রিটেনশন/ফানেল/ARPU"]
    end

    subgraph "সার্ভিস লেয়ার Service Layer"
        S1["HashidsService<br/>ID এনকোড/ডিকোড"]
        S2["SnowflakeService<br/>গ্লোবাল ইউনিক ID জেনারেশন"]
        S3["EncryptionService<br/>এনক্রিপ্ট/ডিক্রিপ্ট + মাস্কিং"]
        S4["GameDashboardService<br/>ওভারভিউ/র্যাংকিং/DAU/ঘণ্টা/আচরণ ডিস্ট্রিবিউশন<br/>MySQL রিয়েল-টাইম অ্যাগ্রিগেশন, DB ফেইল করলে খালি ডেটা ফেরত"]
        S5["DepositLogService<br/>রেভিনিউ ওভারভিউ/গেম কনভার্সন রেট<br/>confirmed অর্ডার পরিসংখ্যান"]
        S6["ProbabilityService<br/>জয়েন্ট/কন্ডিশনাল প্রোবাবিলিটি<br/>SQL বিল্ডার (এস্কেপ/কোট/IN)"]
    end

    subgraph "মডেল লেয়ার Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "ড্রাইভার লেয়ার Driver Layer"
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

## 3. রিকোয়েস্ট লাইফসাইকেল

```mermaid
sequenceDiagram
    participant C as ক্লায়েন্ট
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

    C->>N: HTTPS অনুরোধ<br/>POST /admin/v1/*
    N->>MW_SF: ফরওয়ার্ড

    alt অ-স্ট্যান্ডার্ড HTTP মেথড (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else মেথড ভ্যালিড (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: মেথড হোয়াইটলিস্ট চেক পাস
    end

    alt অ্যাটাক ডিটেকশন ট্রিগার
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: পাস

    alt রেট লিমিট ট্রিগার
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW1: পাস

    alt Token অনুপস্থিত বা অকার্যকর
        MW1-->>C: 401 Unauthorized
    else Token কার্যকর
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt পারমিশন নেই
        MW2-->>C: 403 Forbidden
    else পারমিশন আছে
        MW2->>CTL: কন্ট্রোলারে প্রবেশ
    end

    CTL->>CTL: প্যারামিটার ভ্যালিডেশন (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt সেনসিটিভ অপারেশন (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt পাসওয়ার্ড ভুল
            CTL-->>C: 422 পাসওয়ার্ড ভেরিফিকেশন ব্যর্থ
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast অটো ডিক্রিপ্ট
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: রেসপন্স JSON তৈরি
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: অপারেশন লগ রেকর্ড (POST/PUT/DELETE)
```

---

## 4. অথেনটিকেশন ও ক্যাপচা ফ্লো

```mermaid
sequenceDiagram
    participant U as ইউজার
    participant CL as ক্লায়েন্ট
    participant SV as সার্ভার
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === ধাপ ১: ক্যাপচা সংগ্রহ ===
    CL->>SV: POST /api/v1/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: 300×200 ব্যাকগ্রাউন্ড ইমেজ তৈরি
    CAP->>CAP: এলোমেলোভাবে Nটি চীনা টার্গেট বসান
    CAP->>CAP: key জেনারেট, targets স্টোর
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === ধাপ ২: ইউজার ক্লিক ===
    CL->>CL: ক্যাপচা ইমেজ রেন্ডার
    CL->>CL: প্রম্পট "ক্রম অনুসারে ক্লিক করুন: গাছ → পাখি → ফুল"
    U->>CL: ইমেজের টেক্সটের অবস্থানে ক্রমানুসারে ক্লিক
    CL->>CL: clicks সংগ্রহ: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === ধাপ ৩: লগইন ===
    CL->>SV: POST /api/v1/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt ক্যাপচা ভুল
        CAP-->>SV: false
        SV-->>CL: 422 ক্যাপচা ভুল
    else ক্যাপচা সঠিক
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt ক্রেডেনশিয়াল ভুল
            SV-->>CL: 401 ইউজারনেম বা পাসওয়ার্ড ভুল
        else ক্রেডেনশিয়াল সঠিক
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === পরবর্তী অনুরোধ ===
    CL->>SV: GET /admin/v1/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC পারমিশন মডেল

```mermaid
flowchart LR
    subgraph "ইউজার User"
        U1["admin<br/>(সুপার অ্যাডমিন)"]
        U2["editor<br/>(এডিটর)"]
        U3["viewer<br/>(রিড-অনলি)"]
    end

    subgraph "ভূমিকা Role"
        R1["super_admin<br/>পারমিশন স্লাগ: *"]
        R2["editor<br/>পারমিশন স্লাগ: get.*, post.*"]
        R3["viewer<br/>পারমিশন স্লাগ: get.*"]
    end

    subgraph "পারমিশন Permission (ট্রি)"
        P1["dashboard<br/>type=1 মেনু"]
        P2["user<br/>type=1 মেনু"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 বোতাম"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (সম্পূর্ণ পারমিশন)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "পারমিশন টাইপ"
        T1["type=1 মেনু<br/>সাইডবার দেখানো/লুকানো নিয়ন্ত্রণ"]
        T2["type=2 বোতাম<br/>পেজের অপারেশন বোতাম নিয়ন্ত্রণ"]
        T3["type=3 API<br/>API অ্যাক্সেস নিয়ন্ত্রণ"]
    end

    subgraph "পারমিশন স্লাগ ফরম্যাট"
        F1["{method}.{path}<br/>যেমন: get.admin/user<br/>যেমন: post.admin/user<br/>যেমন: delete.admin/role"]
    end

    subgraph "সিদ্ধান্ত প্রবাহ"
        J1["Token → adminId এক্সট্র্যাক্ট"]
        J2["ইউজার ভূমিকা অনুসন্ধান"]
        J3["সব পারমিশন slug সংগ্রহ"]
        J4["method.path তৈরি"]
        J5{"ম্যাচ?"}
        J6["পাস"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"হ্যাঁ / slug=*"| J6
        J5 -->|না| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. ID সম্পূর্ণ লাইফসাইকেল

```mermaid
flowchart LR
    subgraph "১. জেনারেশন"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>যেমন: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "২. স্টোরেজ"
        S1["MySQL game_* টেবিল<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["সেনসিটিভ ফিল্ড<br/>encryptable cast<br/>AES-128-ECB এনক্রিপশন"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "৩. ট্রান্সমিশন"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid স্ট্রিং<br/>যেমন: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "৪. রিভার্স ডিকোড"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. ডেটা এনক্রিপশন লেয়ারিং

```mermaid
flowchart TB
    subgraph "ট্রান্সপোর্ট লেয়ার এনক্রিপশন (encryption)"
        E1["ক্লায়েন্ট সেনসিটিভ ডেটা পাঠায়"]
        E2["AES-256-CBC এনক্রিপশন"]
        E3["API ট্রান্সপোর্ট সাইফারটেক্সট"]
        E4["সার্ভার ডিক্রিপ্ট প্রসেসিং"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "স্টোরেজ লেয়ার এনক্রিপশন (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["রাইট: অটো এনক্রিপ্ট"]
        D3["MySQL VARCHAR(500)<br/>সাইফারটেক্সট সংরক্ষণ"]
        D4["রিড: অটো ডিক্রিপ্ট"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "প্রেজেন্টেশন লেয়ার মাস্কিং (mask)"
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

## 8. ডেটাবেস ER সম্পর্ক

```mermaid
erDiagram
    game_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "এনক্রিপশন"
        VARCHAR phone "এনক্রিপশন"
        VARCHAR id_card "এনক্রিপশন"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "সফট ডিলিট"
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
        BIGINT parent_id FK "সেলফ-রেফারেন্স"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1মেনু2বোতাম3API"
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
        VARCHAR source "প্ল্যাটফর্ম সোর্স"
        TEXT input "মাস্কিং"
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

## 9. এক্সপোর্ট ব্যবসায়িক ফ্লো

```mermaid
sequenceDiagram
    participant C as ক্লায়েন্ট
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as ফাইল সিস্টেম

    Note over C,FS: === Excel এক্সপোর্ট ===
    C->>CTL: POST /admin/v1/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: ডেটা
    CTL->>CTL: সেনসিটিভ ফিল্ড ডিক্রিপ্ট
    CTL->>CTL: মাস্কিং প্রসেসিং (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet নির্মাণ<br/>হেডারে নীল ব্যাকগ্রাউন্ড সাদা টেক্সট<br/>ডেটা রোতে সূক্ষ্ম বর্ডার<br/>প্রথম রো ফ্রিজ<br/>অটো ফিল্টার
    CTL->>FS: runtime/tmp/export_*.xlsx ফাইলে লেখা
    CTL-->>C: ফাইল ডাউনলোড

    Note over C,FS: === PDF এক্সপোর্ট ===
    C->>CTL: POST /admin/v1/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>পেজ হেডার: টাইটেল+কপিরাইট+সময়<br/>কনটেন্ট: টেবিল বা কার্ড<br/>পেজ ফুটার: অপসারণযোগ্য নয় এমন কপিরাইট
    CTL->>CTL: Dompdf রেন্ডার A4 ল্যান্ডস্কেপ
    CTL->>FS: runtime/tmp/export_*.pdf ফাইলে লেখা
    CTL-->>C: ফাইল ডাউনলোড
```

---

## 10. Flutter Web কম্পোনেন্ট ট্রি

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["লগইন ফর্ম<br/>ইউজারনেম/পাসওয়ার্ড/ক্যাপচা"]
    LF --> CAPTCHA["ক্লিক ক্যাপচা কম্পোনেন্ট<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>ক্লিক মার্ক Circle"]

    DB --> SIDEBAR["সাইডবার NavigationDrawer<br/>কোলাপ্সযোগ্য 64px / 240px<br/>ড্যাশবোর্ড/ইউজার/ভূমিকা/কনফিগ/লগ/পেমেন্ট"]
    DB --> HEADER["টপ বার 56px<br/>কোলাপ্স বোতাম + ইউজার মেনু<br/>লগআউট AlertDialog"]
    DB --> CONTENT["কনটেন্ট এরিয়া"]
    CONTENT --> DASH["DashboardPage<br/>পরিসংখ্যান কার্ড GridView<br/>ট্রেন্ড লাইন চার্ট LineChart<br/>ডিস্ট্রিবিউশন পাই চার্ট PieChart<br/>সাম্প্রতিক অপারেশন ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS পেজ রাউটিং

```mermaid
flowchart LR
    EA["EntryAbility<br/>স্টার্টআপ"]
    EA -->|"Token নেই"| LP["LoginPage<br/>লগইন পেজ"]
    EA -->|"Token আছে"| DP["DashboardPage<br/>ড্যাশবোর্ড"]

    LP -->|"লগইন সফল<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>ইউজার তালিকা"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>প্রোফাইল সেন্টার"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>ইউজার বিস্তারিত/নতুন/এডিট"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"লগআউট<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. নিরাপত্তা ডিফেন্স-ইন-ডেপথ প্যানোরামা

```mermaid
flowchart TB
    subgraph "স্তর ১: হিউম্যান ভেরিফিকেশন"
        L1["ক্লিক ক্যাপচা<br/>Click Captcha<br/>লগইন/রেজিস্ট্রেশনে বাধ্যতামূলক"]
    end

    subgraph "স্তর ২: অপারেশন নিশ্চিতকরণ"
        L2["পাসওয়ার্ড রি-কনফার্মেশন<br/>confirmPassword()<br/>DELETE অপারেশনে আবশ্যক"]
    end

    subgraph "স্তর ৩: ট্রান্সপোর্ট সিকিউরিটি"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "স্তর ৪: আইডেন্টিটি অথেনটিকেশন"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "স্তর ৫: পারমিশন অথরাইজেশন"
        L5["RBAC<br/>method.path গ্রানুলারিটি<br/>সুপার অ্যাডমিন * "]
    end

    subgraph "স্তর ৬: ডেটা সুরক্ষা"
        L6["API ID: Hashids এনক্রিপশন<br/>রিকোয়েস্ট বডি: Encryption এনক্রিপশন<br/>স্টোরেজ লেয়ার: Encryptable এনক্রিপশন<br/>এক্সপোর্ট: মাস্কিং+কপিরাইট"]
    end

    subgraph "স্তর ৭: অডিট ট্রেসিং"
        L7["OperationLog<br/>সব অপারেশন রেকর্ড<br/>ইউজার/IP/সময়/প্ল্যাটফর্ম সোর্স/প্যারামিটার"]
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

## 13. ডিপ্লয়মেন্ট টপোলজি

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web সার্ভার"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["স্ট্যাটিক ফাইল<br/>Flutter Web build/"]
    end

    subgraph "অ্যাপ্লিকেশন সার্ভার (হরাইজন্টালি স্কেলযোগ্য)"
        WM1["webman worker 1<br/>:8789"]
        WM2["webman worker 2<br/>:8789"]
        WM3["webman worker N<br/>:8789"]
    end

    subgraph "ডেটা লেয়ার"
        MYSQL["MySQL 8.0<br/>মাস্টার-রেপ্লিকা রেপ্লিকেশন<br/>game_ প্রিফিক্স"]
        ES["Elasticsearch 8.x<br/>৩-নোড ক্লাস্টার<br/>game_ প্রিফিক্স"]
        REDIS["Redis 7.x<br/>সেন্টিনেল মোড<br/>poster:captcha:*"]
    end

    subgraph "মনিটরিং"
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
