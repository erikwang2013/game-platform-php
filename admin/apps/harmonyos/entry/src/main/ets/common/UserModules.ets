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
 *  3. 权限列表是**树**（buildTree），行是深度优先铺平的，缩进用全角空格（行组件只有
 *     title/subtitle 两个位置，铺平比再造一棵树控件划算）。
 *  4. 父子与授权都走 hashid（后端已改口径）：`PermissionController::store` 收 hashid 形式的
 *     parent_id（空/0 = 根，裸数字 400），树里 parent_id 也是 hashid；`RoleController` 的
 *     permission_ids 收 **hashid 数组**、列表**回传**同形式的 permission_ids 供表单回填。
 *     故两处候选/回填值一律用响应回传的 hashid，前端不自己拼 id。
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
  PermissionNode
} from '../model/DataModels';
import { ResourceModule, buildRow, paged } from './ResourceSchema';
import { PermissionService } from '../service/DataService';

/* ============================== 1 平台用户 ==============================
 * list: GET /admin/v1/platform/user/list（page/limit；控制器另支持 keyword/status 过滤）
 * update: PUT /admin/v1/platform/user/{hashid} —— 只收 nickname(≤50) 与 status(严格 0/1)；
 *   一个都没带时 422「没有可更新的字段」（编辑框里没改动就点确定会看到这条，是后端口径不是缺陷）
 * delete: DELETE 同路径 —— 是**注销**：余额/冻结额非 0 一律 422；通过后匿名化资料 + 软删除 +
 *   清会话/OAuth，不可恢复。控制器**不要密码**（与角色/权限不同）⇒ deletePassword=false
 * 没有新建端点 ⇒ canCreate=false；状态两态 ⇒ 行内切换走局部 PUT {status}
 * ========================================================================= */

const PLATFORM_USER_EDIT_FIELDS: FormField[] = [
  { name: 'nickname', label: '昵称', type: 'text', placeholder: '最长 50 字' }
];

function platformUserRow(item: PlatformUserItem): ResourceRow {
  const values: FormValues = {};
  values.nickname = item.nickname ?? '';
  const nickname = item.nickname ?? '';
  // 删前要能看清是谁：标题取登录名，空则退昵称；副标题补昵称（有则带）
  const title = item.username.length > 0 ? item.username : nickname;
  const sub = nickname.length > 0
    ? `昵称 ${nickname} · 注册 ${item.created_at}`
    : `未设昵称 · 注册 ${item.created_at}`;
  return buildRow(item.id, title.length > 0 ? title : item.id, sub,
    item.status === 1 ? '正常' : '禁用', item.status === 1, item.status, values);
}

const PLATFORM_USER: ResourceModule = {
  key: 'platform_user',
  title: '平台用户',
  desc: '封禁 / 解封与改昵称，注销不可恢复',
  base: '/admin/v1/platform/user',
  canCreate: false,
  paginated: true,
  listPath: (page: number, limit: number, _gameId: string): string =>
    `/admin/v1/platform/user/list?page=${page}&limit=${limit}`,
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
 * permission_ids 是 **hashid 数组**：候选取自权限树（另一个端点，走 prepareForm 钩子拉），
 * 编辑态用列表回传的 permission_ids 回填；全不选 = 空数组（后端 sync([]) 即取消全部授权）。
 * ========================================================================= */

/**
 * 权限多选。options 由 prepareForm 在开框前回填（树是另一个端点的数据），
 * 与 PARENT_FIELD 同一手法：字段对象先建壳，拿到候选后原地改它的 options。
 */
const ROLE_PERMISSION_FIELD: FormField = {
  name: 'permission_ids',
  label: '权限',
  type: 'multi',
  options: []
};

const ROLE_FIELDS: FormField[] = [
  { name: 'name', label: '角色名称', type: 'text', required: true, placeholder: '最长 50 字' },
  { name: 'slug', label: '角色标识', type: 'text', required: true, placeholder: '英文标识，最长 50' },
  { name: 'description', label: '角色描述', type: 'textarea', placeholder: '最长 255 字' },
  ROLE_PERMISSION_FIELD
];

/** 开框前拉权限树填候选。每次开框都拉一次（权限随时会新增/删除，缓存只会让用户勾到过期项） */
async function loadRolePermissionOptions(): Promise<void> {
  const roots = await PermissionService.tree();
  const options: FieldOption[] = [];
  // 行收集器给个临时数组：这一趟只要候选（同一套铺平逻辑，不为角色再写一份）
  flattenPermissions(roots, 0, [], options);
  ROLE_PERMISSION_FIELD.options = options;
}

function roleFields(isEdit: boolean): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < ROLE_FIELDS.length; i++) {
    const field = ROLE_FIELDS[i];
    if (isEdit && field.name === 'slug') {
      fields.push({ name: 'slug', label: '角色标识', type: 'text', placeholder: '创建后不可修改', disabled: true });
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
  const head = `${item.slug} · ${users} 名用户 · ${granted.length} 项权限`;
  const sub = desc.length > 0 ? `${head} · ${desc}` : head;
  return buildRow(item.id, item.name, sub,
    item.status === 1 ? '启用' : '停用', item.status === 1, item.status, values);
}

const ROLE: ResourceModule = {
  key: 'role',
  title: '角色',
  desc: '新建 / 编辑 / 删除与启停（含关联用户数与已授权限）',
  base: '/admin/v1/role',
  createPath: '/admin/v1/role',
  paginated: true,
  listPath: (page: number, limit: number, _gameId: string): string =>
    `/admin/v1/role?page=${page}&limit=${limit}`,
  parseList: (data: Object): ResourceListResult => paged<RoleItem>(data, roleRow),
  fields: roleFields(false),
  editFields: roleFields(true),
  createInitial: roleInitial,
  prepareForm: loadRolePermissionOptions,
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
  { label: '菜单', value: '1' },
  { label: '按钮', value: '2' },
  { label: '接口', value: '3' }
];

/** 新建到树根：parent_id 0 就是 buildTree 的起点 */
const PERMISSION_ROOT_OPTION: FieldOption = { label: '顶级（无父级）', value: '0' };

/**
 * 父级下拉（**只在新建态出现**：update 不收 parent_id，编辑态在 permissionEditFields 里被跳过）。
 * 选项要读完整棵树才知道，故先给只有「顶级」的空壳，parseList 拿到树之后**原地改这个对象的
 * options** —— PERMISSION_FIELDS 里放的是同一个引用，页面 openCreate 时读到的就是回填后的候选。
 */
const PARENT_FIELD: FormField = {
  name: 'parent_id',
  label: '父级权限',
  type: 'select',
  options: [PERMISSION_ROOT_OPTION]
};

const PERMISSION_FIELDS: FormField[] = [
  { name: 'name', label: '权限名称', type: 'text', required: true, placeholder: '最长 50 字' },
  { name: 'slug', label: '权限标识', type: 'text', required: true, placeholder: '如 role.list，最长 100' },
  { name: 'type', label: '权限类型', type: 'select', required: true, options: PERMISSION_TYPE_OPTIONS },
  PARENT_FIELD,
  { name: 'icon', label: '图标', type: 'text', placeholder: '最长 50' },
  { name: 'path', label: '路由 / 接口路径', type: 'text', placeholder: '最长 255' },
  { name: 'sort', label: '排序', type: 'number', placeholder: '越小越靠前，缺省 0' }
];

/** 编辑态：只保留 update 真会写的字段；slug/type 留着做只读回显（update 不接受它们） */
function permissionEditFields(): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < PERMISSION_FIELDS.length; i++) {
    const field = PERMISSION_FIELDS[i];
    if (field.name === 'slug') {
      fields.push({ name: 'slug', label: '权限标识', type: 'text', placeholder: '创建后不可修改', disabled: true });
    } else if (field.name === 'type') {
      fields.push({
        name: 'type', label: '权限类型', type: 'select',
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

function permissionTypeText(type: number): string {
  for (let i = 0; i < PERMISSION_TYPE_OPTIONS.length; i++) {
    if (PERMISSION_TYPE_OPTIONS[i].value === `${type}`) {
      return PERMISSION_TYPE_OPTIONS[i].label;
    }
  }
  return '未知';
}

/** 树深度 → 缩进（每层两个全角空格；行组件只有 title 一个位置，铺平后靠缩进表层级） */
function permissionIndent(depth: number): string {
  let pad = '';
  for (let i = 0; i < depth; i++) {
    pad += '　　';
  }
  return pad;
}

function permissionRow(node: PermissionNode, depth: number): ResourceRow {
  const values: FormValues = {};
  values.name = node.name;
  values.slug = node.slug;
  values.type = `${node.type}`;
  values.icon = node.icon ?? '';
  values.path = node.path ?? '';
  values.sort = `${node.sort}`;
  const path = node.path ?? '';
  const head = `${permissionTypeText(node.type)} · ${node.slug}`;
  // 无 status 列 ⇒ 不画徽标（status 恒 0、badge 空串，行组件据此不渲染）
  return buildRow(node.id, permissionIndent(depth) + node.name,
    path.length > 0 ? `${head} · ${path}` : head, '', true, 0, values);
}

/**
 * 深度优先铺平：一趟同时产出列表行（rows）与候选（options）。
 * 候选的**值**一律取节点自己的 hashid（树的 id 已编、parent_id 也已编成同域 hashid，
 * 见 PermissionController::buildTree）—— 叶节点同样能当父级，父子关系不再受可见性限制。
 * 角色表单只要候选，行收集器传临时数组即可复用这一趟。
 */
function flattenPermissions(nodes: PermissionNode[], depth: number,
  rows: ResourceRow[], options: FieldOption[]): void {
  for (let i = 0; i < nodes.length; i++) {
    const node = nodes[i];
    const option: FieldOption = {
      label: permissionIndent(depth) + node.name,
      value: node.id
    };
    options.push(option);
    rows.push(permissionRow(node, depth));
    flattenPermissions(node.children ?? [], depth + 1, rows, options);
  }
}

const PERMISSION: ResourceModule = {
  key: 'permission',
  title: '权限',
  desc: '权限树：新建节点 / 改名 / 删除（级联子节点）',
  base: '/admin/v1/permission',
  createPath: '/admin/v1/permission',
  paginated: false,
  listPath: (_page: number, _limit: number, _gameId: string): string => '/admin/v1/permission',
  parseList: (data: Object): ResourceListResult => {
    const roots = data as PermissionNode[];
    const rows: ResourceRow[] = [];
    const parents: FieldOption[] = [];
    flattenPermissions(roots, 0, rows, parents);
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
