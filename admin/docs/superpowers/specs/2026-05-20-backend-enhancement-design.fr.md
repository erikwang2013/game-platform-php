# Sous-projet A : Amélioration du backend — Spécifications de conception
<!-- lang-nav -->

Languages: [中文](2026-05-20-backend-enhancement-design.md) · [English](2026-05-20-backend-enhancement-design.en.md) · [한국어](2026-05-20-backend-enhancement-design.ko.md) · [Русский](2026-05-20-backend-enhancement-design.ru.md) · [Deutsch](2026-05-20-backend-enhancement-design.de.md) · **Français** · [Español](2026-05-20-backend-enhancement-design.es.md) · [Português](2026-05-20-backend-enhancement-design.pt.md) · [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) · [العربية](2026-05-20-backend-enhancement-design.ar.md) · [বাংলা](2026-05-20-backend-enhancement-design.bn.md) · [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) · [日本語](2026-05-20-backend-enhancement-design.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Périmètre

Il s'agit de l'amélioration du backend : 15 points fonctionnels au total, impliquant 9 nouveaux fichiers + 4 fichiers modifiés.

---

## Liste des fichiers ajoutés/modifiés

```
app/middleware/
├── OperationLog.php          # Nouveau : enregistrement automatique des journaux d'opérations
├── Cors.php                  # Nouveau : cross-origin
└── RateLimit.php             # Nouveau : rate-limit Redis
app/admin/controller/
├── ConfigController.php      # Nouveau : CRUD de configuration système
├── LogController.php         # Nouveau : consultation des journaux d'opérations
├── ProfileController.php     # Nouveau : espace personnel (avec déconnexion)
├── UploadController.php      # Nouveau : upload de fichiers
├── ImportController.php      # Nouveau : import Excel d'utilisateurs
└── HealthController.php      # Nouveau : health check
app/model/
├── AdminUser.php             # Modifié : ajout des traits SoftDeletes + Searchable
└── OperationLog.php          # Modifié : ajout de public $timestamps = false
app/middleware/
└── AdminAuth.php             # Modifié : vérification de la liste noire JWT
app/admin/controller/
├── DashboardController.php   # Modifié : statistiques temps réel depuis la base
└── UserController.php        # Modifié : nouveaux traitements par lots
config/
└── route.php                 # Modifié : nouvelles routes + middleware
```

---

## 1. Middleware

### 1.1 Middleware CORS

**Fichier** : `app/middleware/Cors.php`

- Les requêtes de préflight OPTIONS renvoient directement 204
- Les requêtes non préflight ajoutent l'en-tête de réponse `Access-Control-Allow-Origin: *`
- En-têtes autorisés : `Authorization, Content-Type, API-Version`
- Cache maximum : 86400 secondes

Montage : middleware global (`config/middleware.php`)

### 1.2 Middleware de rate-limit

**Fichier** : `app/middleware/RateLimit.php`

- Stockage : fenêtre glissante Redis Sorted Set
- Défaut : 60 requêtes/minute/IP/route
- Interfaces sensibles :
  - `/api/auth/login` : 10 requêtes/minute
  - `/api/auth/register` : 5 requêtes/minute
- Dépassement : renvoie `429 Too Many Requests`

Montage : middleware global (`config/middleware.php`), après Cors et avant ApiVersion

### 1.3 Middleware de journal d'opérations

**Fichier** : `app/middleware/OperationLog.php`

- Enregistre uniquement POST/PUT/DELETE
- Champs enregistrés : user_id, action, method, path, ip, input(JSON)
- Écriture asynchrone après la réponse (non bloquant)

Montage : groupe de routes `/admin`, après AdminPermission

### 1.4 Chaîne d'exécution des middleware globaux

```
Toutes les requêtes:
  Cors → RateLimit → ApiVersion → {Middleware de route} → Controller

Requêtes /admin/* :
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Déconnexion (liste noire JWT)

**Fichier** : `app/middleware/AdminAuth.php` (modifié)

**Principe** : le JWT est sans état ; à la déconnexion, le token est ajouté à la liste noire Redis, et AdminAuth vérifie la liste noire lors de la validation.

**Transformation d'AdminAuth** :
- Au début de `process()` : vérifier dans l'ensemble Redis `jwt_blacklist` si le token courant est sur la liste noire
- Correspondance sur la liste noire → renvoie 401

**Route de déconnexion** (sous l'espace personnel) :

| Méthode | Route | Description |
|------|------|------|
| `POST` | `/admin/profile/logout` | Ajoute le token Bearer courant à la liste noire Redis, TTL = durée de validité restante du token |

**Logique de Logout** :
```php
// Résoudre la durée de validité restante du token
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// Ajouter à la liste noire
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Nouveaux contrôleurs et transformations existantes

### 2.1 CRUD de configuration système (`ConfigController`)

Hérite de `BaseController`.

| Méthode | Route | Description |
|------|------|------|
| `index()` | GET `/admin/config` | Liste paginée, filtrable par `group`, pagination `page`/`limit` |
| `store()` | POST `/admin/config` | Crée un élément de configuration, obligatoires : group, key, value |
| `update()` | PUT `/admin/config/{id}` | Met à jour value/type/description |
| `destroy()` | DELETE `/admin/config/{id}` | Supprime un élément, requiert `confirmPassword()` |

### 2.2 Consultation des journaux d'opérations (`LogController`)

Hérite de `BaseController`.

| Méthode | Route | Description |
|------|------|------|
| `index()` | GET `/admin/log` | Liste paginée, filtres : user_id, action, path, created_at (plage) |

Pas de création/modification/suppression ; les journaux sont enregistrés automatiquement par le middleware.

### 2.3 Espace personnel (`ProfileController`)

Hérite de `BaseController`. Agit sur l'utilisateur connecté (`$request->adminId`).

| Méthode | Route | Description |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Met à jour real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Modifie le mot de passe, requiert old_password, new_password, new_password_confirmation |

### 2.4 Upload de fichiers (`UploadController`)

Hérite de `BaseController`.

| Méthode | Route | Description |
|------|------|------|
| `upload()` | POST `/admin/upload` | Reçoit un fichier, types pris en charge : image/jpeg/png/gif/pdf/xlsx/docx |

- Maximum 10 Mo
- Chemin de stockage : `public/upload/{date}/{hash}.{ext}`
- Renvoie : `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Données réelles du tableau de bord

**Fichier** : `app/admin/controller/DashboardController.php` (modifié)

Remplacer les fausses données codées en dur par des statistiques temps réel depuis la base :

| Indicateur | Source | Description |
|------|------|------|
| Nombre total d'utilisateurs | `AdminUser::count()` | Hors suppression douce |
| Nouveaux du jour | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Nombre total de rôles | `AdminRole::count()` | |
| Nombre total de permissions | `AdminPermission::count()` | |
| Données de tendance | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Nouveaux par jour des 7 derniers jours |
| Données de répartition | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Répartition par statut |
| Opérations récentes | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | 10 derniers journaux d'opérations |

### 2.6 Opérations par lots sur les utilisateurs

**Fichier** : `app/admin/controller/UserController.php` (modifié, nouvelles méthodes)

| Méthode | Route | Description |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Suppression par lots, corps `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Activation/désactivation par lots, corps `{ ids: [hashid, ...], status: 1|0 }` |

- Chaque id est d'abord converti en BIGINT via `decodeId()`
- `batchDestroy()` doit passer la vérification `confirmPassword()`

### 2.7 Import de données

**Fichier** : `app/admin/controller/ImportController.php` (nouveau)

| Méthode | Route | Description |
|------|------|------|
| `users()` | POST `/admin/import/users` | Upload d'un fichier Excel, création d'utilisateurs par lots |

Flux :
1. Recevoir le fichier `.xlsx`
2. Analyse PhpSpreadsheet, colonnes attendues : `username, password, real_name, phone, email, status`
3. Validation ligne par ligne + création (ID généré par snowflake, mot de passe bcrypt, phone/email chiffrés via encryption)
4. Renvoie le résultat : `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "nom d'utilisateur déjà existant"}, ...] }`

### 2.8 Health check

**Fichier** : `app/admin/controller/HealthController.php` (nouveau)

`GET /health` (sans authentification, non comptabilisé dans les journaux d'opérations) :

Renvoie l'état de connexion de chaque composant :
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- En cas d'échec de détection d'un composant, le champ correspondant contient la chaîne de description de l'erreur
- La route n'a pas le préfixe `/admin`, enregistrée séparément au niveau global

---

## 3. Corrections des modèles

### 3.1 Horodatage OperationLog

**Fichier** : `app/model/OperationLog.php` (modifié)

La table `game_operation_log` n'a qu'une colonne `created_at` (pas de `updated_at`). Le `save()` par défaut d'Eloquent tenterait d'écrire `updated_at`, provoquant une erreur SQL.

Correctif : `public $timestamps = false;` + spécification manuelle de `created_at` à l'écriture.

### 3.2 Transformation du modèle AdminUser

- Ajout du trait `Searchable`
- Implémentation de `toSearchableArray()` : renvoie username, real_name
- `UserController::index()` utilise `AdminUser::search($kw)->get()` au lieu du LIKE MySQL quand un mot-clé est détecté

ES nécessite d'abord la création de l'index, via les commandes Scout :

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Changements de routes

Nouvelles routes dans `config/route.php` :

```php
// Ajouts dans le groupe de routes /admin :
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// Health check (route globale, hors du groupe /admin)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// Middleware :
ajout de app\middleware\OperationLog::class au groupe /admin
```

Enregistrement des middleware globaux dans `config/middleware.php` :

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Complément de codes d'erreur

| code | Signification | Scénario de déclenchement |
|------|------|---------|
| 429 | Requêtes trop fréquentes | Déclenchement de RateLimit |

---

## 6. Hors périmètre de cette itération

- Système de notifications (nécessite une file de messages + une infrastructure de push frontend)
- Pages frontend Flutter (sous-projet B)
- Rafraîchissement de token HarmonyOS (sous-projet C)
