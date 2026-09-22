# आर्किटेक्चर आरेख और बिज़नेस लॉजिक आरेख
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE.md) · [English](ARCHITECTURE.en.md) · [한국어](ARCHITECTURE.ko.md) · [Русский](ARCHITECTURE.ru.md) · [Deutsch](ARCHITECTURE.de.md) · [Français](ARCHITECTURE.fr.md) · [Español](ARCHITECTURE.es.md) · [Português](ARCHITECTURE.pt.md) · **हिन्दी** · [العربية](ARCHITECTURE.ar.md) · [বাংলা](ARCHITECTURE.bn.md) · [Bahasa Indonesia](ARCHITECTURE.id.md) · [日本語](ARCHITECTURE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> नीचे दिए गए Mermaid चार्ट GitHub / GitLab / VS Code में स्वचालित रूप से रेंडर होते हैं। अन्य वातावरणों के लिए [Mermaid Live Editor](https://mermaid.live/) देखें।

---

## 1. सिस्टम टोपोलॉजी आर्किटेक्चर

```mermaid
flowchart TB
    subgraph "क्लाइंट परत"
        A1["Flutter Web<br/>PC प्रशासन कंसोल<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>मोबाइल/टैबलेट क्लाइंट"]
    end

    subgraph "गेटवे/एज परत (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>रिवर्स प्रॉक्सी + HTTPS + Gzip<br/>स्टैटिक फ़ाइल सेवा"]
    end

    subgraph "एप्लिकेशन परत (webman v2)"
        C1["AdminAuth मिडलवेयर<br/>JWT सत्यापन"]
        C2["AdminPermission मिडलवेयर<br/>RBAC अनुमति सत्यापन"]
        C3["प्रशासनिक Controller<br/>Dashboard / User / Role / Permission / Payment"]
        C4["सार्वजनिक Controller v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "भंडारण परत"
        D1[("MySQL 8.0<br/>मुख्य भंडारण<br/>तालिका उपसर्ग game_")]
        D2[("Elasticsearch<br/>पूर्ण-पाठ खोज<br/>इंडेक्स उपसर्ग game_")]
        D3[("Redis<br/>Session / कैश<br/>Captcha भंडारण")]
    end

    subgraph "बाहरी"
        E1["DevEco Studio<br/>HarmonyOS बिल्ड"]
        E2["Flutter SDK<br/>Web बिल्ड"]
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

## 2. बैकएंड लेयर्ड आर्किटेक्चर

```mermaid
flowchart TD
    subgraph "रूट परत Route Layer"
        R1["config/route.php<br/>URL → Controller मैपिंग"]
    end

    subgraph "मिडलवेयर परत Middleware Layer"
        M_RL["RateLimit<br/>Redis स्लाइडिंग विंडो दर सीमा<br/>X-RateLimit प्रतिक्रिया हेडर"]
        M_SF["SecurityFilter<br/>आक्रमण पहचान अवरोधन<br/>XSS/SQL इंजेक्शन/पाथ ट्रैवर्सल/CSRF"]
        M1["AdminAuth<br/>JWT Token सत्यापन<br/>adminId इंजेक्ट करें"]
        M2["AdminPermission<br/>RBAC अनुमति सत्यापन<br/>method.path मिलान<br/>Redis 60s अनुमति कैश"]
    end

    subgraph "कंट्रोलर परत Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + खोज + पेजिनेशन"]
        CT3["RoleController<br/>CRUD + अनुमति समकालिक"]
        CT4["PermissionController<br/>CRUD + ट्री निर्माण"]
        CT5["DashboardController<br/>सांख्यिकी/प्रवृत्ति/वितरण"]
        CT6["ExportController<br/>Excel/PDF निर्यात"]
        CT7["CaptchaController<br/>कैप्चा जनरेशन/सत्यापन"]
        CT8["AuthController<br/>लॉगिन/पंजीकरण/रिफ़्रेश"]
        CT9["AnalyticsController<br/>12 डेटा विश्लेषण एंडपॉइंट<br/>अवलोकन/रैंकिंग/प्रायिकता/प्रतिधारण/फ़नल/ARPU"]
    end

    subgraph "सेवा परत Service Layer"
        S1["HashidsService<br/>ID एन्कोड/डिकोड"]
        S2["SnowflakeService<br/>वैश्विक अद्वितीय ID जनरेशन"]
        S3["EncryptionService<br/>एन्क्रिप्शन/डिक्रिप्शन + मास्किंग"]
        S4["GameDashboardService<br/>अवलोकन/रैंकिंग/DAU/घंटा/व्यवहार वितरण<br/>MySQL रीयल-टाइम एकत्रीकरण, DB विफलता पर खाली डेटा"]
        S5["DepositLogService<br/>राजस्व अवलोकन/गेम रूपांतरण दर<br/>confirmed ऑर्डर सांख्यिकी"]
        S6["ProbabilityService<br/>संयुक्त/सशर्त प्रायिकता<br/>SQL बिल्डर (एस्केप/कोट/IN)"]
    end

    subgraph "मॉडल परत Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "ड्राइवर परत Driver Layer"
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

## 3. अनुरोध जीवनचक्र

```mermaid
sequenceDiagram
    participant C as क्लाइंट
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

    C->>N: HTTPS अनुरोध<br/>POST /admin/v1/*
    N->>MW_SF: फॉरवर्ड

    alt गैर-मानक HTTP विधि (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else विधि वैध (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: विधि श्वेतसूची जाँच पास
    end

    alt आक्रमण पहचान सक्रिय
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: पास

    alt दर सीमा सक्रिय
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW1: पास

    alt Token अनुपस्थित या अमान्य
        MW1-->>C: 401 Unauthorized
    else Token मान्य
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt अनुमति नहीं
        MW2-->>C: 403 Forbidden
    else अनुमति है
        MW2->>CTL: कंट्रोलर में प्रवेश
    end

    CTL->>CTL: पैरामीटर सत्यापन (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt संवेदनशील ऑपरेशन (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt पासवर्ड गलत
            CTL-->>C: 422 पासवर्ड सत्यापन विफल
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast स्वचालित डिक्रिप्शन
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: प्रतिक्रिया JSON बनाएँ
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: ऑपरेशन लॉग दर्ज करें (POST/PUT/DELETE)
```

---

## 4. प्रमाणीकरण और कैप्चा प्रवाह

```mermaid
sequenceDiagram
    participant U as उपयोगकर्ता
    participant CL as क्लाइंट
    participant SV as सर्वर
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === चरण 1: कैप्चा प्राप्त करें ===
    CL->>SV: POST /api/v1/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: 300×200 पृष्ठभूमि छवि बनाएँ
    CAP->>CAP: बेतरतीब ढंग से N चीनी लक्ष्य रखें
    CAP->>CAP: key बनाएँ, targets संग्रहीत करें
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === चरण 2: उपयोगकर्ता क्लिक ===
    CL->>CL: कैप्चा छवि रेंडर करें
    CL->>CL: संकेत "क्रम से क्लिक करें: पेड़ → पक्षी → फूल"
    U->>CL: छवि में अक्षर स्थानों पर क्रम से क्लिक करें
    CL->>CL: clicks एकत्र करें: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === चरण 3: लॉगिन ===
    CL->>SV: POST /api/v1/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt कैप्चा गलत
        CAP-->>SV: false
        SV-->>CL: 422 कैप्चा गलत
    else कैप्चा सही
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt क्रेडेंशियल गलत
            SV-->>CL: 401 उपयोगकर्ता नाम या पासवर्ड गलत
        else क्रेडेंशियल सही
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === बाद के अनुरोध ===
    CL->>SV: GET /admin/v1/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC अनुमति मॉडल

```mermaid
flowchart LR
    subgraph "उपयोगकर्ता User"
        U1["admin<br/>(सुपर एडमिन)"]
        U2["editor<br/>(संपादक)"]
        U3["viewer<br/>(रीड-ओनली)"]
    end

    subgraph "भूमिका Role"
        R1["super_admin<br/>अनुमति पहचानकर्ता: *"]
        R2["editor<br/>अनुमति पहचानकर्ता: get.*, post.*"]
        R3["viewer<br/>अनुमति पहचानकर्ता: get.*"]
    end

    subgraph "अनुमति Permission (ट्री)"
        P1["dashboard<br/>type=1 मेनू"]
        P2["user<br/>type=1 मेनू"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 बटन"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (पूर्ण अनुमति)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "अनुमति प्रकार"
        T1["type=1 मेनू<br/>साइडबार दिखाना/छिपाना नियंत्रित करें"]
        T2["type=2 बटन<br/>पेज ऑपरेशन बटन नियंत्रित करें"]
        T3["type=3 API<br/>API एक्सेस नियंत्रित करें"]
    end

    subgraph "अनुमति पहचानकर्ता प्रारूप"
        F1["{method}.{path}<br/>उदा.: get.admin/user<br/>उदा.: post.admin/user<br/>उदा.: delete.admin/role"]
    end

    subgraph "निर्धारण प्रवाह"
        J1["Token → adminId निकालें"]
        J2["उपयोगकर्ता भूमिका खोजें"]
        J3["सभी अनुमति slug एकत्र करें"]
        J4["method.path बनाएँ"]
        J5{"मेल खाता है?"}
        J6["पास करें"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"हाँ / slug=*"| J6
        J5 -->|नहीं| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. ID पूर्ण जीवनचक्र

```mermaid
flowchart LR
    subgraph "1. जनरेशन"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>उदा.: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. भंडारण"
        S1["MySQL game_* तालिका<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["संवेदनशील फ़ील्ड<br/>encryptable cast<br/>AES-128-ECB एन्क्रिप्शन"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. ट्रांसमिशन"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid स्ट्रिंग<br/>उदा.: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. रिवर्स डिकोडिंग"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. डेटा एन्क्रिप्शन परतें

```mermaid
flowchart TB
    subgraph "ट्रांसमिशन परत एन्क्रिप्शन (encryption)"
        E1["क्लाइंट संवेदनशील डेटा भेजता है"]
        E2["AES-256-CBC एन्क्रिप्शन"]
        E3["API ट्रांसमिशन सिफरटेक्स्ट"]
        E4["सर्वर डिक्रिप्शन प्रोसेसिंग"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "भंडारण परत एन्क्रिप्शन (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["लेखन: स्वचालित एन्क्रिप्शन"]
        D3["MySQL VARCHAR(500)<br/>सिफरटेक्स्ट संग्रह"]
        D4["पठन: स्वचालित डिक्रिप्शन"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "प्रदर्शन परत मास्किंग (mask)"
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

## 8. डेटाबेस ER संबंध

```mermaid
erDiagram
    game_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "एन्क्रिप्शन"
        VARCHAR phone "एन्क्रिप्शन"
        VARCHAR id_card "एन्क्रिप्शन"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "सॉफ्ट डिलीट"
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
        BIGINT parent_id FK "स्व-संदर्भ"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1 मेनू 2 बटन 3 API"
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
        VARCHAR source "स्रोत छोर"
        TEXT input "मास्किंग"
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

## 9. निर्यात व्यावसायिक प्रवाह

```mermaid
sequenceDiagram
    participant C as क्लाइंट
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as फ़ाइल सिस्टम

    Note over C,FS: === Excel निर्यात ===
    C->>CTL: POST /admin/v1/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: डेटा
    CTL->>CTL: संवेदनशील फ़ील्ड डिक्रिप्ट करें
    CTL->>CTL: मास्किंग (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet बिल्ड<br/>हेडर नीली पृष्ठभूमि सफ़ेद अक्षर<br/>डेटा पंक्ति पतली सीमा<br/>पहली पंक्ति फ़्रीज़<br/>स्वचालित फ़िल्टर
    CTL->>FS: runtime/tmp/export_*.xlsx में लिखें
    CTL-->>C: फ़ाइल डाउनलोड

    Note over C,FS: === PDF निर्यात ===
    C->>CTL: POST /admin/v1/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>हेडर: शीर्षक+कॉपीराइट+समय<br/>सामग्री: तालिका या कार्ड<br/>फ़ुटर: कॉपीराइट हटाना असंभव
    CTL->>CTL: Dompdf A4 लैंडस्केप रेंडरिंग
    CTL->>FS: runtime/tmp/export_*.pdf में लिखें
    CTL-->>C: फ़ाइल डाउनलोड
```

---

## 10. Flutter Web कंपोनेंट ट्री

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["लॉगिन फ़ॉर्म<br/>उपयोगकर्ता नाम/पासवर्ड/कैप्चा"]
    LF --> CAPTCHA["क्लिक कैप्चा कंपोनेंट<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>क्लिक मार्कर Circle"]

    DB --> SIDEBAR["साइडबार NavigationDrawer<br/>संक्षेपणीय 64px / 240px<br/>डैशबोर्ड/उपयोगकर्ता/भूमिका/कॉन्फ़िग/लॉग/भुगतान"]
    DB --> HEADER["टॉपबार 56px<br/>संक्षेपण बटन + उपयोगकर्ता मेनू<br/>लॉगआउट AlertDialog"]
    DB --> CONTENT["सामग्री क्षेत्र"]
    CONTENT --> DASH["DashboardPage<br/>सांख्यिकी कार्ड GridView<br/>प्रवृत्ति लाइन चार्ट LineChart<br/>वितरण पाई चार्ट PieChart<br/>हाल के ऑपरेशन ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS पेज रूटिंग

```mermaid
flowchart LR
    EA["EntryAbility<br/>लॉन्च"]
    EA -->|"Token नहीं"| LP["LoginPage<br/>लॉगिन पेज"]
    EA -->|"Token है"| DP["DashboardPage<br/>डैशबोर्ड"]

    LP -->|"लॉगिन सफल<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>उपयोगकर्ता सूची"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>व्यक्तिगत केंद्र"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>उपयोगकर्ता विवरण/जोड़ें/संपादन"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"लॉगआउट<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. सुरक्षा गहराई-रक्षा परिदृश्य

```mermaid
flowchart TB
    subgraph "परत 1: मानव-मशीन सत्यापन"
        L1["क्लिक कैप्चा<br/>Click Captcha<br/>लॉगिन/पंजीकरण अनिवार्य"]
    end

    subgraph "परत 2: ऑपरेशन पुष्टि"
        L2["पासवर्ड पुनः पुष्टि<br/>confirmPassword()<br/>DELETE ऑपरेशन अनिवार्य"]
    end

    subgraph "परत 3: ट्रांसमिशन सुरक्षा"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "परत 4: पहचान प्रमाणीकरण"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "परत 5: अनुमति सत्यापन"
        L5["RBAC<br/>method.path ग्रेन्युलरिटी<br/>सुपर एडमिन * "]
    end

    subgraph "परत 6: डेटा सुरक्षा"
        L6["API ID: Hashids एन्क्रिप्शन<br/>रिक्वेस्ट बॉडी: Encryption एन्क्रिप्शन<br/>भंडारण परत: Encryptable एन्क्रिप्शन<br/>निर्यात: मास्किंग+कॉपीराइट"]
    end

    subgraph "परत 7: ऑडिट ट्रेसिंग"
        L7["OperationLog<br/>सभी ऑपरेशन रिकॉर्ड करें<br/>उपयोगकर्ता/IP/समय/स्रोत छोर/पैरामीटर"]
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

## 13. डिप्लॉयमेंट टोपोलॉजी

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web सर्वर"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["स्टैटिक फ़ाइल<br/>Flutter Web build/"]
    end

    subgraph "एप्लिकेशन सर्वर (क्षैतिज स्केलिंग संभव)"
        WM1["webman worker 1<br/>:8789"]
        WM2["webman worker 2<br/>:8789"]
        WM3["webman worker N<br/>:8789"]
    end

    subgraph "डेटा परत"
        MYSQL["MySQL 8.0<br/>मास्टर-स्लेव रेप्लिकेशन<br/>game_ उपसर्ग"]
        ES["Elasticsearch 8.x<br/>3 नोड क्लस्टर<br/>game_ उपसर्ग"]
        REDIS["Redis 7.x<br/>सेंटिनल मोड<br/>poster:captcha:*"]
    end

    subgraph "मॉनिटरिंग"
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
