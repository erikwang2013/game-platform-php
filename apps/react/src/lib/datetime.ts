/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 时间戳展示格式化。
 *
 * ⚠ 这不是「美化」，是修一个**8 小时时差**。同一批 C 端接口回的 `created_at` / `start_at`
 * 有**两种线格式**，取决于控制器有没有自己 format 过：
 *
 *  1. **原样透传模型属性**的（`User`/`Ticket`/`app\model\Friend`/`DepositOrder`/`WithdrawOrder`/
 *     `Tournament` …，`$timestamps` 为真 ⇒ Eloquent 把属性转成 Carbon）⇒ `json_encode(Carbon)`
 *     出的是 **ISO8601 UTC**：本机 +08:00 的 `2026-10-01 10:00:00` 在报文里是
 *     `"2026-10-01T02:00:00.000000Z"`（实测，非推断）。直接贴到 DOM 上就是差 8 小时的机器串。
 *     代表调用点：`UserController.php:51`、`TicketController.php:48,97`、`FriendController.php:46`、
 *     `DepositController.php:154-155`、`WithdrawController.php:270,302`、`TournamentController` 全部时间字段。
 *  2. **本地墙钟串**的：控制器自己 `->format('Y-m-d H:i:s')`（如 `AnnouncementController.php:39,64`），
 *     或模型 `$timestamps = false` 且写入侧手写 `date('Y-m-d H:i:s')`
 *     （`Notification`/`TicketReply`/`ExchangeRecord`/`Transaction`/`Message`/`GamePlayLog` 的 `created_at`）。
 *     ⚠ **同一个模型可以两种都有**，要按「哪个字段」而不是「哪个模型」记：`GamePlayLog` 的 `created_at`
 *     是墙钟串，但 detail 端点另回的 `started_at`/`ended_at` 在 `$casts` 里是 datetime ⇒ ISO。
 *
 * 两种都先交给 `Date` 解析再按**本机时区**渲染：
 * `"2026-01-01T00:00:00"`（无时区标记）按 ES 规范就是**本地时间**，故第 2 类行为不变；
 * 带 `Z` 的第 1 类才发生真正的时区换算。解析不出来时**原样返回**，不退化成 `Invalid Date`。
 *
 * 金额/时间一律不做本地运算，这里只做展示，与 `apps/angular` 的 `dt()`（`core/session.ts:65`）同口径。
 */
export function dt(value: string | null | undefined): string {
  if (!value) return '—';
  const parsed = new Date(value.replace(' ', 'T'));
  return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleString();
}
