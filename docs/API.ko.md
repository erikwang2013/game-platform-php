# 인터페이스 문서
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · **한국어** · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

온라인 인터랙티브 문서 (온라인 디버깅 지원):
- C단 비즈니스: http://localhost:8788/apidoc/
- 관리 백오피스: http://localhost:8787/apidoc/
- 비밀번호: admin123

## 1. 규약

### 1.1 기본 URL

| 엔드 | 주소 |
|----|------|
| 관리 백오피스 | `http://localhost:8787` |
| C단 비즈니스 | `http://localhost:8788` |

### 1.2 공통 요청 헤더

```
Content-Type: application/json
API-Version: v1
Authorization: Bearer <token>    (인증이 필요한 인터페이스)
```

### 1.3 통일 응답 형식

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | 의미 |
|------|------|
| 0 | 성공 |
| 400 | 파라미터 오류 |
| 401 | 인증 안 됨 (Token 누락/만료/무효) |
| 403 | 권한 없음 |
| 404 | 리소스가 존재하지 않음 |
| 422 | 검증 실패 |
| 429 | 요청이 너무 빈번함 (레이트 리밋 트리거) |
| 500 | 서버 오류 |

### 1.4 ID 인코딩

모든 인터페이스 요청과 응답의 ID는 Hashids 인코딩 문자열이며, 원본 BIGINT 값이 아닙니다.

```
외부: aB3xK9mW2pQ7rT5v  (hashid 문자열)
내부: 1750123456789      (Snowflake BIGINT)
```

### 1.5 페이지네이션 형식

```
요청: ?page=1&per_page=20

응답: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. C단 인터페이스 (service :8788)

### 2.1 인증

#### POST /api/auth/register — 사용자 등록

```
요청: {
  "username": "player1",
  "password": "123456",
  "email": "player@example.com"     // 선택
}

응답: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": {
    "id": "aB3xK9mW2pQ7rT5v",
    "username": "player1",
    "nickname": "",
    "avatar": ""
  }
}
```

#### POST /api/auth/login — 사용자 로그인

```
요청: {
  "username": "player1",
  "password": "123456"
}

응답: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "...", ... }
}
```

오류: 401 사용자 이름 또는 비밀번호 오류 / 계정이 비활성화됨

#### POST /api/auth/refresh — Token 갱신

```
요청: (Authorization: Bearer <refresh_token>)

응답: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 지갑

#### GET /api/wallet/info — 지갑 정보

```
인증 필요: 예

응답: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/wallet/transactions — 거래 내역

```
인증 필요: 예
파라미터: ?page=1&per_page=20&type=deposit    (type 선택)

응답: {
  "list": [
    {
      "id": "...",
      "type": "deposit",
      "amount": "100.0000",
      "balance_after": "100.5000",
      "remark": "充值到账",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 25,
  "page": 1,
  "per_page": 20
}

type 선택값: deposit / withdraw / exchange_in / exchange_out / game_earn / game_spend
```

### 2.3 충전

#### POST /api/deposit/create — 충전 주문 생성

```
인증 필요: 예

요청: {
  "amount": "10.00",
  "currency": "USD",
  "payment_method_id": "aB3xK..."
}

응답: {
  "order_id": "aB3xK...",
  "order_no": "DEP202605221030000123",
  "amount": "10.00",
  "platform_amount": "10.0000"
}
```

currency 선택값: USD / CNY / EUR

#### GET /api/deposit/orders — 충전 기록

```
인증 필요: 예
파라미터: ?page=1&per_page=20

응답: {
  "list": [
    {
      "id": "...",
      "order_no": "DEP...",
      "amount": "10.00",
      "currency": "USD",
      "platform_amount": "10.0000",
      "status": "pending",
      "paid_at": null,
      "created_at": "2026-05-22 10:25:00"
    }
  ],
  "total": 5,
  "page": 1,
  "per_page": 20
}
```

status 선택값: pending / paid / confirmed / cancelled

### 2.4 환전

#### POST /api/exchange/quote — 견적

```
인증 필요: 예

요청: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "direction": "in",
  "platform_amount": "10.0000"
}

응답: {
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000",
  "spread_pct": "5.00%"
}
```

direction: in=게임 코인 매수 / out=게임 코인 매도

#### POST /api/exchange/buy — 게임 코인 매수

```
인증 필요: 예

요청: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "10.0000"
}

응답: {
  "exchange_id": "aB3xK...",
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000"
}
```

오류: 422 플랫폼 코인 잔액 부족 / 404 게임을 사용할 수 없음

#### POST /api/exchange/sell — 게임 코인 매도

```
인증 필요: 예

요청: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "950.0000"
}

응답: {
  "exchange_id": "aB3xK...",
  "platform_amount": "9.0250",
  "game_amount": "950.0000",
  "spread_fee": "0.4750",
  "rate": "100.00000000"
}
```

오류: 422 게임 코인 잔액 부족

#### GET /api/exchange/records — 환전 기록

```
인증 필요: 예
파라미터: ?page=1&per_page=20

응답: {
  "list": [
    {
      "id": "...",
      "game_id": "...",
      "direction": "in",
      "platform_amount": "10.0000",
      "game_amount": "950.0000",
      "rate": "100.00000000",
      "spread_fee": "50.0000",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 15,
  "page": 1,
  "per_page": 20
}
```

### 2.5 출금

#### POST /api/withdraw/apply — 출금 신청

```
인증 필요: 예

요청: {
  "platform_amount": "50.0000",
  "method": "paypal",
  "account_info": "user@paypal.com"
}

응답: {
  "order_id": "...",
  "order_no": "WTH202605221030000456",
  "status": "approved"
}
```

method 선택값: paypal / bank / crypto

status:
- approved: 자동 승인 (금액 < auto_approve_threshold)
- pending: 심사 대기 (금액 >= auto_approve_threshold)

오류:
- 403 출금 기능이 일시 중지됨 (전역 스위치 꺼짐)
- 400 최소 출금 금액 미만
- 400 일일 출금 한도 초과
- 400 잔액 부족

#### GET /api/withdraw/orders — 출금 기록

```
인증 필요: 예
파라미터: ?page=1&per_page=20

응답: {
  "list": [
    {
      "id": "...",
      "order_no": "WTH...",
      "platform_amount": "50.0000",
      "method": "paypal",
      "status": "pending",
      "review_note": "",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 3,
  "page": 1,
  "per_page": 20
}
```

### 2.6 게임

#### GET /api/game/list — 게임 목록

```
파라미터: ?page=1&per_page=20&keyword=射击&type=self

응답: {
  "list": [
    {
      "id": "aB3xK...",
      "name": "射击大师",
      "slug": "shooter-master",
      "type": "self",
      "description": "一款精彩的射击游戏",
      "cover_image": "https://...",
      "currencies": [
        {
          "id": "...",
          "name": "金币",
          "symbol": "G",
          "exchange_rate": "100.00000000",
          "min_exchange": "1.0000",
          "max_exchange": "10000.0000"
        }
      ]
    }
  ],
  "total": 20,
  "page": 1,
  "per_page": 20
}
```

type 선택값: self / third_party

#### GET /api/game/{hashid} — 게임 상세

```
응답: {
  "id": "...",
  "name": "射击大师",
  "slug": "shooter-master",
  "type": "self",
  "description": "...",
  "cover_image": "https://...",
  "currencies": [
    {
      "id": "...",
      "name": "金币",
      "symbol": "G",
      "exchange_rate": "100.00000000",
      "spread_pct": "5.00",
      "min_exchange": "1.0000",
      "max_exchange": "10000.0000"
    }
  ]
}
```

#### POST /api/game/launch — 게임 시작

```
인증 필요: 예

요청: { "game_id": "aB3xK..." }

응답: {
  "id": "...",
  "name": "射击大师",
  "type": "self",
  "api_endpoint": "https://game.example.com/play"
}
```

### 2.7 OAuth 서드파티 로그인

7개 플랫폼 지원: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/auth/oauth/{provider} — 인증 URL 획득

```
파라미터: provider = google / facebook / apple / twitter / microsoft / linkedin / github

응답: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/auth/oauth/{provider}/callback — OAuth 콜백

```
요청: { "code": "授权码", "state": "防CSRF状态" }

응답: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=신규 등록 사용자 / false=기존 계정 연동

### 2.8 KYC 실명 인증

#### GET /api/user/identity/status — 인증 상태

```
인증 필요: 예

응답: {
  "status": "approved",          // not_submitted / pending / approved / rejected
  "real_name": "J***",
  "id_type": "id_card",
  "review_note": "",
  "submitted_at": "2026-05-22 10:00:00",
  "reviewed_at": "2026-05-23 14:00:00"
}
```

#### POST /api/user/identity/apply — 인증 제출

```
인증 필요: 예

요청: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}

응답: { "message": "KYC submitted successfully" }
```

### 2.9 결제

#### POST /api/payment/callback — 결제 콜백 (공개)

```
요청: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

응답: { "message": "success" }
```

status: success / failed

#### GET /api/payment/methods — 사용 가능한 결제 수단 (공개)

```
응답: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "status": 1 }
  ]
}
```

### 2.10 게임 기록

#### GET /api/game/play-logs — 게임 기록 목록

```
인증 필요: 예
파라미터: ?page=1&per_page=20&game_id=xxx&action=start

응답: {
  "list": [
    {
      "id": "...",
      "game_id": "...",
      "action": "start",
      "game_amount_change": "-10.0000",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 50, "page": 1, "per_page": 20
}
```

#### GET /api/game/play-log/{hashid} — 게임 기록 상세

```
인증 필요: 예
응답: { 완전한 기록, session_id / game_amount_before / after 등 포함 }
```

### 2.12 리더보드

#### GET /api/leaderboard/list — 리더보드 목록

```
응답: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/leaderboard/{hashid} — 리더보드 상세

```
응답: {
  "id": "...",
  "name": "全服累计收入榜",
  "type": "total",
  "rankings": [
    { "rank": 1, "user_id": "...", "score": "50000.0000" }
  ]
}
```

### 2.13 쿠폰

#### GET /api/coupon/available — 수령 가능한 쿠폰

```
인증 필요: 예
응답: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/coupon/claim — 쿠폰 수령

```
인증 필요: 예
요청: { "coupon_id": "hashid" }
응답: { "coupon": { ... } }
```

#### GET /api/coupon/my — 내 쿠폰

```
인증 필요: 예
파라미터: ?status=unused
응답: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 국가 설정

#### GET /api/country/list — 국가 목록

```
응답: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/country/{code} — 국가 상세

```
응답: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 알림

#### GET /api/notification/list — 알림 목록

```
인증 필요: 예
파라미터: ?page=1&per_page=20&is_read=0

응답: {
  "list": [
    { "id": "...", "type": "deposit", "title": "Deposit Received", "is_read": 0, "created_at": "..." }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### GET /api/notification/unread-count — 읽지 않은 수

```
인증 필요: 예
응답: { "count": 3 }
```

#### POST /api/notification/read — 읽음 표시

```
인증 필요: 예
요청: { "id": "hashid" }  // 미전달 = 전체 읽음
```

### 2.17 추천

#### GET /api/referral/my-code — 내 추천 코드

```
인증 필요: 예
응답: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/referral/apply — 추천 코드 사용

```
인증 필요: 예
요청: { "code": "ABC12345" }
응답: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/user/2fa/status — 2FA 상태

```
인증 필요: 예
응답: { "enabled": false }
```

#### POST /api/user/2fa/setup — 2FA 설정

```
인증 필요: 예
응답: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/user/2fa/enable — 2FA 활성화

```
인증 필요: 예
요청: { "code": "123456" }
응답: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/2fa/verify — 2FA 검증 (공개)

```
요청: { "user_id": "hashid", "code": "123456" }
응답: { "valid": true }
```

### 2.19 검색

#### GET /api/search — 전역 검색

```
파라미터: ?q=keyword&type=game&page=1&per_page=20
응답: { "list": [...], "total": 100 }
```

#### GET /api/game/suggest — 검색 제안

```
파라미터: ?q=shoot
응답: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 언어

#### GET /api/language/list — 사용 가능한 언어 목록

```
응답: {
  "current": "en-US",
  "languages": {
    "en-US": { "name": "English", "nativeName": "English", "icon": "us" },
    "zh-CN": { "name": "Chinese (Simplified)", "nativeName": "简体中文", "icon": "cn" },
    "ja-JP": { "name": "Japanese", "nativeName": "日本語", "icon": "jp" },
    "ko-KR": { "name": "Korean", "nativeName": "한국어", "icon": "kr" }
  }
}
```

#### POST /api/language/switch — 언어 전환

```
요청: { "locale": "zh-CN" }
응답: { "locale": "zh-CN" }
```

locale 선택값: en-US / zh-CN / ja-JP / ko-KR

### 2.8 사용자

#### GET /api/user/profile — 개인 정보

```
인증 필요: 예

응답: {
  "id": "...",
  "username": "player1",
  "nickname": "Player One",
  "avatar": "https://...",
  "email": "p***@example.com",
  "phone": "",
  "country": "US",
  "language": "en-US",
  "last_login_at": "2026-05-22 10:00:00",
  "created_at": "2026-05-20 08:00:00"
}
```

#### PUT /api/user/profile — 프로필 편집

```
인증 필요: 예

요청: {
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}

응답: {
  "id": "...",
  "username": "player1",
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}
```

language 선택값: en-US / zh-CN / ja-JP / ko-KR

### 2.9 공지

#### GET /api/announcement/list — 공지 목록

```
응답: {
  "list": [
    {
      "id": "...",
      "title": "系统维护通知",
      "type": "system",
      "created_at": "2026-05-22 09:00:00"
    }
  ]
}
```

#### GET /api/announcement/detail/{hashid} — 공지 상세

```
응답: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

## 3. 관리 백오피스 인터페이스 (admin :8787)

### 3.1 플랫폼 대시보드

#### GET /admin/dashboard/platform

```
인증 필요: 예 (AdminAuth + AdminPermission)

응답: {
  "total_users": 1500,
  "active_users_7d": 320,
  "total_games": 12,
  "pending_withdraws": 5,
  "today_deposits": "500.0000",
  "today_withdraws": "120.0000",
  "total_spread_fee": "1500.5000"
}
```

### 3.2 게임 관리

#### GET /admin/game/list — 게임 목록

```
인증 필요: 예
파라미터: ?page=1&per_page=20&keyword=射击

응답: {
  "list": [
    {
      "id": "...",
      "name": "射击大师",
      "slug": "shooter-master",
      "type": "self",
      "status": 1,
      "sort": 0,
      "currency_count": 2,
      "created_at": "2026-05-20 08:00:00"
    }
  ],
  "total": 12,
  "page": 1,
  "per_page": 20
}
```

#### POST /admin/game/create — 게임 생성

```
인증 필요: 예

요청: {
  "name": "新游戏",
  "slug": "new-game",
  "type": "self",
  "description": "游戏描述",        // 선택
  "cover_image": "https://...",    // 선택
  "api_endpoint": "https://...",   // 선택
  "api_key": "...",                // 선택
  "api_secret": "...",             // 선택
  "status": 1,                     // 선택, 기본 0
  "sort": 0                        // 선택, 기본 0
}

응답: { "id": "aB3xK..." }
```

type 선택값: self / third_party

#### PUT /admin/game/{hashid} — 게임 편집

```
인증 필요: 예

요청: {
  "name": "新名称",
  "status": 1
  // 부분 업데이트 가능, 필드는 create와 동일
}

응답: { "message": "更新成功" }
```

#### DELETE /admin/game/{hashid} — 게임 삭제

```
인증 필요: 예
응답: { "message": "删除成功" }
```

#### POST /admin/game/currency/manage — 코인 관리

```
인증 필요: 예

요청: {
  "game_id": "aB3xK...",
  "currencies": [
    {
      "id": "",                       // 비어있음=신규, 값 있음=업데이트
      "name": "金币",
      "symbol": "G",
      "exchange_rate": "100.00000000",
      "spread_pct": "5.00",
      "min_exchange": "1.0000",
      "max_exchange": "10000.0000"
    }
  ]
}

응답: { "message": "币种更新成功" }
```

### 3.3 출금 관리

#### GET /admin/withdraw/orders — 출금 주문 목록

```
인증 필요: 예
파라미터: ?page=1&per_page=20&status=pending

응답: {
  "list": [
    {
      "id": "...",
      "order_no": "WTH...",
      "user": {
        "id": "...",
        "username": "player1"
      },
      "platform_amount": "500.0000",
      "method": "paypal",
      "status": "pending",
      "reviewer_id": null,
      "review_note": "",
      "reviewed_at": null,
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 5,
  "page": 1,
  "per_page": 20
}
```

#### PUT /admin/withdraw/review — 출금 심사

```
인증 필요: 예

요청: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

응답: { "message": "已通过" }
```

action: approve=승인 / reject=거부 (거부 시 플랫폼 코인 자동 반환)

오류: 422 주문 상태가 심사 대기가 아님

#### PUT /admin/withdraw/switch — 전역 출금 스위치

```
인증 필요: 예

요청: { "enabled": 1 }

응답: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/withdraw/limits/set — 출금 한도 설정

```
인증 필요: 예

요청: {
  "daily_limit": "10000.0000",             // 선택
  "min_amount": "1.0000",                  // 선택
  "auto_approve_threshold": "100.0000"     // 선택
}

응답: {
  "daily_limit": "10000.0000",
  "min_amount": "1.0000",
  "auto_approve_threshold": "100.0000",
  "global_switch": true
}
```

### 3.4 플랫폼 사용자 관리

#### GET /admin/platform/user/list — C단 사용자 목록

```
인증 필요: 예
파라미터: ?page=1&per_page=20&keyword=player&status=1

응답: {
  "list": [
    {
      "id": "...",
      "username": "player1",
      "nickname": "Player One",
      "country": "US",
      "status": 1,
      "last_login_at": "2026-05-22 10:00:00",
      "created_at": "2026-05-20 08:00:00"
    }
  ],
  "total": 1500,
  "page": 1,
  "per_page": 20
}
```

#### GET /admin/platform/user/{hashid} — 사용자 상세

```
인증 필요: 예

응답: {
  "id": "...",
  "username": "player1",
  "nickname": "Player One",
  "email": "p***@example.com",
  "phone": "",
  "country": "US",
  "language": "en-US",
  "status": 1,
  "wallet": {
    "balance": "100.5000",
    "frozen_balance": "0.0000"
  },
  "last_login_at": "2026-05-22 10:00:00",
  "created_at": "2026-05-20 08:00:00"
}
```

#### PUT /admin/platform/user/{hashid} — 사용자 편집/차단

```
인증 필요: 예

요청: {
  "status": 0,         // 0=비활성 1=활성
  "nickname": "..."    // 선택
}

응답: { "message": "更新成功" }
```

### 3.5 결제 관리

#### GET /admin/payment/method/list

```
인증 필요: 예

응답: {
  "list": [
    {
      "id": "...",
      "name": "Stripe",
      "type": "fiat",
      "provider": "stripe",
      "status": 1
    }
  ]
}
```

#### POST /admin/payment/method/toggle — 결제 수단 활성/비활성

```
인증 필요: 예

요청: { "id": "aB3xK...", "status": 0 }

응답: { "message": "已更新" }
```

### 3.6 공지 관리

#### GET /admin/announcement/list

```
인증 필요: 예
파라미터: ?page=1&per_page=20

응답: {
  "list": [
    {
      "id": "...",
      "title": "系统维护通知",
      "type": "system",
      "status": 1,
      "start_at": "2026-05-23 02:00:00",
      "end_at": "2026-05-23 04:00:00",
      "created_at": "2026-05-22 09:00:00"
    }
  ],
  "total": 5,
  "page": 1,
  "per_page": 20
}
```

#### POST /admin/announcement/create — 공지 발행

```
인증 필요: 예

요청: {
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护。",
  "type": "system",           // 선택, 기본 "system"
  "target_lang": "",          // 선택, 비어있음=전 언어
  "status": 1,                // 선택, 기본 1 (0=초안 1=발행)
  "start_at": "2026-05-23 02:00:00",  // 선택
  "end_at": "2026-05-23 04:00:00"     // 선택
}

응답: { "id": "aB3xK..." }
```

### 3.7 KYC 심사

#### GET /admin/identity/list — KYC 목록

```
인증 필요: 예
파라미터: ?page=1&per_page=20&status=pending

응답: {
  "list": [
    {
      "id": "...",
      "user": { "id": "...", "username": "player1" },
      "real_name": "J***",
      "id_type": "id_card",
      "status": "pending",
      "created_at": "2026-05-22 10:00:00"
    }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### PUT /admin/identity/review — KYC 심사

```
인증 필요: 예

요청: { "id": "hashid", "action": "approve", "note": "" }

응답: { "message": "Approved" }
```

action: approve / reject

### 3.8 게임 서버/구역 관리

#### GET /admin/game/server/list — 구역 목록

```
인증 필요: 예
파라미터: ?game_id=hashid

응답: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/game/server/create — 구역 생성

```
인증 필요: 예
요청: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
응답: { "id": "hashid" }
```

#### PUT /admin/game/server/{hashid} — 구역 편집

```
인증 필요: 예
요청: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/game/server/{hashid} — 구역 삭제

```
인증 필요: 예
```

### 3.9 출금 단계별 한도 관리

#### GET /admin/withdraw/limits/list

```
인증 필요: 예

응답: {
  "list": [
    {
      "id": "...",
      "user_level": "verified",
      "single_min": "1.0000",
      "single_max": "5000.0000",
      "daily_limit": "50000.0000",
      "monthly_limit": "200000.0000",
      "fee_pct": "0.50",
      "fee_max": "25.0000",
      "auto_approve_threshold": "500.0000"
    }
  ]
}
```

#### PUT /admin/withdraw/limits/{hashid} — 한도 업데이트

```
인증 필요: 예

요청: { "single_max": "10000.0000", "fee_pct": "0.25" }
// 부분 업데이트 가능
```

### 3.11 게임 분류 관리

#### GET /admin/game/category/list

```
인증 필요: 예
응답: { "list": [{ "id": "...", "name": "动作", "slug": "action", "sort": 1 }] }
```

#### POST /admin/game/category/create

```
인증 필요: 예
요청: { "name": "新分类", "slug": "new-cat", "icon": "star", "sort": 10 }
응답: { "id": "hashid" }
```

#### PUT /admin/game/category/{hashid} — 분류 편집

#### DELETE /admin/game/category/{hashid} — 분류 삭제

#### POST /admin/game/category/assign — 게임 할당

```
인증 필요: 예
요청: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 리더보드 관리

#### GET /admin/leaderboard/list — 리더보드 목록

```
인증 필요: 예
응답: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/leaderboard/create — 리더보드 생성

```
인증 필요: 예
요청: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(선택)" }
```

#### PUT /admin/leaderboard/{hashid} — 리더보드 편집

#### DELETE /admin/leaderboard/{hashid} — 리더보드 삭제

#### POST /admin/leaderboard/{hashid}/refresh — 캐시 갱신

### 3.13 쿠폰 관리

#### GET /admin/coupon/list — 쿠폰 목록

#### POST /admin/coupon/create — 쿠폰 생성

```
인증 필요: 예
요청: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/coupon/{hashid} — 편집 (미수령 시)

#### DELETE /admin/coupon/{hashid} — 삭제

#### GET /admin/coupon/{hashid}/stats — 수령 통계

```
응답: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 국가 설정 관리

#### GET /admin/country/config/list — 국가 설정 목록

#### POST /admin/country/config/create — 국가 설정 생성

```
인증 필요: 예
요청: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/country/config/{hashid} — 국가 설정 편집

### 3.15 데이터 내보내기

#### POST /admin/export/users — C단 사용자 내보내기

```
인증 필요: 예
파라미터(JSON): { "status": 1 }   // 선택 필터

응답: Excel 파일 다운로드 (xlsx)
```

#### POST /admin/export/transactions — 플랫폼 거래 내역 내보내기

```
인증 필요: 예
파라미터(JSON): { "type": "deposit" }   // 선택 필터

응답: Excel 파일 다운로드 (xlsx)
```

### 3.16 데이터 분석 (MySQL 실시간 집계)

모든 엔드포인트는 인증 필요(AdminAuth + AdminPermission), 데이터는 ClickHouse에 의존하지 않고 MySQL에서 실시간 집계합니다.

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/analytics/overview | 플랫폼 총괄 (오늘/최근 7일) |
| GET | /admin/analytics/game-ranking | 게임 랭킹 (?days=7) |
| GET | /admin/analytics/dau-trend | DAU 추세 (?days=30) |
| GET | /admin/analytics/hourly-trend | 시간대별 추세 |
| GET | /admin/analytics/action-distribution | 행동 분포 |
| GET | /admin/analytics/revenue | 수익 분석 |
| GET | /admin/analytics/conversion | 게임 전환율 |
| GET | /admin/analytics/probability | 결합/조건부 확률 |
| GET | /admin/analytics/retention | 리텐션 분석 D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | 전환 퍼널 |
| GET | /admin/analytics/arpu | ARPU/ARPPU 추세 |
| GET | /admin/analytics/economy | 게임 코인 경제 지표 |

### 3.17 티켓 관리

모든 엔드포인트는 인증 필요(AdminAuth + AdminPermission).

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/ticket/list | 티켓 목록 (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | 티켓 상세 (답변 포함) |
| POST | /admin/ticket/{hashid}/reply | 티켓 답변 |
| POST | /admin/ticket/{hashid}/close | 티켓 닫기 |
| POST | /admin/ticket/{hashid}/assign | 처리 담당자 지정 (admin_id) |

## 4. 레이트 리밋 정책

| 인터페이스 | 제한 |
|------|------|
| 기본 | 60회/분/IP |
| POST /api/auth/login | 10회/분 |
| POST /api/auth/register | 5회/분 |

초과 시 429 반환, 응답 헤더 포함:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1716400830
Retry-After: 60
```

## 5. 인증 설명

### C단 (UserAuth)

1. `Authorization: Bearer <token>`에서 Token 추출
2. JWT 서명 검증 (HS256), `sub`(사용자 ID) 파싱
3. `erik_user` 테이블 조회로 사용자 존재 및 status=1 확인
4. `$request->userId` 주입

### 관리 백오피스 (AdminAuth + AdminPermission)

1. AdminAuth: JWT 서명 검증, `sub`(관리자 ID) 파싱, `$request->adminId` 주입
2. AdminPermission: 사용자 역할로 권한 조회, `method.path` 형식의 권한 식별자 매칭
3. `slug=*`인 슈퍼 관리자는 권한 검사 생략

## 6. 오류 코드 빠른 참조

| code | 의미 | 일반적인 시나리오 |
|------|------|---------|
| 0 | 성공 | - |
| 400 | 파라미터 오류 | 요청 형식이 올바르지 않음, 잔액 부족 |
| 401 | 인증 안 됨 | Token 누락/만료/무효, 계정 비활성 |
| 403 | 권한 없음 | 사용자에게 해당 역할 권한 없음, 게임 사용 불가 |
| 404 | 존재하지 않음 | 리소스를 찾을 수 없음 |
| 422 | 검증 실패 | 폼 파라미터가 규칙에 맞지 않음, 주문 상태가 작업 허용 안 함 |
| 429 | 레이트 리밋 | 요청이 너무 빈번함 |
| 500 | 서버 오류 | 예상치 못한 예외 |


## 7. 신규 API (v2.0 생태계 확장)

### 7.1 Provider API — 게임사 콜백 인터페이스

**인증 방식**: HMAC-SHA256 서명 (X-Game-Id + X-Timestamp + X-Signature)
**시간 창**: 5분

#### POST /api/provider/balance — 사용자 잔액 조회

```
요청 헤더:
  X-Game-Id: 1234567890
  X-Timestamp: 1716400830
  X-Signature: abc123...

요청: {
  "user_id": 1234567890,
  "game_id": 9876543210,
  "currency_id": 5555555555
}

응답: {
  "code": 0,
  "message": "success",
  "data": { "balance": "1000.50000000" }
}
```

#### POST /api/provider/bet — 베팅 알림

```
요청: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "bet_type": "straight" }
}

응답: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "990.50000000"
  }
}
```

#### POST /api/provider/settle — 정산 알림

```
요청: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}

응답: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1040.50000000",
    "win_amount": "50.00000000"
  }
}
```

#### POST /api/provider/refund — 환불 알림

```
요청: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "reason": "game_crash"
}

응답: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1000.50000000"
  }
}
```

### 7.2 티켓 API

#### GET /api/ticket/list — 티켓 목록

```
인증 필요: 예
파라미터: ?page=1&per_page=20

응답: {
  "list": [
    {
      "id": "aB3xK...",
      "type": "deposit",
      "subject": "充值未到账",
      "status": "open",
      "priority": 0,
      "reply_count": 1,
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 3, "page": 1, "last_page": 1
}
```

type: deposit / withdraw / game / account / other
status: open / waiting / replied / closed

#### POST /api/ticket/create — 티켓 생성

```
인증 필요: 예
요청: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
응답: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/ticket/{hashid} — 티켓 상세

```
인증 필요: 예
응답: {
  "id": "...", "type": "deposit", "subject": "...",
  "content": "...", "status": "open",
  "replies": [
    { "id": "...", "content": "...", "is_admin": 1, "created_at": "..." }
  ]
}
```

#### POST /api/ticket/{hashid}/reply — 티켓 답변

```
인증 필요: 예
요청: { "content": "已核实，将在24小时内处理" }
응답: { "code": 0, "message": "Reply sent" }
```

### 7.3 이메일 인증 API

#### POST /api/verify/send-email — 이메일 인증 코드 발송

```
인증 필요: 예
요청: { "email": "user@example.com" }
응답: { "code": 0, "message": "Verification code sent" }
오류: 429 60초 후 재시도
```

#### POST /api/verify/confirm-email — 이메일 확인

```
인증 필요: 예
요청: { "code": "123456" }
응답: { "code": 0, "message": "Email verified" }
오류: 422 인증 코드가 유효하지 않거나 만료됨
```

### 7.4 VIP API

#### GET /api/user/vip-status — VIP 상태

```
인증 필요: 예
응답: {
  "level": 2,
  "level_name": "Gold",
  "exp": 300,
  "total_exp": 2800,
  "next_level": { "level": 3, "name": "Platinum", "required_exp": 12500 },
  "benefits": {
    "exchange_discount": "0.05",
    "withdraw_fee_discount": "0.30",
    "rate_bonus": "0.003"
  }
}
```

### 7.5 업적 API

#### GET /api/user/achievements — 업적 목록

```
인증 필요: 예
응답: {
  "achievements": [
    {
      "key": "first_deposit",
      "name": "First Deposit",
      "description": "Make your first deposit",
      "icon": "",
      "points": 20,
      "progress": 1,
      "completed": true
    }
  ]
}
```

### 7.6 관리 백오피스 신규 API

#### GET /admin/ticket/list — 티켓 목록

```
인증 필요: 예
파라미터: ?page=1&limit=20&status=pending&type=deposit

응답: {
  "list": [
    {
      "id": "...", "user_name": "player1",
      "type": "deposit", "subject": "...",
      "status": "open", "reply_count": 0,
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 5, "page": 1, "limit": 20
}
```

#### POST /admin/ticket/{hashid}/reply — 티켓 답변

```
인증 필요: 예
요청: { "content": "已处理" }
응답: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/ticket/{hashid}/close — 티켓 닫기

```
인증 필요: 예
응답: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/ticket/{hashid}/assign — 처리 담당자 지정

```
인증 필요: 예
요청: { "admin_id": 1234567890 }
응답: { "code": 0, "message": "Assigned" }
```

#### GET /admin/analytics/retention — 리텐션 분석

```
인증 필요: 예
파라미터: ?days=30
응답: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/analytics/funnel — 전환 퍼널

```
인증 필요: 예
응답: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — ARPU/ARPPU 추세

```
인증 필요: 예
파라미터: ?days=30
응답: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/analytics/economy — 게임 코인 경제 지표

```
인증 필요: 예
응답: {
  "currencies": [
    {
      "game_name": "Shooter Master",
      "currency": "Gold",
      "total_minted": "500000.0000",
      "total_burned": "320000.0000",
      "circulation": "180000.0000",
      "inflation_rate": "2.3%"
    }
  ]
}
```

## 8. 레이트 리밋 정책 (업데이트)

| 인터페이스 | 제한 |
|------|------|
| 기본 | 60회/분/IP |
| POST /api/auth/login | 10회/분 |
| POST /api/auth/register | 5회/분 |
| POST /api/auth/oauth | 10회/분 |
| POST /api/payment/callback | 30회/분 |
| POST /api/provider/* | 무제한 (HMAC 서명 인증) |

## 9. 인증 설명 (업데이트)

### Provider 인증 (ProviderAuth)

1. 요청 헤더에서 `X-Game-Id`, `X-Timestamp`, `X-Signature` 추출
2. `erik_game` 테이블 조회로 게임 존재 및 status=1 확인
3. 타임스탬프가 5분 창 내인지 검증 (리플레이 방지)
4. `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` 계산하여 서명과 비교
5. `$request->gameId`와 `$request->game` 주입


### 7.7 친구 API

#### GET /api/friend/list — 친구 목록
```
인증 필요: 예
응답: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/friend/requests — 처리 대기 신청
```
인증 필요: 예
응답: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/friend/request — 친구 신청 보내기
```
인증 필요: 예
요청: { "friend_id": "hashid" }
```

#### POST /api/friend/accept — 신청 수락
```
인증 필요: 예
요청: { "request_id": "hashid" }
```

#### POST /api/friend/reject — 신청 거절
```
인증 필요: 예
요청: { "request_id": "hashid" }
```

#### POST /api/friend/remove — 친구 삭제
```
인증 필요: 예
요청: { "friend_id": "hashid" }
```

#### GET /api/friend/search — 사용자 검색
```
인증 필요: 예
파라미터: ?q=username
응답: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 채팅 API

#### GET /api/chat/conversations — 대화 목록
```
인증 필요: 예
응답: {
  "list": [{
    "peer": { "id": "...", "username": "...", "nickname": "...", "avatar": "..." },
    "last_message": "最近一条消息",
    "unread_count": 3,
    "updated_at": "2026-05-22 10:30:00"
  }]
}
```

#### GET /api/chat/messages/{peerHashid} — 메시지 목록
```
인증 필요: 예
파라미터: ?page=1&per_page=50
응답: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
상대가 보낸 읽지 않은 메시지를 자동으로 읽음 표시
```

#### POST /api/chat/send — 메시지 보내기
```
인증 필요: 예
요청: { "to_user_id": "hashid", "content": "Hello!" }
오류: 403 친구가 아니면 전송 불가
```

#### GET /api/chat/unread-total — 읽지 않은 총수
```
인증 필요: 예
응답: { "count": 5 }
```

**WebSocket 연결**: `ws://host:8791`
```
// 인증
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// 메시지 수신
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 Webhook API

#### GET /api/webhook/list — 구독 목록
```
인증 필요: 예
응답: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/webhook/register — 구독 등록
```
인증 필요: 예
요청: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
사용 가능 이벤트: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/webhook/delete — 구독 삭제
```
인증 필요: 예
요청: { "id": "hook_id" }
```

### 7.10 고급 분석 API

#### GET /admin/analytics/retention — 리텐션 분석
```
인증 필요: 예
응답: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/analytics/funnel — 전환 퍼널
```
인증 필요: 예
응답: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — ARPU/ARPPU 추세
```
인증 필요: 예
파라미터: ?days=30
응답: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/analytics/economy — 게임 경제 지표
```
인증 필요: 예
응답: {
  "currencies": [{
    "game_name": "Shooter Master", "currency": "Gold", "symbol": "G",
    "total_minted": "500000.00000000", "total_burned": "320000.00000000",
    "circulation": "180000.00000000", "inflation_rate": "36.00%"
  }]
}
```


### 7.11 토너먼트 API

#### GET /api/tournament/list — 토너먼트 목록
```
파라미터: ?status=active|upcoming|ended&page=1&per_page=20
응답: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/tournament/{hashid} — 토너먼트 상세
```
응답: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/tournament/{hashid}/join — 대회 참가 신청
```
인증 필요: 예
오류: 422 이미 신청함 / 400 시작되었거나 정원 초과 / 503 FeatureFlag 꺼짐
```

### 7.12 쿠폰 조건 (신규)

쿠폰 `conditions` JSON 지원:
- `min_deposit`: 문자열, 최소 누적 충전 금액
- `first_user_only`: bool, 충전 이력이 없는 신규 사용자 전용
- `game_id`: int, 지정 게임 플레이 필요

조건은 `available()` 목록 필터와 `claim()` 수령 시 이중 검증합니다.

### 7.13 다단계 추천 (신규)

추천 커미션에 2차 수익 분배 추가:
- L1: 직접 추천인이 `referrer_bonus` 획득 (설정: referral.referrer_bonus)
- L2: 추천인의 추천인이 `commission = referrer_bonus * level2_rate` 획득 (설정: referral.level2_rate, 기본 5%)
- `erik_referral_commission` 기록 (level/commission_rate/commission_amount)

### 8. 레이트 리밋 정책 (업데이트)

| 인터페이스 | 제한 |
|------|------|
| POST /api/tournament/{id}/join | 10회/분 |
