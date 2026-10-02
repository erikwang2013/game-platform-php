/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * বাংলা 表（语言短码 `bn`）—— 由 `TranslationsEn.ets` 的 196 键同构生成。
 *
 * **措辞无母语者复核**：只做了机械校验（键集/占位符/结构/编译），译文质量未复核。未覆盖的键回落 `TranslationsEn.ets`（本树查表顺序：当前语言 → EN）。
 *
 * 键与分节布局**不在本文件里手写**：生成时从 `TranslationsZh.ets` 读取，故键集/顺序
 * 与真值表恒等（占位符 `{name}` 逐键保留）。与 `TranslationsEn.ets` 同款：
 * `Record` 空字面量 + 逐键赋值（ArkTS 禁非空对象字面量）。
 */
export function buildBn(): Record<string, string> {
  const m: Record<string, string> = {};

  // ── 通用 ──────────────────────────────────────────────────
  m['common.loading'] = 'লোড হচ্ছে...';
  m['common.no_data'] = 'কোনো ডেটা নেই';
  m['common.load_failed'] = 'লোড করা যায়নি';
  m['common.retry'] = 'আবার চেষ্টা করুন';
  m['common.back'] = '← ফিরে যান';
  m['common.confirm'] = 'নিশ্চিত করুন';
  m['common.cancel'] = 'বাতিল';
  m['common.save'] = 'সংরক্ষণ';
  m['common.submitting'] = 'জমা হচ্ছে...';
  m['common.unnamed'] = 'নামহীন';
  m['common.no_description'] = 'কোনো বিবরণ নেই';
  m['common.unlimited'] = 'সীমাহীন';
  m['common.currency'] = 'মুদ্রা';
  m['common.order_no'] = 'অর্ডার নম্বর';
  m['common.balance_after'] = 'ব্যালেন্স';
  m['common.deposit'] = 'ডিপোজিট';
  m['common.withdraw'] = 'উইথড্র';
  m['common.exchange'] = 'এক্সচেঞ্জ';
  m['common.rate_line'] = '1 প্ল্যাটফর্ম কয়েন = {rate} {symbol}';
  m['common.platform_coin_amount'] = '{amount} প্ল্যাটফর্ম কয়েন';

  // ── 底部导航 / 应用名 ──────────────────────────────────
  m['nav.hall'] = 'হল';
  m['nav.wallet'] = 'ওয়ালেট';
  m['nav.profile'] = 'প্রোফাইল';
  m['app.name'] = 'গেমিং প্ল্যাটফর্ম';

  // ── 登录 / 注册 ────────────────────────────────────────
  m['login.username_placeholder'] = 'ব্যবহারকারীর নাম লিখুন';
  m['login.password_placeholder'] = 'পাসওয়ার্ড লিখুন';
  m['login.password_format_register'] = 'পাসওয়ার্ড 8-32 অক্ষরের হতে হবে এবং এতে বড় হাতের, ছোট হাতের অক্ষর ও সংখ্যা থাকতে হবে';
  m['login.password_min_login'] = 'পাসওয়ার্ড কমপক্ষে 6 অক্ষরের হতে হবে';
  m['login.network_error'] = 'নেটওয়ার্ক ত্রুটি, আপনার সংযোগ পরীক্ষা করুন';
  m['login.subtitle_register'] = 'অ্যাকাউন্ট তৈরি করে খেলা শুরু করুন';
  m['login.subtitle_login'] = 'স্বাগতম';
  m['login.hide_password'] = 'লুকান';
  m['login.show_password'] = 'দেখান';
  m['login.register_button'] = 'নিবন্ধন';
  m['login.login_button'] = 'লগ ইন';
  m['login.have_account'] = 'আগেই অ্যাকাউন্ট আছে?';
  m['login.no_account'] = 'অ্যাকাউন্ট নেই?';
  m['login.go_login'] = 'লগ ইন';
  m['login.go_register'] = 'এখনই নিবন্ধন করুন';
  m['login.require_2fa'] = 'এই অ্যাকাউন্টে দুই-ধাপ যাচাইকরণ (2FA) চালু আছে। আপনার প্রমাণক অ্যাপ থেকে ৬ সংখ্যার কোড বা ১০ অক্ষরের ব্যাকআপ কোড লিখুন।';
  m['login.tfa_code_placeholder'] = '৬ সংখ্যার কোড / ১০ অক্ষরের ব্যাকআপ কোড';
  m['login.tfa_back'] = 'ফিরে যান';

  // ── 点击验证码弹框（common/CaptchaDialog.ets） ──
  m['captcha.title'] = 'নিরাপত্তা যাচাই';
  m['captcha.load_failed'] = 'ক্যাপচা লোড করা যায়নি';
  m['captcha.undo'] = 'ফিরিয়ে নিন';
  m['captcha.refresh'] = 'নতুন ছবি';
  m['captcha.submitting'] = 'জমা হচ্ছে…';
  m['captcha.hint'] = 'ছবির অক্ষরগুলো ক্রমানুসারে ক্লিক করুন: {list}';
  m['captcha.clicked'] = 'ক্লিক করা হয়েছে {done}/{total}';

  // ── 游戏大厅 ──────────────────────────────────────────────
  m['hall.title'] = 'গেম হল';
  m['hall.search_placeholder'] = 'গেম খুঁজুন...';
  m['hall.loading'] = 'গেম লোড হচ্ছে...';
  m['hall.no_match'] = 'কোনো মিলে যাওয়া গেম নেই';
  m['hall.empty'] = 'এখনো কোনো গেম নেই';
  m['hall.enter'] = 'প্রবেশ করুন';

  // ── 游戏详情 ──────────────────────────────────────────────
  m['game.missing_id'] = 'গেম ID অনুপস্থিত';
  m['game.launch_failed'] = 'চালু করা যায়নি';
  m['game.title'] = 'গেমের বিবরণ';
  m['game.loading'] = 'বিবরণ লোড হচ্ছে...';
  m['game.supported_currencies'] = 'সমর্থিত মুদ্রা';
  m['game.launching'] = 'চালু হচ্ছে...';
  m['game.launch'] = 'খেলা শুরু করুন';
  m['game.not_found'] = 'গেম পাওয়া যায়নি';
  m['game.launched'] = '{name} চালু হয়েছে';

  // ── 钱包 ──────────────────────────────────────────────────
  m['wallet.title'] = 'আমার ওয়ালেট';
  m['wallet.loading'] = 'ওয়ালেট লোড হচ্ছে...';
  m['wallet.available_balance'] = 'উপলব্ধ ব্যালেন্স';
  m['wallet.total_earned'] = 'মোট অর্জিত';
  m['wallet.total_spent'] = 'মোট ব্যয়';
  m['wallet.transactions'] = 'লেনদেন';
  m['wallet.no_transactions'] = 'এখনো কোনো লেনদেন নেই';
  m['wallet.tx_exchange_in'] = 'এক্সচেঞ্জে প্রাপ্ত';
  m['wallet.tx_exchange_out'] = 'এক্সচেঞ্জে পরিশোধিত';
  m['wallet.tx_game_earn'] = 'গেমে জয়';
  m['wallet.tx_game_spend'] = 'গেমে ব্যয়';
  m['wallet.frozen'] = 'ফ্রিজ {amount}';
  m['wallet.balance_after'] = 'ব্যালেন্স {amount}';

  // ── 充值 ──────────────────────────────────────────────────
  m['deposit.load_methods_failed'] = 'পেমেন্ট পদ্ধতি লোড করা যায়নি';
  m['deposit.amount_required'] = 'ডিপোজিট পরিমাণ লিখুন';
  m['deposit.amount_decimals2'] = 'সর্বোচ্চ 2 দশমিক স্থান';
  m['deposit.method_required'] = 'পেমেন্ট পদ্ধতি নির্বাচন করুন';
  m['deposit.create_failed'] = 'ডিপোজিট অর্ডার তৈরি করা যায়নি';
  m['deposit.loading_methods'] = 'পেমেন্ট পদ্ধতি লোড হচ্ছে...';
  m['deposit.order_created'] = 'অর্ডার তৈরি হয়েছে';
  m['deposit.status_pending'] = 'পেমেন্টের অপেক্ষায়';
  m['deposit.amount_label'] = 'ডিপোজিট পরিমাণ';
  m['deposit.platform_amount'] = 'জমা হওয়া প্ল্যাটফর্ম কয়েন';
  m['deposit.expires_at'] = 'এর আগে পরিশোধ করুন';
  m['deposit.checkout_url'] = 'পেমেন্ট লিংক';
  m['deposit.no_checkout_url'] = '(গেটওয়ে কোনো পেমেন্ট লিংক ফেরত দেয়নি)';
  m['deposit.checkout_hint'] = 'এই অ্যাপ সরাসরি ব্রাউজার খুলতে পারে না। অনুলিপি করতে উপরের লিংকটি দীর্ঘক্ষণ চাপুন, তারপর ব্রাউজারে পেমেন্ট সম্পন্ন করুন।';
  m['deposit.again'] = 'আবার ডিপোজিট করুন';
  m['deposit.no_methods'] = 'কোনো পেমেন্ট পদ্ধতি উপলব্ধ নেই';
  m['deposit.method_label'] = 'পেমেন্ট পদ্ধতি';
  m['deposit.currency_hint'] = 'মুদ্রা অবশ্যই নির্বাচিত পেমেন্ট পদ্ধতির সাথে মিলতে হবে, অন্যথায় সার্ভার অর্ডার প্রত্যাখ্যান করবে';
  m['deposit.integer_amount'] = 'পূর্ণসংখ্যা পরিমাণ';
  m['deposit.submit'] = 'ডিপোজিট অর্ডার তৈরি করুন';
  m['deposit.amount_label_currency'] = 'পরিমাণ ({currency})';
  m['deposit.integer_only'] = '{currency} এর পরিমাণ পূর্ণসংখ্যা হতে হবে';

  // ── 提现 ──────────────────────────────────────────────────
  m['withdraw.method_bank'] = 'ব্যাংক কার্ড';
  m['withdraw.method_crypto'] = 'ক্রিপ্টোকারেন্সি';
  m['withdraw.status_pending'] = 'পর্যালোচনার অপেক্ষায়';
  m['withdraw.status_reviewing'] = 'পর্যালোচনায়';
  m['withdraw.status_manual_review'] = 'ম্যানুয়াল পর্যালোচনা';
  m['withdraw.status_approved'] = 'অনুমোদিত';
  m['withdraw.status_processing'] = 'প্রক্রিয়াধীন';
  m['withdraw.status_completed'] = 'সম্পন্ন';
  m['withdraw.status_paid'] = 'পরিশোধিত';
  m['withdraw.status_rejected'] = 'প্রত্যাখ্যাত';
  m['withdraw.status_cancelled'] = 'বাতিল';
  m['withdraw.status_failed'] = 'ব্যর্থ';
  m['withdraw.amount_required'] = 'উইথড্র পরিমাণ লিখুন';
  m['withdraw.amount_invalid'] = 'পরিমাণের বিন্যাস অবৈধ, সর্বোচ্চ 4 দশমিক স্থান';
  m['withdraw.account_required'] = 'প্রাপকের অ্যাকাউন্টের তথ্য পূরণ করুন';
  m['withdraw.failed'] = 'উইথড্র অনুরোধ পাঠানো যায়নি';
  m['withdraw.submitted'] = 'উইথড্র অনুরোধ পাঠানো হয়েছে';
  m['withdraw.amount_label'] = 'উইথড্র পরিমাণ';
  m['withdraw.fee'] = 'ফি';
  m['withdraw.actual_amount'] = 'নিট পরিমাণ';
  m['withdraw.created_at'] = 'জমা দেওয়ার সময়';
  m['withdraw.again'] = 'আবার উইথড্র করুন';
  m['withdraw.method_label'] = 'উইথড্র পদ্ধতি';
  m['withdraw.amount_platform_label'] = 'উইথড্র পরিমাণ (প্ল্যাটফর্ম কয়েন)';
  m['withdraw.min_amount_hint'] = 'সর্বনিম্ন 0.0001';
  m['withdraw.account_info'] = 'প্রাপকের অ্যাকাউন্টের তথ্য';
  m['withdraw.account_placeholder'] = 'PayPal ইমেল / ব্যাংক কার্ড নম্বর ও ব্যাংক / ওয়ালেট ঠিকানা';
  m['withdraw.submit'] = 'উইথড্র অনুরোধ পাঠান';
  m['withdraw.review_hint'] = 'উইথড্রর জন্য ম্যানুয়াল/ঝুঁকি পর্যালোচনা প্রয়োজন; সীমা ও ফি সার্ভার যা ফেরত দেয় তা অনুসরণ করে।';
  m['withdraw.available'] = 'উপলব্ধ {amount}';

  // ── 兑换 ──────────────────────────────────────────────────
  m['exchange.load_games_failed'] = 'গেম লোড করা যায়নি';
  m['exchange.amount_required'] = 'এক্সচেঞ্জ পরিমাণ লিখুন';
  m['exchange.amount_invalid'] = 'পরিমাণের বিন্যাস অবৈধ, একটি সংখ্যা লিখুন';
  m['exchange.select_first'] = 'প্রথমে একটি গেম ও মুদ্রা নির্বাচন করুন';
  m['exchange.quote_failed'] = 'কোটেশন পাওয়া যায়নি';
  m['exchange.quote_first'] = 'আগে কোটেশন নিন';
  m['exchange.failed'] = 'এক্সচেঞ্জ ব্যর্থ';
  m['exchange.success'] = 'এক্সচেঞ্জ সম্পন্ন';
  m['exchange.buy'] = 'কিনুন';
  m['exchange.sell'] = 'বিক্রি করুন';
  m['exchange.id_label'] = 'রেফারেন্স নম্বর';
  m['exchange.pay_platform'] = 'পরিশোধিত প্ল্যাটফর্ম কয়েন';
  m['exchange.sell_game'] = 'বিক্রিত গেম কয়েন';
  m['exchange.receive_game_after_spread'] = 'প্রাপ্ত গেম কয়েন (স্প্রেড বাদ দিয়ে)';
  m['exchange.receive_platform_after_spread'] = 'প্রাপ্ত প্ল্যাটফর্ম কয়েন (স্প্রেড বাদ দিয়ে)';
  m['exchange.spread'] = 'স্প্রেড';
  m['exchange.rate'] = 'হার';
  m['exchange.again'] = 'আবার এক্সচেঞ্জ করুন';
  m['exchange.no_games'] = 'এক্সচেঞ্জের জন্য কোনো গেম নেই';
  m['exchange.direction'] = 'দিক';
  m['exchange.buy_full'] = 'কিনুন (প্ল্যাটফর্ম কয়েন → গেম কয়েন)';
  m['exchange.sell_full'] = 'বিক্রি করুন (গেম কয়েন → প্ল্যাটফর্ম কয়েন)';
  m['exchange.select_game'] = 'গেম নির্বাচন করুন';
  m['exchange.select_currency'] = 'মুদ্রা নির্বাচন করুন';
  m['exchange.no_currencies'] = 'এই গেমে এক্সচেঞ্জযোগ্য মুদ্রা নেই';
  m['exchange.platform_amount_label'] = 'প্ল্যাটফর্ম কয়েন পরিমাণ';
  m['exchange.sell_amount_hint'] = 'বিক্রি করার সময় এখানে গেম কয়েনের পরিমাণ লিখুন; সার্ভার একই ফিল্ড নামে এটি গ্রহণ করে';
  m['exchange.quoting'] = 'কোটেশন আনা হচ্ছে...';
  m['exchange.quote'] = 'কোটেশন নিন';
  m['exchange.game_before_spread'] = 'এক্সচেঞ্জকৃত গেম কয়েন (স্প্রেড বাদ দেওয়ার আগে)';
  m['exchange.actual_game'] = 'প্রকৃতপক্ষে জমা হওয়া গেম কয়েন';
  m['exchange.platform_before_spread'] = 'প্ল্যাটফর্ম কয়েনে সমতুল্য (স্প্রেড বাদ দেওয়ার আগে)';
  m['exchange.actual_platform'] = 'প্রকৃতপক্ষে জমা হওয়া প্ল্যাটফর্ম কয়েন';
  m['exchange.spread_pct'] = 'স্প্রেড হার';
  m['exchange.quote_hint'] = 'কোটেশন শুধু প্রদর্শনের জন্য; কেনা/বেচার সময় সার্ভার দাম পুনরায় নির্ধারণ করে।';
  m['exchange.confirm_buy'] = 'কেনা নিশ্চিত করুন';
  m['exchange.confirm_sell'] = 'বিক্রি নিশ্চিত করুন';
  m['exchange.currency_line'] = '{name} ({symbol})';
  m['exchange.game_amount_label'] = 'গেম কয়েন পরিমাণ ({symbol})';

  // ── 个人中心 ──────────────────────────────────────────────
  m['profile.nickname_placeholder'] = 'ডাকনাম লিখুন';
  m['profile.saved'] = 'সংরক্ষিত';
  m['profile.save_failed'] = 'সংরক্ষণ করা যায়নি';
  m['profile.logout_confirm_title'] = 'লগ আউট নিশ্চিত করুন';
  m['profile.logout_confirm_message'] = 'আপনি কি সত্যিই লগ আউট করতে চান?';
  m['profile.logout_confirm_ok'] = 'লগ আউট';
  m['profile.logged_out'] = 'লগ আউট হয়েছে';
  m['profile.delete_failed'] = 'অ্যাকাউন্ট মুছে ফেলা যায়নি';
  m['profile.delete_unverified'] = 'মোছার ফলাফল নিশ্চিত করা যায়নি; আবার লগ ইন করে যাচাই করুন';
  m['profile.delete_pending'] = 'মোছার অনুরোধ পাঠানো হয়েছে, তবে প্রোফাইল এখনো পড়া যায়; নিশ্চিত করতে রিফ্রেশ করুন';
  m['profile.deleted'] = 'অ্যাকাউন্ট মুছে ফেলা হয়েছে';
  m['profile.title'] = 'প্রোফাইল';
  m['profile.loading'] = 'প্রোফাইল লোড হচ্ছে...';
  m['profile.field_username'] = 'ব্যবহারকারীর নাম';
  m['profile.field_email'] = 'ইমেল';
  m['profile.field_phone'] = 'ফোন';
  m['profile.field_country'] = 'দেশ';
  m['profile.field_language'] = 'ভাষা';
  m['profile.ui_language'] = 'প্রদর্শনের ভাষা';
  m['profile.field_created_at'] = 'নিবন্ধনের তারিখ';
  m['profile.field_last_login'] = 'সর্বশেষ লগ ইন';
  m['profile.edit_nickname'] = 'ডাকনাম পরিবর্তন করুন';
  m['profile.saving'] = 'সংরক্ষণ হচ্ছে...';
  m['profile.logout'] = 'লগ আউট';
  m['profile.delete_account'] = 'অ্যাকাউন্ট মুছুন';
  m['profile.delete_warning'] = 'মোছার পর এই অ্যাকাউন্ট দিয়ে আর লগ ইন করা যাবে না এবং প্রোফাইল বেনামী করা হবে। অবশিষ্ট ব্যালেন্স আগে তুলে শূন্য করতে হবে, অন্যথায় সার্ভার মোছার অনুরোধ প্রত্যাখ্যান করবে।';
  m['profile.delete_password_placeholder'] = 'আপনার বর্তমান পাসওয়ার্ড লিখুন';
  m['profile.delete_confirm_placeholder'] = 'মোছা নিশ্চিত করতে yes লিখুন';
  m['profile.deleting'] = 'মোছা হচ্ছে...';
  m['profile.delete_confirm_ok'] = 'মোছা নিশ্চিত করুন';

  return m;
}

export const BN: Record<string, string> = buildBn();
