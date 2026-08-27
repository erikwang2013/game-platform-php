# Documento de design de arquitetura
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE-DESIGN.md) · [English](ARCHITECTURE-DESIGN.en.md) · [한국어](ARCHITECTURE-DESIGN.ko.md) · [Русский](ARCHITECTURE-DESIGN.ru.md) · [Deutsch](ARCHITECTURE-DESIGN.de.md) · [Français](ARCHITECTURE-DESIGN.fr.md) · [Español](ARCHITECTURE-DESIGN.es.md) · **Português** · [हिन्दी](ARCHITECTURE-DESIGN.hi.md) · [العربية](ARCHITECTURE-DESIGN.ar.md) · [বাংলা](ARCHITECTURE-DESIGN.bn.md) · [Bahasa Indonesia](ARCHITECTURE-DESIGN.id.md) · [日本語](ARCHITECTURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Objetivos de design

Construir uma plataforma de agregação de jogos global, universal e internacionalizada. Requisitos principais:

- O usuário pode depositar na plataforma, trocar por moeda de jogo, jogar, ganhar moeda de jogo e sacar
- A plataforma gerencia uniformemente vários jogos (próprios + terceiros), cada jogo com moeda de jogo e câmbio independentes
- O backend oferece capacidade completa de revisão, interruptores e gestão de risco
- Suporta operação global com múltiplos idiomas, múltiplas moedas e múltiplos canais de pagamento

## 2. Seleção de arquitetura

### 2.1 Por que monólito modular em vez de microsserviços?

Nesta fase, optamos por Monólito Modular:

| Consideração | Monólito modular | Microsserviços |
|------|----------|--------|
| Eficiência de desenvolvimento | Chamadas no mesmo processo, sem RPC | Exige lidar com latência de rede e serialização |
| Consistência transacional | Transação local do banco | Transação distribuída (complexa) |
| Complexidade de operação | Implantação de processo único | Orquestração de múltiplos serviços, service discovery |
| Escalabilidade | Futuramente pode ser dividido em microsserviços por módulo | Suporte natural a escala independente |
| Tamanho da equipe | Adequado para equipes pequenas (1-5 pessoas) | Adequado para múltiplas equipes em paralelo |

**Decisão**: admin/ (painel administrativo) e service/ (negócio C-side) são duas instâncias webman independentes; podem ser implantadas na mesma máquina (portas diferentes) ou separadamente. A camada compartilhada common/ elimina duplicação de código via PSR-4 autoload. Quando o volume de negócio crescer, service/ pode ser dividido em vários microsserviços (serviço de usuários, serviço de carteira, serviço de jogos).

### 2.2 Por que webman v2 em vez de PHP-FPM tradicional?

| Consideração | webman v2 | PHP-FPM (Laravel) |
|------|-----------|-------------------|
| Performance | Memória residente, suporte a corrotinas | Carrega todos os arquivos a cada requisição |
| Concorrência | Dezenas de milhares de QPS por máquina | Centenas de QPS por máquina |
| Implantação | Simples, um processo com múltiplos workers | Configuração complexa de Nginx + PHP-FPM |
| Ecossistema | Compatível com componentes Illuminate do Laravel | Ecossistema completo |

**Decisão**: a plataforma de jogos precisa lidar com callbacks de depósito, requisições de troca e liquidação de jogos de alta concorrência; a memória residente e a alta capacidade de concorrência do webman são mais adequadas. Além disso, é compatível com ORM, Queue e outros componentes do Laravel, com eficiência de desenvolvimento não inferior à de frameworks tradicionais.

### 2.3 Por que usar estilo PC com Flutter Web?

- Um único código compila para Web (PC), iOS, Android, HarmonyOS
- A biblioteca de componentes Material 3 é madura; o layout estilo PC com sidebar + topbar é plug-and-play
- Compartilha a camada de lógica de negócio com o cliente HarmonyOS
- Evita manter duas frentes de código: React/Vue + Flutter

## 3. Decisões técnicas-chave

### 3.1 Sistema de IDs

```
Snowflake gera BIGINT (único global distribuído)
    ↓
Hashids codifica em string curta (impossível reverter para o ID real externamente)
    ↓
O hashid string é transmitido em requisições/respostas da API
```

**Motivos**:
- Snowflake é globalmente único, tende a ser crescente — favorável para índices, não expõe volume de negócio
- Hashids impede que terceiros percorram dados via IDs incrementais ou estimem escala

### 3.2 Precisão de moedas

Moeda da plataforma e moeda de jogo usam uniformemente a precisão `DECIMAL(18,4)`; no PHP, todos os cálculos de valores usam a família de funções `bcmath` (bcadd/bcsub/bcmul/bcdiv/bccomp).

**Motivo**: floats (float/double) têm erros de precisão, inaceitáveis em cenários financeiros. DECIMAL + bcmath garantem cálculo exato.

### 3.3 Lock otimista de carteira

```sql
UPDATE game_user_wallet 
SET balance = balance + ?, version = version + 1 
WHERE user_id = ? AND version = ?
```

Se a atualização falhar, tenta novamente automaticamente (no máximo 5 vezes).

**Motivos**:
- Depósitos, trocas e saques da plataforma de jogos podem operar concorrentemente na mesma carteira
- Lock pessimista (SELECT FOR UPDATE) tem desempenho ruim sob alta concorrência
- O lock otimista tem desempenho muito superior ao pessimista em cenários de baixa taxa de conflito

### 3.4 Fluxo de revisão de saque

```
Usuário inicia o saque
  ├─ Interruptor global desligado → recusar
  ├─ Valor < limite de revisão automática → aprovação automática
  └─ Valor >= limite → revisão manual → aprovar/recusar (na recusa, devolve moeda da plataforma)
```

**Motivos**:
- O interruptor global serve para gestão de risco emergencial (por exemplo, descoberta de vulnerabilidade ou tráfego anômalo)
- Aprovação automática de valores pequenos reduz custo manual e melhora a experiência do usuário
- Revisão manual de valores grandes previne lavagem de dinheiro e fraude

### 3.5 Modelo de margem de troca

Cada moeda de jogo tem `exchange_rate` independente (1 moeda da plataforma = X moedas de jogo) e `spread_pct` (margem da plataforma %).

Na compra: moeda de jogo creditada = moeda da plataforma × câmbio × (1 - margem%)
Na venda: moeda da plataforma creditada = moeda de jogo ÷ câmbio × (1 - margem%)

**Motivos**:
- A receita da plataforma vem da margem de troca, não de pagamentos dentro do jogo
- Câmbio independente suporta estratégias de precificação diferentes por jogo
- A porcentagem da margem pode ser ajustada com flexibilidade para operação refinada

## 4. Arquitetura de segurança

Com base na defesa em profundidade de 18 camadas existente, novas camadas de proteção para a plataforma de jogos:

| Camada | Medida | Motivo |
|------|------|------|
| Segurança de concorrência | Lock otimista no version da carteira | Evita débito/entrada duplicados |
| Segurança de saque | Interruptor global + limite de valor + limites diário/mensal + verificação poster-php | Defesa em várias camadas, reduz risco financeiro |
| Segurança de troca | Cotação e fechamento separados, cotação expira em 60s | Evita arbitragem causada por flutuação cambial |
| Segurança de jogos | Verificação de assinatura de callbacks de terceiros + whitelist de IP + defesa contra replay attack | Evita liquidação de jogos forjada |
| Gestão de risco | Motor de regras (blacklist de IP, alerta de valores altos, frequência anômala) | Bloqueia transações suspeitas em tempo real |

## 5. Design de internacionalização

### 5.1 Detecção de idioma

```
Requisição entra
  ↓
LanguageMiddleware (middleware global)
  ├── 1. Cabeçalho X-Language
  ├── 2. Cabeçalho Accept-Language (zh → zh-CN, en → en-US)
  └── 3. Padrão en-US
  ↓
TranslationService::setLocale($locale)
  ↓
Função __() no Controller ou TranslationService::trans() obtém o texto traduzido
```

### 5.2 Armazenamento de traduções

- A tabela `game_translation` armazena todos os textos traduzidos (group + key + lang_code + value)
- Na primeira requisição, carrega tudo do banco para o Redis (key: `i18n:translations`, TTL: 1 hora)
- Requisições seguintes leem direto do Redis, com cache em memória para aceleração
- O painel administrativo pode estender a página de gestão de traduções (implementada na versão completa)

### 5.3 Nomenclatura de chaves de tradução

Formato: `group.key`, por exemplo `auth.login_success`, `wallet.insufficient_balance`

| Grupo | Domínio |
|------|------|
| auth | Autenticação |
| wallet | Carteira |
| exchange | Troca |
| withdraw | Saque |
| deposit | Depósito |
| game | Jogos |
| admin | Painel administrativo |
| error | Mensagens de erro |

### 5.4 Estratégia de fallback

- O idioma da requisição tem tradução correspondente → usar
- O idioma da requisição não tem tradução → fallback para en-US
- en-US também não tem → retorna a key original

### 5.5 i18n no frontend

- O Flutter usa `AppTranslations` próprio + `LocaleController` (GetX)
- A preferência de idioma é persistida no SharedPreferences
- Ao alternar o idioma, `Get.updateLocale()` dispara a re-renderização global da UI
- A classe `StringResult` usa o `toString()` do Dart para sintaxe inline natural: `Text('${AppTranslations.t("key")}')`

## 6. Novos designs da versão padrão

### 6.1 Motor de gestão de risco

Antes das operações financeiras críticas, executa verificação de regras em várias camadas:

```
Requisição de depósito/saque/troca
  ↓
RiskService::check(userId, type, context)
  ├── Detecção de blacklist de IP (ip_blacklist) → block
  ├── Detecção de valor anômalo (amount_anomaly) → warn
  ├── Detecção de frequência (frequency) → warn/block
  └── Detecção de velocidade (velocity) → block
  ↓
passed → executa normalmente
warn   → registra log, continua a execução
block  → recusa a operação
```

As regras ficam na tabela `game_risk_rule`, configuradas como JSON, permitindo ajuste dinâmico de limites e ações.

### 6.2 KYC de identidade real

Sistema de verificação em três níveis:
- `default` — não verificado, limites básicos
- `verified` — KYC aprovado, limites maiores + tarifas menores
- `vip` — nível VIP, maiores limites + tarifa zero

Fluxo de verificação:
```
Usuário envia documentos → status=pending
Administrador revisa → approve/reject
approve → usuário é promovido automaticamente ao nível verified
reject → usuário pode reenviar
```

### 6.3 Login OAuth de terceiros

Suporta login Google / Facebook / Apple:

```
Frontend clica no botão OAuth
  → GET /api/auth/oauth/{provider} → obtém URL de autorização
  → redireciona para a página de autorização de terceiros → usuário consente
  → callback POST /api/auth/oauth/{provider}/callback
  → vinculo existente encontrado → login direto
  → sem vínculo → registra automaticamente novo usuário + vincula + cria carteira
```

### 6.4 Callback de pagamento

```
Pagamento de terceiros concluído → POST /api/payment/callback
  → verificação de whitelist do provider (apenas stripe/paypal)
  → verificação de assinatura fail-closed (sem secret/webhook_id configurado, falha de verificação ou timestamp além de ±300s → sempre recusar)
  → conferência do valor do callback com o valor da ordem via bccomp (evita uso indevido entre canais)
  → atualiza status da ordem para confirmed (transacional; se a entrada falhar, faz rollback)
  → UserWallet::addBalance credita
  → registra Transaction
  → RiskService::check verificação de risco
```

### 6.5 Limites escalonados de saque

Limites e tarifas diferentes por nível KYC do usuário:

| Nível | Limite por transação | Limite diário | Limite mensal | Tarifa |
|------|---------|--------|--------|--------|
| default | 1,000 | 10,000 | 50,000 | 1.00% |
| verified | 5,000 | 50,000 | 200,000 | 0.50% |
| vip | 20,000 | 200,000 | 1,000,000 | 0.00% |

## 7. Design de escalabilidade

### 5.1 Escala horizontal

admin/ e service/ suportam múltiplos processos worker. Com o proxy reverso Nginx, é possível implantar em várias máquinas para escala horizontal:

```
Nginx (balanceamento de carga)
  ├── admin-1 (:8787)
  ├── admin-2 (:8787)
  ├── service-1 (:8788)
  └── service-2 (:8788)
```

### 5.2 Caminho de divisão de módulos

Quando um único service/ vira gargalo, divide-se seguindo este caminho:

```
service/ (monólito)
  → service-user/ (serviço de usuários :8788)
  → service-wallet/ (serviço de carteira :8789)
  → service-game/ (serviço de jogos :8790)
  → service-payment/ (serviço de pagamentos :8791)
```

Critérios para decidir a divisão:
- O QPS de um módulo excede a capacidade de uma máquina
- Um módulo precisa de stack tecnológico ou estratégia de implantação independentes
- A equipe cresce a ponto de precisar desenvolver diferentes módulos em paralelo
