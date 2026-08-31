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

### Contrato do campo `meta` (compartilhado por `bet` / `settle`, H5 anti-cheat)

Definições do campo `meta` (objeto) no corpo das requisições `POST /api/provider/bet` e `POST /api/provider/settle`:

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| `device_id` | string | Não | ID do dispositivo (armazenado em texto puro no servidor, usado para agregação por dispositivo) |
| `result` | string | Obrigatório no settle | Resultado da partida: `win` / `fail` |
| `move_count` | int | Não | Número de jogadas desta partida (entrada para detecção de frequência de jogadas) |
| `ended_at` | string | Não | Horário de término da partida `YYYY-MM-DD HH:MM:SS` |
| `level_id` | int | Não | ID do nível |
| `ip` | string | Não | IP de origem do jogador (o lado do jogo encaminha o IP real; o servidor só armazena o sha256 como `ip_hash`, nunca em texto puro) |
| `user_agent` | string | Não | User-Agent do jogador (o servidor só armazena o sha256 como `user_agent_hash`) |

Ao persistir em `game_game_play_log`: `result / move_count / ended_at_round / device_id / level_id` vão para colunas independentes; `ip` / `user_agent` são armazenados com hash em `ip_hash` / `user_agent_hash`; `meta` é salvo como está em `metadata` (JSON).

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
