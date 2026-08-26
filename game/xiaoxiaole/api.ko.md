# 전원 소소락 (田园消消乐) — 플랫폼 연동 API
<!-- lang-nav -->

Languages: [中文](api.md) · [English](api.en.md) · **한국어** · [Русский](api.ru.md) · [Deutsch](api.de.md) · [Français](api.fr.md) · [Español](api.es.md) · [Português](api.pt.md) · [हिन्दी](api.hi.md) · [العربية](api.ar.md) · [বাংলা](api.bn.md) · [Bahasa Indonesia](api.id.md) · [日本語](api.ja.md)


> 이 문서는 《전원 소소락》과 게임 플랫폼 간의 모든 인터페이스 계약입니다. 기술 계층은 `architecture.md`, 일정은 `plan.md`, 플레이어 기능은 `functional-design.md`를 참조하세요.

---

## 1. 시작 링크

```
Flutter / HarmonyOS / PC Web
        │  POST /api/game/launch { game_id }
        ▼
service/ (webman :8788)
  GameController::launch  → session_id + seed + api_endpoint
  SelfProvider            → bet / settle / refund / getBalance
  GamePlayLog + EventBus  → game.played / 업적 / VIP
        │  api_endpoint?session_id=&token= 열기
        ▼
game/xiaoxiaole/  (정적 리소스, Nginx)
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

게임은 **정적 프론트엔드**이며, 권위 있는 세션과 돈은 `service/`에 있습니다. 클라이언트가 보드 상태를 보유하고, 서버는 잔액과 round 멱등성을 보유합니다. 1단계는 단계별 서버 검증을 하지 않지만, 2단계에서 `seed + 조작 시퀀스`를 서버로 보내 재계산할 수 있도록 도메인 레이어는 반드시 결정적이어야 합니다.

---

## 2. 인터페이스 목록

| 인터페이스 | 메서드 | 방향 | 설명 |
|------|------|------|------|
| `/api/game/launch` | POST | 플랫폼 → service | 게임 세션 시작, `session_id, api_endpoint, type=self` 반환 |
| `/api/provider/balance` | GET | 게임 → service | 게임 코인 잔액 조회 |
| `/api/provider/bet` | POST | 게임 → service | 스테이지 시작 시 입장료 차감 |
| `/api/provider/settle` | POST | 게임 → service | 클리어 정산 보상 지급 |
| `/api/provider/refund` | POST | 게임 → service | 첫 수를 두지 않고 나가면 환불 |

게임 측은 `/api/provider/*` 호출을 `PlatformAdapter`로 처리하며 HMAC/JWT 서명을 사용합니다.

---

## 3. 시작 흐름

1. 플랫폼 `POST /api/game/launch`가 `session_id, api_endpoint, type=self`를 반환합니다.
2. `api_endpoint?session_id=&token=`을 엽니다（token은 단기 게임 티켓 또는 JWT 재사용）.
3. 게임 `GET /api/provider/balance`로 게임 코인을 표시합니다.
4. 플레이어가 「이 스테이지 시작」을 누르면 → `POST /api/provider/bet`, `round_id = session_id + ':' + levelId + ':' + attempt`.
5. 도메인 `seed = hash(session_id + round_id)`.
6. 클리어하면 `settle`, 실패하면 settle하지 않음; 조작 없이 나가면 `refund`.

---

## 4. Play-log 보고

`launch`（기존）+ 게임 측이 다음 이벤트를 보고합니다（먼저 ClickHouse `GamePlayLogService`로 기록 가능）:

| 이벤트 | 시점 |
|------|------|
| `level_start` | 스테이지 진입 |
| `level_win` | 클리어 |
| `level_fail` | 실패 |
| `skill_use` | 스킬 사용 |

---

## 5. 기능 스위치（FeatureFlag）

| 스위치 | 기본값 | 설명 |
|------|------|------|
| `xxl.eco_chain` | on | 생태 상성 체인 |
| `xxl.elephant` | off | 코끼리 규칙 |
| `xxl.skills` | on | 농기구 스킬 |
| `xxl.entry_bet` | off | 입장료/지갑 |

끄면 스테이지가 순수 동종 3매치로 퇴화하여 단계별 출시에 적합합니다.

---

## 6. 지갑과 round 멱등성

- `SelfProvider::bet/settle/refund`는 이미 있으며, 게임이 `round_id`로 호출; 단일 round 보상 상한 설정.
- 단일 round는 bet/settle을 한 번만; 타임아웃 세션은 무효 처리; 이상 고득점은 로그만 남기고 자동 보상 없음（settle 상한 설정 가능）.
- 실패 시 입장료 반환 없음; 하나도 교환하지 않고 나가면 → `refund`.

---

## 7. 2단계: 서버 재계산

조작 시퀀스를 업로드하면 서버가 동일한 `domain`의 PHP 이식 또는 Node worker로 재계산합니다（`seed + 조작 시퀀스` → 보드와 점수 검증）.
