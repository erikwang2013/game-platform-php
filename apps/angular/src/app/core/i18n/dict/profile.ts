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
  // —— 「我的」页（`pages/me.html` / me-nick / me-export）——
  'me.registered_at': ['Registered {date}', '注册于 {date}'],
  'me.tiles_label': ['Quick actions', '常用功能'],
  'me.no_notices_title': ['No messages yet', '暂无消息'],
  'me.no_notices_hint': [
    'Announcements and account notices appear here',
    '平台公告与账户通知会出现在这里',
  ],
  // 注销区。`me.del_title` 一处两用：区块标题与那句按钮（中文原文就是同一个词）
  'me.del_title': ['Delete account', '注销账号'],
  'me.del_irreversible': ['Irreversible', '不可撤销'],
  'me.del_hint': [
    'Once deleted the account can no longer sign in and the profile is anonymised. Any balance must be withdrawn to zero first — otherwise the server rejects the deletion.',
    '注销后该账号无法再登录，个人资料将被匿名化。账号内余额需先自行提现清零，否则服务端会拒绝注销。',
  ],
  'me.del_password': ['Current password', '当前密码'],
  'me.del_confirm': ['Confirm deletion (type yes)', '确认注销（输入 yes）'],
  'me.del_busy': ['Deleting…', '注销中…'],
  'me.del_submit': ['Confirm deletion', '确认注销'],
  // 改昵称弹框（`pages/me-nick.ts`）。「改昵称」与「修改昵称」在原硬编码里就是两个不同的串
  'me.nick_open': ['Change nickname', '改昵称'],
  'me.nick_title': ['Change nickname', '修改昵称'],
  'me.nick_label': ['Nickname (max 50 characters)', '昵称（最长 50 字）'],
  'me.nick_ph': ['Enter a nickname', '请输入昵称'],
  'me.nick_saving': ['Saving…', '保存中…'],
  // 导出我的数据（`pages/me-export.ts`）。那段说明里有三处内联标记（`<b>`），按标记切段存
  'me.export_title': ['Download my data', '下载我的数据'],
  'me.export_hint_a': [
    'The server packages your account profile, your platform-coin wallet (balance and lifetime totals), the latest 100 transactions / exchanges / deposits / withdrawals and your linked third-party accounts into one JSON file. ',
    '服务端把账号资料、平台币钱包（余额与累计收支）、最近 100 条流水 / 兑换 / 充值 / 提现，以及已绑定的第三方账号打包成一份 JSON。',
  ],
  'me.export_hint_b': ['Each category is capped at 100 records', '每类明细上限 100 条'],
  'me.export_hint_c': [', not the full history; ', '，不是全部历史；'],
  'me.export_hint_d': [
    'game-coin balances are not in this file',
    '游戏币余额不在这份文件里',
  ],
  'me.export_hint_e': [
    ' (the export reads only the platform-coin wallet and never touches game wallets).',
    '（导出只读平台币钱包，不碰游戏钱包）。',
  ],
  // 四类明细的行数摘要（`core/export-data.ts` 的 exportCounts）。
  // ⚠ 四个计数**不相加成一个数**：相加看不出缺哪一类，且四类上限都是 100。
  'me.export_counts': [
    '{transactions} transactions · {exchanges} exchanges · {deposits} deposits · {withdrawals} withdrawals',
    '流水 {transactions} · 兑换 {exchanges} · 充值 {deposits} · 提现 {withdrawals}',
  ],
  'me.export_busy': ['Exporting…', '导出中…'],
  'me.export_download': ['Download JSON', '下载 JSON'],
};
