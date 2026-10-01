/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { DICT } from '../core/i18n/dictionary';
import { t } from '../core/i18n/i18n';
import { DASH, isNum } from '../core/util';

/**
 * 平台用户钱包（只读）的**声明式常量**：钱包卡的四个金额 + 流水表的列 + 类型文案。
 * 组件那边（users.ts 的详情抽屉）只管取数与布局，这张表管「后端回什么、界面上叫什么」。
 *
 * 两块数据都不新增端点：
 *  - 钱包卡吃 `GET /admin/v1/platform/user/{hashid}` 回包里的 `data.wallet`
 *    （PlatformUserController::detail 的 `User::with('wallet')`）；
 *  - 流水表走 `GET /admin/v1/platform/user/{hashid}/transactions`。
 *
 * **这一屏不许长出任何改余额的控件**：本轮只要「只读展示 + 看流水」。冻结/解冻在风控页
 * （RiskUserController），不在这棵树这一屏 —— 摆上来就是第二条改钱的路。
 *
 * 金额一律是**服务端算好的 bcmath 字符串**，前端只做呈现：不求和、不取差、不 parseFloat
 * （所以卡上没有「净收入 = 累计收入 − 累计支出」这种字段：那是前端算钱）。
 */

/**
 * 钱包卡的四个金额。标签用 `col.*`（列标题那一族）：与表格表头、详情键值走**同一条兜底链**
 * （`colKey`），以后词条表补一条就自动生效，不会有第二处清单要同步。
 */
export const WALLET_STATS: { key: string; label: string }[] = [
  { key: 'balance', label: 'col.balance' },
  { key: 'frozen_balance', label: 'col.frozen_balance' },
  { key: 'total_earned', label: 'col.total_earned' },
  { key: 'total_spent', label: 'col.total_spent' },
];

/**
 * 流水表的列。**只列后端确实回的字段**（contract：items 的 `{id,type,amount,balance_after,
 * ref_type,ref_id,remark,created_at}`），顺序即列序；`ref_type` / `ref_id` 是关联单据，
 * **不进列**（单据类型没有值域可展示，摆出来是一列裸 hashid；ref_id 还可能是 null）。
 * `id` 只当 track 键，也不是一列。
 *
 * 这张表**没有走 `ui-table`**：金额列要按正负分色，而 ui-table 的单元格类名只有
 * `.num`（`isNum` 判出来的右对齐）——它没有「某一列额外挂一个类」的口子。为一张只读流水表
 * 给共用组件加格式化回调不划算（那会同时改到另外十几个页面的渲染路径）。
 */
export const TX_COLS: { key: string; label: string }[] = [
  { key: 'type', label: 'col.type' },
  { key: 'amount', label: 'col.amount' },
  { key: 'balance_after', label: 'col.balance_after' },
  { key: 'remark', label: 'col.remark' },
  { key: 'created_at', label: 'col.created_at' },
];

/**
 * 流水类型（`game_transaction.type`）→ 当前语言的文案。
 *
 * ⚠ **键集是从别处抄来的，一个都没动**：C 端 react 树 `apps/react/src/lib/labels.ts` 的
 * `txLabel` 与 C 端 angular 树 `apps/angular/src/app/pages/wallet.ts` 的 `TX_LABEL` 是同一族
 * （12 键逐键相同），管理端 react 树 `admin/apps/react/src/lib/labels.ts` 也是这一族。
 * **四份要改一起改** —— 后端加一个 `type` 就要四处同步，少改一处的那棵树会把原键露给用户。
 *
 * 抄的是**键集与语义**，不是代码：那几棵树的表是中文常量表，而本树有 13 种语言 ⇒ 表本体落成
 * 词条表的 `tx.*` 12 个键（键集 13 张表全有，`i18n.spec.ts` 有常驻断言）。zh 一侧逐字等于
 * 那几棵树的中文原文。
 *
 * 逐键核过的写入点（除回填迁移外，只有 `WalletService::record()` → `Transaction::create()`
 * 一个口子能建流水行）：
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
 *
 * 别加这几条：`transfer_in` / `transfer_out` / `commission` / `adjust` 全仓零写入（连常量都没有）；
 * `bet` / `win` 是 `game_play_record.action`，不是流水类型（有棵树照抄进表里过）。
 *
 * 未知类型**回落原键**（与另外三棵树同款）：宁可让界面露出英文原键，也不要空白，
 * 更不要编一个不存在的名字。查的是 `DICT`（= 英文表）而不是 `t()` 的返回值 ——
 * `t()` 认不出的键会原样返回，那就成了把 `tx.mystery` 连前缀一起摆给用户。
 */
export function txLabel(type: unknown): string {
  const key = `tx.${String(type ?? '')}`;
  return key in DICT ? t(key) : String(type ?? '');
}

/**
 * 金额的正负方向 —— **只看字符串首字符，不 parseFloat**（本仓铁律：金额禁止 float）。
 * bcmath 的正数不带 `+`（`bcsub('-1','-2')` 回 `1`），所以「不是负号就是正」。
 * 零既不收入也不支出，单独归中性（`0.00000000` 画成绿色会让人以为进账了）——
 * 判零**先剥符号**，`-0.00000000` 也是零（bcmath 理论上不会吐它，但剥一下就隔断了）。
 * 非数字（备注串、空串）一律中性。
 */
export function amountTone(v: unknown): 'pos' | 'neg' | '' {
  const s = String(v ?? '');
  if (!s) return '';
  const bare = s.startsWith('-') ? s.slice(1) : s;
  if (/^0+(\.0+)?$/.test(bare)) return '';
  if (s.startsWith('-')) return 'neg';
  return isNum(s) ? 'pos' : '';
}

/**
 * 带方向的金额显示：正数补一个 `+`（服务端不补），负数/零/非数字原样。**纯字符串拼接**，
 * 不碰数值；空值走占位符（与表格单元格同一口径）。
 */
export function amountText(v: unknown): string {
  const s = String(v ?? '');
  if (!s) return DASH;
  return amountTone(s) === 'pos' ? `+${s}` : s;
}
