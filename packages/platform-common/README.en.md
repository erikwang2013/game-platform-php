# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · **English** · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)


Shares `common\service\*`, referenced by admin/ and service/ via a Composer path repository.

## Services

- DepositLogService — deposit audit + revenue/conversion
- GameDashboardService — operations dashboard
- ProbabilityService — probability analysis
- GamePlayLogService — game behavior log writing

Dependencies provided by the host: `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`.

## Integration

```bash
cd admin && composer update erik/platform-common
cd ../service && composer update erik/platform-common
```

## Remaining dual copies

app/model/*, app/common/*Service, most app/service/*, EventBus are still duplicated on both sides.

## Project Mascot

![Project mascot: Dicey](../../docs/mascot.svg)

**Dicey** — Platform mascot. The die represents games and probability-based gameplay, the coin represents the platform economy and multi-payment gateways, and the purple palette echoes the admin branding. SVG source: `docs/mascot.svg`, infinitely scalable for docs, logos and merchandise.
