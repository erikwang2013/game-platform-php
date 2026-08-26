# Pastoral Match-3 — Platform Integration API
<!-- lang-nav -->

Languages: [中文](api.md) · **English** · [한국어](api.ko.md) · [Русский](api.ru.md) · [Deutsch](api.de.md) · [Français](api.fr.md) · [Español](api.es.md) · [Português](api.pt.md) · [हिन्दी](api.hi.md) · [العربية](api.ar.md) · [বাংলা](api.bn.md) · [Bahasa Indonesia](api.id.md) · [日本語](api.ja.md)


> This document is the complete interface contract between Pastoral Match-3 and the game platform. For the technical layering see `architecture.md`, for scheduling see `plan.md`, and for player features see `functional-design.md`.

---

## 1. Launch Chain

```
Flutter / HarmonyOS / PC Web
        │  POST /api/game/launch { game_id }
        ▼
service/ (webman :8788)
  GameController::launch  → session_id + seed + api_endpoint
  SelfProvider            → bet / settle / refund / getBalance
  GamePlayLog + EventBus  → game.played / 成就 / VIP
        │  打开 api_endpoint?session_id=&token=
        ▼
game/xiaoxiaole/  (静态资源，Nginx)
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

The game is a **static frontend**; the authoritative session and money live in `service/`. The client holds the board state; the server holds the balance and round idempotency. Phase 1 does not do per-move server-side validation, but the domain layer must be deterministic so that phase 2 can send `seed + 操作序列` to the server for recomputation.

---

## 2. Interface List

| Interface | Method | Direction | Description |
|------|------|------|------|
| `/api/game/launch` | POST | Platform → service | Launch game session, returns `session_id, api_endpoint, type=self` |
| `/api/provider/balance` | GET | Game → service | Query game currency balance |
| `/api/provider/bet` | POST | Game → service | Level start entry fee deduction |
| `/api/provider/settle` | POST | Game → service | Level clear settlement reward |
| `/api/provider/refund` | POST | Game → service | Exit refund if the first step was never taken |

The game side calls `/api/provider/*` through `PlatformAdapter`, signed with HMAC/JWT.

---

## 3. Launch Flow

1. The platform `POST /api/game/launch` returns `session_id, api_endpoint, type=self`.
2. Open `api_endpoint?session_id=&token=` (token is a short-lived game ticket, or reuse the JWT).
3. The game `GET /api/provider/balance` shows the game currency.
4. The player taps "Start this level" → `POST /api/provider/bet`, `round_id = session_id + ':' + levelId + ':' + attempt`.
5. Domain `seed = hash(session_id + round_id)`.
6. Clear the level → `settle`; fail → no settle; exit without acting → `refund`.

---

## 4. Play-log Reporting

`launch` (already exists) + the game side reports the following events (can be written to ClickHouse `GamePlayLogService` first):

| Event | Timing |
|------|------|
| `level_start` | Enter level |
| `level_win` | Level cleared |
| `level_fail` | Failed |
| `skill_use` | Skill used |

---

## 5. Feature Flags

| Flag | Default | Description |
|------|------|------|
| `xxl.eco_chain` | on | Ecology restraint chain |
| `xxl.elephant` | off | Elephant rule |
| `xxl.skills` | on | Farm tool skills |
| `xxl.entry_bet` | off | Entry fee/wallet |

When off, the level degenerates to a pure match-3 of identical tiles, for phased rollout.

---

## 6. Wallet and Round Idempotency

- `SelfProvider::bet/settle/refund` already exist; the game calls them by `round_id`; set a per-round payout cap.
- A round can only bet/settle once; timed-out sessions are invalidated; abnormally high scores are only logged, not auto-paid (a settle cap can be set).
- No entry fee refund on failure; exiting before any tile was swapped → `refund`.

---

## 7. Phase 2: Server-Side Recalculation

Upload the operation sequence; the server reruns the same `domain` ported to PHP or a Node worker to recompute (`seed + 操作序列` → verify board and score).
