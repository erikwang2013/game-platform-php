# common/ — Bibliothèque partagée de l'admin
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · **Français** · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Le répertoire du code partagé du backend admin (`admin/`). Les `common\service\*` ont été extraits dans le paquet partagé **erik/platform-common** (`packages/platform-common`) ; ne placez pas de classes PHP dans ce répertoire, elles masqueraient l'autoload du paquet. Voir `packages/platform-common/README.md`.

## Fonctionnalités

| Catégorie | Emplacement | Description |
|------|------|------|
| Modèles | `app\model\*` | Modèles de données (utilisateurs/commandes/jeux, etc.) |
| Services | `common\service\*` | Services métier partagés (dans le paquet erik/platform-common) : DepositLogService (audit des dépôts + revenus/conversion), GameDashboardService (tableau de bord d'exploitation), ProbabilityService (analyse de probabilité), GamePlayLogService (écriture des journaux d'activité de jeu) |
| Middleware | `app\middleware\*` | Cors / SecurityFilter / RateLimit / AdminAuth / AdminPermission / OperationLog |

## Installation

Faisant partie du projet admin, les dépendances sont déjà déclarées dans `admin/composer.json` (y compris le dépôt path `../packages/platform-common`) et sont installées automatiquement par `composer install` ; aucune installation séparée n'est nécessaire :

```bash
cd admin && composer install
```

## Utilisation

- L'espace de noms `app\...` correspond au code du projet admin lui-même, ex. : `use app\model\User;`
- L'espace de noms `common\...` correspond au paquet partagé erik/platform-common (PSR-4 → `src/`), ex. :

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```
