/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 工单域：新建工单表单的**本地校验文案**（`pages/tickets.ts` 的 `create()`）。
 *
 * **这四条为什么需要键**：同 `profile.ts` —— 都是 `formErr.set(…)` 里的字面量。
 * 这套校验是明知服务端也会 422 而先在本地挡一次的（省一次往返），所以它出现在**没人请求**
 * 的时刻：用户把标题留空按下「提交」，屏上那句话是本地拼的，服务端文案那套管不到它。
 *
 * 两条长度上限（200 / 5000）与输入框的 `maxlength` 同源（服务端 validator 也是这两个数）
 * —— 界面上输入框到不了上限，但**粘贴**能直接越过 `maxlength`，所以校验不能省。
 */
export const TICKETS: Record<string, [string, string]> = {
  'tickets.err_subject_required': ['Please enter a subject', '请填写标题'],
  'tickets.err_subject_too_long': ['Subject must be at most 200 characters', '标题不能超过 200 字'],
  'tickets.err_content_required': ['Please enter the details', '请填写详细说明'],
  'tickets.err_content_too_long': ['Details must be at most 5000 characters', '详细说明不能超过 5000 字'],
  // —— 页面本体（tickets 页）：除五条 own 外，全部逐字取自 react 树同格的 `ticket.*` ——
  // 「问题类型 / 标题 / 详细说明 / 追加回复 / 补充说明」这些标签两棵树本来就同句；
  // 类型与状态那 8 条也照 react 取（`status_waiting` 原译「已回复」是**错的**：那是用户回复后置上的状态，本批改成「待回复」，见报告）。
  'tickets.title': ['Support tickets', '客服工单'],
  'tickets.new': ['New ticket', '新建工单'],
  'tickets.submit': ['Submit ticket', '提交工单'],
  'tickets.field_type': ['Issue type', '问题类型'],
  'tickets.field_subject': ['Subject', '标题'],
  'tickets.subject_ph': ['Describe the issue in one line', '一句话说明问题'],
  'tickets.field_content': ['Details', '详细描述'],
  'tickets.content_ph': ['Include the order number, time and other details so we can locate it faster', '请附上订单号、时间等信息，便于定位'],
  'tickets.empty_title': ['No tickets yet', '还没有工单'],
  'tickets.empty_hint': ['Trouble with a deposit, a withdrawal or your account? Submit it here', '充值、提现、账号等遇到问题都可以在这里提交'],
  'tickets.reply_count': ['{count} replies', '{count} 条回复'],
  'tickets.detail_title': ['Ticket details', '工单详情'],
  'tickets.fallback_title': ['Ticket', '工单'],
  'tickets.author_admin': ['Support', '客服'],
  'tickets.author_me': ['Me', '我'],
  'tickets.closed_note': ['This ticket is closed and can no longer be replied to. Open a new ticket if you need more help.', '该工单已关闭，不能再回复。如需继续咨询请另开工单。'],
  'tickets.field_reply': ['Add a reply', '追加回复'],
  'tickets.reply_ph': ['Add more details…', '补充说明…'],
  'tickets.type_deposit': ['Deposit issue', '充值问题'],
  'tickets.type_withdraw': ['Withdrawal issue', '提现问题'],
  'tickets.type_game': ['Game issue', '游戏问题'],
  'tickets.type_account': ['Account issue', '账号问题'],
  'tickets.type_other': ['Other', '其他'],
  'tickets.status_open': ['Open', '待受理'],
  'tickets.status_waiting': ['Awaiting reply', '待回复'],
  'tickets.status_closed': ['Closed', '已关闭'],
};
