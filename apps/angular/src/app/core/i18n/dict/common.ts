/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * **跨页共用**的界面词（主体是资金域四页；`common.captcha_load_failed` 是全树共用；
 * B2 又加了 `back`/`cancel`/`close`/`confirm`/`copied` 五条通用动作词）。
 *
 * 建这个文件而不是各页各写一份的理由：`余额`/`订单号`/`返回钱包`/`重试` 这些在
 * wallet/deposit/withdraw/exchange 四页里反复出现，各写一份就是同一个概念有 N 个键，
 * 翻错一处看不出来（而且 13 张表要跟着同步 N 份）。
 *
 * ⚠ `common.balance`（`余额`）与 `common.account_balance`（`账户余额`）**是两条**：
 * 前者是流水行里 `· 余额 1,234.00` 的后缀，后者是详情页里独立的一格标题，语境不同。
 */
export const COMMON: Record<string, [string, string]> = {
  'common.frozen': ['Frozen', '冻结'],
  'common.unfrozen': ['Unfrozen', '解冻'],
  'common.balance': ['balance', '余额'],
  'common.account_balance': ['Account balance', '账户余额'],
  'common.game_coin': ['game coins', '游戏币'],
  'common.platform': ['platform', '平台'],
  'common.platform_coin': ['platform coins', '平台币'],
  'common.rate': ['Rate', '汇率'],
  'common.spread': ['Spread', '点差'],
  'common.spread_fee': ['Spread fee', '点差费用'],
  'common.order_no': ['Order no.', '订单号'],
  'common.back_wallet': ['Back to wallet', '返回钱包'],
  'common.retry': ['Retry', '重试'],
  'common.loading': ['Loading…', '加载中…'],
  'common.load_failed': ['Failed to load', '加载失败'],
  // 验证码是**独立组件**（`core/captcha.ts`），它的加载失败在充值/提现/登录三处都会出现，
  // 所以放 common 而不是各页一份（与 `common.load_failed` 是两条：后者是「整页加载失败」）
  'common.captcha_load_failed': ['CAPTCHA failed to load', '验证码加载失败'],
  'common.submitting': ['Submitting…', '提交中…'],
  'common.no_records': ['No records yet', '暂无记录'],
  'common.unlimited': ['No limit', '不限'],
  'common.cancelled': ['Cancelled', '已取消'],
  'common.failed': ['Failed', '失败'],
  'common.completed': ['Completed', '已完成'],

  // —— B2 身份安全域 ——
  'common.confirm': ['Confirm', '确认'],
  'common.back': ['Back', '返回'],
  'common.cancel': ['Cancel', '取消'],
  'common.close': ['Close', '关闭'],
  'common.copied': ['Copied', '已复制'],

  // —— C 传输层文案（前端本地判定的那几句；未迁移读点棘轮盯着） ——
  'error.request_failed': ['Request failed', '请求失败'],
  'error.network_connection': ['Could not connect. Check your network and try again', '无法连接服务器，请稍后重试'],
  'error.request_failed_http': ['Request failed (HTTP {status})', '请求失败 (HTTP {status})'],
  'upload.failed_retry': ['Upload failed, please retry', '上传失败，请重试'],
  'upload.no_path': ['Upload finished but the server returned no path — please retry', '上传完成但服务端未返回路径，请重试'],
  'upload.failed_http': ['Upload failed (HTTP {status})', '上传失败 (HTTP {status})'],
  // C-2：401 信封那一支（改了 upload.spec.ts 的一条既有断言）
  'upload.session_expired': ['Your session has expired — sign in again before uploading', '登录状态已失效，请重新登录后再上传'],
  // —— C 批（playlogs 页）——
  // 「刷新」是 7 个页面的同一个按钮（playlogs 本批已迁；chat-room:39 / friends:39 /
  // announcements:24 / leaderboard:36 / activities:60 / tournaments:44 还写着硬编码中文，
  // 各自那批直接复用本键 —— 7 处各写一份就是同一个词有 7 个键，翻错一处看不出来）
  'common.refresh': ['Refresh', '刷新'],
  // —— C 批（tournaments 页）：`查看` 是列表行的徽标词，别处也会用到 ——
  'common.view': ['View', '查看'],
  // —— C 批（tickets 页）：两个按钮词，别处也会用到 ——
  'common.send': ['Send', '发送'],
  'common.sending': ['Sending…', '发送中…'],
};
