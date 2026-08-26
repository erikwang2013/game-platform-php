# Pastoral Match-3 — Technical Architecture
<!-- lang-nav -->

Languages: [中文](architecture.md) · **English** · [한국어](architecture.ko.md) · [Русский](architecture.ru.md) · [Deutsch](architecture.de.md) · [Français](architecture.fr.md) · [Español](architecture.es.md) · [Português](architecture.pt.md) · [हिन्दी](architecture.hi.md) · [العربية](architecture.ar.md) · [বাংলা](architecture.bn.md) · [Bahasa Indonesia](architecture.id.md) · [日本語](architecture.ja.md)


> Player features and acceptance criteria see `functional-design.md`; scheduling see `plan.md`; the thematic vision see `design.md`.
>
> This document only answers how to split modules, how to connect to the platform, and which layer the rules are computed in. No implementation code.
>
> Product positioning: self-developed H5 (`game.type = self`), 8×8 sandbox match-3 + ecology restraint chain, Three.js low-poly 2.5D.

---

## 0. Architecture Decisions Relative to the Plan

The plan is the gameplay vision; the following decisions resolve the tension between "playable, testable, wallet-integrated".

| ID | Decision | Reason |
|----|------|------|
| D1 | **Encyclopedia ≠ board pieces**. The 100+ species are the encyclopedia and appearances; each level's spawn pool only draws **5–8 species** | With dozens of species on an 8×8 board, matches become nearly impossible |
| D2 | Matching has two layers: **same-type by `speciesId`, ecology by `role` + restraint table** | The plan requires both "three apples" and "chicken+bug+bug" |
| D3 | Same-line rule priority: **elephant > ecology > same-type**; mutually exclusive, no double scoring | Avoids one line being scored twice |
| D4 | **Farm tools never enter the board**, only the HUD skill slots; rocks/puddles/trees are obstacles, not swappable | The plan's section 5 conflicts with the piece library; skills + obstacles win |
| D5 | **Domain logic has zero Three.js dependency**, pure functions + snapshots; the presentation layer only subscribes to events | Rules can be unit-tested, replayed, and later server-validated |
| D6 | On launch, `session_id` derives a **deterministic RNG seed**; all drops/refills go through that RNG | Same seed can be replayed; leaves a door open for anti-cheat |
| D7 | No physics engine. Movement/bouncing/clearing use easing, no Cannon/Rapier | The plan already says "simulated animation"; physics has no benefit for a grid game |
| D8 | Camera is **orthographic 2.5D fixed position**, orbit controls disabled | Consistent with the plan, avoids misoperation and motion sickness |
| D9 | Species share **faction geometry templates + colors/accessories**, no per-crop modeling | Traffic and schedule; visual variety comes from coloring and one distinguishing part |
| D10 | Level entry uses `SelfProvider::bet`, clear uses `settle`, mid-level failure does not refund the entry fee; `refund` if the first step was never taken | Aligns with the platform wallet and round idempotency |

---

## 1. System Context

```
Flutter / HarmonyOS / PC Web
        │  POST /api/game/launch { game_id }
        ▼
service/ (webman :8788)
  GameController::launch  → session_id + seed + api_endpoint
  SelfProvider            → bet / settle / refund / getBalance
  GamePlayLog + EventBus  → game.played / 成就 / VIP
        │  打开 api_endpoint?session_id=&token=
        ▼
game/xiaoxiaole/  (静态资源，Nginx)
  Vite + TypeScript + Three.js
  领域引擎 ──事件──► 渲染 / HUD
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

The game is a **static frontend**; the authoritative session and money live in `service/`. The client holds the board state; the server holds the balance and round idempotency. Phase 1 does not do per-move server-side validation, but the domain layer must be deterministic so that phase 2 can send `seed + 操作序列` to the server for recomputation.

---

## 2. Client Layering

Top-down, cross-layer back-dependencies are forbidden (`render` must not be imported by `domain`).

```
app/          组装、状态机、关卡生命周期
hud/          HTML Overlay：分数、步数、目标、技能、结果
platform/     launch 参数、钱包、play-log、特性开关
render/       Three.js：场景、棋盘、棋子网格、输入、VFX
runtime/      命令总线、动画队列、回放
domain/       棋盘、匹配、克制、重力、分数、目录、关卡规则
config/       克制表、刷新权重、几何配方、关卡 JSON
```

**Main loop (rules are NOT computed inside `requestAnimationFrame`)**: input → command → domain synchronous resolution (one swap computes all chains, producing an event list) → runtime queues animations by event → the next input is only accepted when animations finish.

This way "one logic frame, many render frames", and combos never race with clicks for state.

---

## 3. Directory Structure (Proposed)

```
game/xiaoxiaole/
├── design.md
├── architecture.md          ← 本文
├── package.json             # vite, three, gsap, vitest, typescript
├── index.html
├── src/
│   ├── main.ts              # 读 URL，启动 GameApp
│   ├── app/
│   │   ├── GameApp.ts
│   │   └── GameStateMachine.ts
│   ├── domain/
│   │   ├── catalog/         # PieceDef, Faction, Role
│   │   ├── board/           # Grid 8×8, Cell, PieceInstance
│   │   ├── match/           # MatchDetector（同种 / 生态 / 大象）
│   │   ├── eco/             # RestraintTable
│   │   ├── gravity/         # 下落、水洼阻断、刷新
│   │   ├── score/           # 计分、肥料倍率
│   │   ├── level/           # LevelDef, Objective, Win/Lose
│   │   └── rng/             # seeded mulberry32
│   ├── runtime/
│   │   ├── CommandBus.ts    # Select, Swap, UseSkill, Quit
│   │   ├── EventLog.ts      # 可序列化事件，供回放
│   │   └── AnimationQueue.ts
│   ├── render/
│   │   ├── SceneRoot.ts
│   │   ├── CameraRig.ts
│   │   ├── BoardView.ts
│   │   ├── PieceFactory.ts  # 模板几何
│   │   ├── InputRaycaster.ts
│   │   └── vfx/
│   ├── hud/
│   ├── platform/
│   │   ├── ApiClient.ts
│   │   └── Session.ts
│   └── levels/*.json
└── tests/domain/            # 无 WebGL
```

No single file exceeds 500 lines. If `MatchDetector` and `PieceFactory` bloat, split them by rule type / faction template.

---

## 4. Domain Model

### 4.1 Piece Definition (Encyclopedia)

```
PieceDef
  id            speciesId        如 wheat, hen, elephant
  faction       Faction          crop | veg | fruit | flora | insect | poultry | livestock | tree | tool | obstacle | apex
  role          Role             plant | prey | predator_mid | predator_high | apex | obstacle | skill
  tags[]                         crop, edible_by_poultry, tree_seedling, bone …
  rarity        common | rare | legendary
  template      GeometryTemplate 几何模板名
  tint          RGB              模板内配色
  accessory     optional         喙、花瓣、象鼻等区分件
```

All crops/vegetables/fruits/flowers/insects/poultry/livestock/trees from the plan enter the encyclopedia; **tool never spawns onto a cell**. Elephant `rarity = legendary`, `role = apex`.

### 4.2 Cells and Board

```
Cell
  q, r               列、行（0–7）
  height             地形起伏（仅渲染，不参与规则）
  occupant           PieceInstance | null
  overlay            none | fertilizer | puddle | stone(hp) | tree(hp)
  locked             bool          大象狂欢关：大象不可被换走

PieceInstance
  uid, speciesId, def
  special            none | fertilizer_token
```

- **Rocks / trees**: occupy a cell, cannot be swapped or fallen through. HP per level.
- **Puddles**: sit on a cell, block gravity from passing through it (pieces above stop one cell above the puddle).
- **Fertilizer**: stays on the cell after an eco clear; the next time that cell participates in a clear, the score ×2, then it disappears.

### 4.3 Spawn Pool (Level)

```
SpawnPool
  speciesIds[]       5–8 个
  weights[]          与 species 对齐
  maxApex            默认 1
  apexUnlock         combo >= 5 时由系统生成，禁止「交换生成」
```

A level only draws pieces from its own pool via `rng`. However large the encyclopedia, board entropy stays controllable.

---

## 5. Core Rules Engine (Functional Design)

### 5.1 Operations

1. Click piece A → selected (bounce + outline).
2. Click adjacent orthogonal cell B → attempt swap (diagonal swaps forbidden).
3. Click a non-adjacent/empty cell → reselect or cancel.
4. If the swap produces **no legal match**, play the swap-back animation, no step consumed.
5. If there is a match, 1 step is consumed, entering resolution.

Obstacle cells cannot be selected as swap targets. Locked cells (level rules) likewise.

### 5.2 Line Scanning

For each post-swap board:

- Horizontal/vertical runs of length ≥ 3 form a **run**.
- A run only applies one rule (D3).
- Multiple runs may intersect (classic L/T shapes), intersection cells are only cleared once.

### 5.3 Same-Type Clear

All `speciesId` in a run identical, and not obstacles, not elephant privilege (handled separately).

- 3-match: base score.
- 4-match: bonus score, drops **fertilizer** on the center cell (same overlay as eco fertilizer).
- 5-match: bonus score, skill slot +1 charge (see 5.7).

### 5.4 Eco Clear (Restraint Chain)

Rule: **exactly 1 predator + the rest all that predator's prey** (3 cells = 1+2). Prey need not be the same species.

| Predator | Prey match |
|--------|----------|
| Chicken, duck, goose | faction ∈ {flora, veg, fruit, insect}; **not crop (grains)** |
| Dog | faction = poultry (chickens/ducks/geese/pigeons etc.) |
| Pig | faction ∈ {tree, flora, veg, fruit, insect, crop}; **not dog** |
| Cattle, horse | faction ∈ {flora, crop} or tag `tree_seedling`; not insects or meat |
| Elephant | See 5.5, does not use this table |

Effects:

- The whole segment clears, playing the predation animation (the predator "eats" first, then leaves with the prey, or the predator stays — **Phase 1: the whole segment leaves uniformly**, avoiding leftover predators breaking drop balance; if the feel is weak, Phase 2 can switch to a "predator stays" flag).
- Base eco score is higher than same-type.
- **Fertilizer** spawns on the predator's original cell.
- `ecoChainStreak += 1`; multiple eco clears in the same chain only add one streak node (counted once at the end of the whole resolve, avoiding one drop chain maxing out skills).

**Chickens do not eat grains**: crops and chickens can share a board, but cannot form an eco run; crops can only be cleared same-type.

### 5.5 Elephant

- At most 1 on the whole board; extremely low spawn weight; only generated as a reward at `combo >= 5`, or placed by the level's `initialPieces`.
- A run containing 1 elephant + any 2 non-tool, non-obstacle pieces → clears these 3 cells (may include different factions).
- The elephant **cannot** clear tools (tools are not on the board, naturally satisfied) or obstacles (obstacles do not enter runs).
- "Elephant Carnival" level: 1 at start, `locked = true`, cannot be swapped away; prey are swapped next to it to form runs.

### 5.6 Chains, Gravity, Refill

```
resolve:
  detect runs
  if none → idle
  apply scores, overlays, hp 对相邻障碍
  emit Clear
  gravity: 每列从底向上压实，跳过 stone/tree 实心障碍；puddle 阻断穿过
  refill: 从列顶按 SpawnPool 补子（受 maxApex 约束）
  combo++
  goto detect
```

Adjacent clears damage obstacles: a rock takes -1 per adjacent same-type/eco clear, shatters at HP=0; a tree only loses HP from the **hoe** or a level's "three-pig rampage" or pig eco clears (prey includes trees). Destroyer King level tree HP = 5.

Water bucket skill: select one puddle cell → overlay cleared, that column immediately gets one gravity pass.

### 5.7 Skill Slots (Farm Tools)

| Skill | Unlock | Effect |
|------|------|------|
| Sickle | 3 consecutive resolves containing an eco clear | Click a row or column, clears all **plant** role pieces on that line (crop/veg/fruit/flora), no step consumed, consumes charge |
| Hoe | Same, or pre-set by the level | Click a rock/tree, directly HP=0 or -3 (level config) |
| Water bucket | Pre-set by the level or charge | Drains one puddle cell |

Charge rule: `ecoResolveCount` reaches 3 → slot +1, counter reset. Slot cap 2. Which of sickle/hoe/water bucket appear is decided by the level's `allowedSkills[]`.

### 5.8 Scoring

```
same_3     = 10 * n * combo * fertilizerMul
eco        = 25 * n * combo * fertilizerMul
elephant   = 40 * n * combo
skill_clear= 8  * n
obstacle   = 20 * brokenCount
```

`combo` starts at 1, +1 per chain, reset on the player's next manual swap. Fertilizer only applies to "the clear where that cell is removed".

---

## 6. Level Features

| Level | Pool | Win | Lose | Feature |
|------|----|------|------|------|
| Harvest | crop/veg/fruit + high-weight poultry | Clear 50 plants within 20 steps | Steps exhausted | Chickens/ducks/geese interfere with plant same-type clears |
| Herding | poultry + dog, no plants | Use dog eco clears to remove 15 chickens/ducks within the time limit | Timeout | Same-type clears of poultry don't count toward the goal, ecology is required |
| Destroyer King | plants + few pigs + 3 trees (HP5) | Pigs ram down 3 trees | Steps exhausted | Three pigs in a line trigger a **3×3 rampage** (level rule, not global) |
| Elephant Carnival | mixed pool + locked elephant at start | Clear 30 pieces with the elephant rule | Elephant abnormally moved out (should not happen) or steps exhausted | Protect the elephant; the system never spawns a second one |

Common HUD: objective progress, steps or countdown, combo, skill slots, pause/quit.

Win/loss is judged after one resolve (including all chain animations) completes, avoiding mid-animation misjudgment.

---

## 7. Three.js Presentation Layer

| Module | Responsibility |
|------|------|
| SceneRoot | WebGLRenderer, tone mapping, resize, dpr cap 2 |
| CameraRig | OrthographicCamera, pitch ~45°, lookAt board center, OrbitControls disabled |
| Lights | Directional (sun) + Hemisphere (ambient) + weak Rim; no real-time shadows or low-res shadows only on the board |
| BoardView | 8×8 terrain patches; Y variation uses a pre-baked perlin height map (logic cells stay flat) |
| PieceFactory | Builds Groups by `template`: sphere/cylinder/cone/cube; MeshPhongMaterial; object pool |
| InputRaycaster | Only hits piece meshes in `Idle/Selected` |
| VFX | Selected Outline (self-drawn glow ring, no full-screen OutlinePass in Phase 1); swap GSAP; clear scale + particle Points; pollen/fireflies with a small looping Points system |
| HUD | DOM, not in WebGL, for easy i18n and accessibility |

Geometry templates (D9): `grain` `produce` `fruit` `flower` `bug` `bird` `beast` `tree` `apex` `rock`. The encyclopedia only changes tint and accessory.

Performance budget: 64 pieces + terrain patches < 200 draw calls (merge patches where possible); particles < 400; low-end devices disable particles and height variation.

---

## 8. State Machine

```
Boot → Title → Playing
Playing 子状态:
  Idle → Selected → SwapAnim → ResolveLogic → ClearAnim → GravityAnim → RefillAnim
       ↺ 若仍有匹配则回 ResolveLogic（combo）
       → Idle
  Playing → SkillTargeting → SkillAnim → ResolveLogic
  Playing → Won | Lost → Result → Title | next
  Playing → Paused → Playing
```

Illegal input is dropped outside Idle/Selected/SkillTargeting.

**Commands**: `Select` `Swap` `UseSkill` `Pause` `Quit` `AckResult`  
**Events** (written to EventLog): `Swapped` `RejectedSwap` `Matches` `Cleared` `Fell` `Refilled` `Combo` `SkillUsed` `Won` `Lost`

---

## 9. Platform Integration

The complete interface contract (launch / balance / bet / settle / refund / play-log / feature flags) is in **[api.md](api.md)**. Key points:

- Launch: `POST /api/game/launch` returns `session_id, api_endpoint, type=self`, open `api_endpoint?session_id=&token=`.
- Wallet: `SelfProvider::bet/settle/refund`, `round_id = session_id + ':' + levelId + ':' + attempt`; domain `seed = hash(session_id + round_id)`.
- Feature flags: `xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet`; when off, degenerates to pure same-type match-3.
- Security: Phase 1 client-authoritative board + server-authoritative wallet, a round can only bet/settle once; Phase 2 uploads the operation sequence for server-side recomputation.

---

## 10. Non-Functional

| Item | Target |
|----|------|
| First screen | Low-poly + no GLTF, target interactive within 3s (incl. Vite gzip) |
| Frame rate | 60fps on desktop; VFX can be disabled on integrated GPUs |
| Tests | `domain/**` unit tests cover match/gravity/restraint/win-loss; no WebGL tests |
| i18n | HUD text keys, follows the platform `Language` middleware |
| Accessibility | Keyboard directional selection + Enter swap (Phase 2); color blindness: shape templates over solid colors |
| Size | No FBX; three + gsap gzipped, aim for < 250KB code |

---

## 11. Phases

| Phase | Scope | Acceptance |
|----|------|------|
| P0 | Same-type match-3, 8×8, swap/gravity/refill, orthographic scene, 3 template pieces | Playable a round without objectives |
| P1 | Encyclopedia + SpawnPool + four levels' objectives/steps HUD | Harvest level can be cleared |
| P2 | Restraint table + eco clears + fertilizer + combo | Chicken + two bugs clears; grains not cleared by chickens |
| P3 | Rocks/trees/puddles + sickle/hoe/water bucket | Destroyer King level can break trees |
| P4 | Elephant + locked cells + platform bet/settle | Carnival level; balance reconciliation |
| P5 | Particles, sound, object pool, low-end profile, replay | Performance budget met |

P0 does not connect the wallet, local `?debug=1` suffices. `SelfProvider` is only connected at P4.

---

## 12. Module Responsibilities Overview

| Module | Input | Output | Dependencies |
|------|------|------|------|
| Catalog | JSON encyclopedia | PieceDef | None |
| RestraintTable | Restraint config | isEcoRun(run) | Catalog |
| Board | Commands | New snapshot | Catalog, RNG |
| MatchDetector | Snapshot | runs[] | RestraintTable |
| Gravity | Snapshot | Snapshot + Fell | Board |
| Level | Clear stats | Progress/win-loss | Board events |
| Score | Events | Score | Level (multiplier) |
| GameStateMachine | Commands/animation completion | State | The above domain |
| PieceFactory | PieceDef | Object3D | render only |
| PlatformAdapter | Win-loss/bets | HTTP | No domain circular dependency |
