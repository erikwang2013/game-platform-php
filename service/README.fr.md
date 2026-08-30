# service/ — API du service plateforme utilisateur (côté C)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · **Français** · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

L'API du service plateforme utilisateur (côté C) est un backend PHP haute performance basé sur webman v2 (Workerman). Il offre aux utilisateurs l'ensemble des fonctionnalités de la plateforme d'agrégation de jeux : inscription et connexion, portefeuille, dépôt, retrait, change, jeux, classements, coupons, tickets d'assistance, VIP, succès, fonctionnalités sociales et annonces.

## Fonctionnalités

| Module | Description |
|------|------|
| Utilisateurs | Inscription/connexion (identifiant + mot de passe + OAuth 7 plateformes + 2FA TOTP), profil |
| Portefeuille | Portefeuille de jetons de plateforme (verrou optimiste) + portefeuille de monnaie de jeu + historique des transactions |
| Dépôt | 13 passerelles de paiement (Stripe/PayPal/NowPayments/Coinbase, etc.), vérification de signature des callbacks, crédit automatique |
| Retrait | Demande → validation → versement, limites KYC par paliers |
| Change | Cotations en temps réel jeton de plateforme ⇄ monnaie de jeu, remises VIP et bonus de taux |
| Jeux | Liste/catégories/recherche de jeux, historique de jeu, callbacks de règlement Provider |
| Classements | Jour/semaine/mois/tout temps + push WebSocket en temps réel |
| Coupons | Montant fixe + remise en pourcentage, limités dans le temps et la quantité |
| Tickets | Création/réponse aux tickets d'assistance par l'utilisateur |
| VIP | 5 niveaux de fidélité, accumulation d'expérience, remises sur le change |
| Succès | 12 succès intégrés, détection pilotée par événements |
| Social | Système d'amis + messages privés WebSocket en temps réel |
| Annonces | Annonces in-app + notifications/e-mail |

## Stack technique

- PHP 8.3+ / webman v2 (workerman/webman)
- MySQL 8.0+ (préfixe de table `game_`, clés primaires BIGINT sans auto-incrément)
- Redis (Session / Cache / Limitation de débit)
- ClickHouse (analyses OLAP / calculs de probabilité)
- Elasticsearch (recherche plein texte)
- Authentification JWT + signature Provider HMAC-SHA256

## Structure du projet

```
service/
├── app/
│   ├── api/v1/controller/  # Contrôleurs API côté C (35)
│   ├── middleware/         # Middleware (Cors/SecurityFilter/RateLimit/ApiVersion/UserAuth/ProviderAuth)
│   ├── model/              # Modèles de données
│   ├── service/            # Services métier (VIP/classements/risque/notifications, etc.)
│   ├── event/              # Bus d'événements (EventBus Redis Pub/Sub)
│   ├── provider/           # Couche Provider de jeux
│   └── payment/            # Passerelles de paiement
├── common/                 # Services partagés (implémentés dans le paquet erik/platform-common)
├── config/                 # Fichiers de configuration
├── public/                 # Point d'entrée Web
├── tests/                  # Tests PHPUnit
├── start.php               # Point d'entrée de démarrage
└── composer.json
```

## Installation en un clic

Utilisez l'assistant d'installation en un clic à la racine du projet (à exécuter depuis la racine) :

```bash
# 1. Démarrer l'assistant d'installation
php -S 0.0.0.0:8888 -t install/

# 2. Ouvrir http://localhost:8888 dans le navigateur
#    Suivre l'assistant : vérification de l'environnement → configuration BDD → compte admin → installation automatique
```

Ou démarrez tout avec Docker Compose (racine du projet) :

```bash
docker compose up -d
```

## Installation manuelle

```bash
# 1. Installer les dépendances
cd service && composer install

# 2. Configurer les variables d'environnement
cp .env.example .env
# Modifier .env : connexion BDD, clés JWT, etc.

# 3. Démarrer le service (port par défaut 8788)
php start.php start        # premier plan
php start.php start -d     # arrière-plan (démon)
```

## Utilisation

- Référence API : `docs/API.md` (référence complète)
- Documentation en ligne : http://localhost:8788/apidoc/ (documentation interactive hg/apidoc)
- Vérification de santé : `GET http://localhost:8788/health`
- Frontend côté C : `apps/flutter/platform/` (plateforme utilisateur Flutter Web)
- Backend admin : `admin/` (backend admin et frontend `admin/apps/flutter/`)

## Tests

```bash
cd service
SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
```
