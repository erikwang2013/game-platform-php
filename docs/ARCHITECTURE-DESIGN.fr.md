# Document de conception d'architecture
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE-DESIGN.md) · [English](ARCHITECTURE-DESIGN.en.md) · [한국어](ARCHITECTURE-DESIGN.ko.md) · [Русский](ARCHITECTURE-DESIGN.ru.md) · [Deutsch](ARCHITECTURE-DESIGN.de.md) · **Français** · [Español](ARCHITECTURE-DESIGN.es.md) · [Português](ARCHITECTURE-DESIGN.pt.md) · [हिन्दी](ARCHITECTURE-DESIGN.hi.md) · [العربية](ARCHITECTURE-DESIGN.ar.md) · [বাংলা](ARCHITECTURE-DESIGN.bn.md) · [Bahasa Indonesia](ARCHITECTURE-DESIGN.id.md) · [日本語](ARCHITECTURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Objectifs de conception

Construire une plateforme d'agrégation de jeux mondiale, universelle et internationalisée. Exigences clés :

- L'utilisateur peut recharger, échanger des devises de jeu, jouer, gagner des devises de jeu et retirer
- La plateforme gère de façon unifiée plusieurs jeux (propriétaires + tiers), chaque jeu ayant ses devises et taux indépendants
- Le backend fournit des capacités complètes de validation, d'interrupteurs et de gestion des risques
- Prise en charge de l'exploitation mondialisée multilingue, multi-devises, multi-canaux de paiement

## 2. Choix d'architecture

### 2.1 Pourquoi un monolithe modulaire plutôt que des microservices ?

À ce stade, choix du monolithe modulaire (Modular Monolith) :

| Considération | Monolithe modulaire | Microservices |
|------|----------|--------|
| Efficacité de développement | Appels dans le même processus, pas de RPC | Gestion de la latence réseau, sérialisation |
| Cohérence transactionnelle | Transactions locales en base | Transactions distribuées (complexes) |
| Complexité d'exploitation | Déploiement en processus unique | Orchestration multi-services, découverte de services |
| Extensibilité | Peut être découpé en microservices plus tard | Support natif de la mise à l'échelle indépendante |
| Taille de l'équipe | Adapté aux petites équipes (1-5 personnes) | Adapté aux équipes multiples en développement parallèle |

**Décision** : admin/ (backend d'administration) et service/ (métier côté C) sont deux instances webman indépendantes, déployables sur la même machine (ports différents) ou séparément. La couche partagée common/ élimine la duplication de code via l'autoload PSR-4. À l'avenir, si le volume métier augmente, service/ peut être découpé en plusieurs microservices (service utilisateur, service portefeuille, service jeux).

### 2.2 Pourquoi webman v2 plutôt que le PHP-FPM traditionnel ?

| Considération | webman v2 | PHP-FPM (Laravel) |
|------|-----------|-------------------|
| Performance | Résident en mémoire, support des coroutines | Chargement de tous les fichiers à chaque requête |
| Concurrence | Des dizaines de milliers de QPS par machine | Des centaines de QPS par machine |
| Déploiement | Simple, un processus multi-workers | Configuration Nginx + PHP-FPM complexe |
| Écosystème | Compatible avec les composants Laravel Illuminate | Écosystème complet |

**Décision** : la plateforme de jeux doit traiter des rappels de recharge, requêtes d'échange et règlements de jeux à forte concurrence ; la résidence en mémoire et la haute concurrence de webman sont plus adaptées. En même temps, compatible avec l'ORM, Queue et autres composants Laravel, l'efficacité de développement ne cède rien aux frameworks traditionnels.

### 2.3 Pourquoi le style Flutter Web PC ?

- Un seul code compilable vers Web (PC), iOS, Android, HarmonyOS
- La bibliothèque de composants Material 3 est mature, la mise en page PC barre latérale + barre supérieure est prête à l'emploi
- Partage de la couche de logique métier avec le client HarmonyOS
- Évite de maintenir deux codebases frontend React/Vue + Flutter

## 3. Décisions techniques clés

### 3.1 Système d'ID

```
Snowflake génère un BIGINT (unique distribué en interne)
    ↓
Hashids encode en chaîne courte (ID réel non rétro-déductible de l'extérieur)
    ↓
La chaîne hashid est transmise dans les requêtes/réponses API
```

**Raison** :
- Snowflake est globalement unique, à tendance croissante favorable aux index, sans exposer le volume métier
- Hashids empêche l'énumération des données via des ID auto-incrémentés et la divulgation de l'échelle

### 3.2 Précision des devises

Les devises de plateforme et de jeu utilisent de façon unifiée la précision `DECIMAL(18,4)` ; côté PHP, toutes les opérations de montant utilisent la famille de fonctions `bcmath` (bcadd/bcsub/bcmul/bcdiv/bccomp).

**Raison** : les nombres flottants (float/double) présentent des erreurs de précision inacceptables dans un contexte financier. DECIMAL + bcmath garantit des calculs exacts.

### 3.3 Verrou optimiste du portefeuille

```sql
UPDATE erik_user_wallet 
SET balance = balance + ?, version = version + 1 
WHERE user_id = ? AND version = ?
```

Nouvel essai automatique en cas d'échec de mise à jour (5 fois maximum).

**Raison** :
- Recharge, échange et retrait peuvent accéder en concurrence au même portefeuille
- Le verrou pessimiste (SELECT FOR UPDATE) est peu performant en haute concurrence
- Le verrou optimiste est bien plus performant que le verrou pessimiste dans les scénarios à faible taux de conflit

### 3.4 Processus de validation des retraits

```
L'utilisateur lance un retrait
  ├─ Interrupteur global éteint → refus
  ├─ Montant < seuil de validation automatique → approbation automatique
  └─ Montant >= seuil → validation manuelle → approbation/refus (refus : retour des devises de plateforme)
```

**Raison** :
- L'interrupteur global sert à la gestion d'urgence des risques (vulnérabilité découverte, trafic anormal)
- L'approbation automatique des petits montants réduit le coût humain et améliore l'expérience utilisateur
- La validation manuelle des gros montants prévient le blanchiment et la fraude

### 3.5 Modèle d'écart d'échange

Chaque devise de jeu a un `exchange_rate` indépendant (1 devise de plateforme = X devises de jeu) et un `spread_pct` (commission % de la plateforme).

À l'achat : devises de jeu créditées = devises de plateforme × taux × (1 - commission %)
À la vente : devises de plateforme créditées = devises de jeu ÷ taux × (1 - commission %)

**Raison** :
- Les revenus de la plateforme proviennent de l'écart d'échange, pas de paiements in-game
- Les taux indépendants soutiennent des stratégies de prix différentes par jeu
- Le pourcentage d'écart est ajustable à volonté pour une exploitation fine

## 4. Architecture de sécurité

Sur la base des 18 couches de défense en profondeur existantes, de nouvelles couches de protection pour la plateforme de jeux :

| Couche | Mesure | Raison |
|------|------|------|
| Sécurité de concurrence | Verrou optimiste version du portefeuille | Empêche les débits/crédits en double |
| Sécurité des retraits | Interrupteur global + seuil de montant + plafonds jour/mois + vérification poster-php | Défense multicouche, réduction du risque financier |
| Sécurité des échanges | Séparation cotation et exécution, cotation expirant en 60 s | Empêche l'arbitrage lié aux fluctuations de taux |
| Sécurité des jeux | Vérification de signature des rappels tiers + liste blanche IP + défense contre la relecture | Empêche la falsification des règlements de jeux |
| Gestion des risques | Moteur de règles (liste noire IP, alerte gros montants, fréquence anormale) | Blocage temps réel des transactions suspectes |

## 5. Conception de l'internationalisation

### 5.1 Détection de la langue

```
Requête entrante
  ↓
LanguageMiddleware (middleware global)
  ├── 1. En-tête X-Language
  ├── 2. En-tête Accept-Language (zh → zh-CN, en → en-US)
  └── 3. Défaut en-US
  ↓
TranslationService::setLocale($locale)
  ↓
Fonction __() dans le contrôleur ou TranslationService::trans() pour obtenir le texte traduit
```

### 5.2 Stockage des traductions

- La table `erik_translation` stocke tous les textes traduits (group + key + lang_code + value)
- Première requête : chargement complet de la base vers Redis (clé : `i18n:translations`, TTL : 1 heure)
- Requêtes suivantes : lecture directe depuis Redis, accéléré par le cache mémoire
- Le backend d'administration peut étendre une page de gestion des traductions (implémenté dans l'édition complète)

### 5.3 Nommage des clés de traduction

Format : `group.key`, ex. `auth.login_success`, `wallet.insufficient_balance`

| Groupe | Domaine |
|------|------|
| auth | Authentification |
| wallet | Portefeuille |
| exchange | Échange |
| withdraw | Retrait |
| deposit | Recharge |
| game | Jeux |
| admin | Backend d'administration |
| error | Messages d'erreur |

### 5.4 Stratégie de repli

- La langue de la requête a une traduction → l'utiliser
- Pas de traduction pour la langue de la requête → repli sur en-US
- en-US non plus → retourner la clé d'origine

### 5.5 i18n frontend

- Flutter utilise un `AppTranslations` maison + `LocaleController` (GetX)
- La préférence de langue est persistée dans SharedPreferences
- Le changement de langue déclenche le re-rendu global de l'UI via `Get.updateLocale()`
- La classe `StringResult` exploite `toString()` de Dart pour une syntaxe d'interpolation naturelle : `Text('${AppTranslations.t("key")}')`

## 6. Nouvelles conceptions de l'édition standard

### 6.1 Moteur de gestion des risques

Contrôles de règles multicouches avant les opérations financières clés :

```
Requête de recharge/retrait/échange
  ↓
RiskService::check(userId, type, context)
  ├── Détection liste noire IP (ip_blacklist) → block
  ├── Détection d'anomalie de montant (amount_anomaly) → warn
  ├── Détection de fréquence (frequency) → warn/block
  └── Détection de vitesse (velocity) → block
  ↓
passed → exécution normale
warn   → journalisation, poursuite de l'exécution
block  → refus de l'opération
```

Les règles sont stockées dans la table `erik_risk_rule`, configurées en JSON, avec seuils et actions ajustables dynamiquement.

### 6.2 KYC de vérification d'identité

Système de certification à trois niveaux :
- `default` — non certifié, plafonds de base
- `verified` — validation KYC passée, plafonds relevés + frais réduits
- `vip` — niveau VIP, plafonds maximaux + zéro frais

Processus de certification :
```
L'utilisateur soumet les informations d'identité → status=pending
Validation par l'administrateur → approve/reject
approve → l'utilisateur passe automatiquement au niveau verified
reject → l'utilisateur peut resoumettre
```

### 6.3 Connexion OAuth tiers

Prise en charge de la connexion Google / Facebook / Apple :

```
Clic sur le bouton OAuth dans le frontend
  → GET /api/auth/oauth/{provider} → obtention de l'URL d'autorisation
  → redirection vers la page d'autorisation tierce → consentement de l'utilisateur
  → rappel POST /api/auth/oauth/{provider}/callback
  → liaison existante trouvée → connexion directe
  → pas de liaison → inscription automatique d'un nouvel utilisateur + liaison + création du portefeuille
```

### 6.4 Rappels de paiement

```
Paiement tiers terminé → POST /api/payment/callback
  → contrôle de la liste blanche des providers (stripe/paypal uniquement)
  → vérification de signature fail-closed (secret/webhook_id non configuré, échec de vérification, horodatage au-delà de ±300 s : refus systématique)
  → contrôle bccomp du montant du rappel avec le montant de la commande (anti-usurpation inter-canaux)
  → mise à jour de l'état de la commande en confirmed (transactionnelle, rollback si le crédit échoue)
  → UserWallet::addBalance crédite
  → enregistrement de la Transaction
  → contrôle des risques RiskService::check
```

### 6.5 Plafonds de retrait par paliers

Plafonds et frais différents selon le niveau KYC de l'utilisateur :

| Niveau | Plafond par opération | Plafond journalier | Plafond mensuel | Frais |
|------|---------|--------|--------|--------|
| default | 1 000 | 10 000 | 50 000 | 1,00 % |
| verified | 5 000 | 50 000 | 200 000 | 0,50 % |
| vip | 20 000 | 200 000 | 1 000 000 | 0,00 % |

## 7. Conception de l'extensibilité

### 7.1 Extension horizontale

admin/ et service/ prennent en charge les processus multi-workers. Avec le proxy inverse Nginx, plusieurs machines peuvent être déployées pour une extension horizontale :

```
Nginx (équilibrage de charge)
  ├── admin-1 (:8787)
  ├── admin-2 (:8787)
  ├── service-1 (:8788)
  └── service-2 (:8788)
```

### 7.2 Chemin de découpage en modules

Lorsque service/ unique devient un goulot d'étranglement, découper selon ce chemin :

```
service/ (monolithe)
  → service-user/ (service utilisateur :8788)
  → service-wallet/ (service portefeuille :8789)
  → service-game/ (service jeux :8790)
  → service-payment/ (service paiement :8791)
```

Critères pour décider du découpage :
- Le QPS d'un module dépasse la capacité d'une machine
- Un module a besoin d'une pile technique ou d'une stratégie de déploiement indépendante
- L'équipe grandit au point de devoir développer différents modules en parallèle
