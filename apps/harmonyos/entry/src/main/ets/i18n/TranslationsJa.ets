/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 日本語 表（语言短码 `ja`）—— 由 `TranslationsEn.ets` 的 196 键同构生成。
 *
 * **措辞无母语者复核**：只做了机械校验（键集/占位符/结构/编译），译文质量未复核。未覆盖的键回落 `TranslationsEn.ets`（本树查表顺序：当前语言 → EN）。
 *
 * 键与分节布局**不在本文件里手写**：生成时从 `TranslationsZh.ets` 读取，故键集/顺序
 * 与真值表恒等（占位符 `{name}` 逐键保留）。与 `TranslationsEn.ets` 同款：
 * `Record` 空字面量 + 逐键赋值（ArkTS 禁非空对象字面量）。
 */
export function buildJa(): Record<string, string> {
  const m: Record<string, string> = {};

  // ── 通用 ──────────────────────────────────────────────────
  m['common.loading'] = '読み込み中...';
  m['common.no_data'] = 'データがありません';
  m['common.load_failed'] = '読み込みに失敗しました';
  m['common.retry'] = '再試行';
  m['common.back'] = '← 戻る';
  m['common.confirm'] = '確認';
  m['common.cancel'] = 'キャンセル';
  m['common.save'] = '保存';
  m['common.submitting'] = '送信中...';
  m['common.unnamed'] = '名称未設定';
  m['common.no_description'] = '説明なし';
  m['common.unlimited'] = '無制限';
  m['common.currency'] = '通貨';
  m['common.order_no'] = '注文番号';
  m['common.balance_after'] = '残高';
  m['common.deposit'] = 'チャージ';
  m['common.withdraw'] = '出金';
  m['common.exchange'] = '両替';
  m['common.rate_line'] = '1 プラットフォームコイン = {rate} {symbol}';
  m['common.platform_coin_amount'] = '{amount} プラットフォームコイン';

  // ── 底部导航 / 应用名 ──────────────────────────────────
  m['nav.hall'] = 'ホール';
  m['nav.wallet'] = 'ウォレット';
  m['nav.profile'] = 'マイ';
  m['app.name'] = 'ゲームプラットフォーム';

  // ── 登录 / 注册 ────────────────────────────────────────
  m['login.username_placeholder'] = 'ユーザー名を入力';
  m['login.password_placeholder'] = 'パスワードを入力';
  m['login.password_format_register'] = 'パスワードは8〜32文字で、大文字・小文字・数字を含める必要があります';
  m['login.password_min_login'] = 'パスワードは6文字以上';
  m['login.network_error'] = 'ネットワークエラーです。接続を確認してください';
  m['login.subtitle_register'] = 'アカウントを作成してゲームを始めよう';
  m['login.subtitle_login'] = 'おかえりなさい';
  m['login.hide_password'] = '非表示';
  m['login.show_password'] = '表示';
  m['login.register_button'] = '登 録';
  m['login.login_button'] = 'ログイン';
  m['login.have_account'] = 'すでにアカウントをお持ちですか？';
  m['login.no_account'] = 'アカウントをお持ちでないですか？';
  m['login.go_login'] = 'ログイン';
  m['login.go_register'] = '今すぐ登録';
  m['login.require_2fa'] = 'このアカウントは二段階認証（2FA）が有効です。認証アプリの6桁コード、または10桁のバックアップコードを入力してください。';
  m['login.tfa_code_placeholder'] = '6桁コード / 10桁バックアップコード';
  m['login.tfa_back'] = '戻る';

  // ── 点击验证码弹框（common/CaptchaDialog.ets） ──
  m['captcha.title'] = 'セキュリティ認証';
  m['captcha.load_failed'] = '認証コードの読み込みに失敗しました';
  m['captcha.undo'] = '取り消す';
  m['captcha.refresh'] = '別の画像';
  m['captcha.submitting'] = '送信中…';
  m['captcha.hint'] = '画像内の文字を順番にクリックしてください: {list}';
  m['captcha.clicked'] = 'クリック済み {done}/{total}';

  // ── 游戏大厅 ──────────────────────────────────────────────
  m['hall.title'] = 'ゲームホール';
  m['hall.search_placeholder'] = 'ゲームを検索...';
  m['hall.loading'] = 'ゲームを読み込み中...';
  m['hall.no_match'] = '一致するゲームがありません';
  m['hall.empty'] = 'ゲームがありません';
  m['hall.enter'] = '入る';

  // ── 游戏详情 ──────────────────────────────────────────────
  m['game.missing_id'] = 'ゲーム ID がありません';
  m['game.launch_failed'] = '起動に失敗しました';
  m['game.title'] = 'ゲーム詳細';
  m['game.loading'] = '詳細を読み込み中...';
  m['game.supported_currencies'] = '対応通貨';
  m['game.launching'] = '起動中...';
  m['game.launch'] = 'ゲームを始める';
  m['game.not_found'] = 'ゲームが見つかりません';
  m['game.launched'] = '{name} を起動しました';

  // ── 钱包 ──────────────────────────────────────────────────
  m['wallet.title'] = 'マイウォレット';
  m['wallet.loading'] = 'ウォレットを読み込み中...';
  m['wallet.available_balance'] = '利用可能残高';
  m['wallet.total_earned'] = '累計獲得';
  m['wallet.total_spent'] = '累計支出';
  m['wallet.transactions'] = '取引履歴';
  m['wallet.no_transactions'] = '取引履歴がありません';
  m['wallet.tx_exchange_in'] = '両替で受取';
  m['wallet.tx_exchange_out'] = '両替で支払';
  m['wallet.tx_game_earn'] = 'ゲーム勝利';
  m['wallet.tx_game_spend'] = 'ゲーム消費';
  m['wallet.frozen'] = '凍結 {amount}';
  m['wallet.balance_after'] = '残高 {amount}';

  // ── 充值 ──────────────────────────────────────────────────
  m['deposit.load_methods_failed'] = '支払い方法の読み込みに失敗しました';
  m['deposit.amount_required'] = 'チャージ金額を入力してください';
  m['deposit.amount_decimals2'] = '小数点以下は最大2桁';
  m['deposit.method_required'] = '支払い方法を選択してください';
  m['deposit.create_failed'] = 'チャージ注文の作成に失敗しました';
  m['deposit.loading_methods'] = '支払い方法を読み込み中...';
  m['deposit.order_created'] = '注文を作成しました';
  m['deposit.status_pending'] = '支払い待ち';
  m['deposit.amount_label'] = 'チャージ金額';
  m['deposit.platform_amount'] = '付与プラットフォームコイン';
  m['deposit.expires_at'] = '支払い期限';
  m['deposit.checkout_url'] = '支払いリンク';
  m['deposit.no_checkout_url'] = '（ゲートウェイが支払いリンクを返しませんでした）';
  m['deposit.checkout_hint'] = 'このアプリからブラウザを直接開くことはできません。上のリンクを長押ししてコピーし、ブラウザで支払いを完了してください。';
  m['deposit.again'] = 'もう一度チャージ';
  m['deposit.no_methods'] = '利用可能な支払い方法がありません';
  m['deposit.method_label'] = '支払い方法';
  m['deposit.currency_hint'] = '通貨は選択した支払い方法と一致させる必要があります。一致しない場合、サーバーが注文を拒否します';
  m['deposit.integer_amount'] = '整数金額';
  m['deposit.submit'] = 'チャージ注文を作成';
  m['deposit.amount_label_currency'] = '金額（{currency}）';
  m['deposit.integer_only'] = '{currency} の金額は整数のみです';

  // ── 提现 ──────────────────────────────────────────────────
  m['withdraw.method_bank'] = '銀行カード';
  m['withdraw.method_crypto'] = '暗号資産';
  m['withdraw.status_pending'] = '審査待ち';
  m['withdraw.status_reviewing'] = '審査中';
  m['withdraw.status_manual_review'] = '手動審査';
  m['withdraw.status_approved'] = '承認済み';
  m['withdraw.status_processing'] = '処理中';
  m['withdraw.status_completed'] = '完了';
  m['withdraw.status_paid'] = '送金済み';
  m['withdraw.status_rejected'] = '却下';
  m['withdraw.status_cancelled'] = 'キャンセル済み';
  m['withdraw.status_failed'] = '失敗';
  m['withdraw.amount_required'] = '出金金額を入力してください';
  m['withdraw.amount_invalid'] = '金額の形式が正しくありません。小数点以下は最大4桁です';
  m['withdraw.account_required'] = '受取口座情報を入力してください';
  m['withdraw.failed'] = '出金申請に失敗しました';
  m['withdraw.submitted'] = '出金申請を送信しました';
  m['withdraw.amount_label'] = '出金金額';
  m['withdraw.fee'] = '手数料';
  m['withdraw.actual_amount'] = '実際の受取額';
  m['withdraw.created_at'] = '申請日時';
  m['withdraw.again'] = 'もう一度出金';
  m['withdraw.method_label'] = '出金方法';
  m['withdraw.amount_platform_label'] = '出金金額（プラットフォームコイン）';
  m['withdraw.min_amount_hint'] = '最低 0.0001';
  m['withdraw.account_info'] = '受取口座情報';
  m['withdraw.account_placeholder'] = 'PayPal メール / 銀行カード番号と銀行名 / ウォレットアドレス';
  m['withdraw.submit'] = '出金申請を送信';
  m['withdraw.review_hint'] = '出金には手動/リスク審査が必要です。限度額と手数料はサーバーが返す値に従います。';
  m['withdraw.available'] = '利用可能 {amount}';

  // ── 兑换 ──────────────────────────────────────────────────
  m['exchange.load_games_failed'] = 'ゲームの読み込みに失敗しました';
  m['exchange.amount_required'] = '両替数量を入力してください';
  m['exchange.amount_invalid'] = '数量の形式が正しくありません。数字を入力してください';
  m['exchange.select_first'] = '先にゲームと通貨を選択してください';
  m['exchange.quote_failed'] = '見積もりの取得に失敗しました';
  m['exchange.quote_first'] = '先に見積もりを取得してください';
  m['exchange.failed'] = '両替に失敗しました';
  m['exchange.success'] = '両替が完了しました';
  m['exchange.buy'] = '買う';
  m['exchange.sell'] = '売る';
  m['exchange.id_label'] = '受付番号';
  m['exchange.pay_platform'] = '支払ったプラットフォームコイン';
  m['exchange.sell_game'] = '売却したゲームコイン';
  m['exchange.receive_game_after_spread'] = '受取ゲームコイン（スプレッド差引後）';
  m['exchange.receive_platform_after_spread'] = '受取プラットフォームコイン（スプレッド差引後）';
  m['exchange.spread'] = 'スプレッド';
  m['exchange.rate'] = 'レート';
  m['exchange.again'] = 'もう一度両替';
  m['exchange.no_games'] = '両替可能なゲームがありません';
  m['exchange.direction'] = '方向';
  m['exchange.buy_full'] = '買う（プラットフォームコイン → ゲームコイン）';
  m['exchange.sell_full'] = '売る（ゲームコイン → プラットフォームコイン）';
  m['exchange.select_game'] = 'ゲームを選択';
  m['exchange.select_currency'] = '通貨を選択';
  m['exchange.no_currencies'] = 'このゲームに両替可能な通貨がありません';
  m['exchange.platform_amount_label'] = 'プラットフォームコイン数量';
  m['exchange.sell_amount_hint'] = '売却時はここにゲームコインの数量を入力します。サーバーは同じフィールド名で受け取ります';
  m['exchange.quoting'] = '見積もり中...';
  m['exchange.quote'] = '見積もり';
  m['exchange.game_before_spread'] = '両替ゲームコイン（スプレッド差引前）';
  m['exchange.actual_game'] = '実際の受取ゲームコイン';
  m['exchange.platform_before_spread'] = 'プラットフォームコイン換算（スプレッド差引前）';
  m['exchange.actual_platform'] = '実際の受取プラットフォームコイン';
  m['exchange.spread_pct'] = 'スプレッド率';
  m['exchange.quote_hint'] = '見積もりは表示のみです。売買時にサーバーが再計算します。';
  m['exchange.confirm_buy'] = '買いを確定';
  m['exchange.confirm_sell'] = '売りを確定';
  m['exchange.currency_line'] = '{name}（{symbol}）';
  m['exchange.game_amount_label'] = 'ゲームコイン数量（{symbol}）';

  // ── 个人中心 ──────────────────────────────────────────────
  m['profile.nickname_placeholder'] = 'ニックネームを入力';
  m['profile.saved'] = '保存しました';
  m['profile.save_failed'] = '保存に失敗しました';
  m['profile.logout_confirm_title'] = 'ログアウトの確認';
  m['profile.logout_confirm_message'] = 'ログアウトしてもよろしいですか？';
  m['profile.logout_confirm_ok'] = 'ログアウト';
  m['profile.logged_out'] = 'ログアウトしました';
  m['profile.delete_failed'] = 'アカウントの削除に失敗しました';
  m['profile.delete_unverified'] = '削除結果を確認できませんでした。再度ログインして確認してください';
  m['profile.delete_pending'] = '削除リクエストは送信されましたが、アカウント情報はまだ閲覧できます。更新して確認してください';
  m['profile.deleted'] = 'アカウントを削除しました';
  m['profile.title'] = 'マイページ';
  m['profile.loading'] = 'プロフィールを読み込み中...';
  m['profile.field_username'] = 'ユーザー名';
  m['profile.field_email'] = 'メール';
  m['profile.field_phone'] = '電話';
  m['profile.field_country'] = '国';
  m['profile.field_language'] = '言語';
  m['profile.ui_language'] = '表示言語';
  m['profile.field_created_at'] = '登録日時';
  m['profile.field_last_login'] = '最終ログイン';
  m['profile.edit_nickname'] = 'ニックネームを変更';
  m['profile.saving'] = '保存中...';
  m['profile.logout'] = 'ログアウト';
  m['profile.delete_account'] = 'アカウント削除';
  m['profile.delete_warning'] = '削除後はこのアカウントでログインできなくなり、プロフィールは匿名化されます。残高は事前に出金してゼロにしてください。ゼロでない場合、サーバーは削除を拒否します。';
  m['profile.delete_password_placeholder'] = '現在のパスワードを入力';
  m['profile.delete_confirm_placeholder'] = 'yes と入力して削除を確定';
  m['profile.deleting'] = '削除中...';
  m['profile.delete_confirm_ok'] = '削除を確定';

  return m;
}

export const JA: Record<string, string> = buildJa();
