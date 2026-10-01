/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Field } from '../core/crud';
import type { Act } from '../components/table';

/**
 * 财务中心四个标签页的**声明式常量**（字段表 / 动作表 / 枚举），与 finance.ts 的组件分开放：
 * 组件那边是流程（取数、确认、回执），这边是「后端 validator 长什么样」的对照表，
 * 单文件就守得住 <500 行。改字段前先读对应控制器的 validator，别照 DB 列名硬凑。
 *
 * 本文件里的 label/placeholder **全是词条键**（见 dict/finance.ts），在消费处翻：
 * 字段表由 ui-form 过 `t()`，动作表由 ui-table 过 `t()`。
 */

/** 订单状态筛选（真值 = withdraw_order.status 枚举 / orders 端点的 status 参数）；label 是词条键，过 `| t` */
export const ORDER_STATUS = [
  { value: '', label: 'withdraw.all' },
  { value: 'pending', label: 'withdraw.pending' },
  { value: 'approved', label: 'withdraw.approved' },
  { value: 'rejected', label: 'withdraw.rejected' },
  { value: 'completed', label: 'withdraw.completed' },
];

/**
 * 提现订单的行内动作（值域 = WithdrawReviewTrait::review 的 `action`：approve/reject/confirm，
 * 加 executePayout/syncPayout 两个 POST）。订单一没有 PUT/DELETE 端点（改不了也删不掉）、
 * 二没有「新建」⇒ crud().ends 一个都不给（缺省即没有该能力，不出编辑/删除/新建），动作全走 extra()。
 * 五个动作都动钱或动外部：通过=放行资金、驳回=退款+流水、确认=双审第二票、打款=真调 PayPal
 * ⇒ 一律二次确认（文案带订单号与金额）＋确认后读服务端 message（同一端点会回「打款成功」
 * 或「打款已提交」，自己编一句就把这两种结果糊成一种了），绝不乐观改行。
 * label 是词条键（见 dict/finance.ts），表格那边过 `t()`。
 */
export const ORDER_ACTS: Act[] = [
  { key: 'approve', label: 'withdraw.approve' },
  { key: 'reject', label: 'withdraw.reject', danger: true },
  { key: 'confirm', label: 'withdraw.second_confirm' },
  { key: 'payout', label: 'withdraw.execute' },
  { key: 'sync', label: 'withdraw.sync' },
];

/**
 * 字段真值 = WithdrawController::updateLimit 的 validator（全是 nullable|numeric|min:0）。
 * **一律 text**：这些列是 DECIMAL(18,4) / DECIMAL(5,2)，过一趟 number 控件就是过一趟 JS Number，
 * 金额与费率都不许掉精度；格式交服务端的 numeric 规则。
 * 留空 = 不提交（空串过不了 numeric；后端 updateLimit 是局部更新，缺省字段不动）。
 * user_level 不在 fill() 白名单里 ⇒ 表单里没有它（列表里能看到是哪一档）。
 * fee_pct 有 lt:100 的上界：100 会把实收吃成 0，后端直接拒（那是个「设得进去、打款必失败」的档位）。
 */
export const LIMIT_FIELDS: Field[] = [
  { name: 'single_min', label: 'withdraw.single_min', type: 'text', keepIfEmpty: true, placeholder: 'withdraw.single_min_hint' },
  { name: 'single_max', label: 'withdraw.single_max', type: 'text', keepIfEmpty: true, placeholder: 'withdraw.single_max_hint' },
  { name: 'daily_limit', label: 'withdraw.daily_limit', type: 'text', keepIfEmpty: true, placeholder: 'withdraw.keep_hint' },
  { name: 'monthly_limit', label: 'withdraw.monthly_limit', type: 'text', keepIfEmpty: true, placeholder: 'withdraw.keep_hint' },
  { name: 'fee_pct', label: 'withdraw.fee_rate', type: 'text', keepIfEmpty: true, placeholder: 'withdraw.fee_pct_hint' },
  { name: 'fee_max', label: 'withdraw.fee_max', type: 'text', keepIfEmpty: true, placeholder: 'withdraw.fee_max_hint' },
  { name: 'auto_approve_threshold', label: 'withdraw.auto_threshold', type: 'text', keepIfEmpty: true, placeholder: 'withdraw.keep_hint' },
];

/**
 * 字段真值 = WithdrawController::setLimits 的 validator。
 * 这个端点是**全档位重置**：一次把 default/verified/vip 三档一起改（min_amount 落到各档的
 * single_min 列），并同步 platform_config 的回落值，同一事务提交；某一档的单笔最高低于新下限时整笔拒绝。
 * 所以它只在页头出（「全局限额重置」），不挂在某一行上。仍是全 text。
 */
export const SET_FIELDS: Field[] = [
  { name: 'daily_limit', label: 'withdraw.set_daily', type: 'text', keepIfEmpty: true, placeholder: 'withdraw.set_keep' },
  { name: 'min_amount', label: 'withdraw.set_min', type: 'text', keepIfEmpty: true, placeholder: 'withdraw.set_min_hint' },
  { name: 'auto_approve_threshold', label: 'withdraw.set_auto', type: 'text', keepIfEmpty: true, placeholder: 'withdraw.set_keep' },
];

/** 提供商白名单 = PaymentController::create/update 的 `in:` 规则（少一个都 422，多一个也不行） */
const PROVIDERS = [
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
];

/** 可见国家多选的基表（本树没有国家码表可载入；表外的码不会丢，见 finance.ts 的 countryOpts()） */
export const COUNTRY_CODES = ['CN', 'US', 'JP', 'KR', 'BR', 'IN', 'DE', 'GB'];

/**
 * 字段真值 = PaymentController::create/update 的 validator（+ game_payment_method 列宽）。
 * 金额区间 min_amount/max_amount 是 DECIMAL(18,4) ⇒ text，留空 = 不提交（空串过不了 numeric，
 * 新建时后端自己填 '0'）。config 是加密 JSON：列表**回显的是解密后的原文**，textarea 直接编辑；
 * 留空 = 不提交（后端把空串当「清空成 NULL」，那是另一个语义，不能靠留空表达）。
 * status 是 create 的必填项（required|in:0,1，缺了直接 422）⇒ 表单里必须有它（switch 新建恒发）。
 * countries 是 JSON 数组（空数组或 ["*"] = 全球）；type/provider 都在 update 的白名单里，不是 createOnly。
 */
export const METHOD_FIELDS: Field[] = [
  { name: 'name', label: 'payment.name', type: 'text', required: true, placeholder: 'payment.name_hint' },
  {
    name: 'type',
    label: 'payment.type',
    type: 'select',
    required: true,
    options: [
      { value: 'fiat', label: 'payment.fiat' },
      { value: 'crypto', label: 'payment.crypto' },
    ],
  },
  {
    name: 'provider',
    label: 'payment.provider',
    type: 'select',
    required: true,
    options: PROVIDERS.map((v) => ({ value: v, label: v })),
  },
  { name: 'status', label: 'payment.status_required', type: 'switch', required: true },
  { name: 'sort', label: 'payment.sort', type: 'number', placeholder: 'payment.sort_hint' },
  { name: 'currency', label: 'payment.currency', type: 'text', placeholder: 'payment.currency_hint' },
  { name: 'min_amount', label: 'payment.min_amount', type: 'text', keepIfEmpty: true, placeholder: 'payment.min_amount_hint' },
  { name: 'max_amount', label: 'payment.max_amount', type: 'text', keepIfEmpty: true, placeholder: 'payment.max_amount_hint' },
  { name: 'countries', label: 'payment.countries_label', type: 'multi', full: true },
  { name: 'config', label: 'payment.config', type: 'textarea', full: true, keepIfEmpty: true, placeholder: 'payment.config_hint' },
];
