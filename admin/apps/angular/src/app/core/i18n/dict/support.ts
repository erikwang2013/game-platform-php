/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 工单与报表页词条（support.ts）。
 *
 * 键名沿用 `admin/apps/flutter` 的 translations.dart（那边有 `ticket.*` 与 `report.*` 两族同名页面）：
 * ticket.title / ticket.reply_content / ticket.reply_hint / ticket.admin_id / ticket.admin_id_hint /
 * ticket.detail / ticket.reply / ticket.assign / ticket.close / ticket.close_confirm_target /
 * ticket.no_data / report.title / report.summary 逐字沿用那边的键名。
 * 中文一侧**逐字等于抽取前的界面原文**。
 */
export const SUPPORT: Record<string, [string, string]> = {
  'ticket.title': ['Support Tickets', '客服工单'],
  'ticket.subtitle': ['Tickets / Reports', '工单 / 报表'],
  'ticket.tab': ['Tickets', '工单'],
  'ticket.search_hint': ['Ticket no. / user / subject', '工单号 / 用户 / 标题'],

  // ---- 详情抽屉 ----
  'ticket.detail': ['Ticket Detail', '工单详情'],
  'ticket.empty_hint': ['Note', '提示'],
  'ticket.no_data': ['This ticket has no displayable fields', '该工单暂无可展示字段'],

  // ---- 三个动作（抽屉里的按钮）----
  'ticket.reply': ['Reply', '回复'],
  'ticket.assign': ['Assign', '指派'],
  'ticket.close': ['Close', '关闭'],
  /** 弹框标题：`{action}工单：{name}`，action 是上面两个动词之一（回填后与原文逐字一致） */
  'ticket.form_title': ['{action} ticket: {name}', '{action}工单：{name}'],
  'ticket.reply_content': ['Reply content', '回复内容'],
  'ticket.reply_hint': [
    'Required; the server rejects replies once the ticket is closed',
    '不能为空；工单已 closed 时后端拒收',
  ],
  'ticket.admin_id': ['Assignee (admin numeric ID)', '受理人（管理员数字 ID）'],
  'ticket.admin_id_hint': [
    'Digits only; empty = unassign',
    '只能填数字；留空 = 取消指派',
  ],
  /** 前端先挡的一道（后端 empty($content) 直接 422），文案说明为什么 */
  'ticket.reply_required': ['Reply content is required', '回复内容不能为空'],
  'ticket.admin_id_invalid': [
    'The assignee must be a numeric ID (the backend only accepts int; a hashid is coerced to 0 = unassign)',
    '受理人只能填数字 ID（后端只认 int，hashid 会被压成 0 = 取消指派）',
  ],
  'ticket.close_confirm_target': [
    'Close ticket "{name}"? You cannot reply after closing.',
    '确认关闭工单「{name}」？关闭后不能再回复。',
  ],

  // ---- 报表标签页 ----
  'report.tab': ['Reports', '报表'],
  'report.raw_title': ['Raw report summary response', '报表汇总原始响应'],
};
