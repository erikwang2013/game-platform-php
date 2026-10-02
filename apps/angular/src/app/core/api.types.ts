/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 后端契约类型 — 与 service/app/api/v1/controller 的响应字段对齐。
 * 所有 ID 均为 Hashid 字符串；金额字段为 decimal 字符串或 number，仅用于展示。
 */

export interface Envelope<T> {
  code: number;
  message: string;
  data: T;
}

export interface Paged<T> {
  items: T[];
  total: number;
  page: number;
  per_page: number;
  last_page: number;
}

/** 金额字段后端可能返回 string(decimal) 或 number，仅用于展示。 */
export type Num = number | string;

export interface UserBrief {
  id: string;
  username: string;
  nickname: string;
  avatar: string;
}

export interface UserProfile extends UserBrief {
  email: string;
  phone: string;
  country: string;
  language: string;
  last_login_at: string;
  created_at: string;
}

export interface AuthResult {
  access_token: string;
  refresh_token: string;
  user?: UserBrief;
  require_2fa?: boolean;
  pending_2fa_token?: string;
}

/** 点击式验证码上的一个落点（图片原始像素坐标，非显示坐标） */
export interface Click {
  x: number;
  y: number;
}

/** 点击式验证码：key 一次性；texts 按服务端要求的点击顺序（服务端不下发坐标，坐标就是答案） */
export interface CaptchaChallenge {
  key: string;
  image: string;
  texts: string[];
}

/** 验证码答案：与业务字段同级并入原请求体（缺字段 422） */
export interface CaptchaProof {
  captcha_key: string;
  clicks: Click[];
}

export interface PlatformStats {
  total_games: number;
  total_users: number;
  today_game_plays: number;
  active_users_7d: number;
}

export interface Currency {
  id: string;
  name: string;
  symbol: string;
  exchange_rate: Num;
  min_exchange?: Num;
  max_exchange?: Num;
  spread_pct?: Num;
}

export interface GameCategory {
  name: string;
  slug: string;
}

export interface Game {
  id: string;
  name: string;
  slug: string;
  type: string;
  description: string;
  cover_image: string;
  sdk_version: string;
  platform: string;
  region: string;
  currencies: Currency[];
  categories: GameCategory[];
}

export interface GameDetail extends Omit<Game, 'categories'> {
  api_endpoint: string;
}

export interface Suggestion {
  id: string;
  name: string;
  slug: string;
}

/**
 * `/game/balance`：按游戏聚合的**游戏币**余额（平台币余额在 `/wallet/info`，两本账）。
 * 返回体是 `{ games: GameWallet[] }`；`balance`/`frozen_balance` 是 decimal 串，只做展示格式化。
 */
export interface GameWallet {
  game_id: string;
  name: string;
  slug: string;
  type: string;
  currencies: Array<{
    currency_id: string;
    name: string;
    symbol: string;
    balance: Num;
    frozen_balance: Num;
  }>;
}

export interface WalletInfo {
  id: string;
  balance: Num;
  frozen_balance: Num;
  total_earned: Num;
  total_spent: Num;
}

export interface Transaction {
  id: string;
  type: string;
  amount: Num;
  balance_after: Num;
  ref_type: string;
  ref_id: string;
  remark: string;
  created_at: string;
}

export interface DepositOrder {
  id: string;
  order_no: string;
  amount: Num;
  currency: string;
  platform_amount: Num;
  status: string;
  paid_at: string;
  created_at: string;
}

export interface WithdrawOrder {
  id: string;
  order_no: string;
  platform_amount: Num;
  method: string;
  status: string;
  review_note: string;
  created_at: string;
}

/** 支付方式（充值用）。min/max 为 DECIMAL 下发的字符串（形如 "5000.0000"）；max_amount 数值为 0 表示不限。 */
export interface PaymentMethodInfo {
  id: string;
  name: string;
  type: string;
  provider: string;
  min_amount: Num;
  max_amount: Num;
}

export interface DepositCreated {
  order_id: string;
  order_no: string;
  amount: Num;
  platform_amount: Num;
  /** 支付页地址，付款必须在此完成 */
  checkout_url: string;
  expires_at: string;
}

export interface WithdrawApplied {
  order_id: string;
  order_no: string;
  platform_amount: Num;
  fee: Num;
  actual_amount: Num;
  status: string;
  balance_after: Num;
  created_at: string;
}

/** in = 平台币 → 游戏币（buy 买入）；out = 游戏币 → 平台币（sell 卖出） */
export type ExchangeDirection = 'in' | 'out';

export interface ExchangePayload {
  game_id: string;
  currency_id: string;
  direction: ExchangeDirection;
  /**
   * ⚠️ direction='out'（卖出）时本字段承载的是【游戏币】数量 —— 服务端复用了同一个字段名，
   * 请求体字段名不可改成 game_amount，改了会 422。
   */
  platform_amount: string;
}

/** 询价结果：'in' 带 game_amount/actual_game_amount，'out' 带 platform_equivalent/actual_platform_amount */
export interface ExchangeQuote {
  platform_amount: Num;
  rate: Num;
  spread_fee: Num;
  spread_pct: Num;
  game_amount?: Num;
  actual_game_amount?: Num;
  platform_equivalent?: Num;
  actual_platform_amount?: Num;
}

/**
 * 成交结果。两个金额字段随方向换位：in=支出平台币/到账游戏币，
 * out=卖出游戏币/到账平台币（到账侧为扣点差净额）。
 */
export interface ExchangeDone {
  exchange_id: string;
  direction: ExchangeDirection;
  platform_amount: Num;
  game_amount: Num;
  spread_fee: Num;
  rate: Num;
  balance_after: Num;
}

/**
 * `/exchange/records` 的一行（`ExchangeController::records:151-162`）。
 * 与钱包其它列表同形状（items/total/page/last_page）。
 *
 * ⚠ `platform_amount` 的口径**随 direction 换位**，与 ExchangeDone 同一套约定：
 * in（买入）下它是**支出**的平台币；out（卖出）下它是扣过点差的**到账净额**。
 * 钱包页一律按平台币那一侧记收支（与 Exchange 页的头寸口径一致），故符号看 direction 而不是看正负。
 */
export interface ExchangeRecordRow {
  id: string;
  game_id: string;
  currency_id: string;
  direction: ExchangeDirection;
  platform_amount: Num;
  game_amount: Num;
  rate: Num;
  spread_fee: Num;
  created_at: string;
}

export interface Notify {
  id: string;
  type: string;
  title: string;
  content: string;
  is_read: boolean;
  ref_type: string;
  ref_id: string;
  created_at: string;
}

export interface LaunchResult {
  id: string;
  name: string;
  slug: string;
  type: string;
  api_endpoint: string;
  session_id: string;
}

/* ==================== 公告 / 排行榜（公开接口） ==================== */

/** /announcement/list：后端**不分页**，硬 .limit(20)、按 id 倒序；列表不含正文 */
export interface AnnouncementBrief {
  id: string;
  title: string;
  type: string;
  created_at: string;
}

/** /announcement/detail/{hashid}：正文只在详情里，键与列表同级（无嵌套） */
export interface AnnouncementDetail extends AnnouncementBrief {
  content: string;
}

/** /leaderboard/list：后端不分页。game_id 为 0 时下发 null（全平台榜） */
export interface Leaderboard {
  id: string;
  game_id: string | null;
  name: string;
  type: string;
  metric: string;
}

/**
 * /leaderboard/{hashid} 的 ranking 元素（真源：`LeaderboardService::computeRanking`，
 * 但 `user_id` 的编码落在**控制器** —— `LeaderboardController::ranking` 出网前逐行 `encodeId`）。
 * 2026-10-02 起 `user_id` 是 **hashid 字符串**（此前是裸数据库整数）。
 */
export interface RankRow {
  rank: number;
  user_id: string;
  score: Num;
}

/* ==================== 工单 ==================== */

/** 列表项（后端 TicketController::list）——注意列表里没有 content，正文只在详情 */
export interface TicketBrief {
  id: string;
  type: string;
  subject: string;
  status: string;
  priority: number;
  reply_count: number;
  created_at: string;
}

export interface TicketReply {
  id: string;
  content: string;
  /** 后端 int-cast：1=客服，0=本人 */
  is_admin: number;
  created_at: string;
}

export interface TicketDetail extends Omit<TicketBrief, 'reply_count'> {
  content: string;
  replies: TicketReply[];
}

/** 服务端 POST /ticket/create 的 type 白名单（照抄 TicketController 的 validator） */
export const TICKET_TYPES = ['deposit', 'withdraw', 'game', 'account', 'other'] as const;
export type TicketType = (typeof TICKET_TYPES)[number];

/* ==================== 游戏流水 ==================== */

export interface PlayLog {
  id: string;
  game_id: string;
  /** 后端只在存在时编码，可能为 null */
  server_id: string | null;
  session_id: string;
  action: string;
  game_amount_before: Num;
  game_amount_change: Num;
  game_amount_after: Num;
  created_at: string;
}

export interface PlayLogDetail extends PlayLog {
  user_id: string;
  platform_amount_change: Num;
  metadata: string;
  started_at: string;
  ended_at: string;
}

/* `ReferralInfo`（/referral/my-code）2026-10-01 撤下：整条链无 bootstrap 写入路径，
   类型没有消费者就一起删。理由与恢复条件见 api.domains.ts 的同名墓碑注释。 */

/* ==================== 身份认证（KYC） ==================== */

/** 未提交时后端只回 {status:'not_submitted'}，其余字段一律缺席 */
export interface IdentityStatus {
  status: string;
  real_name?: string;
  id_type?: string;
  review_note?: string;
  submitted_at?: string | null;
  reviewed_at?: string | null;
}

/** 服务端 POST /user/identity/apply 的 id_type 白名单 */
export const ID_TYPES = ['id_card', 'passport', 'driver_license'] as const;
export type IdType = (typeof ID_TYPES)[number];

/* ==================== 运营活动 ==================== */

/** 活动类型 → 中文。真源：common\model\Activity 的 TYPE_ 常量（运营在管理端选） */
export const ACTIVITY_TYPE_LABEL: Record<string, string> = {
  signin: '每日签到',
  daily_task: '每日任务',
  invite: '邀请好友',
};

/**
 * 活动定义。
 *
 * `config` 是**按 type 各自定义的 JSON schema**（目标/周期/奖励挂在 rewards 或 tasks 上），
 * 本树**不渲染它** —— schema 随 type 变，猜字段名等于编造。展示用的目标值一律取
 * progress 里服务端快照下来的 `target`（活动改配置不影响历史参与记录）。
 */
export interface Activity {
  id: string;
  type: string;
  name: string;
  /** 0=全平台；>0 时服务端已 encodeId 成 hashid（列表只在 >0 时才转） */
  game_id?: string | number;
  status: number;
  start_at?: string | null;
  end_at?: string | null;
}

/** /activities/progress 只回**当天**（period_key=YYYY-MM-DD）的进度，最多 50 条 */
export interface ActivityProgress {
  activity_id: string;
  current: number;
  target: number;
  /** progressing=进行中 completed=已达标 rewarded=已发奖 */
  status: string;
}

/** 奖励条目：type 是**币种**（platform_coin/game_coin），不是流水类型 */
export interface ActivityReward {
  type: string;
  amount: Num;
}

/**
 * checkin 的返回。三种终态：
 *  - `already`     今天已经领过（服务端幂等，重复点不会重复发奖）
 *  - `progressing` 计了一次但没到 target
 *  - `rewarded`    达标，且**已在同一事务里发到钱包**（reward 是发放明细）
 */
export interface CheckinResult {
  status: 'already' | 'progressing' | 'rewarded';
  reward?: ActivityReward[];
}

/* ==================== 优惠券（2026-10-01 撤下） ==================== */

/**
 * `COUPON_TYPE_LABEL` / `Coupon` / `UserCoupon` 连同 `/coupon/available|claim|my` 三个端点
 * 一起撤：**券可领不可核销** —— `used_in_order` 与 `status='used'` 全仓没有写入方
 * （只出现在 `UserCoupon` 的 `$fillable` 与 `CouponController::my()` 的读过滤器两处），
 * 用户永远兑不掉。
 *
 * 撤的直接触发点是**一个具体承诺**：原实现按 `(1 - value) × 10` 把 `rate` 券渲染成
 * 「打 N 折」。展示券面额已经在暗示可用，印出「打 9 折」更硬 —— 那是一个系统**兑现不了**
 * 的折扣承诺。宁可没有这个页面，也不要一个必然落空的承诺。
 *
 * 恢复条件：先有一条把券核销掉的使用路径（下单/兑换时置 `used` 并写 `used_in_order`），
 * 届时再把类型、端点、页面一起加回来。`value` 的语义备忘（防下次读错）：
 * `rate` 型是**减掉的比例**（0.10 = 9 折），不是折数。
 */

/* ==================== 全局搜索 ==================== */

/**
 * `/search` 的一条结果。**不是 `Game`**：该端点直接吐模型行（`$item->toArray()`），
 * 不是 `/game/list` 那种筛过的形状 —— 没有 currencies/categories，
 * 模型自己的 status/sort/api_endpoint 反倒跟来（api_key/api_secret 被 `$hidden` 挡掉）。
 * 只声明要渲染的字段，别按 `Game` 解。
 */
export interface SearchGame {
  id: string;
  name: string;
  slug: string;
  description?: string | null;
  cover_image?: string | null;
}

/** `/search` 的分页回包。⚠ **没有 `last_page`**（本树其余列表都有）⇒ 末页自己算 `ceil(total/per_page)`。 */
export interface SearchResult {
  list: SearchGame[];
  total: number;
  page: number;
  per_page: number;
}
