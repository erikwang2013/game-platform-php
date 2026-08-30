# Document des fonctionnalités
<!-- lang-nav -->

Languages: [中文](FEATURES.md) · [English](FEATURES.en.md) · [한국어](FEATURES.ko.md) · [Русский](FEATURES.ru.md) · [Deutsch](FEATURES.de.md) · **Français** · [Español](FEATURES.es.md) · [Português](FEATURES.pt.md) · [हिन्दी](FEATURES.hi.md) · [العربية](FEATURES.ar.md) · [বাংলা](FEATURES.bn.md) · [Bahasa Indonesia](FEATURES.id.md) · [日本語](FEATURES.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Vue d'ensemble des fonctionnalités

### Édition de base (MVP) — terminée

| Domaine | Fonctionnalité | Statut |
|----|------|------|
| Utilisateurs | Inscription/connexion/JWT/captcha | Terminé |
| Portefeuille | Solde de devises de plateforme/consultation des transactions | Terminé |
| Recharge | Création de commande de recharge (Stripe 125+ paiements locaux, incl. Alipay/WeChat Pay APM / NOWPayments USDT TRC20·ERC20 / Coinbase USDC·BTC·ETH / callback PayPal) | Terminé |
| Échange | Devises de plateforme ⇄ devises de jeu (taux fixe + écart) | Terminé |
| Retrait | Demande/consultation/interrupteur global/validation automatique/validation manuelle | Terminé |
| Jeux | CRUD backend/gestion des devises/liste côté C/détail/lancement | Terminé |
| Administration | Gestion des jeux/validation des retraits/gestion des utilisateurs/gestion des paiements/gestion des annonces | Terminé |
| Tableaux de bord | Tableau de bord de la plateforme (DAU/transactions/revenus/classements) | Terminé |
| Export | Export Excel des utilisateurs/transactions/retraits | Terminé |
| Internationalisation | Bascule chinois/anglais, table de traduction, middleware de détection de langue | Terminé |
| Frontend | Backend d'administration Flutter PC + plateforme utilisateur côté C (i18n inclus) | Terminé |

### Édition standard — terminée

| Domaine | Fonctionnalité | Statut |
|----|------|------|
| Utilisateurs | Connexion OAuth (Google/Facebook/Apple/Twitter/Microsoft/LinkedIn/GitHub) | Terminé |
| Paiement | Rappels automatiques multi-canaux (Stripe incl. Alipay/WeChat Pay APM / PayPal / NOWPayments IPN / Coinbase Webhook) | Terminé |
| Jeux | Gestion des serveurs, suivi des parties | Terminé |
| Retrait | Plafonds KYC par paliers (default/verified/vip) + frais | Terminé |
| KYC | Demande de vérification d'identité + validation | Terminé |
| Gestion des risques | Liste noire IP/alerte gros montants/détection de fréquence/de vitesse | Terminé |
| Statistiques | Instantané de statistiques quotidien (utilisateurs/recharges/retraits/échanges/jeux) | Terminé |
| Frontend | Admin : validation KYC + journaux de risques / Platform : OAuth+KYC+historique de jeux | Terminé |

### Édition complète — terminée

| Domaine | Fonctionnalité | Statut |
|----|------|------|
| Hall de jeux | 10 catégories prédéfinies, filtrage par catégorie, association jeu-catégorie | Terminé |
| Classements | Quotidien/hebdomadaire/mensuel/général, cache Redis, multi-métriques | Terminé |
| Coupons | Montant fixe + remise proportionnelle, limites de temps et de quantité, suivi d'obtention/utilisation | Terminé |
| Configuration par pays | 8 pays prédéfinis, moyens de paiement/retrait différenciés, montant minimum de recharge | Terminé |
| Statistiques | Instantané de statistiques quotidien + suivi des revenus de la plateforme | Terminé |
| Recherche | Recherche plein texte Elasticsearch (intégrée au niveau modèle) | Terminé |

### Améliorations niveau production — terminées

| Domaine | Fonctionnalité | Statut |
|----|------|------|
| OAuth | Échange de vrais tokens Google/Facebook/Apple | Terminé |
| Paiement | Vérification des signatures de callback (Webhook Stripe incl. Alipay/WeChat Pay APM, Webhook PayPal, NOWPayments IPN HMAC-SHA512, Coinbase HMAC-SHA256 secret base64) | Terminé |
| Captcha | Captcha cliquable poster-php | Terminé |
| Notifications | Messages internes + e-mails, notifications automatiques recharge/retrait/KYC/coupon | Terminé |
| 2FA | TOTP Google Authenticator + codes de secours de rechange | Terminé |
| Parrainage | Code de parrainage, récompense d'inscription, commission de recharge | Terminé |
| Recherche | API de recherche ES + suggestions de jeux + repli LIKE | Terminé |
| Classements | Push temps réel WebSocket (port 8789) | Terminé |
| CDN | Intégration de cinq fournisseurs (Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS upload + purge + préchargement) | Terminé |
| Administration CDN | Configuration des cinq fournisseurs côté admin (identifiants chiffrés/activation-désactivation/test de connexion HeadBucket), le service lit uniquement la base de données | Terminé |
| Rapports | Rapports de données côté admin (résumé/quotidien/export CSV, cache Redis 5 min, période ≤90 jours) | Terminé |
| Statistiques de la plateforme | Statistiques d'accueil C (total jeux/utilisateurs/parties du jour/actifs 7 jours) | Terminé |
| Déploiement | Docker Compose 7 services + proxy inverse Nginx | Terminé |
| Données | Analyse par agrégation MySQL en temps réel + calcul de probabilités conjointes/conditionnelles | Terminé |
| HarmonyOS | 8 pages côté admin ; côté C `apps/harmonyos/` implémente connexion/hall/détail/portefeuille/profil (pointant vers 8788) | Partiellement terminé (le projet s'exécute, IP à modifier sur appareil réel) |
| Documentation API | Documentation interactive hg/apidoc | Terminé |
| Installation en un clic | Assistant d'installation navigateur : créer l'admin, mettre à niveau la BDD existante, install.lock anti-réinstallation | Terminé |
| Tolérance aux pannes | CircuitBreaker + Retry + interrupteur de dégradation feature.provider_mock | Terminé |
| Modes de paiement | CRUD admin + visibilité par pays + plage de montants + restriction de devise | Terminé |
| CI | tag auto-incrémenté au push + GitHub Release | Terminé |

### Extension d'écosystème (v2.0) — tout juste terminée

| Domaine | Fonctionnalité | Statut |
|----|------|------|
| Intégration de jeux | Couche d'abstraction GameProvider (Self/ThirdParty) + signature HMAC-SHA256 | Terminé |
| Rappels de jeux | Passerelle API Provider (balance/bet/settle/refund) + middleware ProviderAuth | Terminé |
| Sessions de jeux | Heartbeat Redis + règlement automatique au dépassement de 15 minutes + GameSessionService | Terminé |
| Système de tickets | Création/réponse côté C + traitement/attribution/fermeture côté admin, 5 types de tickets | Terminé |
| Vérification e-mail | Code à 6 chiffres, expiration Redis 10 minutes, limite de renvoi 60 secondes | Terminé |
| Notifications push | PushService (FCM/APNs/push Huawei) + modèle DeviceToken | Terminé |
| Système VIP | 5 niveaux (normal/argent/or/platine/diamant) + expérience + montée automatique | Terminé |
| Droits VIP | Remise d'échange 2-15 %, exemption de frais de retrait 10-100 %, bonus de taux 0,1-1,0 % | Terminé |
| Système de succès | 12 succès intégrés ; EventConsumer → détection pilotée par événements AchievementService et expérience VIP | Terminé |
| Système d'amis | Demande/acceptation/refus/suppression/recherche, statuts pending/accepted/blocked | Terminé |
| Messages privés/chat | Messages privés REST + messages temps réel WebSocket (port 8790), seuls les amis peuvent écrire | Terminé |
| Bus d'événements | Redis Pub/Sub ; emit + EventConsumer consomme succès/Webhook + INCR de métriques | Terminé |
| Interrupteurs de fonctionnalités | FeatureFlag basé DB ; `inRollout`/`abTest` en buckets crc32 lisant `feature.{name}_percent` | Terminé |
| Analyses avancées | Rétention/D1-D30, entonnoir de conversion, ARPU/ARPPU, indicateurs économiques des devises de jeu (agrégation MySQL en temps réel) | Terminé |
| Webhook | Gestion des abonnements + livraison des événements Redis Pub/Sub, 7 types d'événements optionnels | Terminé |
| Chat | Messages privés REST + messages temps réel WebSocket (port 8791), seuls les amis peuvent écrire | Terminé |
| Tournois | Création/list/detail/join, interrupteur FeatureFlag, classement, plafond de participants | Terminé |
| Rétrocommission multi-niveaux | Partage des revenus de parrainage à deux niveaux, modèle ReferralCommission, taux de commission configurable | Terminé |
| Conditions de coupons | Trois types de restrictions : min_deposit/first_user_only/game_id | Terminé |
| Documentation SDK | Documentation d'intégration Provider (exemples PHP/Go/Python + 4 points d'API) | Terminé |
| Mini-jeu | Farm Match-3 P0 (moteur de domaine + conception de 4 niveaux, tests unitaires TypeScript/Vite/Vitest) | Terminé |

## 2. Fonctionnalités côté C

### 2.1 Parcours utilisateur

```
Inscription → Connexion → Vérification e-mail/téléphone → Parcourir le hall de jeux → Entrer dans le détail du jeu
                                           ↓
Voir le portefeuille ← Jouer ← Échanger des devises de jeu (remise VIP) ← Recharger des devises de plateforme
    ↓
  Retrait (exemption de frais VIP) → Validation backend → Réception des fonds
    ↓
Système d'amis → Chat privé → Compétition au classement → Suivi des succès
    ↓
Support par tickets
```

### 2.2 Interfaces API

| Méthode | Chemin | Description | Authentification |
|------|------|------|------|
| POST | /api/auth/register | Inscription utilisateur | Non |
| POST | /api/auth/login | Connexion utilisateur | Non |
| POST | /api/auth/refresh | Rafraîchir le jeton | Non |
| GET | /api/game/list | Liste des jeux | Non |
| GET | /api/game/detail/{id} | Détail du jeu | Non |
| GET | /api/announcement/list | Liste des annonces | Non |
| GET | /api/wallet/info | Solde du portefeuille | Oui |
| GET | /api/wallet/transactions | Historique des transactions | Oui |
| POST | /api/deposit/create | Créer une commande de recharge | Oui |
| GET | /api/payment/methods | Liste des modes de paiement (routage par pays) | Oui |
| POST | /api/exchange/quote | Cotation d'échange (remise VIP) | Oui |
| POST | /api/exchange/buy | Acheter des devises de jeu | Oui |
| POST | /api/exchange/sell | Vendre des devises de jeu | Oui |
| POST | /api/withdraw/apply | Demande de retrait (exemption VIP) | Oui |
| POST | /api/game/launch | Lancer un jeu | Oui |
| GET | /api/game/play-logs | Historique des parties | Oui |
| POST | /api/referral/apply | Utiliser un code de parrainage | Oui |
| POST | /api/verify/send-email | Envoyer le code de vérification e-mail | Oui |
| POST | /api/verify/confirm-email | Confirmer l'e-mail | Oui |
| GET | /api/ticket/list | Liste des tickets | Oui |
| POST | /api/ticket/create | Créer un ticket | Oui |
| POST | /api/ticket/{id}/reply | Répondre au ticket | Oui |

| GET | /api/platform/stats | Statistiques de la plateforme | Non |
## 3. Fonctionnalités du backend d'administration

### 3.1 Interfaces API (nouvelles)

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/dashboard/platform | Données du tableau de bord de la plateforme |
| GET | /admin/analytics/overview | Vue d'ensemble de la plateforme (agrégation MySQL en temps réel) |
| GET | /admin/analytics/game-ranking | Classement des jeux |
| GET | /admin/analytics/dau-trend | Tendance DAU |
| GET | /admin/analytics/hourly-trend | Tendance horaire |
| GET | /admin/analytics/action-distribution | Répartition des comportements |
| GET | /admin/analytics/revenue | Analyse des revenus |
| GET | /admin/analytics/conversion | Taux de conversion des jeux |
| GET | /admin/analytics/probability | Probabilités conjointes/conditionnelles |
| GET | /admin/analytics/retention | Analyse de rétention D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Entonnoir de conversion |
| GET | /admin/analytics/arpu | Tendance ARPU/ARPPU |
| GET | /admin/analytics/economy | Indicateurs économiques des devises de jeu |
| GET | /admin/report/summary | Récapitulatif des rapports (nouveaux utilisateurs/dépôts/retraits/échanges/parties) |
| GET | /admin/report/daily | Rapport quotidien (agrégation par jour, jours sans données remplis à 0) |
| GET | /admin/report/export | Export du rapport quotidien en CSV (UTF-8 BOM) |
| GET | /admin/game/list | Liste des jeux |
| POST | /admin/game/create | Créer un jeu (provider_config inclus) |
| PUT | /admin/game/{id} | Modifier un jeu |
| GET | /admin/withdraw/orders | Liste des commandes de retrait |
| PUT | /admin/withdraw/review | Valider un retrait |
| GET | /admin/ticket/list | Liste des tickets |
| GET | /admin/ticket/{id} | Détail du ticket |
| POST | /admin/ticket/{id}/reply | Répondre au ticket |
| POST | /admin/ticket/{id}/close | Fermer le ticket |
| POST | /admin/ticket/{id}/assign | Désigner le traitement |

## 4. API Provider (rappels du côté jeu)

| Méthode | Chemin | Description | Authentification |
|------|------|------|------|
| POST | /api/provider/balance | Interroger le solde utilisateur | HMAC-SHA256 |
| POST | /api/provider/bet | Notifier la mise | HMAC-SHA256 |
| POST | /api/provider/settle | Notifier le règlement | HMAC-SHA256 |
| POST | /api/provider/refund | Notifier le remboursement | HMAC-SHA256 |

Algorithme de signature : `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)`
En-têtes : `X-Game-Id` + `X-Timestamp` + `X-Signature`
Fenêtre de temps : 5 minutes

## 5. Système VIP

| Niveau | EXP cumulé | Remise d'échange | Exemption de frais de retrait | Bonus de taux |
|------|---------|---------|-------------|---------|
| Normal | 0 | 0 % | 0 % | Référence |
| Argent | 500 | 2 % | 10 % | +0,1 % |
| Or | 2 500 | 5 % | 30 % | +0,3 % |
| Platine | 12 500 | 10 % | 50 % | +0,5 % |
| Diamant | 62 500 | 15 % | 100 % | +1,0 % |

### Obtention d'expérience

| Comportement | EXP |
|------|-----|
| Recharge de 1 yuan | 10 |
| Connexion quotidienne | 5 |
| KYC complété | 50 |
| Invitation d'un nouvel utilisateur | 100 |
| Succès atteint | 10-100 |

## 6. Liste des succès

| Succès | Condition | Points |
|------|------|------|
| First Deposit | Première recharge | 20 |
| Century Club | 100 de recharge cumulée | 50 |
| High Roller | 1 000 de recharge cumulée | 100 |
| Trader | Premier échange | 20 |
| Day Trader | 100 échanges cumulés | 100 |
| Explorer | Avoir joué à 3 jeux | 30 |
| Adventurer | Avoir joué à 5 jeux | 50 |
| Conqueror | Avoir joué à 10 jeux | 100 |
| Weekly Warrior | 7 jours de connexion consécutifs | 30 |
| Monthly Master | 30 jours de connexion consécutifs | 100 |
| Connector | Inviter 1 ami | 30 |
| Influencer | Inviter 10 amis | 100 |

## 7. Liste des tables de base de données

### Ajouts de l'extension d'écosystème (10 tables)

| Nom de table | Description | Caractéristiques clés |
|------|------|---------|
| game_ticket | Tickets | index user_id+type+status, assigned_to |
| game_ticket_reply | Réponses de tickets | index ticket_id, distinction is_admin |
| game_device_token | Jetons d'appareil | index unique user_id+platform+token |
| game_vip_level | Définition des niveaux VIP | index unique level, bénéfices en JSON |
| game_user_vip | Enregistrement VIP utilisateur | index unique user_id, level+exp+total_exp |
| game_exp_log | Journal d'expérience | index combiné user_id+source |
| game_achievement | Définition des succès | index unique key, condition_json en JSON |
| game_user_achievement | Succès utilisateur | index unique user_id+achievement_id |
| game_friend | Relations d'amitié | index unique user_id+friend_id |
| game_message | Messages privés | from_user_id+to_user_id / to_user_id+is_read |

### Modifications de structure de tables

| Nom de table | Modification |
|------|------|
| game_game | +provider_config (JSON) |
| game_game_play_log | +round_id, +bet_amount, +win_amount |

**Total : install.sql 43 tables** (les 10 de l'extension d'écosystème sont dans `install/`, non fusionnées dans install.sql). Modèles non partagés : admin 46 / service 44 chacun de son côté.

## 8. Couverture des tests

| Fichier de test | Nombre de cas | Couverture |
|---------|--------|---------|
| PlatformTest | 56 | Précision bcmath/calcul d'échange/frais de retrait/plafonds/risques/coupons/KYC/i18n |
| BackendEnhancementTest | 23 | Service de chiffrement/Hashids/Snowflake |
| CaptchaTest | 7 | Génération/vérification de captcha |
| EncryptionServiceTest | 6 | Chiffrement/déchiffrement AES/masquage |
| EnvConfigTest | 4 | Configuration des variables d'environnement |
| HashidsServiceTest | 8 | Aller-retour d'encodage/décodage d'ID |
| SnowflakeServiceTest | 6 | Unicité de la génération d'ID |

**Total : admin ~132 cas / 8 fichiers ; service 3 cas (WebhookUrlSafety + EventBusMessageFormat). Le service n'est pas bloquant en cas d'échec dans la CI.**

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
