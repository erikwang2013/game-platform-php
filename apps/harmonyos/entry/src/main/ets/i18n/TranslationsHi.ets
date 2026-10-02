/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * हिन्दी 表（语言短码 `hi`）—— 由 `TranslationsEn.ets` 的 196 键同构生成。
 *
 * **措辞无母语者复核**：只做了机械校验（键集/占位符/结构/编译），译文质量未复核。未覆盖的键回落 `TranslationsEn.ets`（本树查表顺序：当前语言 → EN）。
 *
 * 键与分节布局**不在本文件里手写**：生成时从 `TranslationsZh.ets` 读取，故键集/顺序
 * 与真值表恒等（占位符 `{name}` 逐键保留）。与 `TranslationsEn.ets` 同款：
 * `Record` 空字面量 + 逐键赋值（ArkTS 禁非空对象字面量）。
 */
export function buildHi(): Record<string, string> {
  const m: Record<string, string> = {};

  // ── 通用 ──────────────────────────────────────────────────
  m['common.loading'] = 'लोड हो रहा है...';
  m['common.no_data'] = 'कोई डेटा नहीं';
  m['common.load_failed'] = 'लोड करने में विफल';
  m['common.retry'] = 'पुनः प्रयास करें';
  m['common.back'] = '← वापस';
  m['common.confirm'] = 'पुष्टि करें';
  m['common.cancel'] = 'रद्द करें';
  m['common.save'] = 'सहेजें';
  m['common.submitting'] = 'सबमिट हो रहा है...';
  m['common.unnamed'] = 'बिना नाम';
  m['common.no_description'] = 'कोई विवरण नहीं';
  m['common.unlimited'] = 'असीमित';
  m['common.currency'] = 'मुद्रा';
  m['common.order_no'] = 'ऑर्डर नंबर';
  m['common.balance_after'] = 'शेष राशि';
  m['common.deposit'] = 'जमा';
  m['common.withdraw'] = 'निकासी';
  m['common.exchange'] = 'विनिमय';
  m['common.rate_line'] = '1 प्लेटफ़ॉर्म कॉइन = {rate} {symbol}';
  m['common.platform_coin_amount'] = '{amount} प्लेटफ़ॉर्म कॉइन';

  // ── 底部导航 / 应用名 ──────────────────────────────────
  m['nav.hall'] = 'हॉल';
  m['nav.wallet'] = 'वॉलेट';
  m['nav.profile'] = 'प्रोफ़ाइल';
  m['app.name'] = 'गेमिंग प्लेटफ़ॉर्म';

  // ── 登录 / 注册 ────────────────────────────────────────
  m['login.username_placeholder'] = 'उपयोगकर्ता नाम दर्ज करें';
  m['login.password_placeholder'] = 'पासवर्ड दर्ज करें';
  m['login.password_format_register'] = 'पासवर्ड 8-32 अक्षरों का होना चाहिए और उसमें बड़े अक्षर, छोटे अक्षर और अंक होने चाहिए';
  m['login.password_min_login'] = 'पासवर्ड कम से कम 6 अक्षरों का होना चाहिए';
  m['login.network_error'] = 'नेटवर्क त्रुटि, कृपया अपना कनेक्शन जांचें';
  m['login.subtitle_register'] = 'खाता बनाएं और खेलना शुरू करें';
  m['login.subtitle_login'] = 'वापसी पर स्वागत है';
  m['login.hide_password'] = 'छिपाएं';
  m['login.show_password'] = 'दिखाएं';
  m['login.register_button'] = 'पंजीकरण';
  m['login.login_button'] = 'लॉग इन';
  m['login.have_account'] = 'पहले से खाता है?';
  m['login.no_account'] = 'खाता नहीं है?';
  m['login.go_login'] = 'लॉग इन';
  m['login.go_register'] = 'अभी पंजीकरण करें';
  m['login.require_2fa'] = 'इस खाते पर दो-चरणीय प्रमाणीकरण (2FA) सक्षम है। अपने प्रमाणक ऐप से 6 अंकों का कोड या 10 अंकों का बैकअप कोड दर्ज करें।';
  m['login.tfa_code_placeholder'] = '6 अंकों का कोड / 10 अंकों का बैकअप कोड';
  m['login.tfa_back'] = 'वापस';

  // ── 点击验证码弹框（common/CaptchaDialog.ets） ──
  m['captcha.title'] = 'सुरक्षा सत्यापन';
  m['captcha.load_failed'] = 'कैप्चा लोड करने में विफल';
  m['captcha.undo'] = 'पूर्ववत करें';
  m['captcha.refresh'] = 'नई छवि';
  m['captcha.submitting'] = 'सबमिट हो रहा है…';
  m['captcha.hint'] = 'छवि में अक्षरों पर क्रम से क्लिक करें: {list}';
  m['captcha.clicked'] = 'क्लिक किया {done}/{total}';

  // ── 游戏大厅 ──────────────────────────────────────────────
  m['hall.title'] = 'गेम हॉल';
  m['hall.search_placeholder'] = 'गेम खोजें...';
  m['hall.loading'] = 'गेम लोड हो रहे हैं...';
  m['hall.no_match'] = 'कोई मेल खाता गेम नहीं';
  m['hall.empty'] = 'अभी कोई गेम नहीं';
  m['hall.enter'] = 'प्रवेश करें';

  // ── 游戏详情 ──────────────────────────────────────────────
  m['game.missing_id'] = 'गेम ID अनुपलब्ध है';
  m['game.launch_failed'] = 'लॉन्च करने में विफल';
  m['game.title'] = 'गेम विवरण';
  m['game.loading'] = 'विवरण लोड हो रहा है...';
  m['game.supported_currencies'] = 'समर्थित मुद्राएं';
  m['game.launching'] = 'लॉन्च हो रहा है...';
  m['game.launch'] = 'खेलना शुरू करें';
  m['game.not_found'] = 'गेम नहीं मिला';
  m['game.launched'] = '{name} लॉन्च किया गया';

  // ── 钱包 ──────────────────────────────────────────────────
  m['wallet.title'] = 'मेरा वॉलेट';
  m['wallet.loading'] = 'वॉलेट लोड हो रहा है...';
  m['wallet.available_balance'] = 'उपलब्ध शेष राशि';
  m['wallet.total_earned'] = 'कुल अर्जित';
  m['wallet.total_spent'] = 'कुल खर्च';
  m['wallet.transactions'] = 'लेन-देन';
  m['wallet.no_transactions'] = 'अभी कोई लेन-देन नहीं';
  m['wallet.tx_exchange_in'] = 'विनिमय से प्राप्त';
  m['wallet.tx_exchange_out'] = 'विनिमय में भुगतान';
  m['wallet.tx_game_earn'] = 'गेम जीत';
  m['wallet.tx_game_spend'] = 'गेम खर्च';
  m['wallet.frozen'] = 'फ्रीज़ {amount}';
  m['wallet.balance_after'] = 'शेष राशि {amount}';

  // ── 充值 ──────────────────────────────────────────────────
  m['deposit.load_methods_failed'] = 'भुगतान विधियां लोड करने में विफल';
  m['deposit.amount_required'] = 'जमा राशि दर्ज करें';
  m['deposit.amount_decimals2'] = 'अधिकतम 2 दशमलव स्थान';
  m['deposit.method_required'] = 'भुगतान विधि चुनें';
  m['deposit.create_failed'] = 'जमा ऑर्डर बनाने में विफल';
  m['deposit.loading_methods'] = 'भुगतान विधियां लोड हो रही हैं...';
  m['deposit.order_created'] = 'ऑर्डर बनाया गया';
  m['deposit.status_pending'] = 'भुगतान प्रतीक्षित';
  m['deposit.amount_label'] = 'जमा राशि';
  m['deposit.platform_amount'] = 'जमा किए गए प्लेटफ़ॉर्म कॉइन';
  m['deposit.expires_at'] = 'इससे पहले भुगतान करें';
  m['deposit.checkout_url'] = 'भुगतान लिंक';
  m['deposit.no_checkout_url'] = '(गेटवे ने कोई भुगतान लिंक नहीं लौटाया)';
  m['deposit.checkout_hint'] = 'यह ऐप सीधे ब्राउज़र नहीं खोल सकता। कॉपी करने के लिए ऊपर दिए लिंक को देर तक दबाएं, फिर ब्राउज़र में भुगतान पूरा करें।';
  m['deposit.again'] = 'फिर से जमा करें';
  m['deposit.no_methods'] = 'कोई भुगतान विधि उपलब्ध नहीं';
  m['deposit.method_label'] = 'भुगतान विधि';
  m['deposit.currency_hint'] = 'मुद्रा चुनी गई भुगतान विधि से मेल खानी चाहिए, अन्यथा सर्वर ऑर्डर अस्वीकार कर देगा';
  m['deposit.integer_amount'] = 'पूर्णांक राशि';
  m['deposit.submit'] = 'जमा ऑर्डर बनाएं';
  m['deposit.amount_label_currency'] = 'राशि ({currency})';
  m['deposit.integer_only'] = '{currency} की राशि पूर्णांक होनी चाहिए';

  // ── 提现 ──────────────────────────────────────────────────
  m['withdraw.method_bank'] = 'बैंक कार्ड';
  m['withdraw.method_crypto'] = 'क्रिप्टोकरेंसी';
  m['withdraw.status_pending'] = 'समीक्षा प्रतीक्षित';
  m['withdraw.status_reviewing'] = 'समीक्षा में';
  m['withdraw.status_manual_review'] = 'मैन्युअल समीक्षा';
  m['withdraw.status_approved'] = 'स्वीकृत';
  m['withdraw.status_processing'] = 'प्रक्रिया में';
  m['withdraw.status_completed'] = 'पूर्ण';
  m['withdraw.status_paid'] = 'भुगतान किया गया';
  m['withdraw.status_rejected'] = 'अस्वीकृत';
  m['withdraw.status_cancelled'] = 'रद्द किया गया';
  m['withdraw.status_failed'] = 'विफल';
  m['withdraw.amount_required'] = 'निकासी राशि दर्ज करें';
  m['withdraw.amount_invalid'] = 'राशि का प्रारूप अमान्य, अधिकतम 4 दशमलव स्थान';
  m['withdraw.account_required'] = 'प्राप्तकर्ता खाता विवरण भरें';
  m['withdraw.failed'] = 'निकासी अनुरोध भेजने में विफल';
  m['withdraw.submitted'] = 'निकासी अनुरोध भेजा गया';
  m['withdraw.amount_label'] = 'निकासी राशि';
  m['withdraw.fee'] = 'शुल्क';
  m['withdraw.actual_amount'] = 'शुद्ध राशि';
  m['withdraw.created_at'] = 'सबमिट किया गया';
  m['withdraw.again'] = 'फिर से निकालें';
  m['withdraw.method_label'] = 'निकासी विधि';
  m['withdraw.amount_platform_label'] = 'निकासी राशि (प्लेटफ़ॉर्म कॉइन)';
  m['withdraw.min_amount_hint'] = 'न्यूनतम 0.0001';
  m['withdraw.account_info'] = 'प्राप्तकर्ता खाता विवरण';
  m['withdraw.account_placeholder'] = 'PayPal ईमेल / बैंक कार्ड नंबर और बैंक / वॉलेट पता';
  m['withdraw.submit'] = 'निकासी अनुरोध भेजें';
  m['withdraw.review_hint'] = 'निकासी के लिए मैन्युअल/जोखिम समीक्षा आवश्यक है; सीमाएं और शुल्क सर्वर द्वारा लौटाए गए अनुसार हैं।';
  m['withdraw.available'] = 'उपलब्ध {amount}';

  // ── 兑换 ──────────────────────────────────────────────────
  m['exchange.load_games_failed'] = 'गेम लोड करने में विफल';
  m['exchange.amount_required'] = 'विनिमय मात्रा दर्ज करें';
  m['exchange.amount_invalid'] = 'मात्रा प्रारूप अमान्य, कृपया एक संख्या दर्ज करें';
  m['exchange.select_first'] = 'पहले एक गेम और मुद्रा चुनें';
  m['exchange.quote_failed'] = 'कोटेशन प्राप्त करने में विफल';
  m['exchange.quote_first'] = 'पहले कोटेशन प्राप्त करें';
  m['exchange.failed'] = 'विनिमय विफल';
  m['exchange.success'] = 'विनिमय पूर्ण';
  m['exchange.buy'] = 'खरीदें';
  m['exchange.sell'] = 'बेचें';
  m['exchange.id_label'] = 'संदर्भ संख्या';
  m['exchange.pay_platform'] = 'भुगतान किए गए प्लेटफ़ॉर्म कॉइन';
  m['exchange.sell_game'] = 'बेचे गए गेम कॉइन';
  m['exchange.receive_game_after_spread'] = 'प्राप्त गेम कॉइन (स्प्रेड घटाकर)';
  m['exchange.receive_platform_after_spread'] = 'प्राप्त प्लेटफ़ॉर्म कॉइन (स्प्रेड घटाकर)';
  m['exchange.spread'] = 'स्प्रेड';
  m['exchange.rate'] = 'दर';
  m['exchange.again'] = 'फिर से विनिमय करें';
  m['exchange.no_games'] = 'विनिमय के लिए कोई गेम उपलब्ध नहीं';
  m['exchange.direction'] = 'दिशा';
  m['exchange.buy_full'] = 'खरीदें (प्लेटफ़ॉर्म कॉइन → गेम कॉइन)';
  m['exchange.sell_full'] = 'बेचें (गेम कॉइन → प्लेटफ़ॉर्म कॉइन)';
  m['exchange.select_game'] = 'गेम चुनें';
  m['exchange.select_currency'] = 'मुद्रा चुनें';
  m['exchange.no_currencies'] = 'इस गेम में विनिमय योग्य मुद्रा नहीं है';
  m['exchange.platform_amount_label'] = 'प्लेटफ़ॉर्म कॉइन मात्रा';
  m['exchange.sell_amount_hint'] = 'बेचते समय यहां गेम कॉइन की मात्रा दर्ज करें; सर्वर इसे उसी फ़ील्ड नाम से स्वीकार करता है';
  m['exchange.quoting'] = 'कोटेशन प्राप्त हो रहा है...';
  m['exchange.quote'] = 'कोटेशन प्राप्त करें';
  m['exchange.game_before_spread'] = 'विनिमयित गेम कॉइन (स्प्रेड घटाने से पहले)';
  m['exchange.actual_game'] = 'वास्तव में जमा गेम कॉइन';
  m['exchange.platform_before_spread'] = 'प्लेटफ़ॉर्म कॉइन में समतुल्य (स्प्रेड घटाने से पहले)';
  m['exchange.actual_platform'] = 'वास्तव में जमा प्लेटफ़ॉर्म कॉइन';
  m['exchange.spread_pct'] = 'स्प्रेड दर';
  m['exchange.quote_hint'] = 'कोटेशन केवल प्रदर्शन के लिए है; खरीद/बिक्री पर सर्वर मूल्य दोबारा तय करता है।';
  m['exchange.confirm_buy'] = 'खरीद की पुष्टि करें';
  m['exchange.confirm_sell'] = 'बिक्री की पुष्टि करें';
  m['exchange.currency_line'] = '{name} ({symbol})';
  m['exchange.game_amount_label'] = 'गेम कॉइन मात्रा ({symbol})';

  // ── 个人中心 ──────────────────────────────────────────────
  m['profile.nickname_placeholder'] = 'उपनाम दर्ज करें';
  m['profile.saved'] = 'सहेजा गया';
  m['profile.save_failed'] = 'सहेजने में विफल';
  m['profile.logout_confirm_title'] = 'लॉग आउट की पुष्टि';
  m['profile.logout_confirm_message'] = 'क्या आप वाकई लॉग आउट करना चाहते हैं?';
  m['profile.logout_confirm_ok'] = 'लॉग आउट';
  m['profile.logged_out'] = 'लॉग आउट हो गया';
  m['profile.delete_failed'] = 'खाता हटाने में विफल';
  m['profile.delete_unverified'] = 'हटाने का परिणाम पुष्ट नहीं किया जा सका; कृपया दोबारा लॉग इन करके जांचें';
  m['profile.delete_pending'] = 'हटाने का अनुरोध भेजा गया, लेकिन प्रोफ़ाइल अभी भी पढ़ी जा सकती है; पुष्टि के लिए रीफ़्रेश करें';
  m['profile.deleted'] = 'खाता हटा दिया गया';
  m['profile.title'] = 'प्रोफ़ाइल';
  m['profile.loading'] = 'प्रोफ़ाइल लोड हो रही है...';
  m['profile.field_username'] = 'उपयोगकर्ता नाम';
  m['profile.field_email'] = 'ईमेल';
  m['profile.field_phone'] = 'फ़ोन';
  m['profile.field_country'] = 'देश';
  m['profile.field_language'] = 'भाषा';
  m['profile.ui_language'] = 'प्रदर्शन भाषा';
  m['profile.field_created_at'] = 'पंजीकरण दिनांक';
  m['profile.field_last_login'] = 'अंतिम लॉगिन';
  m['profile.edit_nickname'] = 'उपनाम बदलें';
  m['profile.saving'] = 'सहेजा जा रहा है...';
  m['profile.logout'] = 'लॉग आउट';
  m['profile.delete_account'] = 'खाता हटाएं';
  m['profile.delete_warning'] = 'हटाने के बाद इस खाते से दोबारा लॉग इन नहीं किया जा सकेगा और प्रोफ़ाइल अनाम कर दी जाएगी। शेष राशि को पहले निकालकर शून्य करना होगा, अन्यथा सर्वर हटाने से इंकार कर देगा।';
  m['profile.delete_password_placeholder'] = 'अपना वर्तमान पासवर्ड दर्ज करें';
  m['profile.delete_confirm_placeholder'] = 'हटाने की पुष्टि के लिए yes टाइप करें';
  m['profile.deleting'] = 'हटाया जा रहा है...';
  m['profile.delete_confirm_ok'] = 'हटाने की पुष्टि करें';

  return m;
}

export const HI: Record<string, string> = buildHi();
