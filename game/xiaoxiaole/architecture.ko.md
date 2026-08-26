# 전원 소소락 (田园消消乐) — 기술 아키텍처
<!-- lang-nav -->

Languages: [中文](architecture.md) · [English](architecture.en.md) · **한국어** · [Русский](architecture.ru.md) · [Deutsch](architecture.de.md) · [Français](architecture.fr.md) · [Español](architecture.es.md) · [Português](architecture.pt.md) · [हिन्दी](architecture.hi.md) · [العربية](architecture.ar.md) · [বাংলা](architecture.bn.md) · [Bahasa Indonesia](architecture.id.md) · [日本語](architecture.ja.md)


> 플레이어 기능과 인수는 `functional-design.md`; 일정은 `plan.md`; 테마 비전은 `design.md` 참조.
>
> 이 문서는 모듈을 어떻게 나누는지, 플랫폼을 어떻게 연동하는지, 규칙을 어느 레이어에서 계산하는지만 다룹니다. 구현 코드는 작성하지 않습니다.
>
> 제품 포지셔닝: 자체 개발 H5（`game.type = self`）, 8×8 사판 3매치 + 생태 상성 체인, Three.js 로우 폴리 2.5D.

---

## 0. 설계 대비 아키텍처 결정

설계는 플레이 비전입니다; 다음 결정들이 「즐길 수 있고, 테스트 가능하고, 지갑을 연결할 수 있는」 모순을 해결합니다.

| ID | 결정 | 이유 |
|----|------|------|
| D1 | **도감 ≠ 동판 말**. 100+ 종은 도감과 외형; 단일 스테이지 리젠 풀은 **5–8개 종만** 추출 | 8×8에 수십 종이 동시 등장하면 거의 매치가 안 됨 |
| D2 | 매칭을 두 레이어로: **동종은 `speciesId`, 생태는 `role` + 상성표** | 설계가 동시에 「사과 3개」와 「닭+벌레+벌레」를 요구 |
| D3 | 같은 라인 규칙 우선순위: **코끼리 > 생태 > 동종**; 상호 배타, 중복 점수 없음 | 한 줄이 두 번 점수를 먹는 것 방지 |
| D4 | **농기구는 보드에 안 들어감**, HUD 스킬 슬롯에만; 돌/웅덩이/나무는 장애물, 교환 불가 | 설계 5절과 말 패턴 충돌, 스킬+장애물 기준 채택 |
| D5 | **도메인 로직은 Three.js 의존성 0**, 순수 함수 + 스냅샷; 표현 레이어는 이벤트만 구독 | 규칙을 단위 테스트/리플레이/추후 서버 검증 가능 |
| D6 | 시작 시 `session_id`로 **결정적 RNG seed** 파생; 낙하/리젠 전부 이 RNG 사용 | 같은 seed로 재현 가능; 안티치트 대비 여지 |
| D7 | 물리 엔진 없음. 이동/점프/소거는 완급 조절, Cannon/Rapier 미도입 | 설계에 이미 「시뮬레이션 애니메이션」; 물리는 그리드 게임에 무의미 |
| D8 | 카메라 **직교 2.5D 고정 기종**, 궤도 제어 끄기 | 설계와 일치, 오조작과 어지럼증 방지 |
| D9 | 종 공용 **진영 기하 템플릿 + 색상/부속품**, 작물마다 개별 모델링 안 함 | 트래픽과 공기; 시각 차이는 배색과 특징 부품 하나로 |
| D10 | 스테이지 입장은 `SelfProvider::bet`, 클리어는 `settle`, 중도 실패는 입장료 반환 없음; 첫 수 안 두면 `refund` 가능 | 플랫폼 지갑과 round 멱등성 정렬 |

---

## 1. 시스템 컨텍스트

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
  Vite + TypeScript + Three.js
  도메인 엔진 ──이벤트──► 렌더 / HUD
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

게임은 **정적 프론트엔드**이며, 권위 있는 세션과 돈은 `service/`에 있습니다. 클라이언트가 보드 상태를 보유하고, 서버는 잔액과 round 멱등성을 보유합니다. 1단계는 단계별 서버 검증을 하지 않지만, 2단계에서 `seed + 조작 시퀀스`를 서버로 보내 재계산할 수 있도록 도메인 레이어는 반드시 결정적이어야 합니다.

---

## 2. 클라이언트 레이어링

위에서 아래로, 레이어 역방향 의존 금지（`render`를 `domain`이 import하면 안 됨）.

```
app/          조립, 상태 머신, 스테이지 라이프사이클
hud/          HTML Overlay: 점수, 스텝, 목표, 스킬, 결과
platform/     launch 파라미터, 지갑, play-log, 기능 스위치
render/       Three.js: 씬, 보드, 말 그리드, 입력, VFX
runtime/      명령 버스, 애니메이션 큐, 리플레이
domain/       보드, 매칭, 상성, 중력, 점수, 도감, 스테이지 규칙
config/       상성표, 리젠 가중치, 기하 레시피, 스테이지 JSON
```

**메인 루프（`requestAnimationFrame` 안에서 규칙 계산하지 않음）**: 입력 → 명령 → 도메인 동기 정산（한 번의 swap으로 모든 연쇄를 계산, 이벤트 목록 생성）→ 런타임이 이벤트별로 애니메이션 큐잉 → 애니메이션이 끝나야 다음 입력 수락.

이렇게 「로직 한 프레임, 표현 여러 프레임」이라 combo가 클릭과 상태를 다툴 일이 없습니다.

---

## 3. 디렉터리 구조（권장）

```
game/xiaoxiaole/
├── design.md
├── architecture.md          ← 본 문서
├── package.json             # vite, three, gsap, vitest, typescript
├── index.html
├── src/
│   ├── main.ts              # URL 읽고 GameApp 시작
│   ├── app/
│   │   ├── GameApp.ts
│   │   └── GameStateMachine.ts
│   ├── domain/
│   │   ├── catalog/         # PieceDef, Faction, Role
│   │   ├── board/           # Grid 8×8, Cell, PieceInstance
│   │   ├── match/           # MatchDetector（동종 / 생태 / 코끼리）
│   │   ├── eco/             # RestraintTable
│   │   ├── gravity/         # 낙하, 웅덩이 차단, 리젠
│   │   ├── score/           # 계수, 비료 배율
│   │   ├── level/           # LevelDef, Objective, Win/Lose
│   │   └── rng/             # seeded mulberry32
│   ├── runtime/
│   │   ├── CommandBus.ts    # Select, Swap, UseSkill, Quit
│   │   ├── EventLog.ts      # 직렬화 가능 이벤트, 리플레이용
│   │   └── AnimationQueue.ts
│   ├── render/
│   │   ├── SceneRoot.ts
│   │   ├── CameraRig.ts
│   │   ├── BoardView.ts
│   │   ├── PieceFactory.ts  # 템플릿 기하
│   │   ├── InputRaycaster.ts
│   │   └── vfx/
│   ├── hud/
│   ├── platform/
│   │   ├── ApiClient.ts
│   │   └── Session.ts
│   └── levels/*.json
└── tests/domain/            # WebGL 없음
```

단일 파일 500줄 이하. `MatchDetector`와 `PieceFactory`가 비대해지면 규칙 유형/진영 템플릿별로 재분할.

---

## 4. 도메인 모델

### 4.1 말 정의（도감）

```
PieceDef
  id            speciesId        예: wheat, hen, elephant
  faction       Faction          crop | veg | fruit | flora | insect | poultry | livestock | tree | tool | obstacle | apex
  role          Role             plant | prey | predator_mid | predator_high | apex | obstacle | skill
  tags[]                         crop, edible_by_poultry, tree_seedling, bone …
  rarity        common | rare | legendary
  template      GeometryTemplate 기하 템플릿 이름
  tint          RGB              템플릿 내 배색
  accessory     optional         부리, 꽃잎, 코끼리 코 등 구분 부품
```

설계의 작물/채소/과일/화초/곤충/가금/가축/나무 전부 도감에 들어감; **tool은 칸에 생성 안 됨**. 코끼리 `rarity = legendary`, `role = apex`.

### 4.2 칸과 보드

```
Cell
  q, r                열, 행（0–7）
  height              지형 기복（렌더 전용, 규칙 미참여）
  occupant           PieceInstance | null
  overlay            none | fertilizer | puddle | stone(hp) | tree(hp)
  locked             bool          코끼리 축제 스테이지: 코끼리는 교환 불가

PieceInstance
  uid, speciesId, def
  special            none | fertilizer_token
```

- **돌 / 나무**: 칸 점유, 교환 불가, 낙하 통과 불가. HP는 스테이지 참조.
- **웅덩이**: 칸 위에 겹침, 그 칸을 통과하는 중력 차단（위 말은 웅덩이 한 칸 위에 멈춤）.
- **비료**: 생태 소거 후 그 칸에 잔류; 다음에 그 칸이 참여하는 소거 점수 ×2, 이후 사라짐.

### 4.3 리젠 풀（스테이지）

```
SpawnPool
  speciesIds[]       5–8개
  weights[]          species와 정렬
  maxApex            기본 1
  apexUnlock         combo >= 5일 때 시스템 생성, 「교환 생성」 금지
```

스테이지는 이 풀에서만 `rng`로 말을 뽑습니다. 도감이 아무리 커도 판면 엔트로피는 통제 가능.

---

## 5. 핵심 규칙 엔진（기능 설계）

### 5.1 조작

1. 말 A 클릭 → 선택（튀어오름 + 윤곽）.
2. 인접 직교 칸 B 재클릭 → 교환 시도（대각 교환 금지）.
3. 비인접 / 빈칸 재클릭 → 재선택 또는 취소.
4. 교환 후 **합법 매치가 하나도 없으면** 되돌리기 애니메이션, 스텝 미소모.
5. 매치 있으면 1스텝 소모 후 정산 진입.

장애물 칸은 교환 대상으로 선택 불가. 잠금 칸（스테이지 규칙）도 동일.

### 5.2 라인 스캔

swap 후 판면마다:

- 가로·세로 연속 칸, 길이 ≥ 3을 **run** 하나로.
- run 하나에는 규칙 하나만 적용（D3）.
- 여러 run이 교차 가능（고전 L/T자형）, 교차 칸은 한 번만 소거.

### 5.3 동종 소거

run 내 `speciesId`가 전부 같고, 장애물이 아니며, 코끼리 특권으로 따로 처리되지 않을 때.

- 3소거: 기본 점수.
- 4소거: 추가 점수, 중앙 칸에 **비료** 드롭（생태 비료와 같은 overlay）.
- 5소거: 추가 점수, 스킬 슬롯 +1 충전（5.7 참조）.

### 5.4 생태 소거（상성 체인）

판정: **정확히 상성자 1개 + 나머지는 전부 그 상성자의 사냥감**（3칸이면 1+2）. 사냥감 동종 불필요.

| 상성자 | 사냥감 매칭 |
|--------|----------|
| 닭, 오리, 거위 | faction ∈ {flora, veg, fruit, insect}；**crop（오곡） 미포함** |
| 개 | faction = poultry（닭·오리·거위·비둘기 등） |
| 돼지 | faction ∈ {tree, flora, veg, fruit, insect, crop}；**개 미포함** |
| 소, 말 | faction ∈ {flora, crop} 또는 tag `tree_seedling`; 곤충과 고기 미포함 |
| 코끼리 | 5.5 참조, 이 표 경유 안 함 |

효과:

- 전체 구간 소거, 포식 애니메이션 재생（상성자가 먼저 「먹고」 사냥감과 함께 퇴장하거나, 상성자 잔류——**1단계는 통일해 전체 퇴장**, 잔류 상성자가 낙하 밸런스 망가뜨리는 것 방지; 체감이 약하면 2단계에서 「상성자 잔류」 스위치로 변경).
- 기초 생태 점수가 동종보다 높음.
- 상성자 원래 칸에 **비료** 생성.
- `ecoChainStreak += 1`; 같은 연쇄 안에서 생태 여러 번이어도 streak 카운트 노드는 한 번만 추가（전체 resolve 종료 시 +1, 단일 낙하로 스킬 꽉 채우는 것 방지）.

**닭은 오곡을 먹지 않음**: 작물과 닭은 같은 판에 있을 수 있지만 생태 run을 만들 수 없음; 작물은 동종 소거로만 소거.

### 5.5 코끼리

- 전역 판면 최대 1마리; 리젠 가중치 극도로 낮음; `combo >= 5` 보상 생성 또는 스테이지 `initialPieces` 배치만.
- run에 코끼리 1마리 + 임의 비농기구·비장애물 말 2개 → 이 3칸 클리어（다른 진영 포함 가능）.
- 코끼리는 **농기구를 소거할 수 없음**（농기구가 보드에 없으니 자연 충족）과 장애물（장애물은 run에 안 들어감）.
- 「코끼리 축제」 스테이지: 시작 시 1마리, `locked = true`, 원래 칸에서 교환 불가; 사냥감을 옆으로 교환해와 run 형성.

### 5.6 연쇄, 중력, 리젠

```
resolve:
  detect runs
  none이면 → idle
  점수, overlay, 인접 장애물 hp 적용
  Clear emit
  gravity: 각 열이 아래에서 위로 압축, stone/tree 실심 장애물 건너뜀; puddle은 통과 차단
  refill: 열 상단부터 SpawnPool로 보충（maxApex 제약）
  combo++
  goto detect
```

인접 소거가 장애물 HP 감소: 돌은 인접 동종/생태마다 -1, HP=0이면 분쇄; 나무는 기본 **괭이** 또는 스테이지 「돼지 3마리 범위 받아치기」 또는 돼지 생태（사냥감에 나무 포함）로만 HP 감소. 파괴왕 스테이지 나무 HP=5.

물통 스킬: 칸 하나의 웅덩이 선택 → overlay 제거, 그 열에 즉시 gravity 한 번.

### 5.7 스킬 슬롯（농기구）

| 스킬 | 해금 | 효과 |
|------|------|------|
| 낫 | 연속 3번 resolve에 생태 포함 | 한 줄 또는 한 열 클릭, 그 라인의 **plant 역할 전부**（crop/veg/fruit/flora）소거, 스텝 미소모, 충전 소모 |
| 괭이 | 위와 동일, 또는 스테이지 프리셋 | 돌/나무 클릭, 직접 HP=0 또는 -3（스테이지 설정） |
| 물통 | 스테이지 프리셋 또는 충전 | 웅덩이 한 칸 물 빼기 |

충전 규칙: `ecoResolveCount`가 3 도달 → 슬롯 +1, 카운트 초기화. 슬롯 상한 2. 낫/괭이/물통은 스테이지 `allowedSkills[]`로 어느 것이 등장할지 결정.

### 5.8 계수

```
same_3     = 10 * n * combo * fertilizerMul
eco        = 25 * n * combo * fertilizerMul
elephant   = 40 * n * combo
skill_clear= 8  * n
obstacle   = 20 * brokenCount
```

`combo`는 1부터 시작, 연쇄마다 +1, 플레이어의 다음 수동 swap에서 리셋. 비료는 「그 칸이 소거된 그 한 번」에만 작용.

---

## 6. 스테이지 기능

| 스테이지 | 풀 | 승리 | 패배 | 특징 |
|------|----|------|------|------|
| 풍년 | crop/veg/fruit + 고가중치 poultry | 20스텝 내 plant 50개 소거 | 스텝 소진 | 닭·오리·거위가 식물 동종 소거 방해 |
| 쫓아내기 | poultry + dog, 식물 없음 | 제한 시간 내 개 생태로 닭/오리 15마리 소거 | 시간 초과 | 가금 동종 소거는 목표 미포함, 반드시 생태여야 함 |
| 파괴왕 | 식물 + 소량 pig + 나무 3그루(HP5) | 돼지로 나무 3그루 받아 넘기기 | 스텝 소진 | 돼지 3마리 직선이 **3×3 받아치기** 발동（스테이지 규칙, 전역 아님） |
| 코끼리 축제 | 혼합 풀 + 시작 잠금 코끼리 | 코끼리 규칙으로 30말 소거 | 코끼리가 비정상적으로 이동되거나（없어야 함）스텝 소진 | 코끼리 보호; 시스템이 두 번째 리젠 안 함 |

공통 HUD: 목표 진행도, 스텝 또는 카운트다운, combo, 스킬 슬롯, 일시정지/나가기.

승패 판정은 한 번의 resolve（전체 연쇄 애니메이션 포함）종료 후에 해, 애니메이션 중 오판 방지.

---

## 7. Three.js 표현 레이어

| 모듈 | 책임 |
|------|------|
| SceneRoot | WebGLRenderer, 톤 매핑, resize, dpr 상한 2 |
| CameraRig | OrthographicCamera, 앙각 약 45°, 보드 중심 lookAt, OrbitControls 금지 |
| Lights | Directional（태양）+ Hemisphere（환경）+ 약한 Rim; 실시간 그림자 없음 또는 보드만 저해상도 shadow 수신 |
| BoardView | 8×8 지형; Y 기복은 perlin 프리베이크 높이맵（논리 칸은 여전히 평평） |
| PieceFactory | `template`별 Group 조립: 구/원기둥/원뿔/육면체; MeshPhongMaterial; 오브젝트 풀 |
| InputRaycaster | `Idle/Selected`일 때만 말 mesh 히트 |
| VFX | 선택 Outline（자체 발광 링, 1단계는 전체 화면 OutlinePass 안 함); 교환 GSAP; 소거 scale+입자 Points; 꽃가루/반딧불은 소량 Points 순환 |
| HUD | DOM, WebGL 밖, i18n과 접근성에 유리 |

기하 템플릿（D9）: `grain` `produce` `fruit` `flower` `bug` `bird` `beast` `tree` `apex` `rock`. 도감은 tint와 accessory만 변경.

성능 예산: 말 64 + 지형 < 200 draw call（지형은 최대한 merge）; 입자 < 400; 저사양 기기에서는 입자와 기복 끔.

---

## 8. 상태 머신

```
Boot → Title → Playing
Playing 하위 상태:
  Idle → Selected → SwapAnim → ResolveLogic → ClearAnim → GravityAnim → RefillAnim
       ↺ 매치 남아 있으면 ResolveLogic 복귀（combo）
       → Idle
  Playing → SkillTargeting → SkillAnim → ResolveLogic
  Playing → Won | Lost → Result → Title | next
  Playing → Paused → Playing
```

비 Idle/Selected/SkillTargeting 상태에서의 불법 입력은 버림.

**명령**: `Select` `Swap` `UseSkill` `Pause` `Quit` `AckResult`  
**이벤트**（EventLog 기록）: `Swapped` `RejectedSwap` `Matches` `Cleared` `Fell` `Refilled` `Combo` `SkillUsed` `Won` `Lost`

---

## 9. 플랫폼 연동

전체 인터페이스 계약（launch / balance / bet / settle / refund / play-log / 기능 스위치）은 **[api.md](api.md)** 참조. 핵심:

- 시작: `POST /api/game/launch`가 `session_id, api_endpoint, type=self` 반환, `api_endpoint?session_id=&token=` 열기.
- 지갑: `SelfProvider::bet/settle/refund`, `round_id = session_id + ':' + levelId + ':' + attempt`; 도메인 `seed = hash(session_id + round_id)`.
- 기능 스위치: `xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet`, 끄면 순수 동종 3매치로 퇴화.
- 보안: 1단계 클라이언트 권위 보드 + 서버 권위 지갑, 단일 round는 bet/settle 한 번만; 2단계 조작 시퀀스 업로드로 서버 재계산.

---

## 10. 비기능

| 항목 | 지표 |
|----|------|
| 첫 화면 | 로우 폴리 + GLTF 없음, 목표 3초 내 인터랙션 가능（Vite gzip 포함） |
| 프레임률 | 데스크톱 60fps; 내장 그래픽은 VFX 끄기 가능 |
| 테스트 | `domain/**` 단위 테스트로 매칭/중력/상성/승패 커버; WebGL 미테스트 |
| i18n | HUD 문구 key, 플랫폼 `Language` 미들웨어 따름 |
| 접근성 | 키보드 방향 선택 + Enter 교환（2단계）; 색맹: 순수 색보다 형태 템플릿 우선 |
| 용량 | FBX 미포함; three + gsap gzip 후 코드 < 250KB 목표 |

---

## 11. 단계 구분

| 단계 | 범위 | 인수 |
|----|------|------|
| P0 | 동종 3매치, 8×8, 교환/중력/리젠, 직교 씬, 템플릿 말 3종 | 목표 없이 한 판 플레이 가능 |
| P1 | 도감 + SpawnPool + 네 스테이지 목표/스텝 HUD | 풍년 스테이지 클리어 가능 |
| P2 | 상성표 + 생태 소거 + 비료 + combo | 닭+벌레 2마리 소거 가능; 오곡은 닭이 소거 못 함 |
| P3 | 돌/나무/웅덩이 + 낫/괭이/물통 | 파괴왕 스테이지 나무 분해 가능 |
| P4 | 코끼리 + 잠금 칸 + 플랫폼 bet/settle | 축제 스테이지; 잔액 대조 |
| P5 | 입자, 사운드, 오브젝트 풀, 저사양 모드, 리플레이 | 성능 예산 달성 |

P0는 지갑 연동 안 함, 로컬 `?debug=1`로 충분. P4에서야 `SelfProvider` 연동.

---

## 12. 모듈 책임 일람

| 모듈 | 입력 | 출력 | 의존 |
|------|------|------|------|
| Catalog | JSON 도감 | PieceDef | 없음 |
| RestraintTable | 상성 설정 | isEcoRun(run) | Catalog |
| Board | 명령 | 새 스냅샷 | Catalog, RNG |
| MatchDetector | 스냅샷 | runs[] | RestraintTable |
| Gravity | 스냅샷 | 스냅샷 + Fell | Board |
| Level | 소거 통계 | 진행도/승패 | Board 이벤트 |
| Score | 이벤트 | 점수 | Level（배율） |
| GameStateMachine | 명령/애니메이션 완료 | 상태 | 위 domain |
| PieceFactory | PieceDef | Object3D | render 전용 |
| PlatformAdapter | 승패/베팅 | HTTP | domain 순환 의존 없음 |
