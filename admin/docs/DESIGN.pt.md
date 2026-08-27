# Painel administrativo aberto — documento de design
<!-- lang-nav -->

Languages: [中文](DESIGN.md) · [English](DESIGN.en.md) · [한국어](DESIGN.ko.md) · [Русский](DESIGN.ru.md) · [Deutsch](DESIGN.de.md) · [Français](DESIGN.fr.md) · [Español](DESIGN.es.md) · **Português** · [हिन्दी](DESIGN.hi.md) · [العربية](DESIGN.ar.md) · [বাংলা](DESIGN.bn.md) · [Bahasa Indonesia](DESIGN.id.md) · [日本語](DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Para os diagramas Mermaid detalhados, consulte [ARCHITECTURE.md](ARCHITECTURE.pt.md) (renderização automática no GitHub/GitLab/VS Code).

## 1. Arquitetura do sistema

> **Lista de funcionalidades**: autenticação (login/register/refresh/logout + bloqueio de conta + limite de sessões) | dashboard (cache Redis) | CRUD de usuários + lote + importação | permissões de funções (RBAC) | configuração do sistema | auditoria de operações (8 origens de plataforma) | arquivos (upload + exportação + mascaramento) | segurança (defesa em 18 camadas) | operações (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Arquitetura do backend

### 2.1 Design em camadas

| Camada | Diretório | Responsabilidade |
|---|------|------|
| Rotas | `config/route.php` | Mapeamento de URL para controladores, vínculo de middlewares, rotas versionadas |
| Middlewares | `app/middleware/` | Interceptação de ataques (SecurityFilter), rate limit (RateLimit), autenticação (JWT), autorização (RBAC), versão da API (ApiVersion) |
| Controladores | 30: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs/Metrics/Analytics/Game/Payment/Withdraw... (painel) + Captcha/Auth (API v1) | Validação de parâmetros da requisição, chamada da lógica de negócio, formatação da resposta |
| Serviços de negócio | `common/service/` | Análise de dados: GameDashboardService (visão geral/ranking/tendências), DepositLogService (receita/conversão), ProbabilityService (probabilidade conjunta/condicional, construtor SQL); em falha do banco, retorna dados vazios em vez de erro |
| Modelos de dados | `app/model/` | Mapeamento ORM, relacionamentos, criptografia/descriptografia de campos |
| Utilitários comuns | `app/common/` | Serviços Hashids, Snowflake, Encryption |

### 2.2 Ciclo de vida da requisição

```
客户端请求
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 匹配
  │
  ▼
中间件链:
  SecurityFilter ──────► HTTP方法检查 → 405 (仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截 (403)
  ▼
  RateLimit ───────────► Redis 滑动窗口限流
  │ (失败返回 429 + Retry-After 头)
  ▼
  ApiVersion ─────────► API-Version 头校验，注入 $request->apiVersion
  │ (失败返回 400)
  ▼
  AdminAuth ──────────► JWT 验证，注入 $request->adminId
  │ (失败返回 401)
  ▼
  AdminPermission ────► RBAC 权限校验（Redis 60s 缓存）
  │ (失败返回 403)
  ▼
  OperationLog ───────► 操作日志记录 (POST/PUT/DELETE)，自动检测来源端
  │
  ▼
Controller::method()
  │
  ├─► 参数验证 (validator)
  ├─► 敏感操作确认 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 操作 (自动 encryptable 加解密)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 Ciclo de vida do ID

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Sistema de criptografia de dados

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-128-ECB，独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. Design do banco de dados

### 3.1 Relações ER

```
game_admin_user ──┬── game_admin_user_role ──┬── game_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    game_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    game_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           game_operation_log
             (操作日志)

game_system_config (系统配置) — 独立表
```

### 3.2 Estrutura das tabelas principais

| Nome da tabela | Nº de campos | Descrição |
|------|-------|------|
| `game_admin_user` | 14 | Usuários administrativos, phone/email/id_card armazenados criptografados, suporte a soft delete |
| `game_admin_role` | 7 | Funções, slug único |
| `game_admin_permission` | 10 | Árvore de permissões (auto-relacionamento via parent_id), type: 1=menu 2=botão 3=API |
| `game_admin_user_role` | 2 | Tabela intermediária muitos-para-muitos usuário-função |
| `game_admin_role_permission` | 2 | Tabela intermediária muitos-para-muitos função-permissão |
| `game_system_config` | 8 | Configuração chave-valor, group+key único combinado |
| `game_operation_log` | 9 | Log de auditoria de operações (inclui source de origem) |

### 3.3 Padrão de chave primária

- Tipo: `BIGINT UNSIGNED NOT NULL`
- Característica: **não auto-incrementável**, gerada pela camada de aplicação com o algoritmo Snowflake
- Vantagens: globalmente único, amigável a ambientes distribuídos, incremento de tendência favorece índices, não expõe o volume de negócio
- Configuração: datacenter_id(0-31) + worker_id(0-31), suporta 1024 nós concorrentes

## 4. Design da API

### 4.1 Padrão de URL

```
公开接口:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

管理端:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

资源路由:
  GET    /admin/user          → 列表
  POST   /admin/user          → 创建
  GET    /admin/user/{hashid} → 详情
  PUT    /admin/user/{hashid} → 更新
  DELETE /admin/user/{hashid} → 删除（需密码确认）

系统配置:  /admin/config[/{hashid}]
操作日志:  /admin/log
个人中心:  /admin/profile[/password|/logout]
导入:     /admin/import/users
上传:     /admin/upload
批量:     /admin/user/batch/{destroy|status}
文档:     /api/docs     (OpenAPI 3.0)
健康:     /health
```

### 4.2 Estratégia de versão da API

A versão da API é controlada por cabeçalho de requisição, **não aparece no caminho da URL**:

```http
API-Version: v1
```

| Mecanismo | Descrição |
|------|------|
| Versão padrão | Sem o cabeçalho `API-Version`, o padrão é `v1` |
| Validação | Middleware `ApiVersion` valida; versões não suportadas retornam 400 |
| Rotas | A função auxiliar `v()` resolve dinamicamente a classe do controlador conforme a versão |
| Diretório | Controladores organizados por versão: `app/api/{version}/controller/` |

Exemplo de extensão — adicionar a API v2:
1. Criar `app/api/v2/controller/AuthController.php`
2. Adicionar `'v2'` à constante `SUPPORTED` do middleware `ApiVersion`
3. As definições de rota não precisam ser alteradas

```bash
# 使用 v1
curl -H "API-Version: v1" /api/auth/login

# 使用 v2
curl -H "API-Version: v2" /api/auth/login

# 不传，默认 v1
curl /api/auth/login
```

### 4.3 Estratégia de rate limit

Baseado no algoritmo de janela deslizante com Redis Sorted Set, executado com script Lua atômico:

| Interface | Limite |
|------|------|
| Padrão | 60/minuto/IP/rota |
| POST /api/auth/login | 10/minuto |
| POST /api/auth/register | 5/minuto |

Ao exceder o limite, retorna 429, com os cabeçalhos de resposta X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 Resposta unificada

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Significado | Cenário de disparo |
|------|------|---------|
| 0 | Sucesso | Resposta normal |
| 400 | Erro de parâmetros | Formato da requisição incorreto |
| 401 | Não autenticado | Token ausente/expirado/inválido |
| 403 | Sem permissão | A função do usuário não contém a permissão necessária |
| 404 | Não existe | Recurso não encontrado |
| 422 | Falha na validação | Parâmetros do formulário não atendem às regras / falha na confirmação da senha |
| 500 | Erro do servidor | Exceção inesperada |

### 4.5 Fluxo de autenticação (inclui CAPTCHA de clique)

```
客户端                               服务端
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② 用户点击图中文字位置              │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 Modelo de permissões (RBAC)

```
  用户 ──┬── 角色 ──┬── 权限
  User     Role      Permission
                 │
                 ├── type=1: 菜单 (控制侧边栏可见)
                 ├── type=2: 按钮 (控制页面内操作)
                 └── type=3: API  (控制接口访问)

  权限标识格式: {method}.{path}
  例: get.admin/user  post.admin/user  delete.admin/user
  超级管理员标识: * (跳过所有权限检查)
```

### 4.7 Confirmação secundária para operações sensíveis

Operações sensíveis, como excluir usuários, funções e permissões, exigem o envio da senha atual do usuário no corpo da requisição para revalidação de identidade:

```
客户端                           服务端
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 密码错误返回 422
  │                                │ → 密码正确继续执行
  │◄── 200 { code: 0 }           │
```

O frontend exibe uma caixa de confirmação antes de disparar a operação de exclusão, coleta a senha do usuário e envia a requisição.

## 5. Design do frontend

### 5.1 Painel administrativo Flutter Web

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

Características: sidebar recolhível, Material 3 com dois temas, tabela de dados de alta densidade, diálogos, interação por hover do mouse

### 5.2 Dispositivos móveis HarmonyOS

Rotas de páginas:

| Página | Rota | Descrição |
|------|------|------|
| LoginPage | `pages/LoginPage` | Login com nome de usuário/senha + CAPTCHA de clique |
| DashboardPage | `pages/DashboardPage` | Cards de estatísticas + operações recentes |
| UserListPage | `pages/UserListPage` | Lista de usuários, busca + pull-to-refresh + scroll de carregamento |
| UserDetailPage | `pages/UserDetailPage` | Criar/editar/visualizar/excluir (confirmação com AlertDialog) |
| ProfilePage | `pages/ProfilePage` | Central pessoal, logout (confirmação com AlertDialog) |

Fluxo de dados: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Design de segurança

### 6.1 Defesa em profundidade

| Camada | Medida |
|------|------|
| Restrição de métodos | SecurityFilter com lista branca de métodos HTTP, apenas GET/POST/PUT/DELETE/OPTIONS/HEAD; métodos não padrão retornam 405 |
| Interceptação de ataques | Middleware SecurityFilter, detecção e bloqueio de XSS/injeção SQL/traversal de caminho/injeção de comandos/CSRF |
| Verificação de humano | CAPTCHA de clique (Click Captcha), validação obrigatória no login/registro |
| Bloqueio de conta | 5 falhas consecutivas de login bloqueiam a conta por 15 minutos; durante o bloqueio, retorna 429 |
| Limite de sessões | Máximo de 3 Tokens concorrentes por usuário; ao exceder, o Token mais antigo vai automaticamente para a lista negra |
| Rate limit | Middleware RateLimit, janela deslizante em Redis, atomização com Lua |
| CSP | Cabeçalho Content-Security-Policy restringe as origens de recursos, contra XSS e injeção de dados |
| Confirmação de operação | Operações sensíveis, como exclusão, exigem confirmação secundária da senha do usuário atual |
| Transporte | HTTPS + Token JWT Bearer |
| IDs de interface | Criptografia com Hashids; o ID real não pode ser revertido externamente |
| Corpo da requisição | Criptografia de campos sensíveis com AES-256-CBC |
| Banco de dados | Chave primária BIGINT (não expõe o incremento) |
| Banco de dados | Armazenamento de campos sensíveis criptografados com AES-128-ECB |
| Autenticação | JWT HS256, expiração de 2h + refresh token |
| Autorização | RBAC, controle de permissão em granularidade method.path |
| Auditoria | OperationLog registra todas as operações (inclui detecção automática da origem source) |

### 6.2 Gerenciamento de chaves

```
JWT_SECRET          → 环境变量注入，64位随机字符串
HASHIDS_SALT        → 唯一盐值，泄漏后需全局更换
ENCRYPTION_KEY      → API 传输加密密钥，32字节
ENCRYPTABLE_KEY     → DB 存储加密密钥，与传输密钥独立
SCOUT_HOSTS         → ES 地址，内网部署
```

### 6.3 Proteção de dados sensíveis

| Cenário | Campo | Medida |
|------|------|------|
| Exibição em lista | phone | Mascaramento: 138****1234 |
| Exibição em lista | email | Mascaramento: a***@example.com |
| Visualização de detalhes | phone/email | Requer interface de descriptografia |
| Exportação Excel | phone/email | Exportado após mascaramento |
| Exportação PDF | Todos os campos | Mascaramento + marca d'água de direitos autorais não removível |
| Armazenamento | phone/email/id_card | Criptografia com encryptable em texto cifrado |

## 7. Design de exportação

### 7.1 Exportação Excel

```
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 Exportação PDF

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. Arquitetura de deploy

### 8.1 Topologia recomendada

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                    静态文件: Flutter Web build/
```

### 8.2 Docker Compose (recomendado para produção)

O `docker-compose.yml` na raiz do projeto orquestra todos os serviços da topologia acima:

| Serviço | Imagem/build | Porta | Descrição |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Proxy reverso + arquivos estáticos + Gzip |
| `app` | construído com `Dockerfile` local | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Banco de dados principal, persistência em volume |
| `redis` | redis:7-alpine | 6379 | Cache / rate limit / CAPTCHA |
| `elasticsearch` | elasticsearch:8.x | 9200 | Busca em texto completo |

Antes de iniciar, substitua as chaves do `docker-compose.yml` — `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY` etc. — por strings aleatórias.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

A integração contínua do GitHub Actions está definida em `.github/workflows/ci.yml`:
- Verificação de sintaxe PHP (`php -l`)
- Testes unitários PHPUnit
- Análise estática Flutter (`flutter analyze`)

### 8.4 Backup do banco de dados

`database/backup/backup.sh` — backup com mysqldump + gzip, limpeza automática de backups com mais de 30 dias.
`database/backup/restore.sh` — seleção interativa e restauração do backup.

### 8.5 Monitoramento

O endpoint `GET /metrics` (`MetricsController`) expõe 5 métricas gauge no formato de texto Prometheus: total de requisições HTTP, usuários ativos, status das conexões de banco/Redis, uso de memória.

### 8.6 Requisitos de ambiente

| Componente | Versão mínima | Configuração recomendada |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ com OPcache habilitado |
| MySQL | 8.0+ | 8.0+ com replicação mestre-escravo |
| Elasticsearch | 7.x | 8.x com cluster de 3 nós |
| Redis | 6.x | 7.x em modo sentinel |
| Nginx | 1.20+ | Proxy reverso + gzip + SSL |
| Flutter SDK | 3.41+ | Última versão estável |
| HarmonyOS | API 12 | DevEco Studio 5.x |
