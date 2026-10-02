/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 我的域：资料导出结果、注销结果、昵称表单校验。
 *
 * **这三条为什么需要键**：同 `identity.ts` —— 都是 `.then/.catch` 里 `set(…)` 的异步文案。
 *
 * ⚠ `me.export_done` 有三个占位符，且**三个都是外部值不只是文本**：
 * `{name}` 是文件名（服务端拼的）、`{at}` 是服务端生成时间的**本地化格式**（`dt()` 出的）、
 * `{counts}` 是各表行数摘要（`exportCounts()` 出的）。所以这一处**只存键 + 三个参数**，
 * 不能存拼好的句子 —— 存了就是「导出那一刻的语言」冻在屏幕上。
 *
 * G2 追加两条**昵称表单**的本地校验文案（`pages/me-nick.ts`）：与上面三条同一个病 ——
 * 字面量在 `set()` 那一刻成文，切语言时那一行不跟着变。
 */
export const PROFILE: Record<string, [string, string]> = {
  'me.export_done': [
    'Exported {name} (server-generated {at}) · {counts}',
    '已导出 {name}（服务端生成于 {at}）· {counts}',
  ],
  'me.del_pending': [
    'Deletion requested — profile data is still readable, refresh to confirm',
    '注销请求已提交，但账号资料仍可读取，请刷新后确认',
  ],
  'me.del_unknown': [
    'Deletion result unconfirmed: {msg}',
    '注销结果无法确认：{msg}',
  ],
  'me.del_hint_relogin': [
    '{msg} (your session may have expired — sign in again and retry)',
    '{msg}（登录状态可能已失效，请重新登录后再试）',
  ],
  'me.err_nick_required': ['Please enter a nickname', '昵称不能为空'],
  'me.err_nick_too_long': ['Nickname must be at most 50 characters', '昵称不能超过 50 字'],
};
