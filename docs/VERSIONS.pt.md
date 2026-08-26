# Comparação de versões
<!-- lang-nav -->

Languages: **中文** · [English](VERSIONS.en.md) · [한국어](VERSIONS.ko.md) · [Русский](VERSIONS.ru.md) · [Deutsch](VERSIONS.de.md) · [Français](VERSIONS.fr.md) · [Español](VERSIONS.es.md) · [Português](VERSIONS.pt.md) · [हिन्दी](VERSIONS.hi.md) · [العربية](VERSIONS.ar.md) · [বাংলা](VERSIONS.bn.md) · [Bahasa Indonesia](VERSIONS.id.md) · [日本語](VERSIONS.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Visão geral

| | Versão base (Lite) | Versão padrão (Standard) | Versão completa (Full) |
|------|------|------|------|
| Tabelas de dados (install.sql) | 19 | 29 | **43** (não os 52 que a documentação já escreveu) |
| Endpoints de API | 38 | 54 | ~149 (admin+service, incluindo Webhook/Provider) |
| Controllers do backend | 14 | 22 | admin 32 + service 30 |
| Modelos de dados | Não compartilhados | Não compartilhados | **admin 46 / service 44, uma cópia cada, sem camada compartilhada** |
| Service compartilhado | Sem camada compartilhada | Sem camada compartilhada | `packages/platform-common` pacote compartilhado único |
| Páginas do frontend Admin | 11 | 13 | 15 |
| Páginas do frontend Platform | 8 | 10 | 10 |
| HarmonyOS (admin) | - | login + dashboard | **8 páginas** `admin/apps/harmonyos/` |
| HarmonyOS (C-side) | - | - | **5 páginas** `apps/harmonyos/` (login/lobby de jogos/detalhes/carteira/meu) |
| Serviços Docker | - | - | **7** (nginx/admin/service/leaderboard-ws/mysql/redis/elasticsearch) |
| Casos de teste | 60 | 60 | admin ~132; service 3 |

---

## Autenticação de usuários

| Funcionalidade | Versão base | Versão padrão | Versão completa |
|------|--------|--------|--------|
| Registro/login com nome de usuário e senha | ✓ | ✓ | ✓ |
| Token JWT (2h+14d) | ✓ | ✓ | ✓ |
| Captcha de clique | stub | stub | ✓ poster-php |
| Bloqueio de conta (5 vezes/15 minutos) | ✓ | ✓ | ✓ |
| Limite de sessões (3 concorrentes) | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ 7 plataformas (incluindo X/MS/LinkedIn/GitHub) |
| 2FA TOTP de dois fatores | - | - | ✓ |
| Exportação/cancelamento de dados GDPR | - | - | ✓ |

---

## Carteira e fundos

| Funcionalidade | Versão base | Versão padrão | Versão completa |
|------|--------|--------|--------|
| Carteira de moeda da plataforma | ✓ | ✓ | ✓ |
| Lock otimista da carteira | ✓ | ✓ | ✓ |
| Registro de transações | ✓ | ✓ | ✓ |
| Carteira de moeda de jogo | ✓ | ✓ | ✓ |
| Criação de ordem de recarga | ✓ | ✓ | ✓ |
| Crédito automático no callback de recarga | - | ✓ manual | ✓ verificação de assinatura Stripe/PayPal |
| Cotação/compra/venda de câmbio | ✓ | ✓ | ✓ |
| Receita de spread de câmbio | ✓ | ✓ | ✓ |
| Solicitação de saque | ✓ | ✓ | ✓ |
| Interruptor global de saque | ✓ | ✓ | ✓ |
| Revisão de saque | ✓ manual | ✓ manual | ✓ em lote + manual |
| Limites escalonados por KYC | - | ✓ 3 níveis | ✓ |
| Tarifa de saque | - | - | ✓ |
| Recibo PDF | - | - | ✓ |

---

## Gerenciamento de jogos

| Funcionalidade | Versão base | Versão padrão | Versão completa |
|------|--------|--------|--------|
| CRUD de jogos | ✓ | ✓ | ✓ |
| Gestão de moedas de jogo | ✓ | ✓ | ✓ |
| Lista/detalhes de jogos C-side | ✓ | ✓ | ✓ |
| Início de jogo | ✓ | ✓ | ✓ |
| Categorias de jogos (10 categorias) | - | - | ✓ |
| Filtro por categoria | - | - | ✓ |
| Gestão de servidores de jogo | - | ✓ | ✓ |
| Rastreamento de registros de partidas | - | ✓ | ✓ |
| Busca full-text ES | - | - | ✓ |
| Sugestões de busca | - | - | ✓ |
| SDK Provider de jogos de terceiros | - | - | ✓ HMAC-SHA256 |

---

## Ferramentas operacionais

| Funcionalidade | Versão base | Versão padrão | Versão completa |
|------|--------|--------|--------|
| Gestão de anúncios | ✓ | ✓ | ✓ |
| Dashboard | ✓ painel administrativo | ✓ painel administrativo | ✓ admin + platform |
| Exportação Excel | ✓ | ✓ | ✓ |
| Exportação PDF | ✓ | ✓ | ✓ |
| Gráficos reais do dashboard | - | - | ✓ fl_chart |
| Sistema de cupons | - | - | ✓ |
| Rankings (diário/semanal/mensal/total) | - | - | ✓ cache Redis |
| Rankings em tempo real via WebSocket | - | - | ✓ porta 8789 |
| Sistema de notificações (no site + email) | - | - | ✓ |
| Recompensa de indicação | - | - | ✓ |
| Snapshot de estatísticas diárias | - | ✓ | ✓ |
| Rastreamento de receita da plataforma | - | - | ✓ |

---

## Segurança e conformidade

| Funcionalidade | Versão base | Versão padrão | Versão completa |
|------|--------|--------|--------|
| 18 camadas de defesa em profundidade | ✓ | ✓ | ✓ |
| Controle de permissões RBAC | ✓ | ✓ | ✓ |
| Log de auditoria de operações | ✓ | ✓ | ✓ |
| Detecção de origem em 8 plataformas | ✓ | ✓ | ✓ |
| Rate limit de janela deslizante Redis | ✓ | ✓ | ✓ |
| Verificação de identidade KYC | - | ✓ | ✓ |
| Motor de risco (4 regras) | - | ✓ | ✓ |
| Verificação de assinatura do callback de pagamento | - | - | ✓ |

---

## Internacionalização

| Funcionalidade | Versão base | Versão padrão | Versão completa |
|------|--------|--------|--------|
| Suporte a vários idiomas | Chinês/inglês | 4 idiomas | 4 idiomas |
| Tabela de traduções + cache | ✓ | ✓ | ✓ |
| Detecção automática de idioma | ✓ | ✓ | ✓ |
| Configuração diferenciada por país | - | - | ✓ 8 países |

---

## Implantação e operação

| Funcionalidade | Versão base | Versão padrão | Versão completa |
|------|--------|--------|--------|
| Implantação webman independente | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ 7 serviços |
| Proxy reverso Nginx | - | - | ✓ |
| Tarefas agendadas Crontab | - | ✓ | ✓ |
| Monitoramento Prometheus | ✓ | ✓ | ✓ `/metrics` gauge de negócio + contadores de eventos |
| Health check | ✓ | ✓ | ✓ |
| Documentação online hg/apidoc | - | - | ✓ 41 controllers |

---

## Clientes

| Funcionalidade | Versão base | Versão padrão | Versão completa |
|------|--------|--------|--------|
| Painel administrativo Flutter Web PC | ✓ 5 páginas | ✓ 11 páginas | ✓ 15 páginas |
| Plataforma de usuário Flutter Web PC | ✓ 5 páginas | ✓ 8 páginas | ✓ 10 páginas |
| HarmonyOS admin | - | ✓ login + dashboard | ✓ 8 páginas `admin/apps/harmonyos/` |
| HarmonyOS C-side | - | - | ✓ 5 páginas `apps/harmonyos/` |

---

## Tabelas do banco de dados

### Versão base (19 tabelas)
```
Painel administrativo (7):  erik_admin_user, erik_admin_role, erik_admin_permission,
               erik_admin_user_role, erik_admin_role_permission,
               erik_operation_log, erik_system_config

Núcleo da plataforma (12): erik_user, erik_user_wallet, erik_user_game_wallet,
               erik_game, erik_game_currency, erik_deposit_order,
               erik_withdraw_order, erik_exchange_record, erik_transaction,
               erik_payment_method, erik_announcement, erik_platform_config
```

### Novas na versão padrão (10 tabelas)
```
erik_user_identity, erik_user_oauth, erik_user_payment_account,
erik_user_session, erik_game_server, erik_game_play_log,
erik_withdraw_limit, erik_risk_rule, erik_risk_log, erik_stat_daily
```

### Novas na versão completa (13 tabelas)
```
erik_game_category, erik_game_category_rel, erik_leaderboard,
erik_coupon, erik_user_coupon, erik_language, erik_translation,
erik_country_config, erik_platform_revenue,
erik_notification, erik_referral, erik_referral_reward, erik_user_2fa
```

---

## Endpoints de API

| Módulo | Versão base | Versão padrão | Versão completa |
|------|--------|--------|--------|
| Autenticação | 3 | 3 | 7 (+OAuth×2 +2FA×5) |
| Carteira | 2 | 2 | 3 (+callback de recarga) |
| Câmbio | 4 | 4 | 4 |
| Saque | 2 | 2 | 8 (+em lote+limites+revisão) |
| Jogos | 3 | 4 | 7 (+servidores+registros+busca) |
| Usuário | 2 | 2 | 7 (+KYC+GDPR+privacidade) |
| Painel administrativo | 18 | 25 | 79 |
| Ferramentas operacionais | - | - | 30 (+rankings+cupons+notificações+indicação) |
| Internacionalização | 2 | 2 | 4 (+configuração por país) |
| **Total** | **38** | **54** | **129** |

---

## Expansão do ecossistema (v2.0) — novo

| Funcionalidade | Observação |
|------|------|
| Camada de abstração GameProvider | SelfProvider (transação DB) + ThirdPartyProvider (HTTP+assinatura) |
| Gateway da Provider API | callbacks balance/bet/settle/refund + middleware ProviderAuth |
| Sistema de tickets | criação/resposta C-side + tratamento/atribuição/fechamento no admin |
| Verificação de email | código de 6 dígitos, expiração Redis 10 min, limite de reenvio 60s |
| Push notifications | PushService (FCM/APNs/华为推送) |
| Sistema VIP | 5 níveis, acúmulo de experiência, upgrade automático, desconto de câmbio, redução de saque, bônus de taxa de câmbio |
| Sistema de conquistas | 12 conquistas integradas, detecção orientada a eventos, rastreamento de progresso |
| Sistema de amigos | solicitação/aceite/recusa/remoção/busca |
| Mensagens privadas/chat | REST + mensagens em tempo real WebSocket (porta 8790) |
| Barramento de eventos | Redis Pub/Sub; emit INCR `metrics:event_*`; processo consumidor `EventConsumer` implementado |
| Feature flags | FeatureFlag baseado em DB; `inRollout`/`abTest` leem `feature.{name}_percent` |
| Webhook | - | - | ✓ 7 tipos de evento + entrega via Pub/Sub |
| Chat | - | - | ✓ REST+WebSocket :8791 |
| Torneios | - | - | ✓ FeatureFlag+tournament |
| Condições de cupons | - | - | ✓ min_deposit/first_user/game_id |
| Comissão em vários níveis | - | - | ✓ repartição de segundo nível |
| Documentação do SDK | - | - | ✓ PHP/Go/Python |
| Análise avançada | retenção/D1-D30, funil de conversão, ARPU/ARPPU |

### Novas tabelas de dados (10)
```
erik_ticket, erik_ticket_reply, erik_device_token,
erik_vip_level, erik_user_vip, erik_exp_log,
erik_achievement, erik_user_achievement,
erik_friend, erik_message
```

### Novos endpoints da Provider API (4)
```
POST /api/provider/balance  — consultar saldo
POST /api/provider/bet      — notificar aposta
POST /api/provider/settle   — notificar liquidação
POST /api/provider/refund   — notificar reembolso
```

### Novos endpoints C-side (8)
```
POST /api/verify/send-email    — enviar código de verificação de email
POST /api/verify/confirm-email — confirmar email
GET  /api/ticket/list             — lista de tickets
POST /api/ticket/create           — criar ticket
GET  /api/ticket/{id}             — detalhes do ticket
POST /api/ticket/{id}/reply       — responder ticket
GET  /api/user/vip-status         — status VIP
GET  /api/user/achievements       — lista de conquistas
```

### Novos endpoints do painel administrativo (6)
```
GET  /admin/ticket/list          — lista de tickets
GET  /admin/ticket/{id}          — detalhes do ticket
POST /admin/ticket/{id}/reply    — responder ticket
POST /admin/ticket/{id}/close    — fechar ticket
POST /admin/ticket/{id}/assign   — designar responsável
GET  /admin/analytics/retention  — análise de retenção
GET  /admin/analytics/funnel     — funil de conversão
GET  /admin/analytics/arpu       — tendência ARPU
GET  /admin/analytics/economy    — indicadores econômicos
```
