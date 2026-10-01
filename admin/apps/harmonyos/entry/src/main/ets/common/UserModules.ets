/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 「用户与权限」三个模块的字段描述（批次 3）：平台用户 / 角色 / 权限。
 * 契约与共用小工具见 ResourceSchema.ets；字段值域以控制器 validator 与列定义为真值：
 *   admin/app/admin/v1/controller/{PlatformUser,Role,Permission}Controller.php
 *
 * 本册特有的四条口径：
 *  1. 平台用户没有新建端点（C 端用户自助注册）⇒ canCreate=false；update 只收 status(严格 0/1)
 *     与 nickname(≤50)，status 由行内切换发（与角色同款：状态不进表单）。
 *  2. 角色/权限的删除都要管理员密码（控制器 confirmPassword）⇒ deletePassword=true。
 *  3. 权限列表是**树**（buildTree）：行按深度优先铺平，但**带层级与祖先链**交给行组件
 *     （depth/hasChildren/ancestors），由页面画缩进 + 展开箭头、按折叠状态整枝隐藏；
 *     父级下拉仍是铺平的一维候选（Select 表达不了层级，缩进用全角空格）。
 *  4. 父子与授权都走 hashid（后端已改口径）：`PermissionController::store` 收 hashid 形式的
 *     parent_id（空/0 = 根，裸数字 400），树里 parent_id 也是 hashid；`RoleController` 的
 *     permission_ids 收 **hashid 数组**、列表**回传**同形式的 permission_ids 供表单回填。
 *     故两处候选/回填值一律用响应回传的 hashid，前端不自己拼 id。
 *  5. 角色的权限字段是 **tree 型**（父子联动 + 三态勾选，见 common/PermissionTree.ets）：
 *     候选取**整棵树**不摊平；提交值仍是勾中节点的 hashid 数组，与 multi 同编码（逗号串进、数组出）。
 *
 * 本册**不做**的一处（报告里说明）：平台用户的 keyword 搜索（ResourceModule 契约没有搜索框）。
 */

import { FormField, FieldOption } from './FormDialog';
import {
  FormValues,
  ResourceRow,
  ResourceListResult,
  PlatformUserItem,
  RoleItem,
  PermissionNode,
  pageQuery
} from '../model/DataModels';
import { ResourceModule, buildRow, paged } from './ResourceSchema';
import { PermissionService } from '../service/DataService';
// 本文件全是**请求期/渲染期**求值的函数与只存**键名**的常量表（FormDialog 对 label/hint/placeholder/
// option.label 各调一次 t()}）⇒ 直接调 t() 安全；顶层调 t() 取成字符串会固化启动语言。
import { t } from '../i18n/Translations';

/* ============================== 1 平台用户 ==============================
 * list: GET /admin/v1/platform/user/list（page/limit；控制器另支持 keyword/status 过滤）
 * update: PUT /admin/v1/platform/user/{hashid} —— 只收 nickname(≤50) 与 status(严格 0/1)；
 *   一个都没带时 422「没有可更新的字段」（编辑框里没改动就点确定会看到这条，是后端口径不是缺陷）
 * delete: DELETE 同路径 —— 是**注销**：余额/冻结额非 0 一律 422；通过后匿名化资料 + 软删除 +
 *   清会话/OAuth，不可恢复。控制器**不要密码**（与角色/权限不同）⇒ deletePassword=false
 * 没有新建端点 ⇒ canCreate=false；状态两态 ⇒ 行内切换走局部 PUT {status}
 * ========================================================================= */

const PLATFORM_USER_EDIT_FIELDS: FormField[] = [
  { name: 'nickname', label: 'um.platform_user.nickname', type: 'text', placeholder: 'common.max_chars_50' }
];

function platformUserRow(item: PlatformUserItem): ResourceRow {
  const values: FormValues = {};
  values.nickname = item.nickname ?? '';
  const nickname = item.nickname ?? '';
  // 删前要能看清是谁：标题取登录名，空则退昵称；副标题补昵称（有则带）
  const title = item.username.length > 0 ? item.username : nickname;
  const sub = nickname.length > 0
    ? t('um.platform_user.sub_nickname').replace('{name}', nickname).replace('{at}', item.created_at)
    : t('um.platform_user.sub_no_nickname').replace('{at}', item.created_at);
  return buildRow(item.id, title.length > 0 ? title : item.id, sub,
    item.status === 1 ? t('um.platform_user.status_active') : t('user.status_disabled'),
    item.status === 1, item.status, values);
}

const PLATFORM_USER: ResourceModule = {
  key: 'platform_user',
  title: 'mod.platform_user.title',
  desc: 'mod.platform_user.desc',
  base: '/admin/v1/platform/user',
  canCreate: false,
  paginated: true,
  listPath: (page: number, limit: number, _gameId: string): string =>
    `/admin/v1/platform/user/list?${pageQuery(page, limit)}`,
  parseList: (data: Object): ResourceListResult => paged<PlatformUserItem>(data, platformUserRow),
  fields: [],
  editFields: PLATFORM_USER_EDIT_FIELDS,
  createInitial: (): FormValues => {
    const values: FormValues = {};
    return values;
  },
  statusMode: 'update',
  togglePath: '',
  needsGame: false,
  deletePassword: false,
  extraText: ''
};

/* ================================ 2 角色 ================================
 * list: GET /admin/v1/role（**没有 /list 段**）、create: POST /admin/v1/role（同样没有 /create）
 * store: name*(≤50) slug*(≤50) description(≤255? store 未校验) status(缺省 1) permission_ids
 * update: name description status(in:0,1) permission_ids；**slug 不在 update 里** ⇒ 创建后不可改
 * delete: 要管理员密码；会把 permissions 与 users 一并 detach（故行里带关联用户数）
 * 状态两态 ⇒ 行内切换走局部 PUT {status}（role 没有 toggle 端点）
 * permission_ids 是 **hashid 数组**：候选是**整棵权限树**（另一个端点，走 prepareForm 钩子拉，
 * 表单里渲染成树多选），编辑态用列表回传的 permission_ids 回填；
 * 全不选 = 空数组（后端 sync([]) 即取消全部授权）。
 * ========================================================================= */

/**
 * 权限树多选。tree 由 prepareForm 在开框前回填（树是另一个端点的数据），
 * 与 PARENT_FIELD 同一手法：字段对象先建壳，拿到树后原地改它的 tree。
 * 值仍是逗号串（FormValues 只认 string）：编辑态用列表回传的 permission_ids 回填，
 * 提交时 FormDialog 按 multi/tree 同一条路 split 成 hashid 数组。
 */
const ROLE_PERMISSION_FIELD: FormField = {
  name: 'permission_ids',
  label: 'um.role.permission_label',
  type: 'tree',
  hint: 'um.role.permission_hint',
  tree: []
};

const ROLE_FIELDS: FormField[] = [
  { name: 'name', label: 'um.role.f.name', type: 'text', required: true, placeholder: 'common.max_chars_50' },
  { name: 'slug', label: 'um.role.f.slug', type: 'text', required: true, placeholder: 'um.role.f.slug_placeholder' },
  { name: 'description', label: 'um.role.f.description', type: 'textarea', placeholder: 'risk.cheat.note_hint' },
  ROLE_PERMISSION_FIELD
];

/** 开框前拉权限树填候选。每次开框都拉一次（权限随时会新增/删除，缓存只会让用户勾到过期项） */
async function loadRolePermissionTree(): Promise<void> {
  ROLE_PERMISSION_FIELD.tree = await PermissionService.tree();
}

function roleFields(isEdit: boolean): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < ROLE_FIELDS.length; i++) {
    const field = ROLE_FIELDS[i];
    if (isEdit && field.name === 'slug') {
      fields.push({ name: 'slug', label: 'um.role.f.slug', type: 'text', placeholder: 'um.role.f.slug_readonly', disabled: true });
    } else {
      fields.push(field);
    }
  }
  return fields;
}

function roleInitial(): FormValues {
  const values: FormValues = {};
  values.name = '';
  values.slug = '';
  values.description = '';
  values.permission_ids = '';
  // status 不在表单里：后端 create 缺省 1，要停用走行内切换（批次 2 的同一取舍）
  return values;
}

function roleRow(item: RoleItem): ResourceRow {
  const values: FormValues = {};
  values.name = item.name;
  values.slug = item.slug;
  values.description = item.description ?? '';
  // 多选值在表单里是逗号串（见 FormDialog 头注释），回填时现拼
  const granted = item.permission_ids ?? [];
  values.permission_ids = granted.join(',');
  const desc = item.description ?? '';
  const users = item.users_count ?? 0;
  const head = t('um.role.head').replace('{slug}', item.slug)
    .replace('{users}', `${users}`).replace('{perms}', `${granted.length}`);
  const sub = desc.length > 0 ? `${head} · ${desc}` : head;
  return buildRow(item.id, item.name, sub,
    item.status === 1 ? t('user.status_enabled') : t('common.disabled'),
    item.status === 1, item.status, values);
}

const ROLE: ResourceModule = {
  key: 'role',
  title: 'mod.role.title',
  desc: 'mod.role.desc',
  base: '/admin/v1/role',
  createPath: '/admin/v1/role',
  paginated: true,
  listPath: (page: number, limit: number, _gameId: string): string =>
    `/admin/v1/role?${pageQuery(page, limit)}`,
  parseList: (data: Object): ResourceListResult => paged<RoleItem>(data, roleRow),
  fields: roleFields(false),
  editFields: roleFields(true),
  createInitial: roleInitial,
  prepareForm: loadRolePermissionTree,
  statusMode: 'update',
  togglePath: '',
  needsGame: false,
  deletePassword: true,
  extraText: ''
};

/* ================================ 3 权限 ================================
 * list: GET /admin/v1/permission —— 整棵树（裸数组 + 嵌套 children，不分页）
 * store: name*(≤50) slug*(≤100) type*(1菜单/2按钮/3接口) parent_id(hashid，空/0=根) icon(≤50)
 *        path(≤255) sort
 * update: name icon path sort —— **slug/type/parent_id 都不在 update 里**（编辑态只读）
 * delete: 要管理员密码；会级联删 children 并 detach roles
 * 无 status 列 ⇒ 没有状态变更
 * ========================================================================= */

const PERMISSION_TYPE_OPTIONS: FieldOption[] = [
  { label: 'um.perm.type_menu', value: '1' },
  { label: 'um.perm.type_button', value: '2' },
  { label: 'um.perm.type_api', value: '3' }
];

/** 新建到树根：parent_id 0 就是 buildTree 的起点 */
const PERMISSION_ROOT_OPTION: FieldOption = { label: 'um.perm.root_option', value: '0' };

/**
 * 父级下拉（**只在新建态出现**：update 不收 parent_id，编辑态在 permissionEditFields 里被跳过）。
 * 选项要读完整棵树才知道，故先给只有「顶级」的空壳，parseList 拿到树之后**原地改这个对象的
 * options** —— PERMISSION_FIELDS 里放的是同一个引用，页面 openCreate 时读到的就是回填后的候选。
 */
const PARENT_FIELD: FormField = {
  name: 'parent_id',
  label: 'um.perm.f.parent',
  type: 'select',
  options: [PERMISSION_ROOT_OPTION]
};

const PERMISSION_FIELDS: FormField[] = [
  { name: 'name', label: 'um.perm.f.name', type: 'text', required: true, placeholder: 'common.max_chars_50' },
  { name: 'slug', label: 'um.perm.f.slug', type: 'text', required: true, placeholder: 'um.perm.f.slug_placeholder' },
  { name: 'type', label: 'um.perm.f.type', type: 'select', required: true, options: PERMISSION_TYPE_OPTIONS },
  PARENT_FIELD,
  { name: 'icon', label: 'um.perm.f.icon', type: 'text', placeholder: 'um.perm.f.icon_placeholder' },
  { name: 'path', label: 'um.perm.f.path', type: 'text', placeholder: 'um.perm.f.path_placeholder' },
  { name: 'sort', label: 'um.perm.f.sort', type: 'number', placeholder: 'um.perm.f.sort_placeholder' }
];

/** 编辑态：只保留 update 真会写的字段；slug/type 留着做只读回显（update 不接受它们） */
function permissionEditFields(): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < PERMISSION_FIELDS.length; i++) {
    const field = PERMISSION_FIELDS[i];
    if (field.name === 'slug') {
      fields.push({ name: 'slug', label: 'um.perm.f.slug', type: 'text', placeholder: 'um.role.f.slug_readonly', disabled: true });
    } else if (field.name === 'type') {
      fields.push({
        name: 'type', label: 'um.perm.f.type', type: 'select',
        options: PERMISSION_TYPE_OPTIONS, disabled: true
      });
    } else if (field.name === 'parent_id') {
      // 不进编辑表单：update 不写 parent_id（挂错枝只能删了重建，与本树 game_id 同一取舍）
      continue;
    } else {
      fields.push(field);
    }
  }
  return fields;
}

function permissionInitial(): FormValues {
  const values: FormValues = {};
  values.name = '';
  values.slug = '';
  values.type = '1';
  values.parent_id = '0';
  values.icon = '';
  values.path = '';
  values.sort = '0';
  return values;
}

/** 选项表存的是**键名** ⇒ 这里必须查表（每次渲染重新查，语言切换即时生效） */
function permissionTypeText(type: number): string {
  for (let i = 0; i < PERMISSION_TYPE_OPTIONS.length; i++) {
    if (PERMISSION_TYPE_OPTIONS[i].value === `${type}`) {
      return t(PERMISSION_TYPE_OPTIONS[i].label);
    }
  }
  return t('um.perm.type_unknown');
}

/** 树深度 → 全角空格缩进：**只给父级下拉**用（Select 摊平后只能靠缩进表层级） */
function optionIndent(depth: number): string {
  let pad = '';
  for (let i = 0; i < depth; i++) {
    pad += '　　';
  }
  return pad;
}

/**
 * 一行权限。缩进不进 title —— 层级交给行组件的 depth（真缩进 + 展开箭头），
 * title 只放名字，否则树形缩进与全角空格会叠成双份。
 */
function permissionRow(node: PermissionNode, depth: number, ancestors: string[]): ResourceRow {
  const values: FormValues = {};
  values.name = node.name;
  values.slug = node.slug;
  values.type = `${node.type}`;
  values.icon = node.icon ?? '';
  values.path = node.path ?? '';
  values.sort = `${node.sort}`;
  const path = node.path ?? '';
  const head = t('um.perm.head').replace('{type}', permissionTypeText(node.type)).replace('{slug}', node.slug);
  // 无 status 列 ⇒ 不画徽标（status 恒 0、badge 空串，行组件据此不渲染）
  const row = buildRow(node.id, node.name, path.length > 0 ? `${head} · ${path}` : head, '', true, 0, values);
  row.depth = depth;
  row.hasChildren = (node.children ?? []).length > 0;
  row.ancestors = ancestors;
  return row;
}

/**
 * 深度优先铺平成行（**带祖先链**）：ancestors 是父到根一路的 id，
 * 页面折叠某节点时按它把整枝一次过滤掉（不必再回树里找后代）。
 * children 只在非空时出现（叶子没有该键）⇒ 一律 `?? []` 兜底。
 */
function flattenPermissionRows(nodes: PermissionNode[], depth: number,
  ancestors: string[], rows: ResourceRow[]): void {
  for (let i = 0; i < nodes.length; i++) {
    const node = nodes[i];
    rows.push(permissionRow(node, depth, ancestors));
    const children = node.children ?? [];
    if (children.length > 0) {
      flattenPermissionRows(children, depth + 1, ancestors.concat(node.id), rows);
    }
  }
}

/**
 * 父级下拉的候选（铺平的一维）：值与树同源，一律取节点自己的 hashid
 * （树的 id 已编、parent_id 也已编成同域 hashid，见 PermissionController::buildTree）——
 * 叶节点同样能当父级，父子关系不受可见性限制。
 */
function collectParentOptions(nodes: PermissionNode[], depth: number, options: FieldOption[]): void {
  for (let i = 0; i < nodes.length; i++) {
    const node = nodes[i];
    const option: FieldOption = {
      label: optionIndent(depth) + node.name,
      value: node.id
    };
    options.push(option);
    collectParentOptions(node.children ?? [], depth + 1, options);
  }
}

const PERMISSION: ResourceModule = {
  key: 'permission',
  title: 'mod.permission.title',
  desc: 'mod.permission.desc',
  base: '/admin/v1/permission',
  createPath: '/admin/v1/permission',
  paginated: false,
  listPath: (_page: number, _limit: number, _gameId: string): string => '/admin/v1/permission',
  parseList: (data: Object): ResourceListResult => {
    const roots = data as PermissionNode[];
    const rows: ResourceRow[] = [];
    const parents: FieldOption[] = [];
    flattenPermissionRows(roots, 0, [], rows);
    collectParentOptions(roots, 0, parents);
    PARENT_FIELD.options = [PERMISSION_ROOT_OPTION].concat(parents);
    const result: ResourceListResult = { rows: rows, total: rows.length };
    return result;
  },
  fields: PERMISSION_FIELDS,
  editFields: permissionEditFields(),
  createInitial: permissionInitial,
  statusMode: '',
  togglePath: '',
  needsGame: false,
  deletePassword: true,
  extraText: ''
};

/** 本组模块（注册表按此顺序拼装） */
export const USER_MODULES: ResourceModule[] = [PLATFORM_USER, ROLE, PERMISSION];
