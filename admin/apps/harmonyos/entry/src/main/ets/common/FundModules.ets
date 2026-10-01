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
  WithdrawLimitItem,
  pageQuery
} from '../model/DataModels';
import { ResourceModule, buildRow, unpaged, paged } from './ResourceSchema';
import { GameService } from '../service/DataService';
import { t, t1 } from '../i18n/Translations';

/** 启用/停用两态（payment/cdn 都有 status 列，且都有独立的 toggle 端点） */
const STATUS_OPTIONS: FieldOption[] = [
  { label: 'common.enabled', value: '1' },
  { label: 'common.disabled', value: '0' }
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
  { label: 'country.cn', value: 'CN' },
  { label: 'country.us', value: 'US' },
  { label: 'country.jp', value: 'JP' },
  { label: 'country.kr', value: 'KR' },
  { label: 'country.br', value: 'BR' },
  { label: 'country.in', value: 'IN' },
  { label: 'country.de', value: 'DE' },
  { label: 'country.gb', value: 'GB' }
];

const PAYMENT_FIELDS: FormField[] = [
  { name: 'name', label: 'payment.name', type: 'text', required: true, placeholder: 'payment.name_hint' },
  {
    name: 'type', label: 'payment.type', type: 'select', required: true, options: [
      { label: 'payment.fiat', value: 'fiat' },
      { label: 'payment.crypto', value: 'crypto' }
    ]
  },
  { name: 'provider', label: 'payment.provider', type: 'select', required: true, options: PAYMENT_PROVIDERS },
  { name: 'status', label: 'payment.status', type: 'select', required: true, options: STATUS_OPTIONS },
  { name: 'countries', label: 'payment.countries', type: 'multi', options: PAYMENT_COUNTRIES },
  { name: 'currency', label: 'payment.currency', type: 'text', placeholder: 'payment.currency_hint' },
  { name: 'min_amount', label: 'payment.min_amount', type: 'text', required: true, placeholder: 'payment.amount_hint' },
  { name: 'max_amount', label: 'payment.max_amount', type: 'text', required: true, placeholder: 'payment.amount_hint' },
  { name: 'config', label: 'payment.config', type: 'textarea', placeholder: 'payment.config_hint' },
  { name: 'sort', label: 'payment.sort', type: 'number', placeholder: 'payment.sort_hint' }
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
  const scope = codes.length > 0 ? codes.join('/') : t('payment.global');
  const range = `${item.min_amount ?? '0'} ~ ${item.max_amount ?? '0'}`;
  return buildRow(item.id, item.name, `${item.type} · ${item.provider} · ${range} · ${scope}`,
    item.status === 1 ? t('common.enabled') : t('common.disabled'), item.status === 1, item.status, values);
}

const PAYMENT: ResourceModule = {
  key: 'payment_method',
  title: 'mod.payment_method.title',
  desc: 'mod.payment_method.desc',
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
  label: 'coupon.game_id',
  type: 'select',
  options: []
};

const COUPON_FIELDS: FormField[] = [
  { name: 'name', label: 'coupon.name', type: 'text', required: true, placeholder: 'coupon.name_hint' },
  {
    name: 'type', label: 'coupon.type', type: 'select', required: true, options: [
      { label: 'coupon.type_fixed', value: 'fixed' },
      { label: 'coupon.type_rate', value: 'rate' }
    ]
  },
  { name: 'value', label: 'coupon.value', type: 'text', required: true, placeholder: 'coupon.value_hint' },
  { name: 'min_amount', label: 'coupon.min_amount', type: 'text', required: true, placeholder: 'coupon.min_amount_hint' },
  { name: 'max_discount', label: 'coupon.max_discount', type: 'text', required: true, placeholder: 'coupon.max_discount_hint' },
  COUPON_GAME_FIELD,
  { name: 'total_qty', label: 'coupon.total_qty', type: 'number', placeholder: 'coupon.total_qty_hint' },
  { name: 'user_limit', label: 'coupon.user_limit', type: 'number', placeholder: 'coupon.user_limit_hint' }
];

/** 编辑态多一个 status（新建那条路后端硬编码 status=1，摆了也是个假开关）；其余字段同表 */
function couponEditFields(): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < COUPON_FIELDS.length; i++) {
    fields.push(COUPON_FIELDS[i]);
  }
  fields.push({ name: 'status', label: 'coupon.status', type: 'select', options: STATUS_OPTIONS });
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
  // 行摘要按**数据装载时**的语言拼：`t()` 读静态 currentCode，不是可观测状态 ⇒ 切语言不会
  // 自动重排这一行，要等下次拉列表才跟着变。已知取舍，未挂 @Watch 重载（该页与并行写者共用）。
  const unit = item.type === 'rate' ? t1('coupon.rate_value', 'value', item.value)
    : t1('coupon.fixed_value', 'value', item.value);
  const quota = item.total_qty > 0
    ? t('coupon.quota_limited').replace('{used}', `${item.used_qty}`).replace('{total}', `${item.total_qty}`)
    : t1('coupon.quota_unlimited', 'used', `${item.used_qty}`);
  const threshold = item.min_amount === '0.0000' ? t('coupon.no_threshold')
    : t1('coupon.threshold', 'amount', item.min_amount);
  return buildRow(item.id, item.name, `${unit} · ${threshold} · ${quota}`,
    item.status === 1 ? t('common.enabled') : t('common.disabled'), item.status === 1, item.status, values);
}

/** 统计：端点只回 data（message 是框架缺省 'success'）⇒ 由这里拼成一句给运营看的话 */
function couponStatsText(data: Object, _message: string): string {
  const stats = data as CouponStats;
  const remaining = stats.remaining === null || stats.remaining === undefined
    ? t('coupon.unlimited') : `${stats.remaining}`;
  return t('coupon.stats_text').replace('{total}', `${stats.total_qty}`)
    .replace('{used}', `${stats.used_qty}`).replace('{remaining}', remaining)
    .replace('{rate}', `${stats.usage_rate}`);
}

const COUPON: ResourceModule = {
  key: 'coupon',
  title: 'mod.coupon.title',
  desc: 'mod.coupon.desc',
  base: '/admin/v1/coupon',
  paginated: true,
  listPath: (page: number, limit: number, _gameId: string): string =>
    `/admin/v1/coupon/list?${pageQuery(page, limit)}`,
  parseList: (data: Object): ResourceListResult => paged<CouponItem>(data, couponRow),
  fields: COUPON_FIELDS,
  editFields: couponEditFields(),
  createInitial: couponInitial,
  prepareForm: loadCouponGameOptions,
  statusMode: 'update',
  togglePath: '',
  needsGame: false,
  deletePassword: false,
  extraText: 'mod.coupon.action',
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
  { name: 'name', label: 'cdn.name', type: 'text', required: true, placeholder: 'cdn.name_hint' },
  {
    name: 'provider', label: 'cdn.provider', type: 'select', required: true, options: [
      { label: 'Cloudflare', value: 'cloudflare' },
      { label: 'CloudFront', value: 'cloudfront' },
      { label: 'cdn.aliyun', value: 'aliyun' },
      { label: 'cdn.tencent', value: 'tencent' },
      { label: 'cdn.huawei', value: 'huawei' }
    ]
  },
  { name: 'status', label: 'cdn.status', type: 'select', required: true, options: STATUS_OPTIONS },
  { name: 'config', label: 'cdn.config', type: 'textarea', placeholder: 'cdn.config_hint' },
  { name: 'sort', label: 'cdn.sort', type: 'number', placeholder: 'cdn.sort_hint' }
];

/** 编辑态：config 表意不同（留空 = 不动），文案与新建分开写 */
function cdnEditFields(): FormField[] {
  const fields: FormField[] = [];
  for (let i = 0; i < CDN_FIELDS.length; i++) {
    const field = CDN_FIELDS[i];
    if (field.name === 'config') {
      // 凭据不回传 ⇒ 无法回显；留空 = 不提交这个字段 = 库中原值不动
      fields.push({ name: 'config', label: 'cdn.config', type: 'textarea', placeholder: 'cdn.config_keep_hint' });
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
  return buildRow(item.id, item.name, `${item.provider} · ${t('cdn.sort')} ${item.sort}`,
    item.status === 1 ? t('common.enabled') : t('common.disabled'), item.status === 1, item.status, values);
}

const CDN: ResourceModule = {
  key: 'cdn_provider',
  title: 'mod.cdn_provider.title',
  desc: 'mod.cdn_provider.desc',
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
  extraText: 'mod.cdn_provider.action',
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
  { name: 'daily_limit', label: 'withdraw_limit.daily_limit', type: 'text', required: true, placeholder: 'withdraw_limit.daily_limit_hint' },
  { name: 'min_amount', label: 'withdraw_limit.single_min', type: 'text', required: true, placeholder: 'withdraw_limit.single_min_hint' },
  { name: 'auto_approve_threshold', label: 'withdraw_limit.auto_approve_threshold', type: 'text', required: true, placeholder: 'withdraw_limit.auto_hint' }
];

const LIMIT_EDIT_FIELDS: FormField[] = [
  { name: 'user_level', label: 'withdraw_limit.user_level', type: 'text', placeholder: 'common.immutable', disabled: true },
  { name: 'single_min', label: 'withdraw_limit.single_min', type: 'text', placeholder: 'withdraw_limit.amount_hint' },
  { name: 'single_max', label: 'withdraw_limit.single_max', type: 'text', placeholder: 'withdraw_limit.amount_unlimited_hint' },
  { name: 'daily_limit', label: 'withdraw_limit.daily_limit', type: 'text', placeholder: 'withdraw_limit.amount_hint' },
  { name: 'monthly_limit', label: 'withdraw_limit.monthly_limit', type: 'text', placeholder: 'withdraw_limit.amount_hint' },
  { name: 'fee_pct', label: 'withdraw_limit.fee_pct', type: 'text', placeholder: 'withdraw_limit.fee_pct_hint' },
  { name: 'fee_max', label: 'withdraw_limit.fee_max', type: 'text', placeholder: 'withdraw_limit.fee_max_hint' },
  { name: 'auto_approve_threshold', label: 'withdraw_limit.auto_approve_threshold', type: 'text', placeholder: 'withdraw_limit.amount_hint' }
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
  const singleMax = item.single_max === '0.0000' ? t('common.unlimited') : item.single_max;
  const subtitle = t('withdraw_limit.row_subtitle').replace('{min}', item.single_min)
    .replace('{max}', singleMax).replace('{daily}', item.daily_limit)
    .replace('{monthly}', item.monthly_limit).replace('{fee}', item.fee_pct)
    .replace('{feeMax}', item.fee_max);
  return buildRow(item.id, t1('withdraw_limit.row_title', 'level', item.user_level),
    subtitle, '', true, 0, values);
}

const WITHDRAW_LIMIT: ResourceModule = {
  key: 'withdraw_limit',
  title: 'mod.withdraw_limit.title',
  desc: 'mod.withdraw_limit.desc',
  base: '/admin/v1/withdraw/limits',
  // 没有逐档新建：这条路是「全档位重置」（POST /withdraw/limits/set），按钮与标题另起名
  createPath: '/admin/v1/withdraw/limits/set',
  createLabel: 'mod.withdraw_limit.create',
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
