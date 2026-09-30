/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 「资金」四个模块的字段描述（批次 4）：支付方式 / 优惠券 / CDN 厂商 / 提现阶梯限额。
 * 契约与共用小工具见 ResourceSchema.ets；字段值域以控制器 validator 与列定义为真值：
 *   admin/app/admin/v1/controller/{Payment,Coupon,CdnProvider,Withdraw}Controller.php
 *   install/install.sql（game_payment_method / game_coupon / game_cdn_provider / game_withdraw_limit）
 *
 * 本册特有的四条口径：
 *  1. **金额一律 text 类型**（min_amount / max_amount / value / single_min / fee_pct …）：列都是
 *     DECIMAL，走 number 控件会让 FormDialog 过一道 Number()（float），小数位在那里就没了；
 *     格式交给服务端的 numeric 规则。排序/数量这类计数列（sort/total_qty/user_limit）照旧用 number。
 *  2. 金额字段**给缺省值不给空串**：控制器的 numeric 规则（无 nullable）会把空串 422 掉
 *     （coupon 的 min_amount 更狠：create 无规则、直接进 DECIMAL NOT NULL 列，空串是 1366/500），
 *     故这些字段一律 prefill '0.0000' 之类并置 required，把「空」挡在本地。
 *  3. 值是 hashid 的字段（coupon.game_id）候选取自另一个端点，走 prepareForm 钩子拉（与角色的
 *     permission_ids 同手法）。空串 = 提交时跳过该字段（后端缺省 0 = 全平台通用）。
 *  4. 提现阶梯限额**没有逐档新建、也没有删除**（config/route.php:156-158 只有 list/set/update）：
 *     create 这条路被借去走「全档位重置」（POST /withdraw/limits/set），故 createLabel 另起名、
 *     canDelete=false；重置表单的初值取自 default 档的当前值（见 limitRow 的注释）。
 */

import { FormField, FieldOption } from './FormDialog';
import {
  FormValues,
  FormPayload,
  ResourceRow,
  ResourceListResult,
  PaymentMethodItem,
  CouponItem,
  CouponStats,
  CdnProviderItem,
  WithdrawLimitItem
} from '../model/DataModels';
import { ResourceModule, buildRow, unpaged, paged } from './ResourceSchema';
import { GameService } from '../service/DataService';

/** 启用/停用两态（payment/cdn 都有 status 列，且都有独立的 toggle 端点） */
const STATUS_OPTIONS: FieldOption[] = [
  { label: '启用', value: '1' },
  { label: '停用', value: '0' }
];

/* =============================== 1 支付方式 ===============================
 * list: GET /admin/v1/payment/method/list（整表返回，无 total）
 * create: name*(≤50) type*(fiat|crypto) provider*(18 枚举)
 *         status*(0/1) sort countries(array, ≤2 位/项) currency(≤10)
 *         min_amount max_amount numeric|min:0（**不可空**）config(string, 加密存储)
 * update: 同上全部（status 变 in:0,1 非必填）；config 传空串 = 清 NULL，不传 = 不动
 * delete: DELETE /payment/method/{hashid}；有 pending 充值单时 422 拒绝
 * 状态：POST /payment/method/toggle（{id, status}）⇒ statusMode='toggle'
 * config 在列表里回传的是**解密后的原文**（PaymentController::list）⇒ 编辑态可原样回填；
 *   不动它就不提交（partial），只有真改了才会写回。
 * ========================================================================== */

const PAYMENT_PROVIDERS: FieldOption[] = [
  { label: 'stripe', value: 'stripe' },
  { label: 'nowpayments', value: 'nowpayments' },
  { label: 'coinbase', value: 'coinbase' },
  { label: 'paypal', value: 'paypal' },
  { label: 'skrill', value: 'skrill' },
  { label: 'neteller', value: 'neteller' },
  { label: 'paysafecard', value: 'paysafecard' },
  { label: 'paytm', value: 'paytm' },
  { label: 'mercadopago', value: 'mercadopago' },
  { label: 'astropay', value: 'astropay' },
  { label: 'paypay', value: 'paypay' },
  { label: 'kakaopay', value: 'kakaopay' },
  { label: 'gcash', value: 'gcash' },
  { label: 'mpesa', value: 'mpesa' },
  { label: 'paystack', value: 'paystack' },
  { label: 'toss', value: 'toss' },
  { label: 'adyen', value: 'adyen' },
  { label: 'grabpay', value: 'grabpay' }
];

/**
 * 可见国家候选 —— 与 flutter 兄弟树 pages/payment/payment_page.dart:13 同一份短名单。
 * 不做全量 ISO 表：multi 控件是全展开勾选，249 个码要滚三屏，而这一列的实际用法是
 * 「给几个主要市场开或不开」。全不选 = 空数组 = 全球（PaymentMethod::isAvailableIn 的口径）。
 * ponytail: 短名单到这就是上限，真要按市场精细投放再改成从国家配置列表拉候选。
 */
const PAYMENT_COUNTRIES: FieldOption[] = [
  { label: '中国 CN', value: 'CN' },
  { label: '美国 US', value: 'US' },
  { label: '日本 JP', value: 'JP' },
  { label: '韩国 KR', value: 'KR' },
  { label: '巴西 BR', value: 'BR' },
  { label: '印度 IN', value: 'IN' },
  { label: '德国 DE', value: 'DE' },
  { label: '英国 GB', value: 'GB' }
];

const PAYMENT_FIELDS: FormField[] = [
  { name: 'name', label: '支付方式名称', type: 'text', required: true, placeholder: '最长 50 字' },
  {
    name: 'type', label: '类型', type: 'select', required: true, options: [
      { label: '法币', value: 'fiat' },
      { label: '加密货币', value: 'crypto' }
    ]
  },
  { name: 'provider', label: '提供商', type: 'select', required: true, options: PAYMENT_PROVIDERS },
  { name: 'status', label: '状态', type: 'select', required: true, options: STATUS_OPTIONS },
  { name: 'countries', label: '可见国家', type: 'multi', options: PAYMENT_COUNTRIES },
  { name: 'currency', label: '限定币种', type: 'text', placeholder: '如 USDT 方法限 USD；空 = 任意，≤10 位' },
  { name: 'min_amount', label: '最小充值', type: 'text', required: true, placeholder: '十进制金额，0 = 不限' },
  { name: 'max_amount', label: '最大充值', type: 'text', required: true, placeholder: '十进制金额，0 = 不限' },
  { name: 'config', label: '支付配置', type: 'textarea', placeholder: 'JSON 文本（加密存储），如 {"network":"TRC20"}；留空用默认' },
  { name: 'sort', label: '排序', type: 'number', placeholder: '越小越靠前，缺省 0' }
];

function paymentInitial(): FormValues {
  const values: FormValues = {};
  values.name = '';
  values.type = 'fiat';
  values.provider = 'stripe';
  // 新建的支付方式默认停用：接错商户号时不会有真实订单打进来
  values.status = '0';
  values.countries = '';
  values.currency = '';
  values.min_amount = '0';
  values.max_amount = '0';
  values.config = '';
  values.sort = '0';
  return values;
}

function paymentRow(item: PaymentMethodItem): ResourceRow {
  const values: FormValues = {};
  values.name = item.name;
  values.type = item.type;
  values.provider = item.provider;
  values.status = `${item.status}`;
  const codes = item.countries ?? [];
  // 逗号连接串就是 FormDialog multi 的取值编码（hashid/国家码都不含逗号）
  values.countries = codes.join(',');
  values.currency = item.currency ?? '';
  values.min_amount = item.min_amount ?? '';
  values.max_amount = item.max_amount ?? '';
  values.config = item.config ?? '';
  values.sort = `${item.sort}`;
  const scope = codes.length > 0 ? codes.join('/') : '全球';
  const range = `${item.min_amount ?? '0'} ~ ${item.max_amount ?? '0'}`;
  return buildRow(item.id, item.name, `${item.type} · ${item.provider} · ${range} · ${scope}`,
    item.status === 1 ? '启用' : '停用', item.status === 1, item.status, values);
}

const PAYMENT: ResourceModule = {
  key: 'payment_method',
  title: '支付方式',
  desc: '法币/加密货币通道的额度、可见国家与启停',
  base: '/admin/v1/payment/method',
  paginated: false,
  listPath: (_page: number, _limit: number, _gameId: string): string => '/admin/v1/payment/method/list',
  parseList: (data: Object): ResourceListResult => unpaged<PaymentMethodItem>(data, paymentRow),
  fields: PAYMENT_FIELDS,
  editFields: PAYMENT_FIELDS,
  createInitial: paymentInitial,
  statusMode: 'toggle',
  togglePath: '/admin/v1/payment/method/toggle',
  needsGame: false,
  deletePassword: false,
  extraText: ''
};

/* ================================ 2 优惠券 ================================
 * list: GET /admin/v1/coupon/list（page/limit；另有 status/type/game_id 过滤，本页不挂）
 * create: name*(≤100) type*(fixed|rate) value*(numeric, ≥0.0001) —— 校验只有这三条；
 *   min_amount/max_discount/total_qty/user_limit/game_id 是**直接读入**的（无规则）⇒
 *   空串会进 DECIMAL NOT NULL 列（500），故这三个金额字段必须给缺省值（且本地 required）。
 *   status 由后端硬编码 1 ⇒ 新建表单不摆状态。
 * update: name type value min_amount max_discount total_qty user_limit start_at end_at status
 *   + game_id（input 单独取，非 null 才写）；**used_qty>0 一律 400「已有用户领取，无法修改」**
 * delete: DELETE /coupon/{hashid} —— 连用户领取记录一起删（UserCoupon）
 * 状态：无 toggle 端点 ⇒ 行内切换走局部 PUT {status}（update 的 status 规则 in:0,1）
 * stats: GET /coupon/{hashid}/stats 是**只读**详情 ⇒ 走行内额外动作 GET + extraMessage
 * conditions（JSON 列）**本表单不摆**：create 不收、update 的 only() 白名单里也没有它
 *   （CouponController 两个方法都没有这个键）⇒ 走管理端点根本写不进去。C 端领券时读它（
 *   service/app/api/v1/controller/CouponController::conditionsBlockReason）⇒ 是后端的缺口，
 *   不是本树能补的（报告里另记）。
 * start_at/end_at 不进表单：要日期控件，不传即不动（与活动/公告同一取舍）。
 * ========================================================================== */

/**
 * 适用游戏的候选取自游戏列表端点（另一个端点，故走 prepareForm 钩子回填 options）。
 * 与角色的权限多选同一手法：字段对象先建壳，拿到候选后原地改它的 options。
 */
const COUPON_GAME_FIELD: FormField = {
  name: 'game_id',
  label: '适用游戏',
  type: 'select',
  options: []
};

const COUPON_FIELDS: FormField[] = [
  { name: 'name', label: '优惠券名称', type: 'text', required: true, placeholder: '最长 100 字' },
  {
    name: 'type', label: '类型', type: 'select', required: true, options: [
      { label: '固定金额（平台币面值）', value: 'fixed' },
      { label: '折扣率（如 0.10 = 9 折）', value: 'rate' }
    ]
  },
  { name: 'value', label: '面值 / 折扣率', type: 'text', required: true, placeholder: '十进制，fixed 为平台币额、rate 为 0.0001~1' },
  { name: 'min_amount', label: '最低使用金额', type: 'text', required: true, placeholder: '十进制金额，0 = 无门槛' },
  { name: 'max_discount', label: '最大优惠金额', type: 'text', required: true, placeholder: '十进制金额，0 = 不封顶（rate 类型有效）' },
  COUPON_GAME_FIELD,
  { name: 'total_qty', label: '发行总量', type: 'number', placeholder: '整数，0 = 不限' },
  { name: 'user_limit', label: '每人限领', type: 'number', placeholder: '整数，≥ 1，缺省 1' }
];

/** 编辑态多一个 status（新建那条路后端硬编码 status=1，摆了也是个假开关）；其余字段同表 */
function couponEditFields(): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < COUPON_FIELDS.length; i++) {
    fields.push(COUPON_FIELDS[i]);
  }
  fields.push({ name: 'status', label: '状态', type: 'select', options: STATUS_OPTIONS });
  return fields;
}

function couponInitial(): FormValues {
  const values: FormValues = {};
  values.name = '';
  values.type = 'fixed';
  values.value = '';
  values.min_amount = '0.0000';
  values.max_discount = '0.0000';
  values.game_id = '';
  values.total_qty = '0';
  values.user_limit = '1';
  values.status = '1';
  return values;
}

/** 开框前拉游戏列表填候选（每次开框都拉：游戏随时会新增，缓存只会让用户选到过期项） */
async function loadCouponGameOptions(): Promise<void> {
  const result = await GameService.getList(1, 100, '');
  const options: FieldOption[] = [];
  for (let i = 0; i < result.list.length; i++) {
    options.push({ label: result.list[i].name, value: result.list[i].id });
  }
  COUPON_GAME_FIELD.options = options;
}

function couponRow(item: CouponItem): ResourceRow {
  const values: FormValues = {};
  values.name = item.name;
  values.type = item.type;
  values.value = item.value;
  values.min_amount = item.min_amount ?? '';
  values.max_discount = item.max_discount ?? '';
  // 0（全平台通用）是裸数字，编不出 hashid ⇒ 只在对得上时才当表单值；对不上时空串 = 提交时跳过
  values.game_id = typeof item.game_id === 'string' ? item.game_id : '';
  values.total_qty = `${item.total_qty}`;
  values.user_limit = `${item.user_limit}`;
  values.status = `${item.status}`;
  const unit = item.type === 'rate' ? `折扣率 ${item.value}` : `面值 ${item.value}`;
  const quota = item.total_qty > 0 ? `已领 ${item.used_qty}/${item.total_qty}` : `已领 ${item.used_qty} · 不限量`;
  const threshold = item.min_amount === '0.0000' ? '无门槛' : `门槛 ${item.min_amount}`;
  return buildRow(item.id, item.name, `${unit} · ${threshold} · ${quota}`,
    item.status === 1 ? '启用' : '停用', item.status === 1, item.status, values);
}

/** 统计：端点只回 data（message 是框架缺省 'success'）⇒ 由这里拼成一句给运营看的话 */
function couponStatsText(data: Object, _message: string): string {
  const stats = data as CouponStats;
  const remaining = stats.remaining === null || stats.remaining === undefined
    ? '不限量'
    : `${stats.remaining}`;
  return `总量 ${stats.total_qty} · 已领 ${stats.used_qty} · 剩余 ${remaining} · 使用率 ${stats.usage_rate}%`;
}

const COUPON: ResourceModule = {
  key: 'coupon',
  title: '优惠券',
  desc: '券定义、门槛与发行量（已领取的不可改）',
  base: '/admin/v1/coupon',
  paginated: true,
  listPath: (page: number, limit: number, _gameId: string): string =>
    `/admin/v1/coupon/list?page=${page}&limit=${limit}`,
  parseList: (data: Object): ResourceListResult => paged<CouponItem>(data, couponRow),
  fields: COUPON_FIELDS,
  editFields: couponEditFields(),
  createInitial: couponInitial,
  prepareForm: loadCouponGameOptions,
  statusMode: 'update',
  togglePath: '',
  needsGame: false,
  deletePassword: false,
  extraText: '统计',
  extraPath: (id: string): string => `/admin/v1/coupon/${id}/stats`,
  extraMethod: 'GET',
  extraMessage: couponStatsText
};

/* ============================== 3 CDN 厂商 ===============================
 * list: GET /admin/v1/cdn/provider/list（整表，按 sort）—— **config 被 unset**（凭据不回传）
 * create: name*(≤50) provider*(cloudflare|cloudfront|aliyun|tencent|huawei) config(string)
 *         status*(0/1) sort；同厂商已存在 422
 * update: name provider config status sort；config **留空 = 不修改**（只有非空才写）
 * delete: DELETE /cdn/provider/{hashid}
 * toggle: POST /cdn/provider/toggle（{id, status}）⇒ statusMode='toggle'
 * test:   POST /cdn/provider/test（**id 在 body 里**，路径无参）⇒ HeadBucket 验凭据与 bucket，
 *         成功 200「连通正常」、失败 422 带探测原因 ⇒ 走行内额外动作（extraBody 送 id），
 *         文案直接用服务端 message（不另编）。
 * config 编辑态**不回填**（列表不回传，这是有意为之）⇒ 留空即不动；要换凭据就整段重贴。
 * ========================================================================== */

const CDN_FIELDS: FormField[] = [
  { name: 'name', label: '显示名称', type: 'text', required: true, placeholder: '最长 50 字' },
  {
    name: 'provider', label: '厂商', type: 'select', required: true, options: [
      { label: 'Cloudflare', value: 'cloudflare' },
      { label: 'CloudFront', value: 'cloudfront' },
      { label: '阿里云', value: 'aliyun' },
      { label: '腾讯云', value: 'tencent' },
      { label: '华为云', value: 'huawei' }
    ]
  },
  { name: 'status', label: '状态', type: 'select', required: true, options: STATUS_OPTIONS },
  { name: 'config', label: '连接配置', type: 'textarea', placeholder: 'JSON 文本（加密存储），如 {"bucket":"...","access_key":"..."}' },
  { name: 'sort', label: '排序', type: 'number', placeholder: '越小越靠前，缺省 0' }
];

/** 编辑态：config 表意不同（留空 = 不动），文案与新建分开写 */
function cdnEditFields(): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < CDN_FIELDS.length; i++) {
    const field = CDN_FIELDS[i];
    if (field.name === 'config') {
      // 凭据不回传 ⇒ 无法回显；留空 = 不提交这个字段 = 库中原值不动
      fields.push({ name: 'config', label: '连接配置', type: 'textarea', placeholder: '留空 = 不修改（凭据不回传，无法回显）' });
    } else {
      fields.push(field);
    }
  }
  return fields;
}

function cdnInitial(): FormValues {
  const values: FormValues = {};
  values.name = '';
  values.provider = 'cloudflare';
  values.status = '0';
  values.config = '';
  values.sort = '0';
  return values;
}

function cdnRow(item: CdnProviderItem): ResourceRow {
  const values: FormValues = {};
  values.name = item.name;
  values.provider = item.provider;
  values.status = `${item.status}`;
  values.sort = `${item.sort}`;
  // config 不在表单值里：它不回传、也不参与局部更新的「改没改」判断
  return buildRow(item.id, item.name, `${item.provider} · 排序 ${item.sort}`,
    item.status === 1 ? '启用' : '停用', item.status === 1, item.status, values);
}

const CDN: ResourceModule = {
  key: 'cdn_provider',
  title: 'CDN 厂商',
  desc: '五厂商配置、启停与连通测试',
  base: '/admin/v1/cdn/provider',
  paginated: false,
  listPath: (_page: number, _limit: number, _gameId: string): string => '/admin/v1/cdn/provider/list',
  parseList: (data: Object): ResourceListResult => unpaged<CdnProviderItem>(data, cdnRow),
  fields: CDN_FIELDS,
  editFields: cdnEditFields(),
  createInitial: cdnInitial,
  statusMode: 'toggle',
  togglePath: '/admin/v1/cdn/provider/toggle',
  needsGame: false,
  deletePassword: false,
  extraText: '连通测试',
  extraPath: (_id: string): string => '/admin/v1/cdn/provider/test',
  extraBody: (id: string): FormPayload => {
    const body: FormPayload = {};
    body.id = id;
    return body;
  }
};

/* ============================ 4 提现阶梯限额 ============================
 * list:    GET /admin/v1/withdraw/limits/list（整表，无 total）
 * set:     POST /admin/v1/withdraw/limits/set —— **全档位重置**：把 daily_limit/min_amount/
 *          auto_approve_threshold 三个值写进**每一档**（并把 min_amount 落到 single_min 列），
 *          同事务写 platform_config 回落值；新下限高于某档既有上限则整笔 422。
 *          single_max / monthly_limit 不在它范围内（保持各档既有值）。
 * update:  PUT /admin/v1/withdraw/limits/{hashid} —— 逐档精调（7 个金额列 + fee_pct 上限 <100）
 * 没有逐档新建、没有删除（config/route.php:156-158）⇒ create 那条路借来走 set（createLabel）、
 *   且 canDelete=false。user_level 不在 update 的 only() 白名单里 ⇒ 编辑态置灰。
 * 8 个列全是 DECIMAL（fee_pct 还是 UNSIGNED）⇒ **一律 text 输入**，前端不做任何换算。
 * ======================================================================= */

/**
 * 全档位重置表单的初值 —— 由 limitRow 从「default 档」的当前值填进来。
 * 不这样做的话表单会以空串开局：numeric 规则不收空串（422），而空串进 DECIMAL 列更糟。
 * 预填当前值 = 运营看得见现状、只改要改的那一项，语义正好对上「重置为这几个值」。
 */
let limitResetSeed: FormValues = {};
/** 初值是否取自 default 档（default 档可能排在列表后面，见到它要以它为准） */
let limitSeedFromDefault: boolean = false;

function seedLimitReset(item: WithdrawLimitItem): void {
  limitResetSeed['daily_limit'] = item.daily_limit;
  limitResetSeed['min_amount'] = item.single_min;
  limitResetSeed['auto_approve_threshold'] = item.auto_approve_threshold;
}

const LIMIT_RESET_FIELDS: FormField[] = [
  { name: 'daily_limit', label: '每日限额', type: 'text', required: true, placeholder: '十进制金额，写进所有档位' },
  { name: 'min_amount', label: '单笔最低', type: 'text', required: true, placeholder: '写进所有档位的 single_min（低于某档上限才放行）' },
  { name: 'auto_approve_threshold', label: '自动审批阈值', type: 'text', required: true, placeholder: '写进所有档位' }
];

const LIMIT_EDIT_FIELDS: FormField[] = [
  { name: 'user_level', label: '档位', type: 'text', placeholder: '创建后不可修改', disabled: true },
  { name: 'single_min', label: '单笔最低', type: 'text', placeholder: '十进制金额' },
  { name: 'single_max', label: '单笔最高', type: 'text', placeholder: '十进制金额，0 = 不限' },
  { name: 'daily_limit', label: '每日限额', type: 'text', placeholder: '十进制金额' },
  { name: 'monthly_limit', label: '每月限额', type: 'text', placeholder: '十进制金额' },
  { name: 'fee_pct', label: '手续费率', type: 'text', placeholder: '百分数，如 0.50（须 < 100）' },
  { name: 'fee_max', label: '手续费上限', type: 'text', placeholder: '十进制金额，0 = 不封顶' },
  { name: 'auto_approve_threshold', label: '自动审批阈值', type: 'text', placeholder: '十进制金额' }
];

function limitInitial(): FormValues {
  const values: FormValues = {};
  const keys = Object.keys(limitResetSeed);
  for (let i = 0; i < keys.length; i++) {
    values[keys[i]] = limitResetSeed[keys[i]];
  }
  return values;
}

function limitRow(item: WithdrawLimitItem): ResourceRow {
  const values: FormValues = {};
  values.user_level = item.user_level;
  values.single_min = item.single_min;
  values.single_max = item.single_max;
  values.daily_limit = item.daily_limit;
  values.monthly_limit = item.monthly_limit;
  values.fee_pct = item.fee_pct;
  values.fee_max = item.fee_max;
  values.auto_approve_threshold = item.auto_approve_threshold;
  // 重置表单的初值取 default 档（「无档位」那档的名义来源）；没有 default 就退回列表第一档
  if (item.user_level === 'default') {
    seedLimitReset(item);
    limitSeedFromDefault = true;
  } else if (!limitSeedFromDefault && limitResetSeed['daily_limit'] === undefined) {
    seedLimitReset(item);
  }
  const singleMax = item.single_max === '0.0000' ? '不限' : item.single_max;
  return buildRow(item.id, `档位 ${item.user_level}`,
    `单笔 ${item.single_min}~${singleMax} · 日 ${item.daily_limit} · 月 ${item.monthly_limit} · 费率 ${item.fee_pct}%（上限 ${item.fee_max}）`,
    '', true, 0, values);
}

const WITHDRAW_LIMIT: ResourceModule = {
  key: 'withdraw_limit',
  title: '提现阶梯限额',
  desc: '各档位额度与费率，含全档位重置',
  base: '/admin/v1/withdraw/limits',
  // 没有逐档新建：这条路是「全档位重置」（POST /withdraw/limits/set），按钮与标题另起名
  createPath: '/admin/v1/withdraw/limits/set',
  createLabel: '全档位重置',
  canDelete: false,
  paginated: false,
  listPath: (_page: number, _limit: number, _gameId: string): string => '/admin/v1/withdraw/limits/list',
  parseList: (data: Object): ResourceListResult => unpaged<WithdrawLimitItem>(data, limitRow),
  fields: LIMIT_RESET_FIELDS,
  editFields: LIMIT_EDIT_FIELDS,
  createInitial: limitInitial,
  statusMode: '',
  togglePath: '',
  needsGame: false,
  deletePassword: false,
  extraText: ''
};

/** 本组模块（注册表按此顺序拼装） */
export const FUND_MODULES: ResourceModule[] = [PAYMENT, COUPON, CDN, WITHDRAW_LIMIT];
