# 项目全面规划 (Project Plan)
<!-- lang-nav -->

Languages: **中文** · [English](PROJECT-PLAN.en.md) · [한국어](PROJECT-PLAN.ko.md) · [Русский](PROJECT-PLAN.ru.md) · [Deutsch](PROJECT-PLAN.de.md) · [Français](PROJECT-PLAN.fr.md) · [Español](PROJECT-PLAN.es.md) · [Português](PROJECT-PLAN.pt.md) · [हिन्दी](PROJECT-PLAN.hi.md) · [العربية](PROJECT-PLAN.ar.md) · [বাংলা](PROJECT-PLAN.bn.md) · [Bahasa Indonesia](PROJECT-PLAN.id.md) · [日本語](PROJECT-PLAN.ja.md)


> Data de geração: 2026-08-16 · Baseado em auditoria somente-leitura da equipe de 6 pessoas (researcher/architect/backend-dev/frontend-dev/tester/reviewer) + verificação prática das conclusões-chave
> Cobertura: resumo do estado atual / problemas e riscos / roteiro P0-P1-P2 / correção de documentação / portões de qualidade

---

## 1. Estado atual do projeto

**Plataforma global de agregação de jogos** — PHP 8.3 + webman v2, monorepo de dois aplicativos:
`admin/`(8787 painel administrativo) + `service/`(8788 C-side) + `apps/`(Flutter + HarmonyOS) + `install/`(assistente de instalação, 43 tabelas).

| Dimensão | Escala verificada |
|------|---------|
| Controllers | admin 32 + service 30 = 62 |
| Endpoints de API | ~149 (admin 103 / service 88, incluindo callbacks de Webhook/Provider) |
| Modelos de dados | admin 46 / service 44, admin/service **duplicados por cópia** (sem camada compartilhada) |
| Testes | 132 casos / 8 arquivos (projeto admin), projeto service **zero testes** |
| Versão | v1.1 (2026-08-07): plugin Redis, serviço de análise, degradação do Redis, correções de testes |

Capacidades já implementadas: JWT+RBAC, lock otimista da carteira, recarga (verificação de assinatura Stripe/PayPal), spread de câmbio, revisão de saque + pagamento via PayPal, CRUD de jogos/gateway Provider (HMAC), cupons/VIP/conquistas/tickets/recomendação com comissão/2FA/social (amigos/chat WS)/torneios/Webhook/push (FCM/APNs/华为)/i18n bilíngue.

---

## 2. Problemas e riscos (verificados na prática)

### CRITICAL — Segurança de fundos

| # | Problema | Local |
|---|------|------|
| C1 | `provider` do callback de pagamento enviado pelo cliente; quando não é stripe/paypal, a **verificação de assinatura é completamente ignorada**, callbacks forjados entram direto no saldo | service/.../PaymentController.php:36-42 |
| C2 | Verificação fail-open: `STRIPE_WEBHOOK_SECRET` não configurado → `return true`; qualquer exceção do PayPal → `return true`. Cadeia de ataque: criar ordem de recarga → forjar callback → recarga infinita | PaymentController.php:167, 225 |
| C3 | `JWT_SECRET_KEY` com fallback para chave hardcoded pública `open-admin-jwt-secret-change-in-production`; sem env configurada em produção, Token de administrador pode ser forjado | admin+service config/plugin/erikwang2013/jwt/jwt.php:12 |

### HIGH — Correção/consistência

| # | Problema | Local |
|---|------|------|
| H1 | AnalyticsController: 12 métodos totalmente implementados mas **zero rotas**, todos 404 código morto, embora VERSIONS.md declare como entregues | admin/config/route.php (0 ocorrências de analytics) |
| H2 | Barramento de eventos quebrado: emit tem 4 chamadas (game.played/withdraw.completed/exchange.completed/referral.applied), nenhum processo registra `subscribe()`, eventos são perdidos ao publicar; mecanismos VIP/conquistas/notificações todos suspensos | admin+service app/event/EventBus.php |
| H3 | common/ e model/ duplicados e já divergentes (DepositLogService com duas cópias diferentes, User.php inconsistente), correção pontual vira trabalho em dobro. **common/service já foi extraído** para `packages/platform-common` (erik/platform-common, o antigo common-php foi incorporado); model e wrappers de app/common ainda duplicados | admin/common vs service/common → packages/platform-common |
| H4 | ~~C-side do HarmonyOS `apps/harmonyos/` é diretório vazio, 0 páginas vs 5 páginas alegadas pelo VERSIONS.md~~ — já implementado (2026-08-18: 5 páginas em `apps/harmonyos/`) | apps/harmonyos/ |
| H5 | Callback do Stripe não valida tolerância do timestamp `t=` (possível replay), e o valor creditado não é conferido com o valor real pago no gateway | PaymentController.php:191-194 |
| H6 | Apple id_token apenas decodifica o payload em base64, sem verificação de assinatura, sem validar aud/iss/exp, risco de confusão de identidade entre aplicativos | OAuthController.php:376-380 |

### MEDIUM — Confiabilidade/defeitos de implementação

| # | Problema |
|---|------|
| M1 | 2FA com defeito duplo: `/api/2fa/verify` público sem bloqueio por usuário (oráculo de força bruta); TOTP usa a string Base32 diretamente como chave HMAC (sem decodificar), incompatível com o Authenticator → **2FA efetivamente inutilizável** |
| M2 | Revisão/pagamento de saque é check-then-act sem atualização atômica de estado, concorrência pode pagar duas vezes; sem dupla revisão |
| M3 | URL de callback do Webhook validada apenas com filter_var, pode apontar para IP interno (SSRF), dispatch faz POST para qualquer URL |
| M4 | Limite diário/mensal de saque "consulta antes de inserir" não atômico, concorrência pode estourar o limite |
| M5 | Falha do Redis fail-open sem abstração unificada: blacklist do JWT não invalida no logout, rate limit falha silenciosamente; lacunas de degradação: PayoutService::getAccessToken, ChatWebSocket brpop, acesso ao state do OAuth |
| M6 | ClickHouse com zero uso: o cálculo de probabilidade é na verdade COUNT(DISTINCT) em tempo real no MySQL + JOIN de subconsultas, risco O(n²) em tabelas grandes; dependência no composer sem capacidade |
| M7 | Fila inacabada: admin/app/queue tem ComputeDailyStats + 3 tarefas ES, mas webman/queue não instalado, sem registro em process.php, todos sem chamadores |
| M8 | Código morto: serviços Vip/Achievement/Notification/FeatureFlag sem chamadores; DepositLogService::log() implementação vazia; modelo Test residual; algoritmo de retenção com cálculo grosseiro de cohort único |

### LOW
- Saque sem 2FA/KYC obrigatório pode pagar para qualquer email PayPal; observação de revisão entra no texto da notificação (superfície XSS)
- Documentação divergente da realidade: install.sql 43 tabelas vs docs que já escreveram 52; docker-compose 7 serviços vs FEATURES.md que já escreveu 8; "Modelos compartilhados 34" inverídico (admin 46 / service 44, uma cópia cada, sem camada compartilhada). CHANGELOG já complementou, ver `docs/CHANGELOG.md`.

### Itens aprovados (revisão de segurança confirmou sem problemas)
Lock otimista da carteira + atualização condicional por versão corretos; idempotência do callback com `where status=pending` correta; tudo ORM sem SQL concatenado; .env fora do git; todas as rotas do admin com AdminAuth+RBAC negando por padrão; validação do state do OAuth + consumo único corretos.

> **Status das correções em 2026-08-18**: C1/C2/C3/H1/H5/H6 corrigidos; H2 barramento de eventos: `process.php` registrou `event-consumer` e a classe consumidora `EventConsumer` foi implementada, emit tem consumidor; M1 Base32 + bloqueio por usuário corrigidos; M2 atomicidade do estado do saque + dupla revisão opcional feitas; M3 SSRF do Webhook bloqueado; M4 lock de usuário no Redis na solicitação de saque feito; M5 parcialmente concluído (RateLimit fail-closed); P2-19 métricas de negócio + FeatureFlag de rollout implementados. A lista de problemas é mantida como conclusão histórica da auditoria.

---

## 3. Roteiro

### P0 — Segurança de fundos + correção (primeiro, bloqueia o lançamento)

1. **Callback de pagamento fail-closed**: whitelist de providers (apenas stripe/paypal) + falta de chave deve recusar com 500 + exceção do PayPal deve recusar (C1/C2) — ✅ Concluído (2026-08-18: whitelist de providers + validação de uso indevido entre canais + validação opcional do IP de origem + creditação transacional no callback)
2. **Validação do JWT na inicialização**: recusar iniciar sem `JWT_SECRET_KEY` no env (C3) — ✅ Concluído (2026-08-18: recusa iniciar quando `JWT_SECRET_KEY` ausente ou com o valor padrão `open-admin-jwt-secret-change-in-production`, consistente entre admin/service)
3. **Montar rotas do serviço de análise**: registrar 12 rotas de analytics + pontos de permissão, cumprir a promessa do VERSIONS.md (H1) — ✅ Concluído (2026-08-18: admin/config/route.php registra 12 rotas `/admin/analytics/*`)
4. **Integrar o barramento de eventos**: registrar processo de assinatura residente para consumir, ou mudar para chamada síncrona direta; eventos persistidos + retry em falha (H2) — ✅ Concluído (2026-08-18: emit/consume já fazem INCR no Redis; `service/config/process.php` registra `event-consumer`, `service/app/process/EventConsumer.php` consome eventos)
5. **Verificação de assinatura do Apple id_token**: validação JWKS + aud/iss/exp (H6) — ✅ Concluído (2026-08-18: RS256 JWKS + refresh de kid + aud/iss/exp)
6. **Replay do Stripe e conferência de valores**: tolerância de timestamp + comparação com o valor do gateway (H5) — ✅ Concluído (2026-08-18: timestamp `t=` ±300s anti-replay + conferência de valores com precisão bccomp + sem secret/webhook_id configurado ou exceção na verificação, tudo recusado)

### P1 — Confiabilidade + consistência

7. **Deduplicação da camada compartilhada**: extrair common/model para composer path repo (ou symlink), eliminar a divergência duplicada (H3) — 🔶 Parcialmente concluído (2026-08-18: `common/service` extraído para um único `packages/platform-common` / `erik/platform-common` path repo (o antigo `common-php` foi incorporado), referenciado por admin+service; model e os wrappers `app/common` ligados ao host ainda duplicados, ver `packages/platform-common/DUAL_MODELS.md`)
8. **Encapsulamento unificado de degradação do Redis**: explicitar a política de falha + alertas sem silêncio; complementar PayoutService/OAuth/ChatWebSocket com fallback (M5) — 🔶 Parcialmente concluído (RateLimit fail-closed implementado: quando o Redis falha, o rate limit recusa em vez de deixar passar silenciosamente; o restante não feito)
9. **Conexão do webman/queue**: abrigar entrega de eventos e webhooks (retry de consumo, dead letter), ativar ou remover tarefas ComputeDailyStats/ES (M7) — ⬜ Não feito
10. **Correção do 2FA**: decodificação Base32 + verify com estado de login e bloqueio por usuário (M1) — ✅ Concluído (2026-08-18: HMAC após decodificação Base32 RFC 4648; `/api/2fa/verify` bloqueia após 5 falhas por 15 minutos, fail-closed quando o Redis falha)
11. **Atomicidade do saque**: atualização condicional na revisão/pagamento + dupla revisão; limite com Lua Redis/restrição única (M2/M4) — 🔶 Parcialmente concluído (2026-08-18: pending→approved/rejected, approved→processing com UPDATE condicional; dupla revisão opcional `withdraw.require_dual_review`; lock de usuário no Redis na solicitação. Sem limite Lua/restrição única)
12. **Bloqueio de SSRF do Webhook**: recusar rede interna/endereços reservados (M3) — ✅ Concluído (2026-08-18: `isSafeWebhookUrl()` apenas https público)
13. **ClickHouse, uma das duas opções**: integração real ou remoção da dependência + revisão da documentação (M6) — ⬜ Não feito
14. **Limpeza de código morto**: integrar ou remover Vip/Achievement/Notification/FeatureFlag; remover modelo Test; DepositLog auditado no banco (M8) — 🔶 Parcialmente concluído (2026-08-18: modelo Test removido, DepositLog auditado no banco; Vip/FeatureFlag/Notification já têm chamadores; AchievementService já é chamado pelo EventConsumer)
15. **Testes do service + portão de CI**: testes de integração de verificação de assinatura do callback/fluxo de saque/degradacão do Redis/cálculo de probabilidade/concorrência do lock otimista; falha do phpunit bloqueia; service no CI (atualmente `|| echo warning` permite falha) — 🔶 Parcialmente concluído (service já tem WebhookUrlSafety / EventBusMessageFormat; já incluído no job `phpunit-service` do CI com bloqueio em falha)

**Extra concluído nesta rodada (2026-08-18) (fora da numeração original)**:
- **Correção de prefixo de tabelas**: 52 modelos sem prefixo `erik_` hardcoded, eliminando o duplo prefixo `erik_erik_`; o prefixo do DB é fornecido uniformemente por config/database.php `prefix=erik_`, sem necessidade de alterar o install.sql
- **Reescrita do refresh token**: lógica de atualização de token do AuthController do service reescrita
- **Transplante do DepositLogService versão service**: service/common/service/DepositLogService.php completado (elimina uma das duas cópias divergentes admin/service)

### P2 — Observabilidade / expansão / experiência

16. **C-side do HarmonyOS** implementar 5 páginas do zero (login/lobby/detalhes/carteira/perfil) (H4) — ✅ Concluído (2026-08-18: `apps/harmonyos/entry/src/main/ets/pages/` 5 páginas no repositório)
17. **Complementação do frontend**: página de verificação 2FA, entradas de cupons/rankings/notificações, UI de busca ES; consolidar fontes de rotas de main.dart/app_pages.dart; callback real do OAuth; camada de transporte AES no frontend
18. **Migrar o cálculo de probabilidade para ClickHouse** ou tabela de estatísticas materializada no MySQL + cache; retenção recalculada por cohort real
19. **Métricas de negócio Prometheus** (taxa de entrega/consumo de eventos, profundidade da fila) + middleware de divisão AB em rollout (reutilizando FeatureFlag) — 🔶 Parcialmente concluído (2026-08-18: `GET /metrics` com saques aguardando revisão/recargas confirmadas hoje/contadores emit·consume; FeatureFlag `inRollout`/`abTest` com buckets crc32. Profundidade da fila não feita)
20. **Fechar o loop da cadeia de dados do WebSocket**: confirmação de persistência de rankings/chat
21. **Alinhamento da documentação**: correção de números de tabelas/serviços/descrição da camada compartilhada, API docs alinhadas com a implementação, complementar CHANGELOG — ✅ Concluído (2026-08-18: ver `docs/CHANGELOG.md`, FEATURES/VERSIONS/PROJECT-PLAN/relatórios de auditoria §10)

---

## 4. Portões de qualidade (colaboração da equipe)

- A cada mudança de código: testes completos do admin `vendor/bin/phpunit` devem passar (remover `|| echo warning`)
- Novos caminhos sensíveis (pagamento/saque/autenticação) devem vir acompanhados de testes
- Ao alterar common/model, sincronizar ambos os lados admin+service (até a camada compartilhada ser implantada)
- Foco da revisão recomendado: assinatura ProviderAuth, criptografia AES, SQL escrito à mão do ProbabilityService

## 5. Equipe

A equipe game-platform (6 membros: researcher/architect/backend-dev/frontend-dev/tester/reviewer) está pronta para executar o P0 diretamente.
