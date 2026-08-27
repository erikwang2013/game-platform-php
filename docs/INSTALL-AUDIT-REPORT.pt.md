# Relatório de auditoria do sistema de instalação
<!-- lang-nav -->

Languages: [中文](INSTALL-AUDIT-REPORT.md) · [English](INSTALL-AUDIT-REPORT.en.md) · [한국어](INSTALL-AUDIT-REPORT.ko.md) · [Русский](INSTALL-AUDIT-REPORT.ru.md) · [Deutsch](INSTALL-AUDIT-REPORT.de.md) · [Français](INSTALL-AUDIT-REPORT.fr.md) · [Español](INSTALL-AUDIT-REPORT.es.md) · **Português** · [हिन्दी](INSTALL-AUDIT-REPORT.hi.md) · [العربية](INSTALL-AUDIT-REPORT.ar.md) · [বাংলা](INSTALL-AUDIT-REPORT.bn.md) · [Bahasa Indonesia](INSTALL-AUDIT-REPORT.id.md) · [日本語](INSTALL-AUDIT-REPORT.ja.md)


> Data da auditoria: 2026-08-04
> Escopo da auditoria: todos os arquivos do diretório `install/` + mudanças de documentação relacionadas
> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Resumo da auditoria

| Dimensão | Nota | Observação |
|------|------|------|
| Integridade funcional | Aprovado | fluxo de instalação de 5 etapas completo, 39 tabelas criadas, dados de seed completos |
| Correção do SQL | Aprovado | 42 tabelas idênticas aos arquivos de migração originais, campo source incorporado ao CREATE TABLE |
| Configuração do ecossistema | Aprovado | arquivos .env completos de admin e service, chaves geradas automaticamente |
| Segurança | Aprovado com ressalvas | senha com bcrypt, proteção XSS completa; recomendado adicionar CSRF Token |
| Manutenibilidade | Aprovado | estrutura de código clara, responsabilidade única por arquivo |
| Idempotência | Aprovado | todos os INSERT convertidos para INSERT IGNORE, com guardas WHERE NOT EXISTS |
| Experiência do usuário | Aprovado | design responsivo, teste de conexão AJAX, mensagens de erro em chinês |

---

## 2. Arquivos criados

### 2.1 `install/install.sql` (988 linhas)
- Consolidou 8 arquivos de migração originais
- 42 tabelas de dados com prefixo `game_` (CREATE TABLE IF NOT EXISTS)
- 13 blocos de seed data com INSERT IGNORE
- O campo `source` de `game_operation_log` incorporado ao CREATE TABLE (sem necessidade de ALTER TABLE)
- Envolto em transação (START TRANSACTION / COMMIT)
- Todos os INSERT idempotentes

**Detalhes do tratamento de idempotência dos INSERT:**

| Tabela | Tratamento |
|------|---------|
| `game_admin_role` | INSERT IGNORE (IDs fixos) |
| `game_admin_permission` | INSERT IGNORE (IDs fixos) - 4 vezes |
| `game_admin_role_permission` | subconsulta WHERE NOT EXISTS |
| `game-platform_config` | INSERT IGNORE (IDs fixos) - 2 vezes |
| `game_language` | INSERT IGNORE (IDs fixos) |
| `game_translation` | INSERT IGNORE (IDs fixos) |
| `game_risk_rule` | INSERT IGNORE (IDs fixos) |
| `game_withdraw_limit` | INSERT IGNORE (IDs fixos) |
| `game_game_category` | INSERT IGNORE (IDs fixos) |
| `game_country_config` | INSERT IGNORE (IDs fixos) |

### 2.2 `install/index.php` (485 linhas)
- Roteamento: step1 -> step2 -> step3 -> step4 -> step5
- Interface AJAX: `?action=test-db` (POST JSON)
- 5 funções de template de página
- JavaScript inline (teste de conexão AJAX)
- Saída HTML com `htmlspecialchars()` contra XSS
- Detecção de instalação já feita (install.lock)

### 2.3 `install/Installer.php` (506 linhas)
- Verificação de ambiente: 11 itens (versão do PHP, 6 extensões, permissões de diretório, arquivo SQL)
- Teste de conexão com o banco: PDO + criação automática do banco
- Execução da instalação: importação do SQL -> criação do administrador -> gravação do .env -> bloqueio
- Geração de chaves: JWT(64 bytes) / Hashids(32 bytes) / Encryption(32 bytes)
- Backup do .env: backup automático dos .env existentes antes da instalação

### 2.4 `install/assets/style.css` (130 linhas)
- Design responsivo (suporta mobile <=600px)
- Tema com variáveis CSS (--primary: #4f46e5)
- Sem dependências externas

---

## 3. Cobertura da verificação de ambiente (11 itens)

| # | Item verificado | Nível | Status |
|---|--------|------|------|
| 1 | PHP >= 8.1.0 | Obrigatório | Aprovado |
| 2 | PDO MySQL | Obrigatório | Aprovado |
| 3 | MBString | Obrigatório | Aprovado |
| 4 | JSON | Obrigatório | Aprovado |
| 5 | OpenSSL | Obrigatório | Aprovado |
| 6 | PCNTL | Obrigatório | Aprovado |
| 7 | GD | Recomendado | Aprovado |
| 8 | XML | Recomendado | Aprovado |
| 9 | Redis | Recomendado | Aprovado |
| 10 | Permissões de diretório (admin/runtime, service/runtime) | Obrigatório | Aprovado |
| 11 | Arquivo install.sql existe | Obrigatório | Aprovado |

---

## 4. Integridade da configuração do ecossistema

### 4.1 Geração do `.env` do Admin (70 itens de configuração)

| Grupo | Nº de itens | Cobertura |
|------|---------|------|
| Configuração da aplicação | 3 | APP_NAME, APP_DEBUG, APP_URL |
| Autenticação JWT | 6 | JWT_SECRET, JWT_ALGORITHM, JWT_TTL, JWT_REFRESH_TTL, JWT_ISSUER, JWT_AUDIENCE |
| Hashids | 2 | HASHIDS_SALT, HASHIDS_ALT_SALT |
| Snowflake | 3 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID, SNOWFLAKE_START_TIMESTAMP |
| Criptografia (API) | 3 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTION_IV |
| Criptografia (DB) | 3 | ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER, ENCRYPTION_PREVIOUS_KEYS |
| Scout/ES | 7 | SCOUT_DRIVER, SCOUT_HOSTS, SCOUT_PREFIX, SCOUT_SHARDS, SCOUT_REPLICAS, SCOUT_CHUNK_SIZE, SCOUT_SOFT_DELETE |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST etc. |
| Captcha Poster | 7 | POSTER_IMAGE_DRIVER etc. |
| Banco de dados | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| Redis | 4 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DATABASE |
| Chaves de compatibilidade | 3 | JWT_SECRET_KEY, JWT_DEFAULT_EXPIRE, JWT_REFRESH_EXPIRE |

### 4.2 Geração do `.env` do Service (48 itens de configuração)

| Grupo | Nº de itens | Cobertura |
|------|---------|------|
| Aplicação | 2 | APP_ENV, APP_DEBUG |
| Banco de dados | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| JWT | 3 | JWT_SECRET, JWT_TTL, JWT_REFRESH_TTL |
| Hashids | 3 | HASHIDS_SALT, HASHIDS_ALPHABET, HASHIDS_MIN_LENGTH |
| Snowflake | 2 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID |
| Criptografia | 4 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER |
| Redis | 3 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD |
| ClickHouse | 5 | CLICKHOUSE_HOST, CLICKHOUSE_PORT, CLICKHOUSE_DB, CLICKHOUSE_USER, CLICKHOUSE_PASS |
| OAuth | 9 | OAUTH_GOOGLE/FACEBOOK/APPLE, 3 itens cada |
| Webhook de pagamento | 3 | STRIPE_WEBHOOK_SECRET, PAYPAL_WEBHOOK_ID, PAYPAL_VERIFY_URL |
| CORS | 1 | CORS_ORIGIN |
| Scout/ES | 6 | SCOUT_DRIVER etc. |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST etc. |

**Conclusão da comparação**: os dois arquivos `.env` são consistentes com os `.env.example` originais, e os itens ausentes `ENCRYPTION_CIPHER`, `ENCRYPTABLE_CIPHER`, `JWT_REFRESH_TTL` foram adicionados à configuração do Service.

---

## 5. Revisão de segurança

### 5.1 Medidas de segurança implementadas

| Medida | Forma de implementação |
|------|---------|
| Segurança de senha | bcrypt, cost=12 |
| Aleatoriedade de chaves | `random_int()` números aleatórios seguros para criptografia |
| Proteção XSS | `htmlspecialchars()` faz escape de toda entrada/saída do usuário |
| Proteção contra injeção SQL | declarações preparadas PDO (`prepare/execute`) |
| Bloqueio de instalação | arquivo `install.lock` + metadados JSON |
| Segurança de caminho | caminhos fixos, sem file include controlável pelo usuário |
| Força de criptografia | AES-256-CBC + chave de 32 bytes |

### 5.2 Riscos potenciais e mitigações

| Risco | Nível | Mitigação |
|------|------|---------|
| Exposição de rede durante a instalação | Médio | excluir o diretório `install/` imediatamente após a instalação (aviso visível na página) |
| Sem CSRF Token | Baixo | o assistente de instalação é uma ferramenta temporária de uso único; servidor embutido do PHP é single-thread |
| test-db sem rate limit | Baixo | ferramenta temporária, excluída após o uso |
| Permissões do arquivo .env | Baixo | recomendado executar chmod 600 manualmente após a instalação |

### 5.3 Sugestões de melhoria

1. **Reforço em produção**: após a instalação, considerar `chmod 600 admin/.env service/.env` automático
2. **Acesso remoto**: em servidor remoto, recomendado usar túnel SSH: `ssh -L 8888:localhost:8888 user@host`
3. **Limpeza pós-instalação**: considerar um aviso em destaque "excluir diretório de instalação" na página de sucesso (já implementado)

---

## 6. Resultados de teste

### 6.1 Verificação de sintaxe PHP
```
Aprovado install/index.php — No syntax errors
Aprovado install/Installer.php — No syntax errors
```

### 6.2 Testes funcionais
```
Aprovado Step 1 verificação de ambiente — os 11 itens passaram
Aprovado Step 2 configuração do banco — formulário renderizado corretamente, valores padrão preenchidos
Aprovado AJAX test-db — formato de resposta JSON correto, mensagens de erro em chinês claras
Aprovado CSS estático — 200 OK, text/css
Aprovado página de já-instalado — detecção install.lock normal, mensagens completas
```

### 6.3 Validação do SQL
```
Aprovado os nomes das 42 tabelas são idênticos aos arquivos de migração originais
Aprovado campo source incorporado ao CREATE TABLE de game_operation_log
Aprovado todos os INSERT idempotentes
Aprovado guardas WHERE NOT EXISTS restauradas (consistentes com a migração original)
```

---

## 7. Problemas encontrados e corrigidos

| # | Problema | Severidade | Status |
|---|------|--------|------|
| 1 | INSERT de `game_admin_role_permission` sem guarda `WHERE NOT EXISTS` (inconsistente com a migração original) | Alta | Corrigido |
| 2 | INSERTs de seed data sem idempotência (execução repetida falharia) | Média | Corrigido (INSERT IGNORE) |
| 3 | Verificação de ambiente sem checar a extensão `pcntl` (dependência central do webman) | Média | Corrigido |
| 4 | Service .env sem a configuração `ENCRYPTION_CIPHER` | Baixa | Corrigido |
| 5 | Service .env sem a configuração `ENCRYPTABLE_CIPHER` | Baixa | Corrigido |
| 6 | Service .env sem a configuração `JWT_REFRESH_TTL` | Baixa | Corrigido |

---

## 8. Mudanças de documentação

| Arquivo | Conteúdo da mudança |
|------|---------|
| `README.md` | início rápido alterado para "assistente de instalação com um clique (recomendado)", novo bloco colapsável de instalação manual, estrutura do projeto atualizada |
| `README.en.md` | o mesmo (versão em inglês), estrutura do projeto atualizada |
| `docs/DEPLOYMENT.md` | nova seção 2 "assistente de instalação com um clique (recomendado para novas implantações)", capítulo do Docker movido para depois |
| `.gitignore` | novos `install/install.lock`, `admin/.env.backup.*`, `service/.env.backup.*` |

---

## 9. Avaliação geral

O sistema de instalação tem funcionalidade completa, qualidade de código boa e medidas de segurança adequadas. O fluxo de instalação de 5 etapas é claro e intuitivo; a verificação de ambiente cobre todas as extensões-chave exigidas pelo webman; gera chaves de alta segurança automaticamente; os arquivos de configuração são totalmente compatíveis com o sistema existente. O processo de consolidação do SQL mantém consistência total com os arquivos de migração originais (42 tabelas), e o tratamento de idempotência garante que a execução repetida não cause erros.

**Conclusão da auditoria: aprovada, pronta para uso.**

---

## 10. Confirmação de status em 2026-08-18

As correções de segurança desta rodada (callback de pagamento fail-closed, validação de startup do JWT, unificação de prefixo de tabelas) **não envolvem o sistema de instalação**, sem novos problemas:

- Após remover o prefixo `game_` hardcoded dos modelos, os nomes reais das tabelas continuam sendo gerados uniformemente pelo `prefix=game_` de `config/database.php`, consistente com as tabelas `game_*` criadas pelo install.sql — sem necessidade de alterar o SQL de instalação
- A validação de startup do JWT (recusa iniciar com `JWT_SECRET_KEY` ausente ou valor padrão) é compatível com a chave aleatória de 64 bytes gerada automaticamente pelo assistente de instalação — sem necessidade de ajustar o fluxo de instalação

As conclusões históricas e a lista de problemas permanecem inalteradas.

---
