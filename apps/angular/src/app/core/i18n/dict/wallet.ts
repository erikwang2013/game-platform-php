/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 钱包页（`pages/wallet.ts`）+ 两张单据状态表。
 *
 * `tx.*` / `status.*` 两组是**组件内数据表的值**，不是模板字面量：`TX_LABEL` 把后端原始
 * `type` 映射到词条键，模板再过 `t()` 渲染。抽它们的理由见 `pages/wallet.ts` 里那张表的注释
 * （它是全仓唯一会把后端原始 type 摆到用户眼前的列表）。
 *
 * ⚠ `已取消` / `失败` / `已完成` 三条**同时属于充值单与提现单**，所以放在 `common.*` 里共用，
 * 不各写一份 —— 两张表里它们本来就是同一个词。
 */
export const WALLET: Record<string, [string, string]> = {
  // —— 余额卡 ——
  'wallet.available': ['Available balance', '可用余额'],
  'wallet.total_earned': ['Total earned', '累计收入'],
  'wallet.total_spent': ['Total spent', '累计支出'],

  // —— 四个动作 / 页签 ——
  'wallet.deposit': ['Deposit', '充值'],
  'wallet.withdraw': ['Withdraw', '提现'],
  'wallet.exchange': ['Exchange', '兑换'],
  'wallet.records': ['Game history', '游戏流水'],
  'wallet.tab_tx': ['Transactions', '交易流水'],
  'wallet.tab_dep': ['Deposit orders', '充值订单'],
  'wallet.tab_wd': ['Withdraw orders', '提现订单'],
  'wallet.tab_ex': ['Exchange records', '兑换记录'],

  // —— 列表 ——
  'wallet.load_more': ['Load more', '加载更多'],
  'wallet.ex_buy': ['Buy game coins', '买入游戏币'],
  'wallet.ex_sell': ['Sell game coins', '卖出游戏币'],
  'wallet.empty_tx': ['No wallet activity yet', '还没有资金变动'],
  'wallet.empty_ex': ['No exchange records yet', '还没有兑换记录'],
  'wallet.empty_orders': ['No orders yet', '还没有相关订单'],

  // —— 流水类型（TX_LABEL）——
  // `充值`/`提现`/`冻结`/`解冻` 四条复用 wallet.* / common.*，不在这里重复造键。
  'tx.refund': ['Refund', '退款'],
  'tx.exchange_in': ['Exchange in', '兑换转入'],
  'tx.exchange_out': ['Exchange out', '兑换转出'],
  'tx.game_spend': ['Game entry fee', '开局扣费'],
  'tx.game_earn': ['Game payout', '游戏派彩'],
  'tx.activity_reward': ['Activity reward', '活动奖励'],
  'tx.referral_bonus': ['Referral bonus', '邀请奖励'],
  'tx.reconcile': ['Reconciliation adjustment', '对账调整'],

  // —— 充值单状态（DEP_LABEL）——
  'status.dep.pending': ['Awaiting payment', '待支付'],
  'status.dep.paid': ['Paid', '已支付'],
  'status.dep.confirmed': ['Credited', '已到账'],
  'status.dep.expired': ['Expired', '已过期'],

  // —— 提现单状态（WD_LABEL / ST_LABEL）——
  'status.wd.pending': ['Pending review', '待审核'],
  'status.wd.reviewing': ['Under review', '审核中'],
  'status.wd.manual_review': ['Manual review', '人工审核'],
  'status.wd.approved': ['Approved', '已通过'],
  'status.wd.processing': ['Processing', '处理中'],
  'status.wd.paid': ['Paid out', '已打款'],
  'status.wd.rejected': ['Rejected', '已驳回'],
};
