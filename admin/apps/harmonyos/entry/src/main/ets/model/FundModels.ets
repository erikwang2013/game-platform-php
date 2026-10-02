/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/* ---------------------------------------------------------------------------
 * 「资金」四个模块（批次 4）—— 通用行 + 各模块列表项。
 * 字段真值同样取自 admin/app/admin/v1/controller/{Payment,Coupon,CdnProvider,Withdraw}Controller.php
 * 的 validator 与 install/install.sql 的列定义，描述见 common/FundModules.ets。
 * 金额列（DECIMAL）一律按字符串收 —— 结构体里就不给它们 number 类型，免得半路被转成 float。
 * ------------------------------------------------------------------------- */

/** 支付方式（GET /admin/v1/payment/method/list）—— 整表返回，无分页 */
export interface PaymentMethodItem {
  id: string;
  name: string;
  /** fiat / crypto */
  type: string;
  /** 18 个枚举之一（validator 的 in:） */
  provider: string;
  status: number;
  sort: number;
  /** JSON 列，cast 成 array；空数组或 ["*"] = 全球 */
  countries?: string[];
  /** 限定币种，空 = 任意 */
  currency?: string;
  /** DECIMAL 列，cast 成 string */
  min_amount?: string;
  max_amount?: string;
  /** 列表回传的是**解密后的原文**（控制器注释里点了名，不能对 cast 出的 array 再 json_encode） */
  config?: string | null;
}

/** 优惠券（GET /admin/v1/coupon/list）—— 分页，过滤参数本页不挂 */
export interface CouponItem {
  id: string;
  name: string;
  /** fixed 固定金额 / rate 折扣率 */
  type: string;
  /** DECIMAL 列：fixed=平台币面值，rate=折扣率（0.10 = 9 折） */
  value: string;
  min_amount: string;
  max_discount: string;
  /** 适用游戏：>0 时控制器编成 hashid，0（全平台通用）保持裸数字 0 ⇒ 联合类型 */
  game_id: string | number;
  total_qty: number;
  used_qty: number;
  user_limit: number;
  start_at: string | null;
  end_at: string | null;
  status: number;
}

/** 优惠券统计（GET /admin/v1/coupon/{hashid}/stats）—— 只读，走行内额外动作 */
export interface CouponStats {
  total_qty: number;
  used_qty: number;
  /** total_qty=0（不限量）时控制器回 null */
  remaining: number | null;
  /** 使用率百分比数字（后端 BcMath::percent） */
  usage_rate: number;
}

/** CDN 厂商（GET /admin/v1/cdn/provider/list）—— 整表返回；**config 不回传**（凭据） */
export interface CdnProviderItem {
  id: string;
  name: string;
  /** cloudflare / cloudfront / aliyun / tencent / huawei */
  provider: string;
  status: number;
  sort: number;
}

/**
 * 提现阶梯限额（GET /admin/v1/withdraw/limits/list）—— 整表返回。
 * 8 个金额列在模型里全 cast 成 string（bcmath 口径），本页一律文本输入。
 */
export interface WithdrawLimitItem {
  id: string;
  /** default / verified / vip（DB 注释另有 svip；update 不写它） */
  user_level: string;
  single_min: string;
  single_max: string;
  daily_limit: string;
  monthly_limit: string;
  /** 手续费率，% 数（列是 DECIMAL(5,2)，即 0.50 = 0.5%） */
  fee_pct: string;
  fee_max: string;
  auto_approve_threshold: string;
}

/**
 * 提现订单（GET /admin/v1/withdraw/orders）—— 分页，控制器另支持 status 过滤。
 * 金额列（platform_amount 平台币 / fiat_amount 法币）是 DECIMAL 的 string cast：
 * **展示与二次确认文案都用原字符串**，前端不做任何加减（也不求和，求和要 float）。
 */
export interface WithdrawOrderItem {
  id: string;
  order_no?: string | null;
  user_id: number;
  platform_amount: string;
  fiat_amount: string;
  currency: string;
  method: string;
  /** pending / approved / rejected / processing / completed */
  status: string;
  /** none / processing / success / failed（PayoutService 口径） */
  payout_status?: string | null;
  /** 非空 = 这笔已提交给 PayPal，才允许「同步状态」 */
  payout_batch_id?: string | null;
  reviewer_id?: number;
  confirmed_by?: number;
  review_note?: string | null;
  created_at: string;
  /**
   * 关联用户。控制器以 `$order->toArray()` 起手，`with('user')` 已预载且关联查不到时
   * toArray 会带出 **null**（不是缺键）；查得到才覆盖成 hashid 对象 ⇒ 三态都可能，两种都要防。
   */
  user?: WithdrawUserRef | null;
}

export interface WithdrawUserRef {
  id: string;
  username: string;
}

/** 提现开关（GET /admin/v1/withdraw/switch）—— 三个键是同一个布尔值的三种读法 */
export interface WithdrawSwitchState {
  global_switch: boolean;
  enabled: boolean;
  status: number;
}

/** 工单（GET /admin/v1/ticket/list）—— 列表已 unset user/user_id，改为 user_name/reply_count */
export interface TicketItem {
  id: string;
  user_name: string;
  type: string;
  subject: string;
  content: string;
  /** open / replied / closed（闭单后回复会被 422 掉） */
  status: string;
  priority: number;
  /** 裸 BIGINT：assign 收的是 `(int) admin_id`，不是 hashid */
  assigned_to: number;
  reply_count: number;
  created_at: string;
}

/**
 * aetherupload 两步协议的回执（preprocess 与 uploading 同一个形状）。
 *
 * **不走 {code,message,data} 信封**：插件端点 `Responser::returnResult()` 直接 json() 出这个对象，
 * 故取值要经 `apiService.postRaw/postMultipart`（见 UploadService）。
 *
 * `error` 为 0 才是成功；**非 0 是给人看的字符串**（插件把异常 message 换成人话塞回该字段），
 * 页面原样显示，不要翻译、不要当错误码比对。没传 hash 时 `savedPath` 恒为空 ⇒ 只能靠 uploading 拿。
 */
export interface UploadTicket {
  error: string | number;
  /** 服务端的建议分块大小（本仓 1000000，仅作参考） */
  chunkSize: number;
  groupSubDir: string;
  resourceTempBaseName: string;
  resourceExt: string;
  /** 非空 = 秒传命中，可直接用；本仓 instant_completion 关着，恒为空 */
  savedPath: string;
}
