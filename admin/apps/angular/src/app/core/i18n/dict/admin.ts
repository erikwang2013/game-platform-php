/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 管理员账号页词条（admins.ts：`game_admin_user`，**不是** C 端平台用户 —— 后者在 pages/users.ts）。
 *
 * `admin/apps/flutter` 的 translations.dart **没有**这一页（那边的「用户管理」指的是 C 端平台用户）,
 * 所以这一域没有可沿用的键名，是按本页语义新写的一组；`role.*` 相关的词条复用 dict/settings.ts
 * （同一份角色数据，两页共用一套文案）。
 * 中文一侧**逐字等于抽取前的界面原文**。
 */
export const ADMIN: Record<string, [string, string]> = {
  'admin.title': ['Admin Accounts', '管理员'],
  'admin.subtitle': ['Backstage accounts / role assignment', '后台账号 / 角色分配'],
  'admin.search_hint': ['Username / real name', '用户名 / 真实姓名'],
  'admin.noun': ['admin', '管理员'],

  // ---- 字段（label/placeholder/hint 存的都是下面的键）----
  'admin.username': ['Username', '用户名'],
  'admin.username_hint': [
    '3-50 characters; cannot be changed after creation (update does not read it)',
    '3-50 位；创建后不可改（update 不读该字段）',
  ],
  'admin.password': ['Password', '密码'],
  'admin.password_hint': [
    '8-32 characters, must contain upper and lower case letters and digits',
    '8-32 位，须含大小写字母与数字',
  ],
  'admin.real_name': ['Real name', '真实姓名'],
  'admin.real_name_hint': ['Max 50 characters', '最长 50'],
  'admin.phone': ['Phone', '手机号'],
  'admin.email': ['Email', '邮箱'],
  'admin.masked_hint': [
    'The list returns a masked value; leaving it untouched skips the field, editing it overwrites the whole value',
    '列表回显为脱敏值；原样不动则不会提交，改动即整体覆盖',
  ],
  'admin.role_ids': ['Roles', '角色'],
  'admin.new_password': ['New password', '新密码'],
  'admin.admin_password': ["The signed-in admin's password", '当前登录管理员的密码'],
  'admin.admin_password_hint': [
    "The server uses it to confirm the operator's identity; it is not the password of the account being edited",
    '服务端用它确认操作者身份；不是被改账号的密码',
  ],

  // ---- 列头 ----
  'admin.phone_masked': ['Phone (masked)', '手机号（脱敏）'],
  'admin.email_masked': ['Email (masked)', '邮箱（脱敏）'],
  'admin.head.status': ['Status (0 disabled / 1 enabled)', '状态(0禁用/1启用)'],
  'admin.head.last_login': ['Last login', '最后登录'],

  // ---- 两个动作型弹框（端点同址，字段集不同）----
  'admin.grant': ['Assign roles', '分配角色'],
  'admin.reset': ['Reset password', '重置密码'],
  /** 弹框标题：`{action}：{name}`，action 是上面两个动作名（回填后与原文逐字一致） */
  'admin.act_title': ['{action}: {name}', '{action}：{name}'],
  /** 自我保护：把自己停用 = 当场自锁，把自己删掉连补救入口都没有 */
  'admin.self_guard': [
    'You cannot disable or delete the admin account you are signed in with',
    '不能停用或删除当前登录的管理员账号',
  ],
  /** 角色列表取失败时拼在字段 label 后面（不把整页打成错误态） */
  'admin.roles_failed': [
    '{name} — role list failed to load: {error}',
    '{name} —— 角色列表加载失败：{error}',
  ],
  /** 列表里多个角色名的连接符：中文顿号，英文用逗号空格 */
  'admin.role_join': [', ', '、'],
};
