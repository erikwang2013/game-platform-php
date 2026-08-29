# Documento de design de funcionalidades
<!-- lang-nav -->

Languages: [中文](FEATURE-DESIGN.md) · [English](FEATURE-DESIGN.en.md) · [한국어](FEATURE-DESIGN.ko.md) · [Русский](FEATURE-DESIGN.ru.md) · [Deutsch](FEATURE-DESIGN.de.md) · [Français](FEATURE-DESIGN.fr.md) · [Español](FEATURE-DESIGN.es.md) · **Português** · [हिन्दी](FEATURE-DESIGN.hi.md) · [العربية](FEATURE-DESIGN.ar.md) · [বাংলা](FEATURE-DESIGN.bn.md) · [Bahasa Indonesia](FEATURE-DESIGN.id.md) · [日本語](FEATURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Design do sistema de moedas

### 1.1 Modelo de moedas em três camadas

```
Camada 1: Moeda fiduciária (USD / CNY / EUR / JPY ...)
       ↕ depósito/saque (conversão pela taxa de câmbio)
Camada 2: Moeda da plataforma (unificada, precisão decimal(18,4))
       ↕ troca (inclui taxa de câmbio + margem da plataforma)
Camada 3: Moeda de jogo (independente por jogo, câmbio independente)
```

### 1.2 Moeda da plataforma

- Unidade de valor unificada dentro da plataforma
- Precisão: `DECIMAL(18,4)`, menor unidade 0.0001
- Obtida por depósito de moeda fiduciária; pode ser trocada por qualquer moeda de jogo
- A moeda de jogo também pode ser convertida de volta em moeda da plataforma e depois sacada como moeda fiduciária
- A plataforma cobra a margem de troca como fonte de receita

### 1.3 Moeda de jogo

- Cada jogo pode ter várias moedas de jogo (por exemplo, ouro, diamantes, pontos)
- Cada moeda define independentemente a taxa de câmbio com a moeda da plataforma (`exchange_rate`)
- Cada moeda define independentemente a margem da plataforma (`spread_pct`)
- Suporta definir limites mínimo/máximo de troca (`min_exchange` / `max_exchange`)

### 1.4 Fórmulas de troca

**Comprar moeda de jogo (moeda da plataforma → moeda de jogo):**
```
Moeda de jogo creditada = quantidade de moeda da plataforma × exchange_rate × (1 - spread_pct / 100)
```

**Vender moeda de jogo (moeda de jogo → moeda da plataforma):**
```
Moeda da plataforma creditada = quantidade de moeda de jogo ÷ exchange_rate × (1 - spread_pct / 100)
```

**Exemplo:**
- exchange_rate = 100 (1 moeda da plataforma = 100 moedas de jogo)
- spread_pct = 5% (a plataforma cobra 5% de margem)
- Usuário compra com 10 moedas da plataforma: (10 × 100 × 0.95) = 950 moedas de jogo
- Usuário vende 950 moedas de jogo: (950 ÷ 100 × 0.95) = 9.025 moedas da plataforma
- Receita da plataforma: 10 - 9.025 = 0.975 moedas da plataforma

## 2. Design da carteira

### 2.1 Carteira de moeda da plataforma (game_user_wallet)

Criada automaticamente no registro do usuário, saldo inicial 0.

| Campo | Observação |
|------|------|
| balance | saldo disponível (pode depositar/sacar/trocar) |
| frozen_balance | saldo congelado (reservado, por exemplo, durante saque) |
| total_earned | rendimento acumulado |
| total_spent | gasto acumulado |
| version | número de versão do lock otimista (+1 a cada atualização) |

### 2.2 Carteira de moeda de jogo (game_user_game_wallet)

Única por usuário+jogo+moeda (três dimensões). Criada automaticamente na primeira troca.

### 2.3 Segurança de concorrência

Usa lock otimista para evitar problemas de concorrência:

```php
// Verificar o número de versão na atualização
$updated = Wallet::where('id', $wallet->id)
    ->where('version', $wallet->version)
    ->update([
        'balance' => bcadd($wallet->balance, $amount, 4),
        'version' => $wallet->version + 1,
    ]);

// Falha na atualização (número de versão mudou) → tentar novamente, no máximo 5 vezes
```

## 3. Design do sistema de saque

### 3.1 Controle em várias camadas

```
Camada 1: Interruptor global de saque
       ├─ desligado → todos os saques recusados, para gestão de risco emergencial
       └─ ligado → passa para a verificação da camada 2

Camada 2: Verificação de limites
       ├─ valor mínimo por transação (min_amount)
       ├─ valor máximo por transação (max_amount)
       └─ limite acumulado diário (daily_limit)

Camada 3: Fluxo de revisão
       ├─ valor < limite de revisão automática → aprovação automática
       └─ valor >= limite de revisão automática → revisão manual → aprovar/recusar
```

### 3.2 Máquina de estados do saque

```
pending (aguardando revisão)
  ├─→ approved (aprovado) → completed (concluído)
  └─→ rejected (recusado) → devolução do saldo + transação de reembolso
```

### 3.3 Controles do painel administrativo

- **Botão de interruptor global**: liga/desliga os saques de todos os usuários com um clique
- **Fila de revisão**: lista de pendências ordenada por tempo, com botões aprovar/recusar
- **Configuração de limites**: ajuste visual dos parâmetros de limite

## 4. Design do depósito

### 4.1 Fluxo de depósito

```
1. Usuário escolhe método de pagamento e valor
2. A plataforma cria a ordem de depósito (status=pending, order_no único gerado)
3. Redireciona para a página de pagamento de terceiros
4. Usuário conclui o pagamento
5. O terceiro notifica a plataforma via callback (POST /api/payment/callback)
6. A plataforma verifica a assinatura → atualiza a ordem (status=confirmed)
7. Moeda da plataforma creditada → registra a transação
```

### 4.2 Métodos de pagamento

| Tipo | Provedor | Observação |
|------|--------|------|
| Fiduciário | Stripe | cartão de crédito internacional |
| Fiduciário | PayPal | carteira eletrônica global |
| Fiduciário | Alipay | Alipay (internacional, via Stripe Checkout APM) |
| Fiduciário | WeChat Pay | WeChat Pay (internacional, via Stripe Checkout APM) |
| Criptomoeda | USDT-TRC20 | USDT na rede TRON |

A versão base integra primeiro um único método de pagamento (por exemplo, Stripe); a versão padrão expande todos os canais.

## 5. Design de integração de jogos

### 5.1 Jogos próprios

Jogos próprios são integrados diretamente à plataforma, compartilhando o sistema de usuários e a carteira:

- O jogo consulta o saldo de moeda de jogo do usuário via API interna
- A liquidação do jogo debita/credita moeda de jogo via API interna
- Sem necessidade de verificação de assinatura adicional

### 5.2 Jogos de terceiros

Jogos de terceiros integram via SDK/API:

```
Lado da plataforma:
  1. Usuário clica em "entrar no jogo"
  2. A plataforma gera a assinatura (user_id + timestamp + api_secret → HMAC-SHA256)
  3. Redireciona 302 ou carrega a URL do jogo em iframe (com parâmetros de assinatura)

Lado do jogo:
  4. Verifica a assinatura → cria sessão do jogo
  5. Consulta saldo: GET /api/game/balance?user_id=...&sign=...
  6. Callback de liquidação: POST /api/game/callback {user_id, amount, type, sign}
  7. A plataforma verifica a assinatura → atualiza saldo → registra transação → retorna resultado
```

### 5.3 Algoritmo de assinatura

```
signature = HMAC-SHA256(
    secret = api_secret,
    message = user_id + "|" + timestamp + "|" + amount + "|" + nonce,
)
```

Condições de validação:
- Assinatura correta
- Timestamp dentro de ±60s (anti replay attack)
- nonce nunca usado (registrado no Redis, expira em 60s)
- IP da requisição na whitelist

## 6. Design de permissões

### 6.1 Roles predefinidos

| Role | Escopo de permissão |
|------|---------|
| Superadministrador | * (todas as permissões) |
| Operador de jogos | gestão de jogos, gestão de anúncios, dashboard |
| Auditor financeiro | revisão de saques, gestão de pagamentos, visualização de transações |
| Suporte | visualização de usuários C-side, visualização de ordens de depósito |

### 6.2 Granularidade de permissões

```
{method}.{path}

Exemplos:
  get.admin/game/list      → ver lista de jogos
  post.admin/game/create   → criar jogo
  put.admin/withdraw/review → revisar saque
  put.admin/withdraw/switch → operar o interruptor de saque (apenas superadministrador)
```

## 呼. Novos designs da versão padrão

### 8.1 Motor de gestão de risco

Quatro tipos de regra:
- `ip_blacklist` — correspondência de blacklist de IP, bloqueia diretamente ao acertar
- `amount_anomaly` — detecção de valor alto por transação, emite alerta ao exceder o limite
- `frequency` — detecção de frequência de operações dentro da janela de tempo
- `velocity` — detecção de associação entre múltiplas contas em curto período

As regras são executadas em ordem decrescente de priority; a primeira regra correspondida decide o resultado (block > warn > log).

### 8.2 Login OAuth de terceiros

Provedores suportados: Google, Facebook, Apple

Fluxo:
1. O frontend solicita `GET /api/auth/oauth/{provider}` para obter a URL de autorização
2. O usuário é redirecionado ao terceiro e conclui a autorização
3. O callback `POST /api/auth/oauth/{provider}/callback` carrega o código de autorização
4. O backend procura vínculo existente → login direto; sem vínculo → registro automático + vínculo + criação de carteira

### 8.3 Sistema de limites KYC

| Nível | Como obter | Limite por transação | Limite diário | Tarifa |
|------|---------|---------|--------|--------|
| default | padrão no registro | 1,000 | 10,000 | 1.00% |
| verified | KYC aprovado | 5,000 | 50,000 | 0.50% |
| vip | concedido pela operação | 20,000 | 200,000 | 0.00% |

### 8.4 Servidores de jogo

Cada jogo pode ter vários servidores (region: global/asia/eu/na); status do servidor: manutenção/normal/cheio/novo.

### 8.5 Snapshot de estatísticas diárias

O crontab executa `ComputeDailyStats::run()` no início da madrugada, calculando cinco métricas:
- Estatísticas de usuários (novos/ativos/acumulados)
- Estatísticas de depósitos (quantidade/valor total)
- Estatísticas de saques (quantidade/valor total)
- Estatísticas de trocas (quantidade/total de tarifas)
- Estatísticas de jogos (nº de jogadores/nº de sessões)

## 9. Funcionalidades de nível de produção

### 9.1 Sistema de notificações

Tipos de notificação: system/deposit/withdraw/kyc/coupon/announcement

Cenários de disparo automático:
- Depósito creditado → NotificationService::send()
- Saque aprovado/recusado na revisão → notificação automática
- KYC aprovado/recusado na revisão → notificação automática
- Cupom resgatado → notificação automática
- Recompensa de indicação creditada → notificação automática

Suporta canal duplo: mensagem no site + email (o email exige configurar a variável de ambiente MAIL_HOST).

### 9.2 Comissão de indicação

```
Usuário A gera código de indicação → compartilha com o usuário B
Usuário B preenche o código no registro → ambos recebem recompensa de registro (signup_reward)
Usuário B deposita → A recebe comissão de depósito (deposit_commission_pct%)
```

### 9.3 Autenticação de dois fatores 2FA

- Protocolo TOTP padrão (RFC 6238), compatível com Google Authenticator
- Fluxo de habilitação: obter chave → escanear QR para vincular → verificar TOTP → gerar 8 códigos de recuperação reserva
- Verificação secundária no login: POST /api/2fa/verify
- Suporta tolerância de ±1 janela de tempo (30 segundos)

### 9.4 Integração OAuth real

| Provedor | Endpoint de token | Endpoint de informações do usuário |
|--------|----------|------------|
| Google | oauth2.googleapis.com/token | www.googleapis.com/oauth2/v3/userinfo |
| Facebook | graph.facebook.com/v18.0/oauth/access_token | graph.facebook.com/me |
| Apple | appleid.apple.com/auth/token | decodificação do JWT id_token |

Configuração via PlatformConfig ou variáveis de ambiente; em caso de falha de requisição, cai automaticamente para o modo mock.

### 9.5 Verificação de assinatura de webhook de pagamento

- Stripe: verificação de assinatura HMAC-SHA256 (cabeçalho Stripe-Signature)
- PayPal: POST de volta ao endpoint de verificação do PayPal
- Sem chave configurada, a verificação é pulada automaticamente (modo de desenvolvimento)

### 9.6 Rankings em tempo real via WebSocket

- Protocolo: WebSocket (ws://host:8789)
- Assinatura: {action: "subscribe", leaderboard_id: 123}
- Push: {type: "ranking_update", rankings: [...]}
- Suporta heartbeat ping/pong para manter a conexão

## 7. Design de internacionalização

### 7.1 Idiomas suportados

| Código | Nome | Nome nativo | Ícone |
|------|------|--------|------|
| en-US | English | English | us |
| zh-CN | Chinese (Simplified) | 简体中文 | cn |
| ja-JP | Japanese | 日本語 | jp |
| ko-KR | Korean | 한국어 | kr |

### 7.2 Gestão de traduções

- Traduções organizadas no formato `group.key` (por exemplo `auth.login_success`)
- Armazenadas na tabela `game_translation`, com cache Redis (TTL 1 hora)
- API: `GET /api/language/list` obtém os idiomas disponíveis, `POST /api/language/switch` alterna o idioma
- O frontend detecta automaticamente via cabeçalho `X-Language` ou `Accept-Language`
- Quando a tradução está ausente, faz fallback para en-US; se en-US também não tiver, retorna a key original

### 7.3 Preferência de idioma do usuário

- Definida automaticamente no registro com base no `Accept-Language` do navegador
- Após o login, o campo `language` pode ser alterado via `PUT /api/user/profile`
- Ao alternar o idioma, o registro do usuário é atualizado em sincronia

## 8. Modelo de receita da plataforma

| Fonte de receita | Cálculo | Observação |
|---------|---------|------|
| Margem de troca | spread_fee de cada troca | cobrada nas duas direções, compra e venda |
| Tarifa de saque | valor do saque × fee_pct | implementada na versão padrão |
| Repartição de jogos | repartição de receita de jogos de terceiros | conforme contrato |
| Diferença cambial de depósito | diferença entre o câmbio fiduciário→plataforma | diferença entre o câmbio definido pela plataforma e o câmbio de mercado |
