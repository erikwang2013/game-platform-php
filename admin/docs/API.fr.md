# Documentation de référence API
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · **Français** · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Aperçu

La plateforme d'administration ouverte (open-admin) est construite sur webman v2 et fournit une API JSON RESTful. Toutes les interfaces d'administration nécessitent une authentification JWT et une vérification des permissions RBAC ; les interfaces publiques sont routées vers les contrôleurs versionnés via l'en-tête de version d'API.

- **URL de base** : `http://localhost:8787`
- **Version de l'API** : contrôlée via l'en-tête de requête `API-Version: v1` (v1 par défaut si absent)

> **Aperçu des points d'extrémité** : authentification(5) | tableau de bord(1) | utilisateurs(7) | rôles(4) | permissions(4) | configuration(4) | journaux(1) | espace personnel(3) | import/export(3) | upload(1) | exploitation(4 : health/metrics/docs/security.txt) | soit 37 points d'extrémité au total
- **Authentification** : `Authorization: Bearer <token>` (JWT)
- **Format de réponse** : `{ "code": 0, "message": "success", "data": {...} }`
- **Point d'extrémité documentation** : `GET /api/docs` renvoie la spécification JSON OpenAPI 3.0

### Exigences des requêtes

- Seules les méthodes `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` sont autorisées ; l'utilisation d'autres méthodes HTTP (telles que TRACE, CONNECT, PATCH) renvoie 405
- Toutes les requêtes `POST` / `PUT` doivent définir `Content-Type: application/json` (sauf upload de fichiers), sinon 415 est renvoyé
- La taille du corps de requête ne doit pas dépasser 10 Mo, sinon 413 est renvoyé
- Le filtre de sécurité analyse toutes les entrées de requête contre XSS, l'injection SQL, le parcours de chemin et l'injection de commandes ; en cas de correspondance, 403 est renvoyé
- 5 échecs de connexion consécutifs déclenchent le verrouillage du compte (15 minutes) ; pendant le verrouillage, les requêtes de connexion renvoient 429
- Un même utilisateur peut détenir au maximum 3 jetons valides simultanément ; au-delà, le jeton le plus ancien est automatiquement ajouté à la liste noire

## 2. Codes d'erreur

| code | Signification | Scénario de déclenchement |
|------|------|---------|
| 0 | Succès | |
| 400 | Erreur de paramètres de requête | Format de requête incorrect |
| 401 | Non authentifié | Jeton manquant / expiré / déjà sur liste noire |
| 403 | Sans permission / interception de sécurité | Permissions RBAC insuffisantes / correspondance SecurityFilter |
| 404 | Ressource inexistante | La cible de consultation/mise à jour/suppression n'existe pas |
| 405 | Méthode de requête non autorisée | Seuls GET/POST/PUT/DELETE/OPTIONS/HEAD sont autorisés ; les méthodes non standard sont directement refusées |
| 413 | Corps de requête trop volumineux | Content-Length dépasse 10 Mo |
| 415 | Type de média non pris en charge | Le Content-Type d'une requête POST/PUT n'est ni JSON ni un upload de fichier |
| 422 | Échec de la validation des paramètres | Champ obligatoire manquant, format incorrect, validation métier non conforme |
| 429 | Requêtes trop fréquentes | RateLimit déclenché / verrouillage de compte (5 échecs de connexion consécutifs = verrouillage 15 min) |
| 500 | Erreur interne du serveur | |

## 3. Points d'extrémité publics

Tous les points d'extrémité publics sont montés sous le groupe `/api` et distribués par le middleware `ApiVersion` vers le contrôleur versionné correspondant selon l'en-tête `API-Version` (par ex. `app\api\v1\controller\AuthController`).

### 3.1 Health check

```
GET /health
```

- **Authentification** : aucune
- **Limitation** : aucune

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.1",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

Valeurs de `database`, `redis`, `elasticsearch` : `"ok"` | `"unavailable"`. `elasticsearch` renvoie `"unavailable"` lorsque ES est inaccessible ; si l'état de santé du cluster n'est ni green ni yellow, la valeur de status réelle est renvoyée (par ex. `"red"`).

### 3.2 Documentation API

```
GET /api/docs
```

- **Authentification** : aucune
- **Limitation** : défaut global (60 requêtes/minute)
- **Réponse** : spécification JSON OpenAPI 3.0.3, incluant les définitions de tous les points d'extrémité, paramètres et schémas

### 3.3 Génération du captcha à clic

```
POST /api/captcha/generate
```

- **Authentification** : aucune
- **En-tête de requête** : `API-Version: v1` (obligatoire)
- **Limitation** : défaut global (60 requêtes/minute)

**Corps de requête** :
```json
{
  "difficulty": "medium"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| difficulty | string | Non | `easy` / `medium` / `hard`, défaut `medium` |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| Champ | Type | Description |
|------|------|------|
| key | string | Identifiant du captcha, à renvoyer lors de la vérification |
| image | string | Image PNG encodée en base64 |
| extra.targets[].order | int | Ordre des clics |
| extra.targets[].text | string | Texte d'indication de la cible à cliquer |

### 3.4 Vérification du captcha à clic

```
POST /api/captcha/verify
```

- **Authentification** : aucune
- **En-tête de requête** : `API-Version: v1` (obligatoire)
- **Limitation** : défaut global (60 requêtes/minute)

**Corps de requête** :
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| key | string | Oui | Clé du captcha, renvoyée par generate |
| clicks | array{object} | Oui | Tableau de coordonnées de clics, chaque élément contient `x` (int) et `y` (int) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

En cas d'échec de vérification, `code` vaut 422, `message` vaut `"验证失败，请重试"` et `data.valid` vaut `false`.

### 3.5 Connexion

```
POST /api/auth/login
```

- **Authentification** : aucune
- **En-tête de requête** : `API-Version: v1` (obligatoire)
- **Limitation** : 10 requêtes/minute (par IP + chemin)

**Corps de requête** :
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| username | string | Oui | min:3, max:50 | Nom d'utilisateur |
| password | string | Oui | min:6, max:32 | Mot de passe |
| captcha_key | string | Oui | | Clé du captcha |
| clicks | array{object} | Oui | min:2 | Tableau de coordonnées de clics |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| Champ | Type | Description |
|------|------|------|
| access_token | string | Jeton d'accès JWT |
| refresh_token | string | Jeton de rafraîchissement JWT |
| expires_in | int | Durée de validité du jeton d'accès (secondes), défaut 7200 |
| user.id | string | ID utilisateur chiffré en hashid |
| user.username | string | Nom d'utilisateur |
| user.real_name | string | Nom réel |

**Erreurs possibles** :
- 422 : échec de la validation des paramètres (champ obligatoire manquant, format incorrect)
- 422 : captcha erroné, veuillez réessayer
- 401 : nom d'utilisateur ou mot de passe incorrect
- 403 : compte désactivé
- 429 : compte verrouillé, veuillez réessayer dans 15 minutes (déclenché par 5 échecs de connexion consécutifs)

### 3.6 Inscription

```
POST /api/auth/register
```

- **Authentification** : aucune
- **En-tête de requête** : `API-Version: v1` (obligatoire)
- **Limitation** : 5 requêtes/minute (par IP + chemin)

**Corps de requête** :
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| username | string | Oui | min:3, max:50 | Nom d'utilisateur (unique) |
| password | string | Oui | min:6, max:32 | Mot de passe (stocké en hash bcrypt) |
| real_name | string | Oui | max:50 | Nom réel |
| captcha_key | string | Oui | | Clé du captcha |
| clicks | array{object} | Oui | min:2 | Tableau de coordonnées de clics |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

Les jetons JWT sont renvoyés directement après une inscription réussie ; le statut utilisateur est activé par défaut (status=1).

### 3.7 Rafraîchissement du jeton

```
POST /api/auth/refresh
```

- **Authentification** : aucune
- **En-tête de requête** : `API-Version: v1` (obligatoire)
- **Limitation** : défaut global (60 requêtes/minute)

**Corps de requête** :
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| refresh_token | string | Oui | refresh_token obtenu à la connexion/inscription |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

Un rafraîchissement réussi renvoie simultanément un nouveau access_token et un nouveau refresh_token ; l'ancien jeton est automatiquement invalidé. Le rafraîchissement met également à jour la dernière heure de connexion et l'IP de l'utilisateur.

**Erreurs possibles** :
- 422 : jeton de rafraîchissement manquant
- 401 : jeton de rafraîchissement invalide ou expiré

### 3.8 Métriques de surveillance Prometheus

```
GET /metrics
```

- **Authentification** : aucune
- **Limitation** : aucune
- **Format de réponse** : format texte Prometheus (`text/plain; version=0.0.4`)

Point d'extrémité de métriques Prometheus public, destiné à être récupéré par Grafana/Prometheus.

**Exemple de réponse** :
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| Nom de métrique | Type | Description |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Nombre total cumulé de requêtes HTTP |
| `openadmin_active_users` | gauge | Nombre d'utilisateurs actifs (connectés dans les 24 heures) |
| `openadmin_db_connection_status` | gauge | État de connexion à la base, 1=normal, 0=anormal |
| `openadmin_redis_connection_status` | gauge | État de connexion Redis, 1=normal, 0=anormal |
| `openadmin_memory_usage_bytes` | gauge | Utilisation mémoire actuelle du processus PHP (bytes) |

## 4. Tableau de bord

Toutes les interfaces d'administration sont montées sous le groupe `/admin` et passent par trois middlewares : `AdminAuth` (authentification JWT), `AdminPermission` (vérification des permissions RBAC), `OperationLog` (journal des opérations).

### 4.1 Données du tableau de bord

```
GET /admin/dashboard
```

- **Authentification** : JWT + RBAC
- **Cache** : Redis 5 minutes

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| Champ stats | Type | Description |
|------|------|------|
| label | string | Nom de l'indicateur |
| value | string | Valeur de l'indicateur (type chaîne) |
| icon | string | Nom de l'icône Material |
| color | string | Couleur de la carte |
| trend | float? | Taux de croissance jour sur jour (pourcentage), uniquement présent pour « 用户总数 » |

| Champ trends | Type | Description |
|------|------|------|
| dates | array{string} | Séquence des 30 derniers jours |
| series | array{object} | Données des lignes de tendance, chaque ligne contient name (nom), data (tableau de valeurs), color (couleur) |

## 5. Gestion des utilisateurs

Tous les `id` renvoyés par les interfaces de gestion des utilisateurs sont des chaînes chiffrées en hashid. Le champ mot de passe est exclu des réponses. Le téléphone et l'email sont affichés masqués dans les interfaces de liste et renvoyés en clair dans les interfaces de détail (les champs chiffrés en base sont automatiquement déchiffrés par le trait Encryptable).

### 5.1 Liste des utilisateurs

```
GET /admin/user
```

- **Authentification** : JWT + RBAC

**Paramètres de requête** :

| Paramètre | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| page | int | Non | 1 | Numéro de page |
| limit | int | Non | 15 | Nombre d'éléments par page |
| keyword | string | Non | | Mot-clé de recherche, correspond au nom d'utilisateur et au nom réel |
| status | int | Non | | Filtre de statut, 0=désactivé, 1=activé |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| Champ | Type | Description |
|------|------|------|
| id | string | ID utilisateur chiffré en hashid |
| username | string | Nom d'utilisateur |
| real_name | string | Nom réel |
| phone | string | Téléphone masqué (format `138****5678`) |
| email | string | Email masqué (format `a***@example.com`) |
| status | int | 1=activé, 0=désactivé |
| last_login_at | string | Dernière connexion (datetime) |
| created_at | string | Date de création (datetime) |

### 5.2 Création d'un utilisateur

```
POST /admin/user
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| username | string | Oui | min:3, max:50 | Nom d'utilisateur (unique) |
| password | string | Oui | min:6, max:32 | Mot de passe (stocké en bcrypt) |
| real_name | string | Oui | max:50 | Nom réel |
| phone | string | Non | | Téléphone (stocké chiffré via Encryptable) |
| email | string | Non | | Email (stocké chiffré via Encryptable) |
| status | int | Non | in:0,1 | Statut, défaut 1 (activé) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**Erreurs possibles** :
- 422 : nom d'utilisateur déjà existant
- 422 : échec de la validation des paramètres (champ obligatoire manquant)

### 5.3 Détail d'un utilisateur

```
GET /admin/user/{id}
```

- **Authentification** : JWT + RBAC
- **Paramètre de chemin** : `{id}` est l'ID utilisateur chiffré en hashid

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

Dans l'interface de détail, `phone` et `email` sont renvoyés en clair (stockage chiffré en base, déchiffrement automatique par le cast Encryptable), sans masquage. `password` et `id_card` ne figurent jamais dans la réponse.

**Erreurs possibles** :
- 404 : utilisateur inexistant

### 5.4 Mise à jour d'un utilisateur

```
PUT /admin/user/{id}
```

- **Authentification** : JWT + RBAC
- **Paramètre de chemin** : `{id}` est l'ID utilisateur chiffré en hashid

**Corps de requête** :
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| real_name | string | Non | Nom réel, conserve la valeur existante si non transmis |
| password | string | Non | Nouveau mot de passe ; chaîne vide ou non transmis = inchangé |
| phone | string | Non | Téléphone |
| email | string | Non | Email |
| status | int | Non | 0=désactivé, 1=activé |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**Erreurs possibles** :
- 404 : utilisateur inexistant

### 5.5 Suppression d'un utilisateur

```
DELETE /admin/user/{id}
```

- **Authentification** : JWT + RBAC
- **Paramètre de chemin** : `{id}` est l'ID utilisateur chiffré en hashid
- **Opération sensible** : confirmation par mot de passe requise

**Corps de requête** :
```json
{
  "password": "admin_password"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| password | string | Oui | Mot de passe de l'utilisateur connecté (double confirmation) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Suppression douce (SoftDeletes Eloquent) : la donnée est marquée `deleted_at` sans suppression physique.

**Erreurs possibles** :
- 404 : utilisateur inexistant
- 422 : les opérations sensibles nécessitent la confirmation par mot de passe (password vide)
- 422 : échec de la vérification du mot de passe (mot de passe non correspondant)

### 5.6 Suppression groupée d'utilisateurs

```
POST /admin/user/batch/destroy
```

- **Authentification** : JWT + RBAC
- **Opération sensible** : confirmation par mot de passe requise

**Corps de requête** :
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| ids | array{string} | Oui | Tableau d'ID utilisateurs chiffrés en hashid |
| password | string | Oui | Mot de passe de l'utilisateur connecté (double confirmation) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Suppression douce ; `data.count` est le nombre réellement supprimé.

**Erreurs possibles** :
- 422 : veuillez sélectionner les utilisateurs à supprimer (ids vide)
- 422 : ID invalide (échec du décodage hashid)
- 422 : échec de la vérification du mot de passe

### 5.7 Activation/désactivation groupée d'utilisateurs

```
POST /admin/user/batch/status
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| ids | array{string} | Oui | Tableau d'ID utilisateurs chiffrés en hashid |
| status | int | Oui | 0=désactivé, 1=activé |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

Le message varie dynamiquement selon la valeur de status : `"批量启用成功"` ou `"批量禁用成功"`.

**Erreurs possibles** :
- 422 : veuillez sélectionner des utilisateurs (ids vide)
- 422 : valeur de statut invalide (status n'est ni 0 ni 1)

## 6. Gestion des rôles

### 6.1 Liste des rôles

```
GET /admin/role
```

- **Authentification** : JWT + RBAC

**Paramètres de requête** :

| Paramètre | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| page | int | Non | 1 | Numéro de page |
| limit | int | Non | 15 | Nombre d'éléments par page |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| Champ | Type | Description |
|------|------|------|
| id | string | ID de rôle chiffré en hashid |
| name | string | Nom du rôle |
| slug | string | Identifiant du rôle (unique, utilisé pour la vérification des permissions) |
| description | string | Description du rôle |
| status | int | 1=activé, 0=désactivé |
| users_count | int | Nombre d'utilisateurs possédant ce rôle |

### 6.2 Création d'un rôle

```
POST /admin/role
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| name | string | Oui | max:50 | Nom du rôle |
| slug | string | Oui | max:50 | Identifiant du rôle |
| description | string | Non | | Description du rôle, défaut chaîne vide |
| status | int | Non | | Statut, défaut 1 |
| permission_ids | array{int} | Non | | Tableau d'ID de permissions (ID INT bruts, non hashid) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 Mise à jour d'un rôle

```
PUT /admin/role/{id}
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| name | string | Non | Nom du rôle |
| description | string | Non | Description |
| status | int | Non | 0=désactivé, 1=activé |
| permission_ids | array{int} | Non | Tableau d'ID de permissions ; s'il est transmis, synchronise (remplace) les permissions du rôle |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 Suppression d'un rôle

```
DELETE /admin/role/{id}
```

- **Authentification** : JWT + RBAC
- **Opération sensible** : confirmation par mot de passe requise

**Corps de requête** :
```json
{
  "password": "admin_password"
}
```

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

La suppression dissocie automatiquement le rôle de toutes ses permissions et de ses utilisateurs, puis supprime physiquement l'enregistrement du rôle.

## 7. Gestion des permissions

Les permissions utilisent une structure arborescente (auto-référence via parent_id) et se divisent en trois types. L'interface de liste renvoie l'arbre de permissions complet.

### 7.1 Arbre de permissions

```
GET /admin/permission
```

- **Authentification** : JWT + RBAC

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| Champ | Type | Description |
|------|------|------|
| id | string | Chiffré en hashid |
| parent_id | string | hashid de la permission parente, « 0 » = nœud racine |
| name | string | Nom de la permission |
| slug | string | Identifiant de la permission (identifiant de route/bouton) |
| type | int | 1=menu, 2=bouton, 3=interface |
| icon | string | Icône de menu (nom d'icône Material) |
| path | string | Chemin de route frontend |
| sort | int | Valeur de tri (croissant) |
| children | array? | Liste des sous-permissions (récursive), absente s'il n'y a pas d'enfants |

### 7.2 Création d'une permission

```
POST /admin/permission
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| parent_id | int | Non | | ID de la permission parente (type INT brut), défaut 0 |
| name | string | Oui | max:50 | Nom de la permission |
| slug | string | Oui | max:100 | Identifiant de la permission |
| type | int | Oui | in:1,2,3 | 1=menu, 2=bouton, 3=interface |
| icon | string | Non | | Icône de menu, défaut vide |
| path | string | Non | | Chemin de route frontend, défaut vide |
| sort | int | Non | | Valeur de tri, défaut 0 |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 Mise à jour d'une permission

```
PUT /admin/permission/{id}
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| name | string | Non | Nom de la permission |
| icon | string | Non | Icône |
| path | string | Non | Chemin de route |
| sort | int | Non | Valeur de tri |

### 7.4 Suppression d'une permission

```
DELETE /admin/permission/{id}
```

- **Authentification** : JWT + RBAC
- **Opération sensible** : confirmation par mot de passe requise

**Corps de requête** :
```json
{
  "password": "admin_password"
}
```

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

La suppression supprime en cascade toutes les sous-permissions (enregistrements avec `parent_id` = ID de la permission courante) et dissocie simultanément tous les rôles.

## 8. Configuration système

La configuration système est unique par combinaison `group` + `key`.

### 8.1 Liste des configurations

```
GET /admin/config
```

- **Authentification** : JWT + RBAC

**Paramètres de requête** :

| Paramètre | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| page | int | Non | 1 | Numéro de page |
| limit | int | Non | 15 | Nombre d'éléments par page |
| group | string | Non | | Filtrer par groupe de configuration |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| Champ | Type | Description |
|------|------|------|
| id | string | hashid |
| group | string | Groupe de configuration (par ex. `system`, `email`, `storage`) |
| key | string | Clé de configuration |
| value | string | Valeur de configuration |
| type | string | Indication du type de valeur (`string`, `integer`, `boolean`, `json`, etc.) |
| description | string | Description de la configuration |

### 8.2 Création d'une configuration

```
POST /admin/config
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| group | string | Oui | max:100 | Groupe de configuration |
| key | string | Oui | max:100 | Clé de configuration (unique au sein du groupe) |
| value | string | Oui | | Valeur de configuration |
| type | string | Non | | Type de valeur, défaut `string` |
| description | string | Non | | Description, défaut vide |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**Erreurs possibles** :
- 422 : l'élément de configuration existe déjà (même group + key)

### 8.3 Mise à jour d'une configuration

```
PUT /admin/config/{id}
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| value | string | Non | Met à jour la valeur |
| type | string | Non | Met à jour le type de valeur |
| description | string | Non | Met à jour le texte de description |

### 8.4 Suppression d'une configuration

```
DELETE /admin/config/{id}
```

- **Authentification** : JWT + RBAC
- **Opération sensible** : confirmation par mot de passe requise

**Corps de requête** :
```json
{
  "password": "admin_password"
}
```

Suppression physique de l'enregistrement de configuration.

## 9. Journaux d'opérations

Les journaux d'opérations sont des interfaces en lecture seule, écrits automatiquement par le middleware `OperationLog` à chaque requête POST/PUT/DELETE ; les champs stockés incluent `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Liste des journaux d'opérations

```
GET /admin/log
```

- **Authentification** : JWT + RBAC

**Paramètres de requête** :

| Paramètre | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| page | int | Non | 1 | Numéro de page |
| limit | int | Non | 15 | Nombre d'éléments par page |
| user_id | int | Non | | Filtrage exact par ID utilisateur (type INT brut) |
| action | string | Non | | Filtrage exact par action |
| path | string | Non | | Filtrage flou par chemin de requête |
| start_date | string | Non | | Date de début (format Y-m-d) |
| end_date | string | Non | | Date de fin (format Y-m-d) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| Champ | Type | Description |
|------|------|------|
| id | string | hashid |
| user_name | string | Nom d'utilisateur de l'opération (obtenu via la relation user ; affiche « 系统 » pour les opérations non connectées) |
| action | string | Description de l'action |
| method | string | Méthode HTTP (POST/PUT/DELETE) |
| path | string | Chemin de requête |
| ip | string | IP du client |
| source | string | Côté d'origine de la requête |
| input | string | Chaîne JSON des paramètres de requête (sans les fichiers) |
| created_at | string | Heure de l'opération (datetime) |

## 10. Espace personnel

Les interfaces de l'espace personnel ne nécessitent qu'une authentification JWT (pas de vérification RBAC — le middleware `AdminPermission` doit les ajouter à sa liste blanche).

### 10.1 Mise à jour des informations personnelles

```
PUT /admin/profile
```

- **Authentification** : JWT

**Corps de requête** :
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| real_name | string | Non | Nom réel |
| phone | string | Non | Téléphone (stocké chiffré via Encryptable) |
| email | string | Non | Email (stocké chiffré via Encryptable) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

Dans la réponse, `phone` et `email` sont renvoyés en clair ; `password` et `id_card` sont exclus.

### 10.2 Modification du mot de passe

```
PUT /admin/profile/password
```

- **Authentification** : JWT

**Corps de requête** :
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| old_password | string | Oui | | Mot de passe actuel |
| new_password | string | Oui | min:6, max:32 | Nouveau mot de passe |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Erreurs possibles** :
- 422 : veuillez renseigner l'ancien et le nouveau mot de passe
- 422 : ancien mot de passe incorrect
- 422 : le nouveau mot de passe doit comporter 6 à 32 caractères

### 10.3 Déconnexion

```
POST /admin/profile/logout
```

- **Authentification** : JWT

**Corps de requête** : aucun (pas de requestBody, le token est lu dans l'en-tête Authorization)

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Logique de déconnexion : décoder le JWT pour obtenir la durée de validité restante (exp - now), écrire le hash md5 de ce token dans la liste noire Redis `jwt_blacklist:{md5}`, TTL = durée de validité restante. Les tokens présents sur la liste noire sont interceptés par le middleware `AdminAuth`, qui renvoie 401.

Sans token, 401 est renvoyé. Si le token est expiré/invalide (exception au décodage), la déconnexion est tout de même considérée comme réussie.

## 11. Import/export

### 11.1 Export Excel

```
POST /admin/export/excel
```

- **Authentification** : JWT + RBAC
- **Type de réponse** : téléchargement de fichier (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Corps de requête** :
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| Champ | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| table | string | Non | `admin_user` | Nom de la table à exporter. Pris en charge : `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | Non | | Tableau des noms de colonnes à exporter ; vide = toutes les colonnes de la table |
| conditions | object | Non | `{}` | Conditions de filtrage, paires clé-valeur ; utilisées pour WHERE si la valeur n'est pas vide |
| title | string | Non | `数据导出` | Titre Excel (affiché comme nom de feuille) |

**Tables et colonnes prises en charge** :

| table | Colonnes disponibles |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Les champs sensibles `phone`, `email`, `id_card` sont automatiquement masqués à l'export. Limite de 10000 lignes. La première ligne Excel est figée, avec filtre automatique.

### 11.2 Export PDF

```
POST /admin/export/pdf
```

- **Authentification** : JWT + RBAC
- **Type de réponse** : téléchargement de fichier (`application/pdf`, A4 paysage)

**Corps de requête** :
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

Ou mode tableau :
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| Champ | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| type | string | Non | `table` | Type d'export : `table` / `dashboard` |
| title | string | Non | `数据导出` | Titre du PDF |
| data | object | Non | `{}` | Données à exporter |

Avec `type=dashboard`, `data` doit contenir un tableau `stats` (rendu sous forme de cartes) ; avec `type=table`, `data` doit contenir les tableaux `columns` et `rows`.

Le modèle PDF inclut les informations de copyright et l'horodatage d'export.

### 11.3 Import d'utilisateurs (Excel)

```
POST /admin/import/users
```

- **Authentification** : JWT + RBAC
- **Type de requête** : `multipart/form-data` (upload de fichier)

**Champs du formulaire** :

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| file | file | Oui | Format `.xlsx` ou `.xls` |

**Exigences sur les colonnes Excel** :

| Nom de colonne | Obligatoire | Description |
|------|------|------|
| username | Oui | Nom d'utilisateur (unique) |
| password | Oui | Mot de passe (stocké en hash bcrypt) |
| real_name | Oui | Nom réel |
| phone | Non | Téléphone |
| email | Non | Email |
| status | Non | Statut, défaut 1 |

La ligne 1 contient les titres de colonnes (insensibles à la casse), les données commencent à la ligne 2.

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| Champ | Type | Description |
|------|------|------|
| total | int | Nombre total de lignes (hors ligne de titre) |
| success | int | Nombre d'imports réussis |
| failed | int | Nombre d'échecs |
| errors | array | Détails des échecs, chaque élément contient row (numéro de ligne Excel) et reason (raison de l'échec) |

## 12. Upload de fichiers

```
POST /admin/upload
```

- **Authentification** : JWT + RBAC
- **Type de requête** : `multipart/form-data`

**Champs du formulaire** :

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| file | file | Oui | Fichier à uploader |

**Types de fichiers autorisés** : `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Taille maximale de fichier** : 10 Mo

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Les fichiers sont stockés dans des répertoires par date sous `public/upload/{Y-m-d}/`, avec un nom de fichier `md5(uniqid) + extension d'origine`. `url` est un chemin relatif à la racine du site.

**Erreurs possibles** :
- 422 : veuillez sélectionner un fichier (aucun upload)
- 422 : type de fichier non pris en charge
- 422 : la taille du fichier ne peut pas dépasser 10 Mo
- 500 : échec de l'upload du fichier (fichier invalide)

## 13. En-têtes de réponse

Toutes les interfaces (injectés au niveau des middlewares globaux) incluent les en-têtes de réponse suivants :

| En-tête | Description |
|----|------|
| `X-RateLimit-Limit` | Plafond de limitation (nombre de requêtes) |
| `X-RateLimit-Remaining` | Nombre de requêtes restantes |
| `X-RateLimit-Reset` | Horodatage de réinitialisation de la fenêtre de limitation |
| `Retry-After` | Uniquement renvoyé lors du déclenchement de la limitation, secondes d'attente recommandées |
| `X-Content-Type-Options` | `nosniff` (par défaut webman, interdit le MIME sniffing) |
| `X-Frame-Options` | `DENY` (fourni par le middleware CORS/la configuration de base de webman) |

Détails de la limitation :
- Limite globale par défaut : 60 requêtes/minute / IP+chemin
- Point d'extrémité de connexion `/api/auth/login` : 10 requêtes/minute
- Point d'extrémité d'inscription `/api/auth/register` : 5 requêtes/minute
- Algorithme de fenêtre glissante atomique Redis (Lua ZSET), évite la course TOCTOU
- Si Redis est indisponible : fail-closed — renvoie 503 (`Retry-After: 5`), ne laisse pas passer les requêtes

## 14. Analyse de données (Analytics)

Tous les points d'extrémité nécessitent une authentification (`AdminAuth` + `AdminPermission`), agrégation MySQL en temps réel, 12 au total :

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/analytics/overview | Aperçu de la plateforme (aujourd'hui/7 derniers jours) |
| GET | /admin/analytics/game-ranking | Classement des jeux (?days=7) |
| GET | /admin/analytics/dau-trend | Tendance DAU (?days=30) |
| GET | /admin/analytics/hourly-trend | Tendance horaire |
| GET | /admin/analytics/action-distribution | Répartition des comportements |
| GET | /admin/analytics/revenue | Analyse des revenus |
| GET | /admin/analytics/conversion | Taux de conversion des jeux |
| GET | /admin/analytics/probability | Probabilités conjointes/conditionnelles |
| GET | /admin/analytics/retention | Analyse de rétention D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Entonnoir de conversion |
| GET | /admin/analytics/arpu | Tendance ARPU/ARPPU |
| GET | /admin/analytics/economy | Indicateurs économiques des devises de jeu |

## 15. Gestion des tickets (Ticket)

Tous les points d'extrémité nécessitent une authentification (`AdminAuth` + `AdminPermission`), 5 au total :

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/ticket/list | Liste des tickets (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | Détail d'un ticket (avec réponses) |
| POST | /admin/ticket/{hashid}/reply | Répondre à un ticket |
| POST | /admin/ticket/{hashid}/close | Clôturer un ticket |
| POST | /admin/ticket/{hashid}/assign | Attribuer un traitement (admin_id) |

## 16. Flux d'authentification

Séquence d'authentification complète :

```
1. Le client appelle POST /api/captcha/generate
   (En-tête de requête : API-Version: v1)
    ↓
   Le serveur renvoie : key + image base64 + indication des cibles à cliquer
   
2. L'utilisateur clique sur les positions des cibles de l'image, le frontend/client collecte les coordonnées des clics
   
3. Le client appelle POST /api/auth/login
   (En-tête de requête : API-Version: v1, Content-Type: application/json)
   Corps de requête : { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   Serveur :
   a. Validation des paramètres → 422
   b. Vérification du captcha → 422
   c. Vérification des identifiants → 401
   d. Contrôle de l'état du compte → 403
   e. Émission du JWT (access + refresh) → 200
   f. Mise à jour de last_login_at / last_login_ip
    ↓
   Le client enregistre : access_token, refresh_token, expires_in

4. Les requêtes suivantes transportent le JWT
   En-tête de requête : Authorization: Bearer <access_token>
    ↓
   Middleware AdminAuth :
   a. Extraction du token Bearer
   b. Contrôle de la liste noire (Redis jwt_blacklist:{md5}) → 401
   c. Décodage du JWT, vérification de l'expiration → 401
   d. Définition de $request->adminId = champ sub
    ↓
   Middleware AdminPermission :
   a. Non connecté (adminId vide) → 401
   b. Résolution de l'identifiant de permission pour la route de la ressource
   c. Consultation des rôles de l'utilisateur → permissions des rôles, correspondance
   d. Sans permission → 403
    ↓
   Le contrôleur traite la requête
    ↓
   Réponse + en-têtes X-RateLimit-*

5. Rafraîchissement avant expiration du token d'accès
   Le client appelle POST /api/auth/refresh
   Corps de requête : { refresh_token: "..." }
    ↓
   Le serveur décode le refresh_token → émet un nouveau access + refresh
    ↓
   Le client met à jour ses jetons locaux

6. Déconnexion
   Le client appelle POST /admin/profile/logout
   En-tête de requête : Authorization: Bearer <access_token>
    ↓
   Serveur :
   a. Décodage du JWT pour obtenir le TTL restant
   b. Écriture dans la liste noire Redis : jwt_blacklist:{md5(token)} = 1, TTL = durée de validité restante
   c. Retour du succès
```

### Structure JWT

- **access_token** : `{ sub: <user_id>, username: "<name>" }`, TTL par défaut 7200 secondes (contrôlé par la configuration JWT `default_expire`)
- **refresh_token** : `{ sub: <user_id>, token_type: "refresh" }`, TTL par défaut 1209600 secondes (contrôlé par la configuration JWT `refresh_expire`, soit 14 jours)

### Gestion de la sécurité

- Les mots de passe sont stockés en hash `PASSWORD_BCRYPT`
- Les champs sensibles (phone, email, id_card) utilisent `erikwang2013/encryptable` pour un chiffrement/déchiffrement transparent au niveau de la base
- Les ID au niveau API utilisent `erikwang2013/hashids` pour un transport chiffré, évitant d'exposer les séquences d'ID snowflake brutes
- SecurityFilter analyse globalement XSS, l'injection SQL, le parcours de chemin et l'injection de commandes ; même IP 5 fois/60 s = liste noire temporaire de 15 minutes
- Les opérations sensibles (suppression d'utilisateur, de rôle, de permission, de configuration) nécessitent une double confirmation par le mot de passe de l'utilisateur connecté
- Limitation des sessions concurrentes : un même utilisateur détient au maximum 3 jetons valides ; à la connexion d'un 4e appareil, le jeton le plus ancien est forcé sur la liste noire
- Verrouillage de compte : 5 échecs de connexion consécutifs déclenchent un verrouillage de 15 minutes, 429 est renvoyé pendant la période de verrouillage

## 15. Déploiement et exploitation

### Docker Compose

Le répertoire racine du projet fournit `docker-compose.yml`, orchestrant 5 services (Nginx, app webman, MySQL, Redis, Elasticsearch). PHP est construit via `Dockerfile` (basé sur `php:8.3-cli`, OPcache activé).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` définit le pipeline d'intégration continue GitHub Actions :
- Vérification de syntaxe `php -l`
- Tests unitaires PHPUnit
- Analyse statique `flutter analyze`

### Sauvegarde de base de données

Le répertoire `database/backup/` fournit des scripts de sauvegarde et de restauration :
- `backup.sh` — sauvegarde compressée mysqldump + gzip, nettoie automatiquement les sauvegardes de plus de 30 jours
- `restore.sh` — restauration interactive, liste les sauvegardes existantes pour que l'utilisateur choisisse

### Configuration de sécurité Nginx

Pour un déploiement en production, référez-vous à `docs/nginx-security.conf` pour renforcer la configuration de sécurité du reverse proxy.

## 16. Analyse de données (Analytics)

Les interfaces d'analyse de données sont fournies par `AnalyticsController`, toutes basées sur une agrégation MySQL en temps réel (`game_game_play_log` journaux de comportement de jeu / `game_deposit_order` commandes de recharge) ; en cas de panne de la base, des données vides sont renvoyées au lieu d'une erreur 500. Sauf mention contraire, l'authentification JWT + RBAC est requise, et le format d'emballage des réponses est uniformément `{ "code": 0, "message": "success", "data": ... }`.

### 16.1 Aperçu de la plateforme

```
GET /admin/analytics/overview
```

**Réponse** : `today` / `week` contiennent chacun `dau` (utilisateurs actifs), `revenue` (total des recharges confirmées, chaîne), `new_users` (nouveaux utilisateurs).

### 16.2 Classement des jeux

```
GET /admin/analytics/game-ranking?days=7
```

**Réponse** : top 10 par nombre décroissant de comportements de jeu, chaque élément contient `game_id` (hashid), `name`, `plays`, `players`.

### 16.3 Tendance DAU

```
GET /admin/analytics/dau-trend?days=30
```

**Réponse** : `{ "日期": nombre d'actifs, ... }`, les dates manquantes sont complétées par 0.

### 16.4 Tendance horaire

```
GET /admin/analytics/hourly-trend?game_id=<hashid>
```

**Réponse** : `{ "0": nombre, ... "23": nombre }` 24 créneaux horaires ; si `game_id` est vide, tous les jeux sont comptabilisés.

### 16.5 Répartition des comportements

```
GET /admin/analytics/action-distribution?game_id=<hashid>&hours=24
```

**Réponse** : `{ "start": n, "end": n, "earn": n, "spend": n }` comptages des quatre catégories de comportements ; `hours` plafonné à 168.

### 16.6 Aperçu des revenus

```
GET /admin/analytics/revenue?days=7
```

**Réponse** : `{ "total": "montant total", "trend": { "日期": "montant du jour", ... } }`, seules les commandes `status=confirmed` sont comptabilisées.

### 16.7 Taux de conversion des jeux

```
GET /admin/analytics/conversion?days=30
```

**Réponse** : chaque jeu contient `game_id` (hashid), `game_name`, `players` (joueurs uniques), `depositors` (rechargeurs uniques), `conversion_rate` (taux de conversion en recharge, 0~1).

### 16.8 Probabilité conjointe

```
GET /admin/analytics/probability?game_a=<hashid>&game_b=<hashid>
```

**Réponse** : `{ "joint": { "joint_probability": 0.12, "confidence": 0.3 } }` — coefficient de Jaccard (joueurs communs aux deux jeux / joueurs de l'union) et confiance (joueurs communs / joueurs du jeu A).

### 16.9 Analyse de rétention

```
GET /admin/analytics/retention?days=30
```

**Réponse** : `{ "D1": "8.5%", "D3": "...", "D7": "...", "D30": "..." }` taux de rétention à J+1/J+3/J+7/J+30 par cohorte de date d'inscription.

### 16.10 Entonnoir de conversion

```
GET /admin/analytics/funnel?days=30
```

**Réponse** : les quatre étapes inscription → premier dépôt → premier échange → première partie de jeu, avec `step`, `count`, `rate` (pourcentage relatif au nombre d'inscriptions).

### 16.11 Tendance ARPU/ARPPU

```
GET /admin/analytics/arpu?days=30
```

**Réponse** : `{ "dates": [...], "arpu": [...], "arppu": [...] }` revenu moyen par utilisateur (ARPU) et revenu moyen par utilisateur payant (ARPPU) par jour.

### 16.12 Indicateurs économiques des jeux

```
GET /admin/analytics/economy
```

**Réponse** : tableau `currencies`, chaque élément contient `game_name`, `currency`, `symbol`, `total_minted` (masse monétaire totale émise), `total_burned` (masse détruite totale), `circulation` (masse en circulation), `inflation_rate` (taux d'inflation), calculs haute précision via bcmath.

## 17. Gestion des paiements (Payment)

La gestion des méthodes de paiement est fournie par `PaymentController` ; les 5 endpoints requièrent une authentification JWT + RBAC. Liste blanche `provider` : `stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash`. `config` est une chaîne JSON de configuration de paiement (stockée chiffrée en base).

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/payment/method/list | Liste des méthodes de paiement (tri croissant par sort) |
| POST | /admin/payment/method/toggle | Activer/désactiver une méthode de paiement |
| POST | /admin/payment/method/create | Créer une méthode de paiement |
| PUT | /admin/payment/method/{hashid} | Mettre à jour une méthode de paiement |
| DELETE | /admin/payment/method/{hashid} | Supprimer une méthode de paiement (refusé si des commandes en attente existent) |

### 17.1 Liste des méthodes de paiement

```
GET /admin/payment/method/list
```

- **Authentification** : JWT + RBAC

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "name": "Carte bancaire Stripe",
        "type": "fiat",
        "provider": "stripe",
        "status": 1,
        "sort": 1,
        "countries": ["US", "SG"],
        "currency": "USD",
        "min_amount": "10",
        "max_amount": "5000",
        "config": null,
        "created_at": "2026-08-29 10:00:00",
        "updated_at": "2026-08-29 10:00:00"
      }
    ]
  }
}
```

| Champ | Type | Description |
|------|------|------|
| id | string | ID de la méthode de paiement (codé hashid) |
| name | string | Nom de la méthode de paiement |
| type | string | `fiat` (monnaie fiduciaire) / `crypto` (cryptomonnaie) |
| provider | string | Fournisseur de passerelle : `stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash` |
| status | int | 1=actif, 0=inactif |
| sort | int | Valeur de tri (croissant) |
| countries | array{string} | Codes pays visibles (tableau vide = visible mondialement) |
| currency | string | Devise (p. ex. USD/USDT), vide = aucune restriction |
| min_amount / max_amount | string | Plage de montants (chaîne pour préserver la précision), 0 = sans limite |
| config | string? | JSON de configuration de paiement (chiffré ; null si non défini) |

### 17.2 Activer/désactiver une méthode de paiement

```
POST /admin/payment/method/toggle
```

**Corps de la requête** :
```json
{
  "id": "a1b2c3d4",
  "status": 1
}
```

| Champ | Type | Requis | Description |
|------|------|------|------|
| id | string | Oui | ID de la méthode de paiement (hashid) |
| status | int | Oui | 0=désactivé, 1=activé |

**Erreurs possibles** :
- 422 : échec de validation (id/status manquant ou status différent de 0/1)
- 404 : méthode de paiement introuvable

### 17.3 Créer une méthode de paiement

```
POST /admin/payment/method/create
```

**Corps de la requête** :
```json
{
  "name": "Cryptomonnaie USDT",
  "type": "crypto",
  "provider": "nowpayments",
  "status": 1,
  "sort": 2,
  "countries": [],
  "currency": "USDT",
  "min_amount": "10",
  "max_amount": "10000",
  "config": "{\"api_key\":\"...\"}"
}
```

| Champ | Type | Requis | Validation | Description |
|------|------|------|---------|------|
| name | string | Oui | max:50 | Nom de la méthode de paiement |
| type | string | Oui | in:fiat,crypto | Type : fiduciaire/cripto |
| provider | string | Oui | in:stripe,paypal,nowpayments,coinbase,skrill,neteller,paysafecard,paytm,mercadopago,astropay,paypay,kakaopay,gcash | Liste blanche des fournisseurs de passerelle |
| status | int | Oui | in:0,1 | État |
| sort | int | Non | integer,min:0 | Valeur de tri, défaut 0 |
| countries | array{string} | Non | max:2 | Codes pays visibles, vide = mondial |
| currency | string | Non | max:10 | Devise, défaut vide |
| min_amount / max_amount | string | Non | numeric,min:0 | Plage de montants, défaut "0" |
| config | string | Non | | JSON de configuration de paiement (chiffré) ; chaîne vide stockée en NULL |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "créé avec succès",
  "data": { "id": "e5f6g7h8" }
}
```

**Erreurs possibles** :
- 422 : échec de validation

### 17.4 Mettre à jour une méthode de paiement

```
PUT /admin/payment/method/{hashid}
```

- **Paramètre de chemin** : `{hashid}` est l'ID de la méthode de paiement codé en hashid
- **Corps de la requête** : identique à la création (17.3), tous les champs optionnels ; seuls les champs transmis sont mis à jour

**Erreurs possibles** :
- 404 : méthode de paiement introuvable
- 422 : échec de validation

### 17.5 Supprimer une méthode de paiement

```
DELETE /admin/payment/method/{hashid}
```

- **Paramètre de chemin** : `{hashid}` est l'ID de la méthode de paiement codé en hashid

**Erreurs possibles** :
- 404 : méthode de paiement introuvable
- 422 : des commandes de dépôt en attente (status=pending) existent, suppression impossible
