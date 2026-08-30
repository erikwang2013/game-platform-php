# common/ — Biblioteca compartilhada de administração
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · **Português** · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

O diretório de código compartilhado do backend admin (`admin/`). Os `common\service\*` foram extraídos para o pacote compartilhado **erik/platform-common** (`packages/platform-common`); não coloque classes PHP neste diretório, pois elas fariam sombra ao autoload do pacote. Consulte `packages/platform-common/README.md`.

## Funcionalidades

| Categoria | Localização | Descrição |
|------|------|------|
| Modelos | `app\model\*` | Modelos de dados (usuários/pedidos/jogos, etc.) |
| Serviços | `common\service\*` | Serviços de negócio compartilhados (no pacote erik/platform-common): DepositLogService (auditoria de depósitos + receita/conversão), GameDashboardService (painel de operações), ProbabilityService (análise de probabilidades), GamePlayLogService (gravação de registros de atividade de jogo) |
| Middleware | `app\middleware\*` | Cors / SecurityFilter / RateLimit / AdminAuth / AdminPermission / OperationLog |

## Instalação

Como parte do projeto admin, as dependências já estão declaradas em `admin/composer.json` (incluindo o repositório path `../packages/platform-common`) e são instaladas automaticamente pelo `composer install`; nenhuma instalação separada é necessária:

```bash
cd admin && composer install
```

## Uso

- O namespace `app\...` corresponde ao código do próprio projeto admin, ex.: `use app\model\User;`
- O namespace `common\...` corresponde ao pacote compartilhado erik/platform-common (PSR-4 → `src/`), ex.:

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```
