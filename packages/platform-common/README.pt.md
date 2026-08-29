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

## Mascote do projeto

![Mascote do projeto: Dicey](../../docs/mascot.svg)

**Dicey** — Mascote da plataforma. O dado representa os jogos e a jogabilidade baseada em probabilidade, a moeda a economia da plataforma e os múltiplos gateways de pagamento, e o roxo reflete a marca do painel administrativo. Arquivo SVG: `docs/mascot.svg`, escalável infinitamente para documentação, logotipos e produtos.
