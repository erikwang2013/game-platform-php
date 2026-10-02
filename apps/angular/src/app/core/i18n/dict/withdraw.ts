/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 提现页（`pages/withdraw.ts`）。
 *
 * `withdraw.m_bank` / `m_crypto` 是**提现方式下拉的标签**：`METHODS` 里存的是词条键，
 * 模板过 `t()` 渲染。品牌名 `PayPal` 不走词条（`t()` 对认不出的键原样返回），
 * 不为它造一条 13 种语言全都一样的词条。
 *
 * ⚠ 三条 `err_*` 把**服务端原文** `{msg}` 放在最前：后端 message 已按 `X-Language` 出了
 * 对应语言，本地只追加一句「怎么办」，不覆盖它 —— 覆盖掉就等于把服务端的诊断信息丢了。
 */
export const WITHDRAW: Record<string, [string, string]> = {
  'withdraw.kyc_pending': ['Identity verification under review', '实名认证审核中'],
  'withdraw.kyc_none': ['Identity verification not completed', '尚未完成实名认证'],
  'withdraw.kyc_hint_pending': [
    'Withdrawals use the default tier until verification is approved (lower limits).',
    '审核通过前提现按默认档计算（额度更低）。',
  ],
  'withdraw.kyc_hint_none': [
    'Withdrawals currently use the default tier; once verified you can raise the per-transaction/day/month limits and lower the fee rate.',
    '当前按默认档计算提现额度；认证通过后可提升单笔/日/月额度并降低费率。',
  ],
  'withdraw.go_kyc': ['Verify now', '去认证'],
  'withdraw.submitted': ['Withdrawal request submitted', '提现申请已提交'],
  'withdraw.amount_label': ['Withdrawal amount', '提现金额'],
  'withdraw.fee': ['Fee', '手续费'],
  'withdraw.actual': ['You receive', '实际到账'],
  'withdraw.submitted_at': ['Submitted at', '提交时间'],
  'withdraw.view_orders': ['View withdrawal orders', '查看提现订单'],
  'withdraw.again': ['Make another withdrawal', '再提一笔'],
  'withdraw.title': ['Request a withdrawal', '申请提现'],
  'withdraw.method_label': ['Withdrawal method', '提现方式'],
  'withdraw.m_bank': ['Bank card', '银行卡'],
  'withdraw.m_crypto': ['Cryptocurrency', '加密货币'],
  'withdraw.amount_platform': ['Withdrawal amount (platform coins)', '提现金额（平台币）'],
  'withdraw.account_info': ['Payout account details', '收款账号信息'],
  'withdraw.account_ph': [
    'PayPal email / bank account and branch / wallet address',
    'PayPal 邮箱 / 银行卡号与开户行 / 钱包地址',
  ],
  'withdraw.submit': ['Submit request', '提交申请'],
  'withdraw.fee_hint': [
    'The fee is based on your tier and VIP discount; the amount you receive is shown after submission.',
    '手续费按等级与 VIP 折扣计算，提交后展示实际到账金额。',
  ],
  'withdraw.captcha_action': ['Confirm withdrawal', '确认提现'],
  'withdraw.err_amount_required': ['Enter a withdrawal amount', '请输入提现金额'],
  'withdraw.err_amount_format': ['Invalid amount format, please enter a number', '金额格式不正确，请输入数字'],
  'withdraw.err_account_required': ['Please fill in the payout account details', '请填写收款账号信息'],
  'withdraw.err_blocked': [
    '{msg} (withdrawals are blocked by the global switch or risk control; contact support if you have questions)',
    '{msg}（提现被全局开关或风控拦截，如有疑问请联系客服）',
  ],
  'withdraw.err_pending': [
    '{msg} (another withdrawal is already being processed, please try again later)',
    '{msg}（已有一笔提现处理中，请稍后再试）',
  ],
  'withdraw.err_unavailable': [
    '{msg} (the withdrawal service is temporarily unavailable, please try again later)',
    '{msg}（提现服务暂时不可用，请稍后重试）',
  ],
};
