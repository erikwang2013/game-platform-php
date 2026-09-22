/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 统一响应格式
 */
export interface ApiResponse<T> {
  code: number;
  message: string;
  data: T;
}

/**
 * 登录请求/响应
 */
export interface LoginRequest {
  username: string;
  password: string;
}

export interface LoginUser {
  id: string;
  username: string;
  nickname: string;
  avatar: string;
}

export interface LoginResponse {
  access_token: string;
  refresh_token: string;
  user?: LoginUser;
}

/**
 * 个人资料
 */
export interface UserProfile {
  id: string;
  username: string;
  nickname: string;
  avatar: string;
  email: string;
  phone: string;
  country: string;
  language: string;
  last_login_at: string;
  created_at: string;
}

/** 个人资料更新 — PUT /api/v1/user/profile，服务端仅接受 nickname / avatar / language */
export interface UserProfileUpdateRequest {
  nickname?: string;
  avatar?: string;
  language?: string;
}

/**
 * 游戏币种
 */
export interface GameCurrency {
  id: string;
  name: string;
  symbol: string;
  exchange_rate: string;
  min_exchange?: string;
  max_exchange?: string;
}

/**
 * 游戏分类
 */
export interface GameCategory {
  name: string;
  slug: string;
}

/**
 * 游戏列表项 / 详情
 */
export interface GameItem {
  id: string;
  name: string;
  slug: string;
  type: string;
  description: string;
  cover_image: string;
  currencies?: GameCurrency[];
  categories?: GameCategory[];
  api_endpoint?: string;
}

/**
 * 分页列表信封 — 服务端历史原因存在 items / list 两种字段名
 */
export interface PagedListResult<T> {
  items?: T[];
  list?: T[];
  total: number;
  page: number;
  per_page: number;
  last_page?: number;
}

export interface GameListResult extends PagedListResult<GameItem> {}

export interface GameLaunchRequest {
  game_id: string;
}

export interface GameLaunchResult {
  id: string;
  name: string;
  slug: string;
  type: string;
  api_endpoint: string;
  session_id: string;
}

/**
 * 钱包
 */
export interface WalletInfo {
  id: string;
  balance: string;
  frozen_balance: string;
  total_earned: string;
  total_spent: string;
}

export interface TransactionItem {
  id: string;
  type: string;
  amount: string;
  balance_after: string;
  ref_type?: string;
  ref_id?: string;
  remark: string;
  created_at: string;
}

export interface TransactionListResult extends PagedListResult<TransactionItem> {}

/**
 * 支付方式 — GET /api/v1/payment/methods
 * min_amount / max_amount 为字符串（DECIMAL 下发形如 "5000.0000"）；
 * max_amount 数值为 0 表示不限上限
 */
export interface PaymentMethodItem {
  id: string;
  name: string;
  type: string;
  provider: string;
  min_amount: string;
  max_amount: string;
}

export interface PaymentMethodListResult {
  list?: PaymentMethodItem[];
}

/**
 * 充值 — POST /api/v1/deposit/create
 * 金额一律以字符串传递，不在客户端做任何数值转换
 */
export interface DepositCreateRequest {
  amount: string;
  currency: string;
  payment_method_id: string;
}

export interface DepositCreateResult {
  order_id: string;
  order_no: string;
  amount: string;
  platform_amount: string;
  checkout_url: string;
  expires_at: string;
}

/**
 * 提现 — POST /api/v1/withdraw/apply
 * account_info 为自由文本（服务端加密存储），三种方式共用同一字段
 */
export interface WithdrawApplyRequest {
  platform_amount: string;
  method: string;
  account_info: string;
}

export interface WithdrawApplyResult {
  order_id: string;
  order_no: string;
  platform_amount: string;
  fee: string;
  actual_amount: string;
  status: string;
  balance_after: string;
  created_at: string;
}

/**
 * 兑换 — POST /api/v1/exchange/quote | buy | sell
 * direction 'out'（卖出）时 platform_amount 承载的是【游戏币】数量，
 * 服务端复用了同一字段名，不要改成 game_amount
 */
export interface ExchangeRequest {
  game_id: string;
  currency_id: string;
  direction: string;
  platform_amount: string;
}

/** 询价响应：in 返回 game_amount/actual_game_amount，out 返回 platform_equivalent/actual_platform_amount */
export interface ExchangeQuoteResult {
  platform_amount: string;
  game_amount?: string;
  platform_equivalent?: string;
  spread_fee: string;
  actual_game_amount?: string;
  actual_platform_amount?: string;
  rate: string;
  spread_pct: string;
}

/** 成交结果：金额字段随方向换位。in=支出平台币/到账游戏币；out=卖出游戏币/到账平台币（到账侧为扣点差净额）。 */
export interface ExchangeDoneResult {
  exchange_id: string;
  direction: string;
  platform_amount: string;
  game_amount: string;
  spread_fee: string;
  rate: string;
  balance_after: string;
}
