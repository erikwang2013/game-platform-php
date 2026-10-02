/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 兑换页（`pages/exchange.ts`）。
 *
 * ⚠ `exchange.rate_line` 与 `exchange.will_receive` / `will_credit` 是**被既有用例当读数的**
 * 三条：`pages/exchange.spec.ts` 用中文标签当 DOM 选择器定位金额格子，并逐字比中文渲染结果
 * （`1 平台币 ≈ 7.12345678 金币`）。它们之所以仍然绿，是因为 jsdom 里没有偏好 ⇒ 走兜底 `zh`。
 * **这 25 处选择器从此隐式依赖 `FALLBACK === 'zh'`** —— `i18n.spec.ts` 里那条
 * 「兜底语言是 zh 字面量」的钉子正是这个隐性依赖的守卫。
 *
 * `{rate}` 是**汇率**不是金额，原样透传（`exchange.spec.ts` 专门钉了它不挂 title、
 * 不被 `money()` 货币化）。
 */
export const EXCHANGE: Record<string, [string, string]> = {
  'exchange.done': ['Exchange complete', '兑换完成'],
  'exchange.buy': ['Buy', '买入'],
  'exchange.sell': ['Sell', '卖出'],
  'exchange.pay_platform': ['Platform coins paid', '支付平台币'],
  'exchange.sell_game': ['Game coins sold', '卖出游戏币'],
  'exchange.recv_game_net': ['Game coins received (after spread)', '到账游戏币（已扣点差）'],
  'exchange.recv_platform_net': ['Platform coins received (after spread)', '到账平台币（已扣点差）'],
  'exchange.filled_rate': ['Filled rate', '成交汇率'],
  'exchange.again': ['Make another exchange', '再兑一笔'],
  'exchange.title': ['Exchange platform coins / game coins', '平台币 / 游戏币互兑'],
  'exchange.game': ['Game', '游戏'],
  'exchange.no_games': ['No game available for exchange', '暂无可兑换的游戏'],
  'exchange.game_currency': ['Game currency', '游戏币种'],
  'exchange.no_currencies': ['This game has no available currency', '该游戏暂无可用币种'],
  'exchange.currency_option': ['{name} ({symbol}) · rate {rate}', '{name}（{symbol}）· 汇率 {rate}'],
  'exchange.direction': ['Direction', '方向'],
  'exchange.dir_in': ['Buy (platform coins → game coins)', '买入（平台币 → 游戏币）'],
  'exchange.dir_out': ['Sell (game coins → platform coins)', '卖出（游戏币 → 平台币）'],
  'exchange.amount_in': ['Platform coins to pay', '支付平台币数量'],
  'exchange.amount_out': ['Game coins to sell ({name})', '卖出游戏币数量（{name}）'],
  'exchange.amount_hint': ['Enter an amount in game coins, not platform coins.', '此处填写的是游戏币数量，不是平台币数量。'],
  'exchange.quote': ['Get a quote', '询价'],
  'exchange.quoting': ['Getting a quote…', '询价中…'],
  'exchange.quote_result': ['Quote', '询价结果'],
  'exchange.rate_line': ['1 platform coin ≈ {rate} {name}', '1 平台币 ≈ {rate} {name}'],
  'exchange.equiv_game': ['Equivalent in game coins (before spread)', '折合游戏币（扣点差前）'],
  'exchange.will_receive': ['You will receive', '预计获得'],
  'exchange.equiv_platform': ['Equivalent in platform coins (before spread)', '折合平台币（扣点差前）'],
  'exchange.will_credit': ['You will be credited', '预计到账'],
  'exchange.confirming': ['Exchanging…', '兑换中…'],
  'exchange.confirm': ['Confirm exchange', '确认兑换'],
  'exchange.quote_hint': ['Quotes move with the market; changing the amount or currency requires a new quote.', '报价随行情变动，修改数量或币种后需重新询价。'],
  'exchange.captcha_action': ['Confirm sell', '确认卖出'],
  'exchange.err_amount_required': ['Enter an exchange amount', '请输入兑换数量'],
  'exchange.err_amount_format': ['Invalid amount format, please enter a number', '数量格式不正确，请输入数字'],
  'exchange.err_pick_required': ['Select a game and currency first', '请先选择游戏与币种'],
};
