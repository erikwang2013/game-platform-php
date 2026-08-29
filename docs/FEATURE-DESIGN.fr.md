# Document de conception fonctionnelle
<!-- lang-nav -->

Languages: [中文](FEATURE-DESIGN.md) · [English](FEATURE-DESIGN.en.md) · [한국어](FEATURE-DESIGN.ko.md) · [Русский](FEATURE-DESIGN.ru.md) · [Deutsch](FEATURE-DESIGN.de.md) · **Français** · [Español](FEATURE-DESIGN.es.md) · [Português](FEATURE-DESIGN.pt.md) · [हिन्दी](FEATURE-DESIGN.hi.md) · [العربية](FEATURE-DESIGN.ar.md) · [বাংলা](FEATURE-DESIGN.bn.md) · [Bahasa Indonesia](FEATURE-DESIGN.id.md) · [日本語](FEATURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Conception du système de devises

### 1.1 Modèle à trois couches de devises

```
Couche 1: Devises fiduciaires (USD / CNY / EUR / JPY ...)
       ↕ Recharge/retrait (conversion au taux de change)
Couche 2: Devises de plateforme (unifiées, précision decimal(18,4))
       ↕ Échange (avec taux de change + marge de la plateforme)
Couche 3: Devises de jeu (indépendantes par jeu, taux indépendants)
```

### 1.2 Devises de plateforme

- Unité de compte unifiée de la plateforme
- Précision : `DECIMAL(18,4)`, plus petite unité 0.0001
- Obtenues par recharge en devise fiduciaire, échangeables contre n'importe quelle devise de jeu
- Les devises de jeu peuvent aussi être reconverties en devises de plateforme, puis retirées en devise fiduciaire
- La plateforme prélève la marge d'échange comme source de revenus

### 1.3 Devises de jeu

- Chaque jeu peut avoir plusieurs devises de jeu (ex. pièces d'or, diamants, points)
- Chaque devise définit indépendamment son taux d'échange contre la devise de plateforme (`exchange_rate`)
- Chaque devise définit indépendamment la marge de la plateforme (`spread_pct`)
- Prise en charge des plafonds d'échange min/max (`min_exchange` / `max_exchange`)

### 1.4 Formules d'échange

**Achat de devises de jeu (devises de plateforme → devises de jeu) :**
```
Devises de jeu créditées = devises de plateforme × exchange_rate × (1 - spread_pct / 100)
```

**Vente de devises de jeu (devises de jeu → devises de plateforme) :**
```
Devises de plateforme créditées = devises de jeu ÷ exchange_rate × (1 - spread_pct / 100)
```

**Exemple :**
- exchange_rate = 100 (1 devise de plateforme = 100 devises de jeu)
- spread_pct = 5 % (la plateforme prélève 5 % de marge)
- L'utilisateur achète avec 10 devises de plateforme : (10 × 100 × 0.95) = 950 devises de jeu
- L'utilisateur vend 950 devises de jeu : (950 ÷ 100 × 0.95) = 9.025 devises de plateforme
- Revenu de la plateforme : 10 - 9.025 = 0.975 devise de plateforme

## 2. Conception du portefeuille

### 2.1 Portefeuille de devises de plateforme (game_user_wallet)

Créé automatiquement à l'inscription de l'utilisateur, solde initial à 0.

| Champ | Description |
|------|------|
| balance | Solde disponible (rechargeable/retirable/échangeable) |
| frozen_balance | Solde gelé (réservé, ex. retrait en cours) |
| total_earned | Revenus cumulés |
| total_spent | Dépenses cumulées |
| version | Numéro de version du verrou optimiste (+1 à chaque mise à jour) |

### 2.2 Portefeuille de devises de jeu (game_user_game_wallet)

Unique selon les trois dimensions utilisateur+jeu+devise. Créé automatiquement au premier échange.

### 2.3 Sécurité de concurrence

Verrou optimiste contre les problèmes de concurrence :

```php
// Vérification du numéro de version lors de la mise à jour
$updated = Wallet::where('id', $wallet->id)
    ->where('version', $wallet->version)
    ->update([
        'balance' => bcadd($wallet->balance, $amount, 4),
        'version' => $wallet->version + 1,
    ]);

// Échec de la mise à jour (version modifiée) → nouvel essai, 5 fois maximum
```

## 3. Conception du système de retrait

### 3.1 Contrôles multicouches

```
Couche 1: Interrupteur global de retrait
       ├─ Désactivé → tous les retraits refusés, pour la gestion d'urgence des risques
       └─ Activé → passage aux contrôles de la couche 2

Couche 2: Contrôles de plafonds
       ├─ Montant minimum par opération (min_amount)
       ├─ Montant maximum par opération (max_amount)
       └─ Plafond quotidien cumulé (daily_limit)

Couche 3: Processus de validation
       ├─ Montant < seuil de validation automatique → approbation automatique
       └─ Montant >= seuil de validation automatique → validation manuelle → approbation/refus
```

### 3.2 Machine à états du retrait

```
pending (en attente de validation)
  ├─→ approved (approuvé) → completed (terminé)
  └─→ rejected (refusé) → retour du solde + flux de remboursement
```

### 3.3 Contrôles d'administration

- **Bouton d'interrupteur global** : active/désactive d'un clic les retraits de tous les utilisateurs
- **File de validation** : liste des demandes en attente triées par date, boutons approuver/refuser
- **Configuration des plafonds** : réglage visuel de chaque paramètre de plafond

## 4. Conception de la recharge

### 4.1 Flux de recharge

```
1. L'utilisateur choisit le mode de paiement et le montant
2. La plateforme crée la commande de recharge (status=pending, order_no unique)
3. Redirection vers la page de paiement tierce
4. L'utilisateur finalise le paiement
5. Le tiers notifie la plateforme (POST /api/payment/callback)
6. Vérification de la signature → mise à jour de la commande (status=confirmed)
7. Crédit des devises de plateforme → enregistrement du flux
```

### 4.2 Modes de paiement

| Type | Fournisseur | Description |
|------|--------|------|
| Fiduciaire | Stripe | Paiement par carte de crédit internationale |
| Fiduciaire | PayPal | Portefeuille électronique mondial |
| Fiduciaire | Alipay | Alipay (international, via Stripe Checkout APM) |
| Fiduciaire | WeChat Pay | WeChat Pay (international, via Stripe Checkout APM) |
| Crypto | USDT-TRC20 | USDT sur la chaîne Tron |

L'édition de base connecte d'abord un mode de paiement unique (ex. Stripe), l'édition standard étend à tous les canaux.

## 5. Conception de l'intégration des jeux

### 5.1 Jeux propriétaires

Les jeux propriétaires sont intégrés directement à la plateforme, partageant le système d'utilisateurs et les portefeuilles :

- Le jeu consulte le solde de devises de jeu de l'utilisateur via l'API interne
- Le règlement du jeu débite/crédite les devises de jeu via l'API interne
- Aucune vérification de signature supplémentaire requise

### 5.2 Jeux tiers

Les jeux tiers se connectent via SDK/API :

```
Côté plateforme:
  1. L'utilisateur clique sur « entrer dans le jeu »
  2. La plateforme génère la signature (user_id + timestamp + api_secret → HMAC-SHA256)
  3. Redirection 302 ou chargement iframe de l'URL du jeu (avec les paramètres signés)

Côté jeu:
  4. Vérification de la signature → création de la session de jeu
  5. Consultation du solde : GET /api/game/balance?user_id=...&sign=...
  6. Callback de règlement : POST /api/game/callback {user_id, amount, type, sign}
  7. La plateforme vérifie la signature → mise à jour du solde → enregistrement du flux → renvoi du résultat
```

### 5.3 Algorithme de signature

```
signature = HMAC-SHA256(
    secret = api_secret,
    message = user_id + "|" + timestamp + "|" + amount + "|" + nonce,
)
```

Conditions de validation :
- Signature correcte
- Horodatage dans ±60 s (anti-rejeu)
- nonce jamais utilisé (enregistré en Redis, expiration 60 s)
- IP de la requête dans la liste blanche

## 6. Conception des permissions

### 6.1 Rôles prédéfinis

| Rôle | Périmètre des permissions |
|------|---------|
| Super administrateur | * (toutes les permissions) |
| Opérations de jeux | Gestion des jeux, gestion des annonces, tableau de bord |
| Validation financière | Validation des retraits, gestion des paiements, consultation des flux |
| Service client | Consultation des utilisateurs C, consultation des commandes de recharge |

### 6.2 Granularité des permissions

```
{method}.{path}

Exemples:
  get.admin/game/list      → consulter la liste des jeux
  post.admin/game/create   → créer un jeu
  put.admin/withdraw/review → valider un retrait
  put.admin/withdraw/switch → actionner l'interrupteur de retrait (super administrateur uniquement)
```

## 呼. Nouvelles conceptions de l'édition standard

### 8.1 Moteur de gestion des risques

Quatre types de règles :
- `ip_blacklist` — correspondance de liste noire IP, blocage direct en cas de correspondance
- `amount_anomaly` — détection de gros montants unitaires, alerte au-delà du seuil
- `frequency` — détection de la fréquence d'opérations dans une fenêtre temporelle
- `velocity` — détection d'association multi-comptes en peu de temps

Les règles s'exécutent par priorité décroissante ; la première règle correspondante décide du résultat (block > warn > log).

### 8.2 Connexion OAuth tiers

Fournisseurs pris en charge : Google, Facebook, Apple

Flux :
1. Le frontend demande `GET /api/auth/oauth/{provider}` pour obtenir l'URL d'autorisation
2. L'utilisateur redirige vers le tiers et termine l'autorisation
3. Callback `POST /api/auth/oauth/{provider}/callback` avec le code d'autorisation
4. Le backend cherche une liaison existante → connexion directe ; sans liaison → inscription automatique + liaison + création du portefeuille

### 8.3 Système de plafonds KYC

| Niveau | Obtention | Plafond par opération | Plafond journalier | Frais |
|------|---------|---------|--------|--------|
| default | Par défaut à l'inscription | 1 000 | 10 000 | 1,00 % |
| verified | Validation KYC passée | 5 000 | 50 000 | 0,50 % |
| vip | Accordé par les opérations | 20 000 | 200 000 | 0,00 % |

### 8.4 Serveurs de jeux

Chaque jeu peut configurer plusieurs serveurs (region: global/asia/eu/na), statut du serveur : maintenance/normal/chaud/nouveau.

### 8.5 Instantané des statistiques quotidiennes

Le crontab exécute `ComputeDailyStats::run()` chaque jour à l'aube, calculant cinq indicateurs :
- Statistiques utilisateurs (nouveaux/actifs/cumulés)
- Statistiques de recharge (nombre/montant total)
- Statistiques de retrait (nombre/montant total)
- Statistiques d'échange (nombre/frais totaux)
- Statistiques de jeux (nombre de joueurs/nombre de sessions)

## 9. Fonctionnalités de niveau production

### 9.1 Système de notifications

Types de notifications : system/deposit/withdraw/kyc/coupon/announcement

Scénarios de déclenchement automatique :
- Recharge créditée → NotificationService::send()
- Retrait approuvé/refusé → notification automatique
- KYC approuvé/refusé → notification automatique
- Coupon réclamé → notification automatique
- Récompense de parrainage créditée → notification automatique

Double canal : messagerie interne + e-mail (l'e-mail nécessite la variable d'environnement MAIL_HOST).

### 9.2 Rétrocommission de parrainage

```
Utilisateur A génère un code de parrainage → le partage avec l'utilisateur B
Utilisateur B renseigne le code à l'inscription → chacun reçoit la récompense d'inscription (signup_reward)
Utilisateur B recharge → A reçoit la commission de recharge (deposit_commission_pct %)
```

### 9.3 Authentification 2FA

- Protocole standard TOTP (RFC 6238), compatible Google Authenticator
- Flux d'activation : obtention de la clé → scan du QR code → validation du TOTP → génération de 8 codes de récupération de secours
- Deuxième vérification à la connexion : POST /api/2fa/verify
- Tolérance de ±1 fenêtre temporelle (30 s)

### 9.4 Intégration OAuth réelle

| Fournisseur | Point d'API token | Point d'API informations utilisateur |
|--------|----------|------------|
| Google | oauth2.googleapis.com/token | www.googleapis.com/oauth2/v3/userinfo |
| Facebook | graph.facebook.com/v18.0/oauth/access_token | graph.facebook.com/me |
| Apple | appleid.apple.com/auth/token | Décodage du JWT id_token |

Configuration via PlatformConfig ou variables d'environnement, repli automatique sur le mode mock en cas d'échec de requête.

### 9.5 Vérification des webhooks de paiement

- Stripe : vérification de la signature HMAC-SHA256 (en-tête Stripe-Signature)
- PayPal : POST vers le point de vérification PayPal
- Vérification automatiquement sautée si la clé n'est pas configurée (mode développement)

### 9.6 Classement WebSocket temps réel

- Protocole : WebSocket (ws://host:8789)
- Abonnement : {action: "subscribe", leaderboard_id: 123}
- Push : {type: "ranking_update", rankings: [...]}
- Heartbeat ping/pong pour le maintien de la connexion

## 7. Conception de l'internationalisation

### 7.1 Langues prises en charge

| Code | Nom | Nom local | Icône |
|------|------|--------|------|
| en-US | English | English | us |
| zh-CN | Chinese (Simplified) | 简体中文 | cn |
| ja-JP | Japanese | 日本語 | jp |
| ko-KR | Korean | 한국어 | kr |

### 7.2 Gestion des traductions

- Traductions organisées au format `group.key` (ex. `auth.login_success`)
- Stockées dans la table `game_translation`, mises en cache Redis (TTL 1 heure)
- API : `GET /api/language/list` pour lister les langues disponibles, `POST /api/language/switch` pour changer de langue
- Le frontend détecte automatiquement via l'en-tête `X-Language` ou `Accept-Language`
- Traduction manquante → repli sur en-US ; absente aussi en en-US → renvoi de la clé d'origine

### 7.3 Préférence de langue de l'utilisateur

- Définie automatiquement à l'inscription selon le `Accept-Language` du navigateur
- Modifiable après connexion via `PUT /api/user/profile` (champ `language`)
- Le changement de langue synchronise l'enregistrement utilisateur

## 8. Modèle de revenus de la plateforme

| Source de revenus | Calcul | Description |
|---------|---------|------|
| Marge d'échange | spread_fee par échange | Prélevée dans les deux sens (achat et vente) |
| Frais de retrait | montant du retrait × fee_pct | Implémenté dans l'édition standard |
| Partage des jeux | partage des revenus des jeux tiers | Selon le contrat |
| Différentiel de change | écart entre le taux de la plateforme et le taux du marché | Écart entre le taux défini par la plateforme et le taux du marché |
