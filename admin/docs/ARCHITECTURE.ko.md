# 아키텍처 설계도 및 비즈니스 로직 다이어그램
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE.md) · [English](ARCHITECTURE.en.md) · **한국어** · [Русский](ARCHITECTURE.ru.md) · [Deutsch](ARCHITECTURE.de.md) · [Français](ARCHITECTURE.fr.md) · [Español](ARCHITECTURE.es.md) · [Português](ARCHITECTURE.pt.md) · [हिन्दी](ARCHITECTURE.hi.md) · [العربية](ARCHITECTURE.ar.md) · [বাংলা](ARCHITECTURE.bn.md) · [Bahasa Indonesia](ARCHITECTURE.id.md) · [日本語](ARCHITECTURE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 아래 Mermaid 차트는 GitHub / GitLab / VS Code에서 자동 렌더링됩니다. 그 외 환경에서는 [Mermaid Live Editor](https://mermaid.live/)를 사용하여 확인하세요.

---

## 1. 시스템 토폴로지 아키텍처

```mermaid
flowchart TB
    subgraph "클라이언트 레이어"
        A1["Flutter Web<br/>PC 관리 백오피스<br/>(포트 3000)"]
        A2["HarmonyOS ArkTS<br/>모바일/태블릿 클라이언트"]
    end

    subgraph "게이트웨이/엣지 레이어 (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>리버스 프록시 + HTTPS + Gzip<br/>정적 파일 서비스"]
    end

    subgraph "애플리케이션 레이어 (webman v2)"
        C1["AdminAuth 미들웨어<br/>JWT 검증"]
        C2["AdminPermission 미들웨어<br/>RBAC 권한 검증"]
        C3["관리단 Controller<br/>Dashboard / User / Role / Permission / Payment"]
        C4["공개 Controller v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "저장 레이어"
        D1[("MySQL 8.0<br/>주 저장소<br/>테이블 접두사 game_")]
        D2[("Elasticsearch<br/>전문 검색<br/>인덱스 접두사 game_")]
        D3[("Redis<br/>Session / 캐시<br/>Captcha 저장")]
    end

    subgraph "외부"
        E1["DevEco Studio<br/>HarmonyOS 빌드"]
        E2["Flutter SDK<br/>Web 빌드"]
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

## 2. 백엔드 계층 아키텍처

```mermaid
flowchart TD
    subgraph "라우트 레이어 Route Layer"
        R1["config/route.php<br/>URL → Controller 매핑"]
    end

    subgraph "미들웨어 레이어 Middleware Layer"
        M_RL["RateLimit<br/>Redis 슬라이딩 윈도우 레이트 리밋<br/>X-RateLimit 응답 헤더"]
        M_SF["SecurityFilter<br/>공격 탐지 차단<br/>XSS/SQL 주입/경로 탐색/CSRF"]
        M1["AdminAuth<br/>JWT Token 검증<br/>adminId 주입"]
        M2["AdminPermission<br/>RBAC 권한 검증<br/>method.path 매칭<br/>Redis 60s 권한 캐시"]
    end

    subgraph "컨트롤러 레이어 Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + 검색 + 페이지네이션"]
        CT3["RoleController<br/>CRUD + 권한 동기화"]
        CT4["PermissionController<br/>CRUD + 트리 구성"]
        CT5["DashboardController<br/>통계/추이/분포"]
        CT6["ExportController<br/>Excel/PDF 내보내기"]
        CT7["CaptchaController<br/>캡차 생성/검증"]
        CT8["AuthController<br/>로그인/회원가입/갱신"]
        CT9["AnalyticsController<br/>데이터 분석 엔드포인트 12개<br/>개요/랭킹/확률/리텐션/퍼널/ARPU"]
    end

    subgraph "서비스 레이어 Service Layer"
        S1["HashidsService<br/>ID 인코딩/디코딩"]
        S2["SnowflakeService<br/>전역 고유 ID 생성"]
        S3["EncryptionService<br/>암복호화 + 마스킹"]
        S4["GameDashboardService<br/>개요/랭킹/DAU/시간/행동 분포<br/>MySQL 실시간 집계, DB 장애 시 빈 데이터 반환"]
        S5["DepositLogService<br/>매출 개요/게임 전환율<br/>confirmed 주문 통계"]
        S6["ProbabilityService<br/>결합/조건부 확률<br/>SQL 빌더 (이스케이프/인용/IN)"]
    end

    subgraph "모델 레이어 Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "드라이버 레이어 Driver Layer"
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

## 3. 요청 라이프사이클

```mermaid
sequenceDiagram
    participant C as 클라이언트
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

    C->>N: HTTPS 요청<br/>POST /admin/v1/*
    N->>MW_SF: 전달

    alt 비표준 HTTP 메서드 (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else 메서드 허용 (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: 메서드 화이트리스트 검사 통과
    end

    alt 공격 탐지 트리거
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: 통과

    alt 레이트 리밋 트리거
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW1: 통과

    alt Token 누락 또는 무효
        MW1-->>C: 401 Unauthorized
    else Token 유효
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt 권한 없음
        MW2-->>C: 403 Forbidden
    else 권한 있음
        MW2->>CTL: 컨트롤러 진입
    end

    CTL->>CTL: 파라미터 검증 (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt 민감 작업 (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt 비밀번호 오류
            CTL-->>C: 422 비밀번호 검증 실패
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast 자동 복호화
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: 응답 JSON 구성
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: 작업 로그 기록 (POST/PUT/DELETE)
```

---

## 4. 인증 및 캡차 흐름

```mermaid
sequenceDiagram
    participant U as 사용자
    participant CL as 클라이언트
    participant SV as 서버
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === 1단계: 캡차 가져오기 ===
    CL->>SV: POST /api/v1/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: 300×200 배경 이미지 생성
    CAP->>CAP: 중국어 대상 N개 무작위 배치
    CAP->>CAP: key 생성, targets 저장
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === 2단계: 사용자 클릭 ===
    CL->>CL: 캡차 이미지 렌더링
    CL->>CL: 안내 "순서대로 클릭: 나무 → 새 → 꽃"
    U->>CL: 이미지 속 글자 위치를 순서대로 클릭
    CL->>CL: clicks 수집: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === 3단계: 로그인 ===
    CL->>SV: POST /api/v1/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt 캡차 오류
        CAP-->>SV: false
        SV-->>CL: 422 캡차 오류
    else 캡차 정상
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt 자격 증명 오류
            SV-->>CL: 401 사용자 이름 또는 비밀번호 오류
        else 자격 증명 정상
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === 후속 요청 ===
    CL->>SV: GET /admin/v1/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC 권한 모델

```mermaid
flowchart LR
    subgraph "사용자 User"
        U1["admin<br/>(슈퍼 관리자)"]
        U2["editor<br/>(편집자)"]
        U3["viewer<br/>(읽기 전용)"]
    end

    subgraph "역할 Role"
        R1["super_admin<br/>권한 식별자: *"]
        R2["editor<br/>권한 식별자: get.*, post.*"]
        R3["viewer<br/>권한 식별자: get.*"]
    end

    subgraph "권한 Permission (트리)"
        P1["dashboard<br/>type=1 메뉴"]
        P2["user<br/>type=1 메뉴"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 버튼"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (전체 권한)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "권한 유형"
        T1["type=1 메뉴<br/>사이드바 표시/숨김 제어"]
        T2["type=2 버튼<br/>페이지 작업 버튼 제어"]
        T3["type=3 API<br/>인터페이스 접근 제어"]
    end

    subgraph "권한 식별자 형식"
        F1["{method}.{path}<br/>예: get.admin/user<br/>예: post.admin/user<br/>예: delete.admin/role"]
    end

    subgraph "판정 흐름"
        J1["Token 추출 → adminId"]
        J2["사용자 역할 조회"]
        J3["모든 권한 slug 수집"]
        J4["method.path 구성"]
        J5{"매칭?"}
        J6["허용"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"예 / slug=*"| J6
        J5 -->|아니오| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. ID 전체 라이프사이클

```mermaid
flowchart LR
    subgraph "1. 생성"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>예: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. 저장"
        S1["MySQL game_* 테이블<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["민감 필드<br/>encryptable cast<br/>AES-128-ECB 암호화"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. 전송"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid 문자열<br/>예: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. 역디코딩"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. 데이터 암호화 계층

```mermaid
flowchart TB
    subgraph "전송 계층 암호화 (encryption)"
        E1["클라이언트 민감 데이터 전송"]
        E2["AES-256-CBC 암호화"]
        E3["API 전송 암호문"]
        E4["서버 복호화 처리"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "저장 계층 암호화 (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["쓰기: 자동 암호화"]
        D3["MySQL VARCHAR(500)<br/>암호문 저장"]
        D4["읽기: 자동 복호화"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "표시 계층 마스킹 (mask)"
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

## 8. 데이터베이스 ER 관계

```mermaid
erDiagram
    game_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "암호화"
        VARCHAR phone "암호화"
        VARCHAR id_card "암호화"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "소프트 삭제"
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
        BIGINT parent_id FK "자기 참조"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1메뉴 2버튼 3API"
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
        VARCHAR source "출처"
        TEXT input "마스킹"
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

## 9. 내보내기 비즈니스 흐름

```mermaid
sequenceDiagram
    participant C as 클라이언트
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as 파일 시스템

    Note over C,FS: === Excel 내보내기 ===
    C->>CTL: POST /admin/v1/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: 데이터
    CTL->>CTL: 민감 필드 복호화
    CTL->>CTL: 마스킹 처리 (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet 구성<br/>헤더 파란 배경 흰 글자<br/>데이터 행 얇은 테두리<br/>첫 행 고정<br/>자동 필터
    CTL->>FS: runtime/tmp/export_*.xlsx 쓰기
    CTL-->>C: 파일 다운로드

    Note over C,FS: === PDF 내보내기 ===
    C->>CTL: POST /admin/v1/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>머리글: 제목+저작권+시간<br/>본문: 표 또는 카드<br/>바닥글: 저작권 제거 불가
    CTL->>CTL: Dompdf A4 가로 렌더링
    CTL->>FS: runtime/tmp/export_*.pdf 쓰기
    CTL-->>C: 파일 다운로드
```

---

## 10. Flutter Web 컴포넌트 트리

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["로그인 폼<br/>사용자 이름/비밀번호/캡차"]
    LF --> CAPTCHA["클릭형 캡차 컴포넌트<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>클릭 표시 Circle"]

    DB --> SIDEBAR["사이드바 NavigationDrawer<br/>접이식 64px / 240px<br/>대시보드/사용자/역할/설정/로그/결제"]
    DB --> HEADER["상단바 56px<br/>접기 버튼 + 사용자 메뉴<br/>로그아웃 AlertDialog"]
    DB --> CONTENT["콘텐츠 영역"]
    CONTENT --> DASH["DashboardPage<br/>통계 카드 GridView<br/>추이 꺾은선 차트 LineChart<br/>분포 파이 차트 PieChart<br/>최근 작업 ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS 페이지 라우트

```mermaid
flowchart LR
    EA["EntryAbility<br/>시작"]
    EA -->|"Token 없음"| LP["LoginPage<br/>로그인 페이지"]
    EA -->|"Token 있음"| DP["DashboardPage<br/>대시보드"]

    LP -->|"로그인 성공<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>사용자 목록"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>내 정보"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>사용자 상세/추가/편집"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"로그아웃<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. 보안 심층 방어 전체 보기

```mermaid
flowchart TB
    subgraph "1계층: 휴먼 검증"
        L1["클릭형 캡차<br/>Click Captcha<br/>로그인/회원가입 필수"]
    end

    subgraph "2계층: 작업 확인"
        L2["비밀번호 2차 확인<br/>confirmPassword()<br/>DELETE 작업 필수"]
    end

    subgraph "3계층: 전송 보안"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "4계층: 인증"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "5계층: 권한 검증"
        L5["RBAC<br/>method.path 세분화<br/>슈퍼 관리자 * "]
    end

    subgraph "6계층: 데이터 보호"
        L6["인터페이스 ID: Hashids 암호화<br/>요청 본문: Encryption 암호화<br/>저장 계층: Encryptable 암호화<br/>내보내기: 마스킹+저작권"]
    end

    subgraph "7계층: 감사 추적"
        L7["OperationLog<br/>모든 작업 기록<br/>사용자/IP/시간/출처/파라미터"]
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

## 13. 배포 토폴로지

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "웹 서버"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["정적 파일<br/>Flutter Web build/"]
    end

    subgraph "애플리케이션 서버 (수평 확장 가능)"
        WM1["webman worker 1<br/>:8789"]
        WM2["webman worker 2<br/>:8789"]
        WM3["webman worker N<br/>:8789"]
    end

    subgraph "데이터 레이어"
        MYSQL["MySQL 8.0<br/>마스터-슬레이브 복제<br/>game_ 접두사"]
        ES["Elasticsearch 8.x<br/>3노드 클러스터<br/>game_ 접두사"]
        REDIS["Redis 7.x<br/>센티널 모드<br/>poster:captcha:*"]
    end

    subgraph "모니터링"
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
