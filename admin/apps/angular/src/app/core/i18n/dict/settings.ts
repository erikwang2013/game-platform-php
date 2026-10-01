/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 系统设置页词条（settings.ts：配置项 / 角色 / 权限 / 监控指标 / 健康检查）。
 *
 * 键名沿用 `admin/apps/flutter` 的 translations.dart —— 那边正好有 `config.*`、`role.*`、
 * `permission.*` 三族同名页面，同名同义的（config.group / config.key / config.value / config.type /
 * config.description / role.name / role.slug / role.description / role.users_count /
 * permission.name / permission.slug / permission.type / permission.type_menu / permission.type_button /
 * permission.type_api / permission.parent_id / permission.icon / permission.path / permission.title）
 * 逐字沿用那边的键名。
 *
 * ⚠ 三处**与 flutter 不同文**（按本树原文写，与 frame.ts 的 `app.search` 同一处置口径）：
 *  - `permission.parent_id`：flutter 是「Parent」（新建时的父级选择），本树同一键还当**列表列头**
 *    用（只读回显），中文原文是「父级」；
 *  - `permission.root`：flutter 那侧是下拉项「Root (top level)」，本树是表格单元格里的占位符「（根）」；
 *  - `permission.type_*`：flutter 从后端枚举取值，本树是把枚举含义写进表头的提示（见 permission.head.type）。
 *
 * 中文一侧**逐字等于抽取前的界面原文**。字段的 `label`/`placeholder`/`hint` 存的都是**键**
 * （渲染时过 `| t`，见 components/form-modal.ts），所以这一域的词条必须在这一层就能查全。
 */
export const SETTINGS: Record<string, [string, string]> = {
  'settings.title': ['Settings', '系统设置'],
  'settings.subtitle': ['Config / Roles / Permissions / Metrics / Health', '配置 / 角色 / 权限 / 指标 / 健康'],
  'settings.search_hint': ['Key / name / ID', '键名 / 名称 / ID'],
  'settings.tab.metrics': ['Metrics', '监控指标'],
  'settings.tab.health': ['Health check', '健康检查'],
  'settings.metrics_empty': ['No metrics yet', '暂无指标'],
  'settings.health_raw': ['Raw health check response', '健康检查原始响应'],

  // ---- 配置项（config 页签）----
  'config.title': ['System Configuration', '系统配置'],
  'config.noun': ['config', '配置项'],
  'config.group': ['Group', '分组'],
  'config.group_hint': ['Max 100 characters', '最长 100'],
  'config.key': ['Key', '配置键'],
  'config.key_hint': ['Max 100 characters', '最长 100'],
  'config.value': ['Value', '配置值'],
  'config.value_hint': [
    'Required; empty = keep unchanged (the backend rejects empty strings)',
    '不能为空；留空不改（后端 required 拒空串）',
  ],
  'config.type': ['Type', '值类型'],
  'config.description': ['Description', '配置说明'],
  'config.description_hint': ['Max 255 characters', '最长 255'],

  // ---- 角色 ----
  'role.title': ['Role Management', '角色'],
  'role.noun': ['role', '角色'],
  'role.name': ['Name', '角色名称'],
  'role.name_hint': ['Max 50 characters', '最长 50'],
  'role.slug': ['Slug', '角色标识'],
  'role.slug_hint': ['Max 50 characters', '最长 50'],
  'role.description': ['Description', '角色描述'],
  'role.description_hint': ['Max 255 characters', '最长 255'],
  'role.permission_ids_hint': [
    'Checking a parent also checks all of its children; clearing revokes every permission',
    '勾父级会连带其下全部子权限；清空 = 收回全部权限',
  ],
  'role.users_count': ['Users Count', '关联用户数'],
  /** 列头把枚举含义写进去（值原样显示，不改数据） */
  'role.head.status': ['Status', '状态'],
  /** 删除确认里的对象标识：destroy 会 detach 掉权限与用户关联，得说清楚 */
  'role.delete_label': [
    '{name} (also detaches its permissions and users)',
    '{name}（并解除其权限与用户关联）',
  ],
  /** 权限树取失败时拼在字段 label 后面（不把整页打成错误态） */
  'role.tree_failed': [
    '{name} — permission tree failed to load: {error}',
    '{name} —— 权限树加载失败：{error}',
  ],

  // ---- 权限 ----
  'permission.title': ['Permissions', '权限'],
  'permission.noun': ['permission', '权限'],
  'permission.name': ['Name', '权限名称'],
  'permission.name_hint': ['Max 50 characters', '最长 50'],
  'permission.slug': ['Slug', '权限标识'],
  'permission.slug_hint': ['Max 100 characters', '最长 100'],
  'permission.type': ['Type', '权限类型'],
  /** 三值枚举的选项名（值仍是 '1'/'2'/'3'，只是文案可译） */
  'permission.type_menu': ['Menu', '菜单'],
  'permission.type_button': ['Button', '按钮'],
  'permission.type_api': ['API', '接口'],
  /** createOnly：update 不收 parent_id，所以「建在根上」只在新建态出现 */
  'permission.parent_id': ['Parent', '父级（不选 = 建在根上）'],
  /** 列表列头 / 树节点的父级回显；根节点的父级名就是这个占位符 */
  'permission.root': ['(root)', '（根）'],
  'permission.icon': ['Icon', '图标'],
  'permission.icon_hint': ['Max 50 characters', '最长 50'],
  'permission.path': ['Route Path', '前端路由路径'],
  'permission.path_hint': ['Max 255 characters', '最长 255'],
  'permission.sort': ['Sort', '排序'],
  'permission.sort_hint': ['Smaller comes first', '数字越小越靠前'],
  'permission.head.type': ['Type (1 menu / 2 button / 3 api)', '类型(1菜单/2按钮/3接口)'],
  /** destroy 会级联删子权限（PermissionController::destroy）—— 删之前说清楚 */
  'permission.delete_label': ['{name} (and all of its child permissions)', '{name}（连同其全部子权限）'],
};
