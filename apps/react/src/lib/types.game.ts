/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 游戏域的形状：游戏目录（`Game`/`Currency`）与游戏资产、战绩流水（`GameWallet`/`PlayLog`）。
 * 2026-10-01 从 types.ts 拆出 —— 那份已顶到 500 行上限，而这一域自成闭环（不引用其它域的类型）。
 * types.ts 顶部 `export type * from './types.game.ts'` 把它并回同一出口，消费者的 import 不用改。
 */

export interface Currency {
  id: string;
  name: string;
  symbol: string;
  exchange_rate?: string;
  spread_pct?: string;
  min_exchange?: string;
  max_exchange?: string;
}

export interface Game {
  id: string;
  name: string;
  slug: string;
  type: string;
  description: string | null;
  cover_image: string | null;
  sdk_version?: string;
  platform?: string;
  region?: string;
  currencies?: Currency[];
  // 后端只回 name/slug（无分类 hashid），故不支持按分类筛选
  categories?: { name: string; slug: string }[];
  api_endpoint?: string | null;
}

/** /game/balance：按游戏聚合的游戏币余额（平台币余额在 /wallet/info） */
export interface GameWallet {
  game_id: string;
  name: string;
  slug: string;
  type: string;
  currencies: Array<{
    currency_id: string;
    name: string;
    symbol: string;
    balance: string;
    frozen_balance: string;
  }>;
}

/**
 * `/game/play-logs` 的行（`GamePlayLogController::list:47-58`）。
 * 与钱包流水是**两本账**：`/wallet/transactions` 记平台币，这里记游戏币。
 * `game_amount_change` 是带符号的十进制串（正=赚、负=花），本树只判符号位，不做数值运算。
 * `action` 的取值见 MyGames.tsx 的 ACTION_LABEL 注释（两个来源合起来才是全集）。
 */
export interface PlayLog {
  id: string;
  game_id: string;
  /** 服务端写成 `$log->server_id ? encodeId(..) : null`，而列是 NOT NULL DEFAULT 0 ⇒ 0 变成 null */
  server_id: string | null;
  session_id: string;
  action: string;
  game_amount_before: string;
  game_amount_change: string;
  game_amount_after: string;
  created_at: string;
}

/**
 * `/game/play-log/{hashid}`（`GamePlayLogController::detail:87-102`）：`PlayLog` 的超集。
 *
 * ⚠ **同一个报文里两种时间格式并存**（这是本接口最容易踩的地方）：
 *  - `created_at` 是**墙钟串** —— 它没有 cast，模型又是 `$timestamps = false`，控制器原样透传；
 *  - `started_at`/`ended_at` 在模型 `$casts` 里是 `datetime` ⇒ 读回是 Carbon ⇒ `json_encode`
 *    出 **ISO8601 UTC**。两个字段都可能是 null（`ended_at` 只有带 meta 的回调才写）。
 *  三个都必须过 `dt()`，直接贴 DOM 上会差 8 小时。
 *
 * `metadata` 是**没 cast 的 TEXT 列**（列注释「游戏自定义数据(JSON)」），控制器原样透传 ⇒
 * 拿到的是写侧 `json_encode` 的原始字符串（可能为 null），**不是对象**。
 * 写入侧两个：`GamePlayLogService::write:45`（含明文 `ip`/`user_agent`，脱敏列只存 sha256）
 * 与 `GamePlayRecorder::record:46`（游戏回调的 meta 原样）。本树只做缩进展示，不猜字段名。
 */
export interface PlayLogDetail extends PlayLog {
  user_id: string;
  platform_amount_change: string;
  metadata: string | null;
  started_at: string | null;
  ended_at: string | null;
}
