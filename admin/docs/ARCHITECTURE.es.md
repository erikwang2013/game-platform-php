# Diagramas de arquitectura y de lógica de negocio
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE.md) · [English](ARCHITECTURE.en.md) · [한국어](ARCHITECTURE.ko.md) · [Русский](ARCHITECTURE.ru.md) · [Deutsch](ARCHITECTURE.de.md) · [Français](ARCHITECTURE.fr.md) · **Español** · [Português](ARCHITECTURE.pt.md) · [हिन्दी](ARCHITECTURE.hi.md) · [العربية](ARCHITECTURE.ar.md) · [বাংলা](ARCHITECTURE.bn.md) · [Bahasa Indonesia](ARCHITECTURE.id.md) · [日本語](ARCHITECTURE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Los siguientes diagramas Mermaid se renderizan automáticamente en GitHub / GitLab / VS Code. En otros entornos, usa el [Mermaid Live Editor](https://mermaid.live/).

---

## 1. Topología del sistema

```mermaid
flowchart TB
    subgraph "Capa de clientes"
        A1["Flutter Web<br/>Panel de administración PC<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>Cliente móvil/tableta"]
    end

    subgraph "Capa de pasarela/borde (Nginx Edge)"
        B1["Nodo perimetral Nginx<br/>Docker nginx:alpine<br/>Proxy inverso + HTTPS + Gzip<br/>Servicio de archivos estáticos"]
    end

    subgraph "Capa de aplicación (webman v2)"
        C1["Middleware AdminAuth<br/>Verificación JWT"]
        C2["Middleware AdminPermission<br/>Validación de permisos RBAC"]
        C3["Controlador de admin<br/>Dashboard / User / Role / Permission / Payment"]
        C4["Controlador público v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Capa de datos"
        D1[("MySQL 8.0<br/>Almacenamiento principal<br/>Prefijo de tablas game_")]
        D2[("Elasticsearch<br/>Búsqueda de texto completo<br/>Prefijo de índices game_")]
        D3[("Redis<br/>Session / caché<br/>Almacenamiento de captcha")]
    end

    subgraph "Externo"
        E1["DevEco Studio<br/>Compilación HarmonyOS"]
        E2["Flutter SDK<br/>Compilación web"]
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

## 2. Arquitectura de capas del backend

```mermaid
flowchart TD
    subgraph "Capa de enrutamiento"
        R1["config/route.php<br/>Mapeo URL → Controller"]
    end

    subgraph "Capa de middleware"
        M_RL["RateLimit<br/>Límite de frecuencia por ventana deslizante en Redis<br/>Cabecera de respuesta X-RateLimit"]
        M_SF["SecurityFilter<br/>Detección e intercepción de ataques<br/>XSS/inyección SQL/path traversal/CSRF"]
        M1["AdminAuth<br/>Verificación del token JWT<br/>Inyecta adminId"]
        M2["AdminPermission<br/>Autorización RBAC<br/>Coincidencia de method.path<br/>Caché de permisos en Redis 60s"]
    end

    subgraph "Capa de controladores"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + búsqueda + paginación"]
        CT3["RoleController<br/>CRUD + sincronización de permisos"]
        CT4["PermissionController<br/>CRUD + construcción del árbol"]
        CT5["DashboardController<br/>Estadísticas/tendencias/distribución"]
        CT6["ExportController<br/>Exportación a Excel/PDF"]
        CT7["CaptchaController<br/>Generación/validación de captcha"]
        CT8["AuthController<br/>Inicio de sesión/registro/refresco"]
        CT9["AnalyticsController<br/>12 endpoints de análisis<br/>Resumen/clasificaciones/probabilidad/retención/embudo/ARPU"]
    end

    subgraph "Capa de servicios"
        S1["HashidsService<br/>Codificación/decodificación de ID"]
        S2["SnowflakeService<br/>Generación global de ID único"]
        S3["EncryptionService<br/>Cifrado/descifrado + enmascaramiento"]
        S4["GameDashboardService<br/>Resumen/clasificaciones/DAU/por hora/distribución de comportamiento<br/>Agregación en tiempo real en MySQL; datos vacíos si falla la BD"]
        S5["DepositLogService<br/>Resumen de ingresos/tasa de conversión de juego<br/>Estadísticas de órdenes confirmed"]
        S6["ProbabilityService<br/>Probabilidad conjunta/condicional<br/>Constructor SQL (escape/quoting/IN)"]
    end

    subgraph "Capa de modelos"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Capa de drivers"
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

## 3. Ciclo de vida de las solicitudes

```mermaid
sequenceDiagram
    participant C as Cliente
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

    C->>N: Solicitud HTTPS<br/>POST /admin/v1/*
    N->>MW_SF: Reenvío

    alt Método HTTP no estándar (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Método permitido (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Comprobación de lista blanca de métodos superada
    end

    alt Detección de ataques activada
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Correcto

    alt Límite de frecuencia activado
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW1: Correcto

    alt Token ausente o no válido
        MW1-->>C: 401 Unauthorized
    else Token válido
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt Sin permiso
        MW2-->>C: 403 Forbidden
    else Con permiso
        MW2->>CTL: Entrar al controlador
    end

    CTL->>CTL: Validación de parámetros (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Operación sensible (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Contraseña incorrecta
            CTL-->>C: 422 Verificación de contraseña fallida
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: descifrado automático del cast encryptable
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: Construir JSON de respuesta
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Registrar la operación (POST/PUT/DELETE)
```

---

## 4. Flujo de autenticación y captcha

```mermaid
sequenceDiagram
    participant U as Usuario
    participant CL as Cliente
    participant SV as Servidor
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Paso 1: Obtener captcha ===
    CL->>SV: POST /api/v1/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Generar imagen de fondo de 300×200
    CAP->>CAP: Colocar aleatoriamente N objetivos chinos
    CAP->>CAP: Generar key, almacenar targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Paso 2: El usuario hace clic ===
    CL->>CL: Renderizar la imagen del captcha
    CL->>CL: Aviso "Haga clic en orden: árbol → pájaro → flor"
    U->>CL: Hacer clic sucesivamente en las posiciones de los caracteres de la imagen
    CL->>CL: Recopilar clics: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Paso 3: Inicio de sesión ===
    CL->>SV: POST /api/v1/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Captcha incorrecto
        CAP-->>SV: false
        SV-->>CL: 422 Captcha incorrecto
    else Captcha correcto
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Credenciales incorrectas
            SV-->>CL: 401 Nombre de usuario o contraseña incorrectos
        else Credenciales correctas
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Solicitudes posteriores ===
    CL->>SV: GET /admin/v1/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. Modelo de permisos RBAC

```mermaid
flowchart LR
    subgraph "Usuario"
        U1["admin<br/>(superadministrador)"]
        U2["editor<br/>(editor)"]
        U3["viewer<br/>(solo lectura)"]
    end

    subgraph "Rol"
        R1["super_admin<br/>slug de permiso: *"]
        R2["editor<br/>slug de permiso: get.*, post.*"]
        R3["viewer<br/>slug de permiso: get.*"]
    end

    subgraph "Permiso (árbol)"
        P1["dashboard<br/>type=1 menú"]
        P2["user<br/>type=1 menú"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 botón"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (permisos completos)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "Tipos de permiso"
        T1["type=1 menú<br/>controla mostrar/ocultar la barra lateral"]
        T2["type=2 botón<br/>controla los botones de acción de la página"]
        T3["type=3 API<br/>controla el acceso a la API"]
    end

    subgraph "Formato del slug de permiso"
        F1["{method}.{path}<br/>ej.: get.admin/user<br/>ej.: post.admin/user<br/>ej.: delete.admin/role"]
    end

    subgraph "Flujo de decisión"
        J1["Extraer Token → adminId"]
        J2["Buscar los roles del usuario"]
        J3["Recopilar todos los slugs de permisos"]
        J4["Construir method.path"]
        J5{"¿Coincidencia?"}
        J6["Permitir"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"Sí / slug=*"| J6
        J5 -->|No| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Ciclo de vida completo de los ID

```mermaid
flowchart LR
    subgraph "1. Generar"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>ej.: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Almacenar"
        S1["Tabla game_* de MySQL<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Campos sensibles<br/>cast encryptable<br/>cifrado AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Transmitir"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["cadena hashid<br/>ej.: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Decodificación inversa"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Capas de cifrado de datos

```mermaid
flowchart TB
    subgraph "Cifrado en la capa de transmisión (encryption)"
        E1["El cliente envía datos sensibles"]
        E2["Cifrado AES-256-CBC"]
        E3["Texto cifrado transmitido por la API"]
        E4["El servidor descifra y procesa"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Cifrado en la capa de almacenamiento (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Al escribir: cifrado automático"]
        D3["MySQL VARCHAR(500)<br/>almacena texto cifrado"]
        D4["Al leer: descifrado automático"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Enmascaramiento en la capa de presentación (mask)"
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

## 8. Relaciones ER de la base de datos

```mermaid
erDiagram
    game_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "Cifrado"
        VARCHAR phone "Cifrado"
        VARCHAR id_card "Cifrado"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Borrado lógico"
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
        BIGINT parent_id FK "Autorreferencia"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1 menú 2 botón 3 API"
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
        VARCHAR source "Plataforma de origen"
        TEXT input "Enmascarado"
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

## 9. Flujo de negocio de exportación

```mermaid
sequenceDiagram
    participant C as Cliente
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Sistema de archivos

    Note over C,FS: === Exportación a Excel ===
    C->>CTL: POST /admin/v1/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Datos
    CTL->>CTL: Descifrar campos sensibles
    CTL->>CTL: Enmascaramiento (maskPhone/maskEmail)
    CTL->>CTL: Construcción con PhpSpreadsheet<br/>Encabezado azul con texto blanco<br/>Bordes finos en las filas de datos<br/>Congelar primera fila<br/>Filtro automático
    CTL->>FS: Escribir en runtime/tmp/export_*.xlsx
    CTL-->>C: Descarga del archivo

    Note over C,FS: === Exportación a PDF ===
    C->>CTL: POST /admin/v1/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>Encabezado: título + copyright + hora<br/>Contenido: tabla o tarjetas<br/>Pie: copyright no eliminable
    CTL->>CTL: Dompdf renderiza A4 horizontal
    CTL->>FS: Escribir en runtime/tmp/export_*.pdf
    CTL-->>C: Descarga del archivo
```

---

## 10. Árbol de componentes Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Formulario de inicio de sesión<br/>Usuario/contraseña/captcha"]
    LF --> CAPTCHA["Widget de captcha por clic<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>El clic marca Circle"]

    DB --> SIDEBAR["Barra lateral NavigationDrawer<br/>Plegable 64px / 240px<br/>Panel/usuarios/roles/configuración/registros/pagos"]
    DB --> HEADER["Barra superior 56px<br/>Botón de plegado + menú de usuario<br/>Cerrar sesión AlertDialog"]
    DB --> CONTENT["Área de contenido"]
    CONTENT --> DASH["DashboardPage<br/>Tarjetas de estadísticas GridView<br/>Gráfico de líneas de tendencia LineChart<br/>Gráfico circular de distribución PieChart<br/>Operaciones recientes ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. Rutas de páginas de HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>Inicio"]
    EA -->|"Sin Token"| LP["LoginPage<br/>Página de inicio de sesión"]
    EA -->|"Con Token"| DP["DashboardPage<br/>Panel"]

    LP -->|"Inicio de sesión correcto<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>Lista de usuarios"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Perfil"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>Detalles del usuario/crear/editar"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"Cerrar sesión<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Panorama de defensa en profundidad de seguridad

```mermaid
flowchart TB
    subgraph "Capa 1: Verificación humano-máquina"
        L1["Captcha de clic<br/>Click Captcha<br/>Obligatorio en login/registro"]
    end

    subgraph "Capa 2: Confirmación de operación"
        L2["Confirmación de contraseña<br/>confirmPassword()<br/>Obligatorio en DELETE"]
    end

    subgraph "Capa 3: Seguridad de transmisión"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Capa 4: Autenticación"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "Capa 5: Autorización"
        L5["RBAC<br/>granularidad de method.path<br/>superadministrador * "]
    end

    subgraph "Capa 6: Protección de datos"
        L6["IDs de API: cifrado Hashids<br/>Cuerpo de la petición: cifrado Encryption<br/>Capa de almacenamiento: cifrado Encryptable<br/>Exportación: enmascaramiento + copyright"]
    end

    subgraph "Capa 7: Trazabilidad de auditoría"
        L7["OperationLog<br/>registra todas las operaciones<br/>usuario/IP/hora/plataforma de origen/parámetros"]
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

## 13. Topología de despliegue

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Servidor web"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["Archivos estáticos<br/>Flutter Web build/"]
    end

    subgraph "Servidores de aplicación (escalables horizontalmente)"
        WM1["webman worker 1<br/>:8789"]
        WM2["webman worker 2<br/>:8789"]
        WM3["webman worker N<br/>:8789"]
    end

    subgraph "Capa de datos"
        MYSQL["MySQL 8.0<br/>Replicación maestro-esclavo<br/>prefijo game_"]
        ES["Elasticsearch 8.x<br/>clúster de 3 nodos<br/>prefijo game_"]
        REDIS["Redis 7.x<br/>modo Sentinel<br/>poster:captcha:*"]
    end

    subgraph "Monitorización"
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
