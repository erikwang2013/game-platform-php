# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · **Français** · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

La couche partagée `common\service\*`, utilisée par admin/ et service/, qui référence le code source local via un dépôt de chemin Composer.

## Services

| Service | Description |
|------|------|
| DepositLogService | Audit des dépôts + revenus/conversion |
| GameDashboardService | Tableau de bord opérationnel |
| ProbabilityService | Analyse de probabilités |
| GamePlayLogService | Écriture des journaux de comportement de jeu |
| CircuitBreaker / Retry | Mécanismes de résilience (disjoncteur/relance) |

Dépend de l'hôte pour `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`.

## Installation

Nom du paquet : `erik/platform-common`. admin/ et service/ ont tous deux configuré le dépôt path (`../packages/platform-common`) dans composer.json, il est donc installé automatiquement via `composer install` ; une mise à jour individuelle depuis admin/ ou service/ est également possible :

```bash
composer update erik/platform-common
```

S'il est publié sur Packagist, il peut aussi être installé directement :

```bash
composer require erik/platform-common
```

## Utilisation

Espace de noms `common\` (PSR-4 → `src/`) :

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```

## Installation en un clic

Installé automatiquement par l'assistant d'installation en un clic de la plateforme (`install/`) : l'assistant exécute `composer install` pour admin/ et service/, la dépendance du dépôt path est installée automatiquement ; aucune configuration manuelle n'est nécessaire.

## Doubles restants

`app/model/*`, `app/common/*Service`, la plupart des `app/service/*` et EventBus restent dupliqués des deux côtés.
