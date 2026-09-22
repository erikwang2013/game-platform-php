# Diagramas de arquitetura e diagramas de lógica de negócio
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE.md) · [English](ARCHITECTURE.en.md) · [한국어](ARCHITECTURE.ko.md) · [Русский](ARCHITECTURE.ru.md) · [Deutsch](ARCHITECTURE.de.md) · [Français](ARCHITECTURE.fr.md) · [Español](ARCHITECTURE.es.md) · **Português** · [हिन्दी](ARCHITECTURE.hi.md) · [العربية](ARCHITECTURE.ar.md) · [বাংলা](ARCHITECTURE.bn.md) · [Bahasa Indonesia](ARCHITECTURE.id.md) · [日本語](ARCHITECTURE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Os diagramas Mermaid abaixo são renderizados automaticamente no GitHub / GitLab / VS Code. Em outros ambientes, use o [Mermaid Live Editor](https://mermaid.live/).

---

## 1. Topologia do sistema

```mermaid
flowchart TB
    subgraph "Camada de Clientes"
        A1["Flutter Web<br/>Painel administrativo PC<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>Cliente mobile/tablet"]
    end

    subgraph "Camada de Gateway/Borda (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>Proxy reverso + HTTPS + Gzip<br/>Serviço de arquivos estáticos"]
    end

    subgraph "Camada de Aplicação (webman v2)"
        C1["Middleware AdminAuth<br/>Validação JWT"]
        C2["Middleware AdminPermission<br/>Verificação de permissão RBAC"]
        C3["Controller do painel<br/>Dashboard / User / Role / Permission / Payment"]
        C4["Controller público v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Camada de Armazenamento"
        D1[("MySQL 8.0<br/>Armazenamento principal<br/>Prefixo de tabela game_")]
        D2[("Elasticsearch<br/>Busca fulltext<br/>Prefixo de índice game_")]
        D3[("Redis<br/>Session / cache<br/>Armazenamento de Captcha")]
    end

    subgraph "Externo"
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

## 2. Arquitetura em camadas do backend

```mermaid
flowchart TD
    subgraph "Camada de Rotas Route Layer"
        R1["config/route.php<br/>Mapeamento URL → Controller"]
    end

    subgraph "Camada de Middleware Middleware Layer"
        M_RL["RateLimit<br/>Limitação por janela deslizante Redis<br/>Cabeçalho de resposta X-RateLimit"]
        M_SF["SecurityFilter<br/>Detecção e bloqueio de ataques<br/>XSS/injeção SQL/traversal de caminho/CSRF"]
        M1["AdminAuth<br/>Validação do token JWT<br/>Injeta adminId"]
        M2["AdminPermission<br/>Autorização RBAC<br/>Correspondência method.path<br/>Cache de permissões no Redis por 60s"]
    end

    subgraph "Camada de Controllers Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + busca + paginação"]
        CT3["RoleController<br/>CRUD + sincronização de permissões"]
        CT4["PermissionController<br/>CRUD + montagem de árvore"]
        CT5["DashboardController<br/>estatísticas/tendências/distribuição"]
        CT6["ExportController<br/>Exportação Excel/PDF"]
        CT7["CaptchaController<br/>Geração/validação de captcha"]
        CT8["AuthController<br/>Login/registro/refresh"]
        CT9["AnalyticsController<br/>12 endpoints de análise de dados<br/>Visão geral/ranking/probabilidade/retenção/funil/ARPU"]
    end

    subgraph "Camada de Serviços Service Layer"
        S1["HashidsService<br/>Codificação/decodificação de ID"]
        S2["SnowflakeService<br/>Geração de ID único global"]
        S3["EncryptionService<br/>Criptografia/descriptografia + mascaramento"]
        S4["GameDashboardService<br/>Visão geral/ranking/DAU/hora/distribuição de comportamento<br/>Agregação em tempo real no MySQL, em caso de falha do DB retorna dados vazios"]
        S5["DepositLogService<br/>Visão geral de receita/taxa de conversão de jogos<br/>Estatísticas de pedidos confirmed"]
        S6["ProbabilityService<br/>Probabilidade conjunta/condicional<br/>Construtor de SQL (escape/quoting/IN)"]
    end

    subgraph "Camada de Modelos Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Camada de Drivers Driver Layer"
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

## 3. Ciclo de vida da requisição

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

    C->>N: Requisição HTTPS<br/>POST /admin/v1/*
    N->>MW_SF: Encaminhamento

    alt Método HTTP não padrão (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Método válido (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Verificação de whitelist de métodos aprovada
    end

    alt Detecção de ataque acionada
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Aprovado

    alt Limitação de taxa acionada
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW1: Aprovado

    alt Token ausente ou inválido
        MW1-->>C: 401 Unauthorized
    else Token válido
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt Sem permissão
        MW2-->>C: 403 Forbidden
    else Com permissão
        MW2->>CTL: Entra no controller
    end

    CTL->>CTL: Validação de parâmetros (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Operação sensível (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Senha incorreta
            CTL-->>C: 422 falha na validação de senha
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: Descriptografia automática do cast encryptable
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: Monta o JSON de resposta
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Registra log de operação (POST/PUT/DELETE)
```

---

## 4. Fluxo de autenticação e CAPTCHA

```mermaid
sequenceDiagram
    participant U as Usuário
    participant CL as Cliente
    participant SV as Servidor
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Primeira etapa: obter o captcha ===
    CL->>SV: POST /api/v1/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Gera a imagem de fundo 300×200
    CAP->>CAP: Posiciona aleatoriamente N alvos em chinês
    CAP->>CAP: Gera key e armazena targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Segunda etapa: clique do usuário ===
    CL->>CL: Renderiza a imagem do captcha
    CL->>CL: Exibe "clique na ordem: árvore → pássaro → flor"
    U->>CL: Clica em sequência nas posições do texto na imagem
    CL->>CL: Coleta clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Terceira etapa: login ===
    CL->>SV: POST /api/v1/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Captcha incorreto
        CAP-->>SV: false
        SV-->>CL: 422 captcha incorreto
    else Captcha correto
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Credenciais incorretas
            SV-->>CL: 401 usuário ou senha incorretos
        else Credenciais corretas
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Requisições seguintes ===
    CL->>SV: GET /admin/v1/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. Modelo de permissões RBAC

```mermaid
flowchart LR
    subgraph "Usuário User"
        U1["admin<br/>(superadministrador)"]
        U2["editor<br/>(edição)"]
        U3["viewer<br/>(somente leitura)"]
    end

    subgraph "Função Role"
        R1["super_admin<br/>Identificador de permissão: *"]
        R2["editor<br/>Identificador de permissão: get.*, post.*"]
        R3["viewer<br/>Identificador de permissão: get.*"]
    end

    subgraph "Permissão Permission (árvore)"
        P1["dashboard<br/>type=1 Menu"]
        P2["user<br/>type=1 Menu"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 Botão"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (permissão total)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "Tipos de permissão"
        T1["type=1 Menu<br/>Controla a exibição/ocultação da barra lateral"]
        T2["type=2 Botão<br/>Controla os botões de ação da página"]
        T3["type=3 API<br/>Controla o acesso aos endpoints"]
    end

    subgraph "Formato do identificador de permissão"
        F1["{method}.{path}<br/>Ex.: get.admin/user<br/>Ex.: post.admin/user<br/>Ex.: delete.admin/role"]
    end

    subgraph "Fluxo de decisão"
        J1["Extrai Token → adminId"]
        J2["Consulta as funções do usuário"]
        J3["Coleta todos os slug de permissão"]
        J4["Monta method.path"]
        J5{"Corresponde?"}
        J6["Liberado"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"Sim / slug=*"| J6
        J5 -->|Não| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Ciclo de vida completo do ID

```mermaid
flowchart LR
    subgraph "1. Geração"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>Ex.: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Armazenamento"
        S1["MySQL tabela game_*<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Campo sensível<br/>cast encryptable<br/>criptografado em AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Transmissão"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["string hashid<br/>Ex.: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Decodificação reversa"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Camadas de criptografia de dados

```mermaid
flowchart TB
    subgraph "Criptografia na camada de transporte (encryption)"
        E1["Cliente envia dados sensíveis"]
        E2["Criptografia AES-256-CBC"]
        E3["API transmite o texto cifrado"]
        E4["Servidor descriptografa e processa"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Criptografia na camada de armazenamento (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Gravação: criptografia automática"]
        D3["MySQL VARCHAR(500)<br/>armazena o texto cifrado"]
        D4["Leitura: descriptografia automática"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Mascaramento na camada de exibição (mask)"
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

## 8. Relações ER do banco de dados

```mermaid
erDiagram
    game_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "Criptografado"
        VARCHAR phone "Criptografado"
        VARCHAR id_card "Criptografado"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Exclusão lógica"
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
        BIGINT parent_id FK "Autorreferência"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1Menu2Botão3API"
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
        VARCHAR source "Lado de origem"
        TEXT input "Mascarado"
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

## 9. Fluxo de negócio de exportação

```mermaid
sequenceDiagram
    participant C as Cliente
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Sistema de arquivos

    Note over C,FS: === Exportação Excel ===
    C->>CTL: POST /admin/v1/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Dados
    CTL->>CTL: Descriptografa campos sensíveis
    CTL->>CTL: Mascaramento (maskPhone/maskEmail)
    CTL->>CTL: Montagem com PhpSpreadsheet<br/>Cabeçalho azul com texto branco<br/>Bordas finas nas linhas de dados<br/>Congela a primeira linha<br/>Filtro automático
    CTL->>FS: Grava em runtime/tmp/export_*.xlsx
    CTL-->>C: Download do arquivo

    Note over C,FS: === Exportação PDF ===
    C->>CTL: POST /admin/v1/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>Cabeçalho: título + copyright + hora<br/>Conteúdo: tabela ou cartões<br/>Rodapé: copyright não removível
    CTL->>CTL: Renderização com Dompdf A4 paisagem
    CTL->>FS: Grava em runtime/tmp/export_*.pdf
    CTL-->>C: Download do arquivo
```

---

## 10. Árvore de componentes Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Formulário de login<br/>Usuário/senha/captcha"]
    LF --> CAPTCHA["Componente de captcha por clique<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Marca de clique Circle"]

    DB --> SIDEBAR["Barra lateral NavigationDrawer<br/>Recolhível 64px / 240px<br/>Painel/Usuário/Função/Configuração/Log/Pagamento"]
    DB --> HEADER["Barra superior 56px<br/>Botão de recolher + menu do usuário<br/>Sair AlertDialog"]
    DB --> CONTENT["Área de conteúdo"]
    CONTENT --> DASH["DashboardPage<br/>Cartões de estatística GridView<br/>Gráfico de linha de tendência LineChart<br/>Gráfico de pizza de distribuição PieChart<br/>Operações recentes ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. Rotas de páginas HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>Inicialização"]
    EA -->|"Sem Token"| LP["LoginPage<br/>Página de login"]
    EA -->|"Com Token"| DP["DashboardPage<br/>Painel"]

    LP -->|"Login bem-sucedido<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>Lista de usuários"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Centro pessoal"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>Detalhe/criação/edição de usuário"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"Sair<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Panorama da defesa em profundidade

```mermaid
flowchart TB
    subgraph "Camada 1: verificação humana"
        L1["Captcha por clique<br/>Click Captcha<br/>Obrigatório no login/registro"]
    end

    subgraph "Camada 2: confirmação da operação"
        L2["Confirmação por senha<br/>confirmPassword()<br/>Obrigatória em DELETE"]
    end

    subgraph "Camada 3: segurança de transporte"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Camada 4: autenticação"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "Camada 5: autorização"
        L5["RBAC<br/>granularidade method.path<br/>superadministrador * "]
    end

    subgraph "Camada 6: proteção de dados"
        L6["ID da API: criptografia Hashids<br/>Corpo da requisição: criptografia Encryption<br/>Camada de armazenamento: criptografia Encryptable<br/>Exportação: mascaramento + copyright"]
    end

    subgraph "Camada 7: auditoria e rastreabilidade"
        L7["OperationLog<br/>Registra todas as operações<br/>Usuário/IP/hora/lado de origem/parâmetros"]
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

## 13. Topologia de deploy

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Servidor Web"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["Arquivos estáticos<br/>Flutter Web build/"]
    end

    subgraph "Servidores de aplicação (escalonamento horizontal)"
        WM1["webman worker 1<br/>:8789"]
        WM2["webman worker 2<br/>:8789"]
        WM3["webman worker N<br/>:8789"]
    end

    subgraph "Camada de dados"
        MYSQL["MySQL 8.0<br/>Replicação primário-réplica<br/>Prefixo game_"]
        ES["Elasticsearch 8.x<br/>Cluster de 3 nós<br/>Prefixo game_"]
        REDIS["Redis 7.x<br/>Modo sentinela<br/>poster:captcha:*"]
    end

    subgraph "Monitoramento"
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
