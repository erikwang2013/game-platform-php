/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 英文全量表 —— **键表真值**（键集以本表为准，缺键回落这里）。
 *
 * 与 `TranslationsZh.ets` 逐键相同；键是 `域.名` 两段式，占位符写 `{name}`。
 * 专有名词保留拉丁原文：`PayPal`、`2FA`、`ID`、`yes`。
 *
 * 与 zh 表同款：`Record` 空字面量 + 逐键赋值（ArkTS 禁非空对象字面量）。
 */
export function buildEn(): Record<string, string> {
  const m: Record<string, string> = {};

  // ── 通用 ──────────────────────────────────────────────────
  m['common.loading'] = 'Loading...';
  m['common.no_data'] = 'No data';
  m['common.load_failed'] = 'Loading failed';
  m['common.retry'] = 'Retry';
  m['common.back'] = '← Back';
  m['common.confirm'] = 'Confirm';
  m['common.cancel'] = 'Cancel';
  m['common.save'] = 'Save';
  m['common.submitting'] = 'Submitting...';
  m['common.unnamed'] = 'Untitled';
  m['common.no_description'] = 'No description';
  m['common.unlimited'] = 'Unlimited';
  m['common.currency'] = 'Currency';
  m['common.order_no'] = 'Order No.';
  m['common.balance_after'] = 'Balance';
  m['common.deposit'] = 'Deposit';
  m['common.withdraw'] = 'Withdraw';
  m['common.exchange'] = 'Exchange';
  m['common.rate_line'] = '1 platform coin = {rate} {symbol}';
  m['common.platform_coin_amount'] = '{amount} platform coins';

  // ── 底部导航 / 应用名 ──────────────────────────────────
  m['nav.hall'] = 'Games';
  m['nav.wallet'] = 'Wallet';
  m['nav.profile'] = 'Me';
  m['app.name'] = 'Game Platform';

  // ── 登录 / 注册 ────────────────────────────────────────
  m['login.username_placeholder'] = 'Please enter username';
  m['login.password_placeholder'] = 'Please enter password';
  m['login.password_format_register'] = 'Password must be 8-32 characters and contain upper case, lower case letters and digits';
  m['login.password_min_login'] = 'Password must be at least 6 characters';
  m['login.network_error'] = 'Network error, please check your connection';
  m['login.subtitle_register'] = 'Create an account and start playing';
  m['login.subtitle_login'] = 'Welcome back';
  m['login.hide_password'] = 'Hide';
  m['login.show_password'] = 'Show';
  m['login.register_button'] = 'Register';
  m['login.login_button'] = 'Log in';
  m['login.have_account'] = 'Already have an account?';
  m['login.no_account'] = 'Don\'t have an account?';
  m['login.go_login'] = 'Log in';
  m['login.go_register'] = 'Register now';
  m['login.require_2fa'] = 'This account has two-factor authentication (2FA) enabled. Enter the 6-digit code from your authenticator app, or a 10-digit backup code.';
  m['login.tfa_code_placeholder'] = '6-digit code / 10-digit backup code';
  m['login.tfa_back'] = 'Back';

  // ── 点击验证码弹框（common/CaptchaDialog.ets） ──
  m['captcha.title'] = 'Security verification';
  m['captcha.load_failed'] = 'Failed to load the captcha';
  m['captcha.undo'] = 'Undo';
  m['captcha.refresh'] = 'New image';
  m['captcha.submitting'] = 'Submitting…';
  m['captcha.hint'] = 'Click the characters in order: {list}';
  m['captcha.clicked'] = 'Clicked {done}/{total}';

  // ── 游戏大厅 ──────────────────────────────────────────────
  m['hall.title'] = 'Game Hall';
  m['hall.search_placeholder'] = 'Search games...';
  m['hall.loading'] = 'Loading games...';
  m['hall.no_match'] = 'No matching games';
  m['hall.empty'] = 'No games yet';
  m['hall.enter'] = 'Enter';

  // ── 游戏详情 ──────────────────────────────────────────────
  m['game.missing_id'] = 'Missing game ID';
  m['game.launch_failed'] = 'Failed to launch';
  m['game.title'] = 'Game Details';
  m['game.loading'] = 'Loading details...';
  m['game.supported_currencies'] = 'Supported currencies';
  m['game.launching'] = 'Launching...';
  m['game.launch'] = 'Start game';
  m['game.not_found'] = 'Game not found';
  m['game.launched'] = 'Launched {name}';

  // ── 钱包 ──────────────────────────────────────────────────
  m['wallet.title'] = 'My Wallet';
  m['wallet.loading'] = 'Loading wallet...';
  m['wallet.available_balance'] = 'Available balance';
  m['wallet.total_earned'] = 'Total earned';
  m['wallet.total_spent'] = 'Total spent';
  m['wallet.transactions'] = 'Transactions';
  m['wallet.no_transactions'] = 'No transactions yet';
  m['wallet.tx_exchange_in'] = 'Exchange in';
  m['wallet.tx_exchange_out'] = 'Exchange out';
  m['wallet.tx_game_earn'] = 'Game winnings';
  m['wallet.tx_game_spend'] = 'Game spending';
  m['wallet.frozen'] = 'Frozen {amount}';
  m['wallet.balance_after'] = 'Balance {amount}';

  // ── 充值 ──────────────────────────────────────────────────
  m['deposit.load_methods_failed'] = 'Failed to load payment methods';
  m['deposit.amount_required'] = 'Please enter the deposit amount';
  m['deposit.amount_decimals2'] = 'Up to 2 decimal places';
  m['deposit.method_required'] = 'Please select a payment method';
  m['deposit.create_failed'] = 'Failed to create the deposit order';
  m['deposit.loading_methods'] = 'Loading payment methods...';
  m['deposit.order_created'] = 'Order created';
  m['deposit.status_pending'] = 'Awaiting payment';
  m['deposit.amount_label'] = 'Deposit amount';
  m['deposit.platform_amount'] = 'Platform coins credited';
  m['deposit.expires_at'] = 'Pay before';
  m['deposit.checkout_url'] = 'Payment link';
  m['deposit.no_checkout_url'] = '(the gateway returned no payment link)';
  m['deposit.checkout_hint'] = 'This app cannot open a browser directly. Long-press the link above to copy it, then complete the payment in your browser.';
  m['deposit.again'] = 'Deposit again';
  m['deposit.no_methods'] = 'No payment method available';
  m['deposit.method_label'] = 'Payment method';
  m['deposit.currency_hint'] = 'The currency must match the selected payment method, otherwise the server will reject the order';
  m['deposit.integer_amount'] = 'Integer amount';
  m['deposit.submit'] = 'Create deposit order';
  m['deposit.amount_label_currency'] = 'Amount ({currency})';
  m['deposit.integer_only'] = '{currency} amounts must be integers';

  // ── 提现 ──────────────────────────────────────────────────
  m['withdraw.method_bank'] = 'Bank card';
  m['withdraw.method_crypto'] = 'Cryptocurrency';
  m['withdraw.status_pending'] = 'Pending review';
  m['withdraw.status_reviewing'] = 'Under review';
  m['withdraw.status_manual_review'] = 'Manual review';
  m['withdraw.status_approved'] = 'Approved';
  m['withdraw.status_processing'] = 'Processing';
  m['withdraw.status_completed'] = 'Completed';
  m['withdraw.status_paid'] = 'Paid out';
  m['withdraw.status_rejected'] = 'Rejected';
  m['withdraw.status_cancelled'] = 'Cancelled';
  m['withdraw.status_failed'] = 'Failed';
  m['withdraw.amount_required'] = 'Please enter the withdrawal amount';
  m['withdraw.amount_invalid'] = 'Invalid amount format, up to 4 decimal places';
  m['withdraw.account_required'] = 'Please fill in the payee account details';
  m['withdraw.failed'] = 'Failed to submit the withdrawal request';
  m['withdraw.submitted'] = 'Withdrawal request submitted';
  m['withdraw.amount_label'] = 'Withdrawal amount';
  m['withdraw.fee'] = 'Fee';
  m['withdraw.actual_amount'] = 'Net amount';
  m['withdraw.created_at'] = 'Submitted at';
  m['withdraw.again'] = 'Withdraw again';
  m['withdraw.method_label'] = 'Withdrawal method';
  m['withdraw.amount_platform_label'] = 'Withdrawal amount (platform coins)';
  m['withdraw.min_amount_hint'] = 'Minimum 0.0001';
  m['withdraw.account_info'] = 'Payee account details';
  m['withdraw.account_placeholder'] = 'PayPal email / bank card number and bank name / wallet address';
  m['withdraw.submit'] = 'Submit withdrawal request';
  m['withdraw.review_hint'] = 'Withdrawals require manual/risk review; limits and fees are those returned by the server.';
  m['withdraw.available'] = 'Available {amount}';

  // ── 兑换 ──────────────────────────────────────────────────
  m['exchange.load_games_failed'] = 'Failed to load games';
  m['exchange.amount_required'] = 'Please enter the exchange amount';
  m['exchange.amount_invalid'] = 'Invalid amount format, please enter a number';
  m['exchange.select_first'] = 'Please select a game and a currency first';
  m['exchange.quote_failed'] = 'Failed to get a quote';
  m['exchange.quote_first'] = 'Please get a quote first';
  m['exchange.failed'] = 'Exchange failed';
  m['exchange.success'] = 'Exchange completed';
  m['exchange.buy'] = 'Buy';
  m['exchange.sell'] = 'Sell';
  m['exchange.id_label'] = 'Reference No.';
  m['exchange.pay_platform'] = 'Platform coins paid';
  m['exchange.sell_game'] = 'Game coins sold';
  m['exchange.receive_game_after_spread'] = 'Game coins received (spread deducted)';
  m['exchange.receive_platform_after_spread'] = 'Platform coins received (spread deducted)';
  m['exchange.spread'] = 'Spread';
  m['exchange.rate'] = 'Rate';
  m['exchange.again'] = 'Exchange again';
  m['exchange.no_games'] = 'No games available for exchange';
  m['exchange.direction'] = 'Direction';
  m['exchange.buy_full'] = 'Buy (platform coins → game coins)';
  m['exchange.sell_full'] = 'Sell (game coins → platform coins)';
  m['exchange.select_game'] = 'Select a game';
  m['exchange.select_currency'] = 'Select a currency';
  m['exchange.no_currencies'] = 'This game has no exchangeable currency';
  m['exchange.platform_amount_label'] = 'Platform coin amount';
  m['exchange.sell_amount_hint'] = 'When selling, enter the game coin amount here; the server accepts it under the same field name';
  m['exchange.quoting'] = 'Getting a quote...';
  m['exchange.quote'] = 'Get quote';
  m['exchange.game_before_spread'] = 'Game coins exchanged (before spread)';
  m['exchange.actual_game'] = 'Game coins actually credited';
  m['exchange.platform_before_spread'] = 'Platform coin equivalent (before spread)';
  m['exchange.actual_platform'] = 'Platform coins actually credited';
  m['exchange.spread_pct'] = 'Spread rate';
  m['exchange.quote_hint'] = 'The quote is for display only; the server re-prices on buy/sell.';
  m['exchange.confirm_buy'] = 'Confirm buy';
  m['exchange.confirm_sell'] = 'Confirm sell';
  m['exchange.currency_line'] = '{name} ({symbol})';
  m['exchange.game_amount_label'] = 'Game coin amount ({symbol})';

  // ── 个人中心 ──────────────────────────────────────────────
  m['profile.nickname_placeholder'] = 'Please enter a nickname';
  m['profile.saved'] = 'Saved';
  m['profile.save_failed'] = 'Failed to save';
  m['profile.logout_confirm_title'] = 'Confirm logout';
  m['profile.logout_confirm_message'] = 'Are you sure you want to log out?';
  m['profile.logout_confirm_ok'] = 'Log out';
  m['profile.logged_out'] = 'Logged out';
  m['profile.delete_failed'] = 'Failed to delete the account';
  m['profile.delete_unverified'] = 'The deletion result could not be confirmed; please log in again and verify';
  m['profile.delete_pending'] = 'The deletion request was submitted, but the account profile is still readable; please refresh to confirm';
  m['profile.deleted'] = 'Account deleted';
  m['profile.title'] = 'Profile';
  m['profile.loading'] = 'Loading profile...';
  m['profile.field_username'] = 'Username';
  m['profile.field_email'] = 'Email';
  m['profile.field_phone'] = 'Phone';
  m['profile.field_country'] = 'Country';
  m['profile.field_language'] = 'Language';
  m['profile.ui_language'] = 'Display language';
  m['profile.field_created_at'] = 'Registered at';
  m['profile.field_last_login'] = 'Last login';
  m['profile.edit_nickname'] = 'Edit nickname';
  m['profile.saving'] = 'Saving...';
  m['profile.logout'] = 'Log out';
  m['profile.delete_account'] = 'Delete account';
  m['profile.delete_warning'] = 'Once deleted, this account can no longer sign in and the profile will be anonymized. Any remaining balance must be withdrawn first, otherwise the server will refuse the deletion.';
  m['profile.delete_password_placeholder'] = 'Please enter your current password';
  m['profile.delete_confirm_placeholder'] = 'Type yes to confirm deletion';
  m['profile.deleting'] = 'Deleting...';
  m['profile.delete_confirm_ok'] = 'Confirm deletion';

  return m;
}

export const EN: Record<string, string> = buildEn();
