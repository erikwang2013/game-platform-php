# 田园消消乐 — プラットフォーム接続 API
<!-- lang-nav -->

Languages: **中文** · [English](api.en.md) · [한국어](api.ko.md) · [Русский](api.ru.md) · [Deutsch](api.de.md) · [Français](api.fr.md) · [Español](api.es.md) · [Português](api.pt.md) · [हिन्दी](api.hi.md) · [العربية](api.ar.md) · [বাংলা](api.bn.md) · [Bahasa Indonesia](api.id.md) · [日本語](api.ja.md)


> 本文は《田园消消乐》と游戏プラットフォーム間の全インターフェース契約です。技術レイヤーは `architecture.md`、スケジュールは `plan.md`、プレイヤー機能は `functional-design.md` を参照。

---

## 1. 起動チェーン

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
game/xiaoxiaole/  (静的リソース，Nginx)
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

游戏は**静的前端**であり、権威的なセッションと残高は `service/` にあります。クライアントは盤面状態を保持し、サーバーは残高と round の冪等性を保持します。第一期では毎手のサーバー側検証は行いませんが、領域層は決定論的でなければならず、第二期で `seed + 操作序列` をサーバーに送って再計算できるようにします。

---

## 2. インターフェース一覧

| インターフェース | メソッド | 方向 | 説明 |
|------|------|------|------|
| `/api/game/launch` | POST | 平台 → service | 游戏会话を起動し、`session_id, api_endpoint, type=self` を返す |
| `/api/provider/balance` | GET | 游戏 → service | ゲームコイン残高の照会 |
| `/api/provider/bet` | POST | 游戏 → service | 关卡开局の入場料引き落とし |
| `/api/provider/settle` | POST | 游戏 → service | 通关決済の賞金支払い |
| `/api/provider/refund` | POST | 游戏 → service | 第一步未着手で退出した場合の返金 |

游戏側の `/api/provider/*` 呼び出しは `PlatformAdapter` を経由し、HMAC/JWT 署名を行います。

---

## 3. 起動フロー

1. 平台の `POST /api/game/launch` が `session_id, api_endpoint, type=self` を返す。
2. `api_endpoint?session_id=&token=` を開く（token は短期ゲームチケット、または JWT を再利用）。
3. 游戏が `GET /api/provider/balance` でゲームコインを表示。
4. プレイヤーが「本关を開始」をクリック → `POST /api/provider/bet`、`round_id = session_id + ':' + levelId + ':' + attempt`。
5. 領域 `seed = hash(session_id + round_id)`。
6. 通关で `settle`、失敗時は settle しない；未操作で退出した場合は `refund`。

---

## 4. Play-log の報告

`launch`（既存）+ 游戏側から以下のイベントを報告（まず ClickHouse `GamePlayLogService` に打てる）：

| イベント | タイミング |
|------|------|
| `level_start` | 关卡への突入 |
| `level_win` | 通关 |
| `level_fail` | 失敗 |
| `skill_use` | スキル使用 |

---

## 5. フィーチャーフラグ（FeatureFlag）

| フラグ | デフォルト | 説明 |
|------|------|------|
| `xxl.eco_chain` | on | 生態克制チェーン |
| `xxl.elephant` | off | 大象ルール |
| `xxl.skills` | on | 農具スキル |
| `xxl.entry_bet` | off | 入場費/ウォレット |

フラグがオフのとき关卡は純粋な同種三消に退化し、段階的なリリースが容易になります。

---

## 6. ウォレットと round の冪等性

- `SelfProvider::bet/settle/refund` は既存、游戏は `round_id` で呼び出す；単 round の賞金上限を設定。
- 単 round は bet/settle を一度だけ行う；タイムアウトした session は無効化；異常な高得点はログのみ記録し自動派奖はしない（settle 上限を設定可）。
- 失敗時は入場費を返さない；一つも入れ替えずに退出した場合 → `refund`。

---

## 7. 第二期：サーバー側再計算

操作序列をアップロードし、サーバー側で同一 `domain` の PHP 移植または Node worker で再計算（`seed + 操作序列` → 盤面とスコアを検証）。
