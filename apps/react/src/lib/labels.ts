/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 后端枚举键 → 中文标签。
 *
 * ⚠ 每张表的键集**必须照服务端写入侧核**，不能照抄别的树/别的页面的表。
 * 照抄的表会同时错两头：把没人写的死键抄进来（用户永远看不到，只是噪音），
 * 又漏掉真在写、只是别处没列的键（用户直接看到英文原键）。
 *
 * 两张表都走 `?? 原值` 回落：后端新增类型时宁可露出原键，也不要空白或编一个不存在的名字。
 */

/**
 * game_transaction.type。逐键核过的写入点：
 *   deposit          PaymentController.php:158（充值到账）
 *   withdraw         WithdrawController.php:232
 *   refund           WithdrawReviewTrait.php:180 / :279（提现驳回退款，直接建 Transaction 行）
 *   exchange_out     ExchangeController.php:231 —— **买入**游戏币时平台币扣款（钱出去）
 *   exchange_in      ExchangeController.php:260 —— **卖出**游戏币时平台币到账（钱进来）
 *   game_spend       SelfProvider.php:54（bet）· game_earn SelfProvider.php:166（settle）
 *   activity_reward  ActivityService.php:334 / :345 · referral_bonus ReferralController.php:261
 *   lock / unlock    WalletService::lock / ::unlock · reconcile 仅回填迁移 2026_09_28 写
 *
 * 名称按**平台币**方向取：买币是平台币出去（exchange_out），卖币是平台币进来（exchange_in）
 * ⇒「转出」恒配负额、「转入」恒配正额，与流水里的符号同一方向（标反比露原键更糟）。
 * 措辞与 angular 树逐词一致（两棵树同词），不用「买入/卖出游戏币」那版。
 *
 * 别加这几条：transfer_in / transfer_out / commission / adjust 全仓零写入；
 * bet / win 是 game_play_record.action，不是流水类型（有棵树照抄进表里了）。
 */
const TX_LABEL: Record<string, string> = {
  deposit: '充值',
  withdraw: '提现',
  refund: '退款',
  exchange_out: '兑换转出',
  exchange_in: '兑换转入',
  game_spend: '开局扣费',
  game_earn: '游戏派彩',
  activity_reward: '活动奖励',
  referral_bonus: '邀请奖励',
  lock: '冻结',
  unlock: '解冻',
  reconcile: '对账调整',
};

/**
 * game_tournament.type —— ⚠ 与上面不同：**这张表在本仓没有写入侧**。
 * `game_tournament` 全仓只有读（TournamentController + Tournament 模型），admin 树零引用、
 * 无 CRUD、install.sql 无种子，列是自由文本 `VARCHAR(32) COMMENT '赛制类型'`。
 * 所以下面是按平台既有的周期性词汇（与 LeaderboardService 的 daily/weekly/monthly 同词）**推定**的，
 * 不是从写入侧核出来的枚举 —— 别把它当契约。运营手填别的值一律由回落原样透出。
 */
const TOURNAMENT_TYPE_LABEL: Record<string, string> = {
  daily: '每日赛',
  weekly: '每周赛',
  monthly: '每月赛',
};

/** 流水类型 → 中文；未知类型回落原键（不返回空串）。 */
export const txLabel = (type: string): string => TX_LABEL[type] ?? type;

/** 赛制类型 → 中文；未知类型回落原键（不返回空串）。 */
export const tournamentTypeLabel = (type: string): string => TOURNAMENT_TYPE_LABEL[type] ?? type;
