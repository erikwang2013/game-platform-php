# 田园消消乐 — 技术架构
<!-- lang-nav -->

Languages: **中文** · [English](architecture.en.md) · [한국어](architecture.ko.md) · [Русский](architecture.ru.md) · [Deutsch](architecture.de.md) · [Français](architecture.fr.md) · [Español](architecture.es.md) · [Português](architecture.pt.md) · [हिन्दी](architecture.hi.md) · [العربية](architecture.ar.md) · [বাংলা](architecture.bn.md) · [Bahasa Indonesia](architecture.id.md) · [日本語](architecture.ja.md)


> 玩家功能与验收见 `functional-design.md`；排期见 `plan.md`；主题愿景见 `design.md`。
>
> 本文只回答怎么拆模块、怎么接平台、规则在哪一层算。不写实现代码。
>
> 产品定位：自研 H5（`game.type = self`），8×8 沙盘三消 + 生态克制链，Three.js 低多边形 2.5D。

---

## 0. 相对规划的架构决策

规划是玩法愿景；下列决策解决「可玩、可测、可接入钱包」的矛盾。

| ID | 决策 | 原因 |
|----|------|------|
| D1 | **图鉴 ≠ 同盘棋子**。100+ 物种是图鉴与外观；单关刷新池只抽 **5–8 个物种** | 8×8 同时出现几十种几乎无法成消 |
| D2 | 匹配分两层：**同种按 `speciesId`，生态按 `role` + 克制表** | 规划同时要求「三个苹果」和「鸡+虫+虫」 |
| D3 | 同一线段规则优先级：**大象 > 生态 > 同种**；互斥，不重复计分 | 避免一行同时吃分两次 |
| D4 | **农具不进棋盘**，只在 HUD 技能槽；石头/水洼/树是障碍，不可交换 | 规划第五节与棋子库冲突，以技能+障碍为准 |
| D5 | **领域逻辑零 Three.js 依赖**，纯函数 + 快照；表现层只订阅事件 | 规则可单测、可回放、可日后服务端校验 |
| D6 | 开局 `session_id` 派生 **确定性 RNG seed**；掉落/刷新全部走该 RNG | 同一 seed 可复盘；为反作弊留口 |
| D7 | 无物理引擎。位移/弹跳/消除用缓动，不引入 Cannon/Rapier | 规划已写「模拟动画」；物理对网格游戏无收益 |
| D8 | 相机 **正交 2.5D 固定机位**，关闭轨道控制 | 与规划一致，避免误操作与眩晕 |
| D9 | 物种共用 **阵营几何模板 + 颜色/配件**，不为每种作物单独建模 | 流量与工期；视觉差异靠配色与一枚特征件 |
| D10 | 关卡入场走 `SelfProvider::bet`，通关 `settle`，中途失败不退入场费；未走第一步可 `refund` | 对齐平台钱包与 round 幂等 |

---

## 1. 系统上下文

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

游戏是**静态前端**，权威会话与钱在 `service/`。客户端持有棋盘状态；服务端持有余额与 round 幂等。第一期不做每步服务端校验，但领域层必须确定性，以便第二期把 `seed + 操作序列` 送到服务端复算。

---

## 2. 客户端分层

自上而下，禁止跨层倒依赖（`render` 不得被 `domain` import）。

```
app/          组装、状态机、关卡生命周期
hud/          HTML Overlay：分数、步数、目标、技能、结果
platform/     launch 参数、钱包、play-log、特性开关
render/       Three.js：场景、棋盘、棋子网格、输入、VFX
runtime/      命令总线、动画队列、回放
domain/       棋盘、匹配、克制、重力、分数、目录、关卡规则
config/       克制表、刷新权重、几何配方、关卡 JSON
```

**主循环（非 `requestAnimationFrame` 里算规则）**：输入 → 命令 → 领域同步结算（一次 swap 算完所有连锁，产出事件列表）→ 运行时按事件排队播放动画 → 动画结束才接受下一次输入。

这样「逻辑一帧、表现多帧」，combo 不会和点击抢状态。

---

## 3. 目录结构（建议）

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

单文件不超过 500 行。`MatchDetector` 与 `PieceFactory` 若膨胀，按规则类型 / 阵营模板再拆。

---

## 4. 领域模型

### 4.1 棋子定义（图鉴）

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

规划中的作物/蔬菜/水果/花草/昆虫/家禽/家畜/树木全部进入图鉴；**tool 不生成到格子**。大象 `rarity = legendary`，`role = apex`。

### 4.2 格子与棋盘

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

- **石头 / 树**：占格，不可交换、不可下落穿过。HP 见关卡。
- **水洼**：叠在格上，阻断重力穿过该格（上方棋子停在水洼上一格）。
- **肥料**：生态消除后留在该格；下一次该格参与的消除得分 ×2，然后消失。

### 4.3 刷新池（关卡）

```
SpawnPool
  speciesIds[]       5–8 个
  weights[]          与 species 对齐
  maxApex            默认 1
  apexUnlock         combo >= 5 时由系统生成，禁止「交换生成」
```

关卡只从本池 `rng` 抽子。图鉴再大，盘面熵可控。

---

## 5. 核心规则引擎（功能设计）

### 5.1 操作

1. 点击棋子 A → 选中（弹跳 + 描边）。
2. 再点相邻正交格 B → 尝试交换（禁止斜换）。
3. 再点不相邻 / 空白 → 改选或取消。
4. 交换后若**无任何合法匹配**，播放换回，不耗步。
5. 有匹配则耗 1 步，进入结算。

障碍格不能被选为交换目标。锁定格（关卡规则）同理。

### 5.2 线段扫描

对每个 swap 后的盘面：

- 横、竖连续格子，长度 ≥ 3 为一条 **run**。
- 一条 run 只套用一种规则（D3）。
- 多条 run 可相交（经典 L/T 形），相交格只消除一次。

### 5.3 同种消除

run 内 `speciesId` 全相同，且非障碍、非大象特权单独处理。

- 3 消：基础分。
- 4 消：额外分，并在中心格掉落 **肥料**（与生态肥料同 overlay）。
- 5 消：额外分，技能槽 +1 充能（见 5.7）。

### 5.4 生态消除（克制链）

判定：**恰好 1 个克制者 + 其余全是该克制者的猎物**（3 格即为 1+2）。不要求猎物同种。

| 克制者 | 猎物匹配 |
|--------|----------|
| 鸡、鸭、鹅 | faction ∈ {flora, veg, fruit, insect}；**不含 crop（五谷）** |
| 狗 | faction = poultry（鸡鸭鹅鸽等） |
| 猪 | faction ∈ {tree, flora, veg, fruit, insect, crop}；**不含狗** |
| 牛、马 | faction ∈ {flora, crop} 或 tag `tree_seedling`；不含昆虫与肉类 |
| 大象 | 见 5.5，不走本表 |

效果：

- 整段消除，播放捕食动画（克制者先「吃」，再与猎物一起离场，或克制者留场——**第一期统一整段离场**，避免残留克制者破坏掉落平衡；若体验偏弱，第二期再改为「克制者留场」开关）。
- 基础生态分高于同种。
- 克制者原格生成 **肥料**。
- `ecoChainStreak += 1`；同一次连锁里多次生态只加一次 streak 计数节点（整次 resolve 结束时 +1，避免单次掉落刷满技能）。

**鸡不吃五谷**：作物与鸡可同盘，但不能组成生态 run；只能靠同种消作物。

### 5.5 大象

- 全局盘面最多 1 只；刷新权重极低；仅 `combo >= 5` 的奖励生成，或关卡 `initialPieces` 放入。
- run 含 1 只大象 + 任意 2 个非农具、非障碍棋子 → 清空这 3 格（可含不同阵营）。
- 大象**不能**消除农具（农具不在棋盘，自然满足）和障碍（障碍不进入 run）。
- 「大象狂欢」关：开局 1 只，`locked = true`，不能被换离原格；猎物被换到它旁边形成 run。

### 5.6 连锁、重力、刷新

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

相邻消除对障碍扣 HP：石头每次相邻同种/生态 -1，HP=0 碎裂；树默认仅 **锄头** 或关卡「三猪范围拱」或猪生态（猎物含树）扣 HP。破坏王关树 HP=5。

水桶技能：选定一格水洼 → overlay 清除，该列立即补一次 gravity。

### 5.7 技能槽（农具）

| 技能 | 解锁 | 效果 |
|------|------|------|
| 镰刀 | 连续 3 次 resolve 含生态 | 点一行或一列，清除该线所有 **plant 角色**（crop/veg/fruit/flora），不耗步，消耗充能 |
| 锄头 | 同上，或关卡预置 | 点石头/树，直接 HP=0 或 -3（关卡配置） |
| 水桶 | 关卡预置或充能 | 抽干一格水洼 |

充能规则：`ecoResolveCount` 达到 3 → 槽位 +1，计数清零。槽位上限 2。镰刀/锄头/水桶由关卡 `allowedSkills[]` 决定出现哪几个。

### 5.8 计分

```
same_3     = 10 * n * combo * fertilizerMul
eco        = 25 * n * combo * fertilizerMul
elephant   = 40 * n * combo
skill_clear= 8  * n
obstacle   = 20 * brokenCount
```

`combo` 从 1 起，每次连锁 +1，玩家下一次手动 swap 重置。肥料只作用于「该格被消除的那一次」。

---

## 6. 关卡功能

| 关卡 | 池 | 胜利 | 失败 | 特色 |
|------|----|------|------|------|
| 丰收 | crop/veg/fruit + 高权重 poultry | 20 步内消除 50 个 plant | 步数耗尽 | 鸡鸭鹅干扰植物同种消 |
| 驱赶 | poultry + dog，无植物 | 限时内用狗生态消 15 只鸡/鸭 | 超时 | 同种消家禽不计入目标，必须生态 |
| 破坏王 | 植物 + 少量 pig + 3 棵树(HP5) | 猪拱掉 3 棵树 | 步数耗尽 | 三只猪直线触发 **3×3 拱击**（关卡规则，非全局） |
| 大象狂欢 | 混合池 + 开局锁定大象 | 大象规则消除 30 子 | 大象被异常移出（不应发生）或步数尽 | 保护大象；系统不刷第二只 |

通用 HUD：目标进度、步数或倒计时、combo、技能槽、暂停/退出。

胜负判定在一次 resolve（含全部连锁动画）结束后结算，避免动画中途误判。

---

## 7. Three.js 表现层

| 模块 | 职责 |
|------|------|
| SceneRoot | WebGLRenderer、色调映射、resize、dpr 上限 2 |
| CameraRig | OrthographicCamera，俯仰约 45°，lookAt 棋盘中心，禁止 OrbitControls |
| Lights | Directional（日）+ Hemisphere（环境）+ 弱 Rim；无实时阴影或仅棋盘接收低分辨率 shadow |
| BoardView | 8×8 地块；Y 起伏用 perlin 预烘焙高度图（逻辑格仍平坦） |
| PieceFactory | 按 `template` 组 Group：球/圆柱/圆锥/立方；MeshPhongMaterial；对象池 |
| InputRaycaster | 只在 `Idle/Selected` 命中棋子 mesh |
| VFX | 选中 Outline（自绘发光环，第一期不做满屏 OutlinePass）；交换 GSAP；消除 scale+粒子 Points；花粉/萤火虫用少量 Points 循环 |
| HUD | DOM，不进 WebGL，便于 i18n 与无障碍 |

几何模板（D9）：`grain` `produce` `fruit` `flower` `bug` `bird` `beast` `tree` `apex` `rock`。图鉴只改 tint 与 accessory。

性能预算：64 棋子 + 地块 < 200 draw call（尽量 merge 地块）；粒子 < 400；低端机关闭粒子与起伏。

---

## 8. 状态机

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

非法输入在非 Idle/Selected/SkillTargeting 时丢弃。

**命令**：`Select` `Swap` `UseSkill` `Pause` `Quit` `AckResult`  
**事件**（写入 EventLog）：`Swapped` `RejectedSwap` `Matches` `Cleared` `Fell` `Refilled` `Combo` `SkillUsed` `Won` `Lost`

---

## 9. 平台接入

完整接口契约（launch / balance / bet / settle / refund / play-log / 特性开关）见 **[api.md](api.md)**。要点：

- 启动：`POST /api/game/launch` 返回 `session_id, api_endpoint, type=self`，打开 `api_endpoint?session_id=&token=`。
- 钱包：`SelfProvider::bet/settle/refund`，`round_id = session_id + ':' + levelId + ':' + attempt`；领域 `seed = hash(session_id + round_id)`。
- 特性开关：`xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet`，关闭时退化为纯同种三消。
- 安全：第一期客户端权威棋盘 + 服务端权威钱包，单 round 只 bet/settle 一次；第二期上传操作序列由服务端复算。

---

## 10. 非功能

| 项 | 指标 |
|----|------|
| 首屏 | 低多边形 + 无 GLTF，目标 3s 内可交互（含 Vite gzip） |
| 帧率 | 桌面 60fps；集成显卡可关 VFX |
| 测试 | `domain/**` 单测覆盖匹配/重力/克制/胜负；不测 WebGL |
| i18n | HUD 文案 key，跟平台 `Language` 中间件 |
| 无障碍 | 键盘方向选择 + Enter 交换（第二期）；色盲：形状模板优先于纯色 |
| 体积 | 不含 FBX；three + gsap gzip 后争取 < 250KB 代码 |

---

## 11. 分期

| 期 | 范围 | 验收 |
|----|------|------|
| P0 | 同种三消、8×8、交换/重力/刷新、正交场景、3 个模板棋子 | 可玩一局无目标 |
| P1 | 图鉴 + SpawnPool + 四关目标/步数 HUD | 丰收关可通关 |
| P2 | 克制表 + 生态消除 + 肥料 + combo | 鸡+两虫可消；五谷不被鸡消 |
| P3 | 石头/树/水洼 + 镰刀/锄头/水桶 | 破坏王关可拆树 |
| P4 | 大象 + 锁定格 + 平台 bet/settle | 狂欢关；余额对账 |
| P5 | 粒子、音效、对象池、低端机档、回放 | 性能预算达标 |

P0 不接钱包，本地 `?debug=1` 即可。P4 才对接 `SelfProvider`。

---

## 12. 模块职责一览

| 模块 | 输入 | 输出 | 依赖 |
|------|------|------|------|
| Catalog | JSON 图鉴 | PieceDef | 无 |
| RestraintTable | 克制配置 | isEcoRun(run) | Catalog |
| Board | 命令 | 新快照 | Catalog, RNG |
| MatchDetector | 快照 | runs[] | RestraintTable |
| Gravity | 快照 | 快照 + Fell | Board |
| Level | 消除统计 | 进度/胜负 | Board 事件 |
| Score | 事件 | 分数 | Level（倍率） |
| GameStateMachine | 命令/动画完成 | 状态 | 以上 domain |
| PieceFactory | PieceDef | Object3D | 仅 render |
| PlatformAdapter | 胜负/下注 | HTTP | 无 domain 循环依赖 |
