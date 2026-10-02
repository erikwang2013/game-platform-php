/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 充值页（`pages/deposit.ts`）。
 *
 * ⚠ 三条 `err_*` 与 `err_gateway` 是**组件方法里 set 进 signal 的字符串**，不是模板字面量 ——
 * 它们同样要过 `t()`：错误提示是用户最需要看懂的地方，留在中文就等于这批没做。
 * `err_gateway` 的 `{msg}` 是**服务端原文**（后端 message 已按 `X-Language` 出对应语言），
 * 这里只补一句本地可操作提示，不覆盖也不改写服务端文案。
 */
export const DEPOSIT: Record<string, [string, string]> = {
  'deposit.created': ['Order created', '订单已创建'],
  'deposit.amount_label': ['Deposit amount', '充值金额'],
  'deposit.credit': ['Platform coins to credit', '到账平台币'],
  'deposit.expires': ['Pay before', '支付截止'],
  'deposit.go_pay': ['Go to checkout', '前往支付'],
  'deposit.pay_hint': [
    'Complete the payment on the checkout page. If it did not open automatically, use the button above.',
    '付款需在收银台完成；若未自动打开，请点上方按钮。',
  ],
  'deposit.bad_link': [
    'The payment link uses an unsupported protocol, so it was not opened automatically. Copy the link below, check it, then open it yourself.',
    '支付链接协议异常，未自动跳转。请复制下方链接、核对无误后自行打开。',
  ],
  'deposit.pay_link': ['Payment link', '支付链接'],
  'deposit.view_orders': ['View deposit orders', '查看充值订单'],
  'deposit.again': ['Make another deposit', '再充一笔'],
  'deposit.title': ['Buy platform coins', '购买平台币'],
  'deposit.method': ['Payment method', '支付方式'],
  'deposit.no_methods': ['No payment method available', '暂无可用支付方式'],
  'deposit.method_option': ['{name} ({limit})', '{name}（{limit}）'],
  'deposit.currency': ['Currency', '币种'],
  'deposit.amount_with_cur': ['Amount ({cur})', '金额（{cur}）'],
  'deposit.submit': ['Pay now', '去支付'],
  'deposit.validity': [
    'The order is valid for 1 hour; please complete the payment within that time.',
    '订单有效期 1 小时，请在此期间完成付款。',
  ],
  'deposit.err_amount_required': ['Enter a deposit amount', '请输入充值金额'],
  'deposit.err_amount_format': [
    'Unsupported amount format: JPY/KRW do not allow decimals, other currencies allow at most 2 decimal places',
    '金额格式不支持：JPY/KRW 不支持小数，其余币种最多 2 位小数',
  ],
  'deposit.err_method_required': ['Select a payment method first', '请先选择支付方式'],
  'deposit.err_gateway': [
    '{msg} (the payment gateway is temporarily unavailable, please try again later)',
    '{msg}（支付网关暂时不可用，请稍后重试）',
  ],
};
