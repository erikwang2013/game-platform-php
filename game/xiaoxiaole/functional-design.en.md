# Pastoral Match-3 — Functional Design
<!-- lang-nav -->

Languages: [中文](functional-design.md) · **English** · [한국어](functional-design.ko.md) · [Русский](functional-design.ru.md) · [Deutsch](functional-design.de.md) · [Français](functional-design.fr.md) · [Español](functional-design.es.md) · [Português](functional-design.pt.md) · [हिन्दी](functional-design.hi.md) · [العربية](functional-design.ar.md) · [বাংলা](functional-design.bn.md) · [Bahasa Indonesia](functional-design.id.md) · [日本語](functional-design.ja.md)


> The specification of what players can see, do, and accept. For the technical layering see `architecture.md`; for the element vision see `design.md`; for scheduling see `plan.md`.
>
> In one sentence: swap adjacent pieces on a 3D pastoral sandbox, clear the board with "three identical" or "predator eats prey", and complete the level objectives.

---

## 1. Product Definition

| Item | Content |
|----|------|
| Name | Pastoral Match-3 |
| Type | 8×8 match-3 + ecology restraint |
| View | Fixed 2.5D orthographic sandbox, not rotatable |
| Controls | Click two adjacent pieces to swap (up/down/left/right only) |
| Platform form | Self-developed H5, opened via `launch` from the game lobby |
| Success experience | Match-3 is learned instantly; the first "chicken eats bug" feels like a rule upgrade; chain drops have rhythm |

**Not in V1:** in-round real-time leaderboards, friend spectating, piece progression, open world, GLTF detailed models, player-customized levels.

---

## 2. Player Flow

```
大厅点「开始」
  → 加载页（读 session）
  → 选关（四关列表 + 余额，P4 才显示扣费）
  → 对局
       HUD：目标 / 步数或倒计时 / 分数 / combo / 技能槽
       棋盘：点击选中 → 点相邻交换
       无消：弹回，不扣步
       有消：扣 1 步 → 消除动画 → 下落 → 补子 → 自动连锁
  → 通关 / 失败结算
  → 下一关 / 重试 / 回选关
```

The first time entering the "Harvest" level, 3 hints pop up then disappear; skippable, never shown again (localStorage).

---

## 3. Core Loop

1. Look at the objective (how many plants / poultry / trees / elephant-stomped cells remain).
2. Find a same-type triple, or move a predator next to two prey.
3. Swap → clear → drop chain.
4. Eco clears leave fertilizer on the original cell; that cell's next clear scores ×2.
5. Complete 3 resolutions containing an eco clear; the skill slot lights up; use the sickle/hoe/water bucket to break a stalemate.
6. Objective met with steps/time remaining → victory.

---

## 4. UI

| Screen | Elements | Behavior |
|------|------|------|
| Loading | Game name, progress | Invalid session prompts return to lobby |
| Level select | Four level cards: name, objective summary, unlocked | All four open in V1; entry fee shown at P4 |
| In-game HUD top | Level name, objective progress bar, remaining steps or countdown, score, combo | Countdown ticks, frozen while paused |
| In-game HUD bottom | Skill slots (max 2), pause | Slots gray when not charged |
| Board | 8×8 terrain + pieces | Selected bounces + outline; illegal cells no outline |
| Pause | Resume / Restart / Give up | Restart consumes an attempt; give up counts as a loss |
| Victory | Score, steps remaining, whether reward paid (P4) | Next level / back to select |
| Defeat | Reason (steps/timeout), how far from objective | Retry / back to select |
| Insufficient balance | Copy + go to deposit | P4 only |

Keyboard (P5): arrow keys change selection, Enter swaps with the cell in the selected direction. V1 is mouse/touch only.

---

## 5. Operation Rules (Player's Perspective)

- Only **orthogonally adjacent** pieces that are both movable can be swapped.
- Rocks, trees, and puddle cells cannot be swap targets. A locked elephant cannot be swapped away (prey is swapped in).
- If the swap produces no "legal triple" horizontally or vertically → swap back, **no step, no time consumed**.
- Legal triple → 1 step consumed (timed levels consume no steps, only the clock).
- The next click is accepted only after all chains finish playing; clicks on the board mid-chain are ignored.
- Diagonal triples do not count. L/T intersections clear each cell once.

---

## 6. Three Clear Types (Functional)

Priority: **elephant > ecology > same-type**. A line scores once at the highest priority only.

### 6.1 Same-Type

Three or more of the **same species** in a line. Example: apple-apple-apple.

| Length | What the player sees |
|------|----------------|
| 3 | Shrink and disappear, base score |
| 4 | Disappear, fertilizer appears on the center cell |
| 5+ | Disappear, skill slot +1 charge (subject to the level's allowed skills) |

### 6.2 Ecology (Predation)

A line contains **exactly 1 predator**, the rest all its prey; the prey need not be the same species. Example: chicken-ant-ladybug.

| Predator | Can eat | Cannot eat |
|--------|------|--------|
| Chicken, duck, goose | Flowers, vegetables, fruits, bugs | Grains |
| Dog | Poultry such as chicken, duck, goose, pigeon | Plants, bugs |
| Pig | Trees, flowers, vegetables, fruits, bugs, grains | Dog |
| Cattle, horse | Flowers, grains, tree saplings | Bugs, meat |
| Elephant | See 6.3 | Obstacles, farm tools |

What the player sees: predation animation → all three cells empty (V1: the predator leaves together) → fertilizer left on the predator's original cell.

### 6.3 Elephant

A line with 1 elephant + any two other clearable pieces → those three cells clear, ignoring faction. At most 1 elephant on the board. Never "synthesized" from a normal swap; at combo 5 the system drops one into an empty top cell, or the level places one at start.

---

## 7. V1 Roster (Not the Plan's 100 Species)

All planned species are kept as encyclopedia data, but **V1 only spawns the following in matches**, ensuring readability and clearability.

| Species | Faction | Appears in levels | Player recognition |
|------|------|----------|----------|
| Wheat 小麦 | Grains | Harvest, Destroyer King, Carnival | Golden ear |
| Rice 水稻 | Grains | Harvest | Green ear |
| Corn 玉米 | Grains | Harvest | Yellow cob |
| Cabbage 白菜 | Vegetable | Harvest | Light green leaf ball |
| Tomato 西红柿 | Vegetable | Harvest | Red ball |
| Apple 苹果 | Fruit | Harvest, Destroyer King, Carnival | Red ball + stem |
| Rose 玫瑰 | Flower/herb | Destroyer King | Red petals |
| Ant 蚂蚁 | Insect | Harvest (low weight) | Small black |
| Ladybug 瓢虫 | Insect | Harvest | Red with dots |
| Hen 鸡 | Poultry | Harvest, Herding, Carnival | Oval + beak |
| Duck 鸭 | Poultry | Harvest, Herding | Flat beak |
| Goose 鹅 | Poultry | Herding | Long neck |
| Pigeon 鸽 | Poultry | Herding | Gray |
| Dog 狗 | Livestock | Herding, Carnival | Four legs |
| Pig 猪 | Livestock | Destroyer King, Carnival | Pink oval |
| Pine 松树 | Tree/obstacle | Destroyer King | Conical crown, not swappable |
| Elephant 象 | Apex | Carnival; combo-5 reward in other levels | Big cube + trunk |

Farm tools (sickle, hoe, water bucket) **never enter the board**, HUD only. The plan's other farm tools are not in V1.

---

## 8. Level Specifications

Win/loss is settled after **the entire chain animation ends**.

### 8.1 Harvest Level

- Pool: wheat, rice, corn, cabbage, tomato, apple, hen, duck; ant/ladybug low weight.
- Win: clear **50** plant-role pieces (grains+vegetables+fruits+flowers) within 20 steps. Hens/ducks cleared do not count.
- Lose: steps reach 0 with the objective unmet.
- Skills: sickle (usable once charged).
- Tutorial: ①tap adjacent pieces to swap ②three identical clear ③hens can eat two bugs/vegetables/fruits beside them, but not wheat.

### 8.2 Herding Level

- Pool: hen, duck, goose, pigeon, dog. No plants.
- Win: clear 15 poultry with **the dog's eco clears** within **90 seconds**.
- Lose: timeout.
- **Same-type clears of three hens do not count toward the objective** (the dog-eats-poultry eco clear is mandatory).
- Skills: none. Pause freezes the timer.

### 8.3 Destroyer King Level

- Pool: wheat, apple, rose, pig (low weight). Fixed 3 pine trees, HP=5, not swappable, cannot fall through.
- Win: 3 trees' HP reach zero.
- Lose: 25 steps exhausted.
- Tree damage: pig eco clear (tree in the prey run) -2; three pigs in a line trigger a **3×3 rampage** (trees in range -5); hoe on a single tree -3; normal adjacent match-3 -1.
- Skills: hoe.

### 8.4 Elephant Carnival

- Pool: wheat, apple, hen, dog, pig. 1 locked elephant near the center at start.
- Win: clear 30 cells via **the elephant rule** (same-type/eco do not count toward this objective).
- Lose: 30 steps exhausted.
- No second elephant spawns. The player swaps prey to the elephant's sides or above/below.
- Skills: none.

---

## 9. Obstacles, Fertilizer, Skills

| Feature | Player perception | Rules |
|------|----------|------|
| Rock | Gray, unclickable | HP3; adjacent clear -1; hoe smashes it in one hit |
| Tree | Tall model, unclickable | See Destroyer King |
| Puddle | Reflective cell surface | Pieces above stop one cell above the puddle; dropping resumes after the water bucket drains it |
| Fertilizer | Dark patch on the cell | That cell's next clear scores ×2, then it disappears |
| Sickle | Bottom bar icon | Select a row or column, clears only plants, no step consumed, consumes 1 charge |
| Hoe | Bottom bar icon | Click 1 rock or tree |
| Water bucket | Bottom bar icon | Click 1 puddle cell |

Charging: in the whole resolution triggered by one player action, +1 whenever an eco clear occurs; at 3, gain 1 slot, cap 2. A 5-match same-type also grants +1 slot (shares the slot with eco charging).

V1 Harvest has no rocks or puddles; Destroyer King has no puddles. Puddles stay in the encyclopedia and do not block the four-level main line.

---

## 10. Score and Economy

```
同种     10 × 消掉数 × combo × 肥料
生态     25 × 消掉数 × combo × 肥料
大象     40 × 消掉数 × combo
技能清格  8 × 消掉数
破障碍   20 × 破碎数
```

combo: the first clear of an action is 1, +1 per additional chain round; reset to 1 on the player's next manual action.

**P4 wallet:**

- Starting a level deducts the entry fee (default 1 game currency per level).
- Clear pays by stars: remaining resources ≥50% three stars, ≥20% two stars, otherwise one star; rewards 2 / 3 / 5 (configurable).
- Failure does not refund the entry fee.
- Exiting before any tile was swapped → refund.
- Cannot start with insufficient balance.

V1 (P0–P3) has no fees; playable locally.

---

## 11. Feature Checklist and Acceptance

| ID | Feature | Acceptance | Phase |
|----|------|------|------|
| F01 | 8×8 click-to-swap | Adjacent swappable, diagonal not, no-match bounces back | P0 |
| F02 | Same-type match-3 + gravity + refill | Three wheats clear, above falls down, new pieces fill the top | P0 |
| F03 | Chains | Auto-reclear after drops, combo number +1 | P0 |
| F04 | Four-level select | Click into the corresponding objective HUD | P1 |
| F05 | Harvest objective | 50 plants within 20 steps, counting plants only | P1 |
| F06 | Eco clear | Chicken + two bugs clears; chicken + two wheats does not | P2 |
| F07 | Fertilizer | After eco, that cell's next clear scores double once | P2 |
| F08 | Herding objective | Same-type hens not counted; dog-eats-hen counted; 90s | P2 |
| F09 | Trees and hoe | Trees not swappable; hoe/pig can break them | P3 |
| F10 | Three-pig 3×3 | Three pigs in a line, trees in range shatter directly | P3 |
| F11 | Sickle | Clears a row of plants, no step consumed | P3 |
| F12 | Locked elephant | Elephant cannot be swapped away; elephant + two pieces clears three cells | P4 |
| F13 | Carnival objective | Only the elephant rule counts toward 30 | P4 |
| F14 | Entry fee/rewards | Balance reconciliation, duplicate settlement never pays twice | P4 |
| F15 | Tutorial | Three hints, permanently skippable | P1 |
| F16 | Pause/restart/give up | Timer freezes; give up counts as a loss | P1 |
| F17 | Low-end device particle toggle | Frame rate stable and playable after switching | P5 |

---

## 12. Boundaries (Must Be Hardcoded)

1. The encyclopedia can be huge, **spawn species per level ≤ 8**.
2. Farm tools never enter the board.
3. Chickens do not eat grains: a "chicken+wheat+wheat" line is neither eco nor same-type; bounces back.
4. Dogs do not eat plants; pigs do not ram dogs.
5. At most 1 elephant on the board at a time.
6. Input is dropped while chains are playing.
7. Win/loss is never judged mid-animation.
8. V1: the predator leaves together with its prey.
9. The Herding level is timed at 90 seconds, no steps.
10. Puddles are not in the four-level main line.
