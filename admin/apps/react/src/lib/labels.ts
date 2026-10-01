/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 流水类型（`game_transaction.type`）→ 当前语言的显示文案。
 *
 * ⚠ **这张表是从别处抄来的，键一个都没动**：C 端 react 树 `apps/react/src/lib/labels.ts`
 * 的 `txLabel` 与 C 端 angular 树 `apps/angular/src/app/pages/wallet.ts` 的 `TX_LABEL`
 * 是同一族（两棵树 12 键逐键相同）。**三份要改一起改** —— 后端加一个 `type` 就要三处同步，
 * 少改一处的那棵树会把原键露给用户。
 *
 * 抄的是**键集与语义**，不是代码：那两棵树的表是中文常量表，而本树有一条棘轮
 * （`i18n/coverage.test.ts`）禁止 `src/` 下出现硬编码中文的字面量，界面文案一律走 `t()`。
 * 所以这里只做**查表与回落**，表本体落成 13 张语言表的 `tx.*` 12 个键
 * （键集必须 13 张全有，见 `i18n.test.ts`；`labels.test.ts` 另按语言逐键核一遍）。
 *
 * 逐键核过的写入点（2026-10-01 全仓 grep 复核，除迁移外只有 `WalletService::record()`
 * → `Transaction::create()` 一个口子能建流水行）：
 *   deposit          PaymentController.php:158（充值到账）
 *   withdraw         WithdrawController.php:232
 *   refund           **admin 树** WithdrawReviewTrait.php（提现驳回退款，直接建 Transaction 行）
 *   exchange_out     ExchangeController.php:231 —— **买入**游戏币时平台币扣款（钱出去）
 *   exchange_in      ExchangeController.php:260 —— **卖出**游戏币时平台币到账（钱进来）
 *   game_spend       SelfProvider.php:54（bet）· game_earn SelfProvider.php:166（settle）
 *   activity_reward  ActivityService.php:334 / :345 · referral_bonus ReferralController.php:261
 *   lock / unlock    WalletService::lock / ::unlock（风控冻结/解冻）
 *   reconcile        仅回填迁移 2026_09_28_wallet_freeze_ledger.sql 写
 *
 * 名称按**平台币**方向取：买币是平台币出去（exchange_out）、卖币是平台币进来（exchange_in）
 * ⇒「转出」恒配负额、「转入」恒配正额，与流水里的符号同向（标反比露原键更糟）。
 * 措辞与 C 端两棵树逐词一致，不用「买入/卖出游戏币」那版。
 *
 * 别加这几条：`transfer_in` / `transfer_out` / `commission` / `adjust` 全仓零写入（连常量都没有）；
 * `bet` / `win` 是 `game_play_record.action`，不是流水类型（有棵树照抄进表里过）。
 */
import { en } from '../i18n/en.ts';
import { t, type MessageKey } from '../i18n/index.ts';

/**
 * 流水类型 → 当前语言的文案。未知类型**回落原键**（与另外两棵树同款）：
 * 后端新增类型时宁可让界面露出英文原键，也不要空白，更不要编一个不存在的名字。
 */
export function txLabel(type: string): string {
  const key = `tx.${type}`;
  return key in en ? t(key as MessageKey) : type;
}
