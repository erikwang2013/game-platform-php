/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Deutsch 表（语言短码 `de`）—— 由 `TranslationsEn.ets` 的 196 键同构生成。
 *
 * **措辞无母语者复核**：只做了机械校验（键集/占位符/结构/编译），译文质量未复核。未覆盖的键回落 `TranslationsEn.ets`（本树查表顺序：当前语言 → EN）。
 *
 * 键与分节布局**不在本文件里手写**：生成时从 `TranslationsZh.ets` 读取，故键集/顺序
 * 与真值表恒等（占位符 `{name}` 逐键保留）。与 `TranslationsEn.ets` 同款：
 * `Record` 空字面量 + 逐键赋值（ArkTS 禁非空对象字面量）。
 */
export function buildDe(): Record<string, string> {
  const m: Record<string, string> = {};

  // ── 通用 ──────────────────────────────────────────────────
  m['common.loading'] = 'Wird geladen...';
  m['common.no_data'] = 'Keine Daten';
  m['common.load_failed'] = 'Laden fehlgeschlagen';
  m['common.retry'] = 'Erneut versuchen';
  m['common.back'] = '← Zurück';
  m['common.confirm'] = 'Bestätigen';
  m['common.cancel'] = 'Abbrechen';
  m['common.save'] = 'Speichern';
  m['common.submitting'] = 'Wird gesendet...';
  m['common.unnamed'] = 'Ohne Namen';
  m['common.no_description'] = 'Keine Beschreibung';
  m['common.unlimited'] = 'Unbegrenzt';
  m['common.currency'] = 'Währung';
  m['common.order_no'] = 'Bestellnummer';
  m['common.balance_after'] = 'Kontostand';
  m['common.deposit'] = 'Einzahlung';
  m['common.withdraw'] = 'Auszahlung';
  m['common.exchange'] = 'Umtausch';
  m['common.rate_line'] = '1 Plattform-Münze = {rate} {symbol}';
  m['common.platform_coin_amount'] = '{amount} Plattform-Münzen';

  // ── 底部导航 / 应用名 ──────────────────────────────────
  m['nav.hall'] = 'Lobby';
  m['nav.wallet'] = 'Wallet';
  m['nav.profile'] = 'Profil';
  m['app.name'] = 'Spieleplattform';

  // ── 登录 / 注册 ────────────────────────────────────────
  m['login.username_placeholder'] = 'Benutzername eingeben';
  m['login.password_placeholder'] = 'Passwort eingeben';
  m['login.password_format_register'] = 'Passwort 8-32 Zeichen, muss Groß- und Kleinbuchstaben sowie Ziffern enthalten';
  m['login.password_min_login'] = 'Passwort mindestens 6 Zeichen';
  m['login.network_error'] = 'Netzwerkfehler, bitte Verbindung prüfen';
  m['login.subtitle_register'] = 'Konto erstellen und losspielen';
  m['login.subtitle_login'] = 'Willkommen zurück';
  m['login.hide_password'] = 'Verbergen';
  m['login.show_password'] = 'Anzeigen';
  m['login.register_button'] = 'Registrieren';
  m['login.login_button'] = 'Anmelden';
  m['login.have_account'] = 'Bereits ein Konto?';
  m['login.no_account'] = 'Noch kein Konto?';
  m['login.go_login'] = 'Anmelden';
  m['login.go_register'] = 'Jetzt registrieren';
  m['login.require_2fa'] = 'Für dieses Konto ist die Zwei-Faktor-Authentifizierung (2FA) aktiviert. Geben Sie den 6-stelligen Code aus Ihrer Authentifizierungs-App oder einen 10-stelligen Backup-Code ein.';
  m['login.tfa_code_placeholder'] = '6-stelliger Code / 10-stelliger Backup-Code';
  m['login.tfa_back'] = 'Zurück';

  // ── 点击验证码弹框（common/CaptchaDialog.ets） ──
  m['captcha.title'] = 'Sicherheitsüberprüfung';
  m['captcha.load_failed'] = 'Captcha konnte nicht geladen werden';
  m['captcha.undo'] = 'Rückgängig';
  m['captcha.refresh'] = 'Neues Bild';
  m['captcha.submitting'] = 'Wird gesendet…';
  m['captcha.hint'] = 'Klicken Sie die Zeichen im Bild der Reihe nach an: {list}';
  m['captcha.clicked'] = 'Geklickt {done}/{total}';

  // ── 游戏大厅 ──────────────────────────────────────────────
  m['hall.title'] = 'Spiele-Lobby';
  m['hall.search_placeholder'] = 'Spiele suchen...';
  m['hall.loading'] = 'Spiele werden geladen...';
  m['hall.no_match'] = 'Keine passenden Spiele';
  m['hall.empty'] = 'Noch keine Spiele';
  m['hall.enter'] = 'Betreten';

  // ── 游戏详情 ──────────────────────────────────────────────
  m['game.missing_id'] = 'Spiel-ID fehlt';
  m['game.launch_failed'] = 'Start fehlgeschlagen';
  m['game.title'] = 'Spieldetails';
  m['game.loading'] = 'Details werden geladen...';
  m['game.supported_currencies'] = 'Unterstützte Währungen';
  m['game.launching'] = 'Wird gestartet...';
  m['game.launch'] = 'Spiel starten';
  m['game.not_found'] = 'Spiel nicht gefunden';
  m['game.launched'] = '{name} gestartet';

  // ── 钱包 ──────────────────────────────────────────────────
  m['wallet.title'] = 'Mein Wallet';
  m['wallet.loading'] = 'Wallet wird geladen...';
  m['wallet.available_balance'] = 'Verfügbares Guthaben';
  m['wallet.total_earned'] = 'Insgesamt verdient';
  m['wallet.total_spent'] = 'Insgesamt ausgegeben';
  m['wallet.transactions'] = 'Transaktionen';
  m['wallet.no_transactions'] = 'Noch keine Transaktionen';
  m['wallet.tx_exchange_in'] = 'Beim Umtausch erhalten';
  m['wallet.tx_exchange_out'] = 'Beim Umtausch ausgegeben';
  m['wallet.tx_game_earn'] = 'Spielgewinn';
  m['wallet.tx_game_spend'] = 'Spielausgaben';
  m['wallet.frozen'] = 'Eingefroren {amount}';
  m['wallet.balance_after'] = 'Kontostand {amount}';

  // ── 充值 ──────────────────────────────────────────────────
  m['deposit.load_methods_failed'] = 'Zahlungsmethoden konnten nicht geladen werden';
  m['deposit.amount_required'] = 'Bitte Einzahlungsbetrag eingeben';
  m['deposit.amount_decimals2'] = 'Maximal 2 Nachkommastellen';
  m['deposit.method_required'] = 'Bitte Zahlungsmethode wählen';
  m['deposit.create_failed'] = 'Einzahlungsauftrag konnte nicht erstellt werden';
  m['deposit.loading_methods'] = 'Zahlungsmethoden werden geladen...';
  m['deposit.order_created'] = 'Auftrag erstellt';
  m['deposit.status_pending'] = 'Zahlung ausstehend';
  m['deposit.amount_label'] = 'Einzahlungsbetrag';
  m['deposit.platform_amount'] = 'Gutgeschriebene Plattform-Münzen';
  m['deposit.expires_at'] = 'Zahlbar bis';
  m['deposit.checkout_url'] = 'Zahlungslink';
  m['deposit.no_checkout_url'] = '(das Gateway hat keinen Zahlungslink zurückgegeben)';
  m['deposit.checkout_hint'] = 'Diese App kann keinen Browser direkt öffnen. Halten Sie den Link oben gedrückt, um ihn zu kopieren, und schließen Sie die Zahlung im Browser ab.';
  m['deposit.again'] = 'Erneut einzahlen';
  m['deposit.no_methods'] = 'Keine Zahlungsmethode verfügbar';
  m['deposit.method_label'] = 'Zahlungsmethode';
  m['deposit.currency_hint'] = 'Die Währung muss mit der gewählten Zahlungsmethode übereinstimmen, sonst lehnt der Server den Auftrag ab';
  m['deposit.integer_amount'] = 'Ganzzahliger Betrag';
  m['deposit.submit'] = 'Einzahlungsauftrag erstellen';
  m['deposit.amount_label_currency'] = 'Betrag ({currency})';
  m['deposit.integer_only'] = 'Beträge in {currency} müssen ganzzahlig sein';

  // ── 提现 ──────────────────────────────────────────────────
  m['withdraw.method_bank'] = 'Bankkarte';
  m['withdraw.method_crypto'] = 'Kryptowährung';
  m['withdraw.status_pending'] = 'Prüfung ausstehend';
  m['withdraw.status_reviewing'] = 'In Prüfung';
  m['withdraw.status_manual_review'] = 'Manuelle Prüfung';
  m['withdraw.status_approved'] = 'Genehmigt';
  m['withdraw.status_processing'] = 'In Bearbeitung';
  m['withdraw.status_completed'] = 'Abgeschlossen';
  m['withdraw.status_paid'] = 'Ausgezahlt';
  m['withdraw.status_rejected'] = 'Abgelehnt';
  m['withdraw.status_cancelled'] = 'Storniert';
  m['withdraw.status_failed'] = 'Fehlgeschlagen';
  m['withdraw.amount_required'] = 'Bitte Auszahlungsbetrag eingeben';
  m['withdraw.amount_invalid'] = 'Ungültiges Betragsformat, maximal 4 Nachkommastellen';
  m['withdraw.account_required'] = 'Bitte Empfängerdaten eingeben';
  m['withdraw.failed'] = 'Auszahlungsantrag konnte nicht übermittelt werden';
  m['withdraw.submitted'] = 'Auszahlungsantrag übermittelt';
  m['withdraw.amount_label'] = 'Auszahlungsbetrag';
  m['withdraw.fee'] = 'Gebühr';
  m['withdraw.actual_amount'] = 'Nettobetrag';
  m['withdraw.created_at'] = 'Übermittelt am';
  m['withdraw.again'] = 'Erneut auszahlen';
  m['withdraw.method_label'] = 'Auszahlungsmethode';
  m['withdraw.amount_platform_label'] = 'Auszahlungsbetrag (Plattform-Münzen)';
  m['withdraw.min_amount_hint'] = 'Mindestens 0.0001';
  m['withdraw.account_info'] = 'Empfängerdaten';
  m['withdraw.account_placeholder'] = 'PayPal-E-Mail / Bankkartennummer und Bankname / Wallet-Adresse';
  m['withdraw.submit'] = 'Auszahlungsantrag übermitteln';
  m['withdraw.review_hint'] = 'Auszahlungen erfordern eine manuelle/Risiko-Prüfung; Limits und Gebühren entsprechen den Serverangaben.';
  m['withdraw.available'] = 'Verfügbar {amount}';

  // ── 兑换 ──────────────────────────────────────────────────
  m['exchange.load_games_failed'] = 'Spiele konnten nicht geladen werden';
  m['exchange.amount_required'] = 'Bitte Umtauschmenge eingeben';
  m['exchange.amount_invalid'] = 'Ungültiges Mengenformat, bitte eine Zahl eingeben';
  m['exchange.select_first'] = 'Bitte zuerst Spiel und Währung wählen';
  m['exchange.quote_failed'] = 'Kurs konnte nicht abgerufen werden';
  m['exchange.quote_first'] = 'Bitte zuerst einen Kurs abrufen';
  m['exchange.failed'] = 'Umtausch fehlgeschlagen';
  m['exchange.success'] = 'Umtausch abgeschlossen';
  m['exchange.buy'] = 'Kaufen';
  m['exchange.sell'] = 'Verkaufen';
  m['exchange.id_label'] = 'Vorgangsnummer';
  m['exchange.pay_platform'] = 'Gezahlte Plattform-Münzen';
  m['exchange.sell_game'] = 'Verkaufte Spiel-Münzen';
  m['exchange.receive_game_after_spread'] = 'Erhaltene Spiel-Münzen (nach Abzug des Spreads)';
  m['exchange.receive_platform_after_spread'] = 'Erhaltene Plattform-Münzen (nach Abzug des Spreads)';
  m['exchange.spread'] = 'Spread';
  m['exchange.rate'] = 'Kurs';
  m['exchange.again'] = 'Erneut umtauschen';
  m['exchange.no_games'] = 'Keine Spiele zum Umtausch verfügbar';
  m['exchange.direction'] = 'Richtung';
  m['exchange.buy_full'] = 'Kaufen (Plattform-Münzen → Spiel-Münzen)';
  m['exchange.sell_full'] = 'Verkaufen (Spiel-Münzen → Plattform-Münzen)';
  m['exchange.select_game'] = 'Spiel wählen';
  m['exchange.select_currency'] = 'Währung wählen';
  m['exchange.no_currencies'] = 'Dieses Spiel hat keine umtauschbaren Währungen';
  m['exchange.platform_amount_label'] = 'Anzahl Plattform-Münzen';
  m['exchange.sell_amount_hint'] = 'Beim Verkaufen wird hier die Anzahl der Spiel-Münzen eingegeben; der Server nimmt sie unter demselben Feldnamen entgegen';
  m['exchange.quoting'] = 'Kurs wird abgerufen...';
  m['exchange.quote'] = 'Kurs abrufen';
  m['exchange.game_before_spread'] = 'Umtauschbare Spiel-Münzen (vor Abzug des Spreads)';
  m['exchange.actual_game'] = 'Tatsächlich gutgeschriebene Spiel-Münzen';
  m['exchange.platform_before_spread'] = 'Gegenwert in Plattform-Münzen (vor Abzug des Spreads)';
  m['exchange.actual_platform'] = 'Tatsächlich gutgeschriebene Plattform-Münzen';
  m['exchange.spread_pct'] = 'Spread-Satz';
  m['exchange.quote_hint'] = 'Der Kurs dient nur zur Anzeige; beim Kauf/Verkauf berechnet der Server den Preis neu.';
  m['exchange.confirm_buy'] = 'Kauf bestätigen';
  m['exchange.confirm_sell'] = 'Verkauf bestätigen';
  m['exchange.currency_line'] = '{name} ({symbol})';
  m['exchange.game_amount_label'] = 'Anzahl Spiel-Münzen ({symbol})';

  // ── 个人中心 ──────────────────────────────────────────────
  m['profile.nickname_placeholder'] = 'Bitte Spitznamen eingeben';
  m['profile.saved'] = 'Gespeichert';
  m['profile.save_failed'] = 'Speichern fehlgeschlagen';
  m['profile.logout_confirm_title'] = 'Abmeldung bestätigen';
  m['profile.logout_confirm_message'] = 'Möchten Sie sich wirklich abmelden?';
  m['profile.logout_confirm_ok'] = 'Abmelden';
  m['profile.logged_out'] = 'Abgemeldet';
  m['profile.delete_failed'] = 'Konto konnte nicht gelöscht werden';
  m['profile.delete_unverified'] = 'Das Löschergebnis konnte nicht bestätigt werden; bitte erneut anmelden und prüfen';
  m['profile.delete_pending'] = 'Der Löschantrag wurde übermittelt, aber das Profil ist weiterhin lesbar; bitte aktualisieren und prüfen';
  m['profile.deleted'] = 'Konto gelöscht';
  m['profile.title'] = 'Profil';
  m['profile.loading'] = 'Profil wird geladen...';
  m['profile.field_username'] = 'Benutzername';
  m['profile.field_email'] = 'E-Mail';
  m['profile.field_phone'] = 'Telefon';
  m['profile.field_country'] = 'Land';
  m['profile.field_language'] = 'Sprache';
  m['profile.ui_language'] = 'Anzeigesprache';
  m['profile.field_created_at'] = 'Registriert am';
  m['profile.field_last_login'] = 'Letzte Anmeldung';
  m['profile.edit_nickname'] = 'Spitznamen ändern';
  m['profile.saving'] = 'Wird gespeichert...';
  m['profile.logout'] = 'Abmelden';
  m['profile.delete_account'] = 'Konto löschen';
  m['profile.delete_warning'] = 'Nach dem Löschen kann sich dieses Konto nicht mehr anmelden und das Profil wird anonymisiert. Ein verbleibendes Guthaben muss zuvor auf null ausgezahlt werden, sonst lehnt der Server die Löschung ab.';
  m['profile.delete_password_placeholder'] = 'Bitte aktuelles Passwort eingeben';
  m['profile.delete_confirm_placeholder'] = 'Zur Bestätigung yes eingeben';
  m['profile.deleting'] = 'Wird gelöscht...';
  m['profile.delete_confirm_ok'] = 'Löschung bestätigen';

  return m;
}

export const DE: Record<string, string> = buildDe();
