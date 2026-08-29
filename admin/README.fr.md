# Administration ouverte (open-admin)

## Mascotte du projet

<img src="../docs/mascot.svg" width="120" alt="Dicey"/>

**Dicey** — Mascotte de la plateforme. Le dé représente les jeux et le gameplay basé sur la probabilité, la pièce l'économie de la plateforme et les passerelles de paiement multiples, et le violet reflète l'identité du panneau d'administration. Fichier SVG : `docs/mascot.svg`, redimensionnable à l'infini pour la documentation, les logos et les produits dérivés.
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · **Français** · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

Système d'administration full-stack basé sur webman v2 + Flutter.

> [Version anglaise](README.en.md) | [Diagramme d'architecture](docs/ARCHITECTURE.fr.md) | [Document de conception](docs/DESIGN.fr.md) | [Architecture de sécurité](docs/SECURITY.fr.md) | [Référence API](docs/API.fr.md)

## Liste des fonctionnalités

| Domaine métier | Fonctionnalité | Description |
|--------|------|------|
| 🔐 Authentification | Connexion/inscription/rafraîchissement de jeton/déconnexion | Captcha à clic + JWT + liste noire |
| | Verrouillage de compte | 5 échecs = verrouillage 15 minutes |
| | Limite de sessions concurrentes | 3 tokens valides maximum par utilisateur |
| 📊 Tableau de bord | Statistiques temps réel/courbe de tendance/diagramme de répartition/opérations récentes | Cache Redis 5 minutes |
| 📈 Analyse de données | 12 points d'API : vue d'ensemble/classement/DAU/heures/répartition des comportements/revenus/conversion/probabilités/rétention/entonnoir/ARPU/indicateurs économiques | Agrégation temps réel MySQL, données vides si la base est en panne |
| 👥 Gestion des utilisateurs | CRUD + suppression/activation/désactivation par lots | Suppression douce + confirmation par mot de passe |
| | Import Excel par lots | Validation ligne par ligne + rapport d'erreurs |
| 🔒 Rôles et permissions | CRUD des rôles + arbre de permissions | Autorisation RBAC à la granularité method.path |
| ⚙ Configuration système | CRUD de paires clé-valeur | Gestion par groupes |
| 📋 Audit des opérations | Consultation des journaux + détection du canal d'origine | Reconnaissance automatique de 8 plateformes |
| 📁 Gestion des fichiers | Upload/export Excel/export PDF | Masquage automatique des données sensibles |
| 🛡 Protection de sécurité | 18 couches de défense en profondeur | XSS/injection SQL/traversée de chemin/injection de commandes/CSRF/rate-limit/CSP... |
| 🏥 Exploitation | Health check/metrics/documentation API/security.txt | Prometheus + OpenAPI 3.0 |

## Pile technique

| Couche | Technologie | Description |
|---|------|------|
| Framework backend | webman v2 (workerman) | Framework PHP résident en mémoire ultra-performant |
| Version PHP | 8.3+ | |
| Base de données | MySQL 8.0+ | Préfixe de tables `game_`, clés primaires BIGINT non auto-incrémentées |
| Moteur de recherche | Elasticsearch | Synchronisation et recherche via `webman-scout` |
| Frontend d'administration | Flutter 3.x | Web au style PC administration (`apps/flutter/`) |
| Mobile | HarmonyOS ArkTS | Client natif HarmonyOS (`apps/harmonyos/`), prend en charge mobile/tablette/2-en-1 |

## Dépendances centrales

| Paquet | Usage |
|---|------|
| `erikwang2013/snowflake-php` | Algorithme Snowflake pour générer des clés primaires BIGINT globalement uniques |
| `erikwang2013/hashids` | Chiffrement/déchiffrement des ID au niveau API, masque les ID de base réels |
| `erikwang2013/jwt-webman` | Émission et validation des jetons d'authentification JWT |
| `erikwang2013/encryption` | Chiffrement/déchiffrement des données sensibles en couche de transport API |
| `erikwang2013/encryptable` | Chiffrement/déchiffrement automatique des champs sensibles en couche de stockage |
| `erikwang2013/webman-scout` | Synchronisation des données Elasticsearch et recherche plein texte |
| `erikwang2013/season` | Données de drapeaux de pays |
| `erikwang2013/poster-php` | Génération et validation de captcha à clic + génération d'affiches |
| `phpoffice/phpspreadsheet` | Export Excel |
| `barryvdh/laravel-dompdf` | Export PDF (basé sur Dompdf) |

## Structure du projet

```
open-admin/
├── app/
│   ├── admin/controller/       # Contrôleurs d'administration
│   │   ├── DashboardController.php # Tableau de bord (cache Redis)
│   │   ├── UserController.php      # CRUD utilisateurs + opérations par lots
│   │   ├── RoleController.php      # CRUD des rôles
│   │   ├── PermissionController.php# CRUD des permissions
│   │   ├── ConfigController.php    # CRUD de configuration système
│   │   ├── LogController.php       # Consultation des journaux d'opérations
│   │   ├── ProfileController.php   # Espace personnel + déconnexion
│   │   ├── ExportController.php    # Export Excel/PDF
│   │   ├── ImportController.php    # Import Excel d'utilisateurs
│   │   ├── UploadController.php    # Upload de fichiers
│   │   ├── HealthController.php    # Health check
│   │   ├── DocsController.php      # Documentation OpenAPI
│   │   └── BaseController.php      # Contrôleur de base
│   ├── api/
│   │   └── v1/controller/          # Contrôleurs API v1 (version contrôlée par l'en-tête API-Version)
│   │       ├── CaptchaController.php # Captcha à clic
│   │       └── AuthController.php    # Connexion/inscription/rafraîchissement de jeton
│   ├── common/                 # Utilitaires communs
│   │   ├── HashidsService.php  # Encodage/décodage d'ID
│   │   ├── SnowflakeService.php# Génération d'ID Snowflake
│   │   └── EncryptionService.php # Chiffrement/déchiffrement des données + masquage
│   ├── middleware/             # Middleware
│   │   ├── Cors.php            # Cross-origin
│   │   ├── SecurityFilter.php  # Interception des attaques (limitation des méthodes HTTP/XSS/injection SQL/traversée de chemin/injection de commandes/CSRF)
│   │   ├── RateLimit.php       # Rate-limit Redis (fenêtre glissante + en-têtes de réponse)
│   │   ├── ApiVersion.php      # Validation de la version API
│   │   ├── AdminAuth.php       # Authentification JWT + liste noire
│   │   ├── AdminPermission.php # Vérification des permissions RBAC
│   │   └── OperationLog.php    # Enregistrement automatique des journaux d'opérations (avec détection du canal d'origine)
│   └── model/                  # Modèles de données
├── apps/
│   ├── flutter/                # Administration Flutter Web (style PC)
│   │   └── lib/app/
│   │       ├── pages/          # Pages complètes (tableau de bord/utilisateurs/rôles/configuration/journaux/espace personnel)
│   │       ├── services/       # ApiService (intercepteur JWT) + AuthService (persistance du token)
│   │       └── layouts/        # Mise en page d'administration réactive (barre latérale + barre supérieure + zone de contenu)
│   └── harmonyos/              # Client natif HarmonyOS (rafraîchissement de token transparent)
├── config/                     # Fichiers de configuration (commentaires chinois inclus)
│   ├── route.php               # Routes + stratégie de versions API
│   ├── middleware.php           # Enregistrement des middleware globaux
│   └── ...                     # Configurations des composants
├── install/        # Fichiers de migration SQL (données de seed des permissions incluses)
├── public/                     # Point d'entrée public
├── runtime/                    # Fichiers d'exécution
└── vendor/                     # Dépendances Composer
```

## Prérequis d'environnement

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (développement frontend uniquement)
- Elasticsearch >= 7.x (optionnel, requis pour la recherche)

## Démarrage rapide

### 1. Installer les dépendances

```bash
composer install
```

### 2. Configurer les variables d'environnement

Copier et modifier les variables d'environnement (optionnel, sinon les valeurs par défaut de `config/*.php` sont utilisées) :

```bash
cp .env.example .env
```

Options de configuration clés :

| Variable d'environnement | Description | Valeur par défaut |
|---------|------|--------|
| `JWT_SECRET` | Clé de signature JWT | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Sel Hashids | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | Clé de chiffrement API | Valeur par défaut 32 octets |
| `SNOWFLAKE_DATACENTER_ID` | ID du centre de données (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID du nœud de travail (0-31) | `1` |
| `SCOUT_HOSTS` | Adresse ES | `http://localhost:9200` |

**En production, remplacez impérativement toutes les clés par des chaînes aléatoires.**

### 3. Initialiser la base de données

Exécuter dans l'ordre les fichiers SQL de `install/` :

```bash
mysql -u root -p < install/install.sql
```

### 4. Démarrer le service

```bash
php start.php start
```

Écoute par défaut sur `http://0.0.0.0:8787`.

### 5. Démarrer le frontend (optionnel)

**Administration Flutter (Web) :**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (style PC administration)
```

**Client HarmonyOS (mobile) :**

Ouvrir le répertoire `apps/harmonyos/` avec DevEco Studio, exécuter sur un appareil réel ou un émulateur.

### 6. Déploiement Docker Compose en un clic (recommandé en production)

Le projet fournit une orchestration Docker complète avec 5 services : Nginx, PHP (application webman), MySQL, Redis, Elasticsearch.

```bash
# 1. Configurer les variables d'environnement Docker
cp .env.docker .env

# 2. Démarrer tous les services
docker-compose up -d

# 3. Initialiser la base de données (exécuter dans le conteneur app)
docker-compose exec app mysql -h mysql -u root -p < install/install.sql

# 4. Accès
# http://localhost:8787  (webman)
# http://localhost:8080  (reverse proxy Nginx)
```

- `Dockerfile` : PHP 8.3 + OPcache + Composer, basé sur `php:8.3-cli`
- `docker-compose.yml` : orchestration de 5 services, isolation réseau, persistance des volumes de données
- `.env.docker` : variables d'environnement dédiées Docker

## Normes de base de données

- **Préfixe de tables** : `game_`
- **Clés primaires** : toutes les tables ont `id BIGINT UNSIGNED NOT NULL` comme clé primaire, **AUTO_INCREMENT interdit**
- **Génération d'ID** : les clés primaires sont générées par `SnowflakeService::generate()` au niveau application, uniques en environnement distribué
- **Champs obligatoires** : chaque table doit contenir `id`, `created_at`, `updated_at`
- **Suppression douce** : les tables concernées ajoutent `deleted_at DATETIME DEFAULT NULL`
- **Champs sensibles** : numéros de téléphone, e-mails, numéros d'identité, etc. chiffrés/déchiffrés automatiquement via le plugin `encryptable`, stockés en `VARCHAR(500)` dans la base

## Normes API

### Format de réponse unifié

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Codes d'erreur métier

| Code d'erreur | Signification | Description |
|-------|------|------|
| `0` | Succès | |
| `400` | Erreur de paramètres de requête | |
| `401` | Non connecté (token invalide ou expiré) | |
| `403` | Sans permission / interception de sécurité | Échec d'autorisation RBAC / détection d'attaque SecurityFilter |
| `404` | Ressource introuvable | |
| `422` | Échec de validation des paramètres | |
| `413` | Corps de requête trop volumineux | Déclenchement de SecurityFilter, dépasse 10 Mo |
| `405` | Méthode de requête non autorisée | Déclenchement de SecurityFilter, seuls GET/POST/PUT/DELETE/OPTIONS/HEAD autorisés |
| `415` | Type de média non pris en charge | Déclenchement de SecurityFilter, Content-Type non JSON |
| `429` | Requêtes trop fréquentes | Déclenchement de RateLimit / verrouillage de compte (5 échecs de connexion = verrouillage 15 minutes) |
| `500` | Erreur interne du serveur | |

### Traitement des ID

- **ID dans les requêtes/réponses** : chiffrés en chaînes via hashids, les ID de base réels ne sont pas exposés
- **Chemins d'API** : `GET /admin/user/{hashid}` — le `{id}` du chemin est une chaîne hashid
- **Stockage en base** : valeur BIGINT d'origine, générée par snowflake

### Versions API

La version API est contrôlée par l'en-tête de requête, **pas présente dans l'URL** :

```http
API-Version: v1
```

- Sans version fournie, `v1` est utilisé par défaut
- Version non prise en charge → `400 Bad Request`
- Pour ajouter une version, il suffit de créer le répertoire `app/api/{version}/controller/` et d'enregistrer la version dans le middleware

### Rate-limit

Basé sur l'algorithme de fenêtre glissante Redis, 60 requêtes/minute/IP/route par défaut. Les interfaces sensibles sont plus strictes :
- Connexion : 10 requêtes/minute
- Inscription : 5 requêtes/minute

Les en-têtes de réponse contiennent `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. En cas de dépassement, renvoie 429 avec `Retry-After`.

### Architecture des middleware

Les middleware globaux s'appliquent à toutes les requêtes, dans l'ordre :

```
Cors (prétraitement cross-origin + en-têtes de réponse)
  → SecurityFilter (limitation des méthodes HTTP/taille du corps/validation Content-Type/XSS/injection SQL/traversée de chemin/injection de commandes/interception CSRF)
  → RateLimit (rate-limit par fenêtre glissante Redis + verrouillage de compte : 5 échecs de connexion = verrouillage 15 minutes)
  → ApiVersion (validation de la version API, groupe de routes /api)
  → AdminAuth (authentification JWT + liste noire, groupe de routes /admin)
  → AdminPermission (autorisation RBAC, groupe de routes /admin)
  → OperationLog (enregistrement automatique des POST/PUT/DELETE, avec détection du canal d'origine, groupe de routes /admin)
```

`/health` et `/api/docs` sont des points publics, ne passant que par `Cors → SecurityFilter → RateLimit`.

Renforcements de sécurité :
- **Verrouillage de compte** : 5 échecs de connexion consécutifs → verrouillage automatique 15 minutes, connexion refusée (429) pendant la période
- **Limite de sessions concurrentes** : 3 tokens valides maximum par utilisateur, le token le plus ancien est ajouté à la liste noire au-delà
- **security.txt** : `GET /.well-known/security.txt` fournit les informations de contact de sécurité standard RFC 9116
- **Configuration de sécurité Nginx** : voir `docs/nginx-security.conf` pour un exemple complet de durcissement du reverse proxy

### Authentification

La connexion et l'inscription nécessitent d'abord le captcha à clic :

1. Le client demande `POST /api/captcha/generate` pour obtenir l'image du captcha (PNG base64) et la liste des textes cibles
2. L'utilisateur clique dans l'ordre sur les positions des textes correspondants, collecte des coordonnées de clic `[{x, y}, ...]`
3. À la connexion, soumettre `captcha_key` et `clicks` ; le serveur valide d'abord le captcha puis les identifiants

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

Les interfaces suivantes de l'administration nécessitent l'authentification JWT :

```http
Authorization: Bearer <token>
```

Après connexion réussie, renvoie access_token (validité 2 heures) et refresh_token (validité 14 jours).

À la déconnexion, le token est ajouté à la liste noire Redis, inutilisable pendant sa période de validité. POST /admin/profile/logout

### Confirmation secondaire des opérations sensibles

Les opérations sensibles (suppression d'utilisateurs, de rôles, de permissions, etc.) nécessitent de transmettre le `password` de l'utilisateur connecté dans le corps de la requête pour confirmer l'identité :

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## Liste des API

> Toutes les interfaces `/api/*` doivent porter l'en-tête `API-Version: v1` (v1 par défaut sinon).

### Interfaces publiques

| Méthode | Chemin | Description |
|-----|------|------|
| `GET` | `/health` | Health check (état DB/Redis/ES) |
| `GET` | `/api/docs` | Documentation de la spécification OpenAPI 3.0 |
| `POST` | `/api/captcha/generate` | Générer un captcha à clic |
| `POST` | `/api/captcha/verify` | Valider le captcha à clic |
| `POST` | `/api/auth/login` | Connexion (requiert le captcha) |
| `POST` | `/api/auth/register` | Inscription (requiert le captcha) |
| `POST` | `/api/auth/refresh` | Rafraîchir le jeton |
| `GET` | `/metrics` | Métriques de monitoring Prometheus |

### Interfaces d'administration (JWT + RBAC requis)

| Méthode | Chemin | Description |
|-----|------|------|
| `GET` | `/admin/dashboard` | Données du tableau de bord (cache Redis 5 minutes) |
| `GET` | `/admin/user` | Liste des utilisateurs (pagination + recherche) |
| `POST` | `/admin/user` | Créer un utilisateur |
| `GET` | `/admin/user/{id}` | Détails d'un utilisateur |
| `PUT` | `/admin/user/{id}` | Mettre à jour un utilisateur |
| `DELETE` | `/admin/user/{id}` | Supprimer un utilisateur (suppression douce, confirmation par mot de passe requise) |
| `POST` | `/admin/user/batch/destroy` | Suppression par lots (confirmation par mot de passe requise) |
| `POST` | `/admin/user/batch/status` | Activation/désactivation par lots |
| `GET` | `/admin/role` | Liste des rôles |
| `POST` | `/admin/role` | Créer un rôle |
| `PUT` | `/admin/role/{id}` | Mettre à jour un rôle |
| `DELETE` | `/admin/role/{id}` | Supprimer un rôle (confirmation par mot de passe requise) |
| `GET` | `/admin/permission` | Arbre des permissions |
| `POST` | `/admin/permission` | Créer une permission |
| `PUT` | `/admin/permission/{id}` | Mettre à jour une permission |
| `DELETE` | `/admin/permission/{id}` | Supprimer une permission (sous-permissions en cascade, confirmation par mot de passe requise) |
| `GET` | `/admin/config` | Liste de la configuration système |
| `POST` | `/admin/config` | Créer un élément de configuration |
| `PUT` | `/admin/config/{id}` | Mettre à jour un élément de configuration |
| `DELETE` | `/admin/config/{id}` | Supprimer un élément de configuration (confirmation par mot de passe requise) |
| `GET` | `/admin/log` | Journaux d'opérations (pagination + filtres) |
| `PUT` | `/admin/profile` | Mettre à jour les informations personnelles |
| `PUT` | `/admin/profile/password` | Modifier le mot de passe |
| `POST` | `/admin/profile/logout` | Déconnexion (liste noire JWT) |
| `POST` | `/admin/export/excel` | Exporter en Excel |
| `POST` | `/admin/export/pdf` | Exporter en PDF |
| `POST` | `/admin/import/users` | Import Excel d'utilisateurs |
| `POST` | `/admin/upload` | Upload de fichiers (images/documents, 10 Mo maximum) |

## Frontend

### Administration Flutter (style PC)

- **Mise en page** : barre latérale (repliable 64px/240px) + barre supérieure + zone de contenu, trois points de rupture réactifs (mobile/tablette/desktop)
- **Pages** : connexion, tableau de bord, gestion des utilisateurs, rôles et permissions, configuration système, journaux d'opérations, espace personnel
- **Gestion d'état** : GetX (`ApiService` singleton + persistance du token `AuthService`)
- **Tableau de bord** : cartes statistiques, courbe de tendance (fl_chart), camembert, journaux d'opérations récents
- **Export** : export Excel/PDF, le PDF contient des informations de copyright inamovibles
- **Opérations par lots** : suppression par lots sur multi-sélection, activation/désactivation par lots
- **Thème** : double thème Material 3 clair/sombre

### Mobile HarmonyOS

- **Pages** : connexion, tableau de bord, liste/détails des utilisateurs, espace personnel
- **Authentification** : JWT Bearer + rafraîchissement de token transparent automatique au 401, redirection automatique vers la page de connexion en cas d'échec du rafraîchissement
- **Stockage** : token géré via AppStorage

## Normes de développement

- Pas de `\` préfixe pour les références de fonctions/classes globales, utiliser uniformément `use` pour les imports
- Tous les fichiers PHP doivent comporter la déclaration de copyright en en-tête
- Tous les fichiers de configuration doivent contenir des commentaires chinois explicatifs
- Les clés primaires de base doivent être générées par snowflake au niveau application, l'auto-incrémentation est interdite
- Tous les ID des paramètres et réponses de la couche API doivent passer par le chiffrement/déchiffrement hashids
- Le middleware AdminPermission utilise le cache Redis pour les permissions utilisateur (TTL=60 s), élimine le goulot des requêtes N+1

## Déploiement

### Docker Compose (recommandé)

Le `docker-compose.yml` à la racine du projet orchestre 5 services :

| Service | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | construit via le `Dockerfile` local | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

L'image PHP est construite via le `Dockerfile`, image de base `php:8.3-cli`, OPcache activé.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

Pipeline d'intégration continue GitHub Actions : `.github/workflows/ci.yml`

- Vérification de la syntaxe PHP (`php -l`)
- Tests unitaires PHPUnit
- Analyse statique Flutter (`flutter analyze`)

### Sauvegarde de la base

Répertoire `database/backup/` :

- `backup.sh` — sauvegarde mysqldump + gzip, nettoyage automatique des sauvegardes de plus de 30 jours
- `restore.sh` — restauration interactive, liste les sauvegardes disponibles

### Configuration de sécurité Nginx

Pour la production, se référer à `docs/nginx-security.conf` pour le durcissement du reverse proxy.

## L'open source ne se fait pas sans vous, soutenez-nous

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](./docs/weixinpay.png "WeChat") | ![Alipay](./docs/alipay.png "Alipay") |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

