# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · **Português** · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

A camada compartilhada `common\service\*`, usada por admin/ e service/, que referencia o código-fonte local via repositório path do Composer.

## Serviços

| Serviço | Descrição |
|------|------|
| DepositLogService | Auditoria de recarga + receita/conversão |
| GameDashboardService | Dashboard operacional |
| ProbabilityService | Análise de probabilidade |
| GamePlayLogService | Gravação de log de comportamento de jogo |
| CircuitBreaker / Retry | Mecanismos de resiliência (disjuntor/tentativa) |

Depende do host fornecer `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`.

## Instalação

Nome do pacote: `erik/platform-common`. Tanto admin/ quanto service/ já configuram o repositório path (`../packages/platform-common`) no composer.json, portanto ele é instalado automaticamente com `composer install`; também é possível atualizá-lo separadamente a partir de admin/ ou service/:

```bash
composer update erik/platform-common
```

Se publicado no Packagist, também pode ser instalado diretamente:

```bash
composer require erik/platform-common
```

## Uso

Namespace `common\` (PSR-4 → `src/`):

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```

## Instalação em um clique

Instalado automaticamente pelo assistente de instalação em um clique da plataforma (`install/`): o assistente executa `composer install` para admin/ e service/, e a dependência do repositório path é instalada automaticamente; nenhuma configuração manual é necessária.

## Cópias duplas restantes

`app/model/*`, `app/common/*Service`, a maioria de `app/service/*` e EventBus ainda são copiados nos dois lados.
