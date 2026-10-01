/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 各模块的写操作字段描述 —— 全仓唯一一份。值域/长度/必填一律照控制器 validator 抄
 * （app/admin/v1/controller/<X>Controller.php 的 create/update），不照抄旧前端。
 *
 * 通用口径（由 lib/crud.ts 执行，本文件只声明字段）：
 * - 新建留空即不提交 ⇒ 服务端的列默认值与 input() 缺省值说了算，默认值只写在后端一处；
 *   switch 的 default 与之一致。
 * - 编辑只提交改动过的字段（update 端点都是 sometimes 局部更新）。
 * - 「创建后不可改」＝ update 的 validator/fill 里没有这个字段，标 readOnly 摆明这件事，
 *   不寄望用户看不出来（见各 editFields）。
 * 挂法见 pages/TabPage.tsx 的 Group.crud。
 */
import type { CrudConfig } from '../components/RowBrowser';
import type { Row } from '../components/DataTable';
// 带 .ts 后缀：modules.test.ts 要在 node --test 下直接 import 本文件（拿后端的路由表对账路径与方法）
import { t, type MessageKey } from '../i18n/index.ts';
import { labelOf, raw, type Field, type FieldOption } from '../lib/crud.ts';
import { api, type Envelope } from '../lib/api.ts';
import { treeNodes, treeOptions, type TreeNode } from '../lib/tree.ts';

/**
 * 把某些字段改成只读（编辑表单用）：不提交，只解释为什么改不了。
 * export 给 adminUsers.ts（管理员模块单独一个文件，见该文件头）；**不要**反过来让本文件 import 它 ——
 * 它在求值期就要调 locked，双向依赖会撞上本条 const 的 TDZ。
 */
export const locked = (fields: Field[], names: string | string[], hint: MessageKey): Field[] => {
  const list = Array.isArray(names) ? names : [names];
  return fields.map((field) => (list.includes(field.name) ? { ...field, readOnly: true, hint } : field));
};

/* ---------------------------------- 游戏 ---------------------------------- */

const GAME_FIELDS: Field[] = [
  { name: 'name', label: 'f.game_name', type: 'text', required: true, placeholder: 'f.max_100_characters' },
  { name: 'slug', label: 'f.game_slug', type: 'text', required: true, placeholder: 'f.lowercase_letters_digits_max_50' },
  {
    name: 'type',
    label: 'f.game_type',
    type: 'select',
    required: true,
    options: [
      { value: 'self', label: 'f.self_developed_self' },
      { value: 'embedded', label: 'f.embedded_embedded' },
      { value: 'third_party', label: 'f.third_party_third_party' },
    ],
  },
  {
    name: 'platform',
    label: 'f.client_platform',
    type: 'select',
    default: 'h5',
    options: [
      { value: 'h5' },
      { value: 'unity' },
      { value: 'web' },
      { value: 'native' },
    ],
  },
  { name: 'region', label: 'f.operating_region', type: 'text', default: 'global', placeholder: 'f.global_cn_us_max_10' },
  { name: 'description', label: 'f.game_description', type: 'textarea' },
  { name: 'cover_image', label: 'f.cover_image', type: 'image', placeholder: 'f.image_url_max_255' },
  { name: 'api_endpoint', label: 'f.api_endpoint', type: 'text', placeholder: 'f.third_party_game_callback_url' },
  { name: 'api_key', label: 'f.api_key', type: 'text', placeholder: 'f.provided_by_the_third_party' },
  { name: 'api_secret', label: 'f.api_secret', type: 'text', placeholder: 'f.leave_empty_when_editing_to' },
  { name: 'sdk_version', label: 'f.sdk_version', type: 'text', placeholder: 'f.self_embedded_max_20' },
  { name: 'sort', label: 'f.sort_order', type: 'number', placeholder: 'f.smaller_values_sort_first' },
  { name: 'status', label: 'f.listed', type: 'switch' },
];

export const GAME_CRUD: CrudConfig = {
  base: '/admin/v1/game',
  noun: 'f.games',
  fields: GAME_FIELDS,
  // slug 是游戏标识（唯一键），后端 update 既不校验也不写入 —— 只读展示，别假装能改
  editFields: locked(GAME_FIELDS, 'slug', 'f.the_game_slug_cannot_be'),
  labelKey: 'name',
  // 游戏没有独立 toggle 端点，状态变更走 update（PUT {status}）
  toggle: 'update',
};

/* ---------------------------------- 公告 ---------------------------------- */

const ANNOUNCEMENT_FIELDS: Field[] = [
  { name: 'title', label: 'f.title', type: 'text', required: true, placeholder: 'f.max_255_characters' },
  { name: 'content', label: 'f.content', type: 'textarea', required: true },
  {
    name: 'type',
    label: 'f.type',
    type: 'select',
    required: true,
    default: 'system',
    options: [
      { value: 'system', label: 'f.system_announcement_system' },
      { value: 'game', label: 'f.game_announcement_game' },
      { value: 'payment', label: 'f.payment_announcement_payment' },
    ],
  },
  // 控制器 create 的缺省是 1（已发布），与库里「0=草稿 1=已发布」一致
  { name: 'status', label: 'f.listed', type: 'switch', default: '1' },
];

export const ANNOUNCEMENT_CRUD: CrudConfig = {
  base: '/admin/v1/announcement',
  noun: 'f.announcements',
  fields: ANNOUNCEMENT_FIELDS,
  labelKey: 'title',
  toggle: '/admin/v1/announcement/toggle',
};

/* -------------------------------- 游戏分类 -------------------------------- */

const CATEGORY_FIELDS: Field[] = [
  { name: 'name', label: 'f.category_name', type: 'text', required: true, placeholder: 'f.max_50_characters' },
  { name: 'slug', label: 'f.category_slug', type: 'text', required: true, placeholder: 'f.lowercase_letters_digits' },
  { name: 'icon', label: 'f.category_icon', type: 'image', placeholder: 'f.icon_url_max_255' },
  { name: 'sort', label: 'f.sort_order', type: 'number', placeholder: 'f.smaller_values_sort_first_must' },
];

export const CATEGORY_CRUD: CrudConfig = {
  base: '/admin/v1/game/category',
  noun: 'f.categories',
  // create 不接受 status（代码里固定写 1）⇒ 只有编辑表单里有它
  fields: CATEGORY_FIELDS,
  editFields: [...locked(CATEGORY_FIELDS, 'slug', 'f.the_category_slug_cannot_be'), { name: 'status', label: 'f.enabled', type: 'switch', default: '1' }],
  labelKey: 'name',
  // 无独立 toggle 端点，状态变更走 update（PUT {status}）
  toggle: 'update',
  actions: [
    {
      label: 'f.assign_games',
      title: 'f.assign_games_to_this_category',
      path: () => '/admin/v1/game/category/assign',
      body: (id) => ({ category_id: id }),
      fields: [
        {
          name: 'game_ids',
          label: 'f.game_hashid',
          type: 'lines',
          required: true,
          placeholder: 'f.one_per_line',
          hint: 'f.from_the_games_list_id',
        },
      ],
    },
  ],
};

/* -------------------------------- 游戏区服 -------------------------------- */

const SERVER_FIELDS: Field[] = [
  { name: 'name', label: 'f.server_name', type: 'text', required: true, placeholder: 'f.max_50_characters' },
  { name: 'region', label: 'f.region', type: 'text', placeholder: 'f.e_g_cn_us_max' },
  {
    name: 'status',
    label: 'f.status',
    type: 'select',
    default: '1',
    options: [
      { value: '0', label: 'f.n_0_maintenance' },
      { value: '1', label: 'f.n_1_normal' },
      { value: '2', label: 'f.n_2_hot' },
      { value: '3', label: 'f.n_3_new' },
    ],
    // 四态枚举，不是 0/1 开关 ⇒ 行内启停不适用（rowact 的启用/停用只按 0/1 判）
    hint: 'f.column_comment_0_maintenance_1',
  },
  { name: 'sort', label: 'f.sort_order', type: 'number', placeholder: 'f.smaller_values_sort_first_must' },
];

/** 区服挂在游戏下（列表端点必填 game_id），故按当前选中的游戏生成一套描述。 */
export function serverCrud(gameId: string): CrudConfig {
  const game: Field = {
    name: 'game_id',
    label: 'f.game',
    type: 'text',
    required: true,
    default: gameId,
    hint: 'f.the_currently_selected_game_id',
  };
  return {
    base: '/admin/v1/game/server',
    noun: 'f.game_servers',
    fields: [game, ...SERVER_FIELDS],
    editFields: [{ ...game, readOnly: true, hint: 'f.a_server_cannot_be_moved' }, ...SERVER_FIELDS],
    labelKey: 'name',
  };
}

/* -------------------------------- 运营活动 -------------------------------- */

const ACTIVITY_FIELDS: Field[] = [
  {
    name: 'type',
    label: 'f.activity_type',
    type: 'select',
    required: true,
    options: [
      { value: 'signin', label: 'f.daily_check_in_signin' },
      { value: 'daily_task', label: 'f.daily_tasks_daily_task' },
      { value: 'invite', label: 'f.invite_invite' },
    ],
    hint: 'f.immutable_after_creation_the_config',
  },
  { name: 'name', label: 'f.activity_name', type: 'text', required: true, placeholder: 'f.max_100_characters' },
  {
    name: 'game_id',
    label: 'f.applicable_game',
    type: 'number',
    placeholder: 'f.numeric_game_id',
    hint: 'f.n_0_or_empty_all_games',
  },
  {
    name: 'status',
    label: 'f.status',
    type: 'select',
    required: true,
    default: '0',
    options: [
      { value: '0', label: 'f.n_0_disabled' },
      { value: '1', label: 'f.n_1_enabled' },
      { value: '2', label: 'f.n_2_ended' },
    ],
    hint: 'f.a_three_state_enum_hence',
  },
  { name: 'start_at', label: 'f.start_time', type: 'text', placeholder: raw('2026-01-01 00:00:00') },
  { name: 'end_at', label: 'f.end_time', type: 'text', placeholder: 'f.must_be_on_or_after' },
  { name: 'rollout_percent', label: 'f.rollout_percentage', type: 'number', placeholder: 'f.n_0_100_empty_100' },
  {
    name: 'config',
    label: 'f.activity_config_json',
    type: 'json',
    placeholder: raw('{"rewards": [{"day": 1, "reward": {"type": "platform_coin", "amount": "100"}}]}'),
    hint: 'f.must_be_valid_json_matching',
  },
];

export const ACTIVITY_CRUD: CrudConfig = {
  base: '/admin/v1/activities',
  noun: 'f.activities',
  fields: ACTIVITY_FIELDS,
  editFields: locked(ACTIVITY_FIELDS, 'type', 'f.the_activity_type_cannot_be'),
  labelKey: 'name',
};

/* --------------------------------- 成就 ---------------------------------- */

const ACHIEVEMENT_FIELDS: Field[] = [
  { name: 'key', label: 'f.achievement_key', type: 'text', required: true, placeholder: 'f.lowercase_letters_digits_underscore_max' },
  { name: 'name', label: 'f.achievement_name', type: 'text', required: true, placeholder: 'f.max_100_characters' },
  { name: 'description', label: 'f.achievement_description', type: 'textarea', placeholder: 'f.max_500_characters' },
  { name: 'icon', label: 'f.icon', type: 'image', placeholder: 'f.icon_url' },
  {
    name: 'condition_json',
    label: 'f.unlock_condition_json',
    type: 'json',
    required: true,
    placeholder: raw('{"event": "login", "count": 7}'),
    hint: 'f.must_parse_into_a_json',
  },
  { name: 'points', label: 'f.achievement_points', type: 'number', required: true, placeholder: raw('≥ 0') },
  {
    name: 'status',
    label: 'f.enabled',
    type: 'switch',
    default: '1',
    hint: 'f.disabling_only_affects_whether_future',
  },
];

export const ACHIEVEMENT_CRUD: CrudConfig = {
  base: '/admin/v1/achievement',
  noun: 'f.achievements',
  fields: ACHIEVEMENT_FIELDS,
  editFields: locked(ACHIEVEMENT_FIELDS, 'key', 'f.the_achievement_key_is_the'),
  labelKey: 'name',
  toggle: '/admin/v1/achievement/toggle',
};

/* ------------------------------- VIP 等级 -------------------------------- */

const VIP_FIELDS: Field[] = [
  { name: 'level', label: 'f.level', type: 'number', required: true, placeholder: raw('≥ 0') },
  { name: 'name', label: 'f.level_name', type: 'text', required: true, placeholder: 'f.max_50_characters' },
  { name: 'required_exp', label: 'f.required_exp', type: 'number', required: true, placeholder: raw('≥ 0') },
  {
    name: 'benefits',
    label: 'f.benefits_json',
    type: 'json',
    required: true,
    placeholder: raw('{"exchange_discount": "0.05"}'),
    hint: 'f.a_json_object_keys_may',
  },
];

export const VIP_CRUD: CrudConfig = {
  base: '/admin/v1/vip/level',
  noun: 'f.vip_levels',
  fields: VIP_FIELDS,
  editFields: locked(VIP_FIELDS, 'level', 'f.the_level_number_is_the'),
  labelKey: 'name',
};

/* -------------------------------- 排行榜 --------------------------------- */

const LEADERBOARD_FIELDS: Field[] = [
  { name: 'name', label: 'f.leaderboard_name', type: 'text', required: true, placeholder: 'f.max_100_characters' },
  {
    name: 'type',
    label: 'f.period',
    type: 'select',
    required: true,
    options: [
      { value: 'daily', label: 'f.daily_daily' },
      { value: 'weekly', label: 'f.weekly_weekly' },
      { value: 'monthly', label: 'f.monthly_monthly' },
      { value: 'alltime', label: 'f.alltime_all_time' },
    ],
  },
  {
    name: 'metric',
    label: 'f.ranking_metric',
    type: 'select',
    required: true,
    options: [
      { value: 'earned', label: 'f.earned' },
      { value: 'spent', label: 'f.spent' },
      { value: 'play_count', label: 'f.play_count' },
    ],
  },
  {
    name: 'game_id',
    label: 'f.linked_game',
    type: 'text',
    placeholder: 'f.game_hashid',
    hint: 'f.taken_from_the_id_column',
  },
  { name: 'rule', label: 'f.ranking_rule', type: 'textarea', placeholder: 'f.fill_in_per_the_json', hint: 'f.the_server_does_not_validate' },
  // 控制器 create 的缺省是 0（未启用），与库里的默认值 1 不同 —— 以控制器为准
  { name: 'status', label: 'f.enabled', type: 'switch', default: '0' },
  { name: 'sort', label: 'f.sort_order', type: 'number', placeholder: 'f.smaller_values_sort_first_must' },
];

export const LEADERBOARD_CRUD: CrudConfig = {
  base: '/admin/v1/leaderboard',
  noun: 'f.leaderboards',
  fields: LEADERBOARD_FIELDS,
  editFields: locked(LEADERBOARD_FIELDS, 'game_id', 'f.the_linked_game_cannot_be'),
  labelKey: 'name',
  // 无独立 toggle 端点，状态变更走 update（PUT {status}）
  toggle: 'update',
  actions: [
    {
      label: 'f.refresh_cache',
      path: (id) => `/admin/v1/leaderboard/${id}/refresh`,
      confirm: () => t('f.clear_and_recompute_the_cache'),
    },
  ],
};

/* ------------------------------- 国家配置 -------------------------------- */

const COUNTRY_FIELDS: Field[] = [
  { name: 'country_code', label: 'f.country_code', type: 'text', required: true, placeholder: 'f.two_letters_e_g_cn', hint: 'f.country_code_iso_3166_1' },
  { name: 'currency', label: 'f.currency_code', type: 'text', required: true, placeholder: 'f.three_letters_e_g_cny', hint: 'f.iso_4217_upper_cased_on' },
  {
    name: 'payment_methods',
    label: 'f.payment_methods_json',
    type: 'json',
    placeholder: raw('{"paypal": {"min": "1.0000", "enabled": true}}'),
    hint: 'f.key_gateway_provider_value_holds',
  },
  {
    name: 'withdraw_methods',
    label: 'f.withdraw_methods_json',
    type: 'json',
    placeholder: raw('{"paypal": {"min": "10.0000", "enabled": true}}'),
    hint: 'f.key_paypal_bank_crypto_value',
  },
  {
    // 金额写字符串：不转浮点（仓库铁律：金额不参与浮点运算），并保住小数位，显示与提交一致
    name: 'min_deposit',
    label: 'f.minimum_deposit',
    type: 'text',
    placeholder: 'f.e_g_1_0000',
    hint: 'f.a_decimal_digit_string_empty',
  },
];

export const COUNTRY_CRUD: CrudConfig = {
  base: '/admin/v1/country/config',
  noun: 'f.country_configs',
  // create 不接受 status（代码里固定写 1）⇒ 只有编辑表单里有它
  fields: COUNTRY_FIELDS,
  editFields: [...locked(COUNTRY_FIELDS, 'country_code', 'f.the_country_code_cannot_be'), { name: 'status', label: 'f.enabled', type: 'switch', default: '1' }],
  labelKey: 'country_code',
  toggle: '/admin/v1/country/config/toggle',
};

/* ------------------------------- 系统配置 -------------------------------- */

const CONFIG_FIELDS: Field[] = [
  { name: 'group', label: 'f.group', type: 'text', required: true, placeholder: 'f.max_100_characters' },
  { name: 'key', label: 'f.config_key', type: 'text', required: true, placeholder: 'f.max_100_characters' },
  {
    name: 'value',
    label: 'f.config_value',
    type: 'textarea',
    required: true,
    hint: 'f.parsed_per_type_json_requires',
  },
  {
    name: 'type',
    label: 'f.value_type',
    type: 'select',
    default: 'string',
    options: [
      { value: 'string' },
      { value: 'int' },
      { value: 'bool' },
      { value: 'json' },
    ],
    hint: 'f.only_affects_how_the_value',
  },
  { name: 'description', label: 'f.description', type: 'text', placeholder: 'f.max_255_characters' },
];

export const CONFIG_CRUD: CrudConfig = {
  base: '/admin/v1/config',
  noun: 'f.config_items',
  // 这个模块的路径参数段叫 {id}（不是 {hashid}），值仍是 hashid；差别在 create 直接 POST 到 base
  createPath: '/admin/v1/config',
  fields: CONFIG_FIELDS,
  editFields: locked(CONFIG_FIELDS, ['group', 'key'], 'f.group_key_forms_the_unique'),
  labelKey: 'key',
  // 删除要管理员密码二次确认（BaseController::confirmPassword），不带密码必被 422 挡下；
  // 密码走请求体（DELETE 也能带 body，webman 按 content-type 解析），不进 URL
  deleteBody: (row) => {
    const password = window.prompt(t('config.delete_password_prompt', { name: labelOf(row, 'key') }));
    return password === null ? null : { password };
  },
};

/* -------------------------------- 平台用户 -------------------------------- */

/**
 * 平台用户**只有**编辑（`PlatformUserController::update` 收 nickname / status 两个字段）。
 * 没有新建端点，删除是「注销」（destroy，还带资金闸），两者都在 TabPage 的 PlatformUsers 里 ——
 * 注销要回读列表确认人真的不在了，通用 delete 撑不住，故这里不给整套 CrudConfig，只给字段。
 */
export const PLATFORM_USER_FIELDS: Field[] = [
  { name: 'nickname', label: 'f.nickname', type: 'text', placeholder: 'f.max_50_characters', hint: 'f.over_length_values_are_rejected' },
  {
    name: 'status',
    label: 'f.status',
    type: 'switch',
    hint: 'f.on_active_1_off_banned',
  },
];

/* -------------------------------- 身份审核 -------------------------------- */

/**
 * 身份记录的对象标识：优先证件上的真实姓名 —— 表格里摆的就是这一列，确认文案与屏幕上看到的对得上；
 * 没有就退回用户账号名（`user` 是嵌套对象，列表里不单列），再没有才是「该记录」。
 */
const identityLabel = (row: Row): string => {
  const realName = String(row.real_name ?? '').trim();
  const username = String(((row.user ?? {}) as Row).username ?? '').trim();
  const name = realName !== '' ? realName : username;
  return name === '' ? t('f.this_record') : name;
};

export const IDENTITY_CRUD: CrudConfig = {
  base: '/admin/v1/identity',
  noun: 'f.kyc_review',
  // 动作型：后端只有 list + review，没有增/改/删端点 ⇒ 不给 fields / labelKey，
  // 界面就只长「通过 / 驳回」两个按钮
  actions: [
    {
      label: 'f.approve',
      // review 是 PUT，且记录 id 走请求体（不经 URL）
      path: () => '/admin/v1/identity/review',
      method: 'PUT',
      body: (id) => ({ id, action: 'approve' }),
      confirm: (row) => t('identity.approve_confirm', { who: identityLabel(row) }),
    },
    {
      label: 'f.reject',
      title: 'f.reject_kyc',
      path: () => '/admin/v1/identity/review',
      method: 'PUT',
      body: (id) => ({ id, action: 'reject' }),
      fields: [
        {
          name: 'note',
          label: 'f.rejection_reason',
          type: 'textarea',
          hint: 'f.appended_verbatim_to_the_notification',
        },
      ],
      confirm: (row) => t('identity.reject_confirm', { who: identityLabel(row) }),
    },
  ],
  // review 只认 pending：已审过的记录会被 422 挡下（后端 CAS），按钮不做预判，错误原样显示
};

/* ---------------------------------- 角色 ---------------------------------- */

/**
 * 角色表单的权限树：不摊平（摊平了就没有父子联动），交给 components/PermissionTree.tsx。
 * 与 permissionOptions 同一个端点，两种呈现。
 */
const permissionTree = async (): Promise<TreeNode[]> => treeNodes(await api<unknown>('/admin/v1/permission'));

/**
 * 权限下拉的值域：同一次 GET /admin/v1/permission，摊平成 [{value: hashid, label: 路径 / 名（slug）}]。
 * 标签带完整父路径与 slug —— 各模块都有叫「列表」的节点，只给 name 分不出是哪一个。
 * 只给权限表单的「父权限」用（单选，树形下拉做不了）；角色的「权限」用树控件，不摊平。
 */
const permissionOptions = async (): Promise<FieldOption[]> => treeOptions(await permissionTree());

const ROLE_FIELDS: Field[] = [
  { name: 'name', label: 'f.role_name', type: 'text', required: true, placeholder: 'f.max_50_characters' },
  { name: 'slug', label: 'f.role_slug', type: 'text', required: true, placeholder: 'f.e_g_super_admin_max' },
  { name: 'description', label: 'f.role_description', type: 'textarea', placeholder: 'f.max_255_characters' },
  {
    name: 'status',
    label: 'f.enabled',
    type: 'switch',
    default: '1',
    hint: 'f.once_disabled_admins_holding_this',
  },
  {
    name: 'permission_ids',
    label: 'f.permissions',
    type: 'tree',
    // 树形多选：父级勾选连带子级，子级全勾父级自动勾；树开框时才拉（见 permissionTree）
    tree: permissionTree,
    hint: 'f.checking_a_parent_also_checks',
  },
];

export const ROLE_CRUD: CrudConfig = {
  base: '/admin/v1/role',
  noun: 'f.roles',
  // Route::resource：列表在 /role 本身（**不是** /role/list），新建也是 POST /role
  createPath: '/admin/v1/role',
  fields: ROLE_FIELDS,
  // update 的 validator/fill 里没有 slug（角色标识是唯一键）⇒ 只读展示
  editFields: locked(ROLE_FIELDS, 'slug', 'f.the_role_slug_cannot_be'),
  labelKey: 'name',
  // 无独立 toggle 端点，状态变更走 update（PUT {status}，validator 收 in:0,1）
  toggle: 'update',
  // 删除要管理员密码（RoleController::destroy 走 confirmPassword），且会解绑该角色的用户与权限
  deleteBody: (row) =>
    deleteWithPassword(t('role.delete_confirm', { name: labelOf(row, 'name') })),
  // permission_ids 走 hashid：RoleController::decodePermissionIds 逐个 decodeId（非法即 400，
  // 不落半截关联），index 也回传 hashid 形式的 permission_ids 供编辑态回填。
  // 编辑时 buildPayload 只发改动过的字段 ⇒ 不碰权限的那次保存不会触发 sync（sync 是整表替换）。
};

/* ---------------------------------- 权限 ---------------------------------- */

const PERMISSION_FIELDS: Field[] = [
  { name: 'name', label: 'f.permission_name', type: 'text', required: true, placeholder: 'f.max_50_characters' },
  { name: 'slug', label: 'f.permission_slug', type: 'text', required: true, placeholder: 'f.e_g_get_admin_user' },
  {
    name: 'type',
    label: 'f.type',
    type: 'select',
    required: true,
    options: [
      { value: '1', label: 'f.n_1_menu' },
      { value: '2', label: 'f.n_2_button' },
      { value: '3', label: 'f.n_3_api' },
    ],
    hint: 'f.column_comment_1_menu_2',
  },
  {
    name: 'parent_id',
    label: 'f.parent_permission',
    type: 'select',
    default: '0',
    // 值域 = 权限树 + 根节点。PermissionController::store 的 decodeParentId 收 hashid（'0'/空 = 根），
    // buildTree 回传的 parent_id 也是 hashid，故这里与列表里的父名是同一套标识。
    options: async () => [{ value: '0', label: 'f.root_node_no_parent' }, ...(await permissionOptions())],
    hint: 'f.only_selectable_on_create_update',
  },
  { name: 'icon', label: 'f.icon', type: 'text', placeholder: 'f.max_50_characters' },
  { name: 'path', label: 'f.frontend_route', type: 'text', placeholder: 'f.e_g_games_max_255' },
  { name: 'sort', label: 'f.sort_order', type: 'number', placeholder: 'f.n_0_smaller_values_sort_first' },
];

export const PERMISSION_CRUD: CrudConfig = {
  base: '/admin/v1/permission',
  noun: 'f.permissions',
  // Route::resource：列表/新建都在 /permission 本身
  createPath: '/admin/v1/permission',
  fields: PERMISSION_FIELDS,
  // update 只写 name/icon/path/sort ⇒ slug/type 标只读摆明「改不了」；
  // parent_id 是 createOnly，编辑表单里整个不给（update 不收它，换个父级还会让子树散架）
  editFields: locked(
    PERMISSION_FIELDS.filter((field) => field.name !== 'parent_id'),
    ['slug', 'type'],
    'f.cannot_be_changed_after_creation',
  ),
  labelKey: 'name',
  // 删除要管理员密码，且后端会级联删子权限、把该权限从所有角色上解绑 —— 确认文案必须说清
  deleteBody: (row) =>
    deleteWithPassword(
      t('permission.delete_confirm', { name: labelOf(row, 'name') }),
    ),
};

/* ---------------------------------- 工单 ---------------------------------- */

export const TICKET_CRUD: CrudConfig = {
  base: '/admin/v1/ticket',
  noun: 'f.tickets',
  // 动作型：后端只有 list/detail + reply/close/assign 三个状态变更，没有增/改/删
  actions: [
    {
      label: 'f.reply',
      title: 'f.reply_to_ticket',
      path: (id) => `/admin/v1/ticket/${id}/reply`,
      fields: [
        {
          name: 'content',
          label: 'f.reply_content',
          type: 'textarea',
          required: true,
          hint: 'f.appends_a_reply_as_the',
        },
      ],
    },
    {
      label: 'f.close',
      path: (id) => `/admin/v1/ticket/${id}/close`,
      confirm: (row) => t('ticket.close_confirm', { name: labelOf(row, 'subject') }),
    },
    {
      label: 'f.assign',
      title: 'f.assign_handler',
      path: (id) => `/admin/v1/ticket/${id}/assign`,
      fields: [
        {
          name: 'admin_id',
          label: 'f.handler_admin_id',
          type: 'number',
          required: true,
          hint: 'f.the_admin_s_numeric_id',
        },
      ],
    },
  ],
};

/* -------------------------------- 提现订单 -------------------------------- */

/** 订单标识：订单号（唯一键，列表里就摆着）优先，退回 hashid —— 确认文案要与屏幕上的对得上。 */
const orderLabel = (row: Row): string => {
  const no = String(row.order_no ?? '').trim();
  return no !== '' ? no : labelOf(row, 'id');
};

/**
 * 金额部分：平台币 + 到账法币。一律字符串透传 —— 金额不参与任何前端运算（也无需运算），
 * 确认文案里出现的就是库里那两个 DECIMAL 的原值。
 */
const orderMoney = (row: Row): string => {
  const currency = String(row.currency ?? '').trim();
  const fiat = String(row.fiat_amount ?? '').trim();
  const fiatText = fiat === '' || fiat === '0.0000' ? '' : t('funds.amount_arrival', { fiat, currency: currency === '' ? '' : ` ${currency}` });
  return t('funds.platform_coin', { amount: String(row.platform_amount ?? '—'), fiat: fiatText });
};

const orderStatus = (row: Row): string => String(row.status ?? '');

/** 审核备注（review / confirm / reject 共用同一个字段：后端都是 $request->input('note')）。 */
const REVIEW_NOTE: Field = {
  name: 'note',
  label: 'f.order_review_note',
  type: 'textarea',
  hint: 'f.written_into_the_order_s',
};

/**
 * 提现订单：动作型 —— 后端只有列表 + review / batch-review / execute-payout / sync-payout
 * 四个状态变更端点，没有增/改/删 ⇒ 不给 fields / labelKey。
 *
 * 状态机（见 WithdrawReviewTrait 的 CAS 条件，故按钮按 status 过滤，不摆点了必然 422 的按钮）：
 *   pending ──通过→ approved（双审开启时只是初审，仍是 pending + reviewer_id）
 *   pending ──驳回→ rejected（同一事务退款 + 记 refund 流水，不可撤销）
 *   approved ──执行打款→ processing ──同步→ 以 PayPal 为准
 * 审核与打款都 `report`：服务端的话（「初审通过，等待另一管理员确认」/「打款已提交」）决定下一步做什么。
 */
export const WITHDRAW_ORDER_CRUD: CrudConfig = {
  base: '/admin/v1/withdraw',
  noun: 'f.withdraw_orders',
  actions: [
    {
      label: 'f.approve',
      path: () => '/admin/v1/withdraw/review',
      method: 'PUT',
      body: (id) => ({ order_id: id, action: 'approve' }),
      fields: [REVIEW_NOTE],
      // reviewer_id 已有值 = 已被第一审核人处理过（双审），再点通过必然 422
      when: (row) => orderStatus(row) === 'pending' && Number(row.reviewer_id ?? 0) <= 0,
      confirm: (row) => t('funds.approve_confirm', { name: orderLabel(row), money: orderMoney(row) }),
      report: true,
    },
    {
      label: 'f.reject',
      title: 'f.reject_withdraw',
      path: () => '/admin/v1/withdraw/review',
      method: 'PUT',
      body: (id) => ({ order_id: id, action: 'reject' }),
      fields: [REVIEW_NOTE],
      when: (row) => orderStatus(row) === 'pending',
      confirm: (row) => t('funds.reject_confirm', { name: orderLabel(row), money: orderMoney(row) }),
      report: true,
    },
    {
      label: 'f.second_confirmation',
      title: 'f.second_confirmation_dual_approval_platform',
      path: () => '/admin/v1/withdraw/review',
      method: 'PUT',
      body: (id) => ({ order_id: id, action: 'confirm' }),
      fields: [REVIEW_NOTE],
      // 只有「已被第一审核人处理过、还停在 pending」的订单才谈得上二次确认；
      // 单审平台不出现此按钮（点了必然 422「双重审核未启用」）
      when: (row) => orderStatus(row) === 'pending' && Number(row.reviewer_id ?? 0) > 0,
      confirm: (row) => t('funds.second_confirm', { name: orderLabel(row), money: orderMoney(row) }),
      report: true,
    },
    {
      label: 'f.execute_payout',
      path: () => '/admin/v1/withdraw/execute-payout',
      body: (id) => ({ order_id: id }),
      when: (row) => orderStatus(row) === 'approved',
      // 真正出钱的一步：文案必须带订单标识与金额，且不可逆（失败会退回 approved 允许重试）
      confirm: (row) => t('funds.payout_confirm', { name: orderLabel(row), money: orderMoney(row) }),
      report: true,
    },
    {
      label: 'f.sync_payout',
      path: () => '/admin/v1/withdraw/sync-payout',
      body: (id) => ({ order_id: id }),
      // 没有批次号 = 还没提交给 PayPal，后端会 422「该订单尚未执行打款」
      when: (row) => String(row.payout_batch_id ?? '').trim() !== '',
      confirm: (row) => t('funds.sync_confirm', { name: orderLabel(row) }),
      // 该端点 message 是占位符 "success"，有用的是 data 里的三个状态
      report: (envelope) => {
        const data = (envelope.data ?? {}) as Row;
        return t('funds.sync_report', { payout: String(data.payout_status ?? '—'), order: String(data.order_status ?? '—'), batch: String(data.synced_status ?? '—') });
      },
    },
  ],
};

/* -------------------------------- 提现开关 -------------------------------- */

/**
 * 全局提现开关（GET / PUT 共用 /admin/v1/withdraw/switch）。**不是行列表**：读回来是一个对象
 * `{global_switch, enabled, status}`（同一个布尔值的三种键名），写的是 `{enabled: 0|1}` ⇒
 * RowBrowser 表达不了，走 pages/funds.tsx 的 WithdrawSwitch。
 */

/* -------------------------------- 阶梯限额 -------------------------------- */

/** 金额/费率一律 text：DECIMAL(18,4) 的字符串原样进出，不经浮点（浮点会吃掉小数位）。 */
const LIMIT_FIELDS: Field[] = [
  {
    name: 'user_level',
    label: 'f.tier',
    type: 'text',
    readOnly: true,
    hint: 'f.default_verified_vip_tiers_are',
  },
  { name: 'single_min', label: 'f.minimum_per_transaction', type: 'text', hint: 'f.e_g_1_0000_must' },
  { name: 'single_max', label: 'f.maximum_per_transaction', type: 'text', hint: 'f.e_g_1000_0000_0' },
  { name: 'daily_limit', label: 'f.daily_limit', type: 'text' },
  { name: 'monthly_limit', label: 'f.monthly_limit', type: 'text' },
  {
    name: 'fee_pct',
    label: 'f.fee_rate',
    type: 'text',
    hint: 'f.must_be_less_than_100',
  },
  { name: 'fee_max', label: 'f.fee_cap', type: 'text', hint: 'f.n_0_no_cap' },
  {
    name: 'auto_approve_threshold',
    label: 'f.auto_review_threshold',
    type: 'text',
    hint: 'f.a_platform_coin_amount_an',
  },
];

/**
 * 阶梯限额：只有 PUT {hashid}（单档精调），没有新建/删除端点 —— `createPath: null` 去掉「+ 新建」，
 * 缺 labelKey 去掉「删除」。全档位一把写走 pages/funds.tsx 的「全档位重置」（POST limits/set）。
 */
export const WITHDRAW_LIMIT_CRUD: CrudConfig = {
  base: '/admin/v1/withdraw/limits',
  noun: 'f.withdraw_tiers',
  createPath: null,
  fields: LIMIT_FIELDS,
};

/* -------------------------------- 支付方式 -------------------------------- */

/** provider 值域：create/update 的 `in:` 列表，一字不差。 */
const PAYMENT_PROVIDERS: FieldOption[] = [
  'stripe',
  'nowpayments',
  'coinbase',
  'paypal',
  'skrill',
  'neteller',
  'paysafecard',
  'paytm',
  'mercadopago',
  'astropay',
  'paypay',
  'kakaopay',
  'gcash',
  'mpesa',
  'paystack',
  'toss',
  'adyen',
  'grabpay',
].map((value): FieldOption => ({ value }));

const PAYMENT_FIELDS: Field[] = [
  { name: 'name', label: 'f.name', type: 'text', required: true, placeholder: 'f.e_g_usdt_trc20_max' },
  {
    name: 'type',
    label: 'f.type',
    type: 'select',
    required: true,
    options: [
      { value: 'fiat', label: 'f.fiat' },
      { value: 'crypto', label: 'f.crypto' },
    ],
  },
  { name: 'provider', label: 'f.provider', type: 'select', required: true, options: PAYMENT_PROVIDERS, hint: 'f.determines_which_payment_gateway_is' },
  // create 的 validator 里 status 是必填（没有 input() 缺省值），故开关一定会上送；
  // 初值取库列默认值 0（禁用）：配置没核过就不该对用户可见，配好再在列表里点启用
  { name: 'status', label: 'f.enabled', type: 'switch', default: '0' },
  { name: 'sort', label: 'f.sort_order', type: 'number', placeholder: 'f.n_0_smaller_values_sort_first' },
  {
    name: 'countries',
    label: 'f.visible_countries',
    type: 'lines',
    placeholder: 'f.one_per_line_e_g',
    hint: 'f.iso_3166_1_alpha_2',
  },
  { name: 'currency', label: 'f.restricted_currencies', type: 'text', placeholder: 'f.e_g_usd_max_10' },
  { name: 'min_amount', label: 'f.minimum_amount', type: 'text', hint: 'f.a_decimal_amount_in_the' },
  { name: 'max_amount', label: 'f.maximum_amount', type: 'text', hint: 'f.n_0_unlimited_must_not_be' },
  {
    name: 'config',
    label: 'f.payment_config',
    type: 'textarea',
    placeholder: raw('{"network":"TRC20"}'),
    hint: 'f.gateway_parameter_json_text_stored',
  },
];

export const PAYMENT_CRUD: CrudConfig = {
  base: '/admin/v1/payment/method',
  noun: 'f.payment_methods',
  createPath: '/admin/v1/payment/method/create',
  fields: PAYMENT_FIELDS,
  labelKey: 'name',
  toggle: '/admin/v1/payment/method/toggle',
  // 删除会被后端按「存在待支付订单」拒绝（422），message 原样显示；无密码二次确认
};

/* --------------------------------- 优惠券 --------------------------------- */

const COUPON_FIELDS: Field[] = [
  { name: 'name', label: 'f.name', type: 'text', required: true, placeholder: 'f.max_100_characters' },
  {
    name: 'type',
    label: 'f.type',
    type: 'select',
    required: true,
    options: [
      { value: 'fixed', label: 'f.fixed_fixed_amount' },
      { value: 'rate', label: 'f.rate_percentage_discount' },
    ],
  },
  {
    name: 'value',
    label: 'f.face_value_discount_rate',
    type: 'text',
    required: true,
    hint: 'f.fixed_platform_coin_amount_e',
  },
  { name: 'min_amount', label: 'f.minimum_spend', type: 'text', hint: 'f.a_platform_coin_amount_empty' },
  { name: 'max_discount', label: 'f.maximum_discount', type: 'text', hint: 'f.only_used_by_the_rate' },
  {
    name: 'game_id',
    label: 'f.applicable_game',
    type: 'text',
    placeholder: 'f.game_hashid',
    hint: 'f.picked_from_the_games_list',
  },
  { name: 'total_qty', label: 'f.total_issuance', type: 'number', hint: 'f.n_0_unlimited' },
  { name: 'user_limit', label: 'f.per_user_limit', type: 'number', hint: 'f.n_1_empty_makes_the_backend' },
  {
    name: 'start_at',
    label: 'f.start_time',
    type: 'text',
    placeholder: raw('2026-01-01 00:00:00'),
    hint: 'f.a_date_time_string_empty',
  },
  { name: 'end_at', label: 'f.end_time', type: 'text', placeholder: raw('2026-01-01 00:00:00'), hint: 'f.must_be_the_start_time' },
];

export const COUPON_CRUD: CrudConfig = {
  base: '/admin/v1/coupon',
  noun: 'f.coupons',
  createPath: '/admin/v1/coupon/create',
  fields: COUPON_FIELDS,
  // create 把 status 硬编码成 1（已启用）⇒ 开关只出现在编辑表单里。
  // conditions（使用条件 JSON）也放进来，但**只读**：create/update 都不收它（$request->only 里没有），
  // 表单里摆一个能输入的框等于骗人；它是 C 端 claim 的准入条件（conditions.game_id 等），只由直写库设置。
  editFields: [
    ...COUPON_FIELDS,
    { name: 'status', label: 'f.enabled', type: 'switch', default: '1' },
    {
      name: 'conditions',
      label: 'f.eligibility_read_only',
      type: 'json',
      readOnly: true,
      hint: 'f.the_eligibility_rule_applied_when',
    },
  ],
  labelKey: 'name',
  // 没有单条详情端点（GET /coupon/{hashid} 不存在），只有 stats ⇒ 只读视图（不是 detailBase，那会 404）
  views: [{ label: 'f.statistics', title: 'f.coupon_statistics', path: (id) => `/admin/v1/coupon/${id}/stats` }],
  // 「已有用户领取」时后端拒绝编辑（400），message 原样显示；「删除会连带删掉所有领取记录」后端不拦
};

/* -------------------------------- CDN 厂商 -------------------------------- */

const CDN_FIELDS: Field[] = [
  { name: 'name', label: 'f.display_name', type: 'text', required: true, placeholder: 'f.max_50_characters' },
  {
    name: 'provider',
    label: 'f.vendor',
    type: 'select',
    required: true,
    options: [
      { value: 'cloudflare' },
      { value: 'cloudfront' },
      { value: 'aliyun' },
      { value: 'tencent' },
      { value: 'huawei' },
    ],
    hint: 'f.the_vendor_is_the_unique',
  },
  {
    name: 'config',
    label: 'f.config_json',
    type: 'json',
    placeholder: raw('{"bucket":"static","domain":"cdn.example.com"}'),
    hint: 'f.credentials_bucket_domain_stored_encrypted',
  },
  // create 必填 status（无 input() 缺省值）；初值取库列默认值 1 但种子行都是停用 —— 给 0，
  // 新建即对外服务太危险，连通测试通过后再启用
  { name: 'status', label: 'f.enabled', type: 'switch', default: '0' },
  { name: 'sort', label: 'f.sort_order', type: 'number', placeholder: 'f.n_0_smaller_values_sort_first' },
];

export const CDN_CRUD: CrudConfig = {
  base: '/admin/v1/cdn/provider',
  noun: 'f.cdn_vendors',
  createPath: '/admin/v1/cdn/provider/create',
  fields: CDN_FIELDS,
  labelKey: 'name',
  toggle: '/admin/v1/cdn/provider/toggle',
  actions: [
    {
      label: 'f.connectivity_test',
      title: 'f.connectivity_test',
      path: () => '/admin/v1/cdn/provider/test',
      body: (id) => ({ id }),
      // 结果就地显示：成功是「连通正常」，失败是探测抛出的原话（如凭据无效），不吞
      report: true,
    },
  ],
};

/* --------------------------------- 风控规则 -------------------------------- */

/** 规则类型值域 = RiskSandboxService::TYPES（与 service 侧评估器注册表一致）。 */
const RULE_TYPES: FieldOption[] = [
  'ip_blacklist',
  'amount_anomaly',
  'frequency',
  'velocity',
  'device_fingerprint',
  'ip_reputation',
  'device_account_graph',
  'withdraw_pattern',
].map((value): FieldOption => ({ value }));

/**
 * config 的键随 type 变，白名单在服务端（RiskRuleController::CONFIG_KEYS 与 INT_BOUNDS）。
 * 静态把每个 type 的键与值域列全 —— 前端**不再实现一套校验**（服务端是唯一真值，多一套必然漂移），
 * 这里只负责让运营知道该写哪些键、边界在哪。写错键名服务端会明确拒绝（不会静默忽略）。
 */
const RULE_CONFIG_HINT: MessageKey = 'f.rule_config_keys_and_bounds';

const RISK_RULE_FIELDS: Field[] = [
  { name: 'name', label: 'f.rule_name', type: 'text', required: true, placeholder: 'f.max_100_characters' },
  { name: 'type', label: 'f.rule_type', type: 'select', required: true, options: RULE_TYPES, hint: 'f.determines_which_keys_config_accepts' },
  {
    name: 'action',
    label: 'f.hit_action',
    type: 'select',
    required: true,
    options: [
      { value: 'log', label: 'f.log_record_only' },
      { value: 'warn', label: 'f.warn' },
      { value: 'block', label: 'f.block' },
    ],
    hint: 'f.block_really_blocks_on_a',
  },
  {
    name: 'scope',
    label: 'f.scope',
    type: 'select',
    default: 'all',
    options: [
      { value: 'all', label: 'f.all' },
      { value: 'deposit', label: 'f.deposit' },
      { value: 'withdraw', label: 'f.withdraw' },
      { value: 'exchange', label: 'f.exchange' },
      { value: 'login', label: 'f.login' },
    ],
    hint: 'f.default_all_all_four_check',
  },
  { name: 'config', label: 'f.rule_config_json', type: 'json', required: true, default: '{}', placeholder: raw('{"window_minutes": 60, "max_count": 5}'), hint: RULE_CONFIG_HINT },
  { name: 'priority', label: 'f.priority', type: 'number', default: '100', hint: 'f.n_0_1000_larger_evaluates_first' },
  { name: 'status', label: 'f.enabled', type: 'switch', default: '1', hint: 'f.once_disabled_it_no_longer' },
];

/**
 * 风控规则。三处与别处不同：
 * - **编辑是全量语义**：update 与 create 共用 fill()，name/type/action 一律从请求体读且必填 ⇒ `fullEdit`。
 * - **启停无请求体**：POST {hashid}/toggle 由服务端自己翻转（不是「客户端给 status」）。
 * - **没有删除端点**：规则是审计对象，下线用「停用」⇒ 不给 labelKey（界面上就没有删除按钮）。
 */
export const RISK_RULE_CRUD: CrudConfig = {
  base: '/admin/v1/risk/rule',
  noun: 'f.risk_rules',
  fields: RISK_RULE_FIELDS,
  fullEdit: true,
  toggle: (id) => ({ path: `/admin/v1/risk/rule/${id}/toggle` }),
  actions: [
    {
      label: 'f.dry_run',
      title: 'f.sandbox_dry_run_read_only',
      path: () => '/admin/v1/risk/rule/test',
      body: (id) => ({ rule_id: id }),
      fields: [
        {
          name: 'user_id',
          label: 'f.user_hashid',
          type: 'text',
          required: true,
          hint: 'f.taken_from_the_user_id',
        },
        {
          name: 'check_type',
          label: 'f.check_stage',
          type: 'select',
          required: true,
          default: 'login',
          options: [
            { value: 'login', label: 'f.login' },
            { value: 'deposit', label: 'f.deposit' },
            { value: 'withdraw', label: 'f.withdraw' },
            { value: 'exchange', label: 'f.exchange' },
          ],
          hint: 'f.frequency_type_rules_query_per',
        },
        {
          name: 'context',
          label: 'f.context_json_object',
          type: 'jsonobj',
          placeholder: raw('{"ip": "1.2.3.4", "user_agent": "Mozilla/5.0", "amount": "5000"}'),
          hint: 'f.the_evaluator_reads_the_four',
        },
      ],
      // 结果全在 data 里（信封 message 恒为 "success"）：只显示 message 等于把试算结果抹掉
      report: (envelope) => {
        const data = (envelope.data ?? {}) as Row;
        const verdict = t(data.matched === true ? 'f.hit' : 'f.no_hit');
        return t('risk.dry_run_report', {
          verdict,
          severity: String(data.severity ?? '—'),
          action: String(data.action ?? '—'),
          message: String(data.message ?? ''),
        });
      },
    },
  ],
};

/* --------------------------------- 风控事件 -------------------------------- */

const EVENT_NOTE: Field = {
  name: 'note',
  label: 'f.action_note',
  type: 'textarea',
  hint: 'f.a_request_parameter_written_into',
};

/** 事件标识：规则名 + 类型（列表里就摆着这两列）。 */
const eventLabel = (row: Row): string => `${labelOf(row, 'rule_name')}（${String(row.type ?? '—')}）`;

/**
 * 风控事件：动作型 —— 后端只有 list/detail + handle，没有增/改/删 ⇒ 不给 fields / labelKey。
 *
 * handle **不写库**（risk_log 没有审核状态列），痕迹落在 OperationLog 的请求参数里，所以列表
 * 上没有任何字段能区分「已处置/待处置」⇒ 不做 when 过滤（筛了会误伤，控制器对每行都收）。
 * 服务端的原话在 data.message（信封 message 恒为 "success"），故 report 取 data.message。
 */
export const RISK_EVENT_CRUD: CrudConfig = {
  base: '/admin/v1/risk/event',
  noun: 'f.risk_events',
  actions: [
    {
      label: 'f.confirm_hit',
      path: (id) => `/admin/v1/risk/event/${id}/handle`,
      body: () => ({ decision: 'approve' }),
      fields: [EVENT_NOTE],
      confirm: (row) => t('risk.confirm_hit_confirm', { name: eventLabel(row) }),
      report: (envelope) => String(((envelope.data ?? {}) as Row).message ?? envelope.message),
    },
    {
      label: 'f.mark_as_false_positive',
      title: 'f.mark_as_false_positive',
      path: (id) => `/admin/v1/risk/event/${id}/handle`,
      body: () => ({ decision: 'reject' }),
      fields: [EVENT_NOTE],
      // 误报率的口径是 result=manual_review（见 RiskDashboardController::rulePerformance），
      // 而 handle 不写 result ⇒ 判误报不会改变面板上的误判率，文案必须说清，别让人以为改了模型
      confirm: (row) => t('risk.false_positive_confirm', { name: eventLabel(row) }),
      report: (envelope) => String(((envelope.data ?? {}) as Row).message ?? envelope.message),
    },
  ],
};

/* -------------------------------- 风控用户 -------------------------------- */

/** 用户标识：列表自带 username（取不到才退回 hashid）。 */
const riskUserLabel = (row: Row): string => labelOf(row, 'username');

/**
 * 风控用户（user_trust 队列）：动作型 —— 没有增/改/删端点，只有冻结/解冻两个资金动作 + 时间线。
 * - 列表把 hashid 放在 **user_id** 列（没有 id 列）⇒ `rowKey` 不指认的话行内动作一个都长不出来。
 * - hold **无请求体**，金额由服务端按当前可用余额全额算；release 的 amount 缺省全额。
 */
export const RISK_USER_CRUD: CrudConfig = {
  base: '/admin/v1/risk/users',
  noun: 'f.risk_users',
  rowKey: 'user_id',
  actions: [
    {
      label: 'f.freeze',
      title: 'f.freeze_platform_coin_balance_full',
      path: (id) => `/admin/v1/risk/users/${id}/hold`,
      // 无 body：服务端自己读余额并全额冻结（M1 WalletService::lock，写 risk_log 留痕）
      confirm: (row) =>
        t('risk.freeze_confirm', { name: riskUserLabel(row) }),
      report: (envelope) => {
        const data = (envelope.data ?? {}) as Row;
        return t('risk.freeze_report', { amount: String(data.frozen_amount ?? '—'), user: String(data.user_id ?? '—') });
      },
    },
    {
      label: 'f.unfreeze',
      title: 'f.release_freeze',
      path: (id) => `/admin/v1/risk/users/${id}/release`,
      fields: [
        {
          name: 'amount',
          label: 'f.unfreeze_amount',
          // 金额一律 text：DECIMAL 字符串原样进出，前端连 Number() 都不碰（仓库铁律）
          type: 'text',
          placeholder: 'f.empty_unfreeze_everything',
          hint: 'f.a_decimal_string_e_g',
        },
      ],
      confirm: (row) =>
        t('risk.release_confirm', { name: riskUserLabel(row) }),
      report: (envelope) => {
        const data = (envelope.data ?? {}) as Row;
        return t('risk.release_report', { amount: String(data.released_amount ?? '—'), user: String(data.user_id ?? '—') });
      },
    },
  ],
  // 只读视图：合并 risk_log / play_log / anticheat_event 的时间线（没有单条详情端点，不用 detailBase）
  views: [{ label: 'f.timeline', title: 'f.risk_timeline', path: (id) => `/admin/v1/risk/users/${id}/timeline` }],
};

/* --------------------------------- 关联团伙 -------------------------------- */

/** 三态枚举（列表注释：1=观察中 2=已处置 0=误判）—— 不是 0/1 开关，行内启停那个控件表达不了。 */
const CLUSTER_STATUS_OPTIONS: FieldOption[] = [
  { value: '1', label: 'f.n_1_watching' },
  { value: '2', label: 'f.n_2_actioned' },
  { value: '0', label: 'f.n_0_false_positive' },
];

/**
 * 已确认团伙：动作型 —— 写入走 POST /clusters/confirm（字段与 create 那套不同，见 pages/risk.tsx 的
 * 检测/确认面板），所以这里不给 fields（否则会摆一个必然 404 的「+ 新建」）。
 */
export const RISK_CLUSTER_CRUD: CrudConfig = {
  base: '/admin/v1/risk/clusters',
  noun: 'f.clusters',
  actions: [
    {
      label: 'f.status',
      title: 'f.change_cluster_status',
      path: (id) => `/admin/v1/risk/clusters/${id}/status`,
      method: 'PUT',
      fields: [
        {
          name: 'status',
          label: 'f.target_status',
          type: 'select',
          required: true,
          options: CLUSTER_STATUS_OPTIONS,
          hint: 'f.n_1_watching_2_actioned_0',
        },
      ],
      confirm: (row) => t('risk.cluster_status_confirm', { name: labelOf(row, 'name') }),
      report: (envelope) => {
        const cluster = ((envelope.data ?? {}) as Row).cluster as Row | undefined;
        return cluster ? t('risk.cluster_status_report', { name: String(cluster.name), status: String(cluster.status) }) : envelope.message;
      },
    },
  ],
  views: [{ label: 'f.members', title: 'f.cluster_members', path: (id) => `/admin/v1/risk/clusters/${id}/members` }],
};

/* -------------------------------- 反作弊事件 ------------------------------- */

/** 复核结论值域 = AntiCheatController::review 的 in_array 列表（字符串枚举，不是 0/1）。 */
const ANTICHEAT_STATUS_OPTIONS: FieldOption[] = [
  { value: 'open', label: 'f.open_pending' },
  { value: 'confirmed', label: 'f.confirmed_cheating_confirmed' },
  { value: 'whitelisted', label: 'f.whitelisted' },
  { value: 'closed', label: 'f.closed' },
];

/**
 * 反作弊事件：动作型 —— 只有 list/detail + review，没有增/改/删 ⇒ 不给 fields / labelKey。
 * review 对任何 status 都收（控制器没有 CAS 前置状态），故不做 when 过滤，只把当前状态写进确认文案。
 */
export const ANTICHEAT_CRUD: CrudConfig = {
  base: '/admin/v1/anticheat/events',
  noun: 'f.anti_cheat_events',
  actions: [
    {
      label: 'f.review',
      title: 'f.manual_review',
      path: (id) => `/admin/v1/anticheat/events/${id}/review`,
      fields: [
        {
          name: 'status',
          label: 'f.review_verdict',
          type: 'select',
          required: true,
          options: ANTICHEAT_STATUS_OPTIONS,
          hint: 'f.a_string_enum_not_0',
        },
        { name: 'note', label: 'f.review_note', type: 'textarea', hint: 'f.written_into_review_note_max' },
      ],
      confirm: (row) =>
        t('risk.review_confirm', {
          name: labelOf(row, 'rule_name'),
          user: String(row.user_id ?? '—'),
          status: String(row.status ?? '—'),
        }),
      report: (envelope) => {
        const data = (envelope.data ?? {}) as Row;
        return t('risk.review_report', { message: String(data.message ?? envelope.message), status: String(data.status ?? '—') });
      },
    },
  ],
};

/* ------------------------ 风控：设备名单（行内动作） ------------------------ */

/**
 * 设备列表不进列的字段：`fp_hash` 是行内动作的**入参**（服务端要 64 位原文），展示用掩码列。
 * 把完整哈希铺在表格里，等于把设备库的原文摆在屏幕上 —— 掩码存在的理由就是不自曝它。
 */
export const RISK_DEVICE_HIDE = ['fp_hash'];

/**
 * 设备名单。列表已回完整 `fp_hash`，故拉黑/解封做成**行内动作**（rowKey 指到 fp_hash 列）；
 * 原来那套「手抄 64 位指纹」的粘贴表单已删 —— 同一个动作两个入口＝两份真值，且粘贴的那份
 * 没人能保证抄对（服务端 400 挡的是格式，抄错一个字符的哈希照样落库）。
 *
 * 拉黑是管理端 Redis 标记（`common\RiskDeviceBlock`，TTL 30 天），**会真的阻断**：
 * `RiskService::check()` 在规则循环之前对该标记短路，不看规则是否启用（install.sql 种子里那条
 * device_fingerprint 规则 status=0 也拦得住）⇒ 该设备的充值/提现会被拒。文案按「会拦」措辞。
 *
 * 两个动作互斥（when 按 blocked 判断）：未拉黑只给「拉黑」，已拉黑只给「解封」。
 * 没有增/改/删端点 ⇒ 不给 fields / labelKey（给了就长出必然 404 的按钮）。
 */
export const RISK_DEVICE_CRUD: CrudConfig = {
  base: '/admin/v1/risk/device',
  noun: 'f.device_fingerprint',
  // hashid 位在 fp_hash 列（不是 id）：不指这一下，整行动作会因取不到 id 而不渲染
  rowKey: 'fp_hash',
  actions: [
    {
      label: 'f.blocklist',
      path: () => '/admin/v1/risk/device/block',
      body: (id) => ({ fp_hash: id }),
      // 反向判：真值只可能是 blocked===true 才叫已拉黑，其余（含字段缺失）都当未拉黑 ——
      // 缺失时用 === false 会让两个按钮都不出现，整行变成死行。
      when: (row) => row.blocked !== true,
      confirm: (row) => t('risk.block_confirm', { name: labelOf(row, 'fp_masked') }),
      report: (envelope) =>
        t('risk.block_report', { name: String(((envelope.data ?? {}) as Row).fp_masked ?? '') }),
    },
    {
      label: 'f.unblock',
      path: () => '/admin/v1/risk/device/unblock',
      body: (id) => ({ fp_hash: id }),
      when: (row) => row.blocked === true,
      confirm: (row) => t('risk.unblock_confirm', { name: labelOf(row, 'fp_masked') }),
      // unblock 只回空 data（success()），认不出对象；列表行自己会翻回「未拉黑」
      report: () => t('f.unblocked_the_redis_marker_has'),
    },
  ],
};

/* ------------------------ 风控：没有行上下文的 IP 页面 ------------------------ */

/**
 * 「粘贴标识」动作：只剩 IP 名单用。
 * `/risk/ip/list` 只回 `ip_masked`（没有 id、没有原文），而 whitelist/recheck 要原文 IP
 * ⇒ 行内按钮凑不出请求体，只能由运营粘贴发起（见 pages/risk.tsx）。
 * 字段描述与路径放在这里而不是那个 tsx 里，是为了让 risk.test.ts 能对路由表逐条对账。
 */
export type FlagAction = {
  /** 按钮与表单标题都是**文案键**：取译文只在渲染期（risk.tsx 现取），同 lib/crud.ts 的 Field.label */
  label: MessageKey;
  title: MessageKey;
  path: string;
  field: Field;
  /** 二次确认：动作改的是服务端状态（IP 的信誉行 + 删其 Redis 缓存），文案要认出「改谁、改成什么」 */
  confirm: (value: string) => string;
  /** 成功提示：这些端点只回 `success` 占位符，有用信息在 data（ip_masked / source / message） */
  report?: (envelope: Envelope<unknown>, value: string) => string;
};

const IP_FIELD: Field = {
  name: 'ip',
  label: 'f.ip_address',
  type: 'text',
  required: true,
  placeholder: raw('1.2.3.4'),
  hint: 'f.the_raw_ip_the_server',
};

/**
 * IP 名单。三种写入落在**同一行** ip_reputation 的 source/score 上：
 * 黑名单 = internal_blacklist/0，白名单 = internal_whitelist/100，而 `appeal`（申诉放行）
 * 在控制器里与 whitelist **逐字节同效**（都走 writeReputation(..., 'internal_whitelist', 100)），
 * 故这里只摆一个「白名单/申诉放行」，不摆两个结果完全一样的按钮。
 * 也**没有解封**端点：撤销只能再写一次覆盖（黑名单→白名单），文案按「覆盖」措辞。
 */
export const RISK_IP_FLAGS: FlagAction[] = [
  {
    label: 'f.blocklist_ip',
    title: 'f.blocklist_ip',
    path: '/admin/v1/risk/ip/block',
    field: IP_FIELD,
    confirm: (value) =>
      t('risk.blacklist_confirm', { value }),
    report: (envelope) => {
      const data = (envelope.data ?? {}) as Row;
      return t('risk.blacklist_report', { name: String(data.ip_masked ?? ''), source: String(data.source ?? '') });
    },
  },
  {
    label: 'f.whitelist_appeal_release',
    title: 'f.add_to_whitelist_appeal_release',
    path: '/admin/v1/risk/ip/whitelist',
    field: IP_FIELD,
    confirm: (value) =>
      t('risk.whitelist_confirm', { value }),
    report: (envelope) => {
      const data = (envelope.data ?? {}) as Row;
      return t('risk.whitelist_report', { name: String(data.ip_masked ?? ''), source: String(data.source ?? '') });
    },
  },
  {
    label: 'f.re_check',
    title: 'f.re_check_clear_the_local',
    path: '/admin/v1/risk/ip/recheck',
    field: IP_FIELD,
    confirm: (value) =>
      t('risk.recheck_confirm', { value }),
    report: (envelope) => String(((envelope.data ?? {}) as Row).message ?? t('f.reputation_cache_refreshed')),
  },
];

/**
 * 团伙检测/确认面板（不是行内动作）：detect 是全局扫描（无参数、无行）。
 * confirm 的 member_ids 收的是 **hashid**（服务端逐个 `decodeId`，非法值 400 直接拒 —— 不是静默丢）；
 * 候选来源是已建团伙的成员只读视图：`GET /risk/clusters/{hashid}/members` 回 `{id: hashid, username}`。
 * 写入后的列表/状态/成员仍走上面的 RISK_CLUSTER_CRUD。
 *
 * fullEdit：确认表单的值是**从检测候选预填**的，而表单缺省是「只发改动字段」——
 * 预填值等于原值 ⇒ 「点开候选、一个字不改直接写入」会发出**空请求体**，服务端因缺 type/fingerprint
 * 直接拒绝（同 IP/同设备两类还会先被判非法）。所以这里必须全量提交（与风控规则同一个坑）。
 */
export const RISK_CLUSTER_PANEL = {
  detect: '/admin/v1/risk/clusters/detect',
  fullEdit: true,
  confirm: '/admin/v1/risk/clusters/confirm',
  fields: [
    {
      name: 'type',
      label: 'f.cluster_type',
      type: 'select',
      required: true,
      options: [
        { value: 'same_ip', label: 'f.same_ip' },
        { value: 'same_device', label: 'f.same_device' },
        { value: 'same_pay_account', label: 'f.same_pay_account' },
        { value: 'manual', label: 'f.manual' },
      ],
      hint: 'f.same_ip_same_device_require',
    },
    {
      name: 'fingerprint',
      label: 'f.fingerprint_hash',
      type: 'text',
      placeholder: 'f.fill_ip_hash_for_same',
      hint: 'f.the_column_in_the_detection',
    },
    { name: 'name', label: 'f.cluster_name', type: 'text', required: true, placeholder: 'f.max_100_characters' },
    {
      name: 'member_ids',
      label: 'f.member_hashid',
      type: 'lines',
      placeholder: 'f.one_per_line',
      hint: 'f.hashid_not_a_numeric_id',
    },
    {
      name: 'user_count',
      label: 'f.member_count',
      type: 'number',
      hint: 'f.for_list_display_the_server',
    },
  ] as Field[],
};

/** 角色 / 权限 / 管理员 的删除都要当前登录密码（BaseController::confirmPassword）。 */
export function deleteWithPassword(question: string): Record<string, unknown> | null {
  if (!window.confirm(question)) return null;
  const password = window.prompt(t('f.this_action_requires_the_current'));
  return password === null ? null : { password };
}
