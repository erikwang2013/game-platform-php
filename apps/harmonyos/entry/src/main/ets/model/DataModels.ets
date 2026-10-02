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
 * 点击验证码 — 登录/注册/提现申请/兑换卖出/领券 由服务端强制校验
 *
 * POST /api/v1/captcha/generate 的 data 段：
 *   { key, image, extra: { texts: [{ order, text }] } }
 * texts 按 order 升序即服务端要求的点击顺序；**不下发坐标**（坐标就是答案）。
 * 画布恒为 300x200（easy/medium/hard 三档实测一致），点击坐标按此换算。
 */
export interface CaptchaTarget {
  order: number;
  text: string;
}

export interface CaptchaExtra {
  texts: CaptchaTarget[];
}

/** 取图响应的 data 段（image 为裸 base64 PNG，无 data: 前缀） */
export interface CaptchaGenerateResult {
  key: string;
  image: string;
  extra?: CaptchaExtra;
}

/** 归一化后的挑战：texts 已按 order 升序，供弹框直接使用 */
export interface CaptchaChallenge {
  key: string;
  image: string;
  texts: CaptchaTarget[];
}

/** 一次点击的画布坐标（0-300 / 0-200），就是提交给服务端的 clicks 元素 */
export interface CaptchaClick {
  x: number;
  y: number;
}

/**
 * 登录请求/响应
 * 登录与注册同为一个接口体：服务端两个端点都要 captcha_key + clicks
 */
export interface LoginRequest {
  username: string;
  password: string;
  captcha_key: string;
  clicks: CaptchaClick[];
}

/**
 * 2FA 第二步 — POST /api/v1/2fa/verify（**公开端点**，不需要登录态）。
 * 身份取自票据里的 `sub`（登录时已过密码校验），客户端无法自选用户。
 */
export interface TwoFactorVerifyRequest {
  pending_2fa_token: string;
  /** 6 位 TOTP（验证器 App）或 10 位备份码 —— 服务端 validator 是 `between:6,10` */
  code: string;
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
  /** 账号开了 2FA 时服务端只回这个 + pending_2fa_token，不下发令牌对（AuthController::login） */
  require_2fa?: boolean;
  pending_2fa_token?: string;
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
 * 服务端强制验证码：captcha_key + clicks 与原字段同级
 */
export interface WithdrawApplyRequest {
  platform_amount: string;
  method: string;
  account_info: string;
  captcha_key: string;
  clicks: CaptchaClick[];
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
 *
 * 三处共用一个请求体：只有 sell 需要验证码（服务端刻意没给 buy/quote 加），
 * 故 captcha 两字段可选 —— 不传时 JSON.stringify 会整键省略，quote/buy 报文与从前逐字节一致
 */
export interface ExchangeRequest {
  game_id: string;
  currency_id: string;
  direction: string;
  platform_amount: string;
  captcha_key?: string;
  clicks?: CaptchaClick[];
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
