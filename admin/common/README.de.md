# common/ — Gemeinsame Admin-Bibliothek
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · **Deutsch** · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Das Verzeichnis für gemeinsamen Code des Admin-Backends (`admin/`). `common\service\*` wurden in das gemeinsame Paket **erik/platform-common** (`packages/platform-common`) ausgelagert; legen Sie hier keine PHP-Klassen ab, da sie das Autoloading des Pakets überschatten würden. Siehe `packages/platform-common/README.md`.

## Funktionsübersicht

| Kategorie | Speicherort | Beschreibung |
|------|------|------|
| Modelle | `app\model\*` | Datenmodelle (Benutzer/Bestellungen/Spiele usw.) |
| Dienste | `common\service\*` | Gemeinsame Geschäftsdienste (im Paket erik/platform-common): DepositLogService (Einzahlungs-Audit + Umsatz/Konversion), GameDashboardService (Operations-Dashboard), ProbabilityService (Wahrscheinlichkeitsanalyse), GamePlayLogService (Schreiben von Spielaktivitäts-Logs) |
| Middleware | `app\middleware\*` | Cors / SecurityFilter / RateLimit / AdminAuth / AdminPermission / OperationLog |

## Installation

Als Teil des Admin-Projekts sind die Abhängigkeiten bereits in `admin/composer.json` deklariert (inklusive Path-Repository `../packages/platform-common`) und werden von `composer install` automatisch installiert; eine separate Installation ist nicht erforderlich:

```bash
cd admin && composer install
```

## Verwendung

- Der Namespace `app\...` entspricht dem Code des Admin-Projekts selbst, z. B.: `use app\model\User;`
- Der Namespace `common\...` entspricht dem gemeinsamen Paket erik/platform-common (PSR-4 → `src/`), z. B.:

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```
