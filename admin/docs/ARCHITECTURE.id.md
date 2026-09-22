# Diagram Arsitektur & Diagram Logika Bisnis
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE.md) · [English](ARCHITECTURE.en.md) · [한국어](ARCHITECTURE.ko.md) · [Русский](ARCHITECTURE.ru.md) · [Deutsch](ARCHITECTURE.de.md) · [Français](ARCHITECTURE.fr.md) · [Español](ARCHITECTURE.es.md) · [Português](ARCHITECTURE.pt.md) · [हिन्दी](ARCHITECTURE.hi.md) · [العربية](ARCHITECTURE.ar.md) · [বাংলা](ARCHITECTURE.bn.md) · **Bahasa Indonesia** · [日本語](ARCHITECTURE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Diagram Mermaid di bawah ini dirender otomatis di GitHub / GitLab / VS Code. Untuk lingkungan lain, gunakan [Mermaid Live Editor](https://mermaid.live/).

---

## 1. Topologi Arsitektur Sistem

```mermaid
flowchart TB
    subgraph "Lapisan Klien"
        A1["Flutter Web<br/>Backend administrasi PC<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>Klien ponsel/tablet"]
    end

    subgraph "Lapisan gateway/edge (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>Reverse proxy + HTTPS + Gzip<br/>Layanan file statis"]
    end

    subgraph "Lapisan aplikasi (webman v2)"
        C1["Middleware AdminAuth<br/>Verifikasi JWT"]
        C2["Middleware AdminPermission<br/>Validasi izin RBAC"]
        C3["Controller sisi admin<br/>Dashboard / User / Role / Permission / Payment"]
        C4["Controller publik v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Lapisan penyimpanan"
        D1[("MySQL 8.0<br/>Penyimpanan utama<br/>Prefiks tabel game_")]
        D2[("Elasticsearch<br/>Pencarian full-text<br/>Prefiks indeks game_")]
        D3[("Redis<br/>Session / cache<br/>Penyimpanan Captcha")]
    end

    subgraph "Eksternal"
        E1["DevEco Studio<br/>Build HarmonyOS"]
        E2["Flutter SDK<br/>Build Web"]
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

## 2. Arsitektur Backend Berlapis

```mermaid
flowchart TD
    subgraph "Lapisan rute Route Layer"
        R1["config/route.php<br/>Pemetaan URL → Controller"]
    end

    subgraph "Lapisan middleware Middleware Layer"
        M_RL["RateLimit<br/>Pembatasan sliding window Redis<br/>Header respons X-RateLimit"]
        M_SF["SecurityFilter<br/>Deteksi dan blokir serangan<br/>XSS/SQL injection/path traversal/CSRF"]
        M1["AdminAuth<br/>Verifikasi Token JWT<br/>Menyuntikkan adminId"]
        M2["AdminPermission<br/>Otorisasi RBAC<br/>Pencocokan method.path<br/>Cache izin Redis 60s"]
    end

    subgraph "Lapisan kontroler Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + pencarian + paginasi"]
        CT3["RoleController<br/>CRUD + sinkronisasi izin"]
        CT4["PermissionController<br/>CRUD + pembangunan pohon"]
        CT5["DashboardController<br/>Statistik/tren/distribusi"]
        CT6["ExportController<br/>Ekspor Excel/PDF"]
        CT7["CaptchaController<br/>Pembuatan/verifikasi captcha"]
        CT8["AuthController<br/>Masuk/daftar/segarkan"]
        CT9["AnalyticsController<br/>12 endpoint analisis data<br/>Ringkasan/peringkat/probabilitas/retensi/funnel/ARPU"]
    end

    subgraph "Lapisan layanan Service Layer"
        S1["HashidsService<br/>Enkode/dekode ID"]
        S2["SnowflakeService<br/>Pembuatan ID unik global"]
        S3["EncryptionService<br/>Enkripsi/dekripsi + penyamaran"]
        S4["GameDashboardService<br/>Ringkasan/peringkat/DAU/jam/distribusi perilaku<br/>Agregasi real-time MySQL, jika DB gagal kembalikan data kosong"]
        S5["DepositLogService<br/>Ringkasan pendapatan/konversi game<br/>Statistik pesanan confirmed"]
        S6["ProbabilityService<br/>Probabilitas gabungan/bersyarat<br/>Pembangun SQL (escape/kutip/IN)"]
    end

    subgraph "Lapisan model Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Lapisan driver Driver Layer"
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

## 3. Siklus Hidup Permintaan

```mermaid
sequenceDiagram
    participant C as Klien
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

    C->>N: Permintaan HTTPS<br/>POST /admin/v1/*
    N->>MW_SF: Teruskan

    alt Metode HTTP non-standar (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Metode sah (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Pemeriksaan daftar putih metode lulus
    end

    alt Deteksi serangan terpicu
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Lulus

    alt Pembatasan frekuensi terpicu
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW1: Lulus

    alt Token hilang atau tidak valid
        MW1-->>C: 401 Unauthorized
    else Token valid
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt Tidak ada izin
        MW2-->>C: 403 Forbidden
    else Memiliki izin
        MW2->>CTL: Masuk ke Controller
    end

    CTL->>CTL: Validasi parameter (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Operasi sensitif (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Kata sandi salah
            CTL-->>C: 422 Verifikasi kata sandi gagal
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: Dekripsi otomatis cast encryptable
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: Bangun respons JSON
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Catat log operasi (POST/PUT/DELETE)
```

---

## 4. Alur Autentikasi & CAPTCHA

```mermaid
sequenceDiagram
    participant U as Pengguna
    participant CL as Klien
    participant SV as Sisi server
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Langkah 1: Ambil captcha ===
    CL->>SV: POST /api/v1/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Buat gambar latar 300×200
    CAP->>CAP: Tempatkan N target karakter Tionghoa secara acak
    CAP->>CAP: Buat key, simpan targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Langkah 2: Pengguna mengklik ===
    CL->>CL: Render gambar captcha
    CL->>CL: Petunjuk "klik berurutan: pohon → burung → bunga"
    U->>CL: Klik posisi teks pada gambar secara berurutan
    CL->>CL: Kumpulkan clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Langkah 3: Masuk ===
    CL->>SV: POST /api/v1/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Captcha salah
        CAP-->>SV: false
        SV-->>CL: 422 Captcha salah
    else Captcha benar
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Kredensial salah
            SV-->>CL: 401 Nama pengguna atau kata sandi salah
        else Kredensial benar
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Permintaan selanjutnya ===
    CL->>SV: GET /admin/v1/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. Model Izin RBAC

```mermaid
flowchart LR
    subgraph "Pengguna User"
        U1["admin<br/>(super admin)"]
        U2["editor<br/>(pengedit)"]
        U3["viewer<br/>(hanya baca)"]
    end

    subgraph "Peran Role"
        R1["super_admin<br/>Penanda izin: *"]
        R2["editor<br/>Penanda izin: get.*, post.*"]
        R3["viewer<br/>Penanda izin: get.*"]
    end

    subgraph "Izin Permission (pohon)"
        P1["dashboard<br/>menu type=1"]
        P2["user<br/>menu type=1"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>tombol type=2"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (izin penuh)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "Jenis izin"
        T1["menu type=1<br/>Kontrol tampil/sembunyi sidebar"]
        T2["tombol type=2<br/>Kontrol tombol aksi halaman"]
        T3["type=3 API<br/>Kontrol akses API"]
    end

    subgraph "Format penanda izin"
        F1["{method}.{path}<br/>contoh: get.admin/user<br/>contoh: post.admin/user<br/>contoh: delete.admin/role"]
    end

    subgraph "Alur penentuan"
        J1["Ekstrak Token → adminId"]
        J2["Cari peran pengguna"]
        J3["Kumpulkan semua slug izin"]
        J4["Susun method.path"]
        J5{"Cocok?"}
        J6["Loloskan"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"Ya / slug=*"| J6
        J5 -->|Tidak| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Siklus Hidup Penuh ID

```mermaid
flowchart LR
    subgraph "1. Buat"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>contoh: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Simpan"
        S1["Tabel MySQL game_*<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Bidang sensitif<br/>cast encryptable<br/>Enkripsi AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Transmisi"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["String hashid<br/>contoh: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Dekode balik"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Lapisan Enkripsi Data

```mermaid
flowchart TB
    subgraph "Enkripsi lapisan transmisi (encryption)"
        E1["Klien mengirim data sensitif"]
        E2["Enkripsi AES-256-CBC"]
        E3["Ciphertext transmisi API"]
        E4["Sisi server mendekripsi dan memproses"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Enkripsi lapisan penyimpanan (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Tulis: enkripsi otomatis"]
        D3["MySQL VARCHAR(500)<br/>Menyimpan ciphertext"]
        D4["Baca: dekripsi otomatis"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Penyamaran lapisan tampilan (mask)"
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

## 8. Hubungan ER Database

```mermaid
erDiagram
    game_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "Enkripsi"
        VARCHAR phone "Enkripsi"
        VARCHAR id_card "Enkripsi"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Penghapusan lunak"
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
        BIGINT parent_id FK "Referensi sendiri"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1 menu 2 tombol 3 API"
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
        VARCHAR source "Sumber"
        TEXT input "Penyamaran"
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

## 9. Alur Bisnis Ekspor

```mermaid
sequenceDiagram
    participant C as Klien
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Sistem file

    Note over C,FS: === Ekspor Excel ===
    C->>CTL: POST /admin/v1/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Data
    CTL->>CTL: Dekripsi bidang sensitif
    CTL->>CTL: Penyamaran (maskPhone/maskEmail)
    CTL->>CTL: Membangun PhpSpreadsheet<br/>Header biru teks putih<br/>Baris data garis tepi tipis<br/>Bekukan baris pertama<br/>Filter otomatis
    CTL->>FS: Tulis ke runtime/tmp/export_*.xlsx
    CTL-->>C: Unduh file

    Note over C,FS: === Ekspor PDF ===
    C->>CTL: POST /admin/v1/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>Header: judul+hak cipta+waktu<br/>Isi: tabel atau kartu<br/>Footer: hak cipta tidak dapat dihapus
    CTL->>CTL: Render Dompdf A4 lanskap
    CTL->>FS: Tulis ke runtime/tmp/export_*.pdf
    CTL-->>C: Unduh file
```

---

## 10. Pohon Komponen Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Formulir masuk<br/>Nama pengguna/kata sandi/captcha"]
    LF --> CAPTCHA["Komponen captcha klik<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Tanda klik Circle"]

    DB --> SIDEBAR["Sidebar NavigationDrawer<br/>Dapat dilipat 64px / 240px<br/>Dasbor/pengguna/peran/konfigurasi/log/pembayaran"]
    DB --> HEADER["Header atas 56px<br/>Tombol lipat + menu pengguna<br/>Keluar AlertDialog"]
    DB --> CONTENT["Area konten"]
    CONTENT --> DASH["DashboardPage<br/>Kartu statistik GridView<br/>Grafik garis tren LineChart<br/>Diagram lingkaran distribusi PieChart<br/>Operasi terbaru ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. Rute Halaman HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>Mulai"]
    EA -->|"Tanpa Token"| LP["LoginPage<br/>Halaman masuk"]
    EA -->|"Dengan Token"| DP["DashboardPage<br/>Dasbor"]

    LP -->|"Berhasil masuk<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>Daftar pengguna"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Profil pengguna"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>Detail/tambah/edit pengguna"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"Keluar<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Panorama Pertahanan Berlapis Keamanan

```mermaid
flowchart TB
    subgraph "Lapisan 1: Verifikasi manusia-mesin"
        L1["Captcha klik<br/>Click Captcha<br/>Wajib login/registrasi"]
    end

    subgraph "Lapisan 2: Konfirmasi operasi"
        L2["Konfirmasi kata sandi kedua<br/>confirmPassword()<br/>Wajib untuk operasi DELETE"]
    end

    subgraph "Lapisan 3: Keamanan transmisi"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Lapisan 4: Autentikasi identitas"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "Lapisan 5: Otorisasi izin"
        L5["RBAC<br/>granularitas method.path<br/>Super admin * "]
    end

    subgraph "Lapisan 6: Perlindungan data"
        L6["ID antarmuka: enkripsi Hashids<br/>Body permintaan: enkripsi Encryption<br/>Lapisan penyimpanan: enkripsi Encryptable<br/>Ekspor: penyamaran+hak cipta"]
    end

    subgraph "Lapisan 7: Jejak audit"
        L7["OperationLog<br/>Catat semua operasi<br/>Pengguna/IP/waktu/sumber/parameter"]
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

## 13. Topologi Deployment

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Server Web"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["File statis<br/>Flutter Web build/"]
    end

    subgraph "Server aplikasi (skalabel horizontal)"
        WM1["webman worker 1<br/>:8789"]
        WM2["webman worker 2<br/>:8789"]
        WM3["webman worker N<br/>:8789"]
    end

    subgraph "Lapisan data"
        MYSQL["MySQL 8.0<br/>Replikasi master-slave<br/>Prefiks game_"]
        ES["Elasticsearch 8.x<br/>Klaster 3 node<br/>Prefiks game_"]
        REDIS["Redis 7.x<br/>Mode sentinel<br/>poster:captcha:*"]
    end

    subgraph "Monitoring"
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
