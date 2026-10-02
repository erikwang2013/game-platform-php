/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 한국어 表（语言短码 `ko`）—— 由 `TranslationsEn.ets` 的 196 键同构生成。
 *
 * **措辞无母语者复核**：只做了机械校验（键集/占位符/结构/编译），译文质量未复核。未覆盖的键回落 `TranslationsEn.ets`（本树查表顺序：当前语言 → EN）。
 *
 * 键与分节布局**不在本文件里手写**：生成时从 `TranslationsZh.ets` 读取，故键集/顺序
 * 与真值表恒等（占位符 `{name}` 逐键保留）。与 `TranslationsEn.ets` 同款：
 * `Record` 空字面量 + 逐键赋值（ArkTS 禁非空对象字面量）。
 */
export function buildKo(): Record<string, string> {
  const m: Record<string, string> = {};

  // ── 通用 ──────────────────────────────────────────────────
  m['common.loading'] = '로딩 중...';
  m['common.no_data'] = '데이터가 없습니다';
  m['common.load_failed'] = '불러오기에 실패했습니다';
  m['common.retry'] = '다시 시도';
  m['common.back'] = '← 뒤로';
  m['common.confirm'] = '확인';
  m['common.cancel'] = '취소';
  m['common.save'] = '저장';
  m['common.submitting'] = '제출 중...';
  m['common.unnamed'] = '이름 없음';
  m['common.no_description'] = '설명 없음';
  m['common.unlimited'] = '제한 없음';
  m['common.currency'] = '통화';
  m['common.order_no'] = '주문 번호';
  m['common.balance_after'] = '잔액';
  m['common.deposit'] = '충전';
  m['common.withdraw'] = '출금';
  m['common.exchange'] = '환전';
  m['common.rate_line'] = '1 플랫폼 코인 = {rate} {symbol}';
  m['common.platform_coin_amount'] = '{amount} 플랫폼 코인';

  // ── 底部导航 / 应用名 ──────────────────────────────────
  m['nav.hall'] = '홀';
  m['nav.wallet'] = '지갑';
  m['nav.profile'] = '마이';
  m['app.name'] = '게임 플랫폼';

  // ── 登录 / 注册 ────────────────────────────────────────
  m['login.username_placeholder'] = '사용자 이름을 입력하세요';
  m['login.password_placeholder'] = '비밀번호를 입력하세요';
  m['login.password_format_register'] = '비밀번호는 8~32자이며 대문자, 소문자, 숫자를 포함해야 합니다';
  m['login.password_min_login'] = '비밀번호는 최소 6자입니다';
  m['login.network_error'] = '네트워크 오류입니다. 연결을 확인하세요';
  m['login.subtitle_register'] = '계정을 만들고 게임을 시작하세요';
  m['login.subtitle_login'] = '다시 오신 것을 환영합니다';
  m['login.hide_password'] = '숨기기';
  m['login.show_password'] = '표시';
  m['login.register_button'] = '가 입';
  m['login.login_button'] = '로그인';
  m['login.have_account'] = '이미 계정이 있으신가요?';
  m['login.no_account'] = '계정이 없으신가요?';
  m['login.go_login'] = '로그인';
  m['login.go_register'] = '지금 가입';
  m['login.require_2fa'] = '이 계정은 2단계 인증(2FA)이 활성화되어 있습니다. 인증 앱의 6자리 코드 또는 10자리 백업 코드를 입력하세요.';
  m['login.tfa_code_placeholder'] = '6자리 코드 / 10자리 백업 코드';
  m['login.tfa_back'] = '뒤로';

  // ── 点击验证码弹框（common/CaptchaDialog.ets） ──
  m['captcha.title'] = '보안 인증';
  m['captcha.load_failed'] = '인증 코드를 불러오지 못했습니다';
  m['captcha.undo'] = '실행 취소';
  m['captcha.refresh'] = '다른 이미지';
  m['captcha.submitting'] = '제출 중…';
  m['captcha.hint'] = '이미지의 문자를 순서대로 클릭하세요: {list}';
  m['captcha.clicked'] = '클릭함 {done}/{total}';

  // ── 游戏大厅 ──────────────────────────────────────────────
  m['hall.title'] = '게임 홀';
  m['hall.search_placeholder'] = '게임 검색...';
  m['hall.loading'] = '게임 불러오는 중...';
  m['hall.no_match'] = '일치하는 게임이 없습니다';
  m['hall.empty'] = '게임이 없습니다';
  m['hall.enter'] = '입장';

  // ── 游戏详情 ──────────────────────────────────────────────
  m['game.missing_id'] = '게임 ID가 없습니다';
  m['game.launch_failed'] = '실행에 실패했습니다';
  m['game.title'] = '게임 상세';
  m['game.loading'] = '상세 정보 불러오는 중...';
  m['game.supported_currencies'] = '지원 통화';
  m['game.launching'] = '실행 중...';
  m['game.launch'] = '게임 시작';
  m['game.not_found'] = '게임을 찾을 수 없습니다';
  m['game.launched'] = '{name} 실행됨';

  // ── 钱包 ──────────────────────────────────────────────────
  m['wallet.title'] = '내 지갑';
  m['wallet.loading'] = '지갑 불러오는 중...';
  m['wallet.available_balance'] = '사용 가능 잔액';
  m['wallet.total_earned'] = '누적 획득';
  m['wallet.total_spent'] = '누적 지출';
  m['wallet.transactions'] = '거래 내역';
  m['wallet.no_transactions'] = '거래 내역이 없습니다';
  m['wallet.tx_exchange_in'] = '환전 수령';
  m['wallet.tx_exchange_out'] = '환전 지출';
  m['wallet.tx_game_earn'] = '게임 승리';
  m['wallet.tx_game_spend'] = '게임 소비';
  m['wallet.frozen'] = '동결 {amount}';
  m['wallet.balance_after'] = '잔액 {amount}';

  // ── 充值 ──────────────────────────────────────────────────
  m['deposit.load_methods_failed'] = '결제 수단을 불러오지 못했습니다';
  m['deposit.amount_required'] = '충전 금액을 입력하세요';
  m['deposit.amount_decimals2'] = '소수점 이하 최대 2자리';
  m['deposit.method_required'] = '결제 수단을 선택하세요';
  m['deposit.create_failed'] = '충전 주문 생성에 실패했습니다';
  m['deposit.loading_methods'] = '결제 수단 불러오는 중...';
  m['deposit.order_created'] = '주문이 생성되었습니다';
  m['deposit.status_pending'] = '결제 대기';
  m['deposit.amount_label'] = '충전 금액';
  m['deposit.platform_amount'] = '지급 플랫폼 코인';
  m['deposit.expires_at'] = '결제 기한';
  m['deposit.checkout_url'] = '결제 링크';
  m['deposit.no_checkout_url'] = '(게이트웨이가 결제 링크를 반환하지 않았습니다)';
  m['deposit.checkout_hint'] = '이 앱에서는 브라우저를 직접 열 수 없습니다. 위 링크를 길게 눌러 복사한 뒤 브라우저에서 결제를 완료하세요.';
  m['deposit.again'] = '다시 충전';
  m['deposit.no_methods'] = '사용 가능한 결제 수단이 없습니다';
  m['deposit.method_label'] = '결제 수단';
  m['deposit.currency_hint'] = '통화는 선택한 결제 수단과 일치해야 하며, 그렇지 않으면 서버가 주문을 거부합니다';
  m['deposit.integer_amount'] = '정수 금액';
  m['deposit.submit'] = '충전 주문 생성';
  m['deposit.amount_label_currency'] = '금액({currency})';
  m['deposit.integer_only'] = '{currency} 금액은 정수만 가능합니다';

  // ── 提现 ──────────────────────────────────────────────────
  m['withdraw.method_bank'] = '은행 카드';
  m['withdraw.method_crypto'] = '암호화폐';
  m['withdraw.status_pending'] = '심사 대기';
  m['withdraw.status_reviewing'] = '심사 중';
  m['withdraw.status_manual_review'] = '수동 심사';
  m['withdraw.status_approved'] = '승인됨';
  m['withdraw.status_processing'] = '처리 중';
  m['withdraw.status_completed'] = '완료';
  m['withdraw.status_paid'] = '송금 완료';
  m['withdraw.status_rejected'] = '거부됨';
  m['withdraw.status_cancelled'] = '취소됨';
  m['withdraw.status_failed'] = '실패';
  m['withdraw.amount_required'] = '출금 금액을 입력하세요';
  m['withdraw.amount_invalid'] = '금액 형식이 올바르지 않습니다. 소수점 이하 최대 4자리입니다';
  m['withdraw.account_required'] = '수취 계좌 정보를 입력하세요';
  m['withdraw.failed'] = '출금 신청에 실패했습니다';
  m['withdraw.submitted'] = '출금 신청이 제출되었습니다';
  m['withdraw.amount_label'] = '출금 금액';
  m['withdraw.fee'] = '수수료';
  m['withdraw.actual_amount'] = '실제 수령액';
  m['withdraw.created_at'] = '제출 시각';
  m['withdraw.again'] = '다시 출금';
  m['withdraw.method_label'] = '출금 방법';
  m['withdraw.amount_platform_label'] = '출금 금액(플랫폼 코인)';
  m['withdraw.min_amount_hint'] = '최소 0.0001';
  m['withdraw.account_info'] = '수취 계좌 정보';
  m['withdraw.account_placeholder'] = 'PayPal 이메일 / 은행 카드 번호와 은행명 / 지갑 주소';
  m['withdraw.submit'] = '출금 신청 제출';
  m['withdraw.review_hint'] = '출금은 수동/리스크 심사가 필요하며, 한도와 수수료는 서버가 반환하는 값을 따릅니다.';
  m['withdraw.available'] = '사용 가능 {amount}';

  // ── 兑换 ──────────────────────────────────────────────────
  m['exchange.load_games_failed'] = '게임을 불러오지 못했습니다';
  m['exchange.amount_required'] = '환전 수량을 입력하세요';
  m['exchange.amount_invalid'] = '수량 형식이 올바르지 않습니다. 숫자를 입력하세요';
  m['exchange.select_first'] = '먼저 게임과 통화를 선택하세요';
  m['exchange.quote_failed'] = '견적 조회에 실패했습니다';
  m['exchange.quote_first'] = '먼저 견적을 조회하세요';
  m['exchange.failed'] = '환전에 실패했습니다';
  m['exchange.success'] = '환전이 완료되었습니다';
  m['exchange.buy'] = '매수';
  m['exchange.sell'] = '매도';
  m['exchange.id_label'] = '접수 번호';
  m['exchange.pay_platform'] = '지불한 플랫폼 코인';
  m['exchange.sell_game'] = '매도한 게임 코인';
  m['exchange.receive_game_after_spread'] = '수령 게임 코인(스프레드 차감 후)';
  m['exchange.receive_platform_after_spread'] = '수령 플랫폼 코인(스프레드 차감 후)';
  m['exchange.spread'] = '스프레드';
  m['exchange.rate'] = '환율';
  m['exchange.again'] = '다시 환전';
  m['exchange.no_games'] = '환전 가능한 게임이 없습니다';
  m['exchange.direction'] = '방향';
  m['exchange.buy_full'] = '매수(플랫폼 코인 → 게임 코인)';
  m['exchange.sell_full'] = '매도(게임 코인 → 플랫폼 코인)';
  m['exchange.select_game'] = '게임 선택';
  m['exchange.select_currency'] = '통화 선택';
  m['exchange.no_currencies'] = '이 게임에는 환전 가능한 통화가 없습니다';
  m['exchange.platform_amount_label'] = '플랫폼 코인 수량';
  m['exchange.sell_amount_hint'] = '매도 시에는 여기에 게임 코인 수량을 입력합니다. 서버는 동일한 필드명으로 수신합니다';
  m['exchange.quoting'] = '견적 조회 중...';
  m['exchange.quote'] = '견적 조회';
  m['exchange.game_before_spread'] = '환전 게임 코인(스프레드 차감 전)';
  m['exchange.actual_game'] = '실제 수령 게임 코인';
  m['exchange.platform_before_spread'] = '플랫폼 코인 환산(스프레드 차감 전)';
  m['exchange.actual_platform'] = '실제 수령 플랫폼 코인';
  m['exchange.spread_pct'] = '스프레드율';
  m['exchange.quote_hint'] = '견적은 표시용이며, 매수/매도 시 서버가 다시 가격을 산정합니다.';
  m['exchange.confirm_buy'] = '매수 확정';
  m['exchange.confirm_sell'] = '매도 확정';
  m['exchange.currency_line'] = '{name}({symbol})';
  m['exchange.game_amount_label'] = '게임 코인 수량({symbol})';

  // ── 个人中心 ──────────────────────────────────────────────
  m['profile.nickname_placeholder'] = '닉네임을 입력하세요';
  m['profile.saved'] = '저장되었습니다';
  m['profile.save_failed'] = '저장에 실패했습니다';
  m['profile.logout_confirm_title'] = '로그아웃 확인';
  m['profile.logout_confirm_message'] = '로그아웃하시겠습니까?';
  m['profile.logout_confirm_ok'] = '로그아웃';
  m['profile.logged_out'] = '로그아웃되었습니다';
  m['profile.delete_failed'] = '계정 삭제에 실패했습니다';
  m['profile.delete_unverified'] = '삭제 결과를 확인할 수 없습니다. 다시 로그인하여 확인하세요';
  m['profile.delete_pending'] = '삭제 요청이 제출되었지만 계정 정보를 여전히 조회할 수 있습니다. 새로고침하여 확인하세요';
  m['profile.deleted'] = '계정이 삭제되었습니다';
  m['profile.title'] = '마이페이지';
  m['profile.loading'] = '프로필 불러오는 중...';
  m['profile.field_username'] = '사용자 이름';
  m['profile.field_email'] = '이메일';
  m['profile.field_phone'] = '전화';
  m['profile.field_country'] = '국가';
  m['profile.field_language'] = '언어';
  m['profile.ui_language'] = '표시 언어';
  m['profile.field_created_at'] = '가입 시각';
  m['profile.field_last_login'] = '최근 로그인';
  m['profile.edit_nickname'] = '닉네임 변경';
  m['profile.saving'] = '저장 중...';
  m['profile.logout'] = '로그아웃';
  m['profile.delete_account'] = '계정 삭제';
  m['profile.delete_warning'] = '삭제 후에는 이 계정으로 로그인할 수 없으며 프로필이 익명화됩니다. 잔액은 먼저 출금하여 0으로 만들어야 하며, 그렇지 않으면 서버가 삭제를 거부합니다.';
  m['profile.delete_password_placeholder'] = '현재 비밀번호를 입력하세요';
  m['profile.delete_confirm_placeholder'] = 'yes를 입력하여 삭제 확인';
  m['profile.deleting'] = '삭제 중...';
  m['profile.delete_confirm_ok'] = '삭제 확정';

  return m;
}

export const KO: Record<string, string> = buildKo();
