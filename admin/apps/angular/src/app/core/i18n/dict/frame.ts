/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 外壳（侧栏/顶栏/弹框/分页/通用动作）的词条。
 *
 * 形状 `键: [英文, 中文]` —— 一行一对，两种语言不会走散；其余 11 种语言**不建表**，
 * 查不到回落英文（见 i18n.ts 的 `t()`）。键的命名与 `admin/apps/flutter` 的 translations.dart
 * 同一套 `域.名` 约定与占位符 `{name}`；同名同义的词条（app.cancel / app.save / app.pager …）
 * 逐字沿用那边的文案，改文案时两棵树要一起改。
 */
export const FRAME: Record<string, [string, string]> = {
  // ---- 品牌与外壳 ----
  'app.brand': ['Game Admin', '游戏运营后台'],
  'app.not_logged_in': ['Not signed in', '未登录'],
  'app.lang': ['Language', '语言'],
  'app.menu': ['Menu', '菜单'],

  // ---- 通用动作（沿用 flutter translations.dart 的键名与文案）----
  'app.logout': ['Logout', '退出'],
  'app.cancel': ['Cancel', '取消'],
  'app.confirm': ['Confirm', '确认'],
  'app.save': ['Save', '保存'],
  'app.delete': ['Delete', '删除'],
  'app.edit': ['Edit', '编辑'],
  'app.create': ['Create', '新建'],
  // 本树的列表页按钮原文是「查询」（不是 flutter 的「搜索」）⇒ 中文一侧按本树写
  'app.search': ['Search', '查询'],
  'app.refresh': ['Refresh', '刷新'],
  'app.close': ['Close', '关闭'],
  'app.retry': ['Retry', '重试'],
  'app.upload': ['Upload', '上传'],
  'app.no_data': ['No data', '暂无数据'],
  'app.yes': ['Yes', '是'],
  'app.no': ['No', '否'],
  'app.loading_failed': ['Loading failed, please retry', '加载失败，请稍后重试'],
  'app.enabled': ['Enabled', '启用'],
  'app.disabled': ['Disabled', '停用'],
  'app.pager': ['Page {page} / {pages} ({total} total)', '共 {total} 条 · 第 {page}/{pages} 页'],
  'app.prev_page': ['Previous', '上一页'],
  'app.next_page': ['Next', '下一页'],

  // ---- 侧栏分组与页面 ----
  'nav.section.overview': ['Overview', '概览'],
  'nav.section.ops': ['Operations', '运营'],
  'nav.section.money': ['Funds & Risk', '资金与风控'],
  'nav.section.support': ['Support', '支撑'],
  'nav.dashboard': ['Dashboard', '仪表盘'],
  'nav.analytics': ['Analytics', '数据分析'],
  // 本树的 /users 是 C 端平台用户（flutter 叫 nav.platform_users），/admins 才是后台账号
  'nav.users': ['Users', '用户管理'],
  'nav.games': ['Game Management', '游戏管理'],
  'nav.content': ['Content', '内容运营'],
  'nav.marketing': ['Marketing', '营销中心'],
  'nav.finance': ['Finance', '财务中心'],
  'nav.risk': ['Risk Control', '风险控制'],
  'nav.support': ['Tickets & Reports', '工单报表'],
  'nav.infra': ['Infrastructure', '基础设施'],
  'nav.admins': ['Admin Accounts', '管理员'],
  'nav.settings': ['Settings', '系统设置'],

  // ---- 状态三态块 / 表格 ----
  'ui.loading': ['Loading…', '加载中…'],
  'table.actions': ['Actions', '操作'],

  // ---- 通用表单弹框 ----
  'form.submit': ['Submit', '提交'],
  'form.submitting': ['Submitting…', '提交中…'],
  'form.uploading': ['Uploading…', '上传中…'],
  'form.select_placeholder': ['Select…', '请选择'],
  'form.keep_if_empty': ['Keep unchanged', '不修改'],
  'form.current_value': ['{value} (current)', '{value}（当前值）'],
  /** 树多选（ui-tree-select）里「值在集合、节点不在树」的那段平铺项标题；组件是通用的，键留在 form 域 */
  'form.tree_orphan_hint': [
    'These permissions are not in the permission tree; unchecking one removes it:',
    '以下权限不在权限树中，取消勾选即回收：',
  ],

  // ---- 增删改底座（crud.ts）----
  'crud.create': ['Create {name}', '新建{name}'],
  'crud.edit': ['Edit {name}', '编辑{name}'],
  'crud.toggle': ['Enable / Disable', '启用/停用'],
  'crud.delete_confirm': [
    'Delete "{name}"? This cannot be undone.',
    '确认删除「{name}」？该操作不可撤销。',
  ],
  'crud.delete_password_prompt': [
    'Enter your login password to confirm',
    '该操作需要输入登录密码确认',
  ],

  // ---- 登录/会话 ----
  'app.session_expired': ['Session expired, please sign in again', '登录状态已失效，请重新登录'],
  'app.network_error': ['Network error', '网络异常'],
  'app.server_unreachable': ['Cannot reach the server', '无法连接服务器'],
  'app.request_failed': ['Request failed ({code})', '请求失败（{code}）'],

  /**
   * 上传（core/upload.ts 的 failText：插件响应不走统一信封，`error` 字段的三种异常形状）。
   * 正常失败时用的是服务端原文（插件已按自己的 `locale` 表单字段译好），这里只是兜底文案。
   * ⚠ 那个 `locale` 仍硬编码 zh_CN（见 upload.ts 的 LOCALE 注释）—— 与本树语言是两套机制。
   */
  'upload.failed_no_error': [
    'Upload failed: the server returned no error field',
    '上传失败：服务端未返回 error 字段',
  ],
  'upload.failed_reason': ['Upload failed ({reason})', '上传失败（{reason}）'],
  'upload.failed_no_path': [
    'Upload failed: the server returned no file path',
    '上传失败：服务端未返回文件路径',
  ],

  /** 各页 `<details>` 里那坨 JSON 的折叠标题（风控总览 / 数据分析都有） */
  'app.raw_response': ['Raw response', '原始响应'],
};
