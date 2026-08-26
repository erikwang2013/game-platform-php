# 기능 설계 문서
<!-- lang-nav -->

Languages: [中文](FEATURE-DESIGN.md) · [English](FEATURE-DESIGN.en.md) · **한국어** · [Русский](FEATURE-DESIGN.ru.md) · [Deutsch](FEATURE-DESIGN.de.md) · [Français](FEATURE-DESIGN.fr.md) · [Español](FEATURE-DESIGN.es.md) · [Português](FEATURE-DESIGN.pt.md) · [हिन्दी](FEATURE-DESIGN.hi.md) · [العربية](FEATURE-DESIGN.ar.md) · [বাংলা](FEATURE-DESIGN.bn.md) · [Bahasa Indonesia](FEATURE-DESIGN.id.md) · [日本語](FEATURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 코인 체계 설계

### 1.1 3단계 코인 모델

```
1단계: 법정화폐 (USD / CNY / EUR / JPY ...)
       ↕ 충전/출금 (환율로 환전)
2단계: 플랫폼 코인 (통일, 정밀도 decimal(18,4))
       ↕ 환전 (환율 + 플랫폼 수수료 차액 포함)
3단계: 게임 코인 (게임별 독립, 독립 환율)
```

### 1.2 플랫폼 코인

- 플랫폼 내 통일된 계산 단위
- 정밀도: `DECIMAL(18,4)`, 최소 단위 0.0001
- 법정화폐 충전으로 획득, 어떤 게임 코인으로도 환전 가능
- 게임 코인도 플랫폼 코인으로 환전 회수 후 법정화폐로 출금 가능
- 플랫폼은 환전 차액을 수익원으로 수취

### 1.3 게임 코인

- 각 게임은 여러 코인 종류 보유 가능 (예: 골드, 다이아몬드, 포인트)
- 각 코인 종류는 플랫폼 코인 대비 환전 환율(`exchange_rate`)을 독립 설정
- 각 코인 종류는 플랫폼 수수료 비율(`spread_pct`)을 독립 설정
- 최소/최대 환전 한도 설정 지원 (`min_exchange` / `max_exchange`)

### 1.4 환전 공식

**게임 코인 매수 (플랫폼 코인 → 게임 코인):**
```
게임 코인 입금 = 플랫폼 코인 수량 × exchange_rate × (1 - spread_pct / 100)
```

**게임 코인 매도 (게임 코인 → 플랫폼 코인):**
```
플랫폼 코인 입금 = 게임 코인 수량 ÷ exchange_rate × (1 - spread_pct / 100)
```

**예시:**
- exchange_rate = 100 (1플랫폼코인 = 100게임코인)
- spread_pct = 5% (플랫폼 5% 차액 수취)
- 사용자가 10 플랫폼 코인 매수: (10 × 100 × 0.95) = 950 게임 코인
- 사용자가 950 게임 코인 매도: (950 ÷ 100 × 0.95) = 9.025 플랫폼 코인
- 플랫폼 수익: 10 - 9.025 = 0.975 플랫폼 코인

## 2. 지갑 설계

### 2.1 플랫폼 코인 지갑 (erik_user_wallet)

사용자 등록 시 자동 생성, 잔액 초기값 0.

| 필드 | 설명 |
|------|------|
| balance | 사용 가능 잔액 (충전/출금/환전 가능) |
| frozen_balance | 동결 잔액 (예약분, 예: 출금 진행 중) |
| total_earned | 누적 수입 |
| total_spent | 누적 지출 |
| version | 낙관적 잠금 버전 번호 (업데이트마다 +1) |

### 2.2 게임 코인 지갑 (erik_user_game_wallet)

사용자+게임+코인 3차원으로 고유. 최초 환전 시 자동 생성.

### 2.3 동시성 안전

낙관적 잠금으로 동시성 문제 방지:

```php
// 업데이트 시 버전 번호 확인
$updated = Wallet::where('id', $wallet->id)
    ->where('version', $wallet->version)
    ->update([
        'balance' => bcadd($wallet->balance, $amount, 4),
        'version' => $wallet->version + 1,
    ]);

// 업데이트 실패 (버전 번호 변경됨) → 재시도, 최대 5회
```

## 3. 출금 시스템 설계

### 3.1 다층 제어

```
1층: 전역 출금 스위치
       ├─ 꺼짐 → 모든 출금 거부, 긴급 리스크 관리용
       └─ 켜짐 → 2층 검사로 진행

2층: 한도 검사
       ├─ 단건 최저 금액 (min_amount)
       ├─ 단건 최고 금액 (max_amount)
       └─ 일일 누적 한도 (daily_limit)

3층: 심사 플로우
       ├─ 금액 < 자동 심사 임계값 → 자동 승인
       └─ 금액 >= 자동 심사 임계값 → 수동 심사 → 승인/거부
```

### 3.2 출금 상태 머신

```
pending (심사 대기)
  ├─→ approved (승인됨) → completed (완료)
  └─→ rejected (거부됨) → 잔액 반환 + 환불 거래 내역
```

### 3.3 관리 백오피스 제어

- **전역 스위치 버튼**: 모든 사용자 출금을 원클릭으로 켜기/끄기
- **심사 큐**: 시간순 정렬된 심사 대기 목록, 승인/거부 버튼
- **한도 설정**: 각 한도 파라미터를 시각적으로 설정

## 4. 충전 설계

### 4.1 충전 플로우

```
1. 사용자가 결제 수단과 금액 선택
2. 플랫폼이 충전 주문 생성 (status=pending, 고유 order_no 생성)
3. 서드파티 결제 페이지로 이동
4. 사용자가 결제 완료
5. 서드파티 콜백으로 플랫폼에 알림 (POST /api/payment/callback)
6. 플랫폼 서명 검증 → 주문 업데이트 (status=confirmed)
7. 플랫폼 코인 입금 → 거래 내역 기록
```

### 4.2 결제 수단

| 유형 | 제공사 | 설명 |
|------|--------|------|
| 법정화폐 | Stripe | 국제 신용카드 결제 |
| 법정화폐 | PayPal | 글로벌 전자 지갑 |
| 법정화폐 | Alipay | 알리페이 (중국 본토) |
| 법정화폐 | WeChat Pay | 위챗페이 (중국 본토) |
| 암호화폐 | USDT-TRC20 | 트론 체인 USDT |

베이직 에디션은 단일 결제 수단(예: Stripe)부터 연동하고, 스탠다드 에디션에서 전체 채널을 확장합니다.

## 5. 게임 통합 설계

### 5.1 자체 개발 게임

자체 개발 게임은 플랫폼에 직접 통합되어 사용자 체계와 지갑을 공유합니다:

- 게임은 내부 API로 사용자 게임 코인 잔액 조회
- 게임 정산은 내부 API로 게임 코인 차감/증가
- 추가 서명 검증 불필요

### 5.2 서드파티 게임

서드파티 게임은 SDK/API로 연동:

```
플랫폼 측:
  1. 사용자가 "게임 입장" 클릭
  2. 플랫폼이 서명 생성 (user_id + timestamp + api_secret → HMAC-SHA256)
  3. 302 리다이렉트 또는 iframe으로 게임 URL 로드 (서명 파라미터 포함)

게임 측:
  4. 서명 검증 → 게임 세션 생성
  5. 잔액 조회: GET /api/game/balance?user_id=...&sign=...
  6. 정산 콜백: POST /api/game/callback {user_id, amount, type, sign}
  7. 플랫폼 서명 검증 → 잔액 업데이트 → 거래 내역 기록 → 결과 반환
```

### 5.3 서명 알고리즘

```
signature = HMAC-SHA256(
    secret = api_secret,
    message = user_id + "|" + timestamp + "|" + amount + "|" + nonce,
)
```

검증 조건:
- 서명 정확
- 타임스탬프가 ±60s 내 (replay attack 방지)
- nonce 미사용 (Redis 기록, 60s 만료)
- 요청 IP가 화이트리스트 내

## 6. 권한 설계

### 6.1 역할 프리셋

| 역할 | 권한 범위 |
|------|---------|
| 슈퍼 관리자 | * (모든 권한) |
| 게임 운영 | 게임 관리, 공지 관리, 대시보드 |
| 재무 심사 | 출금 심사, 결제 관리, 거래 내역 조회 |
| 고객 지원 | C단 사용자 조회, 충전 주문 조회 |

### 6.2 권한 세분화

```
{method}.{path}

예시:
  get.admin/game/list      → 게임 목록 조회
  post.admin/game/create   → 게임 생성
  put.admin/withdraw/review → 출금 심사
  put.admin/withdraw/switch → 출금 스위치 조작 (슈퍼 관리자 전용)
```

## 呼. 스탠다드 에디션 신규 설계

### 8.1 리스크 엔진

4가지 규칙 유형:
- `ip_blacklist` — IP 블랙리스트 매칭, 적중 시 즉시 차단
- `amount_anomaly` — 단건 대액 감지, 임계값 초과 시 경고
- `frequency` — 시간 창 내 작업 빈도 감지
- `velocity` — 단시간 다중 계정 연관 감지

규칙은 priority 내림차순으로 실행되며, 첫 매칭 규칙이 결과를 결정합니다 (block > warn > log).

### 8.2 OAuth 서드파티 로그인

지원 제공사: Google, Facebook, Apple

플로우:
1. 프론트엔드가 `GET /api/auth/oauth/{provider}` 요청으로 인증 URL 획득
2. 사용자가 서드파티로 이동해 인증 완료
3. 콜백 `POST /api/auth/oauth/{provider}/callback`이 인증 코드 전달
4. 백엔드가 기존 연동 조회 → 바로 로그인; 연동 없음 → 자동 등록+연동+지갑 생성

### 8.3 KYC 한도 체계

| 등급 | 획득 방식 | 단건 상한 | 일 한도 | 수수료 |
|------|---------|---------|--------|--------|
| default | 등록 기본 | 1,000 | 10,000 | 1.00% |
| verified | KYC 심사 통과 | 5,000 | 50,000 | 0.50% |
| vip | 운영 부여 | 20,000 | 200,000 | 0.00% |

### 8.4 게임 서버/구역

각 게임은 여러 구역을 설정할 수 있으며(region: global/asia/eu/na), 구역 상태: 점검/정상/인기/신규 서버.

### 8.5 일별 통계 스냅샷

매일 새벽 crontab으로 `ComputeDailyStats::run()` 실행, 5개 지표 계산:
- 사용자 통계 (신규/활성/누적)
- 충전 통계 (건수/총액)
- 출금 통계 (건수/총액)
- 환전 통계 (건수/수수료 총액)
- 게임 통계 (플레이어 수/세션 수)

## 9. 프로덕션급 기능

### 9.1 알림 시스템

알림 유형: system/deposit/withdraw/kyc/coupon/announcement

자동 트리거 시나리오:
- 충전 입금 → NotificationService::send()
- 출금 심사 승인/거부 → 자동 알림
- KYC 심사 승인/거부 → 자동 알림
- 쿠폰 수령 → 자동 알림
- 추천 보상 입금 → 자동 알림

사이트 내 메시지 + 이메일 이중 채널 지원 (이메일은 MAIL_HOST 환경 변수 설정 필요).

### 9.2 추천 리베이트

```
사용자A 추천 코드 생성 → 사용자B에게 공유
사용자B 등록 시 추천 코드 입력 → 양쪽 모두 등록 보상(signup_reward) 획득
사용자B 충전 → A가 충전 커미션(deposit_commission_pct%) 획득
```

### 9.3 2FA 이중 인증

- TOTP 표준 프로토콜 (RFC 6238), Google Authenticator 호환
- 활성화 플로우: 키 획득 → QR 스캔 연동 → TOTP 검증 → 8개 백업 복구 코드 생성
- 로그인 2차 검증: POST /api/2fa/verify
- ±1 시간 창 허용 오차 지원 (30초)

### 9.4 실제 OAuth 연동

| 제공사 | Token 엔드포인트 | 사용자 정보 엔드포인트 |
|--------|----------|------------|
| Google | oauth2.googleapis.com/token | www.googleapis.com/oauth2/v3/userinfo |
| Facebook | graph.facebook.com/v18.0/oauth/access_token | graph.facebook.com/me |
| Apple | appleid.apple.com/auth/token | JWT id_token 디코딩 |

설정은 PlatformConfig 또는 환경 변수로 구성하며, 요청 실패 시 mock 모드로 자동 폴백.

### 9.5 결제 Webhook 서명 검증

- Stripe: HMAC-SHA256 서명 검증 (Stripe-Signature 헤더)
- PayPal: POST로 PayPal 검증 엔드포인트 재조회
- 키 미설정 시 검증 자동 건너뜀 (개발 모드)

### 9.6 WebSocket 실시간 리더보드

- 프로토콜: WebSocket (ws://host:8789)
- 구독: {action: "subscribe", leaderboard_id: 123}
- 푸시: {type: "ranking_update", rankings: [...]}
- ping/pong 하트비트 유지 지원

## 7. 국제화 설계

### 7.1 지원 언어

| 코드 | 이름 | 현지어 | 아이콘 |
|------|------|--------|------|
| en-US | English | English | us |
| zh-CN | Chinese (Simplified) | 简体中文 | cn |
| ja-JP | Japanese | 日本語 | jp |
| ko-KR | Korean | 한국어 | kr |

### 7.2 번역 관리

- 번역은 `group.key` 형식으로 구성 (예: `auth.login_success`)
- 데이터베이스 테이블 `erik_translation`에 저장, Redis 캐시 (TTL 1시간)
- API: `GET /api/language/list`로 사용 가능 언어 조회, `POST /api/language/switch`로 언어 전환
- 프론트엔드는 `X-Language` 요청 헤더 또는 `Accept-Language`로 자동 감지
- 번역 누락 시 en-US로 폴백, en-US에도 없으면 원본 key 반환

### 7.3 사용자 언어 선호도

- 사용자 등록 시 브라우저 `Accept-Language`에 따라 자동 설정
- 로그인 후 `PUT /api/user/profile`로 `language` 필드 수정 가능
- 언어 전환 시 사용자 기록 동시 업데이트

## 8. 플랫폼 수익 모델

| 수익원 | 계산 방식 | 설명 |
|---------|---------|------|
| 환전 차액 | 거래별 spread_fee | 매수/매도 양방향 수취 |
| 출금 수수료 | 출금 금액 × fee_pct | 스탠다드 에디션 구현 |
| 게임 수익 배분 | 서드파티 게임 수익 배분 | 계약 조건에 따름 |
| 충전 환차 | 법정화폐→플랫폼 코인 환율 차 | 플랫폼 설정 환율과 시장 환율의 차이 |
