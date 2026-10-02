/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Русский 表（语言短码 `ru`）—— 由 `TranslationsEn.ets` 的 196 键同构生成。
 *
 * **措辞无母语者复核**：只做了机械校验（键集/占位符/结构/编译），译文质量未复核。未覆盖的键回落 `TranslationsEn.ets`（本树查表顺序：当前语言 → EN）。
 *
 * 键与分节布局**不在本文件里手写**：生成时从 `TranslationsZh.ets` 读取，故键集/顺序
 * 与真值表恒等（占位符 `{name}` 逐键保留）。与 `TranslationsEn.ets` 同款：
 * `Record` 空字面量 + 逐键赋值（ArkTS 禁非空对象字面量）。
 */
export function buildRu(): Record<string, string> {
  const m: Record<string, string> = {};

  // ── 通用 ──────────────────────────────────────────────────
  m['common.loading'] = 'Загрузка...';
  m['common.no_data'] = 'Нет данных';
  m['common.load_failed'] = 'Не удалось загрузить';
  m['common.retry'] = 'Повторить';
  m['common.back'] = '← Назад';
  m['common.confirm'] = 'Подтвердить';
  m['common.cancel'] = 'Отмена';
  m['common.save'] = 'Сохранить';
  m['common.submitting'] = 'Отправка...';
  m['common.unnamed'] = 'Без названия';
  m['common.no_description'] = 'Нет описания';
  m['common.unlimited'] = 'Без ограничений';
  m['common.currency'] = 'Валюта';
  m['common.order_no'] = 'Номер заказа';
  m['common.balance_after'] = 'Баланс';
  m['common.deposit'] = 'Пополнение';
  m['common.withdraw'] = 'Вывод';
  m['common.exchange'] = 'Обмен';
  m['common.rate_line'] = '1 платформенная монета = {rate} {symbol}';
  m['common.platform_coin_amount'] = '{amount} платформенных монет';

  // ── 底部导航 / 应用名 ──────────────────────────────────
  m['nav.hall'] = 'Зал';
  m['nav.wallet'] = 'Кошелёк';
  m['nav.profile'] = 'Профиль';
  m['app.name'] = 'Игровая платформа';

  // ── 登录 / 注册 ────────────────────────────────────────
  m['login.username_placeholder'] = 'Введите имя пользователя';
  m['login.password_placeholder'] = 'Введите пароль';
  m['login.password_format_register'] = 'Пароль 8-32 символа, должен содержать заглавные и строчные буквы и цифры';
  m['login.password_min_login'] = 'Пароль не менее 6 символов';
  m['login.network_error'] = 'Ошибка сети, проверьте подключение';
  m['login.subtitle_register'] = 'Создайте аккаунт и начните играть';
  m['login.subtitle_login'] = 'С возвращением';
  m['login.hide_password'] = 'Скрыть';
  m['login.show_password'] = 'Показать';
  m['login.register_button'] = 'Регистрация';
  m['login.login_button'] = 'Войти';
  m['login.have_account'] = 'Уже есть аккаунт?';
  m['login.no_account'] = 'Нет аккаунта?';
  m['login.go_login'] = 'Войти';
  m['login.go_register'] = 'Зарегистрироваться';
  m['login.require_2fa'] = 'В этом аккаунте включена двухфакторная аутентификация (2FA). Введите 6-значный код из приложения-аутентификатора или 10-значный резервный код.';
  m['login.tfa_code_placeholder'] = '6-значный код / 10-значный резервный код';
  m['login.tfa_back'] = 'Назад';

  // ── 点击验证码弹框（common/CaptchaDialog.ets） ──
  m['captcha.title'] = 'Проверка безопасности';
  m['captcha.load_failed'] = 'Не удалось загрузить капчу';
  m['captcha.undo'] = 'Отменить';
  m['captcha.refresh'] = 'Другое изображение';
  m['captcha.submitting'] = 'Отправка…';
  m['captcha.hint'] = 'Нажимайте символы на изображении по порядку: {list}';
  m['captcha.clicked'] = 'Нажато {done}/{total}';

  // ── 游戏大厅 ──────────────────────────────────────────────
  m['hall.title'] = 'Игровой зал';
  m['hall.search_placeholder'] = 'Поиск игр...';
  m['hall.loading'] = 'Загрузка игр...';
  m['hall.no_match'] = 'Нет подходящих игр';
  m['hall.empty'] = 'Игр пока нет';
  m['hall.enter'] = 'Войти';

  // ── 游戏详情 ──────────────────────────────────────────────
  m['game.missing_id'] = 'Отсутствует ID игры';
  m['game.launch_failed'] = 'Не удалось запустить';
  m['game.title'] = 'Детали игры';
  m['game.loading'] = 'Загрузка деталей...';
  m['game.supported_currencies'] = 'Поддерживаемые валюты';
  m['game.launching'] = 'Запуск...';
  m['game.launch'] = 'Начать игру';
  m['game.not_found'] = 'Игра не найдена';
  m['game.launched'] = 'Запущено: {name}';

  // ── 钱包 ──────────────────────────────────────────────────
  m['wallet.title'] = 'Мой кошелёк';
  m['wallet.loading'] = 'Загрузка кошелька...';
  m['wallet.available_balance'] = 'Доступный баланс';
  m['wallet.total_earned'] = 'Всего заработано';
  m['wallet.total_spent'] = 'Всего потрачено';
  m['wallet.transactions'] = 'История операций';
  m['wallet.no_transactions'] = 'Операций пока нет';
  m['wallet.tx_exchange_in'] = 'Получено при обмене';
  m['wallet.tx_exchange_out'] = 'Списано при обмене';
  m['wallet.tx_game_earn'] = 'Выигрыш в игре';
  m['wallet.tx_game_spend'] = 'Расход в игре';
  m['wallet.frozen'] = 'Заморожено {amount}';
  m['wallet.balance_after'] = 'Баланс {amount}';

  // ── 充值 ──────────────────────────────────────────────────
  m['deposit.load_methods_failed'] = 'Не удалось загрузить способы оплаты';
  m['deposit.amount_required'] = 'Введите сумму пополнения';
  m['deposit.amount_decimals2'] = 'Не более 2 знаков после запятой';
  m['deposit.method_required'] = 'Выберите способ оплаты';
  m['deposit.create_failed'] = 'Не удалось создать заказ на пополнение';
  m['deposit.loading_methods'] = 'Загрузка способов оплаты...';
  m['deposit.order_created'] = 'Заказ создан';
  m['deposit.status_pending'] = 'Ожидает оплаты';
  m['deposit.amount_label'] = 'Сумма пополнения';
  m['deposit.platform_amount'] = 'Зачислено платформенных монет';
  m['deposit.expires_at'] = 'Оплатить до';
  m['deposit.checkout_url'] = 'Платёжная ссылка';
  m['deposit.no_checkout_url'] = '(платёжный шлюз не вернул ссылку)';
  m['deposit.checkout_hint'] = 'Это приложение не может открыть браузер напрямую. Нажмите и удерживайте ссылку выше, чтобы скопировать её, затем завершите оплату в браузере.';
  m['deposit.again'] = 'Пополнить ещё';
  m['deposit.no_methods'] = 'Нет доступных способов оплаты';
  m['deposit.method_label'] = 'Способ оплаты';
  m['deposit.currency_hint'] = 'Валюта должна совпадать с выбранным способом оплаты, иначе сервер отклонит заказ';
  m['deposit.integer_amount'] = 'Целая сумма';
  m['deposit.submit'] = 'Создать заказ на пополнение';
  m['deposit.amount_label_currency'] = 'Сумма ({currency})';
  m['deposit.integer_only'] = 'Сумма в {currency} должна быть целым числом';

  // ── 提现 ──────────────────────────────────────────────────
  m['withdraw.method_bank'] = 'Банковская карта';
  m['withdraw.method_crypto'] = 'Криптовалюта';
  m['withdraw.status_pending'] = 'Ожидает проверки';
  m['withdraw.status_reviewing'] = 'На проверке';
  m['withdraw.status_manual_review'] = 'Ручная проверка';
  m['withdraw.status_approved'] = 'Одобрено';
  m['withdraw.status_processing'] = 'Обработка';
  m['withdraw.status_completed'] = 'Завершено';
  m['withdraw.status_paid'] = 'Выплачено';
  m['withdraw.status_rejected'] = 'Отклонено';
  m['withdraw.status_cancelled'] = 'Отменено';
  m['withdraw.status_failed'] = 'Ошибка';
  m['withdraw.amount_required'] = 'Введите сумму вывода';
  m['withdraw.amount_invalid'] = 'Неверный формат суммы, не более 4 знаков после запятой';
  m['withdraw.account_required'] = 'Укажите данные получателя';
  m['withdraw.failed'] = 'Не удалось отправить заявку на вывод';
  m['withdraw.submitted'] = 'Заявка на вывод отправлена';
  m['withdraw.amount_label'] = 'Сумма вывода';
  m['withdraw.fee'] = 'Комиссия';
  m['withdraw.actual_amount'] = 'К зачислению';
  m['withdraw.created_at'] = 'Время подачи';
  m['withdraw.again'] = 'Вывести ещё';
  m['withdraw.method_label'] = 'Способ вывода';
  m['withdraw.amount_platform_label'] = 'Сумма вывода (платформенные монеты)';
  m['withdraw.min_amount_hint'] = 'Минимум 0.0001';
  m['withdraw.account_info'] = 'Данные получателя';
  m['withdraw.account_placeholder'] = 'Email PayPal / номер банковской карты и банк / адрес кошелька';
  m['withdraw.submit'] = 'Отправить заявку на вывод';
  m['withdraw.review_hint'] = 'Вывод требует ручной проверки/проверки рисков; лимиты и комиссии — как возвращает сервер.';
  m['withdraw.available'] = 'Доступно {amount}';

  // ── 兑换 ──────────────────────────────────────────────────
  m['exchange.load_games_failed'] = 'Не удалось загрузить игры';
  m['exchange.amount_required'] = 'Введите количество для обмена';
  m['exchange.amount_invalid'] = 'Неверный формат количества, введите число';
  m['exchange.select_first'] = 'Сначала выберите игру и валюту';
  m['exchange.quote_failed'] = 'Не удалось получить котировку';
  m['exchange.quote_first'] = 'Сначала запросите котировку';
  m['exchange.failed'] = 'Не удалось обменять';
  m['exchange.success'] = 'Обмен выполнен';
  m['exchange.buy'] = 'Купить';
  m['exchange.sell'] = 'Продать';
  m['exchange.id_label'] = 'Номер операции';
  m['exchange.pay_platform'] = 'Оплачено платформенных монет';
  m['exchange.sell_game'] = 'Продано игровых монет';
  m['exchange.receive_game_after_spread'] = 'Получено игровых монет (за вычетом спреда)';
  m['exchange.receive_platform_after_spread'] = 'Получено платформенных монет (за вычетом спреда)';
  m['exchange.spread'] = 'Спред';
  m['exchange.rate'] = 'Курс';
  m['exchange.again'] = 'Обменять ещё';
  m['exchange.no_games'] = 'Нет игр, доступных для обмена';
  m['exchange.direction'] = 'Направление';
  m['exchange.buy_full'] = 'Купить (платформенные монеты → игровые монеты)';
  m['exchange.sell_full'] = 'Продать (игровые монеты → платформенные монеты)';
  m['exchange.select_game'] = 'Выберите игру';
  m['exchange.select_currency'] = 'Выберите валюту';
  m['exchange.no_currencies'] = 'У этой игры нет валют для обмена';
  m['exchange.platform_amount_label'] = 'Количество платформенных монет';
  m['exchange.sell_amount_hint'] = 'При продаже здесь указывается количество игровых монет; сервер принимает его под тем же именем поля';
  m['exchange.quoting'] = 'Получение котировки...';
  m['exchange.quote'] = 'Котировка';
  m['exchange.game_before_spread'] = 'Обмениваемые игровые монеты (до вычета спреда)';
  m['exchange.actual_game'] = 'Фактически зачисленные игровые монеты';
  m['exchange.platform_before_spread'] = 'Эквивалент в платформенных монетах (до вычета спреда)';
  m['exchange.actual_platform'] = 'Фактически зачисленные платформенные монеты';
  m['exchange.spread_pct'] = 'Ставка спреда';
  m['exchange.quote_hint'] = 'Котировка только для отображения; при покупке/продаже сервер пересчитывает цену.';
  m['exchange.confirm_buy'] = 'Подтвердить покупку';
  m['exchange.confirm_sell'] = 'Подтвердить продажу';
  m['exchange.currency_line'] = '{name} ({symbol})';
  m['exchange.game_amount_label'] = 'Количество игровых монет ({symbol})';

  // ── 个人中心 ──────────────────────────────────────────────
  m['profile.nickname_placeholder'] = 'Введите никнейм';
  m['profile.saved'] = 'Сохранено';
  m['profile.save_failed'] = 'Не удалось сохранить';
  m['profile.logout_confirm_title'] = 'Подтверждение выхода';
  m['profile.logout_confirm_message'] = 'Вы уверены, что хотите выйти?';
  m['profile.logout_confirm_ok'] = 'Выйти';
  m['profile.logged_out'] = 'Вы вышли из аккаунта';
  m['profile.delete_failed'] = 'Не удалось удалить аккаунт';
  m['profile.delete_unverified'] = 'Не удалось подтвердить удаление; войдите снова и проверьте';
  m['profile.delete_pending'] = 'Запрос на удаление отправлен, но профиль всё ещё доступен для чтения; обновите страницу для проверки';
  m['profile.deleted'] = 'Аккаунт удалён';
  m['profile.title'] = 'Профиль';
  m['profile.loading'] = 'Загрузка профиля...';
  m['profile.field_username'] = 'Имя пользователя';
  m['profile.field_email'] = 'Email';
  m['profile.field_phone'] = 'Телефон';
  m['profile.field_country'] = 'Страна';
  m['profile.field_language'] = 'Язык';
  m['profile.ui_language'] = 'Язык интерфейса';
  m['profile.field_created_at'] = 'Дата регистрации';
  m['profile.field_last_login'] = 'Последний вход';
  m['profile.edit_nickname'] = 'Изменить никнейм';
  m['profile.saving'] = 'Сохранение...';
  m['profile.logout'] = 'Выйти';
  m['profile.delete_account'] = 'Удалить аккаунт';
  m['profile.delete_warning'] = 'После удаления вход в этот аккаунт станет невозможен, а профиль будет обезличен. Остаток средств необходимо сначала вывести до нуля, иначе сервер откажет в удалении.';
  m['profile.delete_password_placeholder'] = 'Введите текущий пароль';
  m['profile.delete_confirm_placeholder'] = 'Введите yes для подтверждения удаления';
  m['profile.deleting'] = 'Удаление...';
  m['profile.delete_confirm_ok'] = 'Подтвердить удаление';

  return m;
}

export const RU: Record<string, string> = buildRu();
