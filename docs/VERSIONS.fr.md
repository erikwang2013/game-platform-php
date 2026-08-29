# Comparaison des versions
<!-- lang-nav -->

Languages: [中文](VERSIONS.md) · [English](VERSIONS.en.md) · [한국어](VERSIONS.ko.md) · [Русский](VERSIONS.ru.md) · [Deutsch](VERSIONS.de.md) · **Français** · [Español](VERSIONS.es.md) · [Português](VERSIONS.pt.md) · [हिन्दी](VERSIONS.hi.md) · [العربية](VERSIONS.ar.md) · [বাংলা](VERSIONS.bn.md) · [Bahasa Indonesia](VERSIONS.id.md) · [日本語](VERSIONS.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Vue d'ensemble

| | Édition de base (Lite) | Édition standard (Standard) | Édition complète (Full) |
|------|------|------|------|
| Tables de données (install.sql) | 19 | 29 | **43** (et non les 52 écrites dans les documents) |
| Points d'API | 38 | 54 | ~149 (admin+service, Webhook/Provider inclus) |
| Contrôleurs backend | 14 | 22 | admin 32 + service 30 |
| Modèles de données | Non partagés | Non partagés | **admin 46 / service 44 chacun de son côté, sans couche partagée** |
| Services partagés | Aucune couche partagée | Aucune couche partagée | `packages/platform-common` package partagé unique |
| Pages frontend Admin | 11 | 13 | 15 |
| Pages frontend Platform | 8 | 10 | 10 |
| HarmonyOS (admin) | - | Connexion + tableau de bord | **8 pages** `admin/apps/harmonyos/` |
| HarmonyOS (côté C) | - | - | **5 pages** `apps/harmonyos/` (connexion/hall de jeux/détail/portefeuille/moi) |
| Services Docker | - | - | **7** (nginx/admin/service/leaderboard-ws/mysql/redis/elasticsearch) |
| Cas de test | 60 | 60 | admin ~132 ; service 3 |

---

## Authentification des utilisateurs

| Fonctionnalité | Édition de base | Édition standard | Édition complète |
|------|--------|--------|--------|
| Inscription/connexion nom d'utilisateur + mot de passe | ✓ | ✓ | ✓ |
| Jeton JWT (2h+14j) | ✓ | ✓ | ✓ |
| Captcha cliquable | stub | stub | ✓ poster-php |
| Verrouillage du compte (5 fois/15 minutes) | ✓ | ✓ | ✓ |
| Limitation des sessions (3 concurrentes) | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ 7 plateformes (dont X/MS/LinkedIn/GitHub) |
| 2FA TOTP à double facteur | - | - | ✓ |
| Export/suppression des données GDPR | - | - | ✓ |

---

## Portefeuille et fonds

| Fonctionnalité | Édition de base | Édition standard | Édition complète |
|------|--------|--------|--------|
| Portefeuille de devises de plateforme | ✓ | ✓ | ✓ |
| Verrou optimiste du portefeuille | ✓ | ✓ | ✓ |
| Historique des transactions | ✓ | ✓ | ✓ |
| Portefeuille de devises de jeu | ✓ | ✓ | ✓ |
| Création de commande de recharge (checkout_url/expires_at renseignés à la création) | ✓ | ✓ | ✓ |
| Crédit automatique au rappel de recharge | - | ✓ manuel | ✓ vérification Stripe (incl. Alipay/WeChat Pay)/PayPal/NowPayments IPN/Coinbase webhook |
| Cotation/achat/vente d'échange | ✓ | ✓ | ✓ |
| Gain sur l'écart d'échange | ✓ | ✓ | ✓ |
| Demande de retrait | ✓ | ✓ | ✓ |
| Interrupteur global de retrait | ✓ | ✓ | ✓ |
| Validation des retraits | ✓ manuel | ✓ manuel | ✓ par lot + manuel |
| Plafonds KYC par paliers | - | ✓ 3 niveaux | ✓ |
| Frais de retrait | - | - | ✓ |
| Reçu PDF | - | - | ✓ |

---

## Gestion des jeux

| Fonctionnalité | Édition de base | Édition standard | Édition complète |
|------|--------|--------|--------|
| CRUD de jeux | ✓ | ✓ | ✓ |
| Gestion des devises de jeu | ✓ | ✓ | ✓ |
| Liste/détail des jeux côté C | ✓ | ✓ | ✓ |
| Lancement de jeux | ✓ | ✓ | ✓ |
| Catégories de jeux (10) | - | - | ✓ |
| Filtrage par catégorie | - | - | ✓ |
| Gestion des serveurs de jeux | - | ✓ | ✓ |
| Suivi des parties | - | ✓ | ✓ |
| Recherche plein texte ES | - | - | ✓ |
| Suggestions de recherche | - | - | ✓ |
| SDK Provider de jeux tiers | - | - | ✓ HMAC-SHA256 |

---

## Outils d'exploitation

| Fonctionnalité | Édition de base | Édition standard | Édition complète |
|------|--------|--------|--------|
| Gestion des annonces | ✓ | ✓ | ✓ |
| Tableau de bord | ✓ backend admin | ✓ backend admin | ✓ admin + plateforme |
| Export Excel | ✓ | ✓ | ✓ |
| Export PDF | ✓ | ✓ | ✓ |
| Vrais graphiques du tableau de bord | - | - | ✓ fl_chart |
| Système de coupons | - | - | ✓ |
| Classements (jour/semaine/mois/total) | - | - | ✓ cache Redis |
| Classement temps réel WebSocket | - | - | ✓ port 8789 |
| Système de notifications (interne + e-mail) | - | - | ✓ |
| Rétrocommission de parrainage | - | - | ✓ |
| Instantané de statistiques quotidien | - | ✓ | ✓ |
| Suivi des revenus de la plateforme | - | - | ✓ |

---

## Sécurité et conformité

| Fonctionnalité | Édition de base | Édition standard | Édition complète |
|------|--------|--------|--------|
| Défense en profondeur sur 18 couches | ✓ | ✓ | ✓ |
| Contrôle d'accès RBAC | ✓ | ✓ | ✓ |
| Journal d'audit des opérations | ✓ | ✓ | ✓ |
| Détection des 8 sources de plateforme | ✓ | ✓ | ✓ |
| Limitation de débit à fenêtre glissante Redis | ✓ | ✓ | ✓ |
| KYC de vérification d'identité | - | ✓ | ✓ |
| Moteur de gestion des risques (4 règles) | - | ✓ | ✓ |
| Vérification des signatures des rappels de paiement | - | - | ✓ |

---

## Internationalisation

| Fonctionnalité | Édition de base | Édition standard | Édition complète |
|------|--------|--------|--------|
| Prise en charge multilingue | Chinois/anglais | 4 langues | 4 langues |
| Table de traduction + cache | ✓ | ✓ | ✓ |
| Détection automatique de la langue | ✓ | ✓ | ✓ |
| Configuration différenciée par pays | - | - | ✓ 8 pays |

---

## Déploiement et exploitation

| Fonctionnalité | Édition de base | Édition standard | Édition complète |
|------|--------|--------|--------|
| Déploiement webman autonome | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ 7 services |
| Proxy inverse Nginx | - | - | ✓ |
| Tâches planifiées Crontab | - | ✓ | ✓ |
| Surveillance Prometheus | ✓ | ✓ | ✓ `/metrics` gauges métier + compteurs d'événements |
| Contrôle de santé | ✓ | ✓ | ✓ |
| Documentation en ligne hg/apidoc | - | - | ✓ 41 contrôleurs |

---

## Clients

| Fonctionnalité | Édition de base | Édition standard | Édition complète |
|------|--------|--------|--------|
| Backend d'administration Flutter Web PC | ✓ 5 pages | ✓ 11 pages | ✓ 15 pages |
| Plateforme utilisateur Flutter Web PC | ✓ 5 pages | ✓ 8 pages | ✓ 10 pages |
| HarmonyOS admin | - | ✓ connexion + tableau de bord | ✓ 8 pages `admin/apps/harmonyos/` |
| HarmonyOS côté C | - | - | ✓ 5 pages `apps/harmonyos/` |

---

## Tables de base de données

### Édition de base (19 tables)
```
Backend d'administration (7) : game_admin_user, game_admin_role, game_admin_permission,
               game_admin_user_role, game_admin_role_permission,
               game_operation_log, game_system_config

Noyau de la plateforme (12) : game_user, game_user_wallet, game_user_game_wallet,
               game_game, game_game_currency, game_deposit_order,
               game_withdraw_order, game_exchange_record, game_transaction,
               game_payment_method, game_announcement, game-platform_config
```

### Ajouts de l'édition standard (10 tables)
```
game_user_identity, game_user_oauth, game_user_payment_account,
game_user_session, game_game_server, game_game_play_log,
game_withdraw_limit, game_risk_rule, game_risk_log, game_stat_daily
```

### Ajouts de l'édition complète (13 tables)
```
game_game_category, game_game_category_rel, game_leaderboard,
game_coupon, game_user_coupon, game_language, game_translation,
game_country_config, game-platform_revenue,
game_notification, game_referral, game_referral_reward, game_user_2fa
```

---

## Points d'API

| Module | Édition de base | Édition standard | Édition complète |
|------|--------|--------|--------|
| Authentification | 3 | 3 | 7 (+OAuth×2 +2FA×5) |
| Portefeuille | 2 | 2 | 3 (+rappel de recharge) |
| Échange | 4 | 4 | 4 |
| Retrait | 2 | 2 | 8 (+par lot+plafonds+validation) |
| Jeux | 3 | 4 | 7 (+serveurs+parties+recherche) |
| Utilisateurs | 2 | 2 | 7 (+KYC+GDPR+confidentialité) |
| Backend d'administration | 18 | 25 | 79 |
| Outils d'exploitation | - | - | 30 (+classements+coupons+notifications+parrainage) |
| Internationalisation | 2 | 2 | 4 (+configuration par pays) |
| **Total** | **38** | **54** | **129** |

---

## Extension d'écosystème (v2.0) — nouveau

| Fonctionnalité | Description |
|------|------|
| Couche d'abstraction GameProvider | SelfProvider (transaction DB) + ThirdPartyProvider (HTTP+signature) |
| Passerelle API Provider | rappels balance/bet/settle/refund + middleware ProviderAuth |
| Système de tickets | création/réponse côté C + traitement/attribution/fermeture côté admin |
| Vérification e-mail | code à 6 chiffres, expiration Redis 10 minutes, limite de renvoi 60 secondes |
| Notifications push | PushService (FCM/APNs/push Huawei) |
| Système VIP | 5 niveaux, accumulation d'expérience, montée automatique, remise d'échange, exemption de frais de retrait, bonus de taux |
| Système de succès | 12 succès intégrés, détection pilotée par événements, suivi de progression |
| Système d'amis | demande/acceptation/refus/suppression/recherche |
| Messages privés/chat | messages temps réel REST + WebSocket (port 8790) |
| Bus d'événements | Redis Pub/Sub ; emit INCR `metrics:event_*` ; processus de consommation `EventConsumer` livré |
| Interrupteurs de fonctionnalités | FeatureFlag basé DB ; `inRollout`/`abTest` lisent `feature.{name}_percent` |
| Webhook | - | - | ✓ 7 types d'événements + livraison Pub/Sub |
| Chat | - | - | ✓ REST+WebSocket :8791 |
| Système de tournois | - | - | ✓ FeatureFlag+tournament |
| Conditions de coupons | - | - | ✓ min_deposit/first_user/game_id |
| Rétrocommission multi-niveaux | - | - | ✓ partage à deux niveaux |
| Documentation SDK | - | - | ✓ PHP/Go/Python |
| Analyses avancées | rétention/D1-D30, entonnoir de conversion, ARPU/ARPPU |

### Nouvelles tables (10)
```
game_ticket, game_ticket_reply, game_device_token,
game_vip_level, game_user_vip, game_exp_log,
game_achievement, game_user_achievement,
game_friend, game_message
```

### Nouveaux points d'API Provider (4)
```
POST /api/provider/balance  — interroger le solde
POST /api/provider/bet      — notifier la mise
POST /api/provider/settle   — notifier le règlement
POST /api/provider/refund   — notifier le remboursement
```

### Nouveaux points d'API côté C (8)
```
POST /api/verify/send-email    — envoyer le code e-mail
POST /api/verify/confirm-email — confirmer l'e-mail
GET  /api/ticket/list             — liste des tickets
POST /api/ticket/create           — créer un ticket
GET  /api/ticket/{id}             — détail du ticket
POST /api/ticket/{id}/reply       — répondre au ticket
GET  /api/user/vip-status         — statut VIP
GET  /api/user/achievements       — liste des succès
```

### Nouveaux points d'API backend admin (6)
```
GET  /admin/ticket/list          — liste des tickets
GET  /admin/ticket/{id}          — détail du ticket
POST /admin/ticket/{id}/reply    — répondre au ticket
POST /admin/ticket/{id}/close    — fermer le ticket
POST /admin/ticket/{id}/assign   — désigner le traitement
GET  /admin/analytics/retention  — analyse de rétention
GET  /admin/analytics/funnel     — entonnoir de conversion
GET  /admin/analytics/arpu       — tendance ARPU
GET  /admin/analytics/economy    — indicateurs économiques
```
