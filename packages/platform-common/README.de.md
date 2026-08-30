# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · **Deutsch** · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Die gemeinsame `common\service\*`-Schicht, die von admin/ und service/ verwendet wird und über ein Composer-Path-Repository auf den lokalen Quellcode verweist.

## Dienste

| Dienst | Beschreibung |
|------|------|
| DepositLogService | Einzahlungs-Audit + Umsatz/Konversion |
| GameDashboardService | Betriebs-Dashboard |
| ProbabilityService | Wahrscheinlichkeitsanalyse |
| GamePlayLogService | Schreiben von Spielverhaltens-Logs |
| CircuitBreaker / Retry | Stabilitätsmechanismen (Circuit Breaker/Retry) |

Der Host stellt `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log` bereit.

## Installation

Paketname `erik/platform-common`. Sowohl admin/ als auch service/ haben das Path-Repository (`../packages/platform-common`) bereits in composer.json konfiguriert, sodass es mit `composer install` automatisch installiert wird; ein separates Update aus admin/ oder service/ ist ebenfalls möglich:

```bash
composer update erik/platform-common
```

Falls in Packagist veröffentlicht, kann es auch direkt installiert werden:

```bash
composer require erik/platform-common
```

## Verwendung

Namespace `common\` (PSR-4 → `src/`):

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```

## Ein-Klick-Installation

Wird automatisch vom Ein-Klick-Installationsassistenten der Plattform (`install/`) erledigt: Der Assistent führt `composer install` für admin/ und service/ aus, die Path-Repository-Abhängigkeit wird automatisch installiert; manuelle Konfiguration ist nicht erforderlich.

## Verbleibende Doppelkopien

`app/model/*`, `app/common/*Service`, die meisten `app/service/*` und EventBus sind weiterhin auf beiden Seiten dupliziert.
