# 田园消消乐 — API de integración con la plataforma
<!-- lang-nav -->

Languages: [中文](api.md) · [English](api.en.md) · [한국어](api.ko.md) · [Русский](api.ru.md) · [Deutsch](api.de.md) · [Français](api.fr.md) · **Español** · [Português](api.pt.md) · [हिन्दी](api.hi.md) · [العربية](api.ar.md) · [বাংলা](api.bn.md) · [Bahasa Indonesia](api.id.md) · [日本語](api.ja.md)


> Este documento es el contrato completo de interfaces entre 《田园消消乐》 y la plataforma de juegos. La división técnica está en `architecture.md`, la planificación en `plan.md`, y las funcionalidades para jugadores en `functional-design.md`.

---

## 1. Cadena de inicio

```
Flutter / HarmonyOS / PC Web
        │  POST /api/game/launch { game_id }
        ▼
service/ (webman :8788)
  GameController::launch  → session_id + seed + api_endpoint
  SelfProvider            → bet / settle / refund / getBalance
  GamePlayLog + EventBus  → game.played / logros / VIP
        │  abrir api_endpoint?session_id=&token=
        ▼
game/xiaoxiaole/  (recursos estáticos, Nginx)
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

El juego es un **frontend estático**; la sesión autoritativa y el dinero están en `service/`. El cliente mantiene el estado del tablero; el servidor mantiene el saldo y la idempotencia del round. En la primera fase no se hace validación servidor a servidor por paso, pero la capa de dominio debe ser determinista para que en la segunda fase se pueda enviar `seed + secuencia de operaciones` al servidor para recalcular.

---

## 2. Lista de interfaces

| Interfaz | Método | Dirección | Descripción |
|------|------|------|------|
| `/api/game/launch` | POST | plataforma → service | Inicia la sesión de juego, devuelve `session_id, api_endpoint, type=self` |
| `/api/provider/balance` | GET | juego → service | Consulta el saldo de moneda de juego |
| `/api/provider/bet` | POST | juego → service | Cobra la cuota de entrada al abrir el nivel |
| `/api/provider/settle` | POST | juego → service | Entrega la recompensa al completar el nivel |
| `/api/provider/refund` | POST | juego → service | Reembolso al salir sin haber dado el primer paso |

Las llamadas del juego a `/api/provider/*` pasan por `PlatformAdapter`, con firma HMAC/JWT.

---

## 3. Flujo de inicio

1. La plataforma `POST /api/game/launch` devuelve `session_id, api_endpoint, type=self`.
2. Abrir `api_endpoint?session_id=&token=` (el token es un ticket de juego de corta duración, o se reutiliza el JWT).
3. El juego hace `GET /api/provider/balance` para mostrar la moneda de juego.
4. El jugador pulsa «Empezar este nivel» → `POST /api/provider/bet`, con `round_id = session_id + ':' + levelId + ':' + attempt`.
5. Dominio: `seed = hash(session_id + round_id)`.
6. Al completar el nivel se hace `settle`; si se falla no se hace settle; si se sale sin operar se hace `refund`.

---

## 4. Reporte de play-log

`launch` (ya existente) + el juego reporta los siguientes eventos (se puede escribir primero en ClickHouse mediante `GamePlayLogService`):

| Evento | Momento |
|------|------|
| `level_start` | Al entrar al nivel |
| `level_win` | Al completar el nivel |
| `level_fail` | Al fallar |
| `skill_use` | Al usar una habilidad |

---

## 5. Feature flags (FeatureFlag)

| Interruptor | Por defecto | Descripción |
|------|------|------|
| `xxl.eco_chain` | on | Cadena de contención del ecosistema |
| `xxl.elephant` | off | Regla del elefante |
| `xxl.skills` | on | Habilidades de herramientas agrícolas |
| `xxl.entry_bet` | off | Cuota de entrada/billetera |

Cuando están desactivados, el nivel se degrada a un match-3 puro del mismo tipo, para facilitar el despliegue por fases.

---

## 6. Billetera e idempotencia de round

- `SelfProvider::bet/settle/refund` ya existe; el juego los llama según `round_id`; se establece un tope de premio por round.
- Cada round solo hace bet/settle una vez; las sesiones con timeout se invalidan; las puntuaciones anormalmente altas solo se registran en el log y no se entregan automáticamente (se puede configurar el tope de settle).
- Si se falla no se devuelve la cuota de entrada; si se sale sin haber movido ni una ficha → `refund`.

---

## 7. Segunda fase: recálculo en el servidor

Subir la secuencia de operaciones y que el servidor ejecute el mismo `domain` (trasplante a PHP o worker Node) para recalcular (`seed + secuencia de operaciones` → validar tablero y puntuación).
