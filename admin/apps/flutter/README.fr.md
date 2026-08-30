# admin_app — Frontend web du panneau d'administration (Flutter)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · **Français** · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Le frontend web du panneau d'administration, basé sur Flutter 3.x, avec la mise en page classique d'un back-office PC (barre latérale + barre supérieure + zone de contenu). Il couvre toutes les pages de gestion nécessaires à l'exploitation de la plateforme de jeux : tableau de bord, utilisateurs, rôles et permissions, jeux, paiements, retraits, VIP, succès, annonces, CDN, gestion des risques, vérification d'identité, journaux d'opérations, etc.

## Liste des fonctionnalités

| Module | Description |
|------|------|
| Tableau de bord | Vue d'ensemble des données d'exploitation |
| Rapports | Récapitulatif des rapports/quotidien/export CSV |

| Connexion | Connexion administrateur (avec 2FA) |
| Gestion des utilisateurs | Recherche et gestion des utilisateurs |
| Utilisateurs de la plateforme | Détails, statut et opérations de solde |
| Rôles et permissions | Attribution des rôles et permissions |
| Configuration système | Configuration des paramètres de la plateforme |
| Gestion des jeux | Liste, publication/arrêt et catégories des jeux |
| Gestion des paiements | Dépôts, moyens de paiement et journaux de callback |
| Gestion des retraits | Validation et paiement des retraits |
| Gestion VIP | Configuration des niveaux et avantages VIP |
| Gestion des succès | Définitions des succès et progression |
| Gestion des annonces | Publication et arrêt des annonces |
| Gestion CDN | Configuration des fournisseurs CDN et domaines |
| Gestion des risques | Règles de risque et journaux de blocage |
| Vérification d'identité | Validation des informations de nom réel |
| Journal des opérations | Audit des actions administrateur |
| Profil | Profil administrateur et paramètres de sécurité |

## Exigences

- Flutter SDK 3.x

## Installation et exécution

```bash
cd admin/apps/flutter

# Installer les dépendances
flutter pub get

# Exécuter en développement (Chrome)
flutter run -d chrome

# Spécifier l'adresse du backend (défaut http://localhost:8787)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8787

# Build web de production (sortie dans build/web/)
flutter build web
```

## Utilisation

1. Démarrez d'abord le service backend du panneau d'administration : `cd admin && php start.php start -d` (port par défaut 8787)
2. Connectez-vous avec le compte administrateur créé par l'assistant d'installation (2FA pris en charge)
3. Le frontend utilisateur se trouve dans `apps/flutter/platform/` et partage le même service backend (port par défaut 8788)
