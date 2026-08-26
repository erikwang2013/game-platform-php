# Plan global du projet (Project Plan)
<!-- lang-nav -->

Languages: [中文](PROJECT-PLAN.md) · [English](PROJECT-PLAN.en.md) · [한국어](PROJECT-PLAN.ko.md) · [Русский](PROJECT-PLAN.ru.md) · [Deutsch](PROJECT-PLAN.de.md) · **Français** · [Español](PROJECT-PLAN.es.md) · [Português](PROJECT-PLAN.pt.md) · [हिन्दी](PROJECT-PLAN.hi.md) · [العربية](PROJECT-PLAN.ar.md) · [বাংলা](PROJECT-PLAN.bn.md) · [Bahasa Indonesia](PROJECT-PLAN.id.md) · [日本語](PROJECT-PLAN.ja.md)


> Date de génération : 2026-08-16 · Basé sur l'inventaire en lecture seule d'une équipe de 6 personnes (researcher/architect/backend-dev/frontend-dev/tester/reviewer) + vérification par tests des constats clés
> Couverture : état des lieux / problèmes et risques / feuille de route P0-P1-P2 / réparations documentaires / portes de qualité

---

## I. État actuel du projet

**Plateforme mondiale d'agrégation de jeux** — PHP 8.3 + webman v2, monorepo à deux applications :
`admin/` (8787 backend d'administration) + `service/` (8788 côté C) + `apps/` (Flutter + HarmonyOS) + `install/` (assistant d'installation, 43 tables).

| Dimension | Taille mesurée |
|------|---------|
| Contrôleurs | admin 32 + service 30 = 62 |
| Points d'API | ~149 (admin 103 / service 88, rappels Webhook/Provider inclus) |
| Modèles de données | admin 46 / service 44, **dupliqués** entre admin/service (aucune couche partagée) |
| Tests | 132 cas / 8 fichiers (projet admin), projet service **zéro test** |
| Version | v1.1 (2026-08-07) : plugin Redis, services d'analyse, repli Redis, corrections de tests |

Capacités implémentées : JWT+RBAC, verrou optimiste du portefeuille, recharge (vérification Stripe/PayPal), écart d'échange, validation des retraits + paiement PayPal, CRUD de jeux/passerelle Provider (HMAC), coupons/VIP/succès/tickets/commission de parrainage/2FA/social (amis/chat WS)/tournois/Webhook/push (FCM/APNs/Huawei)/i18n bilingue.

---

## II. Problèmes et risques (vérifiés par tests)

### CRITIQUE — Sécurité des fonds

| # | Problème | Emplacement |
|---|------|------|
| C1 | Le `provider` du rappel de paiement est transmis par le client ; s'il n'est ni stripe ni paypal, la **vérification de signature est totalement ignorée**, un faux rappel crédite directement | service/.../PaymentController.php:36-42 |
| C2 | Vérification fail-open : `STRIPE_WEBHOOK_SECRET` non configuré → `return true` ; toute exception PayPal → `return true`. Chaîne d'attaque : créer une commande de recharge → faux rappel → recharge infinie | PaymentController.php:167, 225 |
| C3 | `JWT_SECRET_KEY` par défaut retombe sur une clé publique codée en dur `open-admin-jwt-secret-change-in-production` ; sans env en production, un jeton admin peut être falsifié | admin+service config/plugin/erikwang2013/jwt/jwt.php:12 |

### ÉLEVÉ — Exactitude / cohérence

| # | Problème | Emplacement |
|---|------|------|
| H1 | Les 12 méthodes du service d'analyse AnalyticsController sont toutes implémentées mais **zéro route**, code mort 404, alors que VERSIONS.md prétend qu'elles sont livrées | admin/config/route.php (0 occurrence analytics) |
| H2 | Bus d'événements déconnecté : 4 appels d'emit (game.played/withdraw.completed/exchange.completed/referral.applied), aucun processus enregistré sur `subscribe()`, les événements publiés sont perdus ; les moteurs VIP/succès/notification sont tous en l'air | admin+service app/event/EventBus.php |
| H3 | common/ et model/ dupliqués des deux côtés et divergés (deux DepositLogService différents, User.php incohérent), une correction ponctuelle devient un double travail. **common/service a été extrait** vers `packages/platform-common` (erik/platform-common, l'ancien common-php y a été fusionné) ; les modèles et les wrappers app/common restent en double | admin/common vs service/common → packages/platform-common |
| H4 | ~~`apps/harmonyos/` côté C HarmonyOS est un répertoire vide, 0 page vs les 5 pages prétendues par VERSIONS.md~~ — livré (2026-08-18 : 5 pages implémentées dans `apps/harmonyos/`) | apps/harmonyos/ |
| H5 | Le rappel Stripe ne vérifie pas la tolérance d'horodatage `t=` (rejouable), et le montant crédité n'est pas comparé au montant réellement payé sur la passerelle | PaymentController.php:191-194 |
| H6 | Apple id_token : seul le payload est décodé en base64, sans vérification de signature ni de aud/iss/exp, risque de confusion d'identité entre applications | OAuthController.php:376-380 |

### MOYEN — Fiabilité / défauts d'implémentation

| # | Problème |
|---|------|
| M1 | Double défaut 2FA : `/api/2fa/verify` public sans verrouillage par utilisateur (oracle de force brute) ; TOTP utilise la chaîne Base32 directement comme clé HMAC (non décodée), incompatible avec l'Authenticator → **la 2FA est en réalité inutilisable** |
| M2 | Validation/paiement des retraits en check-then-act sans mise à jour d'état atomique, paiements dupliqués possibles en concurrence ; pas de double validation |
| M3 | L'URL de rappel Webhook n'est vérifiée que par filter_var, peut pointer vers une IP interne (SSRF), POST vers n'importe quelle URL au dispatch |
| M4 | Les plafonds de retrait journaliers/mensuels en « lire puis insérer » non atomiques, dépassables en concurrence |
| M5 | Panne Redis fail-open sans abstraction unifiée : invalidation de la liste noire JWT inopérante, limitation de débit silencieusement neutralisée ; lacunes de repli : PayoutService::getAccessToken, brpop de ChatWebSocket, stockage de l'état OAuth |
| M6 | ClickHouse inutilisé : le calcul de probabilités est en réalité un COUNT(DISTINCT) MySQL en temps réel + jointures de sous-requêtes, risque O(n²) sur les grandes tables ; dépendance composer sans capacité |
| M7 | File d'attente à moitié finie : admin/app/queue a ComputeDailyStats + 3 tâches ES, mais webman/queue n'est pas installé, process.php n'a aucun enregistrement, tout est sans appelant |
| M8 | Code mort : services Vip/Achievement/Notification/FeatureFlag sans appelant ; implémentation vide de DepositLogService::log() ; modèle Test résiduel ; algorithme de rétention approximatif à cohorte unique |

### BAS
- Les retraits sans obligation 2FA/KYC peuvent être payés vers n'importe quelle adresse e-mail PayPal ; les remarques de validation entrent dans le texte de notification (surface XSS)
- Documentation divergente du réel : install.sql 43 tables vs 52 écrites dans les documents ; docker-compose 7 services vs 8 écrits dans FEATURES.md ; « 34 modèles partagés » inexact (admin 46 / service 44 chacun de son côté, sans couche partagée). CHANGELOG complété, voir `docs/CHANGELOG.fr.md`.

### Points passés (confirmés sans problème par le contrôle de sécurité)
Verrou optimiste du portefeuille + mise à jour conditionnelle par version correcte ; idempotence des rappels par mise à jour conditionnelle `where status=pending` correcte ; aucun SQL concaténé directement dans tout l'ORM ; .env absent du git ; toutes les routes admin passent par AdminAuth+RBAC par défaut refusé ; validation de l'état OAuth + consommation unique correcte.

> **Statut de réparation 2026-08-18** : C1/C2/C3/H1/H5/H6 réparés ; H2 bus d'événements : `process.php` enregistre `event-consumer` et la classe de consommation `EventConsumer` est livrée, les emit ont des consommateurs ; M1 Base32 + verrouillage par utilisateur réparés ; M2 état de retrait atomique + double validation optionnelle faits ; M3 SSRF Webhook bloqué ; M4 verrou utilisateur Redis côté demande de retrait fait ; M5 partiellement fait (RateLimit fail-closed) ; P2-19 métriques métier + déploiement progressif FeatureFlag livrés. La liste des problèmes reste conservée comme conclusion d'audit historique.

---

## III. Feuille de route

### P0 — Sécurité des fonds + exactitude (en premier, bloque la mise en production)

1. **Fail-closed des rappels de paiement** : liste blanche des providers (stripe/paypal uniquement) + clé manquante → refus 500 obligatoire + toute exception PayPal refusée (C1/C2) — ✅ Terminé (2026-08-18 : liste blanche des providers + vérification anti-abus inter-canaux + vérification optionnelle de l'IP source + enregistrement transactionnel des rappels)
2. **Validation JWT au démarrage** : refus de démarrage si `JWT_SECRET_KEY` absente de l'env (C3) — ✅ Terminé (2026-08-18 : refus de démarrage si `JWT_SECRET_KEY` manque ou vaut `open-admin-jwt-secret-change-in-production`, cohérent admin/service)
3. **Montage des routes du service d'analyse** : enregistrer les 12 routes analytics + points de permission, honorer l'engagement de VERSIONS.md (H1) — ✅ Terminé (2026-08-18 : 12 routes `/admin/analytics/*` enregistrées dans admin/config/route.php)
4. **Bus d'événements connecté** : enregistrer un processus d'abonnement permanent pour consommer, ou basculer en appel synchrone direct ; événements en base + nouvel essai en cas d'échec (H2) — ✅ Terminé (2026-08-18 : emit/consume font INCR sur les compteurs Redis ; `service/config/process.php` enregistre `event-consumer`, `service/app/process/EventConsumer.php` consomme les événements)
5. **Vérification Apple id_token** : validation JWKS + aud/iss/exp (H6) — ✅ Terminé (2026-08-18 : JWKS RS256 + rafraîchissement kid + aud/iss/exp)
6. **Relecture et contrôle des montants Stripe** : tolérance d'horodatage + comparaison avec le montant de la passerelle (H5) — ✅ Terminé (2026-08-18 : horodatage `t=` ±300s contre la relecture + contrôle des montants en précision bccomp + refus systématique si secret/webhook_id non configuré ou vérification de signature en erreur)

### P1 — Fiabilité + cohérence

7. **Déduplication de la couche partagée** : extraire common/model en path repo composer (ou liens symboliques), éliminer la divergence en double (H3) — 🔶 Partiellement terminé (2026-08-18 : `common/service` extrait en path repo unique `packages/platform-common` / `erik/platform-common` (l'ancien `common-php` y a été fusionné), référencé par admin+service ; les modèles et les wrappers `app/common` liés à l'hôte restent en double, voir `packages/platform-common/DUAL_MODELS.fr.md`)
8. **Encapsulation unifiée du repli Redis** : stratégie d'échec explicite + alerte non silencieuse ; compléter les replis PayoutService/OAuth/ChatWebSocket (M5) — 🔶 Partiellement terminé (fail-closed RateLimit livré : en cas de panne Redis, la limitation de débit refuse plutôt que de laisser passer silencieusement ; le reste non fait)
9. **Câblage webman/queue** : porter les événements et la livraison des webhooks (nouvel essai de consommation, lettre morte), activer ou supprimer les tâches ComputeDailyStats/ES (M7) — ⬜ Non fait
10. **Réparation 2FA** : décodage Base32 + login requis et verrouillage par utilisateur sur verify (M1) — ✅ Terminé (2026-08-18 : HMAC après décodage Base32 RFC 4648 ; `/api/2fa/verify` 5 échecs → verrouillage 15 minutes, fail-closed en cas de panne Redis)
11. **Atomicité des retraits** : mise à jour conditionnelle de validation/paiement + double validation ; plafonds en Lua Redis/contrainte unique (M2/M4) — 🔶 Partiellement terminé (2026-08-18 : pending→approved/rejected, approved→processing en UPDATE conditionnel ; double validation optionnelle `withdraw.require_dual_review` ; verrou utilisateur Redis côté demande. Pas de plafonds Lua/contrainte unique)
12. **Blocage SSRF Webhook** : refuser les adresses internes/réservées (M3) — ✅ Terminé (2026-08-18 : `isSafeWebhookUrl()` https public uniquement)
13. **ClickHouse, au choix** : intégration réelle ou retrait de la dépendance + révision des documents (M6) — ⬜ Non fait
14. **Nettoyage du code mort** : câbler ou supprimer Vip/Achievement/Notification/FeatureFlag ; supprimer le modèle Test ; audit DepositLog en base (M8) — 🔶 Partiellement terminé (2026-08-18 : modèle Test supprimé, audit DepositLog en base ; Vip/FeatureFlag/Notification ont des appelants ; AchievementService est appelé par EventConsumer)
15. **Tests service + porte CI** : tests d'intégration vérification de rappel/flux de retrait/repli Redis/calcul de probabilités/verrou optimiste concurrent ; blocage si phpunit échoue ; service dans la CI (actuellement `|| echo warning` autorise l'échec) — 🔶 Partiellement terminé (service a WebhookUrlSafety / EventBusMessageFormat ; intégré à la CI, job `phpunit-service` bloquant en cas d'échec)

**Complété en plus cette vague (2026-08-18) (hors numérotation d'origine)** :
- **Correction du préfixe de tables** : suppression du préfixe `erik_` codé en dur des 52 modèles, élimination du double préfixe `erik_erik_` ; le préfixe de base est désormais fourni de façon unifiée par config/database.php `prefix=erik_`, install.sql inchangé
- **Réécriture du refresh token** : logique de rafraîchissement du jeton réécrite dans AuthController de service
- **Portage de DepositLogService côté service** : service/common/service/DepositLogService.php complété (élimine l'une des divergences en double admin/service)

### P2 — Observabilité / extension / expérience

16. **Côté C HarmonyOS** : implémenter de zéro 5 pages (connexion/hall/détail/portefeuille/profil) (H4) — ✅ Terminé (2026-08-18 : `apps/harmonyos/entry/src/main/ets/pages/` 5 pages en dépôt)
17. **Compléments frontend** : page de vérification 2FA, entrées coupons/classements/notifications, UI de recherche ES ; fusionner les sources de routes main.dart/app_pages.dart ; vrai rappel OAuth ; couche de chiffrement AES frontend
18. **Migration du calcul de probabilités vers ClickHouse** ou table de statistiques matérialisées MySQL + cache ; rétention recalculée par vraie cohorte
19. **Métriques métier Prometheus** (taux de livraison/consommation d'événements, profondeur de file) + middleware de répartition AB (réutilisant FeatureFlag) — 🔶 Partiellement terminé (2026-08-18 : `GET /metrics` retraits en attente/confirmations du jour/compteurs emit·consume d'événements ; FeatureFlag `inRollout`/`abTest` en buckets crc32. Profondeur de file non faite)
20. **Boucle fermée de la chaîne de données WebSocket** : confirmation de persistance classements/chat
21. **Alignement documentaire** : correction du nombre de tables/services/description de la couche partagée, alignement de la doc API avec l'implémentation, ajout du CHANGELOG — ✅ Terminé (2026-08-18 : voir `docs/CHANGELOG.fr.md`, FEATURES/VERSIONS/PROJECT-PLAN/rapports d'audit §10)

---

## IV. Portes de qualité (collaboration d'équipe)

- À chaque modification de code : les tests complets admin `vendor/bin/phpunit` doivent passer (sans `|| echo warning`)
- Tout nouveau chemin sensible (paiement/retrait/authentification) doit être accompagné de tests
- Modifier common/model impose la synchronisation des deux côtés admin+service (avant la couche partagée)
- Points forts recommandés par le rapport d'examen : signature ProviderAuth, chiffrement AES, SQL écrit à la main de ProbabilityService

## V. Équipe

L'équipe game-platform (6 membres : researcher/architect/backend-dev/frontend-dev/tester/reviewer) est prête, elle peut exécuter P0 directement.
