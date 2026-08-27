# Changelog
<!-- lang-nav -->

Languages: [中文](CHANGELOG.md) · [English](CHANGELOG.en.md) · [한국어](CHANGELOG.ko.md) · [Русский](CHANGELOG.ru.md) · [Deutsch](CHANGELOG.de.md) · **Français** · [Español](CHANGELOG.es.md) · [Português](CHANGELOG.pt.md) · [हिन्दी](CHANGELOG.hi.md) · [العربية](CHANGELOG.ar.md) · [বাংলা](CHANGELOG.bn.md) · [Bahasa Indonesia](CHANGELOG.id.md) · [日本語](CHANGELOG.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Journal des modifications lisible par les humains. PHP n'importe pas ce fichier. Correspond à PROJECT-PLAN P2-21.

## [1.1] — 2026-08-07

- Intégration du plugin Redis, services d'analyse, repli Redis, corrections de tests.

## [1.1] security / ops — 2026-08-18

### Sécurité

- Rappels de paiement : liste blanche des providers (stripe/paypal), vérification de signature fail-closed, contrôle des montants, enregistrement transactionnel, tolérance d'horodatage Stripe ±300s contre la relecture.
- JWT : refus de démarrage si `JWT_SECRET_KEY` / `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY` manquent ou gardent leur valeur par défaut.
- Apple id_token : vérification JWKS (RS256) + aud/iss/exp.
- Webhook : URL publiques https uniquement, refus des adresses internes/réservées (SSRF).
- 2FA : HMAC TOTP avec clé décodée en Base32 RFC 4648 ; verrouillage par utilisateur sur `/api/2fa/verify` (5 échecs / 15 minutes, fail-closed en cas de panne Redis).
- Retraits : bascule atomique de l'état via UPDATE conditionnel pour validation/paiement ; double validation optionnelle (`withdraw.require_dual_review`) ; verrou utilisateur Redis côté demande contre le dépassement des plafonds en concurrence.
- Limitation de débit : fail-closed en cas de panne Redis.

### Disponibilité

- Montage des 12 routes `/admin/analytics/*` des services d'analyse admin.
- Suppression du préfixe `erik_` codé en dur dans les modèles ; audit DepositLog en base ; suppression du modèle Test.

### Observabilité

- `GET /metrics` ajoute retraits en attente de validation, recharges confirmées du jour (COUNT avec cache Redis 30s), compteurs d'émission/consommation d'événements, `memory_usage`, `info version=1.1`.
- FeatureFlag : `inRollout` / `abTest` répartis en buckets par crc32 lisant `feature.{name}_percent`.
- EventBus : INCR sur `metrics:event_emit_total` / `metrics:event_consume_total` à l'`emit` / `consume`.

### Clients / partagé (complété le même jour)

- Flutter Platform : table de routes `app_pages.dart` ; ajout configuration/vérification 2FA, coupons, classements, notifications, page de rappel OAuth ; l'entrée du hall est reliée à la navigation.
- Côté C HarmonyOS : `apps/harmonyos/` cinq pages (connexion/hall/détail/portefeuille/profil), `BASE_URL` par défaut vers service `8788`.
- Couche partagée : `packages/platform-common` (path repo `erik/platform-common`) extrait DepositLog / GameDashboard / Probability / GamePlayLog ; les modèles restent en double.
- ClickHouse : dépendance composer retirée ; l'analyse continue via agrégation MySQL en temps réel.
- CI : jobs phpunit séparés pour admin / service, blocage en cas d'échec.

### Lacunes restantes

- Les **modèles** admin/service restent en double (seuls certains `common/service` sont dans le package path).
- `webman/queue` non câblé ; probabilités/rétention non migrées vers OLAP.
- Des parties de PROJECT-PLAN / VERSIONS / rapports d'audit peuvent encore être en retard sur ce CHANGELOG ; ce fichier et le disque font foi.

## [1.1] resilience — 2026-08-27

### Stabilité

- Couche partagée : ajout de `CircuitBreaker` (état dans Redis, seuil 5 / fenêtre 30 s, fail-open si Redis indisponible) et `Retry` (backoff exponentiel, uniquement exceptions réseau, max 5 tentatives), dans `packages/platform-common/src/`.
- Interrupteur de dégradation `feature.provider_mock` : PushService (FCM/APNs/HarmonyOS), PayoutService (PayPal), ThirdPartyProvider court-circuitent quand `on`, sans appels réseau réels.
- Correction de 11 défauts de type `getenv($name, '')` (TypeError sous strict_types) ; contrôle mock de PushService déplacé dans try/catch.
- Nouveaux tests : CircuitBreakerTest / RetryTest / ResilienceMockTest ; suite service 45 → 60 cas, tous verts (rapport : [test-reports/resilience.md](test-reports/resilience.md)).
