# 아키텍처 설계 문서
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE-DESIGN.md) · [English](ARCHITECTURE-DESIGN.en.md) · **한국어** · [Русский](ARCHITECTURE-DESIGN.ru.md) · [Deutsch](ARCHITECTURE-DESIGN.de.md) · [Français](ARCHITECTURE-DESIGN.fr.md) · [Español](ARCHITECTURE-DESIGN.es.md) · [Português](ARCHITECTURE-DESIGN.pt.md) · [हिन्दी](ARCHITECTURE-DESIGN.hi.md) · [العربية](ARCHITECTURE-DESIGN.ar.md) · [বাংলা](ARCHITECTURE-DESIGN.bn.md) · [Bahasa Indonesia](ARCHITECTURE-DESIGN.id.md) · [日本語](ARCHITECTURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 설계 목표

글로벌 범용, 국제화된 게임 통합 플랫폼 구축. 핵심 요구사항:

- 사용자는 플랫폼에서 충전, 게임 코인 환전, 게임 플레이, 게임 코인 획득, 출금 가능
- 플랫폼이 여러 게임(자체 개발 + 서드파티)을 통합 관리하며, 각 게임마다 독립된 게임 코인과 환율 보유
- 백오피스에서 완전한 심사, 스위치, 리스크 관리 기능 제공
- 다국어, 다중 코인, 다중 결제 채널의 글로벌 운영 지원

## 2. 아키텍처 선정

### 2.1 왜 마이크로서비스 대신 모듈형 모놀리스인가?

현재 단계에서는 모듈형 모놀리스(Modular Monolith)를 선택:

| 고려 사항 | 모듈형 모놀리스 | 마이크로서비스 |
|------|----------|--------|
| 개발 효율 | 동일 프로세스 내 호출, RPC 불필요 | 네트워크 지연, 직렬화 처리 필요 |
| 트랜잭션 일관성 | 로컬 데이터베이스 트랜잭션 | 분산 트랜잭션 (복잡) |
| 운영 복잡도 | 단일 프로세스 배포 | 다중 서비스 오케스트레이션, 서비스 디스커버리 |
| 확장성 | 추후 모듈별로 마이크로서비스 분리 가능 | 독립 확장/축소에 자연스럽게 적합 |
| 팀 규모 | 소규모 팀에 적합 (1-5명) | 다중 팀 병렬 개발에 적합 |

**결정**: admin/(관리 백오피스)과 service/(C단 비즈니스)는 두 개의 독립 webman 인스턴스로, 동일 머신(다른 포트)에 배포하거나 분리 배포할 수 있습니다. 공유 레이어 common/은 PSR-4 autoload로 코드 중복을 제거합니다. 향후 비즈니스 규모가 커지면 service/를 여러 마이크로서비스(사용자 서비스, 지갑 서비스, 게임 서비스)로 분리할 수 있습니다.

### 2.2 왜 전통적인 PHP-FPM 대신 webman v2인가?

| 고려 사항 | webman v2 | PHP-FPM (Laravel) |
|------|-----------|-------------------|
| 성능 | 상주 메모리, 코루틴 지원 | 요청마다 전체 파일 로드 |
| 동시성 | 단일 머신 수만 QPS | 단일 머신 수백 QPS |
| 배포 | 간단, 단일 프로세스 다중 worker | Nginx + PHP-FPM 설정 복잡 |
| 생태계 | Laravel Illuminate 컴포넌트 호환 | 완전한 생태계 |

**결정**: 게임 플랫폼은 고동시성의 충전 콜백, 환전 요청, 게임 정산을 처리해야 하므로 webman의 상주 메모리와 고동시성 능력이 더 적합합니다. 동시에 Laravel의 ORM, Queue 등 컴포넌트와 호환되어 개발 효율도 전통 프레임워크에 뒤지지 않습니다.

### 2.3 왜 Flutter Web PC 스타일인가?

- 하나의 코드로 Web (PC), iOS, Android, HarmonyOS 동시 컴파일 가능
- Material 3 컴포넌트 라이브러리가 성숙하여 PC 스타일 사이드바+상단 바 레이아웃이 바로 사용 가능
- HarmonyOS 클라이언트와 비즈니스 로직 레이어 공유
- React/Vue + Flutter 두 세트의 프론트엔드 코드 유지 불필요

## 3. 핵심 기술 결정

### 3.1 ID 체계

```
Snowflake로 BIGINT 생성 (내부 분산 고유)
    ↓
Hashids로 짧은 문자열 인코딩 (외부에서 실제 ID 역산 불가)
    ↓
API 요청/응답에서 hashid 문자열 전송
```

**이유**:
- Snowflake는 전역 고유, 추세 증가로 인덱스에 유리, 비즈니스 규모 노출 없음
- Hashids는 외부가 자동 증가 ID로 데이터를 순회하거나 규모를 추측하는 것을 방지

### 3.2 코인 정밀도

플랫폼 코인과 게임 코인은 모두 `DECIMAL(18,4)` 정밀도를 사용하며, PHP 쪽은 `bcmath` 함수군(bcadd/bcsub/bcmul/bcdiv/bccomp)으로 모든 금액 계산을 수행합니다.

**이유**: 부동소수점(float/double)은 정밀도 오차가 있어 금융 시나리오에서는 허용 불가. DECIMAL + bcmath로 정확한 계산 보장.

### 3.3 지갑 낙관적 잠금

```sql
UPDATE game_user_wallet 
SET balance = balance + ?, version = version + 1 
WHERE user_id = ? AND version = ?
```

업데이트 실패 시 자동 재시도 (최대 5회).

**이유**:
- 게임 플랫폼의 충전, 환전, 출금은 동일 지갑을 동시에 조작할 수 있음
- 비관적 잠금(SELECT FOR UPDATE)은 고동시성에서 성능이 낮음
- 낙관적 잠금은 충돌률이 낮은 시나리오에서 비관적 잠금보다 성능이 훨씬 우수

### 3.4 출금 심사 플로우

```
사용자 출금 요청
  ├─ 전역 스위치 꺼짐 → 거부
  ├─ 금액 < 자동 심사 임계값 → 자동 승인
  └─ 금액 >= 임계값 → 수동 심사 → 승인/거부 (거부 시 플랫폼 코인 반환)
```

**이유**:
- 전역 스위치는 긴급 리스크 관리용(취약점 발견, 비정상 트래픽 등)
- 소액 자동 승인으로 인건비 절감, 사용자 경험 향상
- 대액 수동 심사로 자금 세탁과 사기 방지

### 3.5 환전 차액 모델

각 게임 코인은 독립된 `exchange_rate`(1플랫폼코인 = X게임코인)와 `spread_pct`(플랫폼 수수료%)를 가집니다.

매수 시: 게임 코인 입금 = 플랫폼 코인 × 환율 × (1 - 수수료%)
매도 시: 플랫폼 코인 입금 = 게임 코인 ÷ 환율 × (1 - 수수료%)

**이유**:
- 플랫폼 수익은 게임 내 결제가 아닌 환전 차액에서 발생
- 독립 환율로 각 게임의 가격 전략 지원
- 차액 비율을 유연하게 조정하여 정밀한 운영 가능

## 4. 보안 아키텍처

기존 18단계 심층 방어를 바탕으로 게임 플랫폼에 보호 레이어 추가:

| 레이어 | 조치 | 이유 |
|------|------|------|
| 동시성 안전 | 지갑 version 낙관적 잠금 | 중복 차감/중복 입금 방지 |
| 출금 안전 | 전역 스위치 + 금액 임계값 + 일/월 한도 + poster-php 검증 | 다층 방어로 자금 리스크 저감 |
| 환전 안전 | 견적과 체결 분리, 견적 60초 만료 | 환율 변동에 의한 차익 거래 방지 |
| 게임 안전 | 서드파티 콜백 서명 검증 + IP 화이트리스트 + replay attack 방어 | 게임 정산 위조 방지 |
| 리스크 관리 | 규칙 엔진 (IP 블랙리스트, 대액 경보, 빈도 이상) | 의심 거래 실시간 차단 |

## 5. 국제화 설계

### 5.1 언어 감지

```
요청 진입
  ↓
LanguageMiddleware (전역 미들웨어)
  ├── 1. X-Language 요청 헤더
  ├── 2. Accept-Language 헤더 (zh → zh-CN, en → en-US)
  └── 3. 기본 en-US
  ↓
TranslationService::setLocale($locale)
  ↓
Controller에서 __() 함수 또는 TranslationService::trans()로 번역 텍스트 획득
```

### 5.2 번역 저장

- 데이터베이스 테이블 `game_translation`에 모든 번역 텍스트 저장 (group + key + lang_code + value)
- 첫 요청 시 데이터베이스에서 Redis로 전체 로드 (key: `i18n:translations`, TTL: 1시간)
- 이후 요청은 Redis에서 직접 읽고, 메모리 캐시로 가속
- 관리 백오피스에서 번역 관리 페이지 확장 가능 (풀 에디션에서 구현)

### 5.3 번역 키 명명

형식: `group.key` 예: `auth.login_success`, `wallet.insufficient_balance`

| 그룹 | 도메인 |
|------|------|
| auth | 인증 관련 |
| wallet | 지갑 관련 |
| exchange | 환전 관련 |
| withdraw | 출금 관련 |
| deposit | 충전 관련 |
| game | 게임 관련 |
| admin | 관리 백오피스 |
| error | 오류 메시지 |

### 5.4 폴백 전략

- 요청 언어에 해당 번역이 있음 → 사용
- 요청 언어에 해당 번역이 없음 → en-US로 폴백
- en-US에도 없음 → 원본 key 반환

### 5.5 프론트엔드 i18n

- Flutter는 자체 구축 `AppTranslations` + `LocaleController`(GetX) 사용
- 언어 선호도는 SharedPreferences에 영속화
- 언어 전환 시 `Get.updateLocale()`로 전역 UI 재렌더링 트리거
- `StringResult` 클래스가 Dart의 `toString()`을 활용해 자연스러운 인라인 문법 구현: `Text('${AppTranslations.t("key")}')`

## 6. 스탠다드 에디션 신규 설계

### 6.1 리스크 엔진

핵심 자금 작업 전에 다층 규칙 검사 수행:

```
충전/출금/환전 요청
  ↓
RiskService::check(userId, type, context)
  ├── IP 블랙리스트 검사 (ip_blacklist) → block
  ├── 대액 이상 검사 (amount_anomaly) → warn
  ├── 빈도 검사 (frequency) → warn/block
  └── 속도 검사 (velocity) → block
  ↓
passed → 정상 실행
warn   → 로그 기록 후 계속 실행
block  → 작업 거부
```

규칙은 `game_risk_rule` 테이블에 저장되며 JSON으로 설정되어 임계값과 동작을 동적으로 조정할 수 있습니다.

### 6.2 KYC 실명 인증

3단계 인증 체계:
- `default` — 미인증, 기본 한도
- `verified` — KYC 심사 통과, 한도 상향 + 수수료 인하
- `vip` — VIP 등급, 최고 한도 + 수수료 면제

인증 플로우:
```
사용자가 증빙 서류 제출 → status=pending
관리자 심사 → approve/reject
approve → 사용자 자동으로 verified 등급 승격
reject → 사용자 재제출 가능
```

### 6.3 OAuth 서드파티 로그인

Google / Facebook / Apple 로그인 지원:

```
프론트엔드에서 OAuth 버튼 클릭
  → GET /api/auth/oauth/{provider} → 인증 URL 획득
  → 서드파티 인증 페이지 이동 → 사용자 동의
  → 콜백 POST /api/auth/oauth/{provider}/callback
  → 기존 연동 확인 → 바로 로그인
  → 연동 없음 → 신규 사용자 자동 등록 + 연동 + 지갑 생성
```

### 6.4 결제 콜백

```
서드파티 결제 완료 → POST /api/payment/callback
  → provider 화이트리스트 검증 (stripe/paypal만)
  → 서명 검증 fail-closed (secret/webhook_id 미설정, 서명 검증 실패, 타임스탬프 ±300초 초과는 모두 거부)
  → 콜백 금액과 주문 금액 bccomp 대조 (채널 간 도용 방지)
  → 주문 상태 confirmed 업데이트 (트랜잭션화, 입금 실패 시 롤백)
  → UserWallet::addBalance 입금
  → Transaction 기록
  → RiskService::check 리스크 검사
```

### 6.5 단계별 출금 한도

사용자 KYC 등급에 따라 다른 한도와 수수료 적용:

| 등급 | 단건 상한 | 일 한도 | 월 한도 | 수수료 |
|------|---------|--------|--------|--------|
| default | 1,000 | 10,000 | 50,000 | 1.00% |
| verified | 5,000 | 50,000 | 200,000 | 0.50% |
| vip | 20,000 | 200,000 | 1,000,000 | 0.00% |

## 7. 확장성 설계

### 5.1 수평 확장

admin/과 service/ 모두 다중 worker 프로세스를 지원합니다. Nginx 역방향 프록시와 함께 여러 머신에 배포하여 수평 확장 구현:

```
Nginx (로드 밸런싱)
  ├── admin-1 (:8787)
  ├── admin-2 (:8787)
  ├── service-1 (:8788)
  └── service-2 (:8788)
```

### 5.2 모듈 분리 경로

단일 service/가 병목이 될 때 다음 경로로 분리:

```
service/ (모놀리스)
  → service-user/ (사용자 서비스 :8788)
  → service-wallet/ (지갑 서비스 :8789)
  → service-game/ (게임 서비스 :8790)
  → service-payment/ (결제 서비스 :8791)
```

분리 시점 판단 기준:
- 단일 모듈 QPS가 단일 머신 수용 능력 초과
- 특정 모듈에 독립적인 기술 스택이나 배포 전략 필요
- 팀 규모가 서로 다른 모듈 병렬 개발로 확장
