# Diagrammes d'architecture et de logique métier
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE.md) · [English](ARCHITECTURE.en.md) · [한국어](ARCHITECTURE.ko.md) · [Русский](ARCHITECTURE.ru.md) · [Deutsch](ARCHITECTURE.de.md) · **Français** · [Español](ARCHITECTURE.es.md) · [Português](ARCHITECTURE.pt.md) · [हिन्दी](ARCHITECTURE.hi.md) · [العربية](ARCHITECTURE.ar.md) · [বাংলা](ARCHITECTURE.bn.md) · [Bahasa Indonesia](ARCHITECTURE.id.md) · [日本語](ARCHITECTURE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Les diagrammes Mermaid ci-dessous se rendent automatiquement dans GitHub / GitLab / VS Code. Pour d'autres environnements, utilisez le [Mermaid Live Editor](https://mermaid.live/).

---

## 1. Topologie du système

```mermaid
flowchart TB
    subgraph "Couche client"
        A1["Flutter Web<br/>Administration PC<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>Client mobile/tablette"]
    end

    subgraph "Couche passerelle/périphérie (Nginx Edge)"
        B1["Nœud Nginx Edge<br/>Docker nginx:alpine<br/>Reverse proxy + HTTPS + Gzip<br/>Service de fichiers statiques"]
    end

    subgraph "Couche application (webman v2)"
        C0["Middleware ApiVersion<br/>Validation de l'en-tête API-Version"]
        C1["Middleware AdminAuth<br/>Validation JWT"]
        C2["Middleware AdminPermission<br/>Vérification des permissions RBAC"]
        C3["Contrôleur administration<br/>Dashboard / User / Role / Permission"]
        C4["Contrôleurs publics v1<br/>Captcha / Auth"]
        C5["Services communs<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Couche stockage"
        D1[("MySQL 8.0<br/>Stockage principal<br/>Préfixe de tables game_")]
        D2[("Elasticsearch<br/>Recherche plein texte<br/>Préfixe d'index game_")]
        D3[("Redis<br/>Session / Cache<br/>Stockage Captcha")]
    end

    subgraph "Externe"
        E1["DevEco Studio<br/>Build HarmonyOS"]
        E2["Flutter SDK<br/>Build Web"]
    end

    A1 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    A2 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    B1 --> C0
    C0 --> C1
    C1 --> C2
    C2 --> C3
    C0 --> C4
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
    style C0 fill:#EB2F96,color:#fff
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

## 2. Architecture back-end en couches

```mermaid
flowchart TD
    subgraph "Couche routage Route Layer"
        R1["config/route.php<br/>Mapping URL → Controller"]
    end

    subgraph "Couche middleware Middleware Layer"
        M_RL["RateLimit<br/>Limitation à fenêtre glissante Redis<br/>En-têtes de réponse X-RateLimit"]
        M_SF["SecurityFilter<br/>Interception des attaques<br/>XSS/Injection SQL/Chemin d'accès/CSRF"]
        M0["ApiVersion<br/>Validation de version d'API<br/>Injection de apiVersion"]
        M1["AdminAuth<br/>Validation du jeton JWT<br/>Injection de adminId"]
        M2["AdminPermission<br/>Autorisation RBAC<br/>Correspondance method.path<br/>Cache des permissions Redis 60s"]
    end

    subgraph "Couche contrôleur Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + recherche + pagination"]
        CT3["RoleController<br/>CRUD + synchronisation des permissions"]
        CT4["PermissionController<br/>CRUD + construction d'arbre"]
        CT5["DashboardController<br/>Statistiques/tendances/répartition"]
        CT6["ExportController<br/>Export Excel/PDF"]
        CT7["CaptchaController<br/>Génération/vérification de captcha"]
        CT8["AuthController<br/>Connexion/inscription/rafraîchissement"]
        CT9["AnalyticsController<br/>12 points d'extrémité d'analyse<br/>Aperçu/classement/probabilités/rétention/entonnoir/ARPU"]
    end

    subgraph "Couche service Service Layer"
        S1["HashidsService<br/>Encodage/décodage d'ID"]
        S2["SnowflakeService<br/>Génération d'ID uniques globaux"]
        S3["EncryptionService<br/>Chiffrement/déchiffrement + masquage"]
        S4["GameDashboardService<br/>Aperçu/classement/DAU/heures/répartition des comportements<br/>Agrégation MySQL en temps réel, retourne des données vides en cas de panne DB"]
        S5["DepositLogService<br/>Aperçu des revenus/taux de conversion des jeux<br/>Statistiques des commandes confirmées"]
        S6["ProbabilityService<br/>Probabilités conjointes/conditionnelles<br/>Constructeur SQL (échappement/guillemets/IN)"]
    end

    subgraph "Couche modèle Model Layer"
        MD1["AdminUser<br/>casts encryptable"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Couche pilote Driver Layer"
        D1["MySQL PDO"]
        D2["Elasticsearch HTTP"]
        D3["Redis"]
    end

    R1 --> M_SF --> M_RL --> M0
    M0 --> M1
    M1 --> M2
    M2 --> CT2 & CT3 & CT4 & CT5 & CT6 & CT9
    M0 --> CT7 & CT8
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
    style M0 fill:#EB2F96,color:#fff
    style M1 fill:#FA8C16,color:#fff
    style M2 fill:#FA8C16,color:#fff
    style CT1 fill:#1677FF,color:#fff
    style CT9 fill:#1677FF,color:#fff
    style S4 fill:#13C2C2,color:#fff
    style S5 fill:#13C2C2,color:#fff
    style S6 fill:#13C2C2,color:#fff
```

---

## 3. Cycle de vie d'une requête

```mermaid
sequenceDiagram
    participant C as Client
    participant N as Nginx
    participant MW_SF as SecurityFilter
    participant MW_RL as RateLimit
    participant MW0 as ApiVersion
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL
    participant OPLOG as OperationLog

    C->>N: Requête HTTPS<br/>Header: API-Version: v1
    N->>MW_SF: Transmission

    alt Méthode HTTP non standard (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Méthode légitime (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Contrôle de la liste blanche des méthodes réussi
    end

    alt Détection d'attaque déclenchée
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Passage

    alt Limitation déclenchée
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: Passage

    alt Version non prise en charge
        MW0-->>C: 400 Version d'API non prise en charge
    else Version valide
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Jeton manquant ou invalide
        MW1-->>C: 401 Unauthorized
    else Jeton valide
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt Sans permission
        MW2-->>C: 403 Forbidden
    else Avec permission
        MW2->>CTL: Entrée dans le contrôleur
    end

    CTL->>CTL: Validation des paramètres (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Opération sensible (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Mot de passe erroné
            CTL-->>C: 422 Échec de la vérification du mot de passe
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: Déchiffrement automatique via le cast encryptable
    MDL->>DB: SELECT
    DB-->>MDL: Ligne
    MDL-->>CTL: Modèle

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: chaîne hash

    CTL->>CTL: Construction de la réponse JSON
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Journal d'opérations (POST/PUT/DELETE)
```

---

## 4. Flux d'authentification et de captcha

```mermaid
sequenceDiagram
    participant U as Utilisateur
    participant CL as Client
    participant SV as Serveur
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Étape 1: obtention du captcha ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Génération d'une image de fond 300×200
    CAP->>CAP: Placement aléatoire de N cibles chinoises
    CAP->>CAP: Génération de la clé, stockage des targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Étape 2: clic de l'utilisateur ===
    CL->>CL: Rendu de l'image de captcha
    CL->>CL: Invite « Cliquez dans l'ordre : arbre → oiseau → fleur »
    U->>CL: Clics successifs sur les positions des caractères de l'image
    CL->>CL: Collecte des clics: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Étape 3: connexion ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Captcha erroné
        CAP-->>SV: false
        SV-->>CL: 422 Captcha erroné
    else Captcha correct
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Identifiants erronés
            SV-->>CL: 401 Nom d'utilisateur ou mot de passe incorrect
        else Identifiants corrects
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14j)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Requêtes suivantes ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { données du tableau de bord }
```

---

## 5. Modèle de permissions RBAC

```mermaid
flowchart LR
    subgraph "Utilisateurs User"
        U1["admin<br/>(super administrateur)"]
        U2["editor<br/>(éditeur)"]
        U3["viewer<br/>(lecture seule)"]
    end

    subgraph "Rôles Role"
        R1["super_admin<br/>Identifiant de permission: *"]
        R2["editor<br/>Identifiant de permission: get.*, post.*"]
        R3["viewer<br/>Identifiant de permission: get.*"]
    end

    subgraph "Permissions Permission (arbre)"
        P1["dashboard<br/>type=1 menu"]
        P2["user<br/>type=1 menu"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 bouton"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (toutes les permissions)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "Types de permission"
        T1["type=1 menu<br/>Contrôle l'affichage/masquage de la barre latérale"]
        T2["type=2 bouton<br/>Contrôle les boutons d'action des pages"]
        T3["type=3 API<br/>Contrôle l'accès aux interfaces"]
    end

    subgraph "Format des identifiants de permission"
        F1["{method}.{path}<br/>ex: get.admin/user<br/>ex: post.admin/user<br/>ex: delete.admin/role"]
    end

    subgraph "Flux de décision"
        J1["Extraction du jeton → adminId"]
        J2["Recherche des rôles de l'utilisateur"]
        J3["Collecte de tous les slugs de permissions"]
        J4["Construction de method.path"]
        J5{"Correspondance?"}
        J6["Autoriser"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"Oui / slug=*"| J6
        J5 -->|"Non"| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Cycle de vie complet des ID

```mermaid
flowchart LR
    subgraph "1. Génération"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>ex: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Stockage"
        S1["Tables MySQL game_*<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Champs sensibles<br/>cast encryptable<br/>Chiffrement AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Transmission"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["Chaîne hashid<br/>ex: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Décodage inverse"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Chiffrement en couches des données

```mermaid
flowchart TB
    subgraph "Chiffrement de la couche de transmission (encryption)"
        E1["Le client envoie des données sensibles"]
        E2["Chiffrement AES-256-CBC"]
        E3["Transmission du texte chiffré via l'API"]
        E4["Déchiffrement et traitement côté serveur"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Chiffrement de la couche de stockage (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Écriture: chiffrement automatique"]
        D3["MySQL VARCHAR(500)<br/>Stockage du texte chiffré"]
        D4["Lecture: déchiffrement automatique"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Masquage de la couche d'affichage (mask)"
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

## 8. Relations ER de la base de données

```mermaid
erDiagram
    game_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "Chiffré"
        VARCHAR phone "Chiffré"
        VARCHAR id_card "Chiffré"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Suppression douce"
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
        BIGINT parent_id FK "Auto-référence"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1menu2bouton3API"
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
        VARCHAR source "Côté d'origine"
        TEXT input "Masqué"
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

## 9. Flux métier d'export

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Système de fichiers

    Note over C,FS: === Export Excel ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Données
    CTL->>CTL: Déchiffrement des champs sensibles
    CTL->>CTL: Traitement de masquage (maskPhone/maskEmail)
    CTL->>CTL: Construction PhpSpreadsheet<br/>En-tête bleu avec texte blanc<br/>Lignes de données à bordures fines<br/>Ligne d'en-tête figée<br/>Filtre automatique
    CTL->>FS: Écriture de runtime/tmp/export_*.xlsx
    CTL-->>C: Téléchargement du fichier

    Note over C,FS: === Export PDF ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>En-tête: titre + copyright + heure<br/>Contenu: tableau ou cartes<br/>Pied de page: copyright inamovible
    CTL->>CTL: Rendu Dompdf A4 paysage
    CTL->>FS: Écriture de runtime/tmp/export_*.pdf
    CTL-->>C: Téléchargement du fichier
```

---

## 10. Arbre de composants Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Formulaire de connexion<br/>Nom d'utilisateur/mot de passe/captcha"]
    LF --> CAPTCHA["Composant de captcha à clic<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Marquage des clics Circle"]

    DB --> SIDEBAR["Barre latérale NavigationDrawer<br/>Repliable 64px / 240px<br/>Tableau de bord/utilisateurs/rôles/config/journaux"]
    DB --> HEADER["Barre supérieure 56px<br/>Bouton de repli + menu utilisateur<br/>Dialog de déconnexion AlertDialog"]
    DB --> CONTENT["Zone de contenu"]
    CONTENT --> DASH["DashboardPage<br/>Cartes de statistiques GridView<br/>Courbe de tendance LineChart<br/>Camembert de répartition PieChart<br/>Opérations récentes ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. Routage des pages HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>Démarrage"]
    EA -->|"Sans Token"| LP["LoginPage<br/>Page de connexion"]
    EA -->|"Avec Token"| DP["DashboardPage<br/>Tableau de bord"]

    LP -->|"Connexion réussie<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>Liste des utilisateurs"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Espace personnel"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>Détail/ajout/édition d'utilisateur"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"Déconnexion<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Panorama de la défense en profondeur de la sécurité

```mermaid
flowchart TB
    subgraph "Couche 1: Vérification homme-machine"
        L1["Captcha à clic<br/>Click Captcha<br/>Obligatoire à la connexion/inscription"]
    end

    subgraph "Couche 2: Confirmation d'opération"
        L2["Double confirmation par mot de passe<br/>confirmPassword()<br/>Obligatoire pour les opérations DELETE"]
    end

    subgraph "Couche 3: Sécurité de transmission"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Couche 4: Authentification d'identité"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14j"]
    end

    subgraph "Couche 5: Autorisation des permissions"
        L5["RBAC<br/>Granularité method.path<br/>Super administrateur * "]
    end

    subgraph "Couche 6: Protection des données"
        L6["ID d'interface: chiffrement Hashids<br/>Corps de requête: chiffrement Encryption<br/>Couche de stockage: chiffrement Encryptable<br/>Export: masquage + copyright"]
    end

    subgraph "Couche 7: Traçabilité d'audit"
        L7["OperationLog<br/>Enregistre toutes les opérations<br/>Utilisateur/IP/heure/côté d'origine/paramètres"]
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

## 13. Topologie de déploiement

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Serveur web"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → redirection 443<br/>gzip on"]
        STA["Fichiers statiques<br/>build/ Flutter Web"]
    end

    subgraph "Serveurs d'application (extensibles horizontalement)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "Couche de données"
        MYSQL["MySQL 8.0<br/>Réplication maître-esclave<br/>Préfixe game_"]
        ES["Elasticsearch 8.x<br/>Cluster de 3 nœuds<br/>Préfixe game_"]
        REDIS["Redis 7.x<br/>Mode sentinelle<br/>poster:captcha:*"]
    end

    subgraph "Surveillance"
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
