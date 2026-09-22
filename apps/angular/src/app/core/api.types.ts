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
