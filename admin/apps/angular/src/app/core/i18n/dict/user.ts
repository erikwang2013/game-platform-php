/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 用户域词条（users.ts：平台用户列表 / 实名审核）。
 *
 * 与 `dict/frame.ts` 同一套约定：`键: [英文, 中文]`、占位符 `{name}`。**中文一侧逐字等于抽取前的
 * 界面原文**（本批只做抽取、不动文案），英文沿用 flutter translations.dart 的 `user.*` /
 * `identity.*` 说法；键名能对上的直接同名。
 *
 * ⚠ 两处刻意与 flutter 的中文不同（按**本树**的界面原文写，别照 flutter 反改）：
 *  - `identity.reject`：本树的按钮是「驳回」（flutter 是「拒绝」）；
 *  - `identity.note_hint`：本树那句是给驳回用的（flutter 那句是 ≤500 字符的通用口径）。
 */
export const USER: Record<string, [string, string]> = {
  // ---- 页头 / 标签页 ----
  'user.title': ['User Management', '用户管理'],
  'user.subtitle': ['User list / KYC review', '用户列表 / 实名审核'],
  'user.tab_list': ['Users', '用户列表'],
  'user.detail': ['User details', '用户详情'],
  'user.search_hint': ['Username / nickname', '用户名 / 昵称'],
  'identity.title': ['KYC Review', '实名审核'],

  // ---- 列表与表单 ----
  /** 增删改底座的模块名（crud.noun），拼进「新建{name}」「确认删除「{name}」」 */
  'user.noun': ['Platform user', '平台用户'],
  'user.nickname': ['Nickname', '昵称'],
  'user.nickname_hint': [
    'Up to 50 characters (column width game_user.nickname VARCHAR(50))',
    '最长 50 字符（列宽口径 game_user.nickname VARCHAR(50)）',
  ],

  // ---- 详情抽屉 ----
  'user.empty_tip': ['Notice', '提示'],
  'user.empty_note': ['No displayable fields on this record', '该记录暂无可展示字段'],
  'identity.approve': ['Approve', '通过'],
  'identity.reject': ['Reject', '驳回'],
  'user.unban': ['Unban', '解封'],
  'user.ban': ['Ban', '封禁'],

  // ---- 实名审核（驳回不可逆 ⇒ 二次确认 + 原因）----
  'identity.reject_confirm_target': [
    'Reject KYC "{name}"? A reviewed record cannot be reviewed again.',
    '确认驳回「{name}」的实名认证申请？驳回后不可再改。',
  ],
  'identity.note_hint': [
    'Reject reason (sent to the user, may be empty)',
    '驳回原因（会推送给用户，可留空）',
  ],

  // ---- 封禁/解封与注销（成功以回读为准）----
  'user.status_gone': [
    'Submitted, but this user is no longer on the current page - refresh to confirm',
    '操作已提交，但该用户已不在当前页，请刷新确认',
  ],
  'user.status_stale': [
    'Status did not take effect: the server still reports {status}',
    '状态未生效：服务端仍为 {status}',
  ],
  'user.destroy_confirm': [
    'Delete the account "{name}"? This cannot be undone.',
    '确认注销「{name}」的账号？该操作不可撤销。',
  ],
  'user.destroy_stale': [
    'The deletion was submitted, but the user is still in the list - refresh to confirm',
    '注销请求已提交，但该用户仍在列表中，请刷新确认',
  ],
};
