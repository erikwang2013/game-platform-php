# Changelog
<!-- lang-nav -->

Languages: [中文](CHANGELOG.md) · [English](CHANGELOG.en.md) · [한국어](CHANGELOG.ko.md) · [Русский](CHANGELOG.ru.md) · [Deutsch](CHANGELOG.de.md) · [Français](CHANGELOG.fr.md) · [Español](CHANGELOG.es.md) · **Português** · [हिन्दी](CHANGELOG.hi.md) · [العربية](CHANGELOG.ar.md) · [বাংলা](CHANGELOG.bn.md) · [Bahasa Indonesia](CHANGELOG.id.md) · [日本語](CHANGELOG.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Registro de mudanças legível para humanos. O PHP não importa este arquivo. Corresponde ao P2-21 do PROJECT-PLAN.

## [1.1] — 2026-08-07

- Integração do plugin Redis, serviços de análise, degradação do Redis, correções de testes.

## [1.1] security / ops — 2026-08-18

### Segurança

- Callback de pagamento: whitelist de providers (stripe/paypal), verificação de assinatura fail-closed, conferência de valores, entrada transacional, timestamp Stripe ±300s anti-replay.
- JWT: recusa iniciar se `JWT_SECRET_KEY` / `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY` estiverem ausentes ou com valor padrão.
- Apple id_token: verificação de assinatura JWKS (RS256) + aud/iss/exp.
- Webhook: apenas URLs https públicas; recusa redes internas/endereços reservados (SSRF).
- 2FA: chave TOTP HMAC decodificada com Base32 RFC 4648; `/api/2fa/verify` com bloqueio por usuário após falhas (5 vezes / 15 minutos, fail-closed se o Redis falhar).
- Saque: transição atômica de status com UPDATE condicional na revisão/pagamento; revisão dupla opcional (`withdraw.require_dual_review`); lock de usuário no Redis no lado da solicitação para evitar estouro concorrente dos limites.
- Rate limit: fail-closed se o Redis falhar.

### Disponibilidade

- 12 rotas `/admin/analytics/*` do serviço de análise do admin montadas.
- Modelos sem prefixo `game_` hardcoded; DepositLog auditado no banco; model Test removido.

### Observabilidade

- `GET /metrics` adiciona saques aguardando revisão, depósitos confirmados hoje (COUNT com cache Redis 30s), contadores de emit/consume de eventos, `memory_usage`, `info version=1.1`.
- FeatureFlag: `inRollout` / `abTest` com buckets por crc32 lendo `feature.{name}_percent`.
- EventBus faz INCR em `metrics:event_emit_total` / `metrics:event_consume_total` no Redis no `emit` / `consume`.

### Clientes / compartilhado (completado no mesmo dia)

- Flutter Platform: tabela de rotas `app_pages.dart`; páginas de configuração/verificação 2FA, cupons, rankings, notificações e callback OAuth; entrada do lobby já navega.
- HarmonyOS C-side: `apps/harmonyos/` cinco páginas (login/lobby/detalhes/carteira/perfil), `BASE_URL` padrão apontando para o service `8788`.
- Camada compartilhada: `packages/platform-common` (path repo `erik/platform-common`) extrai DepositLog / GameDashboard / Probability / GamePlayLog; os models ainda duplicados.
- ClickHouse: dependência do composer removida; a análise continua com agregação em tempo real no MySQL.
- CI: admin / service rodam phpunit em jobs separados, falha bloqueia.

### Lacunas remanescentes

- Os **models** de admin/service ainda são duplicados (apenas parte dos `common/service` foi para o path package).
- `webman/queue` não conectado; probabilidade/retenção ainda não migradas para OLAP.
- PARTES do PROJECT-PLAN / VERSIONS / relatórios de auditoria ainda podem estar defasadas em relação a este CHANGELOG; prevalecer este arquivo e o disco.

## [1.1] resilience — 2026-08-27

### Estabilidade

- Camada compartilhada: adicionados `CircuitBreaker` (estado no Redis, limite 5 / janela 30 s, fail-open se Redis indisponível) e `Retry` (backoff exponencial, apenas exceções de rede, máx. 5 tentativas), em `packages/platform-common/src/`.
- Interruptor de degradação `feature.provider_mock`: PushService (FCM/APNs/HarmonyOS), PayoutService (PayPal), ThirdPartyProvider fazem curto-circuito quando `on`, sem chamadas de rede reais.
- Corrigidos 11 defeitos de tipo de `getenv($name, '')` (TypeError com strict_types); verificação mock do PushService movida para try/catch.
- Novos testes: CircuitBreakerTest / RetryTest / ResilienceMockTest; suíte service 45 → 60 casos, todos verdes (relatório: [test-reports/resilience.md](test-reports/resilience.md)).
