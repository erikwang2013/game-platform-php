# 田园消消乐 — Technische Architektur
<!-- lang-nav -->

Languages: **中文** · [English](architecture.en.md) · [한국어](architecture.ko.md) · [Русский](architecture.ru.md) · [Deutsch](architecture.de.md) · [Français](architecture.fr.md) · [Español](architecture.es.md) · [Português](architecture.pt.md) · [हिन्दी](architecture.hi.md) · [العربية](architecture.ar.md) · [বাংলা](architecture.bn.md) · [Bahasa Indonesia](architecture.id.md) · [日本語](architecture.ja.md)


> Spielerfunktionen und Abnahme siehe `functional-design.md`; Zeitplan siehe `plan.md`; Themenvision siehe `design.md`.
>
> Dieser Artikel beantwortet nur: Wie werden Module aufgeteilt, wie wird die Plattform angebunden, auf welcher Ebene werden die Regeln berechnet. Er enthält keinen Implementierungscode.
>
> Produktpositionierung: Eigenentwickeltes H5 (`game.type = self`), 8×8-Sandmodell-Match-3 + ökologische Kettmechanik, Three.js Low-Poly 2.5D.

---

## 0. Architekturentscheidungen gegenüber der Planung

Die Planung ist die Gameplay-Vision; die folgenden Entscheidungen lösen den Konflikt zwischen „spielbar, testbar, wallet-anbindbar".

| ID | Entscheidung | Grund |
|----|------|------|
| D1 | **Tafel ≠ Figuren auf dem Brett**. 100+ Arten sind Tafel und Optik; der Spawn-Pool eines Levels zieht nur **5–8 Arten** | Bei 8×8 können mehrere Dutzend Arten gleichzeitig kaum Eliminierungen bilden |
| D2 | Matching in zwei Ebenen: **Gleichart nach `speciesId`, Ökologie nach `role` + Kettentabelle** | Die Planung verlangt gleichzeitig „drei Äpfel" und „Huhn+Käfer+Käfer" |
| D3 | Priorität der Regeln in einer Linie: **Elefant > Ökologie > Gleichart**; sich gegenseitig ausschließend, keine Doppelwertung | Verhindert, dass eine Zeile zweimal Punkte bringt |
| D4 | **Werkzeuge kommen nicht aufs Brett**, nur in den HUD-Fähigkeitsslots; Stein/Wasserpflütze/Baum sind Hindernisse, nicht tauschbar | Abschnitt 5 der Planung kollidiert mit der Figurenbibliothek; Fähigkeiten + Hindernisse gewinnen |
| D5 | **Die Domänenlogik hat null Three.js-Abhängigkeiten**, reine Funktionen + Snapshot; die Darstellungsschicht abonniert nur Ereignisse | Regeln sind unit-testbar, abspielbar und später serverseitig prüfbar |
| D6 | Zu Beginn wird aus `session_id` ein **deterministischer RNG-seed** abgeleitet; Fallen/Spawning laufen komplett über diesen RNG | Gleicher seed = rekonstruierbar; Ansatzpunkt gegen Cheating |
| D7 | Keine Physikengine. Bewegung/Hüpfen/Eliminierung per Easing, kein Cannon/Rapier | Die Planung schreibt „simulierte Animationen" vor; Physik bringt für Raster-Spiele nichts |
| D8 | Kamera **orthografisch 2.5D fixiert**, Orbitsteuerung aus | Konsistent mit der Planung, vermeidet Fehlbedienung und Schwindel |
| D9 | Arten teilen sich **Fraktionen-Geometrievorlagen + Farbe/Zubehör**, keine eigene Modellierung pro Kultur | Datenvolumen und Zeitplan; visuelle Unterschiede durch Farbgebung und ein Merkmalsteil |
| D10 | Level-Eintritt über `SelfProvider::bet`, Abschluss `settle`, Abbruch ohne Eintrittsgebühr-Rückerstattung; ohne ersten Schritt `refund` möglich | Abgleich mit Plattform-Wallet und round-Idempotenz |

---

## 1. Systemkontext

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

Das Spiel ist ein **statisches Frontend**; die maßgebliche Session und das Geld liegen in `service/`. Der Client hält den Brettzustand; der Server hält das Guthaben und die round-Idempotenz. In Phase 1 gibt es keine serverseitige Prüfung jedes einzelnen Schritts, aber die Domänenschicht muss deterministisch sein, damit in Phase 2 `seed + Operationssequenz` zur Neuberechnung an den Server gesendet werden können.

---

## 2. Client-Schichtung

Von oben nach unten; Abhängigkeiten über Ebenen hinweg sind verboten (`render` darf nicht von `domain` importiert werden).

```
app/          组装、状态机、关卡生命周期
hud/          HTML Overlay：分数、步数、目标、技能、结果
platform/     launch 参数、钱包、play-log、特性开关
render/       Three.js：场景、棋盘、棋子网格、输入、VFX
runtime/      命令总线、动画队列、回放
domain/       棋盘、匹配、克制、重力、分数、目录、关卡规则
config/       克制表、刷新权重、几何配方、关卡 JSON
```

**Hauptschleife (Regeln werden nicht in `requestAnimationFrame` berechnet)**: Eingabe → Befehl → Domänen-Synchronauswertung (ein swap rechnet alle Ketten aus und erzeugt eine Ereignisliste) → die Runtime spielt die Ereignisse als Animationen in der Warteschlange ab → erst nach Animationsende wird die nächste Eingabe akzeptiert.

So ist „Logik ein Frame, Darstellung mehrere Frames", und combo konkurriert nicht mit Klicks um den Zustand.

---

## 3. Verzeichnisstruktur (Empfehlung)

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

Keine Datei über 500 Zeilen. Wenn `MatchDetector` und `PieceFactory` anschwellen, nach Regeltyp / Fraktionsvorlage weiter aufteilen.

---

## 4. Domänenmodell

### 4.1 Figurendefinition (Tafel)

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

Alle geplanten Arten (Getreide/Gemüse/Obst/Blumen/Insekten/Geflügel/Nutztiere/Bäume) gehen in die Tafel; **tool wird nicht auf Felder generiert**. Elefant `rarity = legendary`, `role = apex`.

### 4.2 Zellen und Brett

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

- **Stein / Baum**: belegt das Feld, nicht tauschbar, nicht durchfallbar. HP siehe Level.
- **Wasserpfütze**: liegt auf dem Feld, blockiert die Schwerkraft durch dieses Feld (die Figur darüber stoppt auf dem Feld über der Pfütze).
- **Dünger**: bleibt nach ökologischer Eliminierung auf dem Feld; die nächste Eliminierung mit Beteiligung dieses Feldes bringt ×2 Punkte, danach verschwindet er.

### 4.3 Spawn-Pool (Level)

```
SpawnPool
  speciesIds[]       5–8 个
  weights[]          与 species 对齐
  maxApex            默认 1
  apexUnlock         combo >= 5 时由系统生成，禁止「交换生成」
```

Das Level zieht nur aus diesem Pool per `rng`. Egal wie groß die Tafel ist, die Brettentropie bleibt kontrolliert.

---

## 5. Kernregel-Engine (Funktionsdesign)

### 5.1 Bedienung

1. Figur A anklicken → auswählen (Hüpfen + Kontur).
2. Benachbartes orthogonales Feld B anklicken → Tauschversuch (diagonal verboten).
3. Nicht benachbart / leer anklicken → Auswahl wechseln oder abbrechen.
4. Ergibt sich nach dem Tausch **keine gültige Übereinstimmung**, zurücktauschen ohne Schrittverbrauch.
5. Bei Übereinstimmung 1 Schritt verbrauchen, Auswertung startet.

Hindernisfelder können nicht als Tauschziel gewählt werden. Gesperrte Felder (Levelregeln) ebenso.

### 5.2 Linien-Scan

Für jedes Brett nach einem swap:

- Horizontale und vertikale durchgehende Zellen mit Länge ≥ 3 bilden einen **run**.
- Ein run wird nur mit einer Regel ausgewertet (D3).
- Mehrere runs können sich überschneiden (klassisches L/T), überschneidende Zellen werden nur einmal eliminiert.

### 5.3 Gleichart-Eliminierung

Im run sind alle `speciesId` gleich, und es gilt nicht Hindernis- oder Elefantenprivileg-Sonderbehandlung.

- 3er: Grundpunkte.
- 4er: Extrapunkte, und auf dem Mittelfeld erscheint **Dünger** (gleiches overlay wie Öko-Dünger).
- 5er: Extrapunkte, Fähigkeitsslot +1 Aufladung (siehe 5.7).

### 5.4 Ökologische Eliminierung (Kettmechanik)

Auswertung: **genau 1 Unterdrücker + der Rest sind ausschließlich Beutetiere dieses Unterdrückers** (bei 3 Zellen also 1+2). Die Beute muss nicht gleich sein.

| Unterdrücker | Beutematching |
|--------|----------|
| 鸡、鸭、鹅 (Huhn, Ente, Gans) | faction ∈ {flora, veg, fruit, insect}；**不含 crop（五谷）** |
| 狗 (Hund) | faction = poultry (Hühner, Enten, Gänse, Tauben usw.) |
| 猪 (Schwein) | faction ∈ {tree, flora, veg, fruit, insect, crop}；**不含狗** |
| 牛、马 (Rind, Pferd) | faction ∈ {flora, crop} oder tag `tree_seedling`; keine Insekten und kein Fleisch |
| 大象 (Elefant) | siehe 5.5, nicht über diese Tabelle |

Effekte:

- Komplette Eliminierung, Jagdanimation wird abgespielt (der Unterdrücker „frisst" zuerst, dann verlassen alle zusammen das Brett, oder der Unterdrücker bleibt — **in Phase 1 verlässt die ganze Linie das Brett**, um zu vermeiden, dass zurückbleibende Unterdrücker das Fall-Balancing zerstören; falls sich das Erlebnis zu schwach anfühlt, kann in Phase 2 ein Schalter „Unterdrücker bleibt" eingeführt werden).
- Der Öko-Grundwert liegt über dem der Gleichart-Eliminierung.
- Auf dem Ursprungsfeld des Unterdrückers entsteht **Dünger**.
- `ecoChainStreak += 1`; mehrere Öko-Eliminierungen in derselben Kette erhöhen den streak-Zähler nur einmal (bei Abschluss der gesamten resolve +1, damit ein einzelner Fall nicht die Fähigkeiten voll auflädt).

**Hühner fressen kein Getreide**: Feldfrüchte und Hühner können zusammen auf dem Brett sein, aber keinen Öko-run bilden; Feldfrüchte werden nur per Gleichart eliminiert.

### 5.5 Elefant

- Global maximal 1 auf dem Brett; extrem geringes Spawn-Gewicht; nur als `combo >= 5`-Belohnung generiert oder per `initialPieces` des Levels platziert.
- Ein run mit 1 Elefanten + 2 beliebigen Nicht-Werkzeug-, Nicht-Hindernis-Figuren → diese 3 Zellen leeren (verschiedene Fraktionen erlaubt).
- Der Elefant **kann** keine Werkzeuge eliminieren (Werkzeuge sind nicht auf dem Brett, also automatisch erfüllt) und keine Hindernisse (Hindernisse gehen nicht in runs ein).
- „Elefantenparty"-Level: zu Beginn 1 Stück, `locked = true`, kann nicht von seinem Feld weggetauscht werden; Beute wird daneben getauscht, um einen run zu bilden.

### 5.6 Kette, Schwerkraft, Refill

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

Benachbarte Eliminierung zieht HP von Hindernissen ab: Stein bei jeder benachbarten Gleichart-/Öko-Eliminierung -1, bei HP=0 zerbricht er; Baum verliert HP standardmäßig nur durch **Hacke** oder Level-„Drei-Schweine-Rang-Wühlangriff" oder Schwein-Ökologie (Beute enthält Baum). Zerstörerlevel Baum-HP=5.

Eimer-Fähigkeit: eine Wasserpfützen-Zelle wählen → overlay entfernen, diese Spalte führt sofort eine gravity aus.

### 5.7 Fähigkeitsslots (Werkzeuge)

| Fähigkeit | Freischaltung | Effekt |
|------|------|------|
| 镰刀 (Sichel) | 3 aufeinanderfolgende resolves mit Ökologie | Eine Zeile oder Spalte wählen, alle **plant-Figuren** (crop/veg/fruit/flora) dieser Linie entfernen, kein Schrittverbrauch, kostet Aufladung |
| 锄头 (Hacke) | wie links, oder Level-Vorabgabe | Stein/Baum anklicken, direkt HP=0 oder -3 (Levelkonfiguration) |
| 水桶 (Eimer) | Level-Vorabgabe oder Aufladung | Eine Wasserpfütze trockenlegen |

Aufladeregeln: `ecoResolveCount` erreicht 3 → Slot +1, Zähler wird zurückgesetzt. Slot-Obergrenze 2. Welche der Fähigkeiten Sichel/Hacke/Eimer erscheinen, bestimmt `allowedSkills[]` des Levels.

### 5.8 Punktewertung

```
same_3     = 10 * n * combo * fertilizerMul
eco        = 25 * n * combo * fertilizerMul
elephant   = 40 * n * combo
skill_clear= 8  * n
obstacle   = 20 * brokenCount
```

`combo` startet bei 1, jede Kette +1, beim nächsten manuellen swap des Spielers Reset. Dünger wirkt nur auf „diejenige Eliminierung, bei der dieses Feld eliminiert wird".

---

## 6. Levelfunktionen

| Level | Pool | Sieg | Niederlage | Besonderheit |
|------|----|------|------|------|
| 丰收 (Ernte) | crop/veg/fruit + hohes Gewicht poultry | Innerhalb von 20 Schritten 50 plant eliminieren | Schritte verbraucht | Hühner/Enten/Gänse stören die Gleichart-Eliminierung von Pflanzen |
| 驱赶 (Vertreiben) | poultry + dog, keine Pflanzen | Innerhalb der Zeit mit Hund-Ökologie 15 Hühner/Enten eliminieren | Zeitüberschreitung | Gleichart-Geflügel zählt nicht für das Ziel, Ökologie ist Pflicht |
| 破坏王 (Zerstörer) | Pflanzen + wenig pig + 3 Bäume (HP5) | Schwein wühlt 3 Bäume um | Schritte verbraucht | Drei Schweine in einer Linie lösen **3×3-Wühlangriff** aus (Levelregel, nicht global) |
| 大象狂欢 (Elefantenparty) | Mischpool + Start-Lock-Elefant | Elefantenregel eliminiert 30 Figuren | Elefant abnormal entfernt (sollte nicht passieren) oder Schritte verbraucht | Elefanten schützen; das System spawnt keinen zweiten |

Gemeinsames HUD: Zielfortschritt, Schritte oder Countdown, combo, Fähigkeitsslots, Pause/Beenden.

Sieg/Niederlage werden nach Abschluss einer resolve (inkl. aller Kettenanimationen) gewertet, um eine Fehlentscheidung mitten in der Animation zu vermeiden.

---

## 7. Three.js-Darstellungsschicht

| Modul | Aufgabe |
|------|------|
| SceneRoot | WebGLRenderer, Tonemapping, resize, dpr-Obergrenze 2 |
| CameraRig | OrthographicCamera, Neigung ca. 45°, lookAt Brettmitte, OrbitControls verboten |
| Lights | Directional (Sonne) + Hemisphere (Umgebung) + schwaches Rim; keine Echtzeit-Schatten oder nur das Brett empfängt eine niedrig aufgelöste Schattenkarte |
| BoardView | 8×8 Felder; Y-Variation per vorgebackener perlin-Höhenkarte (logische Zellen bleiben flach) |
| PieceFactory | Group nach `template` zusammensetzen: Kugel/Zylinder/Kegel/Würfel; MeshPhongMaterial; Objektpool |
| InputRaycaster | Trifft nur in `Idle/Selected` auf Figuren-Meshes |
| VFX | Auswahl-Outline (selbstgezeichneter Glow-Ring, in Phase 1 kein Vollbild-OutlinePass); Tausch per GSAP; Eliminierung scale+Partikel Points; Pollen/Glühwürmchen mit wenigen Points im Loop |
| HUD | DOM, nicht in WebGL, für i18n und Barrierefreiheit |

Geometrievorlagen (D9): `grain` `produce` `fruit` `flower` `bug` `bird` `beast` `tree` `apex` `rock`. Die Tafel ändert nur tint und accessory.

Leistungsbudget: 64 Figuren + Felder < 200 Draw Calls (Felder möglichst mergen); Partikel < 400; Gerätemodus schaltet Partikel und Höhenvariation ab.

---

## 8. Zustandsautomat

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

Ungültige Eingaben werden außerhalb von Idle/Selected/SkillTargeting verworfen.

**Befehle**: `Select` `Swap` `UseSkill` `Pause` `Quit` `AckResult`  
**Ereignisse** (in EventLog geschrieben): `Swapped` `RejectedSwap` `Matches` `Cleared` `Fell` `Refilled` `Combo` `SkillUsed` `Won` `Lost`

---

## 9. Plattformanbindung

Vollständiger Schnittstellenvertrag (launch / balance / bet / settle / refund / play-log / Feature-Schalter) siehe **[api.md](api.md)**. Kernpunkte:

- Start: `POST /api/game/launch` gibt `session_id, api_endpoint, type=self` zurück, Öffnen von `api_endpoint?session_id=&token=`.
- Wallet: `SelfProvider::bet/settle/refund`, `round_id = session_id + ':' + levelId + ':' + attempt`; Domänenseed `seed = hash(session_id + round_id)`.
- Feature-Schalter: `xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet`, bei Deaktivierung Degeneration zu reinem Gleichart-Match-3.
- Sicherheit: Phase 1 clientautoritatives Brett + serverautoritatives Wallet, eine round nur einmal bet/settle; Phase 2 lädt die Operationssequenz hoch und lässt sie serverseitig neu berechnen.

---

## 10. Nichtfunktionale Anforderungen

| Punkt | Kennzahl |
|----|------|
| First Screen | Low-Poly + kein GLTF, Ziel: innerhalb von 3s interaktiv (inkl. Vite-gzip) |
| Bildrate | Desktop 60fps; integrierte Grafikkarten können VFX abschalten |
| Tests | `domain/**`-Unit-Tests decken Matching/Gravität/Kette/Sieg-Niederlage ab; WebGL wird nicht getestet |
| i18n | HUD-Texte mit Keys, folgt der `Language`-Middleware der Plattform |
| Barrierefreiheit | Tastatur: Pfeile wählen + Enter tauscht (Phase 2); Farbenblindheit: Formvorlagen vor reiner Farbe |
| Volumen | Kein FBX; three + gsap nach gzip < 250KB Code anstreben |

---

## 11. Phasen

| Phase | Umfang | Abnahme |
|----|------|------|
| P0 | Gleichart-Match-3, 8×8, Tausch/Gravität/Refill, Orthografieszene, 3 Vorlagenfiguren | Eine Partie ohne Ziel spielbar |
| P1 | Tafel + SpawnPool + vier Levelziele/Schritt-HUD | Erntelevel abschließbar |
| P2 | Kettentabelle + Öko-Eliminierung + Dünger + combo | Huhn+zwei Käfer eliminierbar; Getreide wird nicht vom Huhn eliminiert |
| P3 | Stein/Baum/Wasserpflütze + Sichel/Hacke/Eimer | Zerstörerlevel kann Bäume abbauen |
| P4 | Elefant + Sperrzelle + Plattform bet/settle | Party-Level; Guthabenabgleich |
| P5 | Partikel, Sound, Objektpool, Gerätemodus, Replay | Leistungsbudget erreicht |

P0 bindet kein Wallet an, lokal mit `?debug=1`. Erst P4 bindet `SelfProvider` an.

---

## 12. Modulverantwortlichkeiten im Überblick

| Modul | Eingabe | Ausgabe | Abhängigkeiten |
|------|------|------|------|
| Catalog | JSON-Tafel | PieceDef | keine |
| RestraintTable | Kettenkonfiguration | isEcoRun(run) | Catalog |
| Board | Befehle | neuer Snapshot | Catalog, RNG |
| MatchDetector | Snapshot | runs[] | RestraintTable |
| Gravity | Snapshot | Snapshot + Fell | Board |
| Level | Eliminierungsstatistik | Fortschritt/Sieg-Niederlage | Board-Ereignisse |
| Score | Ereignisse | Punkte | Level (Multiplikator) |
| GameStateMachine | Befehle/Animationsabschluss | Zustand | obige domain |
| PieceFactory | PieceDef | Object3D | nur render |
| PlatformAdapter | Sieg/Niederlage/Einsatz | HTTP | keine domain-Zyklusabhängigkeit |
