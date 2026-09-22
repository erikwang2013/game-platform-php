# アーキテクチャ設計図とビジネスロジック図
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE.md) · [English](ARCHITECTURE.en.md) · [한국어](ARCHITECTURE.ko.md) · [Русский](ARCHITECTURE.ru.md) · [Deutsch](ARCHITECTURE.de.md) · [Français](ARCHITECTURE.fr.md) · [Español](ARCHITECTURE.es.md) · [Português](ARCHITECTURE.pt.md) · [हिन्दी](ARCHITECTURE.hi.md) · [العربية](ARCHITECTURE.ar.md) · [বাংলা](ARCHITECTURE.bn.md) · [Bahasa Indonesia](ARCHITECTURE.id.md) · **日本語**


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 以下の Mermaid 図は GitHub / GitLab / VS Code で自動レンダリングされます。その他の環境では [Mermaid Live Editor](https://mermaid.live/) でご確認ください。

---

## 1. システムトポロジーアーキテクチャ

```mermaid
flowchart TB
    subgraph "クライアント層"
        A1["Flutter Web<br/>PC 管理画面<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>スマホ/タブレットクライアント"]
    end

    subgraph "ゲートウェイ/エッジ層 (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>リバースプロキシ + HTTPS + Gzip<br/>静的ファイル配信"]
    end

    subgraph "アプリケーション層 (webman v2)"
        C1["AdminAuth ミドルウェア<br/>JWT 検証"]
        C2["AdminPermission ミドルウェア<br/>RBAC 権限チェック"]
        C3["管理画面 Controller<br/>Dashboard / User / Role / Permission / Payment"]
        C4["公開 Controller v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "ストレージ層"
        D1[("MySQL 8.0<br/>メインストレージ<br/>テーブル接頭辞 game_")]
        D2[("Elasticsearch<br/>全文検索<br/>インデックス接頭辞 game_")]
        D3[("Redis<br/>Session / キャッシュ<br/>Captcha ストレージ")]
    end

    subgraph "外部"
        E1["DevEco Studio<br/>HarmonyOS ビルド"]
        E2["Flutter SDK<br/>Web ビルド"]
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

## 2. バックエンド階層アーキテクチャ

```mermaid
flowchart TD
    subgraph "ルーティング層 Route Layer"
        R1["config/route.php<br/>URL → Controller マッピング"]
    end

    subgraph "ミドルウェア層 Middleware Layer"
        M_RL["RateLimit<br/>Redis スライディングウィンドウ制限<br/>X-RateLimit レスポンスヘッダー"]
        M_SF["SecurityFilter<br/>攻撃検知ブロック<br/>XSS/SQLインジェクション/パストラバーサル/CSRF"]
        M1["AdminAuth<br/>JWT Token 検証<br/>adminId の注入"]
        M2["AdminPermission<br/>RBAC 認可<br/>method.path 一致<br/>Redis 60s 権限キャッシュ"]
    end

    subgraph "コントローラー層 Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + 検索 + ページング"]
        CT3["RoleController<br/>CRUD + 権限同期"]
        CT4["PermissionController<br/>CRUD + ツリー構築"]
        CT5["DashboardController<br/>統計/トレンド/分布"]
        CT6["ExportController<br/>Excel/PDF エクスポート"]
        CT7["CaptchaController<br/>検証コードの生成/検証"]
        CT8["AuthController<br/>ログイン/登録/リフレッシュ"]
        CT9["AnalyticsController<br/>12 個のデータ分析エンドポイント<br/>概要/ランキング/確率/リテンション/ファネル/ARPU"]
    end

    subgraph "サービス層 Service Layer"
        S1["HashidsService<br/>ID エンコード/デコード"]
        S2["SnowflakeService<br/>グローバル一意 ID 生成"]
        S3["EncryptionService<br/>暗号化/復号 + マスキング"]
        S4["GameDashboardService<br/>概要/ランキング/DAU/時間別/行動分布<br/>MySQL リアルタイム集計、DB 障害時は空データを返す"]
        S5["DepositLogService<br/>収益概要/ゲーム転換率<br/>confirmed 注文統計"]
        S6["ProbabilityService<br/>結合/条件付き確率<br/>SQL ビルダー（エスケープ/引用/IN）"]
    end

    subgraph "モデル層 Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "ドライバー層 Driver Layer"
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

## 3. リクエストライフサイクル

```mermaid
sequenceDiagram
    participant C as クライアント
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

    C->>N: HTTPS リクエスト<br/>POST /admin/v1/*
    N->>MW_SF: 転送

    alt 非標準 HTTP メソッド (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else メソッド正当 (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: メソッドホワイトリスト検査通過
    end

    alt 攻撃検知が発動
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: 通過

    alt レート制限が発動
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW1: 通過

    alt Token が欠落または無効
        MW1-->>C: 401 Unauthorized
    else Token が有効
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt 権限なし
        MW2-->>C: 403 Forbidden
    else 権限あり
        MW2->>CTL: コントローラーへ遷移
    end

    CTL->>CTL: パラメータ検証 (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt 機密操作 (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt パスワード誤り
            CTL-->>C: 422 パスワード検証失敗
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast による自動復号
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: レスポンス JSON の構築
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: 操作ログ記録 (POST/PUT/DELETE)
```

---

## 4. 認証とキャプチャのフロー

```mermaid
sequenceDiagram
    participant U as ユーザー
    participant CL as クライアント
    participant SV as サーバー
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === 第一段階: 検証コードの取得 ===
    CL->>SV: POST /api/v1/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: 300×200 の背景画像を生成
    CAP->>CAP: 中国語の文字を N 個ランダム配置
    CAP->>CAP: key の生成、targets の保存
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === 第二段階: ユーザーのクリック ===
    CL->>CL: 検証コード画像の描画
    CL->>CL: プロンプト "順にクリック: 木 → 鳥 → 花"
    U->>CL: 画像内の文字位置を順にクリック
    CL->>CL: clicks の収集: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === 第三段階: ログイン ===
    CL->>SV: POST /api/v1/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt 検証コードが誤り
        CAP-->>SV: false
        SV-->>CL: 422 検証コードが誤り
    else 検証コードが正しい
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt 認証情報が誤り
            SV-->>CL: 401 ユーザー名またはパスワードが誤り
        else 認証情報が正しい
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === 後続リクエスト ===
    CL->>SV: GET /admin/v1/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC 権限モデル

```mermaid
flowchart LR
    subgraph "ユーザー User"
        U1["admin<br/>(スーパー管理者)"]
        U2["editor<br/>(編集者)"]
        U3["viewer<br/>(読み取り専用)"]
    end

    subgraph "ロール Role"
        R1["super_admin<br/>権限識別子: *"]
        R2["editor<br/>権限識別子: get.*, post.*"]
        R3["viewer<br/>権限識別子: get.*"]
    end

    subgraph "権限 Permission (ツリー)"
        P1["dashboard<br/>type=1 メニュー"]
        P2["user<br/>type=1 メニュー"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 ボタン"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (全権限)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "権限タイプ"
        T1["type=1 メニュー<br/>サイドバー表示/非表示の制御"]
        T2["type=2 ボタン<br/>ページ操作ボタンの制御"]
        T3["type=3 API<br/>API アクセスの制御"]
    end

    subgraph "権限識別子の形式"
        F1["{method}.{path}<br/>例: get.admin/user<br/>例: post.admin/user<br/>例: delete.admin/role"]
    end

    subgraph "判定フロー"
        J1["Token の抽出 → adminId"]
        J2["ユーザーロールの検索"]
        J3["全権限 slug の収集"]
        J4["method.path の構築"]
        J5{"一致?"}
        J6["許可"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"はい / slug=*"| J6
        J5 -->|いいえ| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. ID 全ライフサイクル

```mermaid
flowchart LR
    subgraph "1. 生成"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>例: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. 保存"
        S1["MySQL game_* テーブル<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["機密フィールド<br/>encryptable cast<br/>AES-128-ECB 暗号化"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. 転送"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid 文字列<br/>例: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. 逆デコード"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. データ暗号化の階層

```mermaid
flowchart TB
    subgraph "トランスポート層暗号化 (encryption)"
        E1["クライアントが機密データを送信"]
        E2["AES-256-CBC 暗号化"]
        E3["API で暗号文を転送"]
        E4["サーバーがデータを復号"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "ストレージ層暗号化 (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["書き込み: 自動暗号化"]
        D3["MySQL VARCHAR(500)<br/>暗号文を保存"]
        D4["読み取り: 自動復号"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "表示層マスキング (mask)"
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

## 8. データベース ER 関係

```mermaid
erDiagram
    game_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "暗号化"
        VARCHAR phone "暗号化"
        VARCHAR id_card "暗号化"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "ソフト削除"
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
        BIGINT parent_id FK "自己参照"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1メニュー2ボタン3API"
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
        VARCHAR source "アクセス元"
        TEXT input "マスキング"
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

## 9. エクスポート業務フロー

```mermaid
sequenceDiagram
    participant C as クライアント
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as ファイルシステム

    Note over C,FS: === Excel エクスポート ===
    C->>CTL: POST /admin/v1/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: データ
    CTL->>CTL: 機密フィールドの復号
    CTL->>CTL: マスキング処理 (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet で構築<br/>ヘッダー: 青背景に白文字<br/>データ行の細い罫線<br/>先頭行の固定<br/>オートフィルター
    CTL->>FS: runtime/tmp/export_*.xlsx へ書き込み
    CTL-->>C: ファイルダウンロード

    Note over C,FS: === PDF エクスポート ===
    C->>CTL: POST /admin/v1/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>ヘッダー: タイトル+著作権+時刻<br/>本文: 表またはカード<br/>フッター: 削除不可の著作権
    CTL->>CTL: Dompdf でレンダリング A4 横向き
    CTL->>FS: runtime/tmp/export_*.pdf へ書き込み
    CTL-->>C: ファイルダウンロード
```

---

## 10. Flutter Web コンポーネントツリー

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["ログインフォーム<br/>ユーザー名/パスワード/検証コード"]
    LF --> CAPTCHA["クリック検証コードコンポーネント<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>クリックマーク Circle"]

    DB --> SIDEBAR["サイドバー NavigationDrawer<br/>折りたたみ可 64px / 240px<br/>ダッシュボード/ユーザー/ロール/設定/ログ/支払い"]
    DB --> HEADER["ヘッダーバー 56px<br/>折りたたみボタン + ユーザーメニュー<br/>ログアウト AlertDialog"]
    DB --> CONTENT["コンテンツ領域"]
    CONTENT --> DASH["DashboardPage<br/>統計カード GridView<br/>トレンド折れ線グラフ LineChart<br/>分布円グラフ PieChart<br/>最近の操作 ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS ページルーティング

```mermaid
flowchart LR
    EA["EntryAbility<br/>起動"]
    EA -->|"Token なし"| LP["LoginPage<br/>ログインページ"]
    EA -->|"Token あり"| DP["DashboardPage<br/>ダッシュボード"]

    LP -->|"ログイン成功<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>ユーザー一覧"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>マイページ"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>ユーザー詳細/新規/編集"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"ログアウト<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. セキュリティ多層防御の全景

```mermaid
flowchart TB
    subgraph "第1層: 人機検証"
        L1["クリック検証コード<br/>Click Captcha<br/>ログイン/登録で必須"]
    end

    subgraph "第2層: 操作確認"
        L2["パスワード再確認<br/>confirmPassword()<br/>DELETE 操作で必須"]
    end

    subgraph "第3層: 伝送セキュリティ"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "第4層: 本人認証"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "第5層: 権限認可"
        L5["RBAC<br/>method.path 粒度<br/>スーパー管理者 * "]
    end

    subgraph "第6層: データ保護"
        L6["API ID: Hashids 暗号化<br/>リクエスト本文: Encryption 暗号化<br/>ストレージ層: Encryptable 暗号化<br/>エクスポート: マスキング+著作権"]
    end

    subgraph "第7層: 監査追跡"
        L7["OperationLog<br/>全操作を記録<br/>ユーザー/IP/時刻/アクセス元/パラメータ"]
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

## 13. デプロイトポロジー

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web サーバー"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["静的ファイル<br/>Flutter Web build/"]
    end

    subgraph "アプリケーションサーバー (水平スケール可)"
        WM1["webman worker 1<br/>:8789"]
        WM2["webman worker 2<br/>:8789"]
        WM3["webman worker N<br/>:8789"]
    end

    subgraph "データ層"
        MYSQL["MySQL 8.0<br/>マスタースレーブ複製<br/>game_ 接頭辞"]
        ES["Elasticsearch 8.x<br/>3 ノードクラスタ<br/>game_ 接頭辞"]
        REDIS["Redis 7.x<br/>センチネルモード<br/>poster:captcha:*"]
    end

    subgraph "監視"
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
