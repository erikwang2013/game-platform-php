/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 管理员（后台账号）模块的写操作描述 —— 端点 `/admin/v1/user`，路由是 `Route::resource('/user')`。
 *
 * 与「平台用户」（`/admin/v1/platform/user/*`，C 端玩家）是两回事：本模块管的是**后台账号**
 * 能不能登录、有哪些角色，两者路径零重叠，各自成页、各自命名（平台用户 / 管理员）。
 *
 * 字段/长度/必填一律照 `UserController::store` / `update` 的 validator 抄
 * （admin/app/admin/v1/controller/UserController.php），不照抄旧前端。
 */
import type { CrudAction, CrudConfig } from '../components/RowBrowser';
import type { Row } from '../components/DataTable';
import { api, session } from '../lib/api.ts';
import { labelOf, rowId, type Field, type FieldOption } from '../lib/crud.ts';
// locked / deleteWithPassword 用的是 modules.ts 那一份（角色、权限、系统配置共用同一套语义）。
// 依赖是**单向**的：modules.ts 不 import 本文件 —— 本文件在**求值期**就要调 locked，
// 双向依赖会让模块求值顺序撞上那条 const 的 TDZ（`import './modules.ts'` 直接 ReferenceError）。
// 故 TabPage 直接 import 本文件，不经 modules.ts 转手。
import { t, type MessageKey } from '../i18n/index.ts';
import { deleteWithPassword, locked } from './modules.ts';

/* ------------------------------ 自我保护 ------------------------------ */

/**
 * 提示语只有这一句：停用与删除都适用（两个入口给同一句，省得两处说法漂移）。
 * 是**文案键**不是译文：本模块的常量在求值期就被 `locked()` / `deleteBody` 用掉，取译文只能在
 * 调用处现取（同 lib/crud.ts 的 Field.label）。
 */
export const SELF_BLOCK: MessageKey = 'admins.self_guard';

/**
 * 行 id 与当前登录管理员是不是同一个人。
 * 两边同一套 hashid：登录回包的 `user.id` 是 `hashids->encode($user->id)`（AuthController::login），
 * 列表行的 `id` 是 `encodeIds`（UserController::index），故可直接比字符串。
 * 取不到任一边（没登录 / 行里没 id）一律判「不是自己」：拿不准就不挡，避免把别人的行也锁死。
 */
export const selfBlock = (id: string, me: string): string | null =>
  id !== '' && me !== '' && id === me ? t(SELF_BLOCK) : null;

/**
 * 行级守卫：返回提示语 = 拒绝，null = 放行。
 * 后端拦不住「停用/删除自己」这件事（AdminAuth 只认令牌、UserController 只校验操作者身份，
 * 都不禁止自伤），真发出去就是把自己账号停了/删了，得再找另一个人来救 —— 挡在点击处。
 * 密码可以改自己的（走「重置密码」并带当前密码确认），不在守卫范围内。
 */
export const blockSelf = (row: Row): string | null => selfBlock(rowId(row), session.user?.id ?? '');

/* ------------------------------- 字段 ------------------------------- */

const USERNAME: Field = {
  name: 'username',
  label: 'f.username',
  type: 'text',
  required: true,
  placeholder: 'admins.username_len',
  hint: 'admins.username_hint',
};

const PASSWORD: Field = {
  name: 'password',
  label: 'f.password',
  type: 'password',
  required: true,
  placeholder: 'admins.password_len',
  hint: 'admins.password_policy',
};

const REAL_NAME: Field = { name: 'real_name', label: 'f.name', type: 'text', required: true, placeholder: 'f.max_50_characters' };

const PHONE: Field = {
  name: 'phone',
  label: 'f.phone',
  type: 'text',
  placeholder: 'admins.optional',
  hint: 'admins.phone_hint',
};

const EMAIL: Field = {
  name: 'email',
  label: 'f.email',
  type: 'text',
  placeholder: 'admins.optional',
  hint: 'admins.email_hint',
};

const STATUS: Field = {
  name: 'status',
  label: 'f.enabled',
  type: 'switch',
  default: '1',
  hint: 'admins.status_hint',
};

/**
 * 角色候选：`GET /admin/v1/role`（Route::resource，列表挂在资源名本身，没有 /role/list）
 * → `[{value: hashid, label: 名称（slug）}]`。
 *
 * limit 取大值：角色是后台的少数实体，一次拉全 —— 走默认 15 条时分页之外的选项会凭空消失
 * （下拉里少一批 = 运营以为「本来就没这个角色」，同 GameServers 取 200 的口径）。
 * 停用的角色照给但标注出来：服务端 sync 照样收，只是它不再授予任何权限（AdminPermission 按 status 判停用）。
 */
const roleOptions = async (): Promise<FieldOption[]> => {
  const data = await api<{ list?: Row[] }>('/admin/v1/role', { query: { limit: 200 } });
  return (data?.list ?? [])
    .filter((role) => rowId(role) !== '')
    .map((role) => ({
      value: rowId(role),
      // 启停态影响用哪条文案 ⇒ 键是静态的，name/slug 走 params（见 lib/crud.ts 的 FieldOption）
      label: Number(role.status) === 1 ? 'admins.role_option' : 'admins.role_option_off',
      params: { name: String(role.name ?? ''), slug: String(role.slug ?? '') },
    }));
};

const ROLE_IDS: Field = {
  name: 'role_ids',
  label: 'f.roles',
  // 多选列表（不是权限树）：值是 hashid 数组 ⇄ 换行分隔串，提交时还原成数组
  type: 'multi',
  options: roleOptions,
  hint: 'admins.roles_hint',
};

const ADMIN_FIELDS: Field[] = [USERNAME, PASSWORD, REAL_NAME, PHONE, EMAIL, STATUS, ROLE_IDS];

/* ------------------------------- 动作 ------------------------------- */

/**
 * 重置密码 —— 走的是 update 端点，**没有**独立的重置端点：`password` = 新密码，
 * `admin_password` = **当前操作者**的密码（二次确认）。两个字段名不同、缺一不可：
 * 只发 password 会被 `confirmPassword` 按空密码挡下（422），而服务端不会告诉你少了哪个字段。
 */
const RESET_PASSWORD: CrudAction = {
  label: 'admins.reset_password',
  title: 'admins.reset_password',
  path: (id) => `/admin/v1/user/${id}`,
  method: 'PUT',
  fields: [
    {
      name: 'password',
      label: 'admins.new_password',
      type: 'password',
      required: true,
      placeholder: 'admins.password_len',
      hint: 'admins.new_password_hint',
    },
    {
      name: 'admin_password',
      label: 'admins.current_password',
      type: 'password',
      required: true,
      hint: 'admins.current_password_hint',
    },
  ],
  confirm: (row) => t('admins.reset_confirm', { name: labelOf(row, 'username') }),
};

/* ------------------------------- 模块配置 ------------------------------- */

export const ADMIN_USER_CRUD: CrudConfig = {
  base: '/admin/v1/user',
  noun: 'nav.admins',
  fields: ADMIN_FIELDS,
  // 三个字段不在编辑表单里，各有各的理由：
  // - username：update 的 validator 里没有它（账号名是唯一键）⇒ 只读展示，摆明「改不了」
  // - password：改密码只能走「重置密码」动作（那条路会同时带 admin_password）；
  //   留在这里编辑会发出**没有二次确认**的 password ⇒ 必然 422
  // - status：启停只有行内那一个按钮，而那个按钮**带自我保护守卫**（见 blockSelf）。
  //   编辑框里再摆一个开关就成了第二条路：对自己翻开、保存即把自己停用，守卫整条绕过
  editFields: locked(
    ADMIN_FIELDS.filter((field) => field.name !== 'password' && field.name !== 'status'),
    'username',
    'admins.username_locked',
  ),
  // 删除确认里的对象标识：就是列表第一列的用户名
  labelKey: 'username',
  // Route::resource ⇒ 新建是 POST 到资源名本身，没有 /user/create 段（同 /role、/permission）
  createPath: '/admin/v1/user',
  // 无独立启停端点（只有批量 /user/batch/status，且批量不在此模块的交付范围）：单行启停走 PUT {status}，
  // validator 收 in:0,1
  toggle: 'update',
  // 不许停用/删除当前登录的自己（见 blockSelf）
  toggleBlock: blockSelf,
  // 删除要当前登录密码（UserController::destroy 走 confirmPassword），且是软删除
  deleteBody: (row) => {
    const blocked = blockSelf(row);
    if (blocked !== null) {
      window.alert(blocked);
      return null; // null = 用户取消，RowBrowser 连确认框都不弹、一个请求都不发
    }
    return deleteWithPassword(t('admins.delete_confirm', { name: labelOf(row, 'username') }));
  },
  actions: [RESET_PASSWORD],
};
