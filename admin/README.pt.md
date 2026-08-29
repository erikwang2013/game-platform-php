# Painel administrativo aberto (open-admin)

## Mascote do projeto

<img src="../docs/mascot.svg" width="120" alt="Dicey"/>

**Dicey** — Mascote da plataforma. O dado representa os jogos e a jogabilidade baseada em probabilidade, a moeda a economia da plataforma e os múltiplos gateways de pagamento, e o roxo reflete a marca do painel administrativo. Arquivo SVG: `docs/mascot.svg`, escalável infinitamente para documentação, logotipos e produtos.
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · **Português** · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

Sistema de painel administrativo full-stack baseado em webman v2 + Flutter.

> [English version](README.en.md) | [Diagrama de arquitetura](docs/ARCHITECTURE.pt.md) | [Documento de design](docs/DESIGN.pt.md) | [Arquitetura de segurança](docs/SECURITY.pt.md) | [Referência da API](docs/API.pt.md)

## Lista de funcionalidades

| Domínio de negócio | Funcionalidade | Descrição |
|--------|------|------|
| 🔐 Autenticação | Login/registro/refresh de token/logout | CAPTCHA de clique + JWT + lista negra |
| | Bloqueio de conta | 5 falhas bloqueiam por 15 minutos |
| | Limite de sessões concorrentes | Máximo de 3 Tokens válidos por usuário |
| 📊 Dashboard | Estatísticas em tempo real/gráfico de tendências/gráfico de distribuição/operações recentes | Cache Redis de 5 minutos |
| 📈 Análise de dados | 12 endpoints: visão geral/ranking/DAU/hora/distribuição de comportamento/receita/conversão/probabilidade/retenção/funil/ARPU/métricas econômicas | Agregação em tempo real no MySQL, dados vazios em caso de falha do banco |
| 👥 Gerenciamento de usuários | CRUD + exclusão em lote/habilitar-desabilitar | Soft delete + confirmação de senha |
| | Importação em lote via Excel | Validação linha a linha + relatório de erros |
| 🔒 Permissões de funções | CRUD de funções + árvore de permissões | Autorização RBAC em granularidade method.path |
| ⚙ Configuração do sistema | CRUD de pares chave-valor | Gerenciamento por grupos |
| 📋 Auditoria de operações | Consulta de logs + detecção de origem | Reconhecimento automático de 8 plataformas |
| 📁 Gerenciamento de arquivos | Upload/exportação Excel/exportação PDF | Mascaramento automático de dados sensíveis |
| 🛡 Proteção de segurança | Defesa em profundidade em 18 camadas | XSS/injeção SQL/traversal de caminho/injeção de comandos/CSRF/rate limit/CSP... |
| 🏥 Operações | Health check/metrics/documentação da API/security.txt | Prometheus + OpenAPI 3.0 |

## Stack tecnológica

| Camada | Tecnologia | Descrição |
|---|------|------|
| Framework do backend | webman v2 (workerman) | Framework PHP de processos residentes de altíssimo desempenho |
| Versão do PHP | 8.3+ | |
| Banco de dados | MySQL 8.0+ | Prefixo de tabela `game_`, chave primária BIGINT não auto-incrementável |
| Motor de busca | Elasticsearch | Sincronização e consulta via `webman-scout` |
| Frontend do painel | Flutter 3.x | Versão Web com estilo de painel administrativo PC (`apps/flutter/`) |
| Dispositivos móveis | HarmonyOS ArkTS | Cliente nativo HarmonyOS (`apps/harmonyos/`), suporte a celular/tablet/2em1 |

## Dependências principais

| Pacote | Finalidade |
|---|------|
| `erikwang2013/snowflake-php` | Algoritmo Snowflake para gerar chaves primárias BIGINT globalmente únicas |
| `erikwang2013/hashids` | Criptografia/descriptografia de IDs na camada de API, ocultando os IDs reais do banco |
| `erikwang2013/jwt-webman` | Emissão e validação de tokens de autenticação JWT |
| `erikwang2013/encryption` | Criptografia/descriptografia de dados sensíveis na camada de transporte da interface |
| `erikwang2013/encryptable` | Criptografia/descriptografia automática de campos sensíveis na camada de armazenamento do banco |
| `erikwang2013/webman-scout` | Sincronização de dados com Elasticsearch e busca em texto completo |
| `erikwang2013/season` | Dados de bandeiras de países |
| `erikwang2013/poster-php` | Geração e validação de CAPTCHA de clique + geração de cartazes |
| `phpoffice/phpspreadsheet` | Exportação Excel |
| `barryvdh/laravel-dompdf` | Exportação PDF (baseado em Dompdf) |

## Estrutura do projeto

```
open-admin/
├── app/
│   ├── admin/controller/       # Controladores do painel administrativo
│   │   ├── DashboardController.php # Dashboard (cache Redis)
│   │   ├── UserController.php      # CRUD de usuários + operações em lote
│   │   ├── RoleController.php      # CRUD de funções
│   │   ├── PermissionController.php# CRUD de permissões
│   │   ├── ConfigController.php    # CRUD de configurações do sistema
│   │   ├── LogController.php       # Consulta de logs de operação
│   │   ├── ProfileController.php   # Central pessoal + logout
│   │   ├── ExportController.php    # Exportação Excel/PDF
│   │   ├── ImportController.php    # Importação de usuários via Excel
│   │   ├── UploadController.php    # Upload de arquivos
│   │   ├── HealthController.php    # Health check
│   │   ├── DocsController.php      # Documentação OpenAPI
│   │   └── BaseController.php      # Controlador base
│   ├── api/
│   │   └── v1/controller/          # Controladores da API v1 (versão controlada pelo cabeçalho API-Version)
│   │       ├── CaptchaController.php # CAPTCHA de clique
│   │       └── AuthController.php    # Login/registro/refresh de token
│   ├── common/                 # Classes utilitárias comuns
│   │   ├── HashidsService.php  # Codificação/decodificação de IDs
│   │   ├── SnowflakeService.php# Geração de IDs Snowflake
│   │   └── EncryptionService.php # Criptografia/descriptografia de dados + mascaramento
│   ├── middleware/             # Middlewares
│   │   ├── Cors.php            # CORS
│   │   ├── SecurityFilter.php  # Detecção e bloqueio de ataques (restrição de métodos HTTP/XSS/injeção SQL/traversal de caminho/injeção de comandos/CSRF)
│   │   ├── RateLimit.php       # Rate limit em Redis (janela deslizante + cabeçalhos de resposta)
│   │   ├── ApiVersion.php      # Validação de versão da API
│   │   ├── AdminAuth.php       # Autenticação JWT + lista negra
│   │   ├── AdminPermission.php # Validação de permissões RBAC
│   │   └── OperationLog.php    # Registro automático de logs de operação (inclui detecção de origem)
│   └── model/                  # Modelos de dados
├── apps/
│   ├── flutter/                # Painel administrativo Flutter Web (estilo PC)
│   │   └── lib/app/
│   │       ├── pages/          # 5 páginas completas (dashboard/usuários/funções/configurações/logs/central pessoal)
│   │       ├── services/       # ApiService (interceptor JWT) + AuthService (persistência de Token)
│   │       └── layouts/        # Layout responsivo do painel (sidebar + topbar + área de conteúdo)
│   └── harmonyos/              # Cliente nativo HarmonyOS (refresh silencioso de Token)
├── config/                     # Arquivos de configuração (com comentários em chinês)
│   ├── route.php               # Rotas + política de versão da API
│   ├── middleware.php           # Registro de middlewares globais
│   └── ...                     # Configurações de cada componente
├── install/        # Arquivos de migração SQL (inclui dados iniciais de permissões)
├── public/                     # Ponto de entrada público
├── runtime/                    # Arquivos de runtime
└── vendor/                     # Dependências Composer
```

## Requisitos de ambiente

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (necessário apenas para desenvolvimento do frontend)
- Elasticsearch >= 7.x (opcional, necessário para funcionalidade de busca)

## Início rápido

### 1. Instalar dependências

```bash
composer install
```

### 2. Configurar variáveis de ambiente

Copie e modifique as variáveis de ambiente (opcional; sem configuração, são usados os valores padrão de `config/*.php`):

```bash
cp .env.example .env
```

Itens de configuração principais:

| Variável de ambiente | Descrição | Valor padrão |
|---------|------|--------|
| `JWT_SECRET` | Chave de assinatura JWT | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Salt do Hashids | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | Chave de criptografia da API | valor padrão de 32 bytes |
| `SNOWFLAKE_DATACENTER_ID` | ID do datacenter (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID do nó de trabalho (0-31) | `1` |
| `SCOUT_HOSTS` | Endereço do ES | `http://localhost:9200` |

**Em produção, altere obrigatoriamente todas as chaves para strings aleatórias.**

### 3. Inicializar o banco de dados

Execute os arquivos SQL de `install/` em ordem:

```bash
mysql -u root -p < install/install.sql
```

### 4. Iniciar o serviço

```bash
php start.php start
```

Por padrão, escuta em `http://0.0.0.0:8787`.

### 5. Iniciar o frontend (opcional)

**Painel administrativo Flutter (versão Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Versão Web (estilo de painel administrativo PC)
```

**Cliente HarmonyOS (dispositivos móveis):**

Use o DevEco Studio para abrir o diretório `apps/harmonyos/` e execute em um dispositivo real ou emulador.

### 6. Deploy com Docker Compose em um clique (recomendado para produção)

O projeto oferece uma solução completa de orquestração Docker com 5 serviços: Nginx, PHP (app webman), MySQL, Redis, Elasticsearch.

```bash
# 1. Configurar variáveis de ambiente do Docker
cp .env.docker .env

# 2. Iniciar todos os serviços
docker-compose up -d

# 3. Inicializar o banco de dados (executar dentro do container app)
docker-compose exec app mysql -h mysql -u root -p < install/install.sql

# 4. Acessar
# http://localhost:8787  (webman)
# http://localhost:8080  (proxy reverso Nginx)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, baseado em `php:8.3-cli`
- `docker-compose.yml`: orquestração de 5 serviços, isolamento de rede, persistência de dados em volumes
- `.env.docker`: variáveis de ambiente específicas do ambiente Docker

## Padrões do banco de dados

- **Prefixo de tabela**: `game_`
- **Chave primária**: todas as tabelas usam `id BIGINT UNSIGNED NOT NULL` como chave primária, com **AUTO_INCREMENT desabilitado**
- **Geração de IDs**: os IDs das chaves primárias são gerados pela camada de aplicação via `SnowflakeService::generate()`, únicos de forma distribuída
- **Campos obrigatórios**: toda tabela deve conter `id`, `created_at`, `updated_at`
- **Soft delete**: tabelas que precisam de soft delete adicionam `deleted_at DATETIME DEFAULT NULL`
- **Campos sensíveis**: telefone, e-mail, número de identidade etc. usam o plugin `encryptable` para criptografia/descriptografia automática; os campos do banco usam `VARCHAR(500)` para armazenar o texto cifrado

## Padrões da API

### Formato de resposta unificado

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Códigos de erro de negócio

| Código de erro | Significado | Descrição |
|-------|------|------|
| `0` | Sucesso | |
| `400` | Erro nos parâmetros da requisição | |
| `401` | Não autenticado (Token inválido ou expirado) | |
| `403` | Sem permissão / bloqueio de segurança | Falha na autorização RBAC / detecção de ataque do SecurityFilter |
| `404` | Recurso não encontrado | |
| `422` | Falha na validação de parâmetros | |
| `413` | Corpo da requisição muito grande | Disparado pelo SecurityFilter, acima de 10MB |
| `405` | Método da requisição não permitido | Disparado pelo SecurityFilter, apenas GET/POST/PUT/DELETE/OPTIONS/HEAD |
| `415` | Tipo de mídia não suportado | Disparado pelo SecurityFilter, Content-Type não é JSON |
| `429` | Requisições em excesso | Disparado pelo RateLimit / bloqueio de conta (5 falhas de login bloqueiam por 15 minutos) |
| `500` | Erro interno do servidor | |

### Tratamento de IDs

- **IDs em requisições/respostas**: criptografados como strings usando hashids, sem expor os IDs reais do banco
- **Caminhos da interface**: `GET /admin/user/{hashid}` — o `{id}` no caminho é uma string hashid
- **Armazenamento no banco**: valor BIGINT original, gerado por snowflake

### Versão da API

A versão da API é controlada por cabeçalho de requisição, **não aparece na URL**:

```http
API-Version: v1
```

- Sem o cabeçalho de versão, o padrão é `v1`
- Versões não suportadas retornam `400 Bad Request`
- Para adicionar uma versão, basta criar o diretório `app/api/{version}/controller/` e registrar a nova versão no middleware

### Rate limit

Baseado no algoritmo de janela deslizante em Redis, padrão de 60 requisições/minuto/IP/rota. Interfaces sensíveis são mais estritas:
- Login: 10 requisições/minuto
- Registro: 5 requisições/minuto

Os cabeçalhos de resposta incluem `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. Ao exceder o limite, retorna 429 com `Retry-After`.

### Arquitetura de middlewares

Os middlewares globais são aplicados a todas as requisições, executados em ordem:

```
Cors（pré-processamento de CORS + cabeçalhos de resposta）
  → SecurityFilter（restrição de métodos HTTP/tamanho do corpo/validação de Content-Type/XSS/injeção SQL/traversal de caminho/injeção de comandos/bloqueio de ataques CSRF）
  → RateLimit（rate limit de janela deslizante em Redis + bloqueio de conta: 5 falhas de login bloqueiam por 15 minutos）
  → ApiVersion（validação de versão da API, grupo de rotas /api）
  → AdminAuth（autenticação JWT + lista negra, grupo de rotas /admin）
  → AdminPermission（autorização RBAC, grupo de rotas /admin）
  → OperationLog（registro automático de POST/PUT/DELETE, incluindo detecção de origem, grupo de rotas /admin）
```

`/health` e `/api/docs` são endpoints públicos, passando apenas por `Cors → SecurityFilter → RateLimit`.

Aprimoramentos de segurança:
- **Bloqueio de conta**: após 5 falhas consecutivas de login, a conta fica bloqueada por 15 minutos, e o login retorna 429 nesse período
- **Limite de sessões concorrentes**: máximo de 3 Tokens válidos por usuário; ao exceder, o Token mais antigo é adicionado automaticamente à lista negra
- **security.txt**: `GET /.well-known/security.txt` fornece informações de contato de segurança no padrão RFC 9116
- **Configuração de segurança Nginx**: consulte `docs/nginx-security.conf` para um exemplo completo de reforço de segurança com proxy reverso

### Autenticação

Login e registro exigem validação prévia com **CAPTCHA de clique**:

1. O cliente chama `POST /api/captcha/generate` para obter a imagem do CAPTCHA (PNG base64) e a lista de alvos de texto
2. O usuário clica nas posições correspondentes do texto na imagem em ordem, coletando as coordenadas dos cliques `[{x, y}, ...]`
3. No login, envie `captcha_key` e `clicks` juntos; o servidor valida primeiro o CAPTCHA e depois as credenciais

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

As interfaces subsequentes do painel exigem autenticação JWT:

```http
Authorization: Bearer <token>
```

Após o login bem-sucedido, retorna access_token com validade de 2 horas; também retorna refresh_token com validade de 14 dias.

No logout, o Token é adicionado à lista negra do Redis e não pode ser reutilizado durante o período de validade. POST /admin/profile/logout

### Confirmação secundária para operações sensíveis

Operações sensíveis, como excluir usuários, funções e permissões, exigem o envio do `password` do usuário logado no corpo da requisição para confirmação secundária de identidade:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## Lista de APIs

> Todas as interfaces `/api/*` exigem o cabeçalho `API-Version: v1` na requisição (sem ele, o padrão é v1).

### Interfaces públicas

| Método | Caminho | Descrição |
|-----|------|------|
| `GET` | `/health` | Health check (status do DB/Redis/ES) |
| `GET` | `/api/docs` | Documento de especificação OpenAPI 3.0 |
| `POST` | `/api/captcha/generate` | Gerar CAPTCHA de clique |
| `POST` | `/api/captcha/verify` | Validar CAPTCHA de clique |
| `POST` | `/api/auth/login` | Login (exige captcha) |
| `POST` | `/api/auth/register` | Registro (exige captcha) |
| `POST` | `/api/auth/refresh` | Refresh de token |
| `GET` | `/metrics` | Métricas de monitoramento Prometheus |

### Interfaces do painel (exigem JWT + RBAC)

| Método | Caminho | Descrição |
|-----|------|------|
| `GET` | `/admin/dashboard` | Dados do dashboard (cache Redis de 5 minutos) |
| `GET` | `/admin/user` | Lista de usuários (paginação + busca) |
| `POST` | `/admin/user` | Criar usuário |
| `GET` | `/admin/user/{id}` | Detalhes do usuário |
| `PUT` | `/admin/user/{id}` | Atualizar usuário |
| `DELETE` | `/admin/user/{id}` | Excluir usuário (soft delete, exige confirmação de senha) |
| `POST` | `/admin/user/batch/destroy` | Exclusão em lote de usuários (exige confirmação de senha) |
| `POST` | `/admin/user/batch/status` | Habilitar/desabilitar usuários em lote |
| `GET` | `/admin/role` | Lista de funções |
| `POST` | `/admin/role` | Criar função |
| `PUT` | `/admin/role/{id}` | Atualizar função |
| `DELETE` | `/admin/role/{id}` | Excluir função (exige confirmação de senha) |
| `GET` | `/admin/permission` | Árvore de permissões |
| `POST` | `/admin/permission` | Criar permissão |
| `PUT` | `/admin/permission/{id}` | Atualizar permissão |
| `DELETE` | `/admin/permission/{id}` | Excluir permissão (cascata de subpermissões, exige confirmação de senha) |
| `GET` | `/admin/config` | Lista de configurações do sistema |
| `POST` | `/admin/config` | Criar item de configuração |
| `PUT` | `/admin/config/{id}` | Atualizar item de configuração |
| `DELETE` | `/admin/config/{id}` | Excluir item de configuração (exige confirmação de senha) |
| `GET` | `/admin/log` | Logs de operação (paginação + filtros) |
| `PUT` | `/admin/profile` | Atualizar informações pessoais |
| `PUT` | `/admin/profile/password` | Alterar senha |
| `POST` | `/admin/profile/logout` | Logout (lista negra JWT) |
| `POST` | `/admin/export/excel` | Exportar Excel |
| `POST` | `/admin/export/pdf` | Exportar PDF |
| `POST` | `/admin/import/users` | Importar usuários via Excel |
| `POST` | `/admin/upload` | Upload de arquivos (imagens/documentos, máximo 10MB) |

## Notas do frontend

### Painel administrativo Flutter (estilo PC)

- **Layout**: sidebar (recolhível 64px/240px) + topbar + área de conteúdo, três breakpoints responsivos (celular/tablet/desktop)
- **Páginas**: login, dashboard, gerenciamento de usuários, permissões de funções, configurações do sistema, logs de operação, central pessoal
- **Gerenciamento de estado**: GetX (`ApiService` singleton + `AuthService` persistência de Token)
- **Dashboard**: cards de estatísticas, gráfico de linhas de tendência (fl_chart), gráfico de pizza, logs de operações recentes
- **Exportação**: exportação Excel/PDF, o PDF inclui informações de direitos autorais não removíveis
- **Operações em lote**: exclusão em lote com seleção múltipla, habilitar/desabilitar em lote
- **Tema**: Material 3 com temas claro/escuro

### Dispositivos móveis HarmonyOS

- **Páginas**: login, dashboard, lista/detalhes de usuários, central pessoal
- **Autenticação**: JWT Bearer + refresh silencioso automático do Token em 401; em caso de falha no refresh, redirecionamento automático para a página de login
- **Armazenamento**: Token gerenciado via AppStorage

## Padrões de desenvolvimento

- Referências a funções/classes globais não usam prefixo `\`; use sempre `use` para importar
- Todos os arquivos PHP devem incluir declaração de direitos autorais no cabeçalho
- Todos os arquivos de configuração devem incluir comentários explicativos em chinês
- As chaves primárias do banco devem ser geradas pela camada de aplicação via snowflake; autoincremento proibido
- Todos os IDs em parâmetros e respostas da camada de API devem passar por criptografia/descriptografia hashids
- O middleware AdminPermission usa cache Redis das permissões do usuário (TTL=60s), eliminando o gargalo de consultas N+1

## Deploy

### Docker Compose (recomendado)

A raiz do projeto fornece `docker-compose.yml`, orquestrando 5 serviços:

| Serviço | Imagem | Porta |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | construído com `Dockerfile` local | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

A imagem PHP é construída via `Dockerfile`, com imagem base `php:8.3-cli` e OPcache habilitado.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

Pipeline de integração contínua com GitHub Actions: `.github/workflows/ci.yml`

- Verificação de sintaxe PHP (`php -l`)
- Testes unitários PHPUnit
- Análise estática Flutter (`flutter analyze`)

### Backup do banco de dados

Diretório `database/backup/`:

- `backup.sh` — backup com mysqldump + gzip, limpeza automática de backups com mais de 30 dias
- `restore.sh` — restauração interativa, lista os backups disponíveis para escolha

### Configuração de segurança Nginx

Para deploy em produção, consulte `docs/nginx-security.conf` para reforço de segurança do proxy reverso.

## Código aberto não é fácil, seu apoio é bem-vindo

| WeChat | Alipay |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

