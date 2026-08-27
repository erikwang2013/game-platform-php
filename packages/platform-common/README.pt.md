# erik/platform-common
<!-- lang-nav -->

Languages: **中文** · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)


Compartilha `common\service\*`, referenciado por admin/ e service/ via repositório path do Composer.

## Serviços

- DepositLogService — auditoria de recarga + receita/conversão
- GameDashboardService — dashboard operacional
- ProbabilityService — análise de probabilidade
- GamePlayLogService — gravação de log de comportamento de jogo

Depende do host fornecer `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`.

## Integração

```bash
cd admin && composer update erik/platform-common
cd ../service && composer update erik/platform-common
```

## Cópias duplas restantes

app/model/*, app/common/*Service, a maioria de app/service/*, EventBus ainda são copiados nos dois lados.
