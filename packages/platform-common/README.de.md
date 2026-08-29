# erik/platform-common

## Projekt-Maskottchen

<img src="../../docs/mascot.svg" width="120" alt="Dicey"/>

**Dicey** — Plattform-Maskottchen. Der Würfel steht für Spiele und wahrscheinlichkeitsbasiertes Gameplay, die Münze für die Plattform-Ökonomie und die Multi-Payment-Gateways, das Lila spiegelt das Admin-Branding wider. SVG-Quelle: `docs/mascot.svg`, unbegrenzt skalierbar für Doku, Logos und Merchandise.
<!-- lang-nav -->

Languages: **中文** · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

Teilt `common\service\*`, auf das admin/ und service/ über Composer-Path-Repositorys verweisen.

## Dienste

- DepositLogService — Einzahlungs-Audit + Umsatz/Konversion
- GameDashboardService — Betriebs-Dashboard
- ProbabilityService — Wahrscheinlichkeitsanalyse
- GamePlayLogService — Schreiben von Spielverhaltens-Logs

Der Host stellt `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log` bereit.

## Anbindung

```bash
cd admin && composer update erik/platform-common
cd ../service && composer update erik/platform-common
```

## Verbleibende Doppelkopien

app/model/*, app/common/*Service, die meisten app/service/*, EventBus sind weiterhin auf beiden Seiten dupliziert.

