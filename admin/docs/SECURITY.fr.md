# Document de conception de l'architecture de sécurité
<!-- lang-nav -->

Languages: [中文](SECURITY.md) · [English](SECURITY.en.md) · [한국어](SECURITY.ko.md) · [Русский](SECURITY.ru.md) · [Deutsch](SECURITY.de.md) · **Français** · [Español](SECURITY.es.md) · [Português](SECURITY.pt.md) · [हिन्दी](SECURITY.hi.md) · [العربية](SECURITY.ar.md) · [বাংলা](SECURITY.bn.md) · [Bahasa Indonesia](SECURITY.id.md) · [日本語](SECURITY.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Vue d'ensemble de la défense en profondeur

Le système adopte un modèle de défense en profondeur à 7 couches, filtrant les requêtes malveillantes de l'extérieur vers l'intérieur, en garantissant que si une couche échoue, les suivantes prennent le relais.

Toute la chaîne de middleware s'exécute dans l'ordre suivant (voir `config/middleware.php`) :

```
Requête → Cors → SecurityFilter → RateLimit → [Middleware du groupe de routes : AdminAuth → AdminPermission → OperationLog] → Controller
```

| Couche | Middleware/mécanisme | Cible de protection |
|----|--------|---------|
| 1 | SecurityFilter | Interception des attaques XSS / injection SQL / traversée de chemin / injection de commandes / CSRF |
| 2 | Cors | Sécurité cross-origin + injection des en-têtes de sécurité de réponse |
| 3 | RateLimit | Rate-limit par fenêtre glissante Redis, anti-force brute |
| 4 | AdminAuth | Authentification JWT + déconnexion par liste noire |
| 5 | AdminPermission | Autorisation RBAC à la granularité method.path |
| 6 | OperationLog | Audit des opérations + traçage du canal d'origine |
| 7 | Chiffrement des données | Obscurcissement des ID Hashids + chiffrement DB Encryptable + chiffrement du transport EncryptionService |

Le frontend (Flutter) dispose en outre de ses propres validations d'entrée indépendantes ; le backend ne fait pas confiance, chaque couche se défend indépendamment.

---

## 2. Moteur de détection d'attaques

### 2.0 Limitation des méthodes HTTP

SecurityFilter valide d'abord la méthode HTTP avant toute détection d'attaque, seules les méthodes standard suivantes sont autorisées :

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

Les méthodes non standard (TRACE, CONNECT, PATCH, méthodes personnalisées, etc.) renvoient directement **405 Method Not Allowed**, corps HTML vide, sans entrer dans la détection d'attaques ni la logique métier.

C'est la première ligne de la défense en profondeur, bloquant efficacement :
- Les attaques de traversée de site TRACE (XST)
- L'abus de proxy tunnel CONNECT
- Le sondage de méthodes WebDAV non standard
- L'énumération de méthodes HTTP par les scanners automatisés

### 2.1 XSS — script inter-sites

Toutes les expressions régulières proviennent de `SecurityFilter::PATTERNS['XSS']`, correspondance insensible à la casse.

| Motif de détection | Expression régulière | Attaque défendue |
|----------|------|-----------|
| Balises script | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` et variantes avec espaces |
| Attributs d'événement | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | `onclick="javascript:..."` et autres événements inline |
| Protocole JS pseudo | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` etc. |
| XSS Data URI | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` etc. |
| Injection de templates | `\{\{.*?\}\}` | `{{constructor}}`, `{{7*7}}` et autres injections de templates serveur/Angular/Vue |

### 2.2 Injection SQL

| Motif de détection | Expression régulière | Attaque défendue |
|----------|------|-----------|
| Requête jointe UNION | `\bUNION\s+(?:ALL\s+)?SELECT\b` | `UNION SELECT`, `UNION ALL SELECT` pour vol de tables |
| Injection OR toujours vraie | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| Destruction de structure | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| Appel de procédures stockées | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | Exécution de commandes via les procédures stockées étendues MSSQL |
| Sondage de métadonnées | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | Sondage des structures MySQL/PG/SQLite/MSSQL |
| Contournement par commentaire | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | Contournement par commentaire `'-- OR SELECT`, `'# AND UPDATE` |

### 2.3 Traversée de chemin

| Motif de détection | Expression régulière | Attaque défendue |
|----------|------|-----------|
| Retour de répertoire | `\.\.[\/\\\\]{2,}` | `../`, `..\`, `....//` traversées multi-niveaux |
| Sondage de fichiers sensibles | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` etc. |
| Troncature par octet nul | `%00` | `../../../etc/passwd%00.jpg` contourne la validation d'extension |

### 2.4 Injection de commandes

| Motif de détection | Expression régulière | Attaque défendue |
|----------|------|-----------|
| Commandes par pipe/point-virgule | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| Substitution par backtick | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| Substitution $() | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| Téléchargement distant par pipe | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF — falsification de requête inter-sites

La logique de validation est implémentée dans `SecurityFilter::checkCsrf()` :

```php
// Seuls POST/PUT/DELETE déclenchent la validation
// Origin et Referer tous deux vides → laisser passer (client non navigateur)
// Origin non vide → analyser le domaine Origin et le comparer au Host
```

Règles de comparaison :
- Supprimer le préfixe `www.` du Host puis comparer exactement avec le domaine d'Origin
- Si le Host est un domaine parent d'Origin (ex. `Origin: app.example.com`, `Host: example.com` — déclenche `str_contains($originHost, '.' . $hostOnly)`), laisser passer
- Ni correspondance exacte ni sous-domaine → renvoyer 403, jugé comme attaque CSRF

Note : les clients non navigateurs (ex. curl sans Origin/Referer) passent directement ; la protection CSRF n'est efficace que dans un environnement navigateur.

### 2.6 Upload de fichiers malveillants

| Motif de détection | Expression régulière | Attaque défendue |
|----------|------|-----------|
| Double extension déguisée | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` contournent la liste blanche |
| Extension PHP | `\.php\s*$/m` | Passage direct d'un chemin `.php` dans les paramètres de requête |

---

## 3. Escalade d'attaque et liste noire IP

SecurityFilter intègre un mécanisme d'escalade d'attaque pour empêcher un même IP de scanner en continu.

### Flux d'escalade

```
1ère détection → Redis INCR security_escalate:{ip} = 1, TTL=60s
2ème détection → INCR → 2
...
5ème détection → INCR → 5
    → déclenche le bannissement : SETEX security_ban:{ip} 900 1
    → efface le compteur : DEL security_escalate:{ip}
    → écrit le journal de sécurité : [SECURITY] IP banned 15min
```

### Comportement pendant le bannissement

Chaque requête vérifie d'abord `isBanned()` en entrant dans SecurityFilter :

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

Un IP banni voit toutes ses requêtes (y compris légitimes) renvoyer directement 403 pendant 15 minutes, la logique métier suivante est entièrement contournée.

### Constantes de configuration

| Constante | Valeur | Signification |
|------|-----|------|
| ESCALATE_LIMIT | 5 | Seuil de déclenchements dans la fenêtre de 60 s |
| ESCALATE_WINDOW | 60 | Fenêtre du compteur (secondes) |
| BAN_DURATION | 900 | Durée du bannissement (secondes), soit 15 minutes |

### Journal de sécurité

Emplacement du fichier : `runtime/logs/security.log`

Exemple de format de journal :
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/v1/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### Limitation de la taille du corps de requête

`Content-Length > 10MB` renvoie directement 413 Payload Too Large, anti-DoS par corps de requête géant.

### Validation du Content-Type

Les requêtes POST/PUT **doivent** déclarer un `Content-Type` `application/json` ou `application/x-www-form-urlencoded`, sinon renvoie 415 Unsupported Media Type. Les requêtes d'upload de fichiers (avec champ file) contournent cette vérification.

---

## 4. En-têtes de sécurité de réponse

Tous les en-têtes sont injectés dans le middleware `Cors`, via `$response->withHeaders()` sur chaque réponse.

| En-tête | Valeur | Rôle |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Autorise le cross-origin de toute origine (scénario d'administration en réseau interne) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Ensemble de méthodes autorisées |
| Access-Control-Allow-Headers | `Authorization,Content-Type` | En-têtes personnalisés autorisés |
| Access-Control-Max-Age | `86400` | Cache des requêtes de préflight 24 h |
| X-Content-Type-Options | `nosniff` | Interdit le sniffing MIME du navigateur |
| X-Frame-Options | `DENY` | Interdit tout iframe, anti-clickjacking |
| X-XSS-Protection | `1; mode=block` | Active le filtre XSS intégré du navigateur et bloque le rendu de la page |
| Referrer-Policy | `strict-origin-when-cross-origin` | URL complète en même origine, domaine seul en cross-origin |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Désactive caméra/micro/localisation sur tout le site |

Les requêtes de préflight OPTIONS renvoient directement une réponse vide 204, sans entrer dans la chaîne de middleware suivante.

### 4.2 Content-Security-Policy (CSP)

Injectée avec les autres en-têtes de sécurité dans le middleware Cors, fournit une défense en profondeur en limitant les sources de ressources que le navigateur peut charger et exécuter.

| En-tête | Valeur | Rôle |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Limite les sources des scripts/styles/images/connexions/frames/formulaires etc. |
| X-Permitted-Cross-Domain-Policies | `none` | Interdit le chargement de fichiers de politiques cross-domain Adobe Flash/PDF |

Points clés de la politique CSP :
- `default-src 'self'` : seules les ressources de même origine par défaut
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'` : scripts de même origine + scripts inline (nécessaire pour Flutter Web) + eval (nécessaire pour le débogage Flutter Web)
- `frame-ancestors 'none'` : interdit l'iframe par toute page, double sécurité avec X-Frame-Options: DENY
- `base-uri 'self'` : la balise `<base>` ne peut pointer que vers la même origine
- `form-action 'self'` : les formulaires ne peuvent être soumis que vers la même origine

---

## 5. Stratégie de rate-limit

### Algorithme

Fenêtre glissante Redis Sorted Set + script Lua atomique, opérations clés :

```lua
-- 1. nettoyer les anciennes entrées hors fenêtre
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. vérifier le comptage de la fenêtre courante
local count = redis.call('ZCARD', KEYS[1])
-- 3. dépassement → renvoyer {0, count}, sinon ZADD et renvoyer {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- suffixe aléatoire pour éviter les écrasements à la même milliseconde
redis.call('EXPIRE', KEYS[1], window + 10)
```

Le script Lua s'exécute en monothread côté serveur Redis, **naturellement atomique**, éliminant la course TOCTOU (Time-of-check to Time-of-use).

### Configuration du rate-limit

| Route | Limite | Fenêtre | Scénario |
|------|------|------|------|
| Défaut (toutes les routes) | 60 requêtes/minute | 60s | API générale |
| `/api/v1/auth/login` | 10 requêtes/minute | 60s | Connexion (anti-force brute) |
| `/api/v1/auth/register` | 5 requêtes/minute | 60s | Inscription (anti-inscription en masse) |

### En-têtes de réponse

Au déclenchement du rate-limit, renvoie HTTP 429 avec un corps JSON :
```json
{"code": 429, "message": "Requêtes trop fréquentes, réessayez plus tard", "data": []}
```

Toutes les réponses (y compris normales) portent les en-têtes suivants :

| En-tête | Description |
|----|------|
| X-RateLimit-Limit | Nombre maximum de requêtes autorisées dans la fenêtre courante |
| X-RateLimit-Remaining | Requêtes encore disponibles dans la fenêtre courante |
| X-RateLimit-Reset | Horodatage Unix de réinitialisation de la fenêtre |
| Retry-After | Présent uniquement en cas de rate-limit, secondes à attendre recommandées |

### Stratégie de dégradation

En cas d'anomalie Redis (timeout de connexion, indisponibilité, etc.) : **fail-closed** :

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    // Redis down : le rate-limit indisponible équivaut à un refus, pour éviter que la ligne de défense soit inopérante (login/callback de paiement)
    return json(['code' => 503, 'message' => 'Service temporairement indisponible, réessayez plus tard', 'data' => []])
        ->withStatus(503)->withHeaders(['Retry-After' => '5']);
}
```

Le rate-limit est la première ligne de défense anti-force brute à la connexion et anti-rejeu des callbacks de paiement : en cas de panne Redis, on préfère refuser la requête (503) plutôt que de laisser passer.

### 5.4 Mécanisme de verrouillage de compte

En complément du rate-limit, l'interface de connexion ajoute un mécanisme de **verrouillage de compte** contre la force brute ciblée sur un utilisateur précis.

**Flux de verrouillage** :

```
Échec de connexion → Redis INCR account_lockout:{userId} TTL=900s
5 échecs consécutifs → Redis SETEX account_locked:{userId} 900 1
            → renvoie 429 "Compte verrouillé, réessayez dans 15 minutes"
            → efface le compteur : DEL account_lockout:{userId}
```

**Comportement pendant le verrouillage** :

Toutes les requêtes de connexion renvoient directement 429 pendant le verrouillage, sans validation du mot de passe, bloquant totalement les tentatives de force brute.

**Constantes de configuration** :

| Constante | Valeur | Signification |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Nombre maximum d'échecs consécutifs |
| LOCKOUT_DURATION | 900 | Durée du verrouillage (secondes), soit 15 minutes |

Note : le verrouillage de compte repose sur `userId` et non sur l'IP, donc un attaquant qui change d'IP ne contourne pas le verrouillage. Combiné au rate-limit IP (10/minute), double protection :
- Niveau IP : le rate-limit de 10/minute bloque la force brute distribuée
- Niveau compte : le verrouillage après 5 échecs bloque la force brute ciblée

---

## 6. Authentification et autorisation

### 6.1 Authentification JWT

Implémentée par le middleware AdminAuth, montée sur les groupes de routes nécessitant l'authentification.

**Configuration des paramètres** (`config/plugin/erikwang2013/jwt/jwt`, injectée par `.env`) :

| Paramètre | Valeur | Description |
|------|-----|------|
| Algorithme | HS256 | Signature symétrique HMAC-SHA256 |
| Clé | `JWT_SECRET_KEY` | Injectée par variable d'environnement, **refus de démarrage** (fail-closed) si manquante ou valeur par défaut |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14j) | `JWT_REFRESH_TTL` |
| Émetteur | `open-admin` | `JWT_ISSUER` |
| Audience | `open-admin` | `JWT_AUDIENCE` |

**Extraction du token** : depuis l'en-tête `Authorization: Bearer <token>`, suppression du préfixe `Bearer ` pour obtenir le JWT brut.

**Flux d'authentification** :
1. Token vide → 401 direct `{"code": 401, "message": "Non connecté"}`
2. Vérification de la liste noire Redis `jwt_blacklist:{md5(token)}` → correspondance → 401 `Token invalide, reconnectez-vous`
3. Décodage JWT → échec (expiré/signature non conforme) → 401 `Token expiré ou invalide`
4. Succès → injection de `$request->adminId` et `$request->adminUsername`

**Mécanisme de liste noire** : à la déconnexion, `md5(token)` est écrit dans Redis avec un TTL égal à la durée de validité restante du JWT. En cas de panne Redis, la vérification de la liste noire est contournée (fail-open) ; le token déconnecté reste alors utilisable à court terme, mais la courte durée de validité du JWT (2h) sert de protection de secours.

**Rafraîchissement du token** : `POST /api/v1/auth/refresh` ne réémet qu'après validation de l'ancien refresh token (`token_type=refresh` non expiré, non banni) et vérifie que `sub` est bien un ID utilisateur valide — **plus aucun refresh token avec sub=null n'est émis**, un échec de rafraîchissement renvoie directement 401.

### 6.2 Limitation des sessions concurrentes

Pour empêcher l'abus multi-appareils après une fuite de token, le système limite le nombre de tokens valides détenus simultanément par un même utilisateur.

**Logique de limitation** :

```
Connexion réussie → émission du nouveau token
         → consultation du nombre de tokens valides de l'utilisateur : Redis SCARD user_tokens:{userId}
         → si le nombre >= 3 (MAX_CONCURRENT_SESSIONS):
            → trier par date de création croissante, retirer le token le plus ancien :
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → ajouter le nouveau token à l'ensemble : Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Constantes de configuration** :

| Constante | Valeur | Signification |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Nombre maximum de tokens concurrents par utilisateur |

**Scénario de déconnexion forcée** : quand l'utilisateur se connecte sur un 4e appareil, le token du 1er appareil est forcé en liste noire ; les requêtes suivantes renvoient 401 « Token invalide, reconnectez-vous ».

À la déconnexion, le token courant est retiré de l'ensemble. À l'expiration naturelle du token, la clé Redis expire automatiquement et le membre de l'ensemble diminue.

### 6.3 Modèle de permissions RBAC

Implémenté par le middleware AdminPermission.

**Modèle de données** : association à trois niveaux User -> Role -> Permission

- `game_admin_user` (table utilisateurs)
- `game_admin_user_role` (table d'association user-rôle)
- `game_admin_role` (table rôles)
- `game_admin_role_permission` (table d'association rôle-permission)
- `game_admin_permission` (table permissions)

**Types de permissions** :
| type | Signification | Exemple |
|------|------|------|
| 1 | Permission de menu | Contrôle la visibilité de la navigation gauche |
| 2 | Permission de bouton | Contrôle les boutons d'opération dans la page (créer/éditer/supprimer) |
| 3 | Permission API | Contrôle l'appel des interfaces backend |

Format de l'identifiant de permission API : `{method}.{path}`

Par exemple :
- `post.admin/user` — créer un utilisateur
- `put.admin/user` — éditer un utilisateur
- `delete.admin/user` — supprimer un utilisateur
- `get.admin/user` — consulter la liste des utilisateurs

**Flux d'autorisation** :
1. `$request->adminId` vide (non connecté) → 401 direct `{"code": 401, "message": "Non connecté"}`, plus aucune autorisation
2. Récupérer l'utilisateur → rôles (en sautant les rôles désactivés `status=0`) → liste des permissions
3. Super administrateur (`slug = '*'`) → autorisation directe
4. Construire `strtolower(method) . '.' . trim(path, '/')` → comparer avec la liste des permissions
5. Pas de correspondance → 403 `{"code": 403, "message": "Accès sans permission"}`

**Confirmation secondaire** : BaseController fournit `confirmPassword()` ; les opérations sensibles (suppression d'utilisateurs, export de données, etc.) exigent en plus, au niveau Controller, la saisie du mot de passe courant, pour prévenir les opérations non autorisées après un vol de session.

### 6.4 Vérification de signature des callbacks de paiement (fail-closed)

La vérification de signature de `POST /api/v1/payment/callback` (callbacks de recharge Stripe/PayPal) est **fail-closed** : toute configuration manquante ou anomalie de validation refuse le callback :

| Scénario | Comportement |
|------|------|
| Stripe sans `STRIPE_WEBHOOK_SECRET` configuré | Refus (403), plus aucun callback non signé accepté |
| Signature Stripe manquante / échec de vérification | Refus (403) |
| Horodatage Stripe `t=` manquant ou écart avec l'heure serveur **> ±5 minutes** | Refus (403), anti-rejeu |
| PayPal sans `PAYPAL_WEBHOOK_ID` configuré | Refus (403) |
| Vérification de rappel PayPal anormale / non SUCCESS | Refus (403) |
| Après configuration optionnelle de `CALLBACK_TRUSTED_IPS`, IP source hors liste blanche | Refus (403) |
| Provider du callback différent du mode de paiement de la commande / mode de paiement inexistant | Refus (403) |

Le crédit du callback (mise à jour d'état + solde + flux) est effectué dans la même transaction de base de données ; tout échec d'une étape annule l'ensemble, empêchant les crédits partiels.

---

## 7. Journaux d'audit

### 7.1 Journaux d'opérations

Le middleware OperationLog enregistre automatiquement les journaux des requêtes POST / PUT / DELETE. Les requêtes GET ne sont pas enregistrées.

**Champs enregistrés** :

| Champ | Source | Description |
|------|------|------|
| id | SnowflakeService::generate() | ID globalement unique |
| user_id | `$request->adminId` | ID de l'opérateur, 0 si non connecté |
| action | `$request->method()` | Équivalent de method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Chemin de la requête |
| ip | `$request->getRealIp()` | IP réelle du client |
| source | detectSource() | Plateforme d'origine du client |
| input | Corps de la requête (JSON après masquage) | Données soumises par l'opération |
| created_at | `date('Y-m-d H:i:s')` | Heure de l'opération |

**Filtrage des champs sensibles** : parcours récursif du corps de requête, les valeurs des champs suivants sont remplacées par `***` :

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Détection du canal d'origine** (`detectSource()`) : par ordre de priorité :

1. Lire d'abord l'en-tête personnalisé `X-Client-Platform` (déclaration explicite des clients natifs)
2. Sinon, déduire de la chaîne User-Agent (ordre de détection de la méthode `detectSource()`) :

| Plateforme | Mots-clés UA |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Valeur par défaut de repli |

**Tolérance aux pannes** : une anomalie d'écriture du journal ne bloque pas la requête métier (`catch (\Throwable)` avalé silencieusement).

### 7.2 Journal de sécurité

**Emplacement du fichier** : `runtime/logs/security.log`

**Contenu enregistré** :
- Journaux d'interception d'attaques : catégorie d'attaque, IP, chemin, champ, source, extrait du payload (200 premiers caractères)
- Notifications de bannissement IP : IP banni, nombre de déclenchements

Les permissions d'écriture sont `FILE_APPEND | LOCK_EX`, garantissant l'écriture concurrente sécurisée.

---

## 8. Protection des données

Le système adopte une stratégie de protection des données à trois couches, correspondant aux trois étapes de circulation des données.

### 8.1 Couche de transport — EncryptionService

`EncryptionService` utilise le paquet `erikwang2013/encryption` pour chiffrer/déchiffrer les champs sensibles des requêtes/réponses API.

**Détails techniques** :
- Algorithme : `aes-256-cbc-hmac` (signature HMAC intégrée anti-falsification)
- Clé : variable d'environnement `ENCRYPTION_KEY`, alignée automatiquement sur 32 octets
- Usage : transport de champs comme téléphones, numéros d'identité entre le client et l'API

**Méthodes utilitaires de masquage** :
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (nom d'utilisateur > 2 caractères) ou `a**@example.com`

### 8.2 Couche de stockage — Encryptable Cast

Le modèle `AdminUser` utilise le cast Eloquent `Erikwang2013\Encryptable\Encryptable`, champs concernés :

- `email` → cast Encryptable, chiffrement/déchiffrement automatique
- `phone` → cast Encryptable, chiffrement/déchiffrement automatique
- `id_card` → cast Encryptable, chiffrement/déchiffrement automatique

À l'écriture en base, chiffrement automatique en texte chiffré ; à la lecture, déchiffrement automatique en clair. Le type de colonne de stockage est `VARCHAR(500)`, le texte chiffré est stocké en base64.

**Système de clés** : indépendant du chiffrement de transport (`ENCRYPTION_KEY`), utilise `ENCRYPTABLE_KEY` ; la fuite d'une clé ne neutralise pas l'autre couche.

Rotation des clés : la variable d'environnement `ENCRYPTION_PREVIOUS_KEYS` prend en charge une liste de clés historiques (séparées par des virgules) ; à la lecture des anciennes données, les clés historiques sont essayées pour déchiffrer, et la clé courante est utilisée pour re-chiffrer à l'écriture.

### 8.3 Couche d'affichage — obscurcissement des ID et masquage

**Obscurcissement des ID Hashids** : `HashidsService` utilise le paquet `erikwang2013/hashids`.

- Les ID BIGINT de base renvoyés par les API externes sont encodés en chaînes hash (ex. `xK3mN9qR2pL7wV8b`)
- Le client transmet la chaîne hash dans ses requêtes, le backend décode automatiquement vers l'ID d'origine
- Le sel `HASHIDS_SALT` est injecté par variable d'environnement ; des sels différents produisent des résultats d'encodage/décodage totalement différents
- Longueur minimale du hash : 16 caractères, jeu de 62 caractères alphanumériques
- BaseController fournit les méthodes pratiques `encodeId()`, `decodeId()`, `encodeIds()`

**Masquage à l'export** : lors des exports Excel/PDF (ExportController), les champs sensibles sont uniformément masqués :
- Téléphone : `138****1234`
- E-mail : `a***@example.com`
- Numéro d'identité : entièrement masqué en `********`

---

## 9. Gestion des clés

Toutes les clés sont injectées via les variables d'environnement `.env` ; les fichiers de configuration les lisent via `getenv()` avec des valeurs par défaut intégrées (sûres uniquement en développement).

| Variable d'environnement | Usage | Paquet | Exigence en production |
|----------|------|-----|---------|
| JWT_SECRET_KEY | Clé de signature JWT | erikwang2013/jwt-webman | Chaîne aléatoire de 64+ caractères ; refus de démarrage si manquante ou valeur par défaut |
| JWT_ALGORITHM | Algorithme de signature JWT | idem | Garder HS256 |
| HASHIDS_SALT | Sel d'encodage des ID | erikwang2013/hashids | Chaîne aléatoire |
| SNOWFLAKE_DATACENTER_ID | ID du centre de données (0-31) | erikwang2013/snowflake-php | Garder la valeur par défaut en centre de données unique |
| ENCRYPTION_KEY | Clé de chiffrement du transport API | erikwang2013/encryption | Chaîne aléatoire de 32 octets |
| ENCRYPTABLE_KEY | Clé de chiffrement du stockage DB | erikwang2013/encryptable | Chaîne aléatoire de 32 octets, différente de la clé de transport |

**Exigences de sécurité** :
- Le fichier `.env` est dans `.gitignore`, interdiction de le committer
- `.env.example` est un modèle public, sans clés réelles
- En production, **toutes** les clés par défaut doivent être remplacées par des chaînes aléatoires
- Recommandé : `openssl rand -base64 32` pour générer les clés

### Isolation du stockage des clés

| Couche | Clé de configuration | Variable d'environnement de la clé |
|----|--------|-------------|
| Chiffrement de transport | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Chiffrement de stockage | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| Obscurcissement des ID | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| Signature JWT | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET_KEY` |

---

## 10. security.txt (RFC 9116)

Le système fournit un point d'accès d'informations de contact de sécurité conforme à la norme RFC 9116 sur `/.well-known/security.txt`, permettant aux chercheurs en sécurité de trouver rapidement le canal de signalement lors de la découverte d'une vulnérabilité.

**Mode d'accès** :

```
GET /.well-known/security.txt
```

**Contenu de la réponse** :

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Description des champs** :

| Champ | Description |
|------|------|
| Contact | Coordonnées de signalement des vulnérabilités |
| Expires | Date d'expiration du fichier, à mettre à jour périodiquement |
| Preferred-Languages | Langues de communication préférées |
| Canonical | URL canonique de ce fichier |
| Policy | Lien vers la politique de sécurité/politique de divulgation des vulnérabilités |

Ce point d'accès n'est pas soumis aux middleware de rate-limit ou d'authentification ; tout le monde peut y accéder directement.

---

## 11. Configuration de sécurité Nginx

Le projet fournit `docs/nginx-security.conf` comme configuration de référence pour le durcissement du reverse proxy Nginx en production.

**Mesures de sécurité incluses** :

| Élément de configuration | Rôle |
|--------|------|
| `server_tokens off` | Masque le numéro de version Nginx |
| `client_max_body_size 10m` | Limite la taille du corps de requête, en coordination avec SecurityFilter |
| `limit_req_zone` | Limitation de fréquence des requêtes au niveau Nginx |
| `limit_conn_zone` | Limitation du nombre de connexions concurrentes |
| `add_header` en-têtes de sécurité | Ajout d'en-têtes de sécurité comme X-XSS-Protection au niveau Nginx |
| `if ($request_method)` | Refus des méthodes HTTP non standard au niveau Nginx |
| Configuration SSL/TLS | TLS 1.2/1.3 moderne, désactivation des suites de chiffrement faibles |
| Masquage des en-têtes backend | `proxy_hide_header` supprime les en-têtes sensibles comme la version webman |

**Mode d'emploi** : fusionner la configuration de `docs/nginx-security.conf` dans votre bloc server Nginx, en ajustant selon votre domaine réel et vos chemins de certificats.

---

## 12. Modèle de menaces

### 12.1 Menaces couvertes

| Type de menace | Vecteur d'attaque | Couches de défense |
|----------|---------|---------|
| Abus de méthodes HTTP | Attaques XST TRACE/TRACK, proxy tunnel CONNECT, sondage de méthodes WebDAV | Liste blanche 405 de SecurityFilter (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| Force brute ciblée | Tentatives de mot de passe répétées contre un utilisateur précis | Verrouillage de compte (5 échecs = 15 min) + RateLimit (login 10/min) + Captcha |
| Force brute | Tentatives distribuées multi-IP sur identifiants | RateLimit (login 10/min) + Captcha |
| XSS script inter-sites | `<script>`, onerror, javascript: | SecurityFilter (5 motifs) + en-tête X-XSS-Protection + CSP |
| Injection SQL | UNION SELECT, OR 1=1, contournement par commentaire | SecurityFilter (6 motifs) + requêtes paramétrées Eloquent ORM |
| CSRF falsification de requête inter-sites | Site malveillant émettant des requêtes à la place de l'utilisateur | Validation Origin/Referer de SecurityFilter |
| Traversée de chemin | `../../etc/passwd` | Motifs de traversée de SecurityFilter + liste blanche d'extensions UploadController |
| Injection de commandes | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 motifs) |
| Vol de session | Vol du token JWT | JWT à courte validité (2h) + déconnexion par liste noire + confirmation par mot de passe des opérations sensibles |
| Énumération d'ID | Parcourir les ID numériques pour deviner le volume de données | Obscurcissement Hashids en chaînes aléatoires |
| Fuite de données | Vol de base / homme du milieu / fuite de journaux | Triple chiffrement/masquage + filtrage des champs sensibles OperationLog |
| Attaque DoS | Corps de requête géant / requêtes à haute fréquence | Limite de 10 Mo du corps + RateLimit 60/min + liste noire IP |
| Élévation de privilèges | Utilisateur à faible privilège accédant aux interfaces d'administration | Autorisation RBAC à la granularité method.path |
| Attaque par upload de fichiers | shell.php.png à double extension | Détection de fichiers malveillants SecurityFilter |

### 12.2 Limites connues

| Limite | Impact | Mesure d'atténuation |
|------|---------|---------|
| La protection CSRF n'est efficace que pour les navigateurs | Les clients non navigateurs (curl, Postman, apps mobiles) contournent la vérification Origin/Referer | Les clients non navigateurs ne sont naturellement pas exposés au CSRF ; s'appuient sur l'authentification JWT en remplacement des cookies |
| Redis indisponible : rate-limit fail-closed (503), vérification de liste noire fail-open | Pendant la panne, une partie des requêtes refusées ; token déconnecté utilisable à court terme | Surveillance de la disponibilité Redis avec alertes ; courte validité JWT comme filet de sécurité |
| Pas de moteur WAF dédié | SecurityFilter utilise `@preg_match` par expressions régulières, pas un moteur de règles WAF dédié | En production, recommander ModSecurity Nginx en frontal ou un WAF Cloudflare |
| JWT sans état impossible à révoquer activement | Impossible de révoquer côté serveur un token non expiré (hors liste noire) | Liste noire + TTL court de 2h réduisant la fenêtre de risque |
| Liste noire IP en mémoire uniquement | Liste noire perdue après redémarrage Redis | Durée de bannissement de 15 min uniquement, impact limité |
| Pas de rate-limit spécial pour les points d'administration | Les interfaces admin partagent la limite par défaut de 60/min avec les interfaces normales | La fréquence d'opération des administrateurs est naturellement faible, pas besoin de distinction pour l'instant |
| `@preg_match` supprime les erreurs | Échec silencieux en cas d'entrée d'expression régulière malformée | `preg_last_error()` permet d'ajouter un monitoring, non implémenté pour l'instant |
