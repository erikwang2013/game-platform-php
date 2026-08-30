# service/ — API do serviço da plataforma de usuários (lado C)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · **Português** · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

O serviço API da plataforma de usuários (lado C) é um backend PHP de alto desempenho baseado em webman v2 (Workerman) que oferece aos usuários todos os recursos da plataforma de agregação de jogos: cadastro e login, carteira, depósito, saque, câmbio, jogos, rankings, cupons, tickets de suporte, VIP, conquistas, recursos sociais e avisos.

## Funcionalidades

| Módulo | Descrição |
|------|------|
| Usuários | Cadastro/login (usuário+senha + OAuth de 7 plataformas + 2FA TOTP), perfil |
| Carteira | Carteira de moedas da plataforma (bloqueio otimista) + carteira de moedas de jogo + histórico de transações |
| Depósito | 13 gateways de pagamento (Stripe/PayPal/NowPayments/Coinbase, etc.) com verificação de assinatura de callbacks e crédito automático |
| Saque | Solicitação → revisão → pagamento, limites escalonados de KYC |
| Câmbio | Cotações em tempo real moeda da plataforma ⇄ moeda de jogo, descontos VIP e bônus de taxa |
| Jogos | Lista/categorias/busca de jogos, histórico de partidas, callbacks de liquidação do Provider |
| Rankings | Diário/semanal/mensal/geral + push WebSocket em tempo real |
| Cupons | Valor fixo + desconto percentual, limitados por tempo e quantidade |
| Tickets | Criação/respostas a tickets de suporte pelo usuário |
| VIP | 5 níveis de fidelidade, acúmulo de experiência, descontos no câmbio |
| Conquistas | 12 conquistas integradas, detecção orientada a eventos |
| Social | Sistema de amigos + mensagens privadas WebSocket em tempo real |
| Avisos | Avisos no app + notificações/e-mail |

## Stack tecnológico

- PHP 8.3+ / webman v2 (workerman/webman)
- MySQL 8.0+ (prefixo de tabela `game_`, chaves primárias BIGINT sem autoincremento)
- Redis (Sessão / Cache / Limite de requisições)
- ClickHouse (análise OLAP / cálculo de probabilidades)
- Elasticsearch (busca de texto completo)
- Autenticação JWT + assinatura HMAC-SHA256 do Provider

## Estrutura do projeto

```
service/
├── app/
│   ├── api/v1/controller/  # Controladores de API lado C (35)
│   ├── middleware/         # Middleware (Cors/SecurityFilter/RateLimit/ApiVersion/UserAuth/ProviderAuth)
│   ├── model/              # Modelos de dados
│   ├── service/            # Serviços de negócio (VIP/rankings/risco/notificações, etc.)
│   ├── event/              # Barramento de eventos (EventBus Redis Pub/Sub)
│   ├── provider/           # Camada de Provider de jogos
│   └── payment/            # Gateways de pagamento
├── common/                 # Serviços compartilhados (implementados no pacote erik/platform-common)
├── config/                 # Arquivos de configuração
├── public/                 # Entrada web
├── tests/                  # Testes PHPUnit
├── start.php               # Entrada de inicialização
└── composer.json
```

## Instalação em um clique

Recomendado: assistente de instalação em um clique na raiz do projeto (executar a partir da raiz):

```bash
# 1. Iniciar o assistente de instalação
php -S 0.0.0.0:8888 -t install/

# 2. Abrir http://localhost:8888 no navegador
#    Seguir o assistente: verificação do ambiente → configuração do banco → conta de administrador → instalação automática
```

Ou iniciar tudo com Docker Compose (raiz do projeto):

```bash
docker compose up -d
```

## Instalação manual

```bash
# 1. Instalar dependências
cd service && composer install

# 2. Configurar variáveis de ambiente
cp .env.example .env
# Editar .env: conexão com o banco, chaves JWT, etc.

# 3. Iniciar o serviço (porta padrão 8788)
php start.php start        # primeiro plano
php start.php start -d     # segundo plano (daemon)
```

## Uso

- Referência da API: `docs/API.md` (referência completa)
- Documentação on-line: http://localhost:8788/apidoc/ (documentação interativa hg/apidoc)
- Verificação de saúde: `GET http://localhost:8788/health`
- Frontend lado C: `apps/flutter/platform/` (plataforma de usuário Flutter Web)
- Backend admin: `admin/` (backend admin e frontend `admin/apps/flutter/`)

## Testes

```bash
cd service
SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
```
