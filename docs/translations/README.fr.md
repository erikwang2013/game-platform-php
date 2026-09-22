# Plateforme de jeux mondiale (Global Game Platform)

## Mascotte du projet

<img src="../mascot.svg" width="120" alt="Dicey"/>

**Dicey** — Mascotte de la plateforme. Le dé représente les jeux et le gameplay basé sur la probabilité, la pièce l'économie de la plateforme et les passerelles de paiement multiples, et le violet reflète l'identité du panneau d'administration. Fichier SVG : `docs/mascot.svg`, redimensionnable à l'infini pour la documentation, les logos et les produits dérivés.
<!-- lang-nav -->

Languages: [中文](../../README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · **Français** · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Plateforme de jeux mondiale, universelle et internationalisée. Après inscription, l'utilisateur recharge des devises de la plateforme, joue et gagne des devises de jeu, qui peuvent être reconverties dans le portefeuille et retirées. Le backend propose une gestion complète des jeux, la validation des retraits, la gestion des utilisateurs et la gestion des paiements. Bascule multilingue (anglais/chinois).

## Stratégie de versions

| Version | Objectif | Statut |
|------|------|------|
| Version complète | Le tout : classements, coupons, catégories de jeux, configuration par pays, recherche ES | Terminée |
| Extension d'écosystème | v2.0 : intégration des providers de jeux, tickets, VIP, succès, social, bus d'événements | Terminée |
| v1.3.15-22 (8 versions) | Rapprochement/règlement, contrôle des risques approfondi, portefeuille unifié, moteur d'activités, anti-triche, croissance sociale, Adyen/GrabPay | Terminée |

## Pile technologique

### Backend
- PHP 8.3+, webman v2 (workerman/webman)
- MySQL 8.0+ (préfixe de tables `game_`, clés primaires BIGINT non auto-incrémentées)
- Redis (Session / Cache / Limitation de débit)
- ClickHouse (analyse OLAP / calcul de probabilités)
- Elasticsearch (recherche plein texte)
- Authentification JWT + contrôle d'accès RBAC
- Chiffrement des données : AES-256-CBC au niveau transport API + AES-128-ECB au niveau stockage base de données

### Frontend

Il existe deux arborescences front-end distinctes, **chacune n'appelant que le backend de son propre côté**, sans croisement :

| Arborescence | Rôle | Préfixe de requête | Backend | Stack technique |
|--------|------|---------|---------|--------|
| `apps/*` | **Plateforme joueur côté C** | `/api/v1/...` | service (8792 par défaut) | Flutter Web / React 19 (Vite) / Angular 21 / HarmonyOS ArkTS |
| `admin/apps/*` | **Console d'administration** | `/admin/v1/...` | admin (8789 par défaut) | Flutter Web / React 19 (Vite) / Angular 21 / HarmonyOS ArkTS |

- Mise en page réactive (Phone / Tablet / Desktop)
- Internationalisation (i18n) : bascule anglais / chinois simplifié

### Composants clés
- `erikwang2013/snowflake-php` — génération d'ID BIGINT uniques globaux
- `erikwang2013/hashids` — chiffrement/déchiffrement des ID au niveau API
- `erikwang2013/jwt-webman` — authentification JWT
- `erikwang2013/encryption` — chiffrement/déchiffrement des données sensibles de l'API
- `erikwang2013/encryptable` — chiffrement/déchiffrement des champs sensibles en base de données
- `erikwang2013/webman-scout` — synchronisation et requêtes Elasticsearch
- `erikwang2013/season` — drapeaux des pays
- `erikwang2013/security-php` — détection par outils de sécurité
- `erikwang2013/poster-php` — vérification aléatoire des opérations sensibles
- `erikwang2013/clickhouse-php` — connexion ClickHouse et calcul de probabilités

## Structure du projet

```
game-platform-php/
├── admin/                     # Backend d'administration (webman v2, port par défaut 8789, configurable via APP_PORT)
│   ├── app/admin/v1/controller/  #   Contrôleurs côté admin
│   ├── app/middleware/        #   Middleware (Cors/SecurityFilter/RateLimit/AdminAuth/AdminPermission/OperationLog)
│   ├── app/model/             #   Modèles propres à l'admin (8 ; les 52 autres modèles partagés sont dans packages/)
│   ├── app/service/           #   Services propres à l'admin (WalletService/WalletScope/RiskSandboxService)
│   ├── app/process/           #   Processus résidents (Http/Monitor/RiskIpCron)
│   ├── app/provider/          #   Couche Provider de jeux (Self/ThirdParty/Factory)
│   ├── app/activity/          #   Moteur d'activités (check-in/parrainage/tâches quotidiennes)
│   ├── app/event/             #   Bus d'événements (EventBus Redis Pub/Sub)
│   ├── config/                #   Fichiers de configuration
│   └── apps/                  #   Front-ends d'administration (4 cibles, appellent /admin/v1 → admin:8789)
│       ├── flutter/           #     Backend d'administration Flutter Web PC
│       ├── react/             #     Console d'administration React 19 (Vite)
│       ├── angular/           #     Console d'administration Angular 21
│       └── harmonyos/         #     Console d'administration HarmonyOS ArkTS (.hap, sans passer par nginx)
│
├── service/                   # Backend métier côté client C (webman v2, port par défaut 8792, configurable via APP_PORT)
│   ├── app/api/v1/controller/ #   Contrôleurs API côté C
│   ├── app/middleware/        #   Middleware (TraceId/Cors/SecurityFilter/RateLimit/LanguageMiddleware/UserAuth/ProviderAuth/SdkSessionAuth)
│   ├── app/model/             #   Modèles propres au service (10 ; les 52 autres modèles partagés sont dans packages/)
│   ├── app/service/           #   Services propres au service (portefeuille/risque/conformité/rapprochement/push/succès/anti-triche, etc.)
│   ├── app/payment/           #   18 adaptateurs de passerelles de paiement (Stripe/PayPal/Adyen/NowPayments/Skrill…) + GatewayFactory
│   ├── app/cdn/               #   Adaptateurs CDN de cinq fournisseurs (Cloudflare/CloudFront/Alibaba/Tencent/Huawei) + CdnFactory
│   ├── app/process/           #   Processus résidents (Http/Monitor/LeaderboardWS:8790/ChatWS:8791/EventConsumer/EventSubscriber/AntiCheatWorker/GroupSweepWorker/Health)
│   ├── app/provider/          #   Couche des providers de jeux
│   ├── app/activity/          #   Moteur d'activités
│   ├── app/event/             #   Bus d'événements (EventBus Redis Pub/Sub)
│   └── config/                #   Fichiers de configuration
│
├── packages/platform-common/  # Couche partagée : admin et service l'importent via un dépôt composer path, évitant deux copies
│   ├── src/model/             #   Modèles Eloquent partagés (52, même source des deux côtés)
│   ├── src/service/           #   Services partagés (DepositLogService / VipService etc., 11 au total, dont calcul de probabilités ClickHouse)
│   ├── src/BcMath.php         #   Arithmétique haute précision des montants/taux (enveloppe bcmath), arrondi, pourcentages
│   ├── src/EncryptionService.php  #   Chiffrement/déchiffrement AES et masquage
│   ├── src/CircuitBreaker.php #   Disjoncteur (plus Retry.php pour les tentatives)
│   ├── src/HashidsService.php #   Encodage/décodage des ID de la couche API
│   └── src/SnowflakeService.php   #   ID BIGINT globalement uniques
│
├── apps/                      # Front-ends joueurs côté C (4 cibles, appellent /api/v1 → service:8792)
│   ├── flutter/platform/      #   Plateforme utilisateur Flutter Web PC côté C
│   ├── react/                 #   React 19 (Vite) côté C
│   ├── angular/               #   Angular 21 côté C
│   └── harmonyos/             #   HarmonyOS ArkTS côté C (.hap, sans passer par nginx)
│
├── game/xiaoxiaole/           # Mini-jeu intégré « Match-3 Champêtre » : TypeScript + Vite + Vitest, moteur src/domain + conception en quatre niveaux + tests/, documents de conception en 13 langues
│
├── install/                   # Assistant d'installation en un clic + SQL d'initialisation de la base
│   ├── index.php              #   Point d'entrée de l'installation
│   ├── Installer.php          #   Logique principale de l'installation
│   ├── install.sql            #   SQL d'installation fusionné (78 tables + données de seed)
│   ├── clickhouse.sql         #   DDL de la base analytique ClickHouse (moteur distinct, import séparé)
│   ├── test-data.sql          #   Données de démonstration/test
│   ├── migrations/            #   Scripts de mise à niveau incrémentale des bases existantes (*.sql)
│   ├── lang/ + lang.php       #   Traductions de l'interface de l'assistant d'installation (13 langues)
│   └── assets/                #   Ressources statiques
│
├── docs/                      # Documentation du projet (tous les textes en 13 langues : .md est la source chinoise, avec les traductions .{lang}.md à côté)
│   ├── ARCHITECTURE.md        #   Document d'architecture
│   ├── ARCHITECTURE-DESIGN.md #   Document de conception d'architecture
│   ├── FEATURES.md            #   Document des fonctionnalités
│   ├── FEATURE-DESIGN.md      #   Document de conception fonctionnelle
│   ├── API.md                 #   Documentation des interfaces
│   ├── DEPLOYMENT.md          #   Document de déploiement (Docker/manuel/configuration des ports)
│   ├── PROVIDER-SDK.md        #   Guide d'intégration des jeux tiers (algorithme de signature + exemples PHP/Go/Python)
│   ├── CLICKHOUSE_INSTALL.md  #   Installer/configurer/migrer/vérifier ClickHouse
│   ├── CLICKHOUSE_USAGE.md    #   Les 4 API des services ClickHouse et le tableau de bord admin
│   ├── translations/          #   Les traductions de ce README en 12 langues
│   ├── diagrams/              #   SVG architecture/flux/fonctionnalités/cycle de vie/sécurité/extension de l'écosystème (13 langues chacun)
│   ├── test-reports/          #   Rapports de tests (php-unit / api / resilience / ui / SUMMARY)
│   └── superpowers/           #   Spécifications de conception et plans de mise en œuvre de ce dépôt (archive historique)
│
├── scripts/                   # Scripts d'exploitation (contrôle de dérive des modèles / migration des annotations apidoc / migration de la sémantique de versement exchange / vérification de signature)
├── tests/api/                 # Tests automatisés de l'API (run_all.sh)
├── runtime/                   # Répertoire d'exécution webman (logs/pid, généré à l'exécution)
│
├── docker-compose.yml         # Orchestration Docker Compose (ports par défaut depuis le .env racine)
├── nginx.conf.template        # Modèle de configuration Nginx (ports upstream rendus par envsubst)
├── .env.example               # Modèle .env racine (variables de ports Docker, copier vers .env pour l'utiliser)
└── admin/docs/superpowers/    # Normes de développement et plans
    ├── specs/                 #   Spécifications de conception
    └── plans/                 #   Plans d'implémentation
```

## Démarrage rapide

### Prérequis
- PHP 8.1+
- MySQL 8.0+
- Redis 6.0+
- Composer 2.x
- Flutter SDK 3.x (frontend, optionnel)

### Méthode 1 : assistant d'installation en un clic (recommandé)

```bash
# 1. Démarrer l'assistant d'installation
php -S 0.0.0.0:8888 -t install/

# 2. Ouvrir http://localhost:8888 dans le navigateur
#    Suivre l'assistant : vérification de l'environnement → configuration de la base de données → compte administrateur → installation automatique

# 3. Installer les dépendances
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 4. Démarrer les services (ports par défaut admin 8789 / service 8792, modifiables via APP_PORT dans le .env respectif)
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 5. Accéder au backend d'administration : http://localhost:8789 (port par défaut)
#    Se connecter avec le compte administrateur défini lors de l'installation

# 6. Supprimer le répertoire d'installation une fois terminé (sécurité)
rm -rf install/
```

L'assistant d'installation effectue automatiquement :
- la vérification de l'environnement (version PHP, extensions, permissions des répertoires) ;
- la création de la base de données et des tables (SQL fusionné, 78 tables + données de seed) ;
- la création du compte super-administrateur (chiffré bcrypt) ;
- la génération automatique des clés JWT/chiffrement et leur écriture dans le fichier .env ;
- la génération de install.lock pour empêcher une réinstallation.

### Méthode 2 : installation manuelle

<details>
<summary>Déplier les étapes d'installation manuelle</summary>

#### 1. Initialisation de la base de données

```bash
# Importer le SQL fusionné en une commande
mysql -u root -e "CREATE DATABASE IF NOT EXISTS game-platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root game-platform < install/install.sql
```

#### 2. Configuration des variables d'environnement

```bash
# Backend d'administration
cd admin
cp .env.example .env
# Modifier les informations de connexion et les clés dans .env

# Backend métier côté C
cd ../service
cp .env.example .env
# Modifier les informations de connexion et les clés dans .env
```

#### 3. Démarrage du backend

```bash
cd admin && composer install && php start.php start -d
cd ../service && composer install && php start.php start -d
```

#### 4. Création de l'administrateur

Il faut insérer manuellement le compte administrateur dans la base de données (mot de passe chiffré en bcrypt).

</details>

### Démarrage du frontend (optionnel)

En développement, chaque front-end démarre son propre serveur de dev ; les requêtes sont proxifiées par ce serveur vers le backend correspondant (voir `proxy.conf.json` / `vite.config.ts` dans chaque répertoire) :

```bash
# --- Plateforme joueur côté C (/api/v1 → service:8792) ---
cd apps/react            && npm install && npm run dev      # http://localhost:5173
cd apps/angular          && npm install && npm start        # http://localhost:4200
cd apps/flutter/platform && flutter pub get && flutter run -d chrome

# --- Console d'administration (/admin/v1 → admin:8789) ---
cd admin/apps/react      && npm install && npm run dev      # http://localhost:5273
cd admin/apps/angular    && npm install && npm start        # http://localhost:4300
cd admin/apps/flutter    && flutter pub get && flutter run -d chrome
```

> Ports des serveurs de dev Angular : la console d'administration fixe explicitement 4300 dans `angular.json`, tandis que le côté C conserve le 4200 par défaut d'Angular ; pour les lancer ensemble, ajoutez `--port` à l'un des deux.
> Les cibles HarmonyOS (`apps/harmonyos`, `admin/apps/harmonyos`) s'ouvrent et se compilent avec DevEco Studio ;
> un émulateur joint le backend hôte via `http://10.0.2.2:<port>` (voir la constante en haut de chaque `ApiService.ets`).

### Déploiement du frontend (Docker/Nginx)

Le service nginx de `docker-compose.yml` monte les artefacts de build de chaque frontend en lecture seule dans le conteneur, et `nginx.conf.template` les expose sur les chemins ci-dessous.
Si un artefact n'est pas construit, le répertoire est vide : une requête sur un chemin renvoie 404, une requête sur le répertoire nu (par ex. `/app-react/`) renvoie 403.

| URL | Point de montage de l'artefact | Commande de build |
|-----|-----------|---------|
| `/` | `apps/flutter/platform/build/web` | `flutter build web` |
| `/app-react/` | `apps/react/dist` | `npm run build` (le script inclut `--base=/app-react/`) |
| `/app-angular/` | `apps/angular/dist/game-client-angular/browser` | `npm run build` (le script inclut `--base-href=/app-angular/`) |
| `/admin-panel/` | `admin/public` | Emplacement générique : copiez n'importe quel artefact de console dans `admin/public` ; en son absence, la réponse est également 404 (répertoire nu 403). L'artefact doit être construit avec `--base=/admin-panel/` (pour Flutter, `--base-href=/admin-panel/`), sinon ses ressources pointent toujours vers le préfixe d'origine et renvoient 404. La forme sans barre oblique redirige en 301 vers cette adresse ; `nginx.conf.template` définit `absolute_redirect off`, la redirection est donc un Location relatif et les déploiements sur un port autre que 80 ne perdent plus le port |
| `/admin-react/` | `admin/apps/react/dist` | `npm run build` (le script inclut `--base=/admin-react/`) |
| `/admin-angular/` | `admin/apps/angular/dist/game-admin-angular/browser` | `npm run build` (le script inclut `--base-href=/admin-angular/`) |
| `/admin-flutter/` | `admin/apps/flutter/build/web` | `flutter build web --base-href=/admin-flutter/` |

`/admin/` (API) → conteneur admin, `/api/` (API) → conteneur service ; le client HarmonyOS est distribué sous forme de paquet `.hap` et ne passe pas par nginx.

### Vérification

```bash
# Tester le backend d'administration (port par défaut 8789)
curl http://localhost:8789/health

# Tester le backend métier côté C (port par défaut 8792)
curl http://localhost:8792/health

# Tester l'inscription d'un utilisateur
curl -X POST http://localhost:8792/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"Abcdef12"}'
```

## Caractéristiques de sécurité

- **Défense en profondeur sur 18 couches** : détection et blocage XSS/injection SQL/CSRF/traversée de chemin/injection de commandes
- **Liste blanche des méthodes HTTP** : seuls GET/POST/PUT/DELETE/OPTIONS/HEAD sont autorisés
- **Authentification JWT** : access_token 2h + refresh_token 14j, limitation des sessions concurrentes
- **Validation des clés JWT au démarrage** : clés indépendantes `ADMIN_JWT_SECRET_KEY` côté admin et `SERVICE_JWT_SECRET_KEY` côté service ; le démarrage est refusé si elles manquent ou restent à leur valeur par défaut
- **Fail-closed des rappels de paiement** : liste blanche des providers (stripe/paypal uniquement) + refus systématique en cas de clé non configurée/échec de vérification de signature/dépassement de l'horodatage + contrôle des montants via bccomp + enregistrement transactionnel des rappels
- **Permissions RBAC** : contrôle d'accès à granularité method.path, cache Redis 60s
- **Captcha cliquable** : vérification homme-machine obligatoire à la connexion/inscription
- **Double confirmation du mot de passe** : saisie du mot de passe requise pour les opérations sensibles
- **Chiffrement des données** : AES-256-CBC au niveau transport + AES-128-ECB au niveau stockage
- **Chiffrement des ID** : génération Snowflake + encodage Hashids, non rétro-déductibles de l'extérieur
- **Verrouillage optimiste du portefeuille** : prévention des débits concurrents / crédits en double
- **Audit des opérations** : journal complet des opérations, détection automatique des 8 sources de plateforme
- **Limitation de débit** : fenêtre glissante Redis, atomique via Lua
- **En-tête CSP** : Content-Security-Policy contre le XSS
- **Sécurité du compte** : 5 échecs de connexion consécutifs → verrouillage 15 minutes

## Tests

Rapports de tests (stockés localement) : [docs/test-reports/](../test-reports/)

| Type de test | Cas/couverture | Résultat |
|---------|----------|------|
| Tests unitaires PHP | mesure actuelle via `phpunit --list-tests` : admin 200 + service 273 cas (le rapport `docs/test-reports/php-unit.md` consigne la relance du 09-22 admin 190 + service 273 et l'instantané du 08-27 admin 153 + service 45 ; le côté admin est encore en cours d'extension) | service tout passe (701 assertions, 3 skipped, 2 warnings + 35 deprecations) ; admin 437 assertions, 3 skipped, 1 échec (`EnvConfigTest` vérifie le vrai `admin/.env` et constate l'absence de `REDIS_CLUSTER_NODES` ; l'ajouter le rend vert) |
| Tests des mécanismes de stabilité | disjoncteur/retry/interrupteur de dégradation, 15 cas (CircuitBreakerTest/RetryTest/ResilienceMockTest) | tout passe |
| Tests API automatisés | 187 points de terminaison (source : `docs/test-reports/api.md`, 2026-08-27) ; route.php enregistre actuellement 261 points de terminaison | 171 réussis / 50 échoués / 4 ignorés (tous les échecs sont des défauts déterministes, voir le rapport) |
| Tests UI Flutter | 12 cas (connexion/tableau de bord/navigation/changement de langue) | tout passe |
| Go/Rust | aucun code Go/Rust dans le dépôt | ignoré, consigné |

```bash
# Tests unitaires PHP (exporter d'abord les variables d'environnement du secret JWT)
cd admin && ADMIN_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
cd service && SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
# Tests API automatisés (les services doivent tourner, voir tests/api/run_all.sh)
bash tests/api/run_all.sh
# Tests UI Flutter
cd admin/apps/flutter && flutter test --timeout 300s
```

Rapports détaillés :
- [Rapport des tests unitaires PHP](../test-reports/php-unit.md)
- [Rapport des tests des mécanismes de stabilité (disjoncteur/retry/dégradation)](../test-reports/resilience.md)
- [Rapport des tests API automatisés](../test-reports/api.md)
- [Rapport des tests UI Flutter](../test-reports/ui.md)

## Aperçu des capacités de la plateforme

| Capacité | Description |
|------|------|
| Authentification utilisateur | Nom d'utilisateur + mot de passe + OAuth 7 plateformes (Google/Facebook/Apple/X(Twitter)/Microsoft/LinkedIn/GitHub) + 2FA TOTP |
| Portefeuille | Portefeuille de devises de plateforme (verrou optimiste) + portefeuille de devises de jeu + historique des transactions |
| Recharge | Création de commande + vérification des rappels Stripe/PayPal + crédit automatique |
| Échange | Devises de plateforme ⇄ devises de jeu, cotation en temps réel, gain sur l'écart |
| Retrait | Demande → validation → paiement, interrupteur global, plafonds KYC par paliers + frais |
| KYC | Soumission de vérification d'identité + validation, relève le plafond de retrait après approbation |
| Jeux | CRUD + catégories (10) + serveurs + suivi des parties |
| Recherche | Recherche plein texte Elasticsearch (avec repli LIKE) |
| Classements | Quotidien/hebdomadaire/mensuel/général, cache Redis, push temps réel WebSocket (port par défaut 8790, configurable via LEADERBOARD_WS_PORT) |
| CDN | Intégration de cinq fournisseurs (Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS upload + purge + préchargement) + configuration/activation/test de connectivité admin |
| Coupons | Montant fixe + remise proportionnelle, limites de temps et de quantité, suivi d'obtention et d'utilisation |
| Notifications | Messages internes + e-mails, notifications automatiques recharge/retrait/KYC/coupon |
| Parrainage | Code de parrainage, récompense d'inscription, commission sur recharges |
| Gestion des risques | Liste noire IP / alertes gros montants / détection de fréquence / de vitesse |
| Contrôle des risques approfondi | Empreinte d'appareil / réputation IP / graphe de liens de comptes + moteur de règles + tableau de bord des risques + AML/KYC/score de confiance |
| Anti-triche | Collecte d'événements anti-triche + statistiques quotidiennes + examen manuel |
| Rapprochement/règlement | Lots de rapprochement quotidiens + détails des écarts + rapprochement de relevés |
| Portefeuille unifié | WalletScope portefeuille unifié |
| Moteur d'activités | Création/participation/récompenses d'activités + check-in |
| Croissance sociale | Groupes + suivi des liens de partage |
| Passerelles de paiement | Nouvelles passerelles Adyen / GrabPay (L1) |
| Internationalisation | 4 langues (en-US/zh-CN/ja-JP/ko-KR), table de traduction + cache |
| Configuration par pays | Moyens de paiement/retrait différenciés pour 18 pays, montant minimum de recharge |
| Statistiques | Instantanés statistiques quotidiens (5 types de métriques) + suivi des revenus de la plateforme |
| Captcha | Vérification homme-machine cliquable (poster-php) |
| Intégration de jeux | SDK Provider (Self+ThirdParty) + signature HMAC-SHA256 + passerelle de rappels |
| Tickets | Création/réponse côté C + traitement/attribution/fermeture côté admin |
| VIP | 5 niveaux de fidélité, accumulation d'expérience, remise d'échange/exemption de frais de retrait/bonus de taux |
| Succès | 12 succès intégrés, détection pilotée par événements, suivi de progression |
| Social | Système d'amis + messagerie privée temps réel WebSocket (port par défaut 8791, configurable via CHAT_WS_PORT), seuls les amis peuvent écrire |
| Tournois | Système de tournois (interrupteur FeatureFlag) + classement + plafond de participants |
| Commission | Partage des revenus de parrainage à deux niveaux (taux de commission configurable) |
| Coupons | Restrictions conditionnelles (min_deposit/first_user/game_id) |
| Événements | Bus d'événements Redis Pub/Sub + livraison d'abonnements Webhook (7 types d'événements) |
| Déploiement | Orchestration Docker Compose 7 services (ports configurés dans le .env racine) + proxy inverse Nginx |
| Clients | Administration 4 cibles (Flutter/React/Angular/HarmonyOS) + côté C 4 cibles (Flutter/React/Angular/HarmonyOS) |

## Modèle métier

```
Devise fiduciaire (USD/CNY/EUR...)
  │  Recharge (Stripe/PayPal/Alipay/WeChat)
  ▼
Devises de plateforme (unifiées, précision decimal(18,4))
  │  Échange (taux + écart prélevé par la plateforme)
  ▼
Devises de jeu (indépendantes par jeu, taux indépendants)
  │  Jouer pour gagner/dépenser
  ▼
Devises de plateforme ← reconversion → Retrait (validation/automatique)
```

## Règlement multi-devises

La plateforme adopte un système de règlement à trois niveaux de devises isolés « devise fiduciaire → devises de plateforme → devises de jeu » : recharge dans plusieurs devises fiduciaires (USD/CNY/EUR/JPY/KRW/GBP/BRL/INR), chaque jeu disposant de sa propre devise de référence ; tous les calculs de montants utilisent l'arithmétique haute précision bcmath, éliminant les erreurs de virgule flottante.

### Modèle à trois niveaux de devises

| Niveau | Devise | Description |
|------|------|------|
| Niveau fiduciaire | USD / CNY / EUR / JPY / KRW / GBP / BRL / INR | Devises de paiement réelles de la recharge/retrait, traitées par Stripe / PayPal |
| Niveau devises de plateforme | Devises de plateforme (unifiées sur toute la plateforme) | Devise de règlement interne unifiée (decimal(18,4)), verrou optimiste du portefeuille contre les débits concurrents / crédits en double |
| Niveau devises de jeu | Devise indépendante par jeu | Taux `exchange_rate` et écart `spread_pct` indépendants par jeu, portefeuille de devises de jeu indépendant |

### Chemins de règlement

- **Règlement de la recharge** : l'utilisateur paie en devise fiduciaire (vérification des rappels Stripe / PayPal, anti-doublon idempotent) → conversion en devises de plateforme selon `default_exchange_rate`, la commande de recharge enregistre simultanément `amount + currency + platform_amount`
- **Règlement de l'échange** : cotation (quote) en temps réel des devises de plateforme ⇄ devises de jeu au taux de la devise du jeu, prélèvement de l'écart `spread_pct` comme gain de la plateforme, les VIP bénéficient de remises d'échange et de bonus de taux
- **Règlement du jeu** : le provider de jeu crédite/débite les devises de jeu de l'utilisateur via le rappel `/api/provider/settle` (signature HMAC-SHA256), règlement automatique à l'expiration de la session de jeu
- **Règlement du retrait** : débit des devises de plateforme → création de la commande de retrait (enregistrement de `platform_amount / fiat_amount / currency`) → approbation côté admin → paiement PayPal Payout → synchronisation du statut du lot jusqu'à complétion

### Schéma du règlement

```mermaid
flowchart LR
    subgraph FIAT["法币层 Fiat"]
        A["用户充值<br/>USD / CNY / EUR / JPY / KRW / GBP / BRL / INR<br/>Stripe / PayPal"]
        H["提现到账<br/>PayPal Payout"]
    end

    subgraph PLAT["平台币层 Platform Token"]
        B["平台币钱包<br/>decimal(18,4) 乐观锁"]
        E["提现订单<br/>platform_amount<br/>fiat_amount / currency"]
    end

    subgraph GAME["游戏币层 Game Currency"]
        D["游戏币种<br/>exchange_rate<br/>spread_pct"]
        C["游戏币钱包<br/>UserGameWallet"]
        G["游戏 Provider<br/>settle 结算回调"]
    end

    A -->|"充值回调验签<br/>平台币 = 法币 × default_exchange_rate"| B
    B -->|"兑换买入 in<br/>扣除点差"| C
    C -->|"兑换卖出 out<br/>按汇率折算"| B
    D -.->|"独立汇率 + VIP 加成"| C
    G <-->|"玩游戏赚/花"| C
    B -->|"提现申请（扣款）"| E
    E -->|"管理端审批<br/>PayPal Payout 打款"| H
```

## Schéma d'architecture

![Schéma d'architecture système](../diagrams/architecture-fr.svg)

## Processus métier clés

![Schéma du processus métier](../diagrams/flow-fr.svg)

## Panorama des fonctionnalités

![Schéma du panorama des fonctionnalités](../diagrams/features-fr.svg)

## Cycle de vie

![Schéma du cycle de vie](../diagrams/lifecycle-fr.svg)

## Architecture de sécurité

![Schéma de l'architecture de sécurité](../diagrams/security-fr.svg)

## Extension d'écosystème (v2.0)

![Schéma de l'architecture d'extension d'écosystème](../diagrams/ecosystem-expansion-fr.svg)

## Index de la documentation

| Document | Description |
|------|------|
| [Comparaison des versions](../VERSIONS.fr.md) | Comparaison des fonctionnalités édition de base/standard/complète |
| [Document de conception d'architecture](../ARCHITECTURE-DESIGN.fr.md) | Justifications des choix d'architecture et décisions de conception |
| [Document d'architecture](../ARCHITECTURE.fr.md) | Topologie du système, architecture des modules, flux de données |
| [Document de conception fonctionnelle](../FEATURE-DESIGN.fr.md) | Modèle métier, spécifications fonctionnelles, conception des processus |
| [Document des fonctionnalités](../FEATURES.fr.md) | Liste des fonctionnalités, description des modules, parcours utilisateur |
| [Documentation des interfaces](../API.fr.md) | Référence API complète (146 interfaces) |
| [Documentation en ligne](http://localhost:8792/apidoc/) | Documentation interactive erikwang2013/apidoc-php (côté C) |
| [Documentation en ligne](http://localhost:8789/apidoc/) | Documentation interactive erikwang2013/apidoc-php (back-end d'administration) |
| [Installation de ClickHouse](../CLICKHOUSE_INSTALL.fr.md) | Installation/configuration/migration/vérification de ClickHouse |
| [Document d'intégration du SDK Provider](../PROVIDER-SDK.fr.md) | Guide d'intégration des jeux tiers (algorithme de signature + exemples PHP/Go/Python) |
| [Utilisation de ClickHouse](../CLICKHOUSE_USAGE.fr.md) | 4 API de services ClickHouse et tableau de bord backend |
| [Document de déploiement](../DEPLOYMENT.fr.md) | Guide de déploiement (Docker + manuel + Nginx + surveillance) |
| [Spécifications de conception](../../admin/docs/superpowers/specs/2026-05-22-game-platform-design.fr.md) | Spécifications de conception complètes |
| [Plan d'implémentation](../../admin/docs/superpowers/plans/2026-05-22-game-platform-plan.fr.md) | Plan d'implémentation détaillé |

---

## Soutenir le projet

Si ce projet vous est utile, n'hésitez pas à offrir un café à l'auteur ☕

<p align="center">
  <table align="center" border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td align="center" width="200">
        <img src="../weixinpay-130.png" width="130" height="130" alt="微信支付"><br>
        <b>WeChat Pay</b>
      </td>
      <td align="center" width="200">
        <img src="../alipay-130.png" width="130" height="130" alt="支付宝"><br>
        <b>Alipay</b>
      </td>
    </tr>
  </table>
</p>

### Virement bancaire mondial (Global Bank Transfer)

**Informations du bénéficiaire (Recipient)**

| Élément | Contenu |
|----|------|
| Nom du bénéficiaire (Beneficiary Name) | WANG KEXUN |
| Numéro de compte (Account Number) | 881015918251 |

**Banque du bénéficiaire (Beneficiary Bank)**

| Élément | Contenu |
|----|------|
| Code SWIFT | AABLHKHHXXX |
| Nom de la banque (Bank Name) | ZA Bank Limited |
| Code banque (Bank Code) | 387 |
| Adresse de la banque (Bank Address) | Core F, Cyberport 3, 100 Cyberport Road, Hong Kong |

**Banque correspondante pour les virements transfrontaliers (Correspondent Bank, si nécessaire)**

> À noter : il s'agit des informations de la banque correspondante (banque intermédiaire) pour les virements transfrontaliers, et non de la banque du bénéficiaire. Veuillez demander à votre banque émettrice si les informations de la banque correspondante sont requises.

- **La banque correspondante pour les virements en dollars de Hong Kong, renminbi et dollars américains est Citibank :**
  - Nom de la banque : Citibank N.A. Hong Kong
  - Code SWIFT : CITIHKHXXXX
  - Code banque : 006
  - Nom de la succursale : Hong Kong Branch
  - Numéro de succursale : 391
  - Adresse de la banque : Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **La banque correspondante pour les virements dans d'autres devises est BNY Mellon :**
  - Nom de la banque : THE BANK OF NEW YORK MELLON
  - Code SWIFT : IRVTUS3NXXX
  - Adresse de la banque : THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

### Don en cryptomonnaie (Crypto Donation)

Si ce projet vous est utile, scannez le code QR pour faire un don, merci !

| Réseau (Network) | Code QR (QR Code) | Adresse du portefeuille (Wallet Address) |
|---|---|---|
| BNB Smart Chain (BEP20) | [<img src="../coin/1.jpg" width="150" alt="BNB Smart Chain (BEP20)">](../coin/1.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Tron (TRC20) | [<img src="../coin/2.jpg" width="150" alt="Tron (TRC20)">](../coin/2.jpg) | `TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| Ethereum (ERC20) | [<img src="../coin/3.jpg" width="150" alt="Ethereum (ERC20)">](../coin/3.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Aptos | [<img src="../coin/4.jpg" width="150" alt="Aptos">](../coin/4.jpg) | `0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| Plasma | [<img src="../coin/5.jpg" width="150" alt="Plasma">](../coin/5.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Polygon POS | [<img src="../coin/6.jpg" width="150" alt="Polygon POS">](../coin/6.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Solana | [<img src="../coin/7.jpg" width="150" alt="Solana">](../coin/7.jpg) | `2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` |
| The Open Network (TON) | [<img src="../coin/8.jpg" width="150" alt="The Open Network (TON)">](../coin/8.jpg) | `UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| Arbitrum One | [<img src="../coin/9.jpg" width="150" alt="Arbitrum One">](../coin/9.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| AVAX C-Chain | [<img src="../coin/10.jpg" width="150" alt="AVAX C-Chain">](../coin/10.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |

