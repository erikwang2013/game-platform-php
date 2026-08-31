# 田园消消乐 — 平台接入 API
<!-- lang-nav -->

Languages: **中文** · [English](api.en.md) · [한국어](api.ko.md) · [Русский](api.ru.md) · [Deutsch](api.de.md) · [Français](api.fr.md) · [Español](api.es.md) · [Português](api.pt.md) · [हिन्दी](api.hi.md) · [العربية](api.ar.md) · [বাংলা](api.bn.md) · [Bahasa Indonesia](api.id.md) · [日本語](api.ja.md)


> 本文是《田园消消乐》与游戏平台之间的全部接口契约。技术分层见 `architecture.md`，排期见 `plan.md`，玩家功能见 `functional-design.md`。

---

## 1. 启动链路

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

游戏是**静态前端**，权威会话与钱在 `service/`。客户端持有棋盘状态；服务端持有余额与 round 幂等。第一期不做每步服务端校验，但领域层必须确定性，以便第二期把 `seed + 操作序列` 送到服务端复算。

---

## 2. 接口清单

| 接口 | 方法 | 方向 | 说明 |
|------|------|------|------|
| `/api/game/launch` | POST | 平台 → service | 启动游戏会话，返回 `session_id, api_endpoint, type=self` |
| `/api/provider/balance` | GET | 游戏 → service | 查询游戏币余额 |
| `/api/provider/bet` | POST | 游戏 → service | 关卡开局扣入场费 |
| `/api/provider/settle` | POST | 游戏 → service | 通关结算发奖 |
| `/api/provider/refund` | POST | 游戏 → service | 未走第一步退出退费 |

游戏侧调用 `/api/provider/*` 走 `PlatformAdapter`，HMAC/JWT 签名。

---

## 3. 启动流程

1. 平台 `POST /api/game/launch` 返回 `session_id, api_endpoint, type=self`。
2. 打开 `api_endpoint?session_id=&token=`（token 为短时游戏票，或复用 JWT）。
3. 游戏 `GET /api/provider/balance` 展示游戏币。
4. 玩家点「开始本关」→ `POST /api/provider/bet`，`round_id = session_id + ':' + levelId + ':' + attempt`。
5. 领域 `seed = hash(session_id + round_id)`。
6. 通关 `settle`，失败不 settle；未操作退出 `refund`。

---

## 4. Play-log 上报

`launch`（已有）+ 游戏侧上报以下事件（可先打 ClickHouse `GamePlayLogService`）：

| 事件 | 时机 |
|------|------|
| `level_start` | 进入关卡 |
| `level_win` | 通关 |
| `level_fail` | 失败 |
| `skill_use` | 使用技能 |

### `meta` 字段契约（`bet` / `settle` 共用，反作弊 H5）

`POST /api/provider/bet` 与 `POST /api/provider/settle` 请求体中的 `meta`（对象）字段定义：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `device_id` | string | 否 | 设备 ID（服务端明文存储，用于设备维度聚合） |
| `result` | string | settle 必填 | 对局结果：`win` / `fail` |
| `move_count` | int | 否 | 本局出招次数（出招频率类检测输入） |
| `ended_at` | string | 否 | 对局结束时间 `YYYY-MM-DD HH:MM:SS` |
| `level_id` | int | 否 | 关卡 ID |
| `ip` | string | 否 | 玩家来源 IP（游戏侧转发真实 IP；服务端只落 sha256 = `ip_hash`，不存明文） |
| `user_agent` | string | 否 | 玩家 User-Agent（服务端只落 sha256 = `user_agent_hash`） |

服务端落库 `game_game_play_log`：`result / move_count / ended_at_round / device_id / level_id` 独立列，
`ip` / `user_agent` 哈希化后入 `ip_hash` / `user_agent_hash`，`meta` 原样存 `metadata`(JSON)。

---

## 5. 特性开关（FeatureFlag）

| 开关 | 默认 | 说明 |
|------|------|------|
| `xxl.eco_chain` | on | 生态克制链 |
| `xxl.elephant` | off | 大象规则 |
| `xxl.skills` | on | 农具技能 |
| `xxl.entry_bet` | off | 入场费/钱包 |

关闭时关卡退化为纯同种三消，便于分批上线。

---

## 6. 钱包与 round 幂等

- `SelfProvider::bet/settle/refund` 已有，游戏按 `round_id` 调用；设单 round 派奖上限。
- 单 round 只 bet/settle 一次；超时 session 作废；异常高分只记日志不自动派奖（可设 settle 上限）。
- 失败不退入场费；一颗子都没换就退出 → `refund`。

---

## 7. 第二期：服务端复算

上传操作序列，服务端跑同一 `domain` 的 PHP 移植或 Node worker 复算（`seed + 操作序列` → 校验棋盘与得分）。
