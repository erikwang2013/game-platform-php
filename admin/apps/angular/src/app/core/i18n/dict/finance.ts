/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 资金域词条（finance.ts / finance-fields.ts）。
 *
 * 与 `dict/frame.ts` 同一套约定：`键: [英文, 中文]`、占位符 `{name}`。**中文一侧逐字等于抽取前的
 * 界面原文**（本批只做抽取、不动文案），英文沿用 flutter translations.dart 里 `withdraw.*` /
 * `payment.*` 的说法；键名能对上的直接同名，本树独有的（页头、批量确认、回执行）另起 `withdraw.` 下的名字。
 *
 * ⚠ 表头与表单标签是**两处**（同一个中文词）：表头用 `withdraw.<col>`，表单标签用 `withdraw.<col>_label`
 * 或直接复用表头键（同一个键两边都合适时才复用，见各条注释）。
 */
export const FINANCE: Record<string, [string, string]> = {
  // ---- 页头 / 标签页 ----
  'fin.title': ['Finance', '财务中心'],
  'fin.subtitle': [
    'Withdraw orders / Switch / Tiers / Payment methods',
    '提现订单 / 提现开关 / 阶梯限额 / 支付方式',
  ],
  'withdraw.orders': ['Withdraw orders', '提现订单'],
  'withdraw.switch': ['Withdraw switch', '提现开关'],
  'withdraw.limits': ['Withdrawal tiers', '阶梯限额'],
  'payment.methods': ['Payment methods', '支付方式'],

  /** 增删改底座的模块名（crud.noun）—— 与标签页文案分开：这里要能拼进「新建{name}」 */
  'withdraw.noun.order': ['Withdraw order', '提现订单'],
  'withdraw.noun.limit': ['Withdrawal tier', '阶梯限额'],
  'payment.noun': ['Payment method', '支付方式'],

  // ---- 提现开关标签页 ----
  'withdraw.global_switch': ['Global withdraw switch', '全局提现开关'],
  'withdraw.switch_on': ['Enabled', '已开启'],
  'withdraw.switch_off': ['Disabled', '已关闭'],
  'withdraw.switch_enable': ['Enable withdrawals', '开启提现'],
  'withdraw.switch_disable': ['Disable withdrawals', '关闭提现'],
  'withdraw.switch_opened': ['Withdrawals enabled', '提现已开启'],
  'withdraw.switch_closed': ['Withdrawals disabled', '提现已关闭'],

  // ---- 订单状态筛选（ORDER_STATUS）----
  'withdraw.all': ['All statuses', '全部状态'],
  'withdraw.pending': ['Pending review', '待审核'],
  'withdraw.approved': ['Approved', '已通过'],
  'withdraw.rejected': ['Rejected', '已拒绝'],
  'withdraw.completed': ['Completed', '已完成'],
  /** 只出现在**单元格**里（执行打款期间的状态），不能当筛选值用 ⇒ 不在 ORDER_STATUS 下拉里 */
  'withdraw.processing': ['Processing', '打款中'],

  // ---- 订单行内动作（ORDER_ACTS）----
  'withdraw.approve': ['Approve', '通过'],
  'withdraw.reject': ['Reject', '驳回'],
  'withdraw.second_confirm': ['Second confirm', '二次确认'],
  'withdraw.execute': ['Execute payout', '执行打款'],
  'withdraw.sync': ['Sync status', '同步状态'],

  // ---- 批量审核 ----
  'withdraw.batch_approve': ['Batch approve ({n})', '批量通过（{n}）'],
  'withdraw.batch_reject': ['Batch reject ({n})', '批量驳回（{n}）'],
  'withdraw.batch_confirm': [
    '{action} {n} pending withdrawals on this page?\n{lines}',
    '确认批量{action}本页 {n} 笔待审核提现？\n{lines}',
  ],
  /** 批量确认里「驳回」那个动词的完整说法（通过那边复用 withdraw.approve 的「通过」） */
  'withdraw.batch_reject_verb': [
    'reject (each one refunds the platform tokens to the user)',
    '驳回（每笔都会把平台币退回用户余额）',
  ],
  'withdraw.batch_line': ['　{id}: {money}', '　{id}：{money}'],
  'withdraw.batch_done': ['Batch finished', '批量处理完成'],

  // ---- 回执与动作二次确认 ----
  /** 订单标识「订单号/哈希（金额）」——确认文案里必须先认得是哪一笔 */
  'withdraw.who': ['{no} ({money})', '「{no}」（{money}）'],
  'withdraw.money': [
    '{platform} platform tokens → {fiat} {currency}',
    '{platform} 平台币 → {fiat} {currency}',
  ],
  'withdraw.note_line': ['{id}: {text}', '{id}：{text}'],
  'withdraw.act_done': ['Done', '操作完成'],
  'withdraw.approve_confirm': [
    'Approve withdrawal {who}? It becomes payable after this (the only way back is a rejection).',
    '确认通过提现订单{who}？通过后进入打款环节（要退款只能驳回）。',
  ],
  'withdraw.reject_confirm': [
    'Reject withdrawal {who}? The platform tokens go back to the user balance and a refund record is written; this cannot be undone.',
    '确认驳回提现订单{who}？会把平台币退回用户余额并记一条退款流水，不可撤销。',
  ],
  'withdraw.confirm_confirm': [
    'Second-review withdrawal {who}? Only available when dual review is on and the first reviewer is someone else.',
    '确认对提现订单{who}做二次复核？仅「双重审核」开启、且初审人不是自己时可用。',
  ],
  'withdraw.execute_confirm': [
    'Execute the payout for withdrawal {who}? This really calls the payment gateway and may produce an irreversible external transfer.',
    '确认对提现订单{who}执行打款？会真实调用支付渠道，可能产生不可撤销的外部转账。',
  ],
  'withdraw.sync_confirm': [
    'Query the gateway and sync the payout status of withdrawal {who}?',
    '确认向渠道查询并同步提现订单{who}的打款状态？',
  ],

  // ---- 阶梯限额 ----
  'withdraw.reset_all': ['Reset all tiers', '全局限额重置'],
  'withdraw.reset_title': ['Reset all tiers (writes through every tier)', '全局限额重置（写穿全部档位）'],
  'withdraw.reset_confirm': [
    'Overwrite the limits of every tier (default/verified/vip) with the values you filled in? Empty fields stay unchanged; the minimum withdrawal amount lands in each tier single_min.',
    '确认用填写值覆盖全部档位（default/verified/vip）的限额？留空项保持不变；最低提现金额写的是各档 single_min。',
  ],
  'withdraw.keep_hint': ['Empty = keep unchanged', '留空 = 不修改'],
  'withdraw.set_keep': ['Empty = leave those tiers unchanged', '留空 = 这些档位不改'],

  // ---- 表头（orders / limits / methods）----
  'withdraw.order_no': ['Order No.', '订单号'],
  'withdraw.user': ['User', '用户'],
  'withdraw.platform_token': ['Platform tokens', '平台币'],
  'withdraw.fiat_amount': ['Fiat amount', '法币'],
  'withdraw.currency': ['Currency', '币种'],
  'withdraw.method': ['Method', '方式'],
  'withdraw.status': ['Status', '状态'],
  'withdraw.payout_status': ['Payout', '打款状态'],
  'withdraw.note': ['Note', '审核备注'],
  'withdraw.submit_time': ['Submit time', '申请时间'],
  'withdraw.limit_level': ['Tier', '档位'],
  'withdraw.single_min': ['Min per order', '单笔最低'],
  'withdraw.single_max': ['Max per order', '单笔最高'],
  'withdraw.daily_limit': ['Daily limit', '日限额'],
  'withdraw.monthly_limit': ['Monthly limit', '月限额'],
  'withdraw.fee_pct': ['Fee rate %', '手续费率%'],
  'withdraw.fee_max': ['Fee cap', '手续费上限'],
  'withdraw.auto_threshold': ['Auto approve threshold', '自动审批阈值'],
  'payment.name': ['Name', '名称'],
  'payment.type': ['Type', '类型'],
  'payment.provider': ['Provider', '提供商'],
  'payment.status': ['Status', '状态'],
  'payment.sort': ['Sort', '排序'],
  'payment.currency': ['Currency', '限定币种'],
  'payment.min_amount': ['Min top-up', '最小充值额'],
  'payment.max_amount': ['Max top-up', '最大充值额'],
  'payment.countries': ['Countries', '可见国家'],

  // ---- 阶梯限额表单（LIMIT_FIELDS / SET_FIELDS）----
  'withdraw.single_min_hint': ['Decimal amount; empty = keep unchanged', '十进制金额；留空 = 不修改'],
  'withdraw.single_max_hint': [
    '0 = no limit; must not be below the tier minimum',
    '0 = 不限；不得低于单笔最低',
  ],
  'withdraw.fee_rate': ['Fee rate (%)', '手续费率（%）'],
  'withdraw.fee_pct_hint': [
    '0 ~ 99.99 (100 is rejected by the server)',
    '0 ~ 99.99（=100 后端拒）',
  ],
  'withdraw.fee_max_hint': ['0 = no cap', '0 = 不封顶'],
  'withdraw.set_daily': ['Daily limit (writes through every tier)', '每日限额（写穿全部档位）'],
  'withdraw.set_min': [
    'Minimum withdrawal amount (writes through every tier single_min)',
    '最低提现金额（写穿各档 single_min）',
  ],
  'withdraw.set_auto': [
    'Auto approve threshold (writes through every tier)',
    '自动审批阈值（写穿全部档位）',
  ],
  'withdraw.set_min_hint': [
    'Rejected outright when above some tier max',
    '高于某档单笔最高时整笔拒绝',
  ],

  // ---- 支付方式表单（METHOD_FIELDS）----
  'payment.name_hint': ['Up to 50 characters', '最长 50'],
  'payment.fiat': ['Fiat', '法币'],
  'payment.crypto': ['Crypto', '加密货币'],
  'payment.status_required': ['Status (required on create)', '状态（新建必填）'],
  'payment.sort_hint': ['Smaller comes first', '数字越小越靠前'],
  'payment.currency_hint': ['Empty = any currency, e.g. USD', '空 = 任意；如 USD'],
  'payment.min_amount_hint': ['Decimal amount, 0 = no limit', '十进制金额，0 = 不限'],
  'payment.max_amount_hint': ['0 = no limit', '0 = 不限'],
  'payment.countries_label': ['Visible countries (none selected = worldwide)', '可见国家（不选 = 全球）'],
  'payment.config': ['Payment config (JSON)', '支付配置（JSON）'],
  'payment.config_hint': [
    'Empty = keep unchanged (the list shows the decrypted text)',
    '留空 = 不修改（列表里回显的是解密后的原文）',
  ],

  // ---- 打款状态（`game_withdraw_order.payout_status`，见 finance-fields.ts 的 PAYOUT_STATUS_LABEL）----
  'withdraw.payout.processing': ['Payout in progress', '打款处理中'],
  'withdraw.payout.success': ['Paid out', '打款成功'],
  'withdraw.payout.failed': ['Payout failed', '打款失败'],

  // ---- 提现订单的电子收据（POST /export/receipt，回 PDF 附件）----
  'withdraw.receipt': ['Receipt (PDF)', '收据 (PDF)'],

  // ---- 平台用户钱包（只读，见 pages/wallet-fields.ts）----
  'wallet.title': ['Wallet', '钱包'],
  'wallet.transactions': ['Transactions', '交易流水'],
  'wallet.missing': ['No wallet for this user', '该用户还没有钱包'],

  /**
   * 流水类型（`game_transaction.type`）—— **键集与中文措辞逐字取自 C 端**
   * `apps/angular/src/app/pages/wallet.ts:82-95` 的 `TX_LABEL`（同一族的还有 C 端 react
   * 与 admin react 的 labels.ts）。四份要改一起改：后端加一个 type 就要四处同步。
   * 别加 transfer_in / transfer_out / commission / adjust：全仓零写入（见 wallet-fields.ts 的注释）。
   */
  'tx.deposit': ['Deposit', '充值'],
  'tx.withdraw': ['Withdraw', '提现'],
  'tx.refund': ['Refund', '退款'],
  'tx.exchange_in': ['Exchange in', '兑换转入'],
  'tx.exchange_out': ['Exchange out', '兑换转出'],
  'tx.game_spend': ['Game bet', '开局扣费'],
  'tx.game_earn': ['Game payout', '游戏派彩'],
  'tx.activity_reward': ['Activity reward', '活动奖励'],
  'tx.referral_bonus': ['Referral bonus', '邀请奖励'],
  'tx.lock': ['Freeze', '冻结'],
  'tx.unlock': ['Unfreeze', '解冻'],
  'tx.reconcile': ['Reconcile', '对账调整'],
};
