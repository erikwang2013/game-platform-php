# 버전 비교
<!-- lang-nav -->

Languages: [中文](VERSIONS.md) · [English](VERSIONS.en.md) · **한국어** · [Русский](VERSIONS.ru.md) · [Deutsch](VERSIONS.de.md) · [Français](VERSIONS.fr.md) · [Español](VERSIONS.es.md) · [Português](VERSIONS.pt.md) · [हिन्दी](VERSIONS.hi.md) · [العربية](VERSIONS.ar.md) · [বাংলা](VERSIONS.bn.md) · [Bahasa Indonesia](VERSIONS.id.md) · [日本語](VERSIONS.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 총괄

| | 베이직 에디션 (Lite) | 스탠다드 에디션 (Standard) | 풀 에디션 (Full) |
|------|------|------|------|
| 데이터 테이블 (install.sql) | 19 | 29 | **43**（문서가 이전에 쓴 52 아님） |
| API 엔드포인트 | 38 | 54 | ~149 (admin+service, Webhook/Provider 포함) |
| 백엔드 컨트롤러 | 14 | 22 | admin 32 + service 30 |
| 데이터 모델 | 비공유 | 비공유 | **admin 46 / service 44 각각 1벌, 공유 레이어 없음** |
| 공유 Service | 공유 레이어 없음 | 공유 레이어 없음 | `packages/platform-common` 단일 공유 패키지 |
| Admin 프론트엔드 페이지 | 11 | 13 | 15 |
| Platform 프론트엔드 페이지 | 8 | 10 | 10 |
| HarmonyOS (admin) | - | 로그인+대시보드 | **8페이지** `admin/apps/harmonyos/` |
| HarmonyOS (C단) | - | - | **5페이지** `apps/harmonyos/`（로그인/게임 로비/상세/지갑/마이） |
| Docker 서비스 | - | - | **7** (nginx/admin/service/leaderboard-ws/mysql/redis/elasticsearch) |
| 테스트 케이스 | 60 | 60 | admin ~132; service 3 |

---

## 사용자 인증

| 기능 | 베이직 | 스탠다드 | 풀 |
|------|--------|--------|--------|
| 사용자 이름/비밀번호 등록·로그인 | ✓ | ✓ | ✓ |
| JWT Token (2h+14d) | ✓ | ✓ | ✓ |
| 클릭형 캡차 | stub | stub | ✓ poster-php |
| 계정 잠금 (5회/15분) | ✓ | ✓ | ✓ |
| 세션 제한 (3개 동시) | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ 7플랫폼 (X/MS/LinkedIn/GitHub 포함) |
| 2FA TOTP 이중 인증 | - | - | ✓ |
| GDPR 데이터 내보내기/탈퇴 | - | - | ✓ |

---

## 지갑과 자금

| 기능 | 베이직 | 스탠다드 | 풀 |
|------|--------|--------|--------|
| 플랫폼 코인 지갑 | ✓ | ✓ | ✓ |
| 지갑 낙관적 잠금 | ✓ | ✓ | ✓ |
| 거래 내역 기록 | ✓ | ✓ | ✓ |
| 게임 코인 지갑 | ✓ | ✓ | ✓ |
| 충전 주문 생성(즉시 checkout_url/expires_at 기록) | ✓ | ✓ | ✓ |
| 충전 콜백 자동 입금 | - | ✓ 수동 | ✓ Stripe (incl. Alipay/WeChat Pay)/PayPal/NowPayments IPN/Coinbase webhook 검증 |
| 환전 견적/매수/매도 | ✓ | ✓ | ✓ |
| 환전 차액 수익 | ✓ | ✓ | ✓ |
| 출금 신청 | ✓ | ✓ | ✓ |
| 전역 출금 스위치 | ✓ | ✓ | ✓ |
| 출금 심사 | ✓ 수동 | ✓ 수동 | ✓ 일괄+수동 |
| KYC 단계별 한도 | - | ✓ 3단계 | ✓ |
| 출금 수수료 | - | - | ✓ |
| PDF 영수증 | - | - | ✓ |

---

## 게임 관리

| 기능 | 베이직 | 스탠다드 | 풀 |
|------|--------|--------|--------|
| 게임 CRUD | ✓ | ✓ | ✓ |
| 게임 코인 관리 | ✓ | ✓ | ✓ |
| C단 게임 목록/상세 | ✓ | ✓ | ✓ |
| 게임 시작 | ✓ | ✓ | ✓ |
| 게임 분류 (10종) | - | - | ✓ |
| 분류 필터 | - | - | ✓ |
| 게임 구역 관리 | - | ✓ | ✓ |
| 게임 기록 추적 | - | ✓ | ✓ |
| ES 전문 검색 | - | - | ✓ |
| 검색 제안 | - | - | ✓ |
| 서드파티 게임 Provider SDK | - | - | ✓ HMAC-SHA256 |

---

## 운영 도구

| 기능 | 베이직 | 스탠다드 | 풀 |
|------|--------|--------|--------|
| 공지 관리 | ✓ | ✓ | ✓ |
| 대시보드 | ✓ 관리 백오피스 | ✓ 관리 백오피스 | ✓ 관리+플랫폼 |
| Excel 내보내기 | ✓ | ✓ | ✓ |
| PDF 내보내기 | ✓ | ✓ | ✓ |
| 대시보드 실제 차트 | - | - | ✓ fl_chart |
| 쿠폰 시스템 | - | - | ✓ |
| 랭킹 (일/주/월/총) | - | - | ✓ Redis 캐시 |
| WebSocket 실시간 랭킹 | - | - | ✓ 포트 8789 |
| 알림 시스템 (사이트 내+이메일) | - | - | ✓ |
| 추천 리베이트 | - | - | ✓ |
| 일별 통계 스냅샷 | - | ✓ | ✓ |
| 플랫폼 수익 추적 | - | - | ✓ |

---

## 보안 컴플라이언스

| 기능 | 베이직 | 스탠다드 | 풀 |
|------|--------|--------|--------|
| 18계층 심층 방어 | ✓ | ✓ | ✓ |
| RBAC 권한 제어 | ✓ | ✓ | ✓ |
| 작업 감사 로그 | ✓ | ✓ | ✓ |
| 8플랫폼 소스 감지 | ✓ | ✓ | ✓ |
| Redis 슬라이딩 윈도우 레이트 리밋 | ✓ | ✓ | ✓ |
| KYC 실명 인증 | - | ✓ | ✓ |
| 리스크 엔진 (4규칙) | - | ✓ | ✓ |
| 결제 콜백 서명 검증 | - | - | ✓ |

---

## 국제화

| 기능 | 베이직 | 스탠다드 | 풀 |
|------|--------|--------|--------|
| 다국어 지원 | 중/영문 | 4언어 | 4언어 |
| 번역 테이블+캐시 | ✓ | ✓ | ✓ |
| 언어 자동 감지 | ✓ | ✓ | ✓ |
| 국가 차등 설정 | - | - | ✓ 8개국 |

---

## 배포 운영

| 기능 | 베이직 | 스탠다드 | 풀 |
|------|--------|--------|--------|
| webman 독립 배포 | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ 7서비스 |
| Nginx 리버스 프록시 | - | - | ✓ |
| Crontab 예약 작업 | - | ✓ | ✓ |
| Prometheus 모니터링 | ✓ | ✓ | ✓ `/metrics` 비즈니스 gauge + 이벤트 counter |
| 헬스 체크 | ✓ | ✓ | ✓ |
| hg/apidoc 온라인 문서 | - | - | ✓ 41컨트롤러 |

---

## 클라이언트

| 기능 | 베이직 | 스탠다드 | 풀 |
|------|--------|--------|--------|
| Flutter Web PC 관리 백오피스 | ✓ 5페이지 | ✓ 11페이지 | ✓ 15페이지 |
| Flutter Web PC 사용자 플랫폼 | ✓ 5페이지 | ✓ 8페이지 | ✓ 10페이지 |
| HarmonyOS admin | - | ✓ 로그인+대시보드 | ✓ 8페이지 `admin/apps/harmonyos/` |
| HarmonyOS C단 | - | - | ✓ 5페이지 `apps/harmonyos/` |

---

## 데이터베이스 테이블

### 베이직 에디션 (19장)
```
관리 백오피스 (7):  game_admin_user, game_admin_role, game_admin_permission,
               game_admin_user_role, game_admin_role_permission,
               game_operation_log, game_system_config

플랫폼 코어 (12): game_user, game_user_wallet, game_user_game_wallet,
               game_game, game_game_currency, game_deposit_order,
               game_withdraw_order, game_exchange_record, game_transaction,
               game_payment_method, game_announcement, game-platform_config
```

### 스탠다드 에디션 추가 (10장)
```
game_user_identity, game_user_oauth, game_user_payment_account,
game_user_session, game_game_server, game_game_play_log,
game_withdraw_limit, game_risk_rule, game_risk_log, game_stat_daily
```

### 풀 에디션 추가 (13장)
```
game_game_category, game_game_category_rel, game_leaderboard,
game_coupon, game_user_coupon, game_language, game_translation,
game_country_config, game-platform_revenue,
game_notification, game_referral, game_referral_reward, game_user_2fa
```

---

## API 엔드포인트

| 모듈 | 베이직 | 스탠다드 | 풀 |
|------|--------|--------|--------|
| 인증 | 3 | 3 | 7 (+OAuth×2 +2FA×5) |
| 지갑 | 2 | 2 | 3 (+충전 콜백) |
| 환전 | 4 | 4 | 4 |
| 출금 | 2 | 2 | 8 (+일괄+한도+심사) |
| 게임 | 3 | 4 | 7 (+구역+기록+검색) |
| 사용자 | 2 | 2 | 7 (+KYC+GDPR+프라이버시) |
| 관리 백오피스 | 18 | 25 | 79 |
| 운영 도구 | - | - | 30 (+랭킹+쿠폰+알림+추천) |
| 국제화 | 2 | 2 | 4 (+국가 설정) |
| **총계** | **38** | **54** | **129** |

---

## 생태계 확장 (v2.0) — 신규

| 기능 | 설명 |
|------|------|
| GameProvider 추상 레이어 | SelfProvider (DB 트랜잭션) + ThirdPartyProvider (HTTP+서명) |
| Provider API 게이트웨이 | balance/bet/settle/refund 콜백 + ProviderAuth 미들웨어 |
| 티켓 시스템 | C단 생성/답변 + 관리단 처리/할당/닫기 |
| 이메일 검증 | 6자리 인증 코드, Redis 10분 만료, 60초 재발송 제한 |
| 푸시 알림 | PushService (FCM/APNs/华为推送) |
| VIP 체계 | 5단계, 경험치 누적, 자동 승급, 환전 할인, 출금 감면, 환율 보너스 |
| 업적 시스템 | 내장 업적 12개, 이벤트 주도 검출, 진행도 추적 |
| 친구 시스템 | 신청/수락/거절/삭제/검색 |
| 쪽지/채팅 | REST + WebSocket 실시간 메시지 (포트 8790) |
| 이벤트 버스 | Redis Pub/Sub; emit INCR `metrics:event_*`; 소비 프로세스 `EventConsumer` 구축 완료 |
| 기능 스위치 | FeatureFlag DB 기반; `inRollout`/`abTest`가 `feature.{name}_percent` 읽음 |
| Webhook | - | - | ✓ 7가지 이벤트+Pub/Sub 전달 |
| 채팅 | - | - | ✓ REST+WebSocket :8791 |
| 토너먼트 | - | - | ✓ FeatureFlag+tournament |
| 쿠폰 조건 | - | - | ✓ min_deposit/first_user/game_id |
| 다단계 커미션 | - | - | ✓ 2차 수익 분배 |
| SDK 문서 | - | - | ✓ PHP/Go/Python |
| 고급 분석 | 리텐션/D1-D30, 전환 퍼널, ARPU/ARPPU |

### 신규 데이터 테이블 (10장)
```
game_ticket, game_ticket_reply, game_device_token,
game_vip_level, game_user_vip, game_exp_log,
game_achievement, game_user_achievement,
game_friend, game_message
```

### 신규 Provider API 엔드포인트 (4개)
```
POST /api/provider/balance  — 잔액 조회
POST /api/provider/bet      — 베팅 알림
POST /api/provider/settle   — 정산 알림
POST /api/provider/refund   — 환불 알림
```

### 신규 C단 API 엔드포인트 (8개)
```
POST /api/verify/send-email    — 이메일 인증 코드 발송
POST /api/verify/confirm-email — 이메일 확인
GET  /api/ticket/list             — 티켓 목록
POST /api/ticket/create           — 티켓 생성
GET  /api/ticket/{id}             — 티켓 상세
POST /api/ticket/{id}/reply       — 티켓 답변
GET  /api/user/vip-status         — VIP 상태
GET  /api/user/achievements       — 업적 목록
```

### 신규 관리 백오피스 API 엔드포인트 (6개)
```
GET  /admin/ticket/list          — 티켓 목록
GET  /admin/ticket/{id}          — 티켓 상세
POST /admin/ticket/{id}/reply    — 티켓 답변
POST /admin/ticket/{id}/close    — 티켓 닫기
POST /admin/ticket/{id}/assign   — 처리 담당자 지정
GET  /admin/analytics/retention  — 리텐션 분석
GET  /admin/analytics/funnel     — 전환 퍼널
GET  /admin/analytics/arpu       — ARPU 추세
GET  /admin/analytics/economy    — 경제 지표
```
