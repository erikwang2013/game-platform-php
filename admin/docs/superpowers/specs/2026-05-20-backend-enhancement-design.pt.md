# Subprojeto A: Melhorias no Backend — Especificação de design
<!-- lang-nav -->

Languages: [中文](2026-05-20-backend-enhancement-design.md) · [English](2026-05-20-backend-enhancement-design.en.md) · [한국어](2026-05-20-backend-enhancement-design.ko.md) · [Русский](2026-05-20-backend-enhancement-design.ru.md) · [Deutsch](2026-05-20-backend-enhancement-design.de.md) · [Français](2026-05-20-backend-enhancement-design.fr.md) · [Español](2026-05-20-backend-enhancement-design.es.md) · **Português** · [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) · [العربية](2026-05-20-backend-enhancement-design.ar.md) · [বাংলা](2026-05-20-backend-enhancement-design.bn.md) · [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) · [日本語](2026-05-20-backend-enhancement-design.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Escopo

Esta é uma melhoria de backend, com 15 funcionalidades no total, envolvendo 9 arquivos novos + 4 arquivos modificados.

---

## Lista de arquivos novos/modificados

```
app/middleware/
├── OperationLog.php          # Novo: registro automático de operações
├── Cors.php                  # Novo: CORS
└── RateLimit.php             # Novo: rate limit via Redis
app/admin/controller/
├── ConfigController.php      # Novo: CRUD de configurações do sistema
├── LogController.php         # Novo: consulta de logs de operação
├── ProfileController.php     # Novo: central pessoal (inclui logout)
├── UploadController.php      # Novo: upload de arquivos
├── ImportController.php      # Novo: importação de usuários via Excel
└── HealthController.php      # Novo: health check
app/model/
├── AdminUser.php             # Modificado: adicionar SoftDeletes + trait Searchable
└── OperationLog.php          # Modificado: adicionar public $timestamps = false
app/middleware/
└── AdminAuth.php             # Modificado: validação de blacklist JWT
app/admin/controller/
├── DashboardController.php   # Modificado: estatísticas reais do banco de dados
└── UserController.php        # Modificado: novas ações em lote
config/
└── route.php                 # Modificado: novas rotas + middlewares
```

---

## 1. Middlewares

### 1.1 Middleware CORS

**Arquivo**: `app/middleware/Cors.php`

- Requisições de preflight OPTIONS retornam diretamente 204
- Requisições não-preflight adicionam `Access-Control-Allow-Origin: *` no cabeçalho da resposta
- Cabeçalhos permitidos: `Authorization, Content-Type, API-Version`
- Cache máximo: 86400 segundos

Montagem: middleware global (`config/middleware.php`)

### 1.2 Middleware de rate limit

**Arquivo**: `app/middleware/RateLimit.php`

- Armazenamento: Redis Sorted Set, janela deslizante
- Padrão: 60 vezes/minuto/IP/rota
- Interfaces sensíveis:
  - `/api/auth/login`: 10 vezes/minuto
  - `/api/auth/register`: 5 vezes/minuto
- Excedeu o limite retorna `429 Too Many Requests`

Montagem: middleware global (`config/middleware.php`), depois do Cors e antes do ApiVersion

### 1.3 Middleware de log de operação

**Arquivo**: `app/middleware/OperationLog.php`

- Registra apenas POST/PUT/DELETE
- Campos registrados: user_id, action, method, path, ip, input(JSON)
- Escrita assíncrona após o retorno da resposta (sem bloqueio)

Montagem: grupo de rotas `/admin`, depois do AdminPermission

### 1.4 Cadeia de execução dos middlewares globais

```
Todas as requisições:
  Cors → RateLimit → ApiVersion → {middlewares da rota} → Controller

Requisições /admin/*:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Logout (blacklist JWT)

**Arquivo**: `app/middleware/AdminAuth.php` (modificado)

**Princípio**: o JWT é stateless por natureza; no logout, o token é adicionado à blacklist do Redis, e o AdminAuth consulta a blacklist primeiro durante a validação.

**Reforma do AdminAuth**:
- Novo início do `process()`: verificar se o token atual está na blacklist no set `jwt_blacklist` do Redis
- Se estiver na blacklist, retorna 401

**Rota de logout** (sob a central pessoal):

| Método | Rota | Observação |
|------|------|------|
| `POST` | `/admin/profile/logout` | Adiciona o Bearer token atual à blacklist do Redis, TTL=tempo restante de validade do token |

**Lógica do Logout**:
```php
// Analisa o tempo restante de validade do token
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// Adiciona à blacklist
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Novos controllers e reformas existentes

### 2.1 CRUD de configurações do sistema (`ConfigController`)

Herda `BaseController`.

| Método | Rota | Observação |
|------|------|------|
| `index()` | GET `/admin/config` | Lista paginada, filtra por `group`, paginação `page`/`limit` |
| `store()` | POST `/admin/config` | Cria item de configuração, obrigatório: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Atualiza value/type/description do item de configuração |
| `destroy()` | DELETE `/admin/config/{id}` | Exclui item de configuração, exige `confirmPassword()` |

### 2.2 Consulta de logs de operação (`LogController`)

Herda `BaseController`.

| Método | Rota | Observação |
|------|------|------|
| `index()` | GET `/admin/log` | Lista paginada, suporta filtros: user_id, action, path, created_at (intervalo) |

Não oferece criar/editar/excluir; os logs são registrados automaticamente pelo middleware.

### 2.3 Central pessoal (`ProfileController`)

Herda `BaseController`. Opera o usuário logado no momento (`$request->adminId`).

| Método | Rota | Observação |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Atualiza real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Altera a senha, exige old_password, new_password, new_password_confirmation |

### 2.4 Upload de arquivos (`UploadController`)

Herda `BaseController`.

| Método | Rota | Observação |
|------|------|------|
| `upload()` | POST `/admin/upload` | Recebe o arquivo, suporta image/jpeg/png/gif/pdf/xlsx/docx |

- Máximo 10MB
- Caminho de armazenamento: `public/upload/{date}/{hash}.{ext}`
- Retorno: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Dados reais do dashboard

**Arquivo**: `app/admin/controller/DashboardController.php` (modificado)

Substituir os atuais dados falsos hardcoded por estatísticas reais do banco:

| Métrica | Fonte | Observação |
|------|------|------|
| Total de usuários | `AdminUser::count()` | Sem soft delete |
| Novos hoje | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Total de roles | `AdminRole::count()` | |
| Total de permissões | `AdminPermission::count()` | |
| Dados de tendência | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Novos por dia, últimos 7 dias |
| Dados de distribuição | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Distribuição por status |
| Operações recentes | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | Últimas 10 entradas de log |

### 2.6 Operações em lote de usuários

**Arquivo**: `app/admin/controller/UserController.php` (modificado, novos métodos)

| Método | Rota | Observação |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Exclusão em lote, corpo `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Ativar/desativar em lote, corpo `{ ids: [hashid, ...], status: 1|0 }` |

- Cada id passa primeiro por `decodeId()` para virar BIGINT
- `batchDestroy()` deve passar pela validação de `confirmPassword()`

### 2.7 Importação de dados

**Arquivo**: `app/admin/controller/ImportController.php` (novo)

| Método | Rota | Observação |
|------|------|------|
| `users()` | POST `/admin/import/users` | Upload de arquivo Excel, criação em lote de usuários |

Fluxo:
1. Recebe o arquivo `.xlsx`
2. PhpSpreadsheet faz o parsing; colunas esperadas: `username, password, real_name, phone, email, status`
3. Validação linha a linha + criação (ID gerado por snowflake, senha bcrypt, phone/email criptografados com encryption)
4. Retorna o resultado: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Health check

**Arquivo**: `app/admin/controller/HealthController.php` (novo)

`GET /health` (sem autenticação, não entra no log de operação):

Retorna o status de conexão de cada componente:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- Quando a detecção de um componente falha, o campo correspondente recebe a string de descrição do erro
- A rota não usa o prefixo `/admin`, é registrada separadamente no escopo global

---

## 3. Correções de modelos

### 3.1 Timestamps do OperationLog

**Arquivo**: `app/model/OperationLog.php` (modificado)

A tabela `erik_operation_log` possui apenas a coluna `created_at` (sem `updated_at`). O `save()` padrão do Eloquent tenta gravar `updated_at`, causando erro de SQL.

Correção: `public $timestamps = false;` + especificar `created_at` manualmente na gravação.

### 3.2 Reforma do modelo AdminUser

- Adicionar trait `Searchable`
- Implementar `toSearchableArray()`: retorna username, real_name
- `UserController::index()` usa `AdminUser::search($kw)->get()` em vez de LIKE do MySQL quando detecta palavra-chave

O ES precisa criar o índice primeiro, através do comando do Scout:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Mudanças de rotas

Novas rotas em `config/route.php`:

```php
// Novas dentro do grupo de rotas /admin:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// Health check (rota global, fora do grupo /admin)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// Middlewares:
Adicionar app\middleware\OperationLog::class ao grupo de middlewares /admin
```

Registro dos middlewares globais em `config/middleware.php`:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Códigos de erro adicionais

| code | Significado | Cenário de disparo |
|------|------|---------|
| 429 | Requisições em excesso | RateLimit disparou |

---

## 6. Fora do escopo desta iteração

- Sistema de notificações (exige fila de mensagens + infraestrutura de push no frontend)
- Páginas do frontend Flutter (subprojeto B)
- Refresh de Token no HarmonyOS (subprojeto C)
