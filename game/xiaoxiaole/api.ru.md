# 田园消消乐 — API подключения к платформе
<!-- lang-nav -->

Languages: **中文** · [English](api.en.md) · [한국어](api.ko.md) · [Русский](api.ru.md) · [Deutsch](api.de.md) · [Français](api.fr.md) · [Español](api.es.md) · [Português](api.pt.md) · [हिन्दी](api.hi.md) · [العربية](api.ar.md) · [বাংলা](api.bn.md) · [Bahasa Indonesia](api.id.md) · [日本語](api.ja.md)


> Этот документ — полный контракт интерфейсов между «田园消消乐» и игровой платформой. Техническая декомпозиция — в `architecture.ru.md`, расписание — в `plan.ru.md`, функции для игроков — в `functional-design.ru.md`.

---

## 1. Цепочка запуска

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

Игра — это **статический фронтенд**: авторитетная сессия и деньги находятся в `service/`. Клиент хранит состояние доски; сервер хранит баланс и идемпотентность раундов. В первой фазе серверной валидации каждого хода нет, но слой домена обязан быть детерминированным, чтобы во второй фазе отправлять `seed + последовательность операций` на сервер для пересчёта.

---

## 2. Перечень интерфейсов

| Интерфейс | Метод | Направление | Описание |
|------|------|------|------|
| `/api/game/launch` | POST | платформа → service | запуск игровой сессии, возвращает `session_id, api_endpoint, type=self` |
| `/api/provider/balance` | GET | игра → service | запрос баланса игровой валюты |
| `/api/provider/bet` | POST | игра → service | списание платы за вход при старте уровня |
| `/api/provider/settle` | POST | игра → service | выплата награды при прохождении |
| `/api/provider/refund` | POST | игра → service | возврат при выходе до первого хода |

Вызовы `/api/provider/*` со стороны игры идут через `PlatformAdapter` с подписью HMAC/JWT.

---

## 3. Процесс запуска

1. Платформа `POST /api/game/launch` возвращает `session_id, api_endpoint, type=self`.
2. Открывается `api_endpoint?session_id=&token=` (token — краткосрочный игровой билет или повторное использование JWT).
3. Игра запрашивает `GET /api/provider/balance` и показывает игровую валюту.
4. Игрок нажимает «Начать уровень» → `POST /api/provider/bet`, `round_id = session_id + ':' + levelId + ':' + attempt`.
5. Домен: `seed = hash(session_id + round_id)`.
6. Прохождение — `settle`; провал — без `settle`; выход без действий — `refund`.

---

## 4. Отправка play-log

`launch` (уже есть) + игра отправляет следующие события (можно сразу в ClickHouse через `GamePlayLogService`):

| Событие | Момент |
|------|------|
| `level_start` | вход в уровень |
| `level_win` | прохождение |
| `level_fail` | провал |
| `skill_use` | использование навыка |

### Контракт поля `meta` (общий для `bet` / `settle`, H5 античит)

Определения поля `meta` (объект) в теле запросов `POST /api/provider/bet` и `POST /api/provider/settle`:

| Поле | Тип | Обязательно | Описание |
|------|------|------|------|
| `device_id` | string | Нет | ID устройства (хранится на сервере в открытом виде, используется для агрегации по устройствам) |
| `result` | string | Обязательно для settle | Результат раунда: `win` / `fail` |
| `move_count` | int | Нет | Число ходов в этом раунде (вход для детекции частоты ходов) |
| `ended_at` | string | Нет | Время окончания раунда `YYYY-MM-DD HH:MM:SS` |
| `level_id` | int | Нет | ID уровня |
| `ip` | string | Нет | Исходный IP игрока (игровая сторона передаёт реальный IP; сервер сохраняет только sha256 как `ip_hash`, не в открытом виде) |
| `user_agent` | string | Нет | User-Agent игрока (сервер сохраняет только sha256 как `user_agent_hash`) |

При сохранении на сервере в `game_game_play_log`: `result / move_count / ended_at_round / device_id / level_id` — в отдельные колонки; `ip` / `user_agent` хешируются в `ip_hash` / `user_agent_hash`; `meta` сохраняется как есть в `metadata` (JSON).

---

## 5. Функциональные переключатели (FeatureFlag)

| Переключатель | По умолчанию | Описание |
|------|------|------|
| `xxl.eco_chain` | on | цепочка экологической «сдержанности» |
| `xxl.elephant` | off | правило слона |
| `xxl.skills` | on | навыки-инструменты |
| `xxl.entry_bet` | off | плата за вход / кошелёк |

При выключении уровень вырождается в чистый три-в-ряд одного типа, что удобно для поэтапного запуска.

---

## 6. Кошелёк и идемпотентность раундов

- `SelfProvider::bet/settle/refund` уже существуют; игра вызывает их по `round_id`; задаётся верхний предел выплаты за раунд.
- Один раунд — только один `bet`/`settle`; сессия по таймауту аннулируется; аномально высокий счёт только логируется, без автоматической выплаты (можно установить лимит `settle`).
- При провале плата за вход не возвращается; выход без единой перестановки → `refund`.

---

## 7. Вторая фаза: пересчёт на сервере

Загрузка последовательности операций; сервер прогоняет тот же `domain` через PHP-порт или Node-worker (пересчёт по `seed + последовательность операций` → проверка доски и очков).
