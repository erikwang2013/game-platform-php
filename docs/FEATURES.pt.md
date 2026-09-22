# Documento de funcionalidades
<!-- lang-nav -->

Languages: [中文](FEATURES.md) · [English](FEATURES.en.md) · [한국어](FEATURES.ko.md) · [Русский](FEATURES.ru.md) · [Deutsch](FEATURES.de.md) · [Français](FEATURES.fr.md) · [Español](FEATURES.es.md) · **Português** · [हिन्दी](FEATURES.hi.md) · [العربية](FEATURES.ar.md) · [বাংলা](FEATURES.bn.md) · [Bahasa Indonesia](FEATURES.id.md) · [日本語](FEATURES.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Visão geral das funcionalidades

### Versão base (MVP) — concluída

| Domínio | Funcionalidade | Status |
|----|------|------|
| Usuários | registro/login/JWT/captcha | Concluído |
| Carteira | saldo de moeda da plataforma/consulta de transações | Concluído |
| Depósito | criação de ordem de depósito (Stripe 125+ pagamentos locais, incl. Alipay/WeChat Pay APM / NOWPayments USDT TRC20·ERC20 / Coinbase USDC·BTC·ETH / callback PayPal) | Concluído |
| Troca | moeda da plataforma⇄moeda de jogo (câmbio fixo + margem) | Concluído |
| Saque | solicitação/consulta/interruptor global/revisão automática/revisão manual | Concluído |
| Jogos | CRUD no backend/gestão de moedas/lista C-side/detalhes/início | Concluído |
| Gestão | gestão de jogos/revisão de saques/gestão de usuários/gestão de pagamentos/gestão de anúncios | Concluído |
| Painel | dashboard da plataforma (DAU/transações/renda/ranking) | Concluído |
| Exportação | exportação Excel de usuários/transações/saques | Concluído |
| Internacionalização | alternância zh/en, tabela de traduções, middleware de detecção de idioma | Concluído |
| Frontend | painel administrativo Flutter PC + plataforma de usuário C-side (incl. i18n) | Concluído |

### Versão padrão — concluída

| Domínio | Funcionalidade | Status |
|----|------|------|
| Usuários | login OAuth (Google/Facebook/Apple/Twitter/Microsoft/LinkedIn/GitHub) | Concluído |
| Pagamento | callback automático de múltiplos canais (Stripe incl. Alipay/WeChat Pay APM / PayPal / NOWPayments IPN / Coinbase Webhook) | Concluído |
| Jogos | gestão de servidores, rastreamento de registros de partidas | Concluído |
| Saque | limites escalonados por KYC (default/verified/vip) + tarifas | Concluído |
| KYC | solicitação de verificação de identidade + revisão | Concluído |
| Risco | blacklist de IP/alerta de valores altos/detecção de frequência/velocidade | Concluído |
| Estatísticas | snapshot de estatísticas diárias (usuários/depósitos/saques/trocas/jogos) | Concluído |
| Frontend | Admin: revisão KYC + logs de risco / Platform: OAuth+KYC+registros de partidas | Concluído |

### Versão completa — concluída

| Domínio | Funcionalidade | Status |
|----|------|------|
| Lobby de jogos | 10 categorias predefinidas, filtro por categoria, associação jogo-categoria | Concluído |
| Rankings | ranking diário/semanal/mensal/total, cache Redis, múltiplas métricas | Concluído |
| Cupons | valor fixo + desconto percentual, limitado por tempo/quantidade, rastreamento de resgate/uso | Concluído |
| Configuração de países | 18 países predefinidos, pagamento/saque diferenciados, depósito mínimo | Concluído |
| Estatísticas | snapshot de estatísticas diárias + rastreamento de receita da plataforma | Concluído |
| Busca | busca full-text Elasticsearch (integrada na camada de modelos) | Concluído |

### Upgrades de nível de produção — concluídos

| Domínio | Funcionalidade | Status |
|----|------|------|
| OAuth | troca de token real Google/Facebook/Apple | Concluído |
| Pagamento | verificação de assinatura de callback (Webhook Stripe incl. Alipay/WeChat Pay APM, Webhook PayPal, NOWPayments IPN HMAC-SHA512, Coinbase HMAC-SHA256 secret base64) | Concluído |
| Captcha | captcha de clique poster-php | Concluído |
| Notificações | mensagem no site + email, notificações automáticas de depósito/saque/KYC/cupom | Concluído |
| 2FA | Google Authenticator TOTP + códigos de recuperação reserva | Concluído |
| Indicação | código de indicação, recompensa de registro, comissão de depósito | Concluído |
| Busca | API de busca ES + sugestões de jogos + fallback LIKE | Concluído |
| Rankings | push em tempo real via WebSocket (porta 8790) | Concluído |
| CDN | Integração de cinco provedores (Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS upload + purga + preload) | Concluído |
| Administração CDN | Configuração dos cinco provedores no admin (credenciais criptografadas/ativação-desativação/teste de conectividade HeadBucket), o serviço só lê do banco de dados | Concluído |
| Relatórios | Relatórios de dados do admin (resumo/diário/exportação CSV, cache Redis 5 min, período ≤90 dias) | Concluído |
| Estatísticas da plataforma | Estatísticas da página inicial C (total jogos/usuários/partidas hoje/ativos 7 dias) | Concluído |
| Implantação | Docker Compose 7 serviços + proxy reverso Nginx | Concluído |
| Dados | análise com agregação em tempo real no MySQL + cálculo de probabilidade conjunta/condicional | Concluído |
| HarmonyOS | admin 8 páginas; C-side `apps/harmonyos/` com login/lobby/detalhes/carteira/perfil implementados (aponta para 8792) | Parcialmente concluído (projeto compila; em dispositivo real, alterar o IP) |
| Documentação da API | documentação interativa erikwang2013/apidoc-php | Concluído |
| Instalação em um clique | Assistente de instalação no navegador: criar admin, atualizar banco existente, install.lock evita reinstalação | Concluído |
| Tolerância a falhas | CircuitBreaker + Retry + interruptor de degradação feature.provider_mock | Concluído |
| Métodos de pagamento | CRUD no admin + visibilidade por país + faixa de valores + restrição de moeda | Concluído |
| CI | tag autoincrementado no push + GitHub Release | Concluído |

### Expansão do ecossistema (v2.0) — recém-concluída

| Domínio | Funcionalidade | Status |
|----|------|------|
| Integração de jogos | camada de abstração GameProvider (Self/ThirdParty) + assinatura HMAC-SHA256 | Concluído |
| Callbacks de jogos | gateway Provider API (balance/bet/settle/refund) + middleware ProviderAuth | Concluído |
| Sessões de jogo | Token de sessão SDK: assinatura HMAC-SHA256 + TTL de 5 minutos (emitido por `GET /api/v1/game/session`, validado por `SdkSessionAuth`) | Concluído |
| Sistema de tickets | criação/resposta C-side + tratamento/atribuição/fechamento no admin, 5 tipos de ticket | Concluído |
| Verificação de email | código de 6 dígitos, expiração Redis 10 min, limite de reenvio 60s | Concluído |
| Push notifications | PushService (FCM/APNs/push Huawei) + modelo DeviceToken | Concluído |
| Sistema VIP | 5 níveis (normal/prata/ouro/platina/diamante) + pontos de experiência + upgrade automático | Concluído |
| Benefícios VIP | desconto de troca 2-15%, redução de tarifa de saque 10-100%, bônus de câmbio 0.1-1.0% | Concluído |
| Sistema de conquistas | 12 conquistas integradas; detecção orientada a eventos com EventConsumer → AchievementService e experiência VIP | Concluído |
| Sistema de amigos | solicitação/aceite/recusa/remoção/busca, status pending/accepted/blocked | Concluído |
| Mensagens privadas/chat | mensagens privadas REST + mensagens em tempo real WebSocket (porta 8791), apenas entre amigos | Concluído |
| Barramento de eventos | Redis Pub/Sub; emit + EventConsumer consome conquistas/Webhook + INCR em metrics | Concluído |
| Feature flags | FeatureFlag baseado em DB; `inRollout`/`abTest` com buckets crc32 lendo `feature.{name}_percent` | Concluído |
| Análise avançada | retenção/D1-D30, funil de conversão, ARPU/ARPPU, indicadores econômicos das moedas de jogo (agregação em tempo real no MySQL) | Concluído |
| Webhook | gestão de assinaturas + entrega de eventos Redis Pub/Sub, 7 tipos de evento | Concluído |
| Chat | mensagens privadas REST + mensagens em tempo real WebSocket (porta 8791), apenas entre amigos | Concluído |
| Torneios | create/list/detail/join, feature flag, rankings, limite de participantes | Concluído |
| Comissão em vários níveis | repartição de indicação de segundo nível, modelo ReferralCommission, taxa de comissão configurável | Concluído |
| Condições de cupons | três tipos de restrição: min_deposit/first_user_only/game_id | Concluído |
| Documentação do SDK | documentação de integração do Provider (exemplos PHP/Go/Python + 4 endpoints de API) | Concluído |
| Minijogo | Farm Match-3 P0 (motor de domínio + design de 4 níveis, testes unitários TypeScript/Vite/Vitest) | Concluído |

## 2. Funcionalidades do usuário C-side

### 2.1 Jornada do usuário

```
Registro → Login → verificação de email/telefone → navegar no lobby de jogos → entrar nos detalhes do jogo
                                           ↓
Ver carteira ← jogar ← trocar moeda de jogo (desconto VIP) ← depositar moeda da plataforma
    ↓
Saque (redução de tarifa VIP) → revisão do backend → valor creditado
    ↓
Sistema de amigos → chat privado → ranking competitivo → rastreamento de conquistas
    ↓
Suporte via tickets
```

### 2.2 Interfaces de API

| Método | Caminho | Observação | Autenticação |
|------|------|------|------|
| POST | /api/v1/auth/register | registro de usuário | Não |
| POST | /api/v1/auth/login | login de usuário | Não |
| POST | /api/v1/auth/refresh | refresh do Token | Não |
| GET | /api/v1/game/list | lista de jogos | Não |
| GET | /api/v1/game/detail/{id} | detalhes do jogo | Não |
| GET | /api/v1/announcement/list | lista de anúncios | Não |
| GET | /api/v1/wallet/info | saldo da carteira | Sim |
| GET | /api/v1/wallet/transactions | registro de transações | Sim |
| POST | /api/v1/deposit/create | criar ordem de depósito | Sim |
| GET | /api/v1/payment/methods | lista de métodos de pagamento (rota por país) | Sim |
| POST | /api/v1/exchange/quote | cotação de troca (desconto VIP) | Sim |
| POST | /api/v1/exchange/buy | comprar moeda de jogo | Sim |
| POST | /api/v1/exchange/sell | vender moeda de jogo | Sim |
| POST | /api/v1/withdraw/apply | solicitação de saque (redução VIP) | Sim |
| POST | /api/v1/game/launch | iniciar jogo | Sim |
| GET | /api/v1/game/play-logs | registros de partidas | Sim |
| POST | /api/v1/referral/apply | usar código de indicação | Sim |
| POST | /api/v1/verify/send-email | enviar código de verificação de email | Sim |
| POST | /api/v1/verify/confirm-email | confirmar email | Sim |
| GET | /api/v1/ticket/list | lista de tickets | Sim |
| POST | /api/v1/ticket/create | criar ticket | Sim |
| POST | /api/v1/ticket/{id}/reply | responder ticket | Sim |
| GET | /api/v1/platform/stats | Estatísticas da plataforma | Não |

## 3. Funcionalidades do painel administrativo

### 3.1 Interfaces de API (novas)

| Método | Caminho | Observação |
|------|------|------|
| GET | /admin/v1/dashboard/platform | dados do dashboard da plataforma |
| GET | /admin/v1/analytics/overview | visão geral da plataforma (agregação em tempo real no MySQL) |
| GET | /admin/v1/analytics/game-ranking | ranking de jogos |
| GET | /admin/v1/analytics/dau-trend | tendência de DAU |
| GET | /admin/v1/analytics/hourly-trend | tendência por hora |
| GET | /admin/v1/analytics/action-distribution | distribuição de ações |
| GET | /admin/v1/analytics/revenue | análise de receita |
| GET | /admin/v1/analytics/conversion | taxa de conversão de jogos |
| GET | /admin/v1/analytics/probability | probabilidade conjunta/condicional |
| GET | /admin/v1/analytics/retention | análise de retenção D1/D3/D7/D30 |
| GET | /admin/v1/analytics/funnel | funil de conversão |
| GET | /admin/v1/analytics/arpu | tendência ARPU/ARPPU |
| GET | /admin/v1/analytics/economy | indicadores econômicos das moedas de jogo |
| GET | /admin/v1/report/summary | Resumo de relatórios (novos usuários/depósitos/saques/câmbios/partidas) |
| GET | /admin/v1/report/daily | Relatório diário (agregação por dia, datas sem dados preenchidas com 0) |
| GET | /admin/v1/report/export | Exportação do relatório diário em CSV (UTF-8 BOM) |
| GET | /admin/v1/game/list | lista de jogos |
| GET | /admin/v1/game/{id} | detalhes do jogo |
| POST | /admin/v1/game/launch | Pré-visualização do jogo (somente leitura) |
| POST | /admin/v1/game/create | criar jogo (incl. provider_config) |
| PUT | /admin/v1/game/{id} | editar jogo |
| GET | /admin/v1/withdraw/orders | lista de ordens de saque |
| PUT | /admin/v1/withdraw/review | revisar saque |
| GET | /admin/v1/ticket/list | lista de tickets |
| GET | /admin/v1/ticket/{id} | detalhes do ticket |
| POST | /admin/v1/ticket/{id}/reply | responder ticket |
| POST | /admin/v1/ticket/{id}/close | fechar ticket |
| POST | /admin/v1/ticket/{id}/assign | designar responsável |

## 4. Provider API (callbacks do provedor de jogos)

| Método | Caminho | Observação | Autenticação |
|------|------|------|------|
| POST | /api/provider/balance | consultar saldo do usuário | HMAC-SHA256 |
| POST | /api/provider/bet | notificar aposta | HMAC-SHA256 |
| POST | /api/provider/settle | notificar liquidação | HMAC-SHA256 |
| POST | /api/provider/refund | notificar reembolso | HMAC-SHA256 |

Algoritmo de assinatura: `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)`
Cabeçalhos da requisição: `X-Game-Id` + `X-Timestamp` + `X-Signature`
Janela de tempo: 5 minutos

## 5. Sistema VIP

| Nível | EXP acumulado | Desconto de troca | Redução de tarifa de saque | Bônus de câmbio |
|------|---------|---------|-------------|---------|
| Normal | 0 | 0% | 0% | base |
| Prata | 500 | 2% | 10% | +0.1% |
| Ouro | 2,500 | 5% | 30% | +0.3% |
| Platina | 12,500 | 10% | 50% | +0.5% |
| Diamante | 62,500 | 15% | 100% | +1.0% |

### Como ganhar experiência

| Ação | EXP |
|------|-----|
| Depositar 1 unidade | 10 |
| Login diário | 5 |
| Concluir KYC | 50 |
| Convidar novo usuário | 100 |
| Alcançar conquista | 10-100 |

## 6. Lista de conquistas

| Conquista | Condição | Pontos |
|------|------|------|
| First Deposit | primeiro depósito | 20 |
| Century Club | depósito acumulado de 100 | 50 |
| High Roller | depósito acumulado de 1000 | 100 |
| Trader | primeira troca | 20 |
| Day Trader | 100 trocas acumuladas | 100 |
| Explorer | jogou 3 jogos | 30 |
| Adventurer | jogou 5 jogos | 50 |
| Conqueror | jogou 10 jogos | 100 |
| Weekly Warrior | login 7 dias consecutivos | 30 |
| Monthly Master | login 30 dias consecutivos | 100 |
| Connector | convidou 1 amigo | 30 |
| Influencer | convidou 10 amigos | 100 |

## 7. Lista de tabelas do banco de dados

### Novas tabelas da expansão do ecossistema (10)

| Nome da tabela | Observação | Características-chave |
|------|------|---------|
| game_ticket | tickets | índice user_id+type+status, assigned_to |
| game_ticket_reply | respostas de tickets | índice ticket_id, is_admin distingue |
| game_device_token | tokens de dispositivo | índice único user_id+platform+token |
| game_vip_level | definição de níveis VIP | índice único level, benefits JSON |
| game_user_vip | registro VIP do usuário | índice único user_id, level+exp+total_exp |
| game_exp_log | log de pontos de experiência | índice combinado user_id+source |
| game_achievement | definição de conquistas | índice único key, condition_json JSON |
| game_user_achievement | conquistas do usuário | índice único user_id+achievement_id |
| game_friend | relação de amizade | índice único user_id+friend_id |
| game_message | mensagens privadas | from_user_id+to_user_id / to_user_id+is_read |

### Mudanças de estrutura de tabelas

| Nome da tabela | Mudança |
|------|------|
| game_game | +provider_config (JSON) |
| game_game_play_log | +round_id, +bet_amount, +win_amount |

**Total: 78 tabelas no install.sql**. Modelos: 52 compartilhados em `packages/platform-common/src/model/`; os 8 em admin/app/model/ e 10 em service/app/model/ são exclusivos de cada host (sem sobreposição de nomes de arquivo).

## 8. Cobertura de testes

| Arquivo de teste | Nº de casos | Cobertura |
|---------|--------|---------|
| PlatformTest | 55 | precisão bcmath/cálculos de troca/tarifas de saque/limites/risco/cupons/KYC/i18n |
| BackendEnhancementTest | 27 | serviços de criptografia/Hashids/Snowflake |
| CaptchaTest | 5 | geração/validação de captcha |
| EncryptionServiceTest | 8 | AES criptografia/descriptografia/mascaramento |
| EnvConfigTest | 6 | configuração de variáveis de ambiente |
| HashidsServiceTest | 6 | ida e volta de codificação/decodificação de IDs |
| SnowflakeServiceTest | 5 | unicidade de geração de IDs |

**Total (phpunit --list-tests, medição atual): admin 200 casos / 21 arquivos, service 273 casos / 42 arquivos (incl. WebhookUrlSafety + EventBusMessageFormat; relatório: repetição de 09-22 admin 190 + service 273, snapshot de 08-27 admin 153 + service 45). service não incluído no bloqueio de falhas do CI (não verificado).**

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
