# Administration ouverte — Document de conception
<!-- lang-nav -->

Languages: [中文](DESIGN.md) · [English](DESIGN.en.md) · [한국어](DESIGN.ko.md) · [Русский](DESIGN.ru.md) · [Deutsch](DESIGN.de.md) · **Français** · [Español](DESIGN.es.md) · [Português](DESIGN.pt.md) · [हिन्दी](DESIGN.hi.md) · [العربية](DESIGN.ar.md) · [বাংলা](DESIGN.bn.md) · [Bahasa Indonesia](DESIGN.id.md) · [日本語](DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Les diagrammes d'architecture Mermaid détaillés sont dans [ARCHITECTURE.fr.md](ARCHITECTURE.fr.md) (rendu automatique GitHub/GitLab/VS Code).

## 1. Architecture du système

> **Liste des fonctionnalités** : authentification (login/register/refresh/logout + verrouillage de compte + limitation de sessions) | tableau de bord (cache Redis) | CRUD utilisateurs + opérations par lots + import | rôles et permissions (RBAC) | configuration système | audit des opérations (8 canaux d'origine) | fichiers (upload + export + masquage) | sécurité (18 couches de défense) | exploitation (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        Couche client                          │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  Administration      │  │  Client (mobile/tablette/2en1)│  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                    Couche passerelle API              │    │
│  │  AdminAuth (authentification) → AdminPermission → Controller │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │       Couche logique métier (Controller/Service)      │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                    Couche Model                        │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (génération    (chiffrement   (chiffrement  │    │    │
│  │  │  des clés)      des champs DB)  du transport API)  │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │               Couche de stockage                      │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (stockage │  │ (recherche   │  │ (cache)  │        │    │
│  │  │  principal)│  │  plein texte)│  │          │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Architecture backend

### 2.1 Conception en couches

| Couche | Répertoire | Responsabilité |
|---|------|------|
| Routes | `config/route.php` | Mappage URL → contrôleur, liaison des middleware, routes versionnées |
| Middleware | `app/middleware/` | Interception des attaques (SecurityFilter), rate-limit (RateLimit), authentification (JWT), autorisation (RBAC), version API (ApiVersion) |
| Contrôleurs | 30 : Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs/Metrics/Analytics/Game/Payment/Withdraw... (administration) + Captcha/Auth (API v1) | Validation des paramètres de requête, appel de la logique métier, formatage des réponses |
| Services métier | `common/service/` | Analyse de données : GameDashboardService (vue d'ensemble/classement/tendances), DepositLogService (revenus/conversion), ProbabilityService (probabilités jointes/conditionnelles, constructeur SQL) ; renvoie des données vides (et non une erreur) si la base est en panne |
| Modèles de données | `app/model/` | Mappage ORM, relations, chiffrement/déchiffrement des champs |
| Utilitaires communs | `app/common/` | Services Hashids, Snowflake, Encryption |

### 2.2 Cycle de vie d'une requête

```
Requête client
  │
  ▼
Serveur HTTP webman (workerman)
  │
  ▼
Correspondance Route
  │
  ▼
Chaîne de middleware:
  SecurityFilter ──────► contrôle des méthodes HTTP → 405 (seuls GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     interception des attaques XSS/injection SQL/traversée de chemin/injection de commandes/CSRF (403)
  ▼
  RateLimit ───────────► rate-limit par fenêtre glissante Redis
  │ (échec → 429 + en-tête Retry-After)
  ▼
  ApiVersion ─────────► validation de l'en-tête API-Version, injection de $request->apiVersion
  │ (échec → 400)
  ▼
  AdminAuth ──────────► validation JWT, injection de $request->adminId
  │ (échec → 401)
  ▼
  AdminPermission ────► vérification des permissions RBAC (cache Redis 60 s)
  │ (échec → 403)
  ▼
  OperationLog ───────► enregistrement du journal d'opérations (POST/PUT/DELETE), détection automatique du canal d'origine
  │
  ▼
Controller::method()
  │
  ├─► validation des paramètres (validator)
  ├─► confirmation des opérations sensibles (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► opérations Model (chiffrement/déchiffrement automatique encryptable)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 Cycle de vie des ID

```
Génération (Snowflake) → stockage (MySQL BIGINT) → transport (encodage Hashids) → externe (chaîne hash)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Système de chiffrement des données

```
Couche de transport (encryption)     — AES-256-CBC, clé indépendante
Couche de stockage (encryptable)     — AES-128-ECB, clé indépendante, traité automatiquement via les $casts du Model
Couche d'affichage (mask)            — téléphone : 138****1234, e-mail : a***@example.com
```

## 3. Conception de la base de données

### 3.1 Relations ER

```
erik_admin_user ──┬── erik_admin_user_role ──┬── erik_admin_role
  (utilisateurs)   │    (association user-role)│     (rôles)
                  │                          │
                  │                    erik_admin_role_permission
                  │                     (association rôle-permission)
                  │                          │
                  │                          ▼
                  │                    erik_admin_permission
                  │                      (permissions/menus)
                  │
                  ▼
           erik_operation_log
             (journaux d'opérations)

erik_system_config (configuration système) — table indépendante
```

### 3.2 Structures de tables centrales

| Table | Nombre de champs | Description |
|------|-------|------|
| `erik_admin_user` | 14 | Utilisateurs administrateurs, phone/email/id_card stockés chiffrés, suppression douce prise en charge |
| `erik_admin_role` | 7 | Rôles, slug unique |
| `erik_admin_permission` | 10 | Arbre de permissions (auto-référence parent_id), type : 1=menu 2=bouton 3=API |
| `erik_admin_user_role` | 2 | Table intermédiaire many-to-many user-rôle |
| `erik_admin_role_permission` | 2 | Table intermédiaire many-to-many rôle-permission |
| `erik_system_config` | 8 | Configuration clé-valeur, unicité group+key |
| `erik_operation_log` | 9 | Journaux d'audit des opérations (avec source du canal) |

### 3.3 Normes des clés primaires

- Type : `BIGINT UNSIGNED NOT NULL`
- Caractéristique : **non auto-incrémentée**, générée au niveau application par l'algorithme Snowflake
- Avantages : unicité globale, compatible distribué, croissance ordonnée favorable aux index, ne divulgue pas le volume métier
- Configuration : datacenter_id (0-31) + worker_id (0-31), prend en charge 1024 nœuds en concurrence

## 4. Conception de l'API

### 4.1 Normes URL

```
Interfaces publiques :  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

Administration :   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

Routes de ressources :
  GET    /admin/user          → liste
  POST   /admin/user          → création
  GET    /admin/user/{hashid} → détails
  PUT    /admin/user/{hashid} → mise à jour
  DELETE /admin/user/{hashid} → suppression (confirmation par mot de passe requise)

Configuration système :  /admin/config[/{hashid}]
Journaux d'opérations :  /admin/log
Espace personnel :       /admin/profile[/password|/logout]
Import :     /admin/import/users
Upload :     /admin/upload
Par lots :   /admin/user/batch/{destroy|status}
Documentation :     /api/docs     (OpenAPI 3.0)
Health :     /health
```

### 4.2 Stratégie de versions API

La version API est contrôlée par l'en-tête de requête, **pas présente dans le chemin URL** :

```http
API-Version: v1
```

| Mécanisme | Description |
|------|------|
| Version par défaut | `v1` si l'en-tête `API-Version` est absent |
| Validation | le middleware `ApiVersion` valide, version non prise en charge → 400 |
| Routage | la fonction d'aide `v()` résout dynamiquement la classe de contrôleur selon la version |
| Répertoires | contrôleurs organisés par version : `app/api/{version}/controller/` |

Exemple d'extension — ajouter une API v2 :
1. Créer `app/api/v2/controller/AuthController.php`
2. Ajouter `'v2'` à la constante `SUPPORTED` du middleware `ApiVersion`
3. Les définitions de routes n'ont pas besoin d'être modifiées

```bash
# Utiliser v1
curl -H "API-Version: v1" /api/auth/login

# Utiliser v2
curl -H "API-Version: v2" /api/auth/login

# Sans en-tête, v1 par défaut
curl /api/auth/login
```

### 4.3 Stratégie de rate-limit

Algorithme de fenêtre glissante Redis Sorted Set, exécution atomique via script Lua :

| Interface | Limite |
|------|------|
| Par défaut | 60 requêtes/minute/IP/route |
| POST /api/auth/login | 10 requêtes/minute |
| POST /api/auth/register | 5 requêtes/minute |

En cas de dépassement, renvoie 429, les en-têtes contiennent X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 Réponse unifiée

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Signification | Scénario de déclenchement |
|------|------|---------|
| 0 | Succès | Réponse normale |
| 400 | Erreur de paramètres | Format de requête incorrect |
| 401 | Non authentifié | Token manquant/expiré/invalide |
| 403 | Sans permission | Le rôle de l'utilisateur ne contient pas la permission requise |
| 404 | Introuvable | Ressource non trouvée |
| 422 | Échec de validation | Paramètres du formulaire non conformes / échec de confirmation du mot de passe |
| 500 | Erreur serveur | Exception inattendue |

### 4.5 Flux d'authentification (avec captcha à clic)

```
Client                               Serveur
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② l'utilisateur clique les positions des textes de l'image │
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

### 4.6 Modèle de permissions (RBAC)

```
  Utilisateur ──┬── Rôle ──┬── Permission
  User            Role       Permission
                 │
                 ├── type=1: menu (contrôle la visibilité de la barre latérale)
                 ├── type=2: bouton (contrôle les opérations dans la page)
                 └── type=3: API  (contrôle l'accès aux interfaces)

  Format de l'identifiant de permission : {method}.{path}
  Ex. : get.admin/user  post.admin/user  delete.admin/user
  Identifiant super administrateur : * (contourne toutes les vérifications de permissions)
```

### 4.7 Confirmation secondaire des opérations sensibles

Les opérations sensibles (suppression d'utilisateurs, de rôles, de permissions, etc.) exigent de transmettre le mot de passe courant de l'utilisateur dans le corps de la requête pour revérifier l'identité :

```
Client                            Serveur
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → mot de passe erroné : 422
  │                                │ → mot de passe correct : exécution
  │◄── 200 { code: 0 }           │
```

Le frontend affiche une boîte de dialogue de confirmation avant l'opération de suppression, collecte le mot de passe de l'utilisateur puis envoie la requête.

## 5. Conception du frontend

### 5.1 Administration Flutter Web

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ bouton menu        🔔 messages  👤 admin  ▼ │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Zone de contenu                    │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 tableau│  │ cartes stat.×4│ │ graphique │     │
│ 👥 users │  └──────────────┘ └──────────┘     │
│ 🔒 rôles │  ┌──────┐ ┌────────────────┐       │
│ ⚙ config │  │camem-│ │ journaux récents│       │
│ 📋 logs  │  │bord  │ │ d'opérations    │       │
└──────────┴─────────────────────────────────────┘
```

Fonctionnalités : barre latérale repliable, double thème Material 3, tableaux de données à haute densité, boîtes de dialogue, interactions au survol de la souris

### 5.2 Mobile HarmonyOS

Routage des pages :

| Page | Route | Description |
|------|------|------|
| LoginPage | `pages/LoginPage` | Connexion nom d'utilisateur/mot de passe + captcha à clic |
| DashboardPage | `pages/DashboardPage` | Cartes statistiques + opérations récentes |
| UserListPage | `pages/UserListPage` | Liste des utilisateurs, recherche + pull-to-refresh + chargement au scroll |
| UserDetailPage | `pages/UserDetailPage` | Création/édition/consultation/suppression (confirmation AlertDialog) |
| ProfilePage | `pages/ProfilePage` | Espace personnel, déconnexion (confirmation AlertDialog) |

Flux de données : Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Conception de la sécurité

### 6.1 Défense en profondeur

| Couche | Mesure |
|------|------|
| Limitation des méthodes | liste blanche des méthodes HTTP SecurityFilter, seuls GET/POST/PUT/DELETE/OPTIONS/HEAD, méthodes non standard → 405 |
| Interception des attaques | middleware SecurityFilter, détection et interception XSS/injection SQL/traversée de chemin/injection de commandes/CSRF |
| Vérification homme-machine | captcha à clic (Click Captcha), validé obligatoirement à la connexion/inscription |
| Verrouillage de compte | 5 échecs de connexion consécutifs → verrouillage 15 minutes, 429 pendant le verrouillage |
| Limitation de sessions | 3 tokens concurrents maximum par utilisateur, le plus ancien est automatiquement mis en liste noire au-delà |
| Rate-limit | middleware RateLimit, fenêtre glissante Redis, atomisation Lua |
| CSP | en-tête Content-Security-Policy limitant les sources de ressources, anti-XSS et injection de données |
| Confirmation d'opérations | les opérations sensibles (suppression, etc.) exigent la saisie du mot de passe courant de l'utilisateur pour une seconde confirmation |
| Transport | HTTPS + jeton Bearer JWT |
| ID d'interface | chiffrement Hashids, ID réel non rétro-déductible de l'extérieur |
| Corps de requête | chiffrement AES-256-CBC des champs sensibles |
| Base de données | clés primaires BIGINT (aucune auto-incrémentation exposée) |
| Base de données | chiffrement AES-128-ECB des champs sensibles au stockage |
| Authentification | JWT HS256, expiration 2 h + refresh token |
| Autorisation | RBAC, contrôle des permissions à la granularité method.path |
| Audit | OperationLog enregistre toutes les opérations (détection automatique du canal d'origine source incluse) |

### 6.2 Gestion des clés

```
JWT_SECRET          → injecté par variable d'environnement, chaîne aléatoire de 64 caractères
HASHIDS_SALT        → sel unique, à changer globalement en cas de fuite
ENCRYPTION_KEY      → clé de chiffrement du transport API, 32 octets
ENCRYPTABLE_KEY     → clé de chiffrement du stockage DB, indépendante de la clé de transport
SCOUT_HOSTS         → adresse ES, déploiement en réseau interne
```

### 6.3 Protection des données sensibles

| Scénario | Champ | Mesure |
|------|------|------|
| Affichage en liste | phone | Masquage : 138****1234 |
| Affichage en liste | email | Masquage : a***@example.com |
| Consultation des détails | phone/email | Interface de déchiffrement requise |
| Export Excel | phone/email | Export après masquage |
| Export PDF | tous les champs | Masquage + filigrane de copyright inamovible |
| Stockage | phone/email/id_card | Chiffrement en texte chiffré via encryptable |

## 7. Conception des exports

### 7.1 Export Excel

```
Requête : POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() interroge les données (limit 10000)
  → masquage des champs sensibles
  → construction PhpSpreadsheet (en-tête blanc sur fond bleu + première ligne figée + filtre automatique)
  → écriture dans runtime/tmp/ → réponse download
```

### 7.2 Export PDF

```
Requête : POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + CSS inline + copyright en en-tête de page + copyright inamovible en pied de page
  → rendu Dompdf A4 paysage
  → écriture dans runtime/tmp/ → réponse download
```

## 8. Architecture de déploiement

### 8.1 Topologie recommandée

```
Nginx (:443 HTTPS) → workers webman × N (:8787) → MySQL + ES + Redis
                    fichiers statiques : build Flutter Web/
```

### 8.2 Docker Compose (recommandé en production)

Le `docker-compose.yml` à la racine du projet orchestre tous les services de la topologie ci-dessus :

| Service | Image/construction | Port | Description |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Reverse proxy + fichiers statiques + Gzip |
| `app` | construit via le `Dockerfile` local | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Base principale, volume de données persistant |
| `redis` | redis:7-alpine | 6379 | Cache / rate-limit / captcha |
| `elasticsearch` | elasticsearch:8.x | 9200 | Recherche plein texte |

Avant le démarrage, remplacer les clés du `docker-compose.yml` (`JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY`, etc.) par des chaînes aléatoires.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

L'intégration continue GitHub Actions est définie dans `.github/workflows/ci.yml` :
- Vérification de la syntaxe PHP (`php -l`)
- Tests unitaires PHPUnit
- Analyse statique Flutter (`flutter analyze`)

### 8.4 Sauvegarde de la base

`database/backup/backup.sh` — sauvegarde mysqldump + gzip, nettoyage automatique des sauvegardes de plus de 30 jours.
`database/backup/restore.sh` — sélection interactive et restauration des sauvegardes.

### 8.5 Monitoring

Le point `GET /metrics` (`MetricsController`) expose 5 métriques gauge au format texte Prometheus : nombre total de requêtes HTTP, nombre d'utilisateurs actifs, état des connexions base de données/Redis, utilisation mémoire.

### 8.6 Prérequis d'environnement

| Composant | Version minimale | Configuration recommandée |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ OPcache activé |
| MySQL | 8.0+ | 8.0+ réplication maître-esclave |
| Elasticsearch | 7.x | 8.x cluster de 3 nœuds |
| Redis | 6.x | 7.x mode sentinel |
| Nginx | 1.20+ | Reverse proxy + gzip + SSL |
| Flutter SDK | 3.41+ | Dernière version stable |
| HarmonyOS | API 12 | DevEco Studio 5.x |
