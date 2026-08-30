# 기능 문서
<!-- lang-nav -->

Languages: [中文](FEATURES.md) · [English](FEATURES.en.md) · **한국어** · [Русский](FEATURES.ru.md) · [Deutsch](FEATURES.de.md) · [Français](FEATURES.fr.md) · [Español](FEATURES.es.md) · [Português](FEATURES.pt.md) · [हिन्दी](FEATURES.hi.md) · [العربية](FEATURES.ar.md) · [বাংলা](FEATURES.bn.md) · [Bahasa Indonesia](FEATURES.id.md) · [日本語](FEATURES.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 기능 총괄

### 베이직 에디션 (MVP) — 완료

| 도메인 | 기능 | 상태 |
|----|------|------|
| 사용자 | 등록/로그인/JWT/캡차 | 완료 |
| 지갑 | 플랫폼 코인 잔액/거래 내역 조회 | 완료 |
| 충전 | 충전 주문 생성 (Stripe 125+ 로컬 결제, Alipay/WeChat Pay APM 포함 / NOWPayments USDT TRC20·ERC20 / Coinbase USDC·BTC·ETH / PayPal 콜백) | 완료 |
| 환전 | 플랫폼 코인⇄게임 코인 (고정 환율+차액) | 완료 |
| 출금 | 신청/조회/전역 스위치/자동 심사/수동 심사 | 완료 |
| 게임 | 백오피스 CRUD/코인 관리/C단 목록/상세/시작 | 완료 |
| 관리 | 게임 관리/출금 심사/사용자 관리/결제 관리/공지 관리 | 완료 |
| 대시보드 | 플랫폼 대시보드 (DAU/거래 내역/수익/랭킹) | 완료 |
| 내보내기 | 사용자/거래 내역/출금 Excel 내보내기 | 완료 |
| 국제화 | 중/영어 전환, 번역 테이블, 언어 감지 미들웨어 | 완료 |
| 프론트엔드 | Flutter PC 관리 백오피스 + C단 사용자 플랫폼 (i18n 포함) | 완료 |

### 스탠다드 에디션 — 완료

| 도메인 | 기능 | 상태 |
|----|------|------|
| 사용자 | OAuth 로그인 (Google/Facebook/Apple/Twitter/Microsoft/LinkedIn/GitHub) | 완료 |
| 결제 | 다중 결제 채널 자동 콜백 (Stripe Alipay/WeChat Pay APM 포함 / PayPal / NOWPayments IPN / Coinbase Webhook) | 완료 |
| 게임 | 구역 관리, 게임 기록 추적 | 완료 |
| 출금 | KYC 단계별 한도 (default/verified/vip) + 수수료 | 완료 |
| KYC | 실명 인증 신청+심사 | 완료 |
| 리스크 관리 | IP 블랙리스트/대액 경보/빈도/속도 감지 | 완료 |
| 통계 | 일별 통계 스냅샷 (사용자/충전/출금/환전/게임) | 완료 |
| 프론트엔드 | Admin: KYC 심사+리스크 로그 / Platform: OAuth+KYC+게임 기록 | 완료 |

### 풀 에디션 — 완료

| 도메인 | 기능 | 상태 |
|----|------|------|
| 게임 로비 | 프리셋 분류 10개, 분류 필터, 게임-분류 연관 | 완료 |
| 리더보드 | 일간/주간/월간/총 누적, Redis 캐시, 다중 지표 | 완료 |
| 쿠폰 | 고정 금액+비율 할인, 기한/수량 제한, 수령/사용 추적 | 완료 |
| 국가 설정 | 8개국 프리셋, 차등 결제/출금 방식, 최소 충전액 | 완료 |
| 통계 | 일별 통계 스냅샷 + 플랫폼 수익 추적 | 완료 |
| 검색 | Elasticsearch 전문 검색 (모델 레이어 통합) | 완료 |

### 프로덕션급 업그레이드 — 완료

| 도메인 | 기능 | 상태 |
|----|------|------|
| OAuth | Google/Facebook/Apple 실제 token 교환 | 완료 |
| 결제 | 콜백 서명 검증 (Stripe Webhook Alipay/WeChat Pay APM 포함, PayPal Webhook, NOWPayments IPN HMAC-SHA512, Coinbase HMAC-SHA256 base64 시크릿) | 완료 |
| 캡차 | poster-php 클릭형 캡차 | 완료 |
| 알림 | 사이트 내 메시지 + 이메일, 충전/출금/KYC/쿠폰 자동 알림 | 완료 |
| 2FA | Google Authenticator TOTP + 백업 복구 코드 | 완료 |
| 추천 | 추천 코드, 등록 보상, 충전 커미션 | 완료 |
| 검색 | ES 검색 API + 게임 제안 + LIKE 폴백 | 완료 |
| 리더보드 | WebSocket 실시간 푸시 (포트 8789) | 완료 |
| CDN | 5개 업체 연동 (Cloudflare R2 / AWS S3 / 알리 OSS / 텐센트 COS / 화웨이 OBS 업로드 + 캐시 제거 + 프리로드) | 완료 |
| CDN 관리 | 관리자가 5개 업체 설정 (자격증명 암호화 저장/활성·비활성/HeadBucket 연결 테스트), service는 DB만 읽음 | 완료 |
| 리포트 | 관리자 데이터 리포트 (요약/일일/CSV 내보내기, Redis 5분 캐시, 기간 ≤90일) | 완료 |
| 플랫폼 통계 | C측 홈 통계 (게임 총수/사용자 총수/오늘 플레이/7일 활성) | 완료 |
| 배포 | Docker Compose 7서비스 + Nginx 리버스 프록시 | 완료 |
| 데이터 | MySQL 실시간 집계 분석 + 결합/조건부 확률 계산 | 완료 |
| HarmonyOS | admin 단 8페이지; C단 `apps/harmonyos/`에 로그인/로비/상세/지갑/개인 구현 (8788 지시) | 부분 완료 (프로젝트 실행 가능, 실기기 IP 변경 필요) |
| API 문서 | hg/apidoc 인터랙티브 문서 | 완료 |
| 원클릭 설치 | 브라우저 설치 마법사: 관리자 생성, 기존 DB 업그레이드, install.lock 재설치 방지 | 완료 |
| 내결함성 | CircuitBreaker 차단 + Retry 재시도 + feature.provider_mock 다운그레이드 스위치 | 완료 |
| 결제 수단 | 백오피스 CRUD + 국가별 표시 + 금액 구간 + 통화 제한 | 완료 |
| CI | push 시 자동 증가 tag + GitHub Release | 완료 |

### 생태계 확장 (v2.0) — 방금 완료

| 도메인 | 기능 | 상태 |
|----|------|------|
| 게임 연동 | GameProvider 추상 레이어 (Self/ThirdParty) + HMAC-SHA256 서명 | 완료 |
| 게임 콜백 | Provider API 게이트웨이 (balance/bet/settle/refund) + ProviderAuth 미들웨어 | 완료 |
| 게임 세션 | Redis 하트비트 + 15분 타임아웃 자동 정산 + GameSessionService | 완료 |
| 티켓 시스템 | C단 생성/답변 + 관리단 처리/할당/닫기, 5가지 티켓 유형 | 완료 |
| 이메일 검증 | 6자리 인증 코드, Redis 10분 만료, 60초 재발송 제한 | 완료 |
| 푸시 알림 | PushService (FCM/APNs/华为推送) + DeviceToken 모델 | 완료 |
| VIP 체계 | 5단계 (일반/실버/골드/플래티넘/다이아몬드) + 경험치 + 자동 승급 | 완료 |
| VIP 혜택 | 환전 할인 2-15%, 출금 수수료 감면 10-100%, 환율 보너스 0.1-1.0% | 완료 |
| 업적 시스템 | 내장 업적 12개; EventConsumer → AchievementService 이벤트 주도 검출과 VIP 경험치 | 완료 |
| 친구 시스템 | 신청/수락/거절/삭제/검색, pending/accepted/blocked 상태 | 완료 |
| 쪽지/채팅 | REST 쪽지 + WebSocket 실시간 메시지 (포트 8790), 친구만 전송 가능 | 완료 |
| 이벤트 버스 | Redis Pub/Sub; emit + EventConsumer가 업적/Webhook 소비 + metrics INCR | 완료 |
| 기능 스위치 | FeatureFlag DB 기반; `inRollout`/`abTest` crc32 버킷으로 `feature.{name}_percent` 읽기 | 완료 |
| 고급 분석 | 리텐션/D1-D30, 전환 퍼널, ARPU/ARPPU, 게임 코인 경제 지표 (MySQL 실시간 집계) | 완료 |
| Webhook | 구독 관리 + Redis Pub/Sub 이벤트 전달, 7가지 이벤트 선택 가능 | 완료 |
| 채팅 | REST 쪽지 + WebSocket 실시간 메시지 (포트 8791), 친구만 전송 가능 | 완료 |
| 토너먼트 | 생성/list/detail/join, FeatureFlag 스위치, 리더보드, 인원 상한 | 완료 |
| 다단계 커미션 | 2차 추천 수익 분배, ReferralCommission 모델, 커미션율 설정 가능 | 완료 |
| 쿠폰 조건 | min_deposit/first_user_only/game_id 3가지 조건 제한 | 완료 |
| SDK 문서 | Provider 연동 문서 (PHP/Go/Python 예시 + 4개 API 엔드포인트) | 완료 |
| 미니게임 | Farm 매치-3 P0 (도메인 엔진 + 4레벨 설계, TypeScript/Vite/Vitest 단위 테스트) | 완료 |

## 2. C단 사용자 기능

### 2.1 사용자 여정

```
등록 → 로그인 → 이메일/휴대폰 검증 → 게임 로비 둘러보기 → 게임 상세 입장
                                           ↓
지갑 조회 ← 게임 플레이 ← 게임 코인 환전 (VIP 할인) ← 플랫폼 코인 충전
    ↓
  출금 (VIP 수수료 감면) → 백오피스 심사 → 입금
    ↓
친구 시스템 → 쪽지 채팅 → 리더보드 경쟁 → 업적 추적
    ↓
티켓 지원
```

### 2.2 API 인터페이스

| 메서드 | 경로 | 설명 | 인증 |
|------|------|------|------|
| POST | /api/auth/register | 사용자 등록 | 아니요 |
| POST | /api/auth/login | 사용자 로그인 | 아니요 |
| POST | /api/auth/refresh | Token 갱신 | 아니요 |
| GET | /api/game/list | 게임 목록 | 아니요 |
| GET | /api/game/detail/{id} | 게임 상세 | 아니요 |
| GET | /api/announcement/list | 공지 목록 | 아니요 |
| GET | /api/wallet/info | 지갑 잔액 | 예 |
| GET | /api/wallet/transactions | 거래 내역 | 예 |
| POST | /api/deposit/create | 충전 주문 생성 | 예 |
| GET | /api/payment/methods | 결제 수단 목록 (국가별 라우팅) | 예 |
| POST | /api/exchange/quote | 환전 견적 (VIP 할인) | 예 |
| POST | /api/exchange/buy | 게임 코인 매수 | 예 |
| POST | /api/exchange/sell | 게임 코인 매도 | 예 |
| POST | /api/withdraw/apply | 출금 신청 (VIP 감면) | 예 |
| POST | /api/game/launch | 게임 시작 | 예 |
| GET | /api/game/play-logs | 게임 기록 | 예 |
| POST | /api/referral/apply | 추천 코드 사용 | 예 |
| POST | /api/verify/send-email | 이메일 인증 코드 발송 | 예 |
| POST | /api/verify/confirm-email | 이메일 확인 | 예 |
| GET | /api/ticket/list | 티켓 목록 | 예 |
| POST | /api/ticket/create | 티켓 생성 | 예 |
| POST | /api/ticket/{id}/reply | 티켓 답변 | 예 |

| GET | /api/platform/stats | 플랫폼 통계 | 아니요 |
## 3. 관리 백오피스 기능

### 3.1 API 인터페이스 (신규)

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/dashboard/platform | 플랫폼 대시보드 데이터 |
| GET | /admin/analytics/overview | 플랫폼 총괄 (MySQL 실시간 집계) |
| GET | /admin/analytics/game-ranking | 게임 랭킹 |
| GET | /admin/analytics/dau-trend | DAU 추세 |
| GET | /admin/analytics/hourly-trend | 시간대별 추세 |
| GET | /admin/analytics/action-distribution | 행동 분포 |
| GET | /admin/analytics/revenue | 수익 분석 |
| GET | /admin/analytics/conversion | 게임 전환율 |
| GET | /admin/analytics/probability | 결합/조건부 확률 |
| GET | /admin/analytics/retention | 리텐션 분석 D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | 전환 퍼널 |
| GET | /admin/analytics/arpu | ARPU/ARPPU 추세 |
| GET | /admin/analytics/economy | 게임 코인 경제 지표 |
| GET | /admin/report/summary | 리포트 요약 (신규 사용자/입금/출금/환전/게임 플레이 수) |
| GET | /admin/report/daily | 일일 리포트 (일별 집계, 데이터 없는 날짜는 0 채움) |
| GET | /admin/report/export | 일일 리포트 CSV 내보내기 (UTF-8 BOM) |
| GET | /admin/game/list | 게임 목록 |
| POST | /admin/game/create | 게임 생성 (provider_config 포함) |
| PUT | /admin/game/{id} | 게임 편집 |
| GET | /admin/withdraw/orders | 출금 주문 목록 |
| PUT | /admin/withdraw/review | 출금 심사 |
| GET | /admin/ticket/list | 티켓 목록 |
| GET | /admin/ticket/{id} | 티켓 상세 |
| POST | /admin/ticket/{id}/reply | 티켓 답변 |
| POST | /admin/ticket/{id}/close | 티켓 닫기 |
| POST | /admin/ticket/{id}/assign | 처리 담당자 지정 |

## 4. Provider API (게임사 콜백)

| 메서드 | 경로 | 설명 | 인증 |
|------|------|------|------|
| POST | /api/provider/balance | 사용자 잔액 조회 | HMAC-SHA256 |
| POST | /api/provider/bet | 베팅 알림 | HMAC-SHA256 |
| POST | /api/provider/settle | 정산 알림 | HMAC-SHA256 |
| POST | /api/provider/refund | 환불 알림 | HMAC-SHA256 |

서명 알고리즘: `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)`
요청 헤더: `X-Game-Id` + `X-Timestamp` + `X-Signature`
시간 창: 5분

## 5. VIP 체계

| 등급 | 누적 EXP | 환전 할인 | 출금 수수료 감면 | 환율 보너스 |
|------|---------|---------|-------------|---------|
| 일반 | 0 | 0% | 0% | 기준 |
| 실버 | 500 | 2% | 10% | +0.1% |
| 골드 | 2,500 | 5% | 30% | +0.3% |
| 플래티넘 | 12,500 | 10% | 50% | +0.5% |
| 다이아몬드 | 62,500 | 15% | 100% | +1.0% |

### 경험치 획득

| 행동 | EXP |
|------|-----|
| 1원 충전 | 10 |
| 일일 로그인 | 5 |
| KYC 완료 | 50 |
| 신규 사용자 초대 | 100 |
| 업적 달성 | 10-100 |

## 6. 업적 목록

| 업적 | 조건 | 포인트 |
|------|------|------|
| First Deposit | 최초 충전 | 20 |
| Century Club | 누적 충전 100 | 50 |
| High Roller | 누적 충전 1000 | 100 |
| Trader | 최초 환전 | 20 |
| Day Trader | 누적 환전 100회 | 100 |
| Explorer | 게임 3개 플레이 | 30 |
| Adventurer | 게임 5개 플레이 | 50 |
| Conqueror | 게임 10개 플레이 | 100 |
| Weekly Warrior | 7일 연속 로그인 | 30 |
| Monthly Master | 30일 연속 로그인 | 100 |
| Connector | 친구 1명 초대 | 30 |
| Influencer | 친구 10명 초대 | 100 |

## 7. 데이터베이스 테이블 목록

### 생태계 확장 신규 (10장)

| 테이블명 | 설명 | 핵심 특성 |
|------|------|---------|
| game_ticket | 티켓 | user_id+type+status 인덱스, assigned_to |
| game_ticket_reply | 티켓 답변 | ticket_id 인덱스, is_admin 구분 |
| game_device_token | 기기 토큰 | user_id+platform+token 고유 인덱스 |
| game_vip_level | VIP 등급 정의 | level 고유 인덱스, benefits JSON |
| game_user_vip | 사용자 VIP 기록 | user_id 고유 인덱스, level+exp+total_exp |
| game_exp_log | 경험치 로그 | user_id+source 복합 인덱스 |
| game_achievement | 업적 정의 | key 고유 인덱스, condition_json JSON |
| game_user_achievement | 사용자 업적 | user_id+achievement_id 고유 인덱스 |
| game_friend | 친구 관계 | user_id+friend_id 고유 인덱스 |
| game_message | 쪽지 | from_user_id+to_user_id / to_user_id+is_read |

### 테이블 구조 변경

| 테이블명 | 변경 |
|------|------|
| game_game | +provider_config (JSON) |
| game_game_play_log | +round_id, +bet_amount, +win_amount |

**총계: install.sql 43장 테이블** (생태계 확장 10장은 `install/`에 있으며 install.sql에 미통합). 모델은 공유되지 않음: admin 46 / service 44 각각 1벌.

## 8. 테스트 커버리지

| 테스트 파일 | 케이스 수 | 커버 범위 |
|---------|--------|---------|
| PlatformTest | 56 | bcmath 정밀도/환전 계산/출금 수수료/한도/리스크/쿠폰/KYC/i18n |
| BackendEnhancementTest | 23 | 암호화 서비스/Hashids/Snowflake |
| CaptchaTest | 7 | 캡차 생성/검증 |
| EncryptionServiceTest | 6 | AES 암복호화/마스킹 |
| EnvConfigTest | 4 | 환경 변수 설정 |
| HashidsServiceTest | 8 | ID 인코딩/디코딩 왕복 |
| SnowflakeServiceTest | 6 | ID 생성 고유성 |

**총계: admin ~132 케이스 / 8 파일; service 3 케이스 (WebhookUrlSafety + EventBusMessageFormat). service는 CI 실패 차단 미적용.**

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
