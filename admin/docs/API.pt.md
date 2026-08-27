# Documento de referência da API
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · **Português** · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Visão geral

O painel administrativo aberto (open-admin) é construído sobre webman v2 e fornece uma API JSON RESTful. Todas as interfaces do painel exigem autenticação JWT e validação de permissões RBAC; as interfaces públicas são roteadas para controladores versionados por meio do cabeçalho de versão da API.

- **URL base**: `http://localhost:8787`
- **Versão da API**: controlada pelo cabeçalho de requisição `API-Version: v1` (padrão v1 quando ausente)

> **Visão geral dos endpoints**: autenticação(5) | dashboard(1) | usuários(7) | funções(4) | permissões(4) | configurações(4) | logs(1) | central pessoal(3) | importação/exportação(3) | upload(1) | operações(4: health/metrics/docs/security.txt) | total de 37 endpoints
- **Autenticação**: `Authorization: Bearer <token>` (JWT)
- **Formato de resposta**: `{ "code": 0, "message": "success", "data": {...} }`
- **Endpoint de documentação**: `GET /api/docs` retorna a especificação JSON OpenAPI 3.0

### Requisitos de requisição

- Apenas os métodos `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` são permitidos; o uso de outros métodos HTTP (como TRACE, CONNECT, PATCH) retorna 405
- Todas as requisições `POST` / `PUT` devem definir `Content-Type: application/json` (exceto upload de arquivos), caso contrário retorna 415
- O corpo da requisição não pode exceder 10MB, caso contrário retorna 413
- O filtro de segurança escaneia todas as entradas das requisições contra XSS, injeção SQL, traversal de caminho e injeção de comandos; em caso de detecção, retorna 403
- 5 falhas consecutivas de login disparam o bloqueio de conta (15 minutos); durante o bloqueio, requisições de login retornam 429
- O mesmo usuário pode manter no máximo 3 Tokens válidos simultaneamente; ao exceder, o Token mais antigo é adicionado automaticamente à lista negra

## 2. Códigos de erro

| code | Significado | Cenário de disparo |
|------|------|---------|
| 0 | Sucesso | |
| 400 | Erro nos parâmetros da requisição | Formato da requisição incorreto |
| 401 | Não autenticado | Token ausente / expirado / já na lista negra |
| 403 | Sem permissão / bloqueio de segurança | Permissões RBAC insuficientes / detecção do SecurityFilter |
| 404 | Recurso não encontrado | O alvo de consulta/atualização/exclusão não existe |
| 405 | Método da requisição não permitido | Apenas GET/POST/PUT/DELETE/OPTIONS/HEAD são permitidos; métodos não padrão são rejeitados diretamente |
| 413 | Corpo da requisição muito grande | Content-Length acima de 10MB |
| 415 | Tipo de mídia não suportado | Content-Type de requisições POST/PUT não é JSON e não é upload de arquivo |
| 422 | Falha na validação de parâmetros | Campos obrigatórios ausentes, formato incorreto, validação de negócio reprovada |
| 429 | Requisições em excesso | Disparado pelo RateLimit / bloqueio de conta (5 falhas consecutivas de login bloqueiam por 15 minutos) |
| 500 | Erro interno do servidor | |

## 3. Endpoints públicos

Todos os endpoints públicos ficam no grupo `/api`, distribuídos pelo middleware `ApiVersion` conforme o cabeçalho `API-Version` para o controlador versionado correspondente (ex.: `app\api\v1\controller\AuthController`).

### 3.1 Health check

```
GET /health
```

- **Autenticação**: nenhuma
- **Rate limit**: nenhum

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.1",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

Valores de `database`, `redis`, `elasticsearch`: `"ok"` | `"unavailable"`. Quando o ES está inacessível, `elasticsearch` retorna `"unavailable"`; se o status de saúde do cluster não for green/yellow, retorna o valor real do status (ex.: `"red"`).

### 3.2 Documentação da API

```
GET /api/docs
```

- **Autenticação**: nenhuma
- **Rate limit**: padrão global (60/minuto)
- **Resposta**: especificação JSON OpenAPI 3.0.3, incluindo definições de todos os endpoints, parâmetros e Schemas

### 3.3 Gerar CAPTCHA de clique

```
POST /api/captcha/generate
```

- **Autenticação**: nenhuma
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: padrão global (60/minuto)

**Corpo da requisição**:
```json
{
  "difficulty": "medium"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| difficulty | string | não | `easy` / `medium` / `hard`, padrão `medium` |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| key | string | Identificador do CAPTCHA, retornado na validação |
| image | string | Imagem PNG codificada em base64 |
| extra.targets[].order | int | Ordem do clique |
| extra.targets[].text | string | Texto de instrução do alvo do clique |

### 3.4 Validar CAPTCHA de clique

```
POST /api/captcha/verify
```

- **Autenticação**: nenhuma
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: padrão global (60/minuto)

**Corpo da requisição**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| key | string | sim | Chave do CAPTCHA, retornada pelo generate |
| clicks | array{object} | sim | Array de coordenadas de clique, cada elemento contém `x` (int) e `y` (int) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

Em caso de falha na validação, `code` é 422, `message` é `"验证失败，请重试"` e `data.valid` é `false`.

### 3.5 Login

```
POST /api/auth/login
```

- **Autenticação**: nenhuma
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: 10/minuto (por IP + caminho)

**Corpo da requisição**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| username | string | sim | min:3, max:50 | Nome de usuário |
| password | string | sim | min:6, max:32 | Senha |
| captcha_key | string | sim | | Chave do CAPTCHA |
| clicks | array{object} | sim | min:2 | Array de coordenadas de clique |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| access_token | string | Token de acesso JWT |
| refresh_token | string | Token de atualização JWT |
| expires_in | int | Validade do token de acesso (segundos), padrão 7200 |
| user.id | string | ID do usuário criptografado com hashid |
| user.username | string | Nome de usuário |
| user.real_name | string | Nome real |

**Erros possíveis**:
- 422: falha na validação de parâmetros (campos obrigatórios ausentes, formato incorreto)
- 422: CAPTCHA incorreto, tente novamente
- 401: nome de usuário ou senha incorretos
- 403: conta desabilitada
- 429: conta bloqueada, tente novamente em 15 minutos (disparado por 5 falhas consecutivas de login)

### 3.6 Registro

```
POST /api/auth/register
```

- **Autenticação**: nenhuma
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: 5/minuto (por IP + caminho)

**Corpo da requisição**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| username | string | sim | min:3, max:50 | Nome de usuário (único) |
| password | string | sim | min:6, max:32 | Senha (armazenada com hash bcrypt) |
| real_name | string | sim | max:50 | Nome real |
| captcha_key | string | sim | | Chave do CAPTCHA |
| clicks | array{object} | sim | min:2 | Array de coordenadas de clique |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

Após o registro bem-sucedido, os tokens JWT são retornados diretamente e o status do usuário é habilitado por padrão (status=1).

### 3.7 Refresh de token

```
POST /api/auth/refresh
```

- **Autenticação**: nenhuma
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: padrão global (60/minuto)

**Corpo da requisição**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| refresh_token | string | sim | refresh_token obtido no login/registro |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

Um refresh bem-sucedido retorna novos access_token e refresh_token, e o token antigo é invalidado automaticamente. O refresh também atualiza o horário do último login e o IP do usuário.

**Erros possíveis**:
- 422: token de atualização ausente
- 401: token de atualização inválido ou expirado

### 3.8 Métricas de monitoramento Prometheus

```
GET /metrics
```

- **Autenticação**: nenhuma
- **Rate limit**: nenhum
- **Formato de resposta**: texto Prometheus (`text/plain; version=0.0.4`)

Endpoint público de métricas de monitoramento Prometheus, para coleta por Grafana/Prometheus.

**Exemplo de resposta**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| Nome da métrica | Tipo | Descrição |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Total acumulado de requisições HTTP |
| `openadmin_active_users` | gauge | Usuários ativos atuais (login nas últimas 24 horas) |
| `openadmin_db_connection_status` | gauge | Status da conexão do banco de dados, 1=normal, 0=anormal |
| `openadmin_redis_connection_status` | gauge | Status da conexão Redis, 1=normal, 0=anormal |
| `openadmin_memory_usage_bytes` | gauge | Uso de memória atual do processo PHP (bytes) |

## 4. Dashboard

Todas as interfaces do painel ficam no grupo `/admin` e passam por três middlewares: `AdminAuth` (autenticação JWT), `AdminPermission` (validação de permissões RBAC) e `OperationLog` (registro de operações).

### 4.1 Dados do dashboard

```
GET /admin/dashboard
```

- **Autenticação**: JWT + RBAC
- **Cache**: Redis, 5 minutos

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| Campo de stats | Tipo | Descrição |
|------|------|------|
| label | string | Nome da métrica |
| value | string | Valor da métrica (tipo string) |
| icon | string | Nome do ícone Material |
| color | string | Cor do card |
| trend | float? | Taxa de crescimento diária (porcentagem); apenas "用户总数" possui este campo |

| Campo de trends | Tipo | Descrição |
|------|------|------|
| dates | array{string} | Sequência de datas dos últimos 30 dias |
| series | array{object} | Dados das linhas de tendência, cada uma com name (nome), data (array de valores), color (cor) |

## 5. Gerenciamento de usuários

Todos os `id` retornados pelas interfaces de gerenciamento de usuários são strings criptografadas com hashid. O campo de senha é excluído das respostas. Telefone e e-mail aparecem mascarados nas interfaces de lista e em texto claro nas interfaces de detalhes (os campos criptografados do banco são descriptografados automaticamente pelo trait Encryptable).

### 5.1 Lista de usuários

```
GET /admin/user
```

- **Autenticação**: JWT + RBAC

**Parâmetros de consulta**:

| Parâmetro | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| page | int | não | 1 | Número da página |
| limit | int | não | 15 | Itens por página |
| keyword | string | não | | Palavra-chave de busca, corresponde a nome de usuário e nome real |
| status | int | não | | Filtro de status, 0=desabilitado, 1=habilitado |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | ID do usuário criptografado com hashid |
| username | string | Nome de usuário |
| real_name | string | Nome real |
| phone | string | Telefone mascarado (formato `138****5678`) |
| email | string | E-mail mascarado (formato `a***@example.com`) |
| status | int | 1=habilitado, 0=desabilitado |
| last_login_at | string | Horário do último login (datetime) |
| created_at | string | Data de criação (datetime) |

### 5.2 Criar usuário

```
POST /admin/user
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| username | string | sim | min:3, max:50 | Nome de usuário (único) |
| password | string | sim | min:6, max:32 | Senha (armazenada com bcrypt) |
| real_name | string | sim | max:50 | Nome real |
| phone | string | não | | Telefone (armazenado criptografado com Encryptable) |
| email | string | não | | E-mail (armazenado criptografado com Encryptable) |
| status | int | não | in:0,1 | Status, padrão 1 (habilitado) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**Erros possíveis**:
- 422: nome de usuário já existe
- 422: falha na validação de parâmetros (campos obrigatórios ausentes)

### 5.3 Detalhes do usuário

```
GET /admin/user/{id}
```

- **Autenticação**: JWT + RBAC
- **Parâmetro de caminho**: `{id}` é o ID do usuário criptografado com hashid

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

Na interface de detalhes, `phone` e `email` são retornados em texto claro (no banco são armazenados criptografados, descriptografados automaticamente pelo cast Encryptable), sem mascaramento. `password` e `id_card` nunca aparecem na resposta.

**Erros possíveis**:
- 404: usuário não existe

### 5.4 Atualizar usuário

```
PUT /admin/user/{id}
```

- **Autenticação**: JWT + RBAC
- **Parâmetro de caminho**: `{id}` é o ID do usuário criptografado com hashid

**Corpo da requisição**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| real_name | string | não | Nome real; não enviar mantém o valor atual |
| password | string | não | Nova senha; string vazia ou não enviada não altera |
| phone | string | não | Telefone |
| email | string | não | E-mail |
| status | int | não | 0=desabilitado, 1=habilitado |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**Erros possíveis**:
- 404: usuário não existe

### 5.5 Excluir usuário

```
DELETE /admin/user/{id}
```

- **Autenticação**: JWT + RBAC
- **Parâmetro de caminho**: `{id}` é o ID do usuário criptografado com hashid
- **Operação sensível**: exige confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "password": "admin_password"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| password | string | sim | Senha do usuário atualmente logado (confirmação secundária) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Executa soft delete (SoftDeletes do Eloquent): o dado é marcado com deleted_at, sem exclusão física.

**Erros possíveis**:
- 404: usuário não existe
- 422: operação sensível exige confirmação de senha (password vazio)
- 422: falha na validação da senha (senha não corresponde)

### 5.6 Exclusão em lote de usuários

```
POST /admin/user/batch/destroy
```

- **Autenticação**: JWT + RBAC
- **Operação sensível**: exige confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| ids | array{string} | sim | Array de IDs de usuários criptografados com hashid |
| password | string | sim | Senha do usuário atualmente logado (confirmação secundária) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Executa soft delete; `data.count` é a quantidade efetivamente excluída.

**Erros possíveis**:
- 422: selecione os usuários a excluir (ids vazio)
- 422: ID inválido (falha na decodificação do hashid)
- 422: falha na validação da senha

### 5.7 Habilitar/desabilitar usuários em lote

```
POST /admin/user/batch/status
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| ids | array{string} | sim | Array de IDs de usuários criptografados com hashid |
| status | int | sim | 0=desabilitado, 1=habilitado |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

O message varia dinamicamente conforme o valor de status: `"批量启用成功"` ou `"批量禁用成功"`.

**Erros possíveis**:
- 422: selecione os usuários (ids vazio)
- 422: valor de status inválido (status não é 0 ou 1)

## 6. Gerenciamento de funções

### 6.1 Lista de funções

```
GET /admin/role
```

- **Autenticação**: JWT + RBAC

**Parâmetros de consulta**:

| Parâmetro | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| page | int | não | 1 | Número da página |
| limit | int | não | 15 | Itens por página |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | ID da função criptografado com hashid |
| name | string | Nome da função |
| slug | string | Identificador da função (único, usado para verificação de permissões) |
| description | string | Descrição da função |
| status | int | 1=habilitado, 0=desabilitado |
| users_count | int | Quantidade de usuários com esta função |

### 6.2 Criar função

```
POST /admin/role
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| name | string | sim | max:50 | Nome da função |
| slug | string | sim | max:50 | Identificador da função |
| description | string | não | | Descrição da função, padrão string vazia |
| status | int | não | | Status, padrão 1 |
| permission_ids | array{int} | não | | Array de IDs de permissões (IDs INT originais, não hashid) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 Atualizar função

```
PUT /admin/role/{id}
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| name | string | não | Nome da função |
| description | string | não | Descrição |
| status | int | não | 0=desabilitado, 1=habilitado |
| permission_ids | array{int} | não | Array de IDs de permissões; se enviado, sincroniza (substitui) as permissões da função |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 Excluir função

```
DELETE /admin/role/{id}
```

- **Autenticação**: JWT + RBAC
- **Operação sensível**: exige confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "password": "admin_password"
}
```

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Na exclusão, os vínculos da função com todas as permissões e usuários são removidos automaticamente e, em seguida, o registro da função é excluído fisicamente.

## 7. Gerenciamento de permissões

As permissões usam estrutura de árvore (auto-relacionamento via parent_id) e são divididas em três tipos. A interface de lista retorna a árvore de permissões completa.

### 7.1 Árvore de permissões

```
GET /admin/permission
```

- **Autenticação**: JWT + RBAC

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | Criptografado com hashid |
| parent_id | string | hashid da permissão pai; "0" indica nó raiz |
| name | string | Nome da permissão |
| slug | string | Identificador da permissão (identificador de rota/botão) |
| type | int | 1=menu, 2=botão, 3=interface |
| icon | string | Ícone do menu (nome do ícone Material) |
| path | string | Caminho da rota do frontend |
| sort | int | Valor de ordenação (crescente) |
| children | array? | Lista de subpermissões (recursiva); campo ausente quando não há nós filhos |

### 7.2 Criar permissão

```
POST /admin/permission
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| parent_id | int | não | | ID da permissão pai (tipo INT original), padrão 0 |
| name | string | sim | max:50 | Nome da permissão |
| slug | string | sim | max:100 | Identificador da permissão |
| type | int | sim | in:1,2,3 | 1=menu, 2=botão, 3=interface |
| icon | string | não | | Ícone do menu, padrão vazio |
| path | string | não | | Caminho da rota do frontend, padrão vazio |
| sort | int | não | | Valor de ordenação, padrão 0 |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 Atualizar permissão

```
PUT /admin/permission/{id}
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| name | string | não | Nome da permissão |
| icon | string | não | Ícone |
| path | string | não | Caminho da rota |
| sort | int | não | Valor de ordenação |

### 7.4 Excluir permissão

```
DELETE /admin/permission/{id}
```

- **Autenticação**: JWT + RBAC
- **Operação sensível**: exige confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "password": "admin_password"
}
```

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

A exclusão remove em cascata todas as subpermissões (registros com `parent_id` = ID da permissão atual) e desfaz o vínculo com todas as funções.

## 8. Configuração do sistema

As configurações do sistema são únicas pela combinação de `group` + `key`.

### 8.1 Lista de configurações

```
GET /admin/config
```

- **Autenticação**: JWT + RBAC

**Parâmetros de consulta**:

| Parâmetro | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| page | int | não | 1 | Número da página |
| limit | int | não | 15 | Itens por página |
| group | string | não | | Filtro por grupo de configuração |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | hashid |
| group | string | Grupo de configuração (ex.: `system`, `email`, `storage`) |
| key | string | Chave de configuração |
| value | string | Valor de configuração |
| type | string | Dica de tipo do valor (`string`, `integer`, `boolean`, `json` etc.) |
| description | string | Descrição da configuração |

### 8.2 Criar configuração

```
POST /admin/config
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| group | string | sim | max:100 | Grupo de configuração |
| key | string | sim | max:100 | Chave de configuração (única dentro do grupo) |
| value | string | sim | | Valor de configuração |
| type | string | não | | Tipo do valor, padrão `string` |
| description | string | não | | Descrição da configuração, padrão vazio |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**Erros possíveis**:
- 422: item de configuração já existe (mesmo group + key)

### 8.3 Atualizar configuração

```
PUT /admin/config/{id}
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| value | string | não | Atualiza o valor da configuração |
| type | string | não | Atualiza o tipo do valor |
| description | string | não | Atualiza o texto da descrição |

### 8.4 Excluir configuração

```
DELETE /admin/config/{id}
```

- **Autenticação**: JWT + RBAC
- **Operação sensível**: exige confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "password": "admin_password"
}
```

Exclui fisicamente o registro de configuração.

## 9. Logs de operação

Os logs de operação são uma interface somente leitura, gravados automaticamente pelo middleware `OperationLog` em cada requisição POST/PUT/DELETE; os campos armazenados incluem `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Lista de logs de operação

```
GET /admin/log
```

- **Autenticação**: JWT + RBAC

**Parâmetros de consulta**:

| Parâmetro | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| page | int | não | 1 | Número da página |
| limit | int | não | 15 | Itens por página |
| user_id | int | não | | Filtro exato por ID de usuário (tipo INT original) |
| action | string | não | | Filtro exato por ação |
| path | string | não | | Filtro difuso por caminho da requisição |
| start_date | string | não | | Data de início (formato Y-m-d) |
| end_date | string | não | | Data de fim (formato Y-m-d) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | hashid |
| user_name | string | Nome de usuário da operação (obtido via relacionamento com user; operações sem login exibem "系统") |
| action | string | Descrição da ação |
| method | string | Método HTTP (POST/PUT/DELETE) |
| path | string | Caminho da requisição |
| ip | string | IP do cliente |
| source | string | Origem da requisição |
| input | string | String JSON dos parâmetros da requisição (sem arquivos) |
| created_at | string | Horário da operação (datetime) |

## 10. Central pessoal

As interfaces da central pessoal exigem apenas autenticação JWT (sem validação de permissões RBAC — o middleware `AdminPermission` deve adicioná-las à lista branca).

### 10.1 Atualizar informações pessoais

```
PUT /admin/profile
```

- **Autenticação**: JWT

**Corpo da requisição**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| real_name | string | não | Nome real |
| phone | string | não | Telefone (armazenado criptografado com Encryptable) |
| email | string | não | E-mail (armazenado criptografado com Encryptable) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

Na resposta, `phone` e `email` são retornados em texto claro; `password` e `id_card` são removidos.

### 10.2 Alterar senha

```
PUT /admin/profile/password
```

- **Autenticação**: JWT

**Corpo da requisição**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| old_password | string | sim | | Senha atual |
| new_password | string | sim | min:6, max:32 | Nova senha |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Erros possíveis**:
- 422: informe a senha antiga e a nova
- 422: senha antiga incorreta
- 422: a nova senha deve ter 6-32 caracteres

### 10.3 Logout

```
POST /admin/profile/logout
```

- **Autenticação**: JWT

**Corpo da requisição**: nenhum (sem requestBody; o token é lido do cabeçalho Authorization)

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Lógica do logout: decodifica o JWT para obter a validade restante (exp - now) e grava o hash md5 do token na lista negra do Redis `jwt_blacklist:{md5}`, com TTL = validade restante. Tokens na lista negra são bloqueados pelo middleware `AdminAuth`, retornando 401.

Sem token, retorna 401. Token expirado/inválido (exceção na decodificação) ainda é tratado como logout bem-sucedido.

## 11. Importação e exportação

### 11.1 Exportar Excel

```
POST /admin/export/excel
```

- **Autenticação**: JWT + RBAC
- **Tipo de resposta**: download de arquivo (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Corpo da requisição**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| Campo | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| table | string | não | `admin_user` | Nome da tabela a exportar. Suportadas: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | não | | Array de nomes de colunas a exportar; vazio exporta todas as colunas da tabela |
| conditions | object | não | `{}` | Condições de filtro, pares key-value; valores não vazios são usados no WHERE |
| title | string | não | `数据导出` | Título do Excel (exibido como nome da Sheet) |

**Tabelas e colunas suportadas**:

| table | Colunas disponíveis |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Os campos sensíveis `phone`, `email` e `id_card` são mascarados automaticamente na exportação. Limite de 10000 linhas de dados. A primeira linha do Excel é congelada, com auto-filtro.

### 11.2 Exportar PDF

```
POST /admin/export/pdf
```

- **Autenticação**: JWT + RBAC
- **Tipo de resposta**: download de arquivo (`application/pdf`, A4 paisagem)

**Corpo da requisição**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

Ou modo tabela:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| Campo | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| type | string | não | `table` | Tipo de exportação: `table` / `dashboard` |
| title | string | não | `数据导出` | Título do PDF |
| data | object | não | `{}` | Dados a exportar |

Com `type=dashboard`, `data` deve conter o array `stats` (renderizado em formato de cards); com `type=table`, `data` deve conter os arrays `columns` e `rows`.

O template do PDF inclui informações de direitos autorais e carimbo de data/hora da exportação.

### 11.3 Importar usuários (Excel)

```
POST /admin/import/users
```

- **Autenticação**: JWT + RBAC
- **Tipo de requisição**: `multipart/form-data` (upload de arquivo)

**Campos do formulário**:

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| file | file | sim | Formato `.xlsx` ou `.xls` |

**Requisitos das colunas do Excel**:

| Nome da coluna | Obrigatório | Descrição |
|------|------|------|
| username | sim | Nome de usuário (único) |
| password | sim | Senha (armazenada com hash bcrypt) |
| real_name | sim | Nome real |
| phone | não | Telefone |
| email | não | E-mail |
| status | não | Status, padrão 1 |

A linha 1 contém os títulos das colunas (não sensível a maiúsculas/minúsculas); os dados começam na linha 2.

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| total | int | Total de linhas (sem a linha de título) |
| success | int | Quantidade importada com sucesso |
| failed | int | Quantidade com falha |
| errors | array | Detalhes das falhas, cada item com row (número da linha do Excel) e reason (motivo da falha) |

## 12. Upload de arquivos

```
POST /admin/upload
```

- **Autenticação**: JWT + RBAC
- **Tipo de requisição**: `multipart/form-data`

**Campos do formulário**:

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| file | file | sim | Arquivo a enviar |

**Tipos de arquivo permitidos**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Tamanho máximo do arquivo**: 10MB

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Os arquivos são armazenados em diretórios por data em `public/upload/{Y-m-d}/`, com nome `md5(uniqid) + extensão original`. `url` é um caminho relativo à raiz do site.

**Erros possíveis**:
- 422: selecione um arquivo (nenhum enviado)
- 422: tipo de arquivo não suportado
- 422: o tamanho do arquivo não pode exceder 10MB
- 500: falha no upload do arquivo (arquivo inválido)

## 13. Cabeçalhos de resposta

Todas as interfaces (injetados na camada de middlewares globais) incluem os seguintes cabeçalhos de resposta:

| Cabeçalho | Descrição |
|----|------|
| `X-RateLimit-Limit` | Limite máximo do rate limit (quantidade) |
| `X-RateLimit-Remaining` | Quantidade de requisições restantes |
| `X-RateLimit-Reset` | Timestamp de reset da janela do rate limit |
| `Retry-After` | Retornado apenas quando o rate limit é disparado; segundos sugeridos de espera |
| `X-Content-Type-Options` | `nosniff` (padrão do webman, proíbe MIME sniffing) |
| `X-Frame-Options` | `DENY` (fornecido pelo middleware CORS/configuração base do webman) |

Detalhes do rate limit:
- Limite global padrão: 60/minuto / IP+caminho
- Endpoint de login `/api/auth/login`: 10/minuto
- Endpoint de registro `/api/auth/register`: 5/minuto
- Usa algoritmo de janela deslizante atômico do Redis (Lua ZSET), evitando corrida TOCTOU
- Redis indisponível: fail-closed — retorna 503 (`Retry-After: 5`), sem liberar requisições

## 14. Análise de dados (Analytics)

Todos os endpoints exigem autenticação (`AdminAuth` + `AdminPermission`), agregação em tempo real no MySQL, total de 12:

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/analytics/overview | Visão geral da plataforma (hoje/últimos 7 dias) |
| GET | /admin/analytics/game-ranking | Ranking de jogos (?days=7) |
| GET | /admin/analytics/dau-trend | Tendência de DAU (?days=30) |
| GET | /admin/analytics/hourly-trend | Tendência por hora |
| GET | /admin/analytics/action-distribution | Distribuição de comportamentos |
| GET | /admin/analytics/revenue | Análise de receita |
| GET | /admin/analytics/conversion | Taxa de conversão de jogos |
| GET | /admin/analytics/probability | Probabilidade conjunta/condicional |
| GET | /admin/analytics/retention | Análise de retenção D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Funil de conversão |
| GET | /admin/analytics/arpu | Tendência de ARPU/ARPPU |
| GET | /admin/analytics/economy | Métricas econômicas das moedas de jogo |

## 15. Gerenciamento de tickets (Ticket)

Todos os endpoints exigem autenticação (`AdminAuth` + `AdminPermission`), total de 5:

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/ticket/list | Lista de tickets (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | Detalhes do ticket (inclui respostas) |
| POST | /admin/ticket/{hashid}/reply | Responder ticket |
| POST | /admin/ticket/{hashid}/close | Fechar ticket |
| POST | /admin/ticket/{hashid}/assign | Atribuir responsável (admin_id) |

## 16. Fluxo de autenticação

Sequência completa de autenticação:

```
1. 客户端请求 POST /api/captcha/generate
   (请求头: API-Version: v1)
    ↓
   服务端返回: key + base64 图片 + 点击目标提示
   
2. 用户点击图片目标位置，前/客户端收集点击坐标
   
3. 客户端请求 POST /api/auth/login
   (请求头: API-Version: v1, Content-Type: application/json)
   请求体: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   服务端:
   a. 参数校验 → 422
   b. 校验验证码 → 422
   c. 校验用户凭证 → 401
   d. 检查账号状态 → 403
   e. 签发 JWT (access + refresh) → 200
   f. 更新 last_login_at / last_login_ip
    ↓
   客户端保存: access_token, refresh_token, expires_in

4. 后续请求携带 JWT
   请求头: Authorization: Bearer <access_token>
    ↓
   AdminAuth 中间件:
   a. 提取 Bearer token
   b. 检查黑名单 (Redis jwt_blacklist:{md5}) → 401
   c. 解码 JWT，校验过期 → 401
   d. 设置 $request->adminId = sub 字段
    ↓
   AdminPermission 中间件:
   a. 未登录（adminId 为空）→ 401
   b. 对资源路由解析权限标识
   c. 查询用户角色 → 角色权限，进行匹配
   d. 无权限 → 403
    ↓
   Controller 处理请求
    ↓
   Response + X-RateLimit-* 头

5. Access Token 过期前刷新
   客户端请求 POST /api/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

6. 登出
   客户端请求 POST /admin/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### Estrutura do JWT

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, TTL padrão de 7200 segundos (controlado por `default_expire` na configuração do JWT)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, TTL padrão de 1209600 segundos (controlado por `refresh_expire` na configuração do JWT, ou seja, 14 dias)

### Gerenciamento de segurança

- Senhas armazenadas com hash `PASSWORD_BCRYPT`
- Campos sensíveis (phone, email, id_card) usam `erikwang2013/encryptable` para criptografia/descriptografia transparente na camada do banco
- IDs na camada de API usam `erikwang2013/hashids` para transmissão criptografada, evitando expor a sequência de IDs snowflake original
- O SecurityFilter escaneia globalmente XSS, injeção SQL, traversal de caminho e injeção de comandos; mesmo IP com 5 ocorrências/60s entra em lista negra temporária por 15 minutos
- Operações sensíveis (excluir usuários, funções, permissões, configurações) exigem confirmação secundária da senha do usuário logado
- Limite de sessões concorrentes: máximo de 3 Tokens válidos por usuário; o Token mais antigo é forçado para a lista negra quando um 4º dispositivo faz login
- Bloqueio de conta: 5 falhas consecutivas de login disparam bloqueio de 15 minutos; durante o bloqueio, retorna 429

## 15. Deploy e operações

### Docker Compose

A raiz do projeto fornece `docker-compose.yml`, orquestrando 5 serviços (Nginx, app webman, MySQL, Redis, Elasticsearch). O PHP é construído via `Dockerfile` (baseado em `php:8.3-cli`, com OPcache habilitado).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` define o pipeline de integração contínua do GitHub Actions:
- Verificação de sintaxe com `php -l`
- Testes unitários PHPUnit
- Análise estática com `flutter analyze`

### Backup do banco de dados

O diretório `database/backup/` fornece scripts de backup e restauração:
- `backup.sh` — backup compactado com mysqldump + gzip, limpeza automática de backups com mais de 30 dias
- `restore.sh` — restauração interativa, lista os backups existentes para escolha

### Configuração de segurança Nginx

Para deploy em produção, consulte `docs/nginx-security.conf` para reforço de segurança do proxy reverso.

## 16. Análise de dados (Analytics)

As interfaces de análise de dados são fornecidas pelo `AnalyticsController`, todas baseadas em agregação em tempo real no MySQL (`game_game_play_log` logs de comportamento de jogo / `game_deposit_order` pedidos de recarga); em caso de falha do banco, retorna dados vazios em vez de 500. Exceto quando indicado, todas exigem autenticação JWT + RBAC, e o formato de resposta é unificado como `{ "code": 0, "message": "success", "data": ... }`.

### 16.1 Visão geral da plataforma

```
GET /admin/analytics/overview
```

**Resposta**: `today` / `week` contêm cada um `dau` (usuários ativos), `revenue` (total de recargas confirmadas, string), `new_users` (novos usuários).

### 16.2 Ranking de jogos

```
GET /admin/analytics/game-ranking?days=7
```

**Resposta**: top 10 em ordem decrescente de quantidade de comportamentos de jogo, cada item com `game_id` (hashid), `name`, `plays`, `players`.

### 16.3 Tendência de DAU

```
GET /admin/analytics/dau-trend?days=30
```

**Resposta**: `{ "日期": 活跃数, ... }`, datas ausentes preenchidas com 0.

### 16.4 Tendência por hora

```
GET /admin/analytics/hourly-trend?game_id=<hashid>
```

**Resposta**: `{ "0": 次数, ... "23": 次数 }` — 24 faixas de horas; com `game_id` vazio, calcula todos os jogos.

### 16.5 Distribuição de comportamentos

```
GET /admin/analytics/action-distribution?game_id=<hashid>&hours=24
```

**Resposta**: `{ "start": n, "end": n, "earn": n, "spend": n }` — contagens das quatro categorias de comportamento; `hours` com limite de 168.

### 16.6 Visão geral da receita

```
GET /admin/analytics/revenue?days=7
```

**Resposta**: `{ "total": "总额", "trend": { "日期": "当日额", ... } }`, contando apenas pedidos com `status=confirmed`.

### 16.7 Taxa de conversão de jogos

```
GET /admin/analytics/conversion?days=30
```

**Resposta**: cada jogo contém `game_id` (hashid), `game_name`, `players` (jogadores únicos), `depositors` (recarregadores únicos), `conversion_rate` (taxa de conversão de recarga, 0~1).

### 16.8 Probabilidade conjunta

```
GET /admin/analytics/probability?game_a=<hashid>&game_b=<hashid>
```

**Resposta**: `{ "joint": { "joint_probability": 0.12, "confidence": 0.3 } }` — coeficiente de Jaccard (jogadores comuns aos dois jogos / união de jogadores) e confiança (jogadores comuns / jogadores do jogo A).

### 16.9 Análise de retenção

```
GET /admin/analytics/retention?days=30
```

**Resposta**: `{ "D1": "8.5%", "D3": "...", "D7": "...", "D30": "..." }` — taxas de retenção de 1/3/7/30 dias agrupadas por dia de registro.

### 16.10 Funil de conversão

```
GET /admin/analytics/funnel?days=30
```

**Resposta**: as quatro etapas registro → primeira recarga → primeira troca → primeira partida, com `step`, `count`, `rate` (porcentagem relativa ao número de registros).

### 16.11 Tendência de ARPU/ARPPU

```
GET /admin/analytics/arpu?days=30
```

**Resposta**: `{ "dates": [...], "arpu": [...], "arppu": [...] }` — receita média diária por usuário (ARPU) e receita média por usuário pagante (ARPPU).

### 16.12 Métricas econômicas dos jogos

```
GET /admin/analytics/economy
```

**Resposta**: array `currencies`, cada item com `game_name`, `currency`, `symbol`, `total_minted` (total cunhado), `total_burned` (total destruído), `circulation` (circulação), `inflation_rate` (taxa de inflação), calculado com aritmética de alta precisão bcmath.
