# Documento de design da arquitetura de segurança
<!-- lang-nav -->

Languages: [中文](SECURITY.md) · [English](SECURITY.en.md) · [한국어](SECURITY.ko.md) · [Русский](SECURITY.ru.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Español](SECURITY.es.md) · **Português** · [हिन्दी](SECURITY.hi.md) · [العربية](SECURITY.ar.md) · [বাংলা](SECURITY.bn.md) · [Bahasa Indonesia](SECURITY.id.md) · [日本語](SECURITY.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Panorama da defesa em profundidade

O sistema adota um modelo de defesa em profundidade em 7 camadas, filtrando requisições maliciosas de fora para dentro, camada por camada, garantindo que, mesmo se qualquer camada individual falhar, as linhas de defesa seguintes continuem como rede de segurança.

Toda a cadeia de middlewares é executada na seguinte ordem (ver `config/middleware.php`):

```
请求 → Cors → SecurityFilter → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Camada | Middleware/mecanismo | Alvo de proteção |
|----|--------|---------|
| 1 | SecurityFilter | Interceptação de ataques XSS / injeção SQL / traversal de caminho / injeção de comandos / CSRF |
| 2 | Cors | Segurança de CORS + injeção de cabeçalhos de resposta de segurança |
| 3 | RateLimit | Rate limit de janela deslizante em Redis, contra força bruta |
| 4 | AdminAuth | Autenticação JWT + logout com lista negra |
| 5 | AdminPermission | Autorização RBAC em granularidade method.path |
| 6 | OperationLog | Auditoria de operações + rastreamento de origem |
| 7 | Criptografia de dados | Ofuscação de IDs com Hashids + criptografia de DB com Encryptable + criptografia de transporte com EncryptionService |

As três camadas do frontend (Flutter) têm validação de entrada independente; o backend não confia nelas, cada camada defende de forma independente.

---

## 2. Motor de detecção de ataques

### 2.0 Restrição de métodos HTTP

O SecurityFilter valida o método HTTP antes de qualquer detecção de ataque, permitindo apenas os seguintes métodos padrão:

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

Métodos não padrão (como TRACE, CONNECT, PATCH, métodos personalizados etc.) retornam diretamente **405 Method Not Allowed**, com corpo HTML vazio, sem entrar na detecção de ataques ou na lógica de negócio.

Esta é a primeira linha de defesa da defesa em profundidade, bloqueando efetivamente:
- Ataques de rastreamento entre sites via TRACE (XST)
- Abuso de proxy de túnel via CONNECT
- Sondagem de métodos WebDAV não padrão
- Enumeração de métodos HTTP por scanners automatizados

### 2.1 XSS (script entre sites)

Todos os regex vêm de `SecurityFilter::PATTERNS['XSS']`, com correspondência sem distinção de maiúsculas/minúsculas.

| Padrão de detecção | Regex | Ataque defendido |
|----------|------|-----------|
| Tag de script | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` e variantes com espaços |
| Atributo de evento | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | Eventos inline como `onclick="javascript:..."` |
| Protocolo JS falso | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` etc. |
| XSS por Data URI | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` etc. |
| Injeção de template | `\{\{.*?\}\}` | Injeção de template de servidor/Angular/Vue como `{{constructor}}`, `{{7*7}}` |

### 2.2 Injeção SQL

| Padrão de detecção | Regex | Ataque defendido |
|----------|------|-----------|
| Consulta UNION | `\bUNION\s+(?:ALL\s+)?SELECT\b` | Exfiltração de dados com `UNION SELECT`, `UNION ALL SELECT` |
| Injeção OR sempre verdadeira | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| Destruição de estrutura de tabela | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| Chamada de stored procedure | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | Execução de comandos por stored procedures estendidas do MSSQL |
| Sondagem de metadados | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | Sondagem de estrutura de banco em MySQL/PG/SQLite/MSSQL |
| Bypass por comentário | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | Bypass por comentário como `'-- OR SELECT`, `'# AND UPDATE` |

### 2.3 Traversal de caminho

| Padrão de detecção | Regex | Ataque defendido |
|----------|------|-----------|
| Retrocesso de diretório | `\.\.[\/\\\\]{2,}` | Retrocesso de múltiplos níveis como `../`, `..\`, `....//` |
| Sondagem de arquivos sensíveis | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` etc. |
| Truncamento por byte nulo | `%00` | Bypass de validação de extensão como `../../../etc/passwd%00.jpg` |

### 2.4 Injeção de comandos

| Padrão de detecção | Regex | Ataque defendido |
|----------|------|-----------|
| Comandos por pipe/ponto e vírgula | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| Substituição com crases | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| Substituição $() | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| Download remoto com pipe | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF (falsificação de requisição entre sites)

A lógica de validação está implementada em `SecurityFilter::checkCsrf()`:

```php
// 仅 POST/PUT/DELETE 触发校验
// Origin 头和 Referer 均为空 → 放行（非浏览器客户端）
// Origin 非空 → 解析 Origin 域名与 Host 比对
```

Regras de comparação:
- Remove o prefixo `www.` do Host e compara exatamente com o domínio do Origin
- Se o Host for domínio pai do Origin (ex.: `Origin: app.example.com`, `Host: example.com` — dispara `str_contains($originHost, '.' . $hostOnly)`), libera
- Nem correspondência exata nem subdomínio → retorna 403, julgado como ataque CSRF

Observação: clientes não navegador (ex.: curl sem Origin/Referer) passam direto; a proteção CSRF é eficaz apenas em ambientes de navegador.

### 2.6 Upload malicioso de arquivos

| Padrão de detecção | Regex | Ataque defendido |
|----------|------|-----------|
| Disfarce de extensão dupla | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | Bypass de lista branca com `shell.php.png`, `shell.phar.jpg` |
| Extensão PHP | `\.php\s*$/m` | Passagem direta de caminho `.php` nos parâmetros da requisição |

---

## 3. Escalação de ataques e lista negra de IP

O SecurityFilter tem um mecanismo interno de escalação de ataques para impedir que o mesmo IP continue escaneando ataques.

### Fluxo de escalação

```
第 1 次扫描命中 → Redis INCR security_escalate:{ip} = 1, TTL=60s
第 2 次扫描命中 → INCR → 2
...
第 5 次扫描命中 → INCR → 5
    → 触发封禁: SETEX security_ban:{ip} 900 1
    → 清除计数器 DEL security_escalate:{ip}
    → 写入安全日志: [SECURITY] IP banned 15min
```

### Comportamento durante o banimento

Toda requisição verifica `isBanned()` ao entrar no SecurityFilter:

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

Um IP banido tem todas as requisições (incluindo as legítimas) rejeitadas diretamente com 403 por 15 minutos, pulando completamente a lógica de negócio posterior.

### Constantes de configuração

| Constante | Valor | Significado |
|------|-----|------|
| ESCALATE_LIMIT | 5 | Limite de disparos dentro da janela de 60 segundos |
| ESCALATE_WINDOW | 60 | Janela do contador (segundos) |
| BAN_DURATION | 900 | Duração da lista negra (segundos), ou seja, 15 minutos |

### Log de segurança

Local do arquivo: `runtime/logs/security.log`

Exemplo de formato de log:
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/v1/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### Limite de tamanho do corpo da requisição

`Content-Length > 10MB` retorna diretamente 413 Payload Too Large, contra ataques DoS de corpo de requisição gigante.

### Validação de Content-Type

Requisições POST/PUT **devem** declarar `Content-Type` como `application/json` ou `application/x-www-form-urlencoded`; caso contrário, retorna 415 Unsupported Media Type. Requisições de upload de arquivo (com campo file) pulam esta verificação.

---

## 4. Cabeçalhos de segurança da resposta

Todos os cabeçalhos são injetados no middleware `Cors` via `$response->withHeaders()` em cada resposta.

| Cabeçalho | Valor | Função |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Permite CORS de qualquer origem (cenário de painel administrativo em intranet) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Conjunto de métodos permitidos |
| Access-Control-Allow-Headers | `Authorization,Content-Type` | Cabeçalhos personalizados permitidos |
| Access-Control-Max-Age | `86400` | Cache da requisição preflight por 24 horas |
| X-Content-Type-Options | `nosniff` | Proíbe MIME sniffing do navegador |
| X-Frame-Options | `DENY` | Proíbe qualquer incorporação em iframe, contra clickjacking |
| X-XSS-Protection | `1; mode=block` | Habilita o filtro XSS embutido do navegador e bloqueia a renderização da página |
| Referrer-Policy | `strict-origin-when-cross-origin` | Mesma origem envia URL completa; cross-origin envia apenas o domínio |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Desabilita em todo o site as APIs de câmera/microfone/geolocalização |

Requisições preflight OPTIONS retornam diretamente 204 com resposta vazia, sem entrar na cadeia de middlewares posterior.

### 4.2 Content-Security-Policy (CSP)

Injetada no middleware Cors junto com os outros cabeçalhos de segurança, fornecendo defesa em profundidade que restringe as origens de recursos que o navegador pode carregar e executar.

| Cabeçalho | Valor | Função |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Restringe as origens de scripts/estilos/imagens/conexões/frames/formulários |
| X-Permitted-Cross-Domain-Policies | `none` | Proíbe o carregamento de arquivos de política cross-domain por Adobe Flash/PDF |

Pontos principais da política CSP:
- `default-src 'self'`: por padrão, apenas recursos de mesma origem
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: permite scripts de mesma origem + scripts inline (necessário para Flutter Web) + eval (necessário para depuração de Flutter Web)
- `frame-ancestors 'none'`: proíbe incorporação em iframe por qualquer página, dupla garantia com X-Frame-Options: DENY
- `base-uri 'self'`: limita a tag `<base>` a apontar apenas para a mesma origem
- `form-action 'self'`: limita formulários a enviar apenas para a mesma origem

---

## 5. Estratégia de rate limit

### Algoritmo

Janela deslizante com Redis Sorted Set + script Lua atômico, operações-chave:

```lua
-- 1. 清理窗口外的旧记录
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. 检查当前窗口计数
local count = redis.call('ZCARD', KEYS[1])
-- 3. 超限则返回 {0, count}，未超限则 ZADD 并返回 {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- 随机后缀避免同毫秒覆盖
redis.call('EXPIRE', KEYS[1], window + 10)
```

O script Lua é executado em thread única no servidor Redis, **naturalmente atômico**, eliminando as condições de corrida TOCTOU (Time-of-check to Time-of-use).

### Configuração de rate limit

| Rota | Limite | Janela | Cenário |
|------|------|------|------|
| Padrão (todas as rotas) | 60/minuto | 60s | API geral |
| `/api/v1/auth/login` | 10/minuto | 60s | Login (contra força bruta) |
| `/api/v1/auth/register` | 5/minuto | 60s | Registro (contra cadastro em massa) |

### Cabeçalhos de resposta

Ao disparar o rate limit, retorna HTTP 429 com corpo JSON:
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

Todas as respostas (incluindo as normais) carregam os seguintes cabeçalhos:

| Cabeçalho | Descrição |
|----|------|
| X-RateLimit-Limit | Número máximo de requisições permitidas na janela atual |
| X-RateLimit-Remaining | Requisições restantes disponíveis na janela atual |
| X-RateLimit-Reset | Timestamp Unix de reset da janela |
| Retry-After | Presente apenas quando o rate limit dispara; segundos sugeridos de espera |

### Estratégia de degradação

Em caso de anomalia do Redis (timeout de conexão, indisponível etc.), é **fail-closed**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    // Redis down: 限流不可用即拒绝，避免安全防线失效（登录/支付回调限流为空转）
    return json(['code' => 503, 'message' => '服务暂不可用，请稍后再试', 'data' => []])
        ->withStatus(503)->withHeaders(['Retry-After' => '5']);
}
```

O rate limit é a primeira linha de defesa do login contra força bruta e dos callbacks de pagamento contra replay; com falha no Redis, é melhor rejeitar a requisição (503) do que liberar.

### 5.4 Mecanismo de bloqueio de conta

Além do rate limit, a interface de login tem um mecanismo adicional de **bloqueio de conta**, contra força bruta direcionada a usuários específicos.

**Fluxo de bloqueio**:

```
登录失败 → Redis INCR account_lockout:{userId} TTL=900s
连续 5 次失败 → Redis SETEX account_locked:{userId} 900 1
            → 返回 429 "账号已被锁定，请15分钟后再试"
            → 清除计数器 DEL account_lockout:{userId}
```

**Comportamento durante o bloqueio**:

Durante o bloqueio, todas as requisições de login retornam diretamente 429, sem validação de senha, bloqueando completamente as tentativas de força bruta.

**Constantes de configuração**:

| Constante | Valor | Significado |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Número máximo de falhas consecutivas |
| LOCKOUT_DURATION | 900 | Duração do bloqueio (segundos), ou seja, 15 minutos |

Observação: o bloqueio de conta é baseado em `userId`, não em IP; portanto, trocar de IP não contorna o bloqueio. Combinado com o rate limit por IP (10/minuto), forma dupla proteção:
- Nível de IP: rate limit de 10/minuto bloqueia força bruta distribuída
- Nível de conta: bloqueio após 5 falhas bloqueia força bruta direcionada

---

## 6. Autenticação e autorização

### 6.1 Autenticação JWT

Implementada pelo middleware AdminAuth, montado nos grupos de rotas que exigem autenticação.

**Configuração de parâmetros** (`config/plugin/erikwang2013/jwt/jwt`, injetada via `.env`):

| Parâmetro | Valor | Descrição |
|------|-----|------|
| Algoritmo | HS256 | Assinatura simétrica HMAC-SHA256 |
| Chave | `JWT_SECRET_KEY` | Injetada por variável de ambiente; **recusa a inicialização** (fail-closed) se ausente ou ainda com o valor padrão |
| TTL do access_token | 7200s (2h) | `JWT_TTL` |
| TTL do refresh_token | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Emissor | `open-admin` | `JWT_ISSUER` |
| Audiência | `open-admin` | `JWT_AUDIENCE` |

**Extração do Token**: do cabeçalho `Authorization: Bearer <token>`, removendo o prefixo `Bearer ` para obter o JWT original.

**Fluxo de autenticação**:
1. Token vazio → diretamente 401 `{"code": 401, "message": "未登录"}`
2. Verifica lista negra do Redis `jwt_blacklist:{md5(token)}` → presente → 401 `Token已失效，请重新登录`
3. Decodificação do JWT → falha (expirado/assinatura não corresponde) → 401 `Token已过期或无效`
4. Sucesso → injeta `$request->adminId` e `$request->adminUsername`

**Mecanismo de lista negra**: no logout do usuário, `md5(token)` é gravado no Redis com TTL igual à validade restante do JWT. Em falha do Redis, a verificação da lista negra é pulada (fail-open); o Token deslogado pode ser usado por pouco tempo, mas o curto prazo de validade do próprio JWT (2h) serve como proteção de rede de segurança.

**Refresh de Token**: `POST /api/v1/auth/refresh` valida o refresh token original (`token_type=refresh` e não expirado/não banido) antes de rotacionar e emitir, e valida que `sub` deve ser um ID de usuário válido — **não emite mais refresh tokens com sub=null**; falha no refresh retorna diretamente 401.

### 6.2 Limite de sessões concorrentes

Para evitar o abuso de Token vazado em múltiplos dispositivos, o sistema limita o número de Tokens válidos que um mesmo usuário pode manter simultaneamente.

**Lógica de limitação**:

```
登录成功 → 签发新 Token
         → 查询当前用户有效 Token 数量: Redis SCARD user_tokens:{userId}
         → 若数量 >= 3（MAX_CONCURRENT_SESSIONS）:
            → 按创建时间升序排列，移除最旧的 Token:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → 将新 Token 加入集合: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Constantes de configuração**:

| Constante | Valor | Significado |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Número máximo de Tokens concorrentes por usuário |

**Cenário de expulsão**: quando o usuário faz login no 4º dispositivo, o Token do 1º dispositivo é forçado para a lista negra e as requisições seguintes retornam 401 "Token已失效，请重新登录".

No logout, o Token atual é removido do conjunto. Quando o Token expira naturalmente, a chave do Redis expira automaticamente e os membros do conjunto diminuem.

### 6.3 Modelo de permissões RBAC

Implementado pelo middleware AdminPermission.

**Modelo de dados**: associação em três níveis User -> Role -> Permission

- `game_admin_user` (tabela de usuários)
- `game_admin_user_role` (tabela de associação usuário-função)
- `game_admin_role` (tabela de funções)
- `game_admin_role_permission` (tabela de associação função-permissão)
- `game_admin_permission` (tabela de permissões)

**Tipos de permissão**:
| type | Significado | Exemplo |
|------|------|------|
| 1 | Permissão de menu | Controla a visibilidade da navegação à esquerda |
| 2 | Permissão de botão | Controla os botões de operação dentro da página (criar/editar/excluir) |
| 3 | Permissão de API | Controla as chamadas de interface do backend |

Formato do identificador de permissão de API: `{method}.{path}`

Por exemplo:
- `post.admin/user` — criar usuário
- `put.admin/user` — editar usuário
- `delete.admin/user` — excluir usuário
- `get.admin/user` — visualizar lista de usuários

**Fluxo de autorização**:
1. `$request->adminId` vazio (não logado) → diretamente 401 `{"code": 401, "message": "未登录"}`, sem liberar
2. Obtém usuário → funções (pulando funções desabilitadas com `status=0`) → lista de permissões
3. Superadministrador (`slug = '*'`) → libera diretamente
4. Constrói `strtolower(method) . '.' . trim(path, '/')` → compara com a lista de permissões
5. Sem correspondência → 403 `{"code": 403, "message": "无权限访问"}`

**Confirmação secundária**: o BaseController fornece o método `confirmPassword()`; operações sensíveis (exclusão de usuários, exportação de dados etc.) exigem adicionalmente a senha atual na camada de Controller, evitando operações não autorizadas após sequestro de sessão.

### 6.4 Verificação de assinatura de callbacks de pagamento (fail-closed)

`POST /api/v1/payment/callback` (callbacks de recarga Stripe/PayPal) usa verificação de assinatura **fail-closed**; qualquer configuração ausente ou erro de validação rejeita o callback:

| Cenário | Comportamento |
|------|------|
| Stripe sem `STRIPE_WEBHOOK_SECRET` configurado | Rejeita (403), não aceita mais callbacks sem assinatura |
| Assinatura Stripe ausente / falha na verificação | Rejeita (403) |
| Timestamp Stripe `t=` ausente ou diferença com o horário do servidor **> ±5 minutos** | Rejeita (403), contra replay |
| PayPal sem `PAYPAL_WEBHOOK_ID` configurado | Rejeita (403) |
| Verificação reversa do PayPal com anomalia / não SUCCESS | Rejeita (403) |
| Com `CALLBACK_TRUSTED_IPS` opcional configurado, IP de origem fora da lista branca | Rejeita (403) |
| Provider do callback inconsistente com o método de pagamento do pedido / método de pagamento inexistente | Rejeita (403) |

A creditação do callback (atualização de status + saldo + fluxo) é concluída dentro da mesma transação de banco; se qualquer etapa falhar, tudo é revertido, evitando creditação parcial.

---

## 7. Logs de auditoria

### 7.1 Logs de operação

O middleware OperationLog registra automaticamente logs de operação para requisições POST / PUT / DELETE. Requisições GET não são registradas.

**Campos registrados**:

| Campo | Origem | Descrição |
|------|------|------|
| id | SnowflakeService::generate() | ID globalmente único |
| user_id | `$request->adminId` | ID do operador; 0 se não logado |
| action | `$request->method()` | Equivalente ao method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Caminho da requisição |
| ip | `$request->getRealIp()` | IP real do cliente |
| source | detectSource() | Plataforma de origem do cliente |
| input | corpo da requisição (JSON mascarado) | Dados enviados na operação |
| created_at | `date('Y-m-d H:i:s')` | Horário da operação |

**Filtro de campos sensíveis**: percorre recursivamente o corpo da requisição; os valores dos seguintes campos são substituídos por `***`:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Detecção de origem** (`detectSource()`), por prioridade:

1. Lê primeiro o cabeçalho personalizado `X-Client-Platform` (declarado explicitamente pelos clientes nativos)
2. Faz fallback para inferência pela string User-Agent (ordem de detecção do método `detectSource()`):

| Plataforma | Palavra-chave do UA |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Valor padrão de fallback |

**Tolerância a falhas**: anomalias na escrita do log não bloqueiam requisições de negócio (`catch (\Throwable)` engole silenciosamente).

### 7.2 Logs de segurança

**Local do arquivo**: `runtime/logs/security.log`

**Conteúdo registrado**:
- Logs de interceptação de ataques: categoria do ataque, IP, caminho, campo, origem, trecho do payload (primeiros 200 caracteres)
- Notificações de banimento de IP: IP banido, número de disparos

As permissões do log são `FILE_APPEND | LOCK_EX`, garantindo escrita concorrente segura.

---

## 8. Proteção de dados

O sistema adota uma estratégia de proteção de dados em três camadas, correspondendo às três fases do fluxo de dados.

### 8.1 Camada de transporte — EncryptionService

O `EncryptionService` usa o pacote `erikwang2013/encryption` para criptografar/descriptografar campos sensíveis nas requisições/respostas da API.

**Detalhes técnicos**:
- Algoritmo: `aes-256-cbc-hmac` (com assinatura HMAC própria contra adulteração)
- Chave: variável de ambiente `ENCRYPTION_KEY`, alinhada automaticamente a 32 bytes
- Uso: transmissão entre cliente e API de campos como telefone e número de identidade

**Métodos utilitários de mascaramento**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (nome de usuário com mais de 2 caracteres) ou `a**@example.com`

### 8.2 Camada de armazenamento — Cast Encryptable

O modelo `AdminUser` usa o cast Eloquent `Erikwang2013\Encryptable\Encryptable`; campos correspondentes:

- `email` → cast para Encryptable, criptografia/descriptografia automática
- `phone` → cast para Encryptable, criptografia/descriptografia automática
- `id_card` → cast para Encryptable, criptografia/descriptografia automática

Ao gravar no banco, criptografa automaticamente em texto cifrado; ao ler, descriptografa automaticamente em texto claro. O tipo de coluna de armazenamento é `VARCHAR(500)`, com o texto cifrado armazenado em base64.

**Sistema de chaves**: independente da criptografia da camada de transporte (`ENCRYPTION_KEY`), usa `ENCRYPTABLE_KEY`; o vazamento de uma chave não compromete a outra camada.

Rotação de chaves: a variável de ambiente `ENCRYPTION_PREVIOUS_KEYS` suporta uma lista de chaves históricas (separadas por vírgula); ao ler dados antigos, tenta descriptografar com as chaves históricas e, ao gravar, re-criptografa com a chave atual.

### 8.3 Camada de exibição — Ofuscação de IDs e mascaramento

**Ofuscação de IDs com Hashids**: o `HashidsService` usa o pacote `erikwang2013/hashids`.

- Os IDs BIGINT do banco retornados pela API externa são codificados como strings hash (ex.: `xK3mN9qR2pL7wV8b`)
- O cliente envia a string hash nas requisições; o backend decodifica automaticamente para o ID original
- O salt `HASHIDS_SALT` é injetado por variável de ambiente; salts diferentes produzem resultados de codificação/decodificação totalmente diferentes
- O hash tem comprimento mínimo de 16 caracteres, usando o conjunto de caracteres alfanuméricos de 62 bits
- O BaseController fornece os métodos convenientes `encodeId()`, `decodeId()`, `encodeIds()`

**Mascaramento em exportação**: nas exportações Excel/PDF (ExportController), os campos sensíveis são mascarados uniformemente:
- Telefone: `138****1234`
- E-mail: `a***@example.com`
- Identidade: totalmente coberta como `********`

---

## 9. Gerenciamento de chaves

Todas as chaves são injetadas via variáveis de ambiente do `.env`; os arquivos de configuração leem com `getenv()` e têm valores padrão de fallback embutidos (seguros apenas para desenvolvimento).

| Variável de ambiente | Finalidade | Pacote | Requisito de produção |
|----------|------|-----|---------|
| JWT_SECRET_KEY | Chave de assinatura JWT | erikwang2013/jwt-webman | String aleatória com 64+ caracteres; recusa inicialização se ausente ou padrão |
| JWT_ALGORITHM | Algoritmo de assinatura JWT | mesmo acima | Manter HS256 |
| HASHIDS_SALT | Salt de codificação de IDs | erikwang2013/hashids | String aleatória |
| SNOWFLAKE_DATACENTER_ID | ID do datacenter (0-31) | erikwang2013/snowflake-php | Manter o padrão em datacenter único |
| ENCRYPTION_KEY | Chave de criptografia da camada de transporte da API | erikwang2013/encryption | String aleatória de 32 bytes |
| ENCRYPTABLE_KEY | Chave de criptografia da camada de armazenamento do DB | erikwang2013/encryptable | String aleatória de 32 bytes, diferente da chave de transporte |

**Requisitos de segurança**:
- O arquivo `.env` está no `.gitignore`; é estritamente proibido commitá-lo no repositório
- O `.env.example` é um template público e não contém chaves reais
- Em produção, **obrigatoriamente** troque todas as chaves padrão por strings aleatórias
- Recomenda-se gerar chaves com `openssl rand -base64 32`

### Isolamento de armazenamento de chaves

| Camada | Chave de configuração | Variável de ambiente da chave |
|----|--------|-------------|
| Criptografia de transporte | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Criptografia de armazenamento | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| Ofuscação de IDs | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| Assinatura JWT | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET_KEY` |

---

## 10. security.txt (RFC 9116)

O sistema fornece um endpoint de informações de contato de segurança compatível com RFC 9116 em `/.well-known/security.txt`, facilitando que pesquisadores de segurança encontrem rapidamente o canal de reporte ao descobrir vulnerabilidades.

**Forma de acesso**:

```
GET /.well-known/security.txt
```

**Conteúdo da resposta**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Descrição dos campos**:

| Campo | Descrição |
|------|------|
| Contact | Contato para reporte de vulnerabilidades de segurança |
| Expires | Data de expiração do arquivo, requer atualização periódica |
| Preferred-Languages | Idiomas de comunicação preferidos |
| Canonical | URL canônica deste arquivo |
| Policy | Link da política de segurança / divulgação de vulnerabilidades |

Este endpoint não é limitado por middlewares de rate limit, autenticação etc.; qualquer pessoa pode acessá-lo diretamente.

---

## 11. Configuração de segurança Nginx

O projeto fornece `docs/nginx-security.conf` como configuração de referência para reforço de segurança do proxy reverso Nginx em produção.

**Medidas de segurança incluídas**:

| Item de configuração | Função |
|--------|------|
| `server_tokens off` | Oculta o número de versão do Nginx |
| `client_max_body_size 10m` | Limita o tamanho do corpo da requisição, em conjunto com o SecurityFilter |
| `limit_req_zone` | Limite de frequência de requisições no nível do Nginx |
| `limit_conn_zone` | Limite de conexões concorrentes |
| `add_header` de segurança | Adiciona cabeçalhos de segurança como X-XSS-Protection no nível do Nginx |
| `if ($request_method)` | Rejeita métodos HTTP não padrão no nível do Nginx |
| Configuração SSL/TLS | Configuração moderna TLS 1.2/1.3, desabilita suites de criptografia fracas |
| Ocultação de cabeçalhos do backend | `proxy_hide_header` remove cabeçalhos sensíveis como a versão do webman |

**Como usar**: mescle as configurações de `docs/nginx-security.conf` no bloco server do seu Nginx, ajustando conforme o domínio real e o caminho do certificado.

---

## 12. Modelo de ameaças

### 12.1 Ameaças defendidas

| Tipo de ameaça | Vetor de ataque | Camadas de defesa |
|----------|---------|---------|
| Abuso de método HTTP | Ataque XST com TRACE/TRACK, proxy de túnel CONNECT, sondagem de métodos WebDAV | Lista branca de métodos do SecurityFilter 405 (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| Força bruta direcionada | Tentativas repetidas de senha contra usuários específicos | Bloqueio de conta (5 falhas bloqueiam 15 min) + RateLimit (login 10/min) + Captcha |
| Força bruta | Tentativas distribuídas de usuário/senha por vários IPs | RateLimit (login 10/min) + Captcha |
| XSS (script entre sites) | `<script>`, onerror, javascript: | SecurityFilter (5 padrões) + cabeçalho de resposta X-XSS-Protection + CSP |
| Injeção SQL | UNION SELECT, OR 1=1, bypass por comentário | SecurityFilter (6 padrões) + consultas parametrizadas do ORM Eloquent |
| CSRF (falsificação de requisição entre sites) | Sites maliciosos enviam requisições em nome do usuário | Validação Origin/Referer do SecurityFilter |
| Traversal de caminho | `../../etc/passwd` | Padrões de traversal do SecurityFilter + lista branca de extensões do UploadController |
| Injeção de comandos | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 padrões) |
| Sequestro de sessão | Roubo de Token JWT | JWT de curta validade (2h) + logout com lista negra + confirmação secundária de senha em operações sensíveis |
| Enumeração de IDs | Percorrer IDs numéricos para estimar volume de dados | Ofuscação com Hashids para strings aleatórias |
| Vazamento de dados | Exfiltração do DB / homem no meio / vazamento de logs | Criptografia/mascaramento em três camadas + filtro de campos sensíveis do OperationLog |
| Ataques DoS | Corpo de requisição gigante / requisições em alta frequência | Limite de 10MB do corpo + RateLimit 60/min + lista negra de IP |
| Escalação de privilégios | Usuários de baixa permissão acessam interfaces administrativas | Autorização RBAC em granularidade method.path |
| Ataques de upload de arquivos | Extensão dupla shell.php.png | Detecção de arquivos maliciosos do SecurityFilter |

### 12.2 Limitações conhecidas

| Limitação | Escopo do impacto | Medidas de mitigação |
|------|---------|---------|
| A proteção CSRF vale apenas para navegadores | Clientes não navegador (curl, Postman, Apps móveis) podem pular a verificação Origin/Referer | Clientes não navegador naturalmente não sofrem CSRF; depende do JWT em vez de Cookie |
| Redis indisponível: rate limit fail-closed (503), verificação de lista negra fail-open | Durante o rate limit, parte das requisições é rejeitada; Tokens deslogados ficam utilizáveis por pouco tempo | Monitorar disponibilidade do Redis com alertas; a curta validade do JWT serve como rede de segurança |
| Sem motor WAF dedicado | O SecurityFilter usa correspondência de regex com `@preg_match`, não é um motor de regras WAF dedicado | Em produção, recomenda-se Nginx ModSecurity ou Cloudflare WAF na frente |
| JWT sem estado não pode ser invalidado proativamente | Tokens não podem ser revogados pelo servidor antes da expiração (exceto lista negra) | Lista negra + TTL curto de 2h reduzem a janela de risco |
| Lista negra de IP apenas em memória | Após reinício do Redis, a lista negra é perdida | A duração do banimento é de apenas 15 minutos, impacto limitado |
| Endpoints de administrador sem rate limit especial | Interfaces administrativas compartilham o limite padrão de 60/min com as comuns | A frequência de operações administrativas é naturalmente baixa; sem necessidade de diferenciação por enquanto |
| `@preg_match` suprime erros | Falha silenciosa em caso de regex malformado | `preg_last_error()` pode ser monitorado; ainda não implementado |
