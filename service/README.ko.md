# service/ — C측 사용자 플랫폼 API 서비스
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · **한국어** · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

C측 사용자 플랫폼 API 서비스. webman v2(Workerman) 기반의 고성능 PHP 백엔드로, 사용자에게 회원가입/로그인, 지갑, 충전, 출금, 환전, 게임, 랭킹, 쿠폰, 고객센터 티켓, VIP, 업적, 소셜, 공지 등 게임 통합 플랫폼의 모든 기능을 제공합니다.

## 기능 목록

| 모듈 | 설명 |
|------|------|
| 사용자 | 회원가입/로그인(아이디·비밀번호 + 7개 플랫폼 OAuth + 2FA TOTP), 프로필 |
| 지갑 | 플랫폼 코인 지갑(낙관적 잠금) + 게임 코인 지갑 + 거래 내역 |
| 충전 | 13개 결제 게이트웨이(Stripe/PayPal/NowPayments/Coinbase 등) 콜백 서명 검증, 자동 입금 |
| 출금 | 신청 → 심사 → 지급, KYC 단계별 한도 |
| 환전 | 플랫폼 코인 ⇄ 게임 코인 실시간 견적, VIP 할인 및 환율 가산 |
| 게임 | 게임 목록/카테고리/검색, 플레이 기록, Provider 정산 콜백 |
| 랭킹 | 일간/주간/월간/전체 + WebSocket 실시간 푸시 |
| 쿠폰 | 고정 금액 + 비율 할인, 기간·수량 한정 |
| 티켓 | 사용자의 고객센터 티켓 생성/답변 |
| VIP | 5단계 로열티, 경험치 누적, 환전 할인 |
| 업적 | 내장 12개 업적, 이벤트 기반 감지 |
| 소셜 | 친구 시스템 + WebSocket 실시간 메시지 |
| 공지 | 앱 내 공지 + 알림/이메일 |

## 기술 스택

- PHP 8.3+ / webman v2(workerman/webman)
- MySQL 8.0+(테이블 접두사 `game_`, BIGINT 비자동증가 기본 키)
- Redis(Session / 캐시 / 속도 제한)
- ClickHouse(OLAP 분석 / 확률 계산)
- Elasticsearch(전문 검색)
- JWT 인증 + HMAC-SHA256 Provider 서명

## 프로젝트 구조

```
service/
├── app/
│   ├── api/v1/controller/  # C측 API 컨트롤러(35개)
│   ├── middleware/         # 미들웨어(Cors/SecurityFilter/RateLimit/ApiVersion/UserAuth/ProviderAuth)
│   ├── model/              # 데이터 모델
│   ├── service/            # 비즈니스 서비스(VIP/랭킹/리스크/알림 등)
│   ├── event/              # 이벤트 버스(EventBus Redis Pub/Sub)
│   ├── provider/           # 게임 Provider 계층
│   └── payment/            # 결제 게이트웨이
├── common/                 # 공유 서비스 디렉터리(실체는 erik/platform-common 패키지)
├── config/                 # 설정 파일
├── public/                 # Web 진입점
├── tests/                  # PHPUnit 테스트
├── start.php               # 시작 진입점
└── composer.json
```

## 원클릭 설치

프로젝트 루트의 원클릭 설치 마법사를 권장합니다(프로젝트 루트에서 실행):

```bash
# 1. 설치 마법사 시작
php -S 0.0.0.0:8888 -t install/

# 2. 브라우저에서 http://localhost:8888 열기
#    마법사에 따라 진행: 환경 점검 → 데이터베이스 설정 → 관리자 계정 생성 → 자동 설치
```

또는 Docker Compose로 일괄 시작(프로젝트 루트):

```bash
docker compose up -d
```

## 수동 설치

```bash
# 1. 의존성 설치
cd service && composer install

# 2. 환경 변수 설정
cp .env.example .env
# .env 편집: 데이터베이스 연결 정보, JWT 키 등

# 3. 서비스 시작(기본 포트 8788)
php start.php start        # 포그라운드
php start.php start -d     # 백그라운드
```

## 사용 방법

- API 문서: `docs/API.md`(전체 API 레퍼런스)
- 온라인 문서: http://localhost:8788/apidoc/ (hg/apidoc 대화형 문서)
- 헬스 체크: `GET http://localhost:8788/health`
- C측 프런트엔드: `apps/flutter/platform/`(Flutter Web 사용자 플랫폼)
- 관리 백엔드: `admin/`(관리 백엔드 및 `admin/apps/flutter/` 프런트엔드)

## 테스트

```bash
cd service
SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
```
