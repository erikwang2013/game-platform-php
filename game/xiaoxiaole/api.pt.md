# 田园消消乐 — API de integração com a plataforma
<!-- lang-nav -->

Languages: **中文** · [English](api.en.md) · [한국어](api.ko.md) · [Русский](api.ru.md) · [Deutsch](api.de.md) · [Français](api.fr.md) · [Español](api.es.md) · [Português](api.pt.md) · [हिन्दी](api.hi.md) · [العربية](api.ar.md) · [বাংলা](api.bn.md) · [Bahasa Indonesia](api.id.md) · [日本語](api.ja.md)


> Este documento é o contrato completo de interfaces entre o 《田园消消乐》 e a plataforma de jogos. A divisão técnica está em `architecture.md`, o cronograma em `plan.md`, e as funcionalidades para jogadores em `functional-design.md`.

---

## 1. Cadeia de inicialização

```
Flutter / HarmonyOS / PC Web
        │  POST /api/game/launch { game_id }
        ▼
service/ (webman :8788)
  GameController::launch  → session_id + seed + api_endpoint
  SelfProvider            → bet / settle / refund / getBalance
  GamePlayLog + EventBus  → game.played / conquistas / VIP
        │  abre api_endpoint?session_id=&token=
        ▼
game/xiaoxiaole/  (recursos estáticos, Nginx)
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

O jogo é um **frontend estático**; a sessão autoritativa e o dinheiro ficam em `service/`. O cliente mantém o estado do tabuleiro; o servidor mantém o saldo e a idempotência do round. Na primeira fase não há validação passo a passo no servidor, mas a camada de domínio deve ser determinística, para que na segunda fase `seed + sequência de operações` possa ser enviado ao servidor para recálculo.

---

## 2. Lista de interfaces

| Interface | Método | Direção | Observação |
|------|------|------|------|
| `/api/game/launch` | POST | plataforma → service | Inicia a sessão do jogo, retorna `session_id, api_endpoint, type=self` |
| `/api/provider/balance` | GET | jogo → service | Consulta o saldo da moeda de jogo |
| `/api/provider/bet` | POST | jogo → service | Deduz a taxa de entrada ao iniciar uma fase |
| `/api/provider/settle` | POST | jogo → service | Credita o prêmio ao concluir a fase |
| `/api/provider/refund` | POST | jogo → service | Reembolsa ao sair sem dar o primeiro passo |

O lado do jogo chama `/api/provider/*` através do `PlatformAdapter`, com assinatura HMAC/JWT.

---

## 3. Fluxo de inicialização

1. A plataforma `POST /api/game/launch` retorna `session_id, api_endpoint, type=self`.
2. Abrir `api_endpoint?session_id=&token=` (o token é um ticket de jogo de curta duração, ou reutiliza o JWT).
3. O jogo faz `GET /api/provider/balance` para exibir a moeda de jogo.
4. O jogador clica em "Iniciar esta fase" → `POST /api/provider/bet`, `round_id = session_id + ':' + levelId + ':' + attempt`.
5. Domínio `seed = hash(session_id + round_id)`.
6. Ao concluir, `settle`; ao falhar, não faz settle; ao sair sem operar, `refund`.

---

## 4. Envio do play-log

`launch` (já existente) + o lado do jogo envia os seguintes eventos (pode gravar no ClickHouse `GamePlayLogService` primeiro):

| Evento | Momento |
|------|------|
| `level_start` | Entrar na fase |
| `level_win` | Concluir a fase |
| `level_fail` | Falhar |
| `skill_use` | Usar habilidade |

---

## 5. Feature flags

| Flag | Padrão | Observação |
|------|------|------|
| `xxl.eco_chain` | on | Cadeia de restrição ecológica |
| `xxl.elephant` | off | Regra do elefante |
| `xxl.skills` | on | Habilidades de ferramentas agrícolas |
| `xxl.entry_bet` | off | Taxa de entrada/carteira |

Quando desligadas, as fases regridem para o três-em-linha puro do mesmo tipo, facilitando o lançamento em lotes.

---

## 6. Carteira e idempotência do round

- `SelfProvider::bet/settle/refund` já existe; o jogo chama por `round_id`; definir um teto de prêmio por round.
- Cada round só faz bet/settle uma vez; sessão com timeout é invalidada; pontuação anormalmente alta apenas é registrada em log, sem crédito automático (pode configurar teto de settle).
- Em caso de falha, a taxa de entrada não é reembolsada; sair sem trocar nenhuma peça → `refund`.

---

## 7. Segunda fase: recálculo no servidor

Enviar a sequência de operações; o servidor executa o mesmo `domain` em portabilidade PHP ou worker Node para recálculo (`seed + sequência de operações` → validar tabuleiro e pontuação).
