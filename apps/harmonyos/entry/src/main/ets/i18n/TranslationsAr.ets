/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * العربية 表（语言短码 `ar`）—— 由 `TranslationsEn.ets` 的 196 键同构生成。
 *
 * **措辞无母语者复核**：只做了机械校验（键集/占位符/结构/编译），译文质量未复核。未覆盖的键回落 `TranslationsEn.ets`（本树查表顺序：当前语言 → EN）。本表是 RTL 语言，书写方向由 `LocaleController` 的 `ApplicationContext.setLanguage()` 交给系统。
 *
 * 键与分节布局**不在本文件里手写**：生成时从 `TranslationsZh.ets` 读取，故键集/顺序
 * 与真值表恒等（占位符 `{name}` 逐键保留）。与 `TranslationsEn.ets` 同款：
 * `Record` 空字面量 + 逐键赋值（ArkTS 禁非空对象字面量）。
 */
export function buildAr(): Record<string, string> {
  const m: Record<string, string> = {};

  // ── 通用 ──────────────────────────────────────────────────
  m['common.loading'] = 'جارٍ التحميل...';
  m['common.no_data'] = 'لا توجد بيانات';
  m['common.load_failed'] = 'فشل التحميل';
  m['common.retry'] = 'إعادة المحاولة';
  m['common.back'] = '← رجوع';
  m['common.confirm'] = 'تأكيد';
  m['common.cancel'] = 'إلغاء';
  m['common.save'] = 'حفظ';
  m['common.submitting'] = 'جارٍ الإرسال...';
  m['common.unnamed'] = 'بدون اسم';
  m['common.no_description'] = 'لا يوجد وصف';
  m['common.unlimited'] = 'بلا حدود';
  m['common.currency'] = 'العملة';
  m['common.order_no'] = 'رقم الطلب';
  m['common.balance_after'] = 'الرصيد';
  m['common.deposit'] = 'إيداع';
  m['common.withdraw'] = 'سحب';
  m['common.exchange'] = 'صرف';
  m['common.rate_line'] = '1 عملة المنصة = {rate} {symbol}';
  m['common.platform_coin_amount'] = '{amount} عملة المنصة';

  // ── 底部导航 / 应用名 ──────────────────────────────────
  m['nav.hall'] = 'الصالة';
  m['nav.wallet'] = 'المحفظة';
  m['nav.profile'] = 'الملف الشخصي';
  m['app.name'] = 'منصة الألعاب';

  // ── 登录 / 注册 ────────────────────────────────────────
  m['login.username_placeholder'] = 'أدخل اسم المستخدم';
  m['login.password_placeholder'] = 'أدخل كلمة المرور';
  m['login.password_format_register'] = 'يجب أن تتكون كلمة المرور من 8 إلى 32 حرفًا وأن تحتوي على أحرف كبيرة وصغيرة وأرقام';
  m['login.password_min_login'] = 'يجب أن تتكون كلمة المرور من 6 أحرف على الأقل';
  m['login.network_error'] = 'خطأ في الشبكة، يرجى التحقق من اتصالك';
  m['login.subtitle_register'] = 'أنشئ حسابًا وابدأ اللعب';
  m['login.subtitle_login'] = 'مرحبًا بعودتك';
  m['login.hide_password'] = 'إخفاء';
  m['login.show_password'] = 'إظهار';
  m['login.register_button'] = 'تسجيل';
  m['login.login_button'] = 'تسجيل الدخول';
  m['login.have_account'] = 'لديك حساب بالفعل؟';
  m['login.no_account'] = 'ليس لديك حساب؟';
  m['login.go_login'] = 'تسجيل الدخول';
  m['login.go_register'] = 'سجّل الآن';
  m['login.require_2fa'] = 'هذا الحساب مفعّل عليه التحقق بخطوتين (2FA). أدخل الرمز المكوّن من 6 أرقام من تطبيق المصادقة، أو رمزًا احتياطيًا من 10 خانات.';
  m['login.tfa_code_placeholder'] = 'رمز من 6 أرقام / رمز احتياطي من 10 خانات';
  m['login.tfa_back'] = 'رجوع';

  // ── 点击验证码弹框（common/CaptchaDialog.ets） ──
  m['captcha.title'] = 'التحقق الأمني';
  m['captcha.load_failed'] = 'فشل تحميل رمز التحقق';
  m['captcha.undo'] = 'تراجع';
  m['captcha.refresh'] = 'صورة أخرى';
  m['captcha.submitting'] = 'جارٍ الإرسال…';
  m['captcha.hint'] = 'انقر على الأحرف في الصورة بالترتيب: {list}';
  m['captcha.clicked'] = 'تم النقر {done}/{total}';

  // ── 游戏大厅 ──────────────────────────────────────────────
  m['hall.title'] = 'صالة الألعاب';
  m['hall.search_placeholder'] = 'ابحث عن الألعاب...';
  m['hall.loading'] = 'جارٍ تحميل الألعاب...';
  m['hall.no_match'] = 'لا توجد ألعاب مطابقة';
  m['hall.empty'] = 'لا توجد ألعاب بعد';
  m['hall.enter'] = 'دخول';

  // ── 游戏详情 ──────────────────────────────────────────────
  m['game.missing_id'] = 'معرّف اللعبة مفقود';
  m['game.launch_failed'] = 'فشل التشغيل';
  m['game.title'] = 'تفاصيل اللعبة';
  m['game.loading'] = 'جارٍ تحميل التفاصيل...';
  m['game.supported_currencies'] = 'العملات المدعومة';
  m['game.launching'] = 'جارٍ التشغيل...';
  m['game.launch'] = 'ابدأ اللعب';
  m['game.not_found'] = 'اللعبة غير موجودة';
  m['game.launched'] = 'تم تشغيل {name}';

  // ── 钱包 ──────────────────────────────────────────────────
  m['wallet.title'] = 'محفظتي';
  m['wallet.loading'] = 'جارٍ تحميل المحفظة...';
  m['wallet.available_balance'] = 'الرصيد المتاح';
  m['wallet.total_earned'] = 'إجمالي المكتسب';
  m['wallet.total_spent'] = 'إجمالي المنفق';
  m['wallet.transactions'] = 'سجل المعاملات';
  m['wallet.no_transactions'] = 'لا توجد معاملات بعد';
  m['wallet.tx_exchange_in'] = 'مستلم من الصرف';
  m['wallet.tx_exchange_out'] = 'مدفوع في الصرف';
  m['wallet.tx_game_earn'] = 'أرباح اللعب';
  m['wallet.tx_game_spend'] = 'مصروفات اللعب';
  m['wallet.frozen'] = 'مجمّد {amount}';
  m['wallet.balance_after'] = 'الرصيد {amount}';

  // ── 充值 ──────────────────────────────────────────────────
  m['deposit.load_methods_failed'] = 'فشل تحميل طرق الدفع';
  m['deposit.amount_required'] = 'أدخل مبلغ الإيداع';
  m['deposit.amount_decimals2'] = 'بحد أقصى منزلتين عشريتين';
  m['deposit.method_required'] = 'اختر طريقة الدفع';
  m['deposit.create_failed'] = 'فشل إنشاء طلب الإيداع';
  m['deposit.loading_methods'] = 'جارٍ تحميل طرق الدفع...';
  m['deposit.order_created'] = 'تم إنشاء الطلب';
  m['deposit.status_pending'] = 'في انتظار الدفع';
  m['deposit.amount_label'] = 'مبلغ الإيداع';
  m['deposit.platform_amount'] = 'عملات المنصة المضافة';
  m['deposit.expires_at'] = 'الدفع قبل';
  m['deposit.checkout_url'] = 'رابط الدفع';
  m['deposit.no_checkout_url'] = '(لم تُرجع البوابة أي رابط دفع)';
  m['deposit.checkout_hint'] = 'لا يمكن لهذا التطبيق فتح المتصفح مباشرة. اضغط مطولًا على الرابط أعلاه لنسخه، ثم أكمل الدفع في المتصفح.';
  m['deposit.again'] = 'إيداع مرة أخرى';
  m['deposit.no_methods'] = 'لا توجد طريقة دفع متاحة';
  m['deposit.method_label'] = 'طريقة الدفع';
  m['deposit.currency_hint'] = 'يجب أن تتطابق العملة مع طريقة الدفع المختارة، وإلا سيرفض الخادم الطلب';
  m['deposit.integer_amount'] = 'مبلغ صحيح';
  m['deposit.submit'] = 'إنشاء طلب الإيداع';
  m['deposit.amount_label_currency'] = 'المبلغ ({currency})';
  m['deposit.integer_only'] = 'يجب أن تكون المبالغ بـ {currency} أعدادًا صحيحة';

  // ── 提现 ──────────────────────────────────────────────────
  m['withdraw.method_bank'] = 'بطاقة بنكية';
  m['withdraw.method_crypto'] = 'عملة رقمية';
  m['withdraw.status_pending'] = 'في انتظار المراجعة';
  m['withdraw.status_reviewing'] = 'قيد المراجعة';
  m['withdraw.status_manual_review'] = 'مراجعة يدوية';
  m['withdraw.status_approved'] = 'مقبول';
  m['withdraw.status_processing'] = 'قيد المعالجة';
  m['withdraw.status_completed'] = 'مكتمل';
  m['withdraw.status_paid'] = 'تم الدفع';
  m['withdraw.status_rejected'] = 'مرفوض';
  m['withdraw.status_cancelled'] = 'ملغى';
  m['withdraw.status_failed'] = 'فشل';
  m['withdraw.amount_required'] = 'أدخل مبلغ السحب';
  m['withdraw.amount_invalid'] = 'تنسيق المبلغ غير صالح، بحد أقصى 4 منازل عشرية';
  m['withdraw.account_required'] = 'أدخل بيانات المستلم';
  m['withdraw.failed'] = 'فشل إرسال طلب السحب';
  m['withdraw.submitted'] = 'تم إرسال طلب السحب';
  m['withdraw.amount_label'] = 'مبلغ السحب';
  m['withdraw.fee'] = 'الرسوم';
  m['withdraw.actual_amount'] = 'المبلغ الصافي';
  m['withdraw.created_at'] = 'تاريخ الإرسال';
  m['withdraw.again'] = 'اسحب مرة أخرى';
  m['withdraw.method_label'] = 'طريقة السحب';
  m['withdraw.amount_platform_label'] = 'مبلغ السحب (عملات المنصة)';
  m['withdraw.min_amount_hint'] = 'الحد الأدنى 0.0001';
  m['withdraw.account_info'] = 'بيانات المستلم';
  m['withdraw.account_placeholder'] = 'بريد PayPal / رقم البطاقة البنكية والبنك / عنوان المحفظة';
  m['withdraw.submit'] = 'إرسال طلب السحب';
  m['withdraw.review_hint'] = 'تتطلب عمليات السحب مراجعة يدوية/مراجعة مخاطر؛ الحدود والرسوم وفق ما يرجعه الخادم.';
  m['withdraw.available'] = 'المتاح {amount}';

  // ── 兑换 ──────────────────────────────────────────────────
  m['exchange.load_games_failed'] = 'فشل تحميل الألعاب';
  m['exchange.amount_required'] = 'أدخل كمية الصرف';
  m['exchange.amount_invalid'] = 'تنسيق الكمية غير صالح، أدخل رقمًا';
  m['exchange.select_first'] = 'اختر لعبة وعملة أولًا';
  m['exchange.quote_failed'] = 'فشل الحصول على العرض السعري';
  m['exchange.quote_first'] = 'اطلب عرضًا سعريًا أولًا';
  m['exchange.failed'] = 'فشل الصرف';
  m['exchange.success'] = 'تم الصرف';
  m['exchange.buy'] = 'شراء';
  m['exchange.sell'] = 'بيع';
  m['exchange.id_label'] = 'رقم المرجع';
  m['exchange.pay_platform'] = 'عملات المنصة المدفوعة';
  m['exchange.sell_game'] = 'عملات اللعبة المبيعة';
  m['exchange.receive_game_after_spread'] = 'عملات اللعبة المستلمة (بعد خصم الفارق)';
  m['exchange.receive_platform_after_spread'] = 'عملات المنصة المستلمة (بعد خصم الفارق)';
  m['exchange.spread'] = 'الفارق السعري';
  m['exchange.rate'] = 'السعر';
  m['exchange.again'] = 'اصرف مرة أخرى';
  m['exchange.no_games'] = 'لا توجد ألعاب متاحة للصرف';
  m['exchange.direction'] = 'الاتجاه';
  m['exchange.buy_full'] = 'شراء (عملات المنصة ← عملات اللعبة)';
  m['exchange.sell_full'] = 'بيع (عملات اللعبة ← عملات المنصة)';
  m['exchange.select_game'] = 'اختر لعبة';
  m['exchange.select_currency'] = 'اختر عملة';
  m['exchange.no_currencies'] = 'لا توجد عملات قابلة للصرف في هذه اللعبة';
  m['exchange.platform_amount_label'] = 'كمية عملات المنصة';
  m['exchange.sell_amount_hint'] = 'عند البيع، أدخل هنا كمية عملات اللعبة؛ يقبلها الخادم بنفس اسم الحقل';
  m['exchange.quoting'] = 'جارٍ الحصول على العرض السعري...';
  m['exchange.quote'] = 'الحصول على عرض سعري';
  m['exchange.game_before_spread'] = 'عملات اللعبة المصروفة (قبل خصم الفارق)';
  m['exchange.actual_game'] = 'عملات اللعبة المضافة فعليًا';
  m['exchange.platform_before_spread'] = 'ما يعادل بعملات المنصة (قبل خصم الفارق)';
  m['exchange.actual_platform'] = 'عملات المنصة المضافة فعليًا';
  m['exchange.spread_pct'] = 'نسبة الفارق السعري';
  m['exchange.quote_hint'] = 'العرض السعري للعرض فقط؛ يعيد الخادم التسعير عند الشراء/البيع.';
  m['exchange.confirm_buy'] = 'تأكيد الشراء';
  m['exchange.confirm_sell'] = 'تأكيد البيع';
  m['exchange.currency_line'] = '{name} ({symbol})';
  m['exchange.game_amount_label'] = 'كمية عملات اللعبة ({symbol})';

  // ── 个人中心 ──────────────────────────────────────────────
  m['profile.nickname_placeholder'] = 'أدخل الاسم المستعار';
  m['profile.saved'] = 'تم الحفظ';
  m['profile.save_failed'] = 'فشل الحفظ';
  m['profile.logout_confirm_title'] = 'تأكيد تسجيل الخروج';
  m['profile.logout_confirm_message'] = 'هل أنت متأكد من تسجيل الخروج؟';
  m['profile.logout_confirm_ok'] = 'تسجيل الخروج';
  m['profile.logged_out'] = 'تم تسجيل الخروج';
  m['profile.delete_failed'] = 'فشل حذف الحساب';
  m['profile.delete_unverified'] = 'تعذّر تأكيد نتيجة الحذف؛ يرجى تسجيل الدخول مرة أخرى والتحقق';
  m['profile.delete_pending'] = 'تم إرسال طلب الحذف، لكن الملف الشخصي ما زال قابلًا للقراءة؛ حدّث الصفحة للتأكد';
  m['profile.deleted'] = 'تم حذف الحساب';
  m['profile.title'] = 'الملف الشخصي';
  m['profile.loading'] = 'جارٍ تحميل الملف الشخصي...';
  m['profile.field_username'] = 'اسم المستخدم';
  m['profile.field_email'] = 'البريد الإلكتروني';
  m['profile.field_phone'] = 'الهاتف';
  m['profile.field_country'] = 'الدولة';
  m['profile.field_language'] = 'اللغة';
  m['profile.ui_language'] = 'لغة الواجهة';
  m['profile.field_created_at'] = 'تاريخ التسجيل';
  m['profile.field_last_login'] = 'آخر تسجيل دخول';
  m['profile.edit_nickname'] = 'تغيير الاسم المستعار';
  m['profile.saving'] = 'جارٍ الحفظ...';
  m['profile.logout'] = 'تسجيل الخروج';
  m['profile.delete_account'] = 'حذف الحساب';
  m['profile.delete_warning'] = 'بعد الحذف، لن يتمكن هذا الحساب من تسجيل الدخول وستُجهَّل بيانات الملف الشخصي. يجب سحب الرصيد المتبقي حتى الصفر أولًا، وإلا سيرفض الخادم الحذف.';
  m['profile.delete_password_placeholder'] = 'أدخل كلمة المرور الحالية';
  m['profile.delete_confirm_placeholder'] = 'اكتب yes لتأكيد الحذف';
  m['profile.deleting'] = 'جارٍ الحذف...';
  m['profile.delete_confirm_ok'] = 'تأكيد الحذف';

  return m;
}

export const AR: Record<string, string> = buildAr();
