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
import { labelOf, type Field, type FieldOption } from '../lib/crud.ts';
import { api, type Envelope } from '../lib/api.ts';
import { flattenTree } from '../lib/format.ts';

/** 把某些字段改成只读（编辑表单用）：不提交，只解释为什么改不了。 */
const locked = (fields: Field[], names: string | string[], hint: string): Field[] => {
  const list = Array.isArray(names) ? names : [names];
  return fields.map((field) => (list.includes(field.name) ? { ...field, readOnly: true, hint } : field));
};

/* ---------------------------------- 游戏 ---------------------------------- */

const GAME_FIELDS: Field[] = [
  { name: 'name', label: '游戏名称', type: 'text', required: true, placeholder: '最长 100 字' },
  { name: 'slug', label: '游戏标识', type: 'text', required: true, placeholder: '小写字母/数字/-/_，最长 50' },
  {
    name: 'type',
    label: '游戏类型',
    type: 'select',
    required: true,
    options: [
      { value: 'self', label: '自研 self' },
      { value: 'embedded', label: '内嵌 embedded' },
      { value: 'third_party', label: '第三方 third_party' },
    ],
  },
  {
    name: 'platform',
    label: '客户端平台',
    type: 'select',
    default: 'h5',
    options: [
      { value: 'h5', label: 'h5' },
      { value: 'unity', label: 'unity' },
      { value: 'web', label: 'web' },
      { value: 'native', label: 'native' },
    ],
  },
  { name: 'region', label: '运营区域', type: 'text', default: 'global', placeholder: 'global / CN / US …最长 10' },
  { name: 'description', label: '游戏简介', type: 'textarea' },
  { name: 'cover_image', label: '封面图', type: 'text', placeholder: '图片 URL，最长 255' },
  { name: 'api_endpoint', label: 'API 端点', type: 'text', placeholder: '第三方游戏回调地址，最长 255' },
  { name: 'api_key', label: 'API Key', type: 'text', placeholder: '第三方提供；自研/内嵌留空由平台生成' },
  { name: 'api_secret', label: 'API Secret', type: 'text', placeholder: '编辑时留空 = 不改动现有密钥' },
  { name: 'sdk_version', label: 'SDK 版本', type: 'text', placeholder: '自研/内嵌，最长 20' },
  { name: 'sort', label: '排序', type: 'number', placeholder: '越小越靠前' },
  { name: 'status', label: '上架', type: 'switch' },
];

export const GAME_CRUD: CrudConfig = {
  base: '/admin/v1/game',
  noun: '游戏',
  fields: GAME_FIELDS,
  // slug 是游戏标识（唯一键），后端 update 既不校验也不写入 —— 只读展示，别假装能改
  editFields: locked(GAME_FIELDS, 'slug', '游戏标识创建后不可改'),
  labelKey: 'name',
  // 游戏没有独立 toggle 端点，状态变更走 update（PUT {status}）
  toggle: 'update',
};

/* ---------------------------------- 公告 ---------------------------------- */

const ANNOUNCEMENT_FIELDS: Field[] = [
  { name: 'title', label: '标题', type: 'text', required: true, placeholder: '最长 255 字' },
  { name: 'content', label: '内容', type: 'textarea', required: true },
  {
    name: 'type',
    label: '类型',
    type: 'select',
    required: true,
    default: 'system',
    options: [
      { value: 'system', label: '系统公告 system' },
      { value: 'game', label: '游戏公告 game' },
      { value: 'payment', label: '支付公告 payment' },
    ],
  },
  // 控制器 create 的缺省是 1（已发布），与库里「0=草稿 1=已发布」一致
  { name: 'status', label: '上架', type: 'switch', default: '1' },
];

export const ANNOUNCEMENT_CRUD: CrudConfig = {
  base: '/admin/v1/announcement',
  noun: '公告',
  fields: ANNOUNCEMENT_FIELDS,
  labelKey: 'title',
  toggle: '/admin/v1/announcement/toggle',
};

/* -------------------------------- 游戏分类 -------------------------------- */

const CATEGORY_FIELDS: Field[] = [
  { name: 'name', label: '分类名称', type: 'text', required: true, placeholder: '最长 50 字' },
  { name: 'slug', label: '分类标识', type: 'text', required: true, placeholder: '小写字母/数字/-/_' },
  { name: 'icon', label: '分类图标', type: 'text', placeholder: '图标 URL，最长 255' },
  { name: 'sort', label: '排序', type: 'number', placeholder: '越小越靠前；编辑时须 ≥ 0' },
];

export const CATEGORY_CRUD: CrudConfig = {
  base: '/admin/v1/game/category',
  noun: '分类',
  // create 不接受 status（代码里固定写 1）⇒ 只有编辑表单里有它
  fields: CATEGORY_FIELDS,
  editFields: [...locked(CATEGORY_FIELDS, 'slug', '分类标识创建后不可改'), { name: 'status', label: '启用', type: 'switch', default: '1' }],
  labelKey: 'name',
  // 无独立 toggle 端点，状态变更走 update（PUT {status}）
  toggle: 'update',
  actions: [
    {
      label: '分配游戏',
      title: '分配游戏到分类',
      path: () => '/admin/v1/game/category/assign',
      body: (id) => ({ category_id: id }),
      fields: [
        {
          name: 'game_ids',
          label: '游戏 hashid',
          type: 'lines',
          required: true,
          placeholder: '每行一个',
          hint: '取自游戏列表的 id 列；本次提交整体替换该分类的关联（不是追加）',
        },
      ],
    },
  ],
};

/* -------------------------------- 游戏区服 -------------------------------- */

const SERVER_FIELDS: Field[] = [
  { name: 'name', label: '区服名称', type: 'text', required: true, placeholder: '最长 50 字' },
  { name: 'region', label: '区域', type: 'text', placeholder: '如 CN / US，最长 20' },
  {
    name: 'status',
    label: '状态',
    type: 'select',
    default: '1',
    options: [
      { value: '0', label: '0 维护' },
      { value: '1', label: '1 正常' },
      { value: '2', label: '2 火爆' },
      { value: '3', label: '3 新服' },
    ],
    // 四态枚举，不是 0/1 开关 ⇒ 行内启停不适用（rowact 的启用/停用只按 0/1 判）
    hint: '列注释：0=维护 1=正常 2=火爆 3=新服',
  },
  { name: 'sort', label: '排序', type: 'number', placeholder: '越小越靠前；编辑时须 ≥ 0' },
];

/** 区服挂在游戏下（列表端点必填 game_id），故按当前选中的游戏生成一套描述。 */
export function serverCrud(gameId: string): CrudConfig {
  const game: Field = {
    name: 'game_id',
    label: '所属游戏',
    type: 'text',
    required: true,
    default: gameId,
    hint: '当前选中的游戏（游戏列表的 id 列，hashid）',
  };
  return {
    base: '/admin/v1/game/server',
    noun: '区服',
    fields: [game, ...SERVER_FIELDS],
    editFields: [{ ...game, readOnly: true, hint: '区服创建后不可换游戏（update 不接受 game_id）' }, ...SERVER_FIELDS],
    labelKey: 'name',
  };
}

/* -------------------------------- 运营活动 -------------------------------- */

const ACTIVITY_FIELDS: Field[] = [
  {
    name: 'type',
    label: '活动类型',
    type: 'select',
    required: true,
    options: [
      { value: 'signin', label: '签到 signin' },
      { value: 'daily_task', label: '每日任务 daily_task' },
      { value: 'invite', label: '邀请 invite' },
    ],
    hint: '创建后不可改：config 的 schema 按 type 校验，update 不收 type',
  },
  { name: 'name', label: '活动名称', type: 'text', required: true, placeholder: '最长 100 字' },
  {
    name: 'game_id',
    label: '适用游戏',
    type: 'number',
    placeholder: '游戏数值 ID',
    hint: '0 或留空 = 全平台；此端点收数值 ID，不是 hashid（列表里这个字段也没被编成 hashid）',
  },
  {
    name: 'status',
    label: '状态',
    type: 'select',
    required: true,
    default: '0',
    options: [
      { value: '0', label: '0 禁用' },
      { value: '1', label: '1 启用' },
      { value: '2', label: '2 已结束' },
    ],
    hint: '三态枚举，故不做行内启停；create 必填',
  },
  { name: 'start_at', label: '开始时间', type: 'text', placeholder: '2026-01-01 00:00:00' },
  { name: 'end_at', label: '结束时间', type: 'text', placeholder: '须 ≥ 开始时间' },
  { name: 'rollout_percent', label: '灰度百分比', type: 'number', placeholder: '0-100，留空 = 100' },
  {
    name: 'config',
    label: '活动配置（JSON）',
    type: 'json',
    placeholder: '{"rewards": [{"day": 1, "reward": {"type": "platform_coin", "amount": "100"}}]}',
    hint: '必须是合法 JSON，且符合该 type 的 schema：signin={rewards:[{day,reward}]}、daily_task={tasks:[{event,target,reward}]}、invite={target,rewards:[…]}；reward.type 只能是 platform_coin / game_coin，amount 是十进制字符串且单条 ≤ 10000。不符则服务端 422；留空 = 该 type 的默认配置',
  },
];

export const ACTIVITY_CRUD: CrudConfig = {
  base: '/admin/v1/activities',
  noun: '活动',
  fields: ACTIVITY_FIELDS,
  editFields: locked(ACTIVITY_FIELDS, 'type', '活动类型创建后不可改'),
  labelKey: 'name',
};

/* --------------------------------- 成就 ---------------------------------- */

const ACHIEVEMENT_FIELDS: Field[] = [
  { name: 'key', label: '成就标识', type: 'text', required: true, placeholder: '小写字母/数字/下划线，最长 50' },
  { name: 'name', label: '成就名称', type: 'text', required: true, placeholder: '最长 100 字' },
  { name: 'description', label: '成就描述', type: 'textarea', placeholder: '最长 500 字' },
  { name: 'icon', label: '图标', type: 'text', placeholder: '图标 URL' },
  {
    name: 'condition_json',
    label: '达成条件（JSON）',
    type: 'json',
    required: true,
    placeholder: '{"event": "login", "count": 7}',
    hint: '必须能解成 JSON 对象/数组，否则服务端 422',
  },
  { name: 'points', label: '成就积分', type: 'number', required: true, placeholder: '≥ 0' },
  {
    name: 'status',
    label: '启用',
    type: 'switch',
    default: '1',
    hint: '停用只影响后续事件是否再授予；已授予的记录与用户进度不受影响',
  },
];

export const ACHIEVEMENT_CRUD: CrudConfig = {
  base: '/admin/v1/achievement',
  noun: '成就',
  fields: ACHIEVEMENT_FIELDS,
  editFields: locked(ACHIEVEMENT_FIELDS, 'key', '成就标识是唯一键，创建后不可改'),
  labelKey: 'name',
  toggle: '/admin/v1/achievement/toggle',
};

/* ------------------------------- VIP 等级 -------------------------------- */

const VIP_FIELDS: Field[] = [
  { name: 'level', label: '等级', type: 'number', required: true, placeholder: '≥ 0' },
  { name: 'name', label: '等级名称', type: 'text', required: true, placeholder: '最长 50 字' },
  { name: 'required_exp', label: '所需经验', type: 'number', required: true, placeholder: '≥ 0' },
  {
    name: 'benefits',
    label: '权益（JSON）',
    type: 'json',
    required: true,
    placeholder: '{"exchange_discount": "0.05"}',
    hint: 'JSON 对象；键只能用 exchange_discount / withdraw_fee_discount / rate_bonus，值为 [0,1] 的十进制数（写别的键或越界值会被服务端 422 挡下）',
  },
];

export const VIP_CRUD: CrudConfig = {
  base: '/admin/v1/vip/level',
  noun: 'VIP 等级',
  fields: VIP_FIELDS,
  editFields: locked(VIP_FIELDS, 'level', '等级数字是唯一键，创建后不可改'),
  labelKey: 'name',
};

/* -------------------------------- 排行榜 --------------------------------- */

const LEADERBOARD_FIELDS: Field[] = [
  { name: 'name', label: '排行榜名称', type: 'text', required: true, placeholder: '最长 100 字' },
  {
    name: 'type',
    label: '周期',
    type: 'select',
    required: true,
    options: [
      { value: 'daily', label: 'daily 日榜' },
      { value: 'weekly', label: 'weekly 周榜' },
      { value: 'monthly', label: 'monthly 月榜' },
      { value: 'alltime', label: 'alltime 总榜' },
    ],
  },
  {
    name: 'metric',
    label: '排序指标',
    type: 'select',
    required: true,
    options: [
      { value: 'earned', label: 'earned 获得' },
      { value: 'spent', label: 'spent 消耗' },
      { value: 'play_count', label: 'play_count 游玩次数' },
    ],
  },
  {
    name: 'game_id',
    label: '关联游戏',
    type: 'text',
    placeholder: '游戏 hashid',
    hint: '取自游戏列表的 id 列；留空 = 全平台。create 只在这里收 hashid，update 不收（创建后不可改）',
  },
  { name: 'rule', label: '排行规则', type: 'textarea', placeholder: '按 JSON 约定填写', hint: '服务端不做校验，原样存进 rule 列' },
  // 控制器 create 的缺省是 0（未启用），与库里的默认值 1 不同 —— 以控制器为准
  { name: 'status', label: '启用', type: 'switch', default: '0' },
  { name: 'sort', label: '排序', type: 'number', placeholder: '越小越靠前；编辑时须 ≥ 0' },
];

export const LEADERBOARD_CRUD: CrudConfig = {
  base: '/admin/v1/leaderboard',
  noun: '排行榜',
  fields: LEADERBOARD_FIELDS,
  editFields: locked(LEADERBOARD_FIELDS, 'game_id', '关联游戏创建后不可改'),
  labelKey: 'name',
  // 无独立 toggle 端点，状态变更走 update（PUT {status}）
  toggle: 'update',
  actions: [
    {
      label: '刷新缓存',
      path: (id) => `/admin/v1/leaderboard/${id}/refresh`,
      confirm: '清除并重算该排行榜的缓存？',
    },
  ],
};

/* ------------------------------- 国家配置 -------------------------------- */

const COUNTRY_FIELDS: Field[] = [
  { name: 'country_code', label: '国家代码', type: 'text', required: true, placeholder: '两位，如 CN', hint: 'ISO 3166-1 alpha-2，提交时统一转大写' },
  { name: 'currency', label: '货币代码', type: 'text', required: true, placeholder: '三位，如 CNY', hint: 'ISO 4217，提交时统一转大写' },
  {
    name: 'payment_methods',
    label: '支付方式（JSON）',
    type: 'json',
    placeholder: '{"paypal": {"min": "1.0000", "enabled": true}}',
    hint: '键=网关 provider，值含 min/max/fee_percent/enabled；留空 = 空配置',
  },
  {
    name: 'withdraw_methods',
    label: '提现方式（JSON）',
    type: 'json',
    placeholder: '{"paypal": {"min": "10.0000", "enabled": true}}',
    hint: '键=paypal/bank/crypto，值含 min/max/fee_percent/enabled；留空 = 空配置',
  },
  {
    // 金额写字符串：不转浮点（仓库铁律：金额不参与浮点运算），并保住小数位，显示与提交一致
    name: 'min_deposit',
    label: '最低充值额',
    type: 'text',
    placeholder: '如 1.0000',
    hint: '十进制数字字符串；留空 = 默认 1.0000',
  },
];

export const COUNTRY_CRUD: CrudConfig = {
  base: '/admin/v1/country/config',
  noun: '国家配置',
  // create 不接受 status（代码里固定写 1）⇒ 只有编辑表单里有它
  fields: COUNTRY_FIELDS,
  editFields: [...locked(COUNTRY_FIELDS, 'country_code', '国家代码创建后不可改'), { name: 'status', label: '启用', type: 'switch', default: '1' }],
  labelKey: 'country_code',
  toggle: '/admin/v1/country/config/toggle',
};

/* ------------------------------- 系统配置 -------------------------------- */

const CONFIG_FIELDS: Field[] = [
  { name: 'group', label: '分组', type: 'text', required: true, placeholder: '最长 100 字' },
  { name: 'key', label: '配置键', type: 'text', required: true, placeholder: '最长 100 字' },
  {
    name: 'value',
    label: '配置值',
    type: 'textarea',
    required: true,
    hint: '按 type 解析（json 类型要写合法 JSON）；服务端两边都不允许清空',
  },
  {
    name: 'type',
    label: '值类型',
    type: 'select',
    default: 'string',
    options: [
      { value: 'string', label: 'string' },
      { value: 'int', label: 'int' },
      { value: 'bool', label: 'bool' },
      { value: 'json', label: 'json' },
    ],
    hint: '只影响读取时的解析方式，服务端不收口枚举',
  },
  { name: 'description', label: '说明', type: 'text', placeholder: '最长 255 字' },
];

export const CONFIG_CRUD: CrudConfig = {
  base: '/admin/v1/config',
  noun: '配置项',
  // 这个模块的路径参数段叫 {id}（不是 {hashid}），值仍是 hashid；差别在 create 直接 POST 到 base
  createPath: '/admin/v1/config',
  fields: CONFIG_FIELDS,
  editFields: locked(CONFIG_FIELDS, ['group', 'key'], '分组 + 键构成唯一项，创建后不可改'),
  labelKey: 'key',
  // 删除要管理员密码二次确认（BaseController::confirmPassword），不带密码必被 422 挡下；
  // 密码走请求体（DELETE 也能带 body，webman 按 content-type 解析），不进 URL
  deleteBody: (row) => {
    const password = window.prompt(`删除配置项「${labelOf(row, 'key')}」需要输入当前登录密码：`);
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
  { name: 'nickname', label: '昵称', type: 'text', placeholder: '最长 50 字', hint: '超长会被服务端拒绝（422）' },
  {
    name: 'status',
    label: '状态',
    type: 'switch',
    hint: '开 = 正常（1），关 = 封禁（0）；后端只收 0/1，别的值一律 422',
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
  return name === '' ? '该记录' : name;
};

export const IDENTITY_CRUD: CrudConfig = {
  base: '/admin/v1/identity',
  noun: '实名认证',
  // 动作型：后端只有 list + review，没有增/改/删端点 ⇒ 不给 fields / labelKey，
  // 界面就只长「通过 / 驳回」两个按钮
  actions: [
    {
      label: '通过',
      // review 是 PUT，且记录 id 走请求体（不经 URL）
      path: () => '/admin/v1/identity/review',
      method: 'PUT',
      body: (id) => ({ id, action: 'approve' }),
      confirm: (row) => `确认通过「${identityLabel(row)}」的实名认证？通过后会立即给该用户发通知。`,
    },
    {
      label: '驳回',
      title: '驳回实名认证',
      path: () => '/admin/v1/identity/review',
      method: 'PUT',
      body: (id) => ({ id, action: 'reject' }),
      fields: [
        {
          name: 'note',
          label: '驳回理由',
          type: 'textarea',
          hint: '会原样拼进发给用户的通知（最长 500 字）；留空则通知里没有理由',
        },
      ],
      confirm: (row) => `确认驳回「${identityLabel(row)}」的实名认证？驳回后用户会收到通知。`,
    },
  ],
  // review 只认 pending：已审过的记录会被 422 挡下（后端 CAS），按钮不做预判，错误原样显示
};

/* ---------------------------------- 角色 ---------------------------------- */

/**
 * 权限下拉的值域：GET /admin/v1/permission 的树拍平成 [{value: hashid, label: 路径（slug）}]。
 * 标签带父名与 slug —— 各模块都有叫「列表」的节点，只给 name 分不出是哪一个。
 * 角色表单的「权限」多选与权限表单的「父权限」共用这一份。
 */
const permissionOptions = async (): Promise<FieldOption[]> => {
  const data = await api<unknown>('/admin/v1/permission');
  const tree = Array.isArray(data) ? (data as Row[]) : [];
  return flattenTree(tree)
    .map((node) => {
      const parent = String(node.parent_name ?? '');
      const name = String(node.name ?? '');
      const slug = String(node.slug ?? '');
      return {
        value: String(node.id ?? ''),
        label: `${parent === '' ? '' : `${parent} / `}${name}${slug === '' ? '' : `（${slug}）`}`,
      };
    })
    .filter((option) => option.value !== '');
};

const ROLE_FIELDS: Field[] = [
  { name: 'name', label: '角色名称', type: 'text', required: true, placeholder: '最长 50 字' },
  { name: 'slug', label: '角色标识', type: 'text', required: true, placeholder: '如 super_admin，最长 50 字' },
  { name: 'description', label: '角色描述', type: 'textarea', placeholder: '最长 255 字' },
  {
    name: 'status',
    label: '启用',
    type: 'switch',
    default: '1',
    hint: '停用后该角色的管理员不再获得它授予的权限（中间件按 status === 0 判停用）',
  },
  {
    name: 'permission_ids',
    label: '权限',
    type: 'multi',
    // 值域是权限树，开框时才拉（见 permissionOptions）
    options: permissionOptions,
    hint: '按 Ctrl/⌘ 多选，不选 = 该角色没有任何权限。后端 sync 是整表替换：只有改动过才会重发全量，没动过就不会碰已有授权。',
  },
];

export const ROLE_CRUD: CrudConfig = {
  base: '/admin/v1/role',
  noun: '角色',
  // Route::resource：列表在 /role 本身（**不是** /role/list），新建也是 POST /role
  createPath: '/admin/v1/role',
  fields: ROLE_FIELDS,
  // update 的 validator/fill 里没有 slug（角色标识是唯一键）⇒ 只读展示
  editFields: locked(ROLE_FIELDS, 'slug', '角色标识创建后不可改'),
  labelKey: 'name',
  // 无独立 toggle 端点，状态变更走 update（PUT {status}，validator 收 in:0,1）
  toggle: 'update',
  // 删除要管理员密码（RoleController::destroy 走 confirmPassword），且会解绑该角色的用户与权限
  deleteBody: (row) =>
    deleteWithPassword(`确认删除角色「${labelOf(row, 'name')}」？该角色下的管理员会与它解绑，操作不可撤销。`),
  // permission_ids 走 hashid：RoleController::decodePermissionIds 逐个 decodeId（非法即 400，
  // 不落半截关联），index 也回传 hashid 形式的 permission_ids 供编辑态回填。
  // 编辑时 buildPayload 只发改动过的字段 ⇒ 不碰权限的那次保存不会触发 sync（sync 是整表替换）。
};

/* ---------------------------------- 权限 ---------------------------------- */

const PERMISSION_FIELDS: Field[] = [
  { name: 'name', label: '权限名称', type: 'text', required: true, placeholder: '最长 50 字' },
  { name: 'slug', label: '权限标识', type: 'text', required: true, placeholder: '如 get.admin/user，最长 100 字' },
  {
    name: 'type',
    label: '类型',
    type: 'select',
    required: true,
    options: [
      { value: '1', label: '1 菜单' },
      { value: '2', label: '2 按钮' },
      { value: '3', label: '3 接口' },
    ],
    hint: '列注释：1=菜单 2=按钮 3=API 接口；icon/path 仅菜单用',
  },
  {
    name: 'parent_id',
    label: '父权限',
    type: 'select',
    default: '0',
    // 值域 = 权限树 + 根节点。PermissionController::store 的 decodeParentId 收 hashid（'0'/空 = 根），
    // buildTree 回传的 parent_id 也是 hashid，故这里与列表里的父名是同一套标识。
    options: async () => [{ value: '0', label: '根节点（无父级）' }, ...(await permissionOptions())],
    hint: '仅新建时可选：update 不收 parent_id（换父级要先防环，属另一件事），建好后在列表里用「父权限」列看归属',
  },
  { name: 'icon', label: '图标', type: 'text', placeholder: '最长 50 字' },
  { name: 'path', label: '前端路由', type: 'text', placeholder: '如 /games，最长 255 字' },
  { name: 'sort', label: '排序', type: 'number', placeholder: '≥ 0，越小越靠前' },
];

export const PERMISSION_CRUD: CrudConfig = {
  base: '/admin/v1/permission',
  noun: '权限',
  // Route::resource：列表/新建都在 /permission 本身
  createPath: '/admin/v1/permission',
  fields: PERMISSION_FIELDS,
  // update 只写 name/icon/path/sort ⇒ slug/type 标只读摆明「改不了」；
  // parent_id 是 createOnly，编辑表单里整个不给（update 不收它，换个父级还会让子树散架）
  editFields: locked(
    PERMISSION_FIELDS.filter((field) => field.name !== 'parent_id'),
    ['slug', 'type'],
    '创建后不可改（update 不收这个字段）',
  ),
  labelKey: 'name',
  // 删除要管理员密码，且后端会级联删子权限、把该权限从所有角色上解绑 —— 确认文案必须说清
  deleteBody: (row) =>
    deleteWithPassword(
      `确认删除权限「${labelOf(row, 'name')}」？其所有子权限会一并删除，该权限也会从所有角色上解绑。`,
    ),
};

/* ---------------------------------- 工单 ---------------------------------- */

export const TICKET_CRUD: CrudConfig = {
  base: '/admin/v1/ticket',
  noun: '工单',
  // 动作型：后端只有 list/detail + reply/close/assign 三个状态变更，没有增/改/删
  actions: [
    {
      label: '回复',
      title: '回复工单',
      path: (id) => `/admin/v1/ticket/${id}/reply`,
      fields: [
        {
          name: 'content',
          label: '回复内容',
          type: 'textarea',
          required: true,
          hint: '以管理员身份追加一条回复，并把工单状态置为 replied；工单已 closed 时后端拒绝（422）',
        },
      ],
    },
    {
      label: '关闭',
      path: (id) => `/admin/v1/ticket/${id}/close`,
      confirm: (row) => `确认关闭工单「${labelOf(row, 'subject')}」？关闭后不能再回复。`,
    },
    {
      label: '指派',
      title: '指派受理人',
      path: (id) => `/admin/v1/ticket/${id}/assign`,
      fields: [
        {
          name: 'admin_id',
          label: '受理管理员 ID',
          type: 'number',
          required: true,
          hint: '管理员在库里的数值 ID，不是列表里的 hashid：后端走 (int) 强转，填 hashid 会被截成 0 或某个不相干的小数字（指派给错人）；0 = 取消指派',
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
  const fiatText = fiat === '' || fiat === '0.0000' ? '' : `，到账 ${fiat}${currency === '' ? '' : ` ${currency}`}`;
  return `${String(row.platform_amount ?? '—')} 平台币${fiatText}`;
};

const orderStatus = (row: Row): string => String(row.status ?? '');

/** 审核备注（review / confirm / reject 共用同一个字段：后端都是 $request->input('note')）。 */
const REVIEW_NOTE: Field = {
  name: 'note',
  label: '审核备注',
  type: 'textarea',
  hint: '写进订单的 review_note（最长 500 字）；驳回时该备注会出现在给用户的退款流水说明里',
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
  noun: '提现订单',
  actions: [
    {
      label: '通过',
      path: () => '/admin/v1/withdraw/review',
      method: 'PUT',
      body: (id) => ({ order_id: id, action: 'approve' }),
      fields: [REVIEW_NOTE],
      // reviewer_id 已有值 = 已被第一审核人处理过（双审），再点通过必然 422
      when: (row) => orderStatus(row) === 'pending' && Number(row.reviewer_id ?? 0) <= 0,
      confirm: (row) => `确认通过订单「${orderLabel(row)}」（${orderMoney(row)}）？通过后该笔即可执行打款。`,
      report: true,
    },
    {
      label: '驳回',
      title: '驳回提现',
      path: () => '/admin/v1/withdraw/review',
      method: 'PUT',
      body: (id) => ({ order_id: id, action: 'reject' }),
      fields: [REVIEW_NOTE],
      when: (row) => orderStatus(row) === 'pending',
      confirm: (row) =>
        `确认驳回订单「${orderLabel(row)}」（${orderMoney(row)}）？驳回会立即把这笔平台币退回用户余额并记一条退款流水，不可撤销。`,
      report: true,
    },
    {
      label: '二次确认',
      title: '二次确认（双审平台）',
      path: () => '/admin/v1/withdraw/review',
      method: 'PUT',
      body: (id) => ({ order_id: id, action: 'confirm' }),
      fields: [REVIEW_NOTE],
      // 只有「已被第一审核人处理过、还停在 pending」的订单才谈得上二次确认；
      // 单审平台不出现此按钮（点了必然 422「双重审核未启用」）
      when: (row) => orderStatus(row) === 'pending' && Number(row.reviewer_id ?? 0) > 0,
      confirm: (row) => `确认对订单「${orderLabel(row)}」（${orderMoney(row)}）做二次确认？确认后该笔即可执行打款。`,
      report: true,
    },
    {
      label: '执行打款',
      path: () => '/admin/v1/withdraw/execute-payout',
      body: (id) => ({ order_id: id }),
      when: (row) => orderStatus(row) === 'approved',
      // 真正出钱的一步：文案必须带订单标识与金额，且不可逆（失败会退回 approved 允许重试）
      confirm: (row) =>
        `确认对订单「${orderLabel(row)}」（${orderMoney(row)}）执行 PayPal 打款？这是真实出款，不可撤销。`,
      report: true,
    },
    {
      label: '同步打款',
      path: () => '/admin/v1/withdraw/sync-payout',
      body: (id) => ({ order_id: id }),
      // 没有批次号 = 还没提交给 PayPal，后端会 422「该订单尚未执行打款」
      when: (row) => String(row.payout_batch_id ?? '').trim() !== '',
      confirm: (row) => `从 PayPal 同步订单「${orderLabel(row)}」的打款状态？只读不改钱。`,
      // 该端点 message 是占位符 "success"，有用的是 data 里的三个状态
      report: (envelope) => {
        const data = (envelope.data ?? {}) as Row;
        return `打款状态 ${data.payout_status ?? '—'}，订单状态 ${data.order_status ?? '—'}（PayPal 批次状态 ${data.synced_status ?? '—'}）`;
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
    label: '档位',
    type: 'text',
    readOnly: true,
    hint: 'default / verified / vip —— 档位是库里的预置行，只能改，不能新建或删除',
  },
  { name: 'single_min', label: '单笔最低', type: 'text', hint: '如 1.0000；不得高于本档单笔最高' },
  { name: 'single_max', label: '单笔最高', type: 'text', hint: '如 1000.0000；0 = 不限（此时不与单笔最低比较）' },
  { name: 'daily_limit', label: '每日限额', type: 'text' },
  { name: 'monthly_limit', label: '每月限额', type: 'text' },
  {
    name: 'fee_pct',
    label: '手续费率（%）',
    type: 'text',
    hint: '必须小于 100：等于 100 会把实收吃成 0，服务端直接拒绝（≥100 也是）',
  },
  { name: 'fee_max', label: '手续费上限', type: 'text', hint: '0 = 不封顶' },
  {
    name: 'auto_approve_threshold',
    label: '自动审核阈值',
    type: 'text',
    hint: '平台币金额：订单金额**小于**它、且风控与双审都放行时才免审通过（不是「一定自动过」）；0 = 不自动',
  },
];

/**
 * 阶梯限额：只有 PUT {hashid}（单档精调），没有新建/删除端点 —— `createPath: null` 去掉「+ 新建」，
 * 缺 labelKey 去掉「删除」。全档位一把写走 pages/funds.tsx 的「全档位重置」（POST limits/set）。
 */
export const WITHDRAW_LIMIT_CRUD: CrudConfig = {
  base: '/admin/v1/withdraw/limits',
  noun: '限额档位',
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
].map((value) => ({ value, label: value }));

const PAYMENT_FIELDS: Field[] = [
  { name: 'name', label: '名称', type: 'text', required: true, placeholder: '如 USDT (TRC20)，最长 50 字' },
  {
    name: 'type',
    label: '类型',
    type: 'select',
    required: true,
    options: [
      { value: 'fiat', label: 'fiat 法币' },
      { value: 'crypto', label: 'crypto 加密货币' },
    ],
  },
  { name: 'provider', label: '提供商', type: 'select', required: true, options: PAYMENT_PROVIDERS, hint: '决定实际走哪个支付网关' },
  // create 的 validator 里 status 是必填（没有 input() 缺省值），故开关一定会上送；
  // 初值取库列默认值 0（禁用）：配置没核过就不该对用户可见，配好再在列表里点启用
  { name: 'status', label: '启用', type: 'switch', default: '0' },
  { name: 'sort', label: '排序', type: 'number', placeholder: '≥ 0，越小越靠前' },
  {
    name: 'countries',
    label: '可见国家',
    type: 'lines',
    placeholder: '每行一个，如\nCN\nUS',
    hint: 'ISO 3166-1 alpha-2，每行一个（最长 2 位）；留空 = 全球可见',
  },
  { name: 'currency', label: '限定币种', type: 'text', placeholder: '如 USD，最长 10；留空 = 任意币种' },
  { name: 'min_amount', label: '最小金额', type: 'text', hint: '订单币种的十进制金额，如 1.0000；0 = 不限' },
  { name: 'max_amount', label: '最大金额', type: 'text', hint: '0 = 不限；不得小于最小金额' },
  {
    name: 'config',
    label: '支付配置',
    type: 'textarea',
    placeholder: '{"network":"TRC20"}',
    hint: '网关参数 JSON 文本，加密存储；列表回显的是解密后的原文，编辑时不动它就保持不变（留空 = 不修改）',
  },
];

export const PAYMENT_CRUD: CrudConfig = {
  base: '/admin/v1/payment/method',
  noun: '支付方式',
  createPath: '/admin/v1/payment/method/create',
  fields: PAYMENT_FIELDS,
  labelKey: 'name',
  toggle: '/admin/v1/payment/method/toggle',
  // 删除会被后端按「存在待支付订单」拒绝（422），message 原样显示；无密码二次确认
};

/* --------------------------------- 优惠券 --------------------------------- */

const COUPON_FIELDS: Field[] = [
  { name: 'name', label: '名称', type: 'text', required: true, placeholder: '最长 100 字' },
  {
    name: 'type',
    label: '类型',
    type: 'select',
    required: true,
    options: [
      { value: 'fixed', label: 'fixed 固定金额' },
      { value: 'rate', label: 'rate 比例折扣' },
    ],
  },
  {
    name: 'value',
    label: '面值 / 折扣率',
    type: 'text',
    required: true,
    hint: 'fixed = 平台币金额（如 10）；rate = 折扣率（0.10 = 9 折）。须大于 0，十进制字符串',
  },
  { name: 'min_amount', label: '最低使用金额', type: 'text', hint: '平台币金额；留空 = 0（无门槛）' },
  { name: 'max_discount', label: '最高优惠金额', type: 'text', hint: '仅 rate 类型用；留空 = 0' },
  {
    name: 'game_id',
    label: '适用游戏',
    type: 'text',
    placeholder: '游戏 hashid',
    hint: '取自游戏列表的 id 列；留空 = 全平台通用（存 0）',
  },
  { name: 'total_qty', label: '发行总量', type: 'number', hint: '0 = 不限量' },
  { name: 'user_limit', label: '每人限领', type: 'number', hint: '≥ 1；留空由后端取 1' },
  {
    name: 'start_at',
    label: '开始时间',
    type: 'text',
    placeholder: '2026-01-01 00:00:00',
    hint: '日期时间串；留空 = 不限起始（create 不做日期校验，格式写错会撞库报错）',
  },
  { name: 'end_at', label: '结束时间', type: 'text', placeholder: '2026-01-01 00:00:00', hint: '编辑时须 ≥ 开始时间' },
];

export const COUPON_CRUD: CrudConfig = {
  base: '/admin/v1/coupon',
  noun: '优惠券',
  createPath: '/admin/v1/coupon/create',
  fields: COUPON_FIELDS,
  // create 把 status 硬编码成 1（已启用）⇒ 开关只出现在编辑表单里。
  // conditions（使用条件 JSON）也放进来，但**只读**：create/update 都不收它（$request->only 里没有），
  // 表单里摆一个能输入的框等于骗人；它是 C 端 claim 的准入条件（conditions.game_id 等），只由直写库设置。
  editFields: [
    ...COUPON_FIELDS,
    { name: 'status', label: '启用', type: 'switch', default: '1' },
    {
      name: 'conditions',
      label: '使用条件（只读）',
      type: 'json',
      readOnly: true,
      hint: 'C 端领取时的准入条件（如 {"game_id": …}）；后端 create/update 都不收这个字段，只能在库里改',
    },
  ],
  labelKey: 'name',
  // 没有单条详情端点（GET /coupon/{hashid} 不存在），只有 stats ⇒ 只读视图（不是 detailBase，那会 404）
  views: [{ label: '统计', title: '优惠券统计', path: (id) => `/admin/v1/coupon/${id}/stats` }],
  // 「已有用户领取」时后端拒绝编辑（400），message 原样显示；「删除会连带删掉所有领取记录」后端不拦
};

/* -------------------------------- CDN 厂商 -------------------------------- */

const CDN_FIELDS: Field[] = [
  { name: 'name', label: '显示名称', type: 'text', required: true, placeholder: '最长 50 字' },
  {
    name: 'provider',
    label: '厂商',
    type: 'select',
    required: true,
    options: [
      { value: 'cloudflare', label: 'cloudflare' },
      { value: 'cloudfront', label: 'cloudfront' },
      { value: 'aliyun', label: 'aliyun' },
      { value: 'tencent', label: 'tencent' },
      { value: 'huawei', label: 'huawei' },
    ],
    hint: '厂商是唯一键：同一厂商只能有一条配置',
  },
  {
    name: 'config',
    label: '配置（JSON）',
    type: 'json',
    placeholder: '{"bucket":"static","domain":"cdn.example.com"}',
    hint: '凭据/桶/域名，加密存储；列表**不回传** config ⇒ 编辑时留空 = 保持原凭据不变（服务端也只在校验通过且非空时才覆盖）',
  },
  // create 必填 status（无 input() 缺省值）；初值取库列默认值 1 但种子行都是停用 —— 给 0，
  // 新建即对外服务太危险，连通测试通过后再启用
  { name: 'status', label: '启用', type: 'switch', default: '0' },
  { name: 'sort', label: '排序', type: 'number', placeholder: '≥ 0，越小越靠前' },
];

export const CDN_CRUD: CrudConfig = {
  base: '/admin/v1/cdn/provider',
  noun: 'CDN 厂商',
  createPath: '/admin/v1/cdn/provider/create',
  fields: CDN_FIELDS,
  labelKey: 'name',
  toggle: '/admin/v1/cdn/provider/toggle',
  actions: [
    {
      label: '连通测试',
      title: '连通测试',
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
].map((value) => ({ value, label: value }));

/**
 * config 的键随 type 变，白名单在服务端（RiskRuleController::CONFIG_KEYS 与 INT_BOUNDS）。
 * 静态把每个 type 的键与值域列全 —— 前端**不再实现一套校验**（服务端是唯一真值，多一套必然漂移），
 * 这里只负责让运营知道该写哪些键、边界在哪。写错键名服务端会明确拒绝（不会静默忽略）。
 */
const RULE_CONFIG_HINT =
  'JSON 对象，键必须属于所选 type 的白名单（服务端逐键校验）。' +
  'ip_blacklist: blacklist（IP 原文的字符串数组）；' +
  'amount_anomaly: min_amount（金额，必须大于 0）、currency（≤10 字符）；' +
  'frequency: window_minutes（1..10080 分钟）、max_count（1..100000）；' +
  'velocity: window_minutes（1..10080）、max_accounts（1..100000）、same_ip（true/false）；' +
  'device_fingerprint: max_accounts_per_device（1..100000）、new_device_lookback_hours（1..8760）、new_device_withdraw_block（true/false）；' +
  'ip_reputation: block_score_below（0..100）、warn_score_below（0..100）、block_unknown（true/false）；' +
  'device_account_graph: cluster_threshold（1..100000）、max_accounts_per_device（1..100000）、frozen_sibling_block（true/false）；' +
  'withdraw_pattern: window_minutes（1..10080）、max_applies（1..100000）、single_hard_cap（金额，必须大于 0）、drain_ratio（比率，落在 (0, 1]）、sigma_window_days（2..3650）、sigma_multiplier（1..100）、fast_interval_seconds（1..86400）、fast_interval_min_count（1..100000）。' +
  '布尔键必须写真正的 true/false（写成字符串服务端直接拒绝：它按 (bool) 读，字符串 "false" 恒为真）。' +
  '阈值不能填 0 的键一律有下界 —— 取 0 会让规则恒命中且 action=block 时连充值一起停。';

const RISK_RULE_FIELDS: Field[] = [
  { name: 'name', label: '规则名称', type: 'text', required: true, placeholder: '最长 100 字' },
  { name: 'type', label: '规则类型', type: 'select', required: true, options: RULE_TYPES, hint: '决定 config 收哪些键；评估器按类型注册，改类型等于换一套配置' },
  {
    name: 'action',
    label: '命中动作',
    type: 'select',
    required: true,
    options: [
      { value: 'log', label: 'log 仅记录' },
      { value: 'warn', label: 'warn 警告' },
      { value: 'block', label: 'block 阻断' },
    ],
    hint: 'block 是真的拦：命中后 severity=high 保留本规则动作，充值/提现/兑换/登录共用这条路径',
  },
  {
    name: 'scope',
    label: '生效范围',
    type: 'select',
    default: 'all',
    options: [
      { value: 'all', label: 'all 全环节' },
      { value: 'deposit', label: 'deposit 充值' },
      { value: 'withdraw', label: 'withdraw 提现' },
      { value: 'exchange', label: 'exchange 兑换' },
      { value: 'login', label: 'login 登录' },
    ],
    hint: '缺省 all = 四类检查都走这条规则',
  },
  { name: 'config', label: '规则配置（JSON）', type: 'json', required: true, default: '{}', placeholder: '{"window_minutes": 60, "max_count": 5}', hint: RULE_CONFIG_HINT },
  { name: 'priority', label: '优先级', type: 'number', default: '100', hint: '0..1000，越大越先评估（越界会被服务端夹到区间内）' },
  { name: 'status', label: '启用', type: 'switch', default: '1', hint: '停用后不再参与评估（getEnabled 只取 status=1）' },
];

/**
 * 风控规则。三处与别处不同：
 * - **编辑是全量语义**：update 与 create 共用 fill()，name/type/action 一律从请求体读且必填 ⇒ `fullEdit`。
 * - **启停无请求体**：POST {hashid}/toggle 由服务端自己翻转（不是「客户端给 status」）。
 * - **没有删除端点**：规则是审计对象，下线用「停用」⇒ 不给 labelKey（界面上就没有删除按钮）。
 */
export const RISK_RULE_CRUD: CrudConfig = {
  base: '/admin/v1/risk/rule',
  noun: '风控规则',
  fields: RISK_RULE_FIELDS,
  fullEdit: true,
  toggle: (id) => ({ path: `/admin/v1/risk/rule/${id}/toggle` }),
  actions: [
    {
      label: '试算',
      title: '沙箱试算（只读：不写库、不落日志、不触发处置）',
      path: () => '/admin/v1/risk/rule/test',
      body: (id) => ({ rule_id: id }),
      fields: [
        {
          name: 'user_id',
          label: '用户 hashid',
          type: 'text',
          required: true,
          hint: '取自用户列表的 user_id 列；必填 —— 服务端要 decode 它，留空/非法会被 400 挡下',
        },
        {
          name: 'check_type',
          label: '检查环节',
          type: 'select',
          required: true,
          default: 'login',
          options: [
            { value: 'login', label: 'login 登录' },
            { value: 'deposit', label: 'deposit 充值' },
            { value: 'withdraw', label: 'withdraw 提现' },
            { value: 'exchange', label: 'exchange 兑换' },
          ],
          hint: 'frequency 类规则按环节查库（充值看已确认单、提现看非取消单、兑换看兑换记录，其余按 risk_log 计数）',
        },
        {
          name: 'context',
          label: '上下文（JSON 对象）',
          type: 'jsonobj',
          placeholder: '{"ip": "1.2.3.4", "user_agent": "Mozilla/5.0", "amount": "5000"}',
          hint: '评估器读 ip / user_agent / amount / fp_hash 四个键；留空 = 空上下文。必须是 JSON **对象**（传字符串服务端会当成没传、静默按空上下文评估）',
        },
      ],
      // 结果全在 data 里（信封 message 恒为 "success"）：只显示 message 等于把试算结果抹掉
      report: (envelope) => {
        const data = (envelope.data ?? {}) as Row;
        const verdict = data.matched === true ? '命中' : '未命中';
        return `试算${verdict}（severity ${String(data.severity ?? '—')}，本规则处置 ${String(data.action ?? '—')}）：${String(data.message ?? '')}`;
      },
    },
  ],
};

/* --------------------------------- 风控事件 -------------------------------- */

const EVENT_NOTE: Field = {
  name: 'note',
  label: '处置备注',
  type: 'textarea',
  hint: '写进操作审计的请求参数（最长 500 字，超出会被截断）',
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
  noun: '风控事件',
  actions: [
    {
      label: '确认命中',
      path: (id) => `/admin/v1/risk/event/${id}/handle`,
      body: () => ({ decision: 'approve' }),
      fields: [EVENT_NOTE],
      confirm: (row) => `确认事件「${eventLabel(row)}」的命中判定正确？处置只记入操作审计，不改事件数据。`,
      report: (envelope) => String(((envelope.data ?? {}) as Row).message ?? envelope.message),
    },
    {
      label: '判为误报',
      title: '判为误报',
      path: (id) => `/admin/v1/risk/event/${id}/handle`,
      body: () => ({ decision: 'reject' }),
      fields: [EVENT_NOTE],
      // 误报率的口径是 result=manual_review（见 RiskDashboardController::rulePerformance），
      // 而 handle 不写 result ⇒ 判误报不会改变面板上的误判率，文案必须说清，别让人以为改了模型
      confirm: (row) => `确认把事件「${eventLabel(row)}」判为误报？该动作只记入操作审计，不改事件数据、也不改风控面板的误判率。`,
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
  noun: '风险用户',
  rowKey: 'user_id',
  actions: [
    {
      label: '冻结',
      title: '冻结平台币余额（全额）',
      path: (id) => `/admin/v1/risk/users/${id}/hold`,
      // 无 body：服务端自己读余额并全额冻结（M1 WalletService::lock，写 risk_log 留痕）
      confirm: (row) =>
        `确认冻结用户「${riskUserLabel(row)}」的平台币余额？服务端按当前可用余额**全额**冻结（金额在结果里显示），冻结后需人工解冻才能动用。`,
      report: (envelope) => {
        const data = (envelope.data ?? {}) as Row;
        return `已全额冻结 ${String(data.frozen_amount ?? '—')} 平台币（用户 ${String(data.user_id ?? '—')}）`;
      },
    },
    {
      label: '解冻',
      title: '解除冻结',
      path: (id) => `/admin/v1/risk/users/${id}/release`,
      fields: [
        {
          name: 'amount',
          label: '解冻金额',
          // 金额一律 text：DECIMAL 字符串原样进出，前端连 Number() 都不碰（仓库铁律）
          type: 'text',
          placeholder: '留空 = 全额解冻',
          hint: '十进制字符串（如 10.00000000），原样上送、前端不做任何换算；留空按当前冻结余额全额释放。释放量与冻结台账逐笔对账，超过冻结额会被服务端拒绝',
        },
      ],
      confirm: (row) =>
        `确认解冻用户「${riskUserLabel(row)}」的冻结余额？留空即全额解冻。解冻是 frozen→available 的纯搬移，不铸币。`,
      report: (envelope) => {
        const data = (envelope.data ?? {}) as Row;
        return `已解冻 ${String(data.released_amount ?? '—')} 平台币（用户 ${String(data.user_id ?? '—')}）`;
      },
    },
  ],
  // 只读视图：合并 risk_log / play_log / anticheat_event 的时间线（没有单条详情端点，不用 detailBase）
  views: [{ label: '时间线', title: '风控时间线', path: (id) => `/admin/v1/risk/users/${id}/timeline` }],
};

/* --------------------------------- 关联团伙 -------------------------------- */

/** 三态枚举（列表注释：1=观察中 2=已处置 0=误判）—— 不是 0/1 开关，行内启停那个控件表达不了。 */
const CLUSTER_STATUS_OPTIONS: FieldOption[] = [
  { value: '1', label: '1 观察中' },
  { value: '2', label: '2 已处置' },
  { value: '0', label: '0 误判' },
];

/**
 * 已确认团伙：动作型 —— 写入走 POST /clusters/confirm（字段与 create 那套不同，见 pages/risk.tsx 的
 * 检测/确认面板），所以这里不给 fields（否则会摆一个必然 404 的「+ 新建」）。
 */
export const RISK_CLUSTER_CRUD: CrudConfig = {
  base: '/admin/v1/risk/clusters',
  noun: '团伙',
  actions: [
    {
      label: '状态',
      title: '变更团伙状态',
      path: (id) => `/admin/v1/risk/clusters/${id}/status`,
      method: 'PUT',
      fields: [
        {
          name: 'status',
          label: '目标状态',
          type: 'select',
          required: true,
          options: CLUSTER_STATUS_OPTIONS,
          hint: '1=观察中 2=已处置 0=误判。三态，故不用 0/1 启停（按 0/1 翻转会把 2 静默压成 0）；当前值见列表 status 列',
        },
      ],
      confirm: (row) => `确认变更团伙「${labelOf(row, 'name')}」的状态？`,
      report: (envelope) => {
        const cluster = ((envelope.data ?? {}) as Row).cluster as Row | undefined;
        return cluster ? `团伙「${String(cluster.name)}」状态已置为 ${String(cluster.status)}` : envelope.message;
      },
    },
  ],
  views: [{ label: '成员', title: '团伙成员', path: (id) => `/admin/v1/risk/clusters/${id}/members` }],
};

/* -------------------------------- 反作弊事件 ------------------------------- */

/** 复核结论值域 = AntiCheatController::review 的 in_array 列表（字符串枚举，不是 0/1）。 */
const ANTICHEAT_STATUS_OPTIONS: FieldOption[] = [
  { value: 'open', label: 'open 待处理' },
  { value: 'confirmed', label: 'confirmed 确认作弊' },
  { value: 'whitelisted', label: 'whitelisted 白名单' },
  { value: 'closed', label: 'closed 关闭' },
];

/**
 * 反作弊事件：动作型 —— 只有 list/detail + review，没有增/改/删 ⇒ 不给 fields / labelKey。
 * review 对任何 status 都收（控制器没有 CAS 前置状态），故不做 when 过滤，只把当前状态写进确认文案。
 */
export const ANTICHEAT_CRUD: CrudConfig = {
  base: '/admin/v1/anticheat/events',
  noun: '反作弊事件',
  actions: [
    {
      label: '复核',
      title: '人工复核',
      path: (id) => `/admin/v1/anticheat/events/${id}/review`,
      fields: [
        {
          name: 'status',
          label: '复核结论',
          type: 'select',
          required: true,
          options: ANTICHEAT_STATUS_OPTIONS,
          hint: '字符串枚举（不是 0/1）；当前值见列表 status 列。whitelisted 建议附理由',
        },
        { name: 'note', label: '复核备注', type: 'textarea', hint: '写进 review_note（最长 255 字，超出会被截断）' },
      ],
      confirm: (row) =>
        `确认提交对事件「${labelOf(row, 'rule_name')}」（用户 ${String(row.user_id ?? '—')}，当前状态 ${String(row.status ?? '—')}）的复核？`,
      report: (envelope) => {
        const data = (envelope.data ?? {}) as Row;
        return `${String(data.message ?? envelope.message)}（新状态 ${String(data.status ?? '—')}）`;
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
  noun: '设备指纹',
  // hashid 位在 fp_hash 列（不是 id）：不指这一下，整行动作会因取不到 id 而不渲染
  rowKey: 'fp_hash',
  actions: [
    {
      label: '拉黑',
      path: () => '/admin/v1/risk/device/block',
      body: (id) => ({ fp_hash: id }),
      // 反向判：真值只可能是 blocked===true 才叫已拉黑，其余（含字段缺失）都当未拉黑 ——
      // 缺失时用 === false 会让两个按钮都不出现，整行变成死行。
      when: (row) => row.blocked !== true,
      confirm: (row) =>
        `确认拉黑设备 ${labelOf(row, 'fp_masked')}？拉黑后该设备的充值/提现会被风控**直接阻断**`
        + `（Redis 标记，30 天后自动过期；不看规则是否启用）。`,
      report: (envelope) =>
        `已拉黑 ${String(((envelope.data ?? {}) as Row).fp_masked ?? '')}（Redis 标记，30 天后自动过期）`,
    },
    {
      label: '解封',
      path: () => '/admin/v1/risk/device/unblock',
      body: (id) => ({ fp_hash: id }),
      when: (row) => row.blocked === true,
      confirm: (row) => `确认解封设备 ${labelOf(row, 'fp_masked')}？解封后这条标记不再阻断它的充值/提现。`,
      // unblock 只回空 data（success()），认不出对象；列表行自己会翻回「未拉黑」
      report: () => '已解封（Redis 标记已删除）',
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
  label: string;
  title: string;
  path: string;
  field: Field;
  /** 二次确认：动作改的是服务端状态（IP 的信誉行 + 删其 Redis 缓存），文案要认出「改谁、改成什么」 */
  confirm: (value: string) => string;
  /** 成功提示：这些端点只回 `success` 占位符，有用信息在 data（ip_masked / source / message） */
  report?: (envelope: Envelope<unknown>, value: string) => string;
};

const IP_FIELD: Field = {
  name: 'ip',
  label: 'IP 地址',
  type: 'text',
  required: true,
  placeholder: '1.2.3.4',
  hint: '**原文** IP（服务端 filter_var 校验后自己算 sha256 写库），不是哈希值；列表只回显 ip_masked，可用列表上的 keyword 过滤前缀定位',
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
    label: '拉黑 IP',
    title: '拉黑 IP',
    path: '/admin/v1/risk/ip/block',
    field: IP_FIELD,
    confirm: (value) =>
      `确认把 ${value} 写入黑名单（source=internal_blacklist，score 0）？评估器会据此处置，并删掉该 IP 的信誉缓存使其立刻生效。`,
    report: (envelope) => {
      const data = (envelope.data ?? {}) as Row;
      return `已写入黑名单 ${String(data.ip_masked ?? '')}（source=${String(data.source ?? '')}），信誉缓存已删`;
    },
  },
  {
    label: '白名单/申诉放行',
    title: '加入白名单（申诉放行）',
    path: '/admin/v1/risk/ip/whitelist',
    field: IP_FIELD,
    confirm: (value) =>
      `确认把 ${value} 加入白名单（source=internal_whitelist，score 100）？白名单在评估器里最先判、直接放行 —— 误加等于给这个 IP 开免检。`,
    report: (envelope) => {
      const data = (envelope.data ?? {}) as Row;
      return `已放行 ${String(data.ip_masked ?? '')}（source=${String(data.source ?? '')}），信誉缓存已删`;
    },
  },
  {
    label: '重查',
    title: '重查（清本地信誉缓存）',
    path: '/admin/v1/risk/ip/recheck',
    field: IP_FIELD,
    confirm: (value) =>
      `确认重查 ${value}？只删除本地信誉缓存、下次请求重读 DB；外部代理/VPN 检测服务未接入，不会问到第三方。`,
    report: (envelope) => String(((envelope.data ?? {}) as Row).message ?? '已刷新信誉缓存'),
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
      label: '团伙类型',
      type: 'select',
      required: true,
      options: [
        { value: 'same_ip', label: 'same_ip 同 IP' },
        { value: 'same_device', label: 'same_device 同设备' },
        { value: 'same_pay_account', label: 'same_pay_account 同支付账户' },
        { value: 'manual', label: 'manual 人工标注' },
      ],
      hint: 'same_ip / same_device 必须给指纹（服务端据此反查成员）；另两类可只给成员列表',
    },
    {
      name: 'fingerprint',
      label: '指纹（哈希）',
      type: 'text',
      placeholder: '同 IP 填 ip_hash、同设备填 fp_hash',
      hint: '检测结果给的『指纹』列就是**完整值**（界面只显示前 8 位）；选 same_ip/same_device 时必填，服务端用它反查成员',
    },
    { name: 'name', label: '团伙名称', type: 'text', required: true, placeholder: '最长 100 字' },
    {
      name: 'member_ids',
      label: '成员 hashid',
      type: 'lines',
      placeholder: '每行一个',
      hint: '**hashid**（不是数字 id）：服务端逐个 decodeId，非法值直接 400 拒掉（不会静默丢）。候选见已建团伙的「成员」只读视图（回 id=hashid 与用户名）。留空即不写成员 —— same_ip / same_device 会按指纹反查成员',
    },
    {
      name: 'user_count',
      label: '成员数',
      type: 'number',
      hint: '列表展示用；服务端取「成员列表条数」与这里的较大值，故留空也不会小于实际成员数',
    },
  ] as Field[],
};

/** 角色 / 权限的删除都要当前登录密码（BaseController::confirmPassword）。 */
function deleteWithPassword(question: string): Record<string, unknown> | null {
  if (!window.confirm(question)) return null;
  const password = window.prompt('该操作需要输入当前登录密码：');
  return password === null ? null : { password };
}
