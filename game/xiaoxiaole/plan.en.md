# Pastoral Match-3 — Development Plan
<!-- lang-nav -->

Languages: [中文](plan.md) · **English** · [한국어](plan.ko.md) · [Русский](plan.ru.md) · [Deutsch](plan.de.md) · [Français](plan.fr.md) · [Español](plan.es.md) · [Português](plan.pt.md) · [हिन्दी](plan.hi.md) · [العربية](plan.ar.md) · [বাংলা](plan.bn.md) · [Bahasa Indonesia](plan.id.md) · [日本語](plan.ja.md)


> Turning the vision (`design.md`) into something schedulable. Feature details follow `functional-design.md`, technical constraints follow `architecture.md`.

---

## 1. How the Three Documents Are Used

| Document | Questions it answers | Questions it does not |
|------|------------|--------|
| `design.md` | Pastoral theme, restraint fantasy, 3D character | How many species spawn per level, acceptance clauses |
| `functional-design.md` | What the player clicks, how winning is judged, who appears in V1 | How the catalog is split, whether to use a physics engine |
| `architecture.md` | Layering, modules, platform wallet, deterministic RNG | 90 seconds or 20 steps (already decided in the functional design) |

Development only recognizes the latter two; when the vision conflicts with them, the latter two win (already-decided exceptions are written in functional design section 12).

---

## 2. V1 Scope

**Done means shippable:** the four levels are clearable, three clear types, skills and Destroyer King obstacles, the H5 can be opened from the lobby. The wallet can be turned off (feature flag `xxl.entry_bet`).

**Explicitly cut or deferred:** 100 species on the board at once, farm tools as pieces, physics engine, GLTF, spectating, in-round rankings, puddle main-line levels, predator staying after eating, per-move server-side validation.

---

## 3. Milestones

| Milestone | Target date (relative to start) | Playable result | What ships |
|--------|----------------------|----------|----------|
| M0 Skeleton | Week 1 | Open a blank sandbox locally | Vite, Three orthographic scene, 8×8 terrain |
| M1 Can clear | Week 2 | Three identical clear and drop | F01–F03, domain unit tests |
| M2 Has levels | Week 3 | Harvest level winnable and losable | F04 F05 F15 F16 |
| M3 Ecology | Week 4 | Chicken eats bugs, Herding level | F06 F07 F08 |
| M4 Farm tools | Week 5 | Destroyer King breaks trees | F09 F10 F11 |
| M5 Integration | Week 6 | Lobby entry, elephant level, optional fee | F12 F13 F14 |
| M6 Polish | Week 7 | Particles/sound/low-end profile | F17 |

One week assumes one person full-time. Parallelizing (domain + render) can compress to about 5 weeks.

---

## 4. Phases and Dependencies

```
P0 同种三消 ─────────┐
P1 选关与丰收 ───────┼─ P2 生态与驱赶 ─ P3 障碍农具 ─ P4 象+钱包 ─ P5 抛光
渲染沙盘（可与 P0 并行）┘
```

- P0 has no PHP dependency. Play locally with `?debug=1`.
- P1 has no wallet dependency.
- P2 depends on extending P0's match scanning, does not change the control scheme.
- P3 depends on cell overlays.
- P4 depends on the platform's existing `POST /api/game/launch` and `SelfProvider`; the game side adds ticket, bet, settle.
- P5 has no functional dependency; the low-end toggle can be inserted anytime.

---

## 5. Work Packages (By Person)

**A Domain (no UI)**  
Encyclopedia JSON → board snapshot → matching (same-type/eco/elephant) → gravity → level win/loss → score. Vitest before visuals.

**B Presentation**  
Scene, camera, build 3 of the 10 templates first (grain ear/fruit/chicken), Raycaster, swap and clear easing. HUD in DOM.

**C Level Content**  
Four level JSONs: spawn pool, objective, steps/time limit, skill whitelist, starting obstacles.

**D Platform**  
launch URL params, balance display, bet/settle, failure refund policy, play-log events.

Suggested order: A's P0 tests red-green → B consumes snapshots → C Harvest → A ecology tests → C remaining three levels → D.

---

## 6. Platform-Side Changes (P4 Only)

The interface contract is in **[api.md](api.md)**. Platform-side change points:

| Item | Current state | Planned action |
|----|------|----------|
| Game record | `GameController::launch` already writes session | Add a record in the admin with type=self, api_endpoint pointing to this H5 |
| Wallet | `SelfProvider::bet/settle` already exist | The game calls by round_id; set a per-round payout cap |
| Feature flags | `FeatureFlag` already exists | `xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet` |
| Static hosting | Nginx already serves | `/games/xiaoxiaole/` points to the build output |
| Lobby opening | Flutter `launchUrl` | endpoint appends `session_id` |

P0–P3 **require no PHP changes**.

---

## 7. Risks

| Risk | Impact | Mitigation |
|------|------|------|
| Players cannot understand the eco rules | Herding level unbeatable | Tutorial hint #3; clearable preview deferred to P5 |
| Spawn variety still too high | No possible moves | Hard cap of 8 species per level |
| Elephant too strong | Carnival clears instantly | Objective only counts the elephant rule; hard cap of 1 on the board |
| Client-side score tampering for rewards | Wallet | P4 payout cap; replay verification deferred |
| Low-end device frame drops | Experience | dpr cap 2; particles toggleable |

---

## 8. Already Decided (No More Questions)

- After an eco clear, the predator **leaves together** with the prey.
- The Herding level is **timed at 90 seconds**, no steps.
- Puddles are not in the four-level main line.
- V1 only spawns the table in functional design section 7; the rest only enter the encyclopedia file.

To change any of these four, change `functional-design.md` first, then the code.

---

## 9. Next Steps (Waiting for Your Go-Ahead)

1. Write the implementation task list per P0 (file-level, tests first), or  
2. Directly scaffold Vite + the `domain` skeleton + an empty scene.

Specific function implementations are not written in this plan.
