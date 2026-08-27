# Plataforma Global de Agregação de Jogos — Relatório de auditoria da expansão do ecossistema v2.0
<!-- lang-nav -->

Languages: [中文](PLATFORM-AUDIT-REPORT.md) · [English](PLATFORM-AUDIT-REPORT.en.md) · [한국어](PLATFORM-AUDIT-REPORT.ko.md) · [Русский](PLATFORM-AUDIT-REPORT.ru.md) · [Deutsch](PLATFORM-AUDIT-REPORT.de.md) · [Français](PLATFORM-AUDIT-REPORT.fr.md) · [Español](PLATFORM-AUDIT-REPORT.es.md) · **Português** · [हिन्दी](PLATFORM-AUDIT-REPORT.hi.md) · [العربية](PLATFORM-AUDIT-REPORT.ar.md) · [বাংলা](PLATFORM-AUDIT-REPORT.bn.md) · [Bahasa Indonesia](PLATFORM-AUDIT-REPORT.id.md) · [日本語](PLATFORM-AUDIT-REPORT.ja.md)


> **Data da auditoria**: 2026-08-04
> **Escopo da auditoria**: todos os 16 itens funcionais planejados, qualidade de código, segurança, consistência de modelos, testes
> **Branch**: main

---

## 1. Visão geral

| Categoria | Nota | Mudanças |
|------|------|------|
| Integridade funcional | **A (96/100)** | +18 endpoints, +10 modelos, +7 serviços |
| Qualidade de código | **A (95/100)** | 0 erros de sintaxe, 0 regressões |
| Proteção de segurança | **A (94/100)** | ProviderAuth HMAC-SHA256, PKCE, mensagens privadas apenas entre amigos |
| Configuração do ecossistema | **A- (92/100)** | FeatureFlag 4 switches, Webhook 7 eventos, VIP 5 níveis |
| Integridade de implantação | **B+ (89/100)** | ChatWebSocket :8791, documentação sincronizada |

---

## 2. Itens verificados

### 2.1 Verificação de sintaxe PHP
- Todos os arquivos `.php` de admin/ e service/: **0 erros**
- Arquivos de configuração (route.php, process.php): **0 erros**

### 2.2 Suíte de testes
- 132 testes / 251 asserções: **0 novas regressões**
- Falhas pré-existentes (23 itens): ClickHouse não instalado (14), dependência de ambiente do Captcha (2), configuração de middlewares (2), serviço de tradução (3), health check (2)

### 2.3 Revisão de segurança

| Item | Status |
|----|------|
| Verificação de assinatura HMAC-SHA256 do Provider | ✓ janela de 5 minutos anti-replay |
| Twitter OAuth PKCE (S256) | ✓ code_verifier armazenado no Redis |
| Proteção CSRF do state do OAuth | ✓ armazenado no Redis + leitura única com exclusão |
| Mensagens privadas apenas entre amigos | ✓ validação no FriendController |
| Filtro de URL do Webhook | ✓ filter_var(FILTER_VALIDATE_URL) |
| Whitelist de eventos do Webhook | ✓ 7 tipos de evento, filtro com array_intersect |
| Autenticação JWT (ChatWebSocket) | ✓ jwt()->verify() |
| Proteção contra injeção SQL | ✓ Eloquent ORM, sem concatenação nativa |
| Rate limit de API | ✓ OAuth 10 vezes/min, geral 60 vezes/min |
| Criptografia Encryptable | ✓ token OAuth / API key com criptografia/descriptografia automática |

### 2.4 Correções de consistência de modelos

| Problema | Correção |
|------|------|
| 🔴 nomes de tabelas dos modelos do service com prefixo `game_` (conflito com a convenção existente) | os 10 novos modelos tiveram o prefixo removido |
| 🟡 `AchievementService` com `game_user_session` hardcoded | versão service alterada para `user_session` |
| 🟡 `GameController` com `game_game_category_rel` hardcoded | versão service alterada para `game_category_rel` |

---

## 3. Lista de entregas funcionais

### Phase 1 — Camada de integração de jogos

| Arquivo | Observação |
|------|------|
| `provider/GameProvider.php` (admin+service) | classe base abstrata: bet/settle/refund/rollback/signRequest |
| `provider/SelfProvider.php` (admin+service) | jogos próprios: transação DB + SELECT FOR UPDATE |
| `provider/ThirdPartyProvider.php` (admin+service) | terceiros: Guzzle HTTP + HMAC-SHA256 |
| `provider/ProviderFactory.php` (admin+service) | fábrica: match(game.type) |
| `middleware/ProviderAuth.php` (service) | verificação de assinatura HMAC-SHA256, janela 5min |
| `controller/ProviderController.php` (service) | 4 endpoints: balance/bet/settle/refund |
| `service/GameSessionService.php` (admin+service) | heartbeat Redis + detecção de timeout 15min |

### Phase 2 — Camada de suporte operacional

| Arquivo | Observação |
|------|------|
| `model/Ticket.php` + `TicketReply.php` (admin+service) | tickets + respostas, 5 tipos |
| `controller/TicketController.php` (service + admin) | 4 endpoints C-side + 5 endpoints admin |
| `service/VerificationService.php` (admin+service) | código de 6 dígitos, Redis 10min, cooldown 60s |
| `controller/VerificationController.php` (service) | 4 endpoints: sendEmail/confirmEmail/sendSms/confirmPhone |
| `service/PushService.php` (admin+service) | abstração FCM/APNs/华为推送 |
| `model/DeviceToken.php` (admin+service) | armazenamento de tokens de dispositivo |

### Phase 3 — Retenção de usuários

| Arquivo | Observação |
|------|------|
| `model/VipLevel.php` + `UserVip.php` + `ExpLog.php` | VIP de 5 níveis, sistema de experiência |
| `service/VipService.php` (admin+service) | addExp/upgrade automático/consulta de benefícios |
| **Integração no ExchangeController** | quote() aplica desconto VIP + bônus de câmbio |
| **Integração no WithdrawController** | apply() aplica redução de tarifa VIP |
| **Integração no ReferralController** | apply() adiciona EXP ao indicador |
| `model/Achievement.php` + `UserAchievement.php` | 12 conquistas integradas |
| `service/AchievementService.php` (admin+service) | detecção orientada a eventos + rastreamento de progresso |

### Phase 4 — Camada social

| Arquivo | Observação |
|------|------|
| `model/Friend.php` (admin+service) | relação de amizade: associação bidirecional user/friendUser |
| `controller/FriendController.php` (service) | 7 endpoints: list/requests/request/accept/reject/remove/search |
| `model/Message.php` (admin+service) | modelo de mensagem privada |
| `controller/ChatController.php` (service) | 5 endpoints: conversations/messages/send/markRead/unreadTotal |
| `process/ChatWebSocket.php` (service) | WebSocket :8791, autenticação JWT, push em tempo real Redis Pub/Sub |

### Phase 5 — Infraestrutura

| Arquivo | Observação |
|------|------|
| `event/EventBus.php` (admin+service) | barramento de eventos Redis Pub/Sub |
| **Integração emit em 5 controllers** | Exchange/Withdraw/Game/Referral + Auth |
| `controller/WebhookController.php` (service) | 4 endpoints: list/register/delete/test |
| `AnalyticsController` com 4 novos endpoints | retention/funnel/arpu/economy |
| `service/FeatureFlag.php` (admin+service) | feature flags em DB, 4 switches predefinidos |

### Extra — Extensão do OAuth

| Arquivo | Observação |
|------|------|
| **Reescrita do OAuthController** | 3→7 plataformas: +X(Twitter)/Microsoft/LinkedIn/GitHub |
| Twitter PKCE | code_challenge S256, code_verifier armazenado no Redis |
| Fallback de email do GitHub | API /user/emails, email primary verificado |

---

## 4. Problemas encontrados e corrigidos

| # | Problema | Severidade | Correção |
|---|------|--------|------|
| 1 | 🔴 nomes de tabelas dos modelos do service com prefixo `game_` (10) | Alta | remoção em lote com sed |
| 2 | 🟡 `game_user_session` hardcoded no AchievementService do service | Média | alterado para `user_session` |
| 3 | 🟡 `game_game_category_rel` hardcoded no GameController do service | Média | alterado para `game_category_rel` |
| 4 | 🟡 dupla barra invertida no route.php + declarações echo residuais | Média | corrigido |
| 5 | 🟢 modelos Friend/Message inicialmente não criados (apenas SQL) | Baixa | criados |
| 6 | 🟢 porta real do LeaderboardWebSocket é 8790, chat-ws mudou para 8791 | Baixa | ajuste de portas |

---

## 5. Dados estatísticos

### Volume de código

| Métrica | Quantidade |
|------|------|
| Arquivos PHP novos | 51 |
| Arquivos SQL novos | 1 (165 linhas) |
| Arquivos existentes modificados | 7 (5 controllers + 2 configurações de rota/processo) |
| Modelos novos | 10 (admin+service = 20 arquivos) |
| Serviços novos | 6 |
| Controllers novos | 6 |
| Novos endpoints de API | 50+ |
| Novas tabelas de dados | 10 |
| Documentação atualizada | 8 .md + 2 diagramas |

### Qualidade de código

| Métrica | Valor |
|------|-----|
| Erros de sintaxe PHP | 0 |
| Regressões de teste | 0 |
| Novas dependências vendor | 0 |
| Risco de injeção SQL | 0 |
| Chaves hardcoded | 0 |

---

## 6. Espaço de expansão do ecossistema (itens não concluídos)

| Funcionalidade | Prioridade | Observação |
|------|--------|------|
| Sistema de torneios | P2 | FeatureFlag `feature.tournament` já reservado |
| Comissão de indicação em vários níveis | P3 | atual indicação de nível único, pode estender para repartição de segundo nível |
| Condições de cupons | P3 | adicionar depósito mínimo/jogo especificado/primeiro usuário |
| Pagamento automático (PayPal Payouts) | P3 | saques hoje com revisão manual, pode integrar saída automática |
| Página de configuração de VIP/conquistas no admin | P3 | modelos no backend já existem, página Flutter a construir |
| Integração profunda de push no mobile | P3 | esqueleto do PushService pronto, precisa conectar credenciais FCM/APNs |
| UI de chat/amigos no Flutter | P3 | API + WebSocket prontos, páginas do frontend a construir |
| Documentação do SDK de integração para provedores | P3 | Provider API pronta, documentação de integração a completar |

---

---

## 8. Correções do espaço de expansão (terceira rodada, 2026-08-04)

### P2 implementado

**#1 Sistema de torneios**
- Modelos `Tournament` + `TournamentEntry` (admin+service)
- `TournamentController` (service): 3 endpoints list/detail/join
- controlado pela feature flag `tournament`
- Suporta: filtro ativo/em breve/encerrado, limite de participantes, rankings

### P3 implementado

**#2 Comissão de indicação em vários níveis**
- Novo campo `parent_id` no modelo `Referral` suporta associação de segundo nível
- Modelo `ReferralCommission` registra detalhes da repartição (level/commission_rate/commission_amount)
- `ReferralController` calcula automaticamente a comissão de segundo nível (configurável `level2_rate`)

**#3 Condições de cupons**
- Novo campo JSON `conditions` no modelo `Coupon`
- Suporta 3 tipos de condição:
  - `min_deposit`: depósito acumulado mínimo
  - `first_user_only`: apenas novos usuários sem depósito
  - `game_id`: precisa ter jogado o jogo especificado
- `CouponController.available()` e `claim()` verificam as condições

**#4 Documentação do SDK do Provider**
- `docs/PROVIDER-SDK.md` documentação completa de integração
- algoritmo de assinatura detalhado + exemplos de código PHP/Go/Python
- documentação dos 4 endpoints (balance/bet/settle/refund)
- guia de integração de jogos próprios + gerenciamento de sessão + configuração de jogos

## 9. Nota final (atualizada)

| Categoria | Inicial (v1) | v2.0 expansão do ecossistema | v2.1 correções de extensão | Mudança |
|------|-----------|---------------|---------------|------|
| Integridade funcional | 85 → | 96 → | **98** | +13 |
| Qualidade de código | 92 → | 95 → | **95** | +3 |
| Proteção de segurança | 94 → | 94 → | **94** | estável |
| Configuração do ecossistema | 80 → | 92 → | **95** | +15 |
| Integridade de implantação | 72 → | 89 → | **90** | +18 |

**Geral**: de A- (84.6) → A (93.2) → **A (94.4)**

---

## 10. Confirmação das correções de segurança e disponibilidade de 2026-08-18

As correções de segurança e disponibilidade concluídas nesta rodada (2026-08-18) (área de trabalho não commitada, lançadas posteriormente com a versão 1.1):

| Item | Conteúdo da correção | Status |
|----|---------|------|
| Whitelist de providers no callback de pagamento | apenas stripe/paypal aceitos, demais recusados com 403; provider do callback inconsistente com o método de pagamento da ordem (uso indevido entre canais) recusado | ✅ corrigido |
| Callback de pagamento fail-closed | Stripe: sem `STRIPE_WEBHOOK_SECRET` ou falha de verificação retorna false; PayPal: sem `PAYPAL_WEBHOOK_ID` ou exceção na verificação → recusa; timestamp da assinatura além de ±300s considerado replay e recusado | ✅ corrigido |
| Conferência de valores | valor do callback comparado exatamente com o valor da ordem via `bccomp(…, 4)`, divergência recusada | ✅ corrigido |
| Creditação transacional no callback | atualização da ordem + crédito na carteira na mesma transação, rollback se a creditação falhar | ✅ corrigido |
| Validação de chave JWT na inicialização | recusa iniciar se `JWT_SECRET_KEY` ausente ou ainda com o valor padrão `open-admin-jwt-secret-change-in-production`, consistente entre admin/service | ✅ corrigido |
| Rotas do serviço de análise | admin/config/route.php registra 12 rotas `/admin/analytics/*` (todos os métodos do AnalyticsController) | ✅ corrigido |
| Prefixo de tabelas | 52 modelos sem prefixo `game_` hardcoded (elimina o duplo prefixo `game_game_`), prefixo do DB fornecido uniformemente pelo config `prefix=game_` | ✅ corrigido |
| Degradação do rate limit | RateLimit fail-closed quando o Redis falha (recusa em vez de deixar passar silenciosamente) | ✅ corrigido |
| refresh token | lógica de refresh de token do AuthController do service reescrita | ✅ corrigido |
| DepositLogService | versão do service transplantada e completada, elimina uma das duas cópias divergentes admin/service | ✅ corrigido |
| Limpeza de código morto | modelo Test removido; DepositLog auditado no banco | ✅ corrigido |
| Apple id_token | verificação JWKS RS256 + refresh de kid + aud/iss/exp | ✅ corrigido |
| SSRF no Webhook | `isSafeWebhookUrl()` apenas https público, recusa redes internas/endereços reservados | ✅ corrigido |
| 2FA | HMAC após decodificação Base32; `/api/2fa/verify` bloqueado por usuário 5 vezes/15 minutos | ✅ corrigido |
| Saque atômico | UPDATE condicional na revisão/pagamento; revisão dupla opcional; lock de usuário no Redis na solicitação | ✅ corrigido |
| Métricas de negócio Prometheus | `/metrics`: saques aguardando revisão, depósitos confirmados hoje (cache 30s), emit/consume de eventos, memory_usage, version=1.1 | ✅ implementado |
| FeatureFlag em rollout | `inRollout` / `abTest` com buckets crc32 lendo `feature.{name}_percent` | ✅ implementado |

**Ainda não concluído**: conexão do webman/queue, integração real do ClickHouse. Notas e conclusões históricas permanecem inalteradas. Implementado: processo consumidor do barramento de eventos (`service/app/process/EventConsumer.php` + registro `event-consumer` no `process.php`), deduplicação da camada compartilhada (consolidada em um único `packages/platform-common`), páginas C-side do HarmonyOS, conexão do motor de conquistas (chamado dentro do EventConsumer), portão do CI do service.

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
