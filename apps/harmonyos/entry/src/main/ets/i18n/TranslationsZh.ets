/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 中文全量表 —— **抽取前源码里那些字面量的原文，逐字未改**（改完可用
 * `git show HEAD:apps/harmonyos/entry/src/main/ets/**` 逐键对盘）。
 *
 * 本表也是本树的**默认语言**（`LocaleController.FALLBACK_CODE = 'zh'`）：
 * C 端 App 面向中文用户，且服务端 `config('translation.locale')` 的默认也是 zh
 * —— 两边默认必须同语言，否则就是"界面中文、服务端英文"的同屏两语言。
 *
 * 键是 `域.名` 两段式；占位符写 `{name}`，查表后用 `t1()` 或 `.replace()` 填。
 *
 * **为什么用 `Record` 空字面量 + 逐键赋值，而不是一个大对象字面量**：ArkTS 禁
 * `arkts-no-untyped-obj-literals`，非空对象字面量直接报错；空字面量 + 下标赋值是
 * 合法形态。codelinter 对这条零灵敏 —— 只有真编译器认（见
 * `admin/apps/harmonyos/entry/src/main/ets/i18n/TranslationsZh.ets` 同款写法）。
 */
export function buildZh(): Record<string, string> {
  const m: Record<string, string> = {};

  // ── 通用 ──────────────────────────────────────────────────
  m['common.loading'] = '加载中...';
  m['common.no_data'] = '暂无数据';
  m['common.load_failed'] = '加载失败';
  m['common.retry'] = '重试';
  m['common.back'] = '← 返回';
  m['common.confirm'] = '确认';
  m['common.cancel'] = '取消';
  m['common.save'] = '保存';
  m['common.submitting'] = '提交中...';
  m['common.unnamed'] = '未命名';
  m['common.no_description'] = '暂无简介';
  m['common.unlimited'] = '不限';
  m['common.currency'] = '币种';
  m['common.order_no'] = '订单号';
  m['common.balance_after'] = '账户余额';
  m['common.deposit'] = '充值';
  m['common.withdraw'] = '提现';
  m['common.exchange'] = '兑换';
  m['common.rate_line'] = '1 平台币 = {rate} {symbol}';
  m['common.platform_coin_amount'] = '{amount} 平台币';

  // ── 底部导航 / 应用名 ──────────────────────────────────
  m['nav.hall'] = '大厅';
  m['nav.wallet'] = '钱包';
  m['nav.profile'] = '我的';
  m['app.name'] = '游戏平台';

  // ── 登录 / 注册 ────────────────────────────────────────
  m['login.username_placeholder'] = '请输入用户名';
  m['login.password_placeholder'] = '请输入密码';
  m['login.password_format_register'] = '密码 8-32 位，需含大小写字母和数字';
  m['login.password_min_login'] = '密码至少 6 位';
  m['login.network_error'] = '网络错误，请检查连接';
  m['login.subtitle_register'] = '创建账号，开始游戏';
  m['login.subtitle_login'] = '欢迎回来';
  m['login.hide_password'] = '隐藏';
  m['login.show_password'] = '显示';
  m['login.register_button'] = '注 册';
  m['login.login_button'] = '登 录';
  m['login.have_account'] = '已有账号？';
  m['login.no_account'] = '还没有账号？';
  m['login.go_login'] = '去登录';
  m['login.go_register'] = '立即注册';
  m['login.require_2fa'] = '该账号已开启二次验证（2FA），请输入验证器 App 的 6 位动态码，或一个 10 位备份码。';
  m['login.tfa_code_placeholder'] = '6 位动态码 / 10 位备份码';
  m['login.tfa_back'] = '返回上一步';

  // ── 点击验证码弹框（common/CaptchaDialog.ets） ──
  m['captcha.title'] = '安全验证';
  m['captcha.load_failed'] = '验证码加载失败';
  m['captcha.undo'] = '撤销';
  m['captcha.refresh'] = '换一张';
  m['captcha.submitting'] = '提交中…';
  m['captcha.hint'] = '请按顺序点击图中文字: {list}';
  m['captcha.clicked'] = '已点击 {done}/{total}';

  // ── 游戏大厅 ──────────────────────────────────────────────
  m['hall.title'] = '游戏大厅';
  m['hall.search_placeholder'] = '搜索游戏...';
  m['hall.loading'] = '加载游戏中...';
  m['hall.no_match'] = '没有匹配的游戏';
  m['hall.empty'] = '暂无游戏';
  m['hall.enter'] = '进入';

  // ── 游戏详情 ──────────────────────────────────────────────
  m['game.missing_id'] = '缺少游戏 ID';
  m['game.launch_failed'] = '启动失败';
  m['game.title'] = '游戏详情';
  m['game.loading'] = '加载详情中...';
  m['game.supported_currencies'] = '支持币种';
  m['game.launching'] = '启动中...';
  m['game.launch'] = '开始游戏';
  m['game.not_found'] = '游戏不存在';
  m['game.launched'] = '已启动 {name}';

  // ── 钱包 ──────────────────────────────────────────────────
  m['wallet.title'] = '我的钱包';
  m['wallet.loading'] = '加载钱包中...';
  m['wallet.available_balance'] = '可用余额';
  m['wallet.total_earned'] = '累计获得';
  m['wallet.total_spent'] = '累计支出';
  m['wallet.transactions'] = '流水记录';
  m['wallet.no_transactions'] = '暂无流水';
  m['wallet.tx_exchange_in'] = '兑入';
  m['wallet.tx_exchange_out'] = '兑出';
  m['wallet.tx_game_earn'] = '游戏赢得';
  m['wallet.tx_game_spend'] = '游戏消费';
  m['wallet.frozen'] = '冻结 {amount}';
  m['wallet.balance_after'] = '余额 {amount}';

  // ── 充值 ──────────────────────────────────────────────────
  m['deposit.load_methods_failed'] = '加载支付方式失败';
  m['deposit.amount_required'] = '请输入充值金额';
  m['deposit.amount_decimals2'] = '金额最多 2 位小数';
  m['deposit.method_required'] = '请选择支付方式';
  m['deposit.create_failed'] = '创建充值订单失败';
  m['deposit.loading_methods'] = '加载支付方式中...';
  m['deposit.order_created'] = '订单已创建';
  m['deposit.status_pending'] = '待支付';
  m['deposit.amount_label'] = '充值金额';
  m['deposit.platform_amount'] = '到账平台币';
  m['deposit.expires_at'] = '支付截止';
  m['deposit.checkout_url'] = '支付链接';
  m['deposit.no_checkout_url'] = '（网关未返回支付链接）';
  m['deposit.checkout_hint'] = '本应用无法直接打开浏览器，请长按上方链接复制，再到浏览器中完成支付。';
  m['deposit.again'] = '再充一笔';
  m['deposit.no_methods'] = '暂无可用支付方式';
  m['deposit.method_label'] = '支付方式';
  m['deposit.currency_hint'] = '币种需与所选支付方式一致，否则服务端会拒绝下单';
  m['deposit.integer_amount'] = '整数金额';
  m['deposit.submit'] = '创建充值订单';
  m['deposit.amount_label_currency'] = '金额（{currency}）';
  m['deposit.integer_only'] = '{currency} 金额只能为整数';

  // ── 提现 ──────────────────────────────────────────────────
  m['withdraw.method_bank'] = '银行卡';
  m['withdraw.method_crypto'] = '加密货币';
  m['withdraw.status_pending'] = '待审核';
  m['withdraw.status_reviewing'] = '审核中';
  m['withdraw.status_manual_review'] = '人工审核';
  m['withdraw.status_approved'] = '已通过';
  m['withdraw.status_processing'] = '处理中';
  m['withdraw.status_completed'] = '已完成';
  m['withdraw.status_paid'] = '已打款';
  m['withdraw.status_rejected'] = '已驳回';
  m['withdraw.status_cancelled'] = '已取消';
  m['withdraw.status_failed'] = '失败';
  m['withdraw.amount_required'] = '请输入提现金额';
  m['withdraw.amount_invalid'] = '金额格式不正确，最多 4 位小数';
  m['withdraw.account_required'] = '请填写收款账号信息';
  m['withdraw.failed'] = '提现申请失败';
  m['withdraw.submitted'] = '提现申请已提交';
  m['withdraw.amount_label'] = '提现金额';
  m['withdraw.fee'] = '手续费';
  m['withdraw.actual_amount'] = '实际到账';
  m['withdraw.created_at'] = '提交时间';
  m['withdraw.again'] = '再提一笔';
  m['withdraw.method_label'] = '提现方式';
  m['withdraw.amount_platform_label'] = '提现金额（平台币）';
  m['withdraw.min_amount_hint'] = '最低 0.0001';
  m['withdraw.account_info'] = '收款账号信息';
  m['withdraw.account_placeholder'] = 'PayPal 邮箱 / 银行卡号与开户行 / 钱包地址';
  m['withdraw.submit'] = '提交提现申请';
  m['withdraw.review_hint'] = '提现需人工/风控审核，限额与手续费以服务端返回为准。';
  m['withdraw.available'] = '可用 {amount}';

  // ── 兑换 ──────────────────────────────────────────────────
  m['exchange.load_games_failed'] = '加载游戏失败';
  m['exchange.amount_required'] = '请输入兑换数量';
  m['exchange.amount_invalid'] = '数量格式不正确，请输入数字';
  m['exchange.select_first'] = '请先选择游戏与币种';
  m['exchange.quote_failed'] = '询价失败';
  m['exchange.quote_first'] = '请先询价';
  m['exchange.failed'] = '兑换失败';
  m['exchange.success'] = '兑换成功';
  m['exchange.buy'] = '买入';
  m['exchange.sell'] = '卖出';
  m['exchange.id_label'] = '单号';
  m['exchange.pay_platform'] = '支付平台币';
  m['exchange.sell_game'] = '卖出游戏币';
  m['exchange.receive_game_after_spread'] = '到账游戏币（已扣点差）';
  m['exchange.receive_platform_after_spread'] = '到账平台币（已扣点差）';
  m['exchange.spread'] = '点差';
  m['exchange.rate'] = '汇率';
  m['exchange.again'] = '再兑换一笔';
  m['exchange.no_games'] = '暂无可兑换的游戏';
  m['exchange.direction'] = '方向';
  m['exchange.buy_full'] = '买入（平台币 → 游戏币）';
  m['exchange.sell_full'] = '卖出（游戏币 → 平台币）';
  m['exchange.select_game'] = '选择游戏';
  m['exchange.select_currency'] = '选择币种';
  m['exchange.no_currencies'] = '该游戏暂无可兑换币种';
  m['exchange.platform_amount_label'] = '平台币数量';
  m['exchange.sell_amount_hint'] = '卖出时此处填写的是游戏币数量，服务端按同一字段名接收';
  m['exchange.quoting'] = '询价中...';
  m['exchange.quote'] = '询价';
  m['exchange.game_before_spread'] = '兑换游戏币（扣点差前）';
  m['exchange.actual_game'] = '实际到账游戏币';
  m['exchange.platform_before_spread'] = '折合平台币（扣点差前）';
  m['exchange.actual_platform'] = '实际到账平台币';
  m['exchange.spread_pct'] = '点差率';
  m['exchange.quote_hint'] = '报价仅作展示，买入/卖出时服务端会重新定价。';
  m['exchange.confirm_buy'] = '确认买入';
  m['exchange.confirm_sell'] = '确认卖出';
  m['exchange.currency_line'] = '{name}（{symbol}）';
  m['exchange.game_amount_label'] = '游戏币数量（{symbol}）';

  // ── 个人中心 ──────────────────────────────────────────────
  m['profile.nickname_placeholder'] = '请输入昵称';
  m['profile.saved'] = '已保存';
  m['profile.save_failed'] = '保存失败';
  m['profile.logout_confirm_title'] = '确认退出';
  m['profile.logout_confirm_message'] = '确定要退出登录吗？';
  m['profile.logout_confirm_ok'] = '确定退出';
  m['profile.logged_out'] = '已退出登录';
  m['profile.delete_failed'] = '注销失败';
  m['profile.delete_unverified'] = '注销结果无法确认，请重新登录后再核实';
  m['profile.delete_pending'] = '注销请求已提交，但账号资料仍可读取，请刷新后确认';
  m['profile.deleted'] = '账号已注销';
  m['profile.title'] = '个人中心';
  m['profile.loading'] = '加载资料中...';
  m['profile.field_username'] = '用户名';
  m['profile.field_email'] = '邮箱';
  m['profile.field_phone'] = '手机';
  m['profile.field_country'] = '国家';
  m['profile.field_language'] = '语言';
  m['profile.ui_language'] = '界面语言';
  m['profile.field_created_at'] = '注册时间';
  m['profile.field_last_login'] = '最近登录';
  m['profile.edit_nickname'] = '修改昵称';
  m['profile.saving'] = '保存中...';
  m['profile.logout'] = '退出登录';
  m['profile.delete_account'] = '注销账号';
  m['profile.delete_warning'] = '注销后该账号无法再登录，个人资料会被匿名化。账号内余额需先自行提现清零，否则服务端会拒绝注销。';
  m['profile.delete_password_placeholder'] = '请输入当前密码';
  m['profile.delete_confirm_placeholder'] = '输入 yes 确认注销';
  m['profile.deleting'] = '注销中...';
  m['profile.delete_confirm_ok'] = '确认注销';

  return m;
}

export const ZH: Record<string, string> = buildZh();
