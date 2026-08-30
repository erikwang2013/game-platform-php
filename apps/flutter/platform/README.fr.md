# game_platform — Plateforme utilisateur (Flutter Web)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · **Français** · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Le frontend Web de la plateforme utilisateur (côté C), basé sur Flutter 3.x, offre aux utilisateurs l'expérience complète de la plateforme d'agrégation de jeux : inscription et connexion, hall de jeux, portefeuille, dépôt, retrait, change, classements, coupons, notifications, chat, amis et tickets d'assistance.

## Fonctionnalités

| Module | Description |
|------|------|
| Connexion/inscription | Identifiant + mot de passe / OAuth / 2FA |
| Hall de jeux | Liste/catégories/recherche de jeux |
| Portefeuille | Soldes et transactions des jetons de plateforme/monnaie de jeu |
| Dépôt | Choix du moyen de paiement, redirection vers le paiement par passerelle |
| Retrait | Demande de retrait, suivi du statut |
| Change | Change en temps réel jeton de plateforme ⇄ monnaie de jeu |
| Classements | Jour/semaine/mois/tout temps |
| Coupons | Obtention et utilisation |
| Notifications | Messages in-app (dépôt/retrait/coupons, etc.) |
| Chat | Messages WebSocket en temps réel |
| Amis | Système d'amis |
| Tickets | Création et réponse aux tickets d'assistance |
| Profil | Édition du profil/paramètres de sécurité |

## Prérequis

- Flutter SDK 3.x

## Installation et exécution

```bash
cd apps/flutter/platform

# Installer les dépendances
flutter pub get

# Exécution en développement (Chrome)
flutter run -d chrome

# Spécifier l'adresse du backend (par défaut http://localhost:8788)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8788

# Build Web de production (sortie dans build/web/)
flutter build web
```

## Utilisation

1. Démarrez d'abord le backend : `cd service && php start.php start -d` (port par défaut 8788)
2. Créez un compte et connectez-vous (identifiant + mot de passe, OAuth et 2FA sont pris en charge)
3. Après le dépôt, jouez avec les jetons de plateforme et changez-les en monnaie de jeu ; la monnaie de jeu peut être reconvertie dans le portefeuille pour retrait
4. Le backend admin se trouve dans le répertoire `admin/` (y compris le frontend Flutter Web `admin/apps/flutter/`)
