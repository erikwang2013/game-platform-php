# 田园消消乐 — 技術アーキテクチャ
<!-- lang-nav -->

Languages: **中文** · [English](architecture.en.md) · [한국어](architecture.ko.md) · [Русский](architecture.ru.md) · [Deutsch](architecture.de.md) · [Français](architecture.fr.md) · [Español](architecture.es.md) · [Português](architecture.pt.md) · [हिन्दी](architecture.hi.md) · [العربية](architecture.ar.md) · [বাংলা](architecture.bn.md) · [Bahasa Indonesia](architecture.id.md) · [日本語](architecture.ja.md)


> プレイヤー機能と受け入れ条件は `functional-design.md`、スケジュールは `plan.md`、テーマビジョンは `design.md` を参照。
>
> 本文はモジュール分割の方法、プラットフォームとの接続方法、ルールをどの層で計算するかのみを扱います。実装コードは書きません。
>
> 製品ポジショニング：自研 H5（`game.type = self`）、8×8 サンドボックス三消 + 生態克制チェーン、Three.js ローポリ 2.5D。

---

## 0. 計画に対するアーキテクチャ決定

計画はプレイビジョンです。以下の決定は「遊べる・テストできる・ウォレットに接続できる」という矛盾を解決します。

| ID | 決定 | 理由 |
|----|------|------|
| D1 | **図鑑 ≠ 同一盤面の駒**。100+ 種は図鑑と外観であり、単関のリフレッシュプールは **5–8 種** のみ抽選 | 8×8 に同時に数十種が出現するとほぼ揃わない |
| D2 | マッチングは2層：**同種は `speciesId`、生態は `role` + 克制表** | 計画は「りんご3つ」と「鶏+虫+虫」の両方を要求 |
| D3 | 同一線分のルール優先度：**大象 > 生態 > 同種**；互いに排他、重複加点しない | 一行が同時に2回加点されるのを防ぐ |
| D4 | **農具は盤面に入れない**、HUD のスキルスロットのみ；石/水たまり/木は障害物で、交換不可 | 計画の第5節と駒カタログが衝突、スキル+障害を優先 |
| D5 | **領域ロジックは Three.js 非依存**、純関数 + スナップショット；表現層はイベントを購読するのみ | ルールの単体テスト・リプレイ・将来のサーバー側検証が可能 |
| D6 | 開局 `session_id` から **決定論的 RNG seed** を派生；落下/リフレッシュはすべてこの RNG を使う | 同一 seed で再現可能；アンチチートの余地を残す |
| D7 | 物理エンジンなし。移動/弾み/消去はイージングで行い、Cannon/Rapier を導入しない | 計画は「模拟动画」と記載；物理はグリッドゲームに利益なし |
| D8 | カメラは **直交 2.5D 固定カメラ**、軌道コントロールを無効化 | 計画と一致、誤操作と目まいを防止 |
| D9 | 種は **陣営幾何テンプレート + 色/アクセサリ** を共有し、作物ごとに個別モデリングしない | トラフィックと工数；視覚差は配色と特徴パーツ一つで |
| D10 | 关卡の入場は `SelfProvider::bet`、通关は `settle`、途中失敗では入場費を返さない；未着手での退出は `refund` | プラットフォームのウォレットと round 冪等性に整合 |

---

## 1. システムコンテキスト

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
game/xiaoxiaole/  (静的リソース，Nginx)
  Vite + TypeScript + Three.js
  領域エンジン ──イベント──► レンダリング / HUD
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

游戏は**静的前端**であり、権威的なセッションと残高は `service/` にあります。クライアントは盤面状態を保持し、サーバーは残高と round の冪等性を保持します。第一期では毎手のサーバー側検証は行いませんが、領域層は決定論的でなければならず、第二期で `seed + 操作序列` をサーバーに送って再計算できるようにします。

---

## 2. クライアント層構造

上から下へ、層を越えた逆依存は禁止（`render` は `domain` から import されてはならない）。

```
app/          組み立て、状態機械、关卡ライフサイクル
hud/          HTML Overlay：スコア、手数、目標、スキル、結果
platform/     launch パラメータ、ウォレット、play-log、フィーチャーフラグ
render/       Three.js：シーン、盤面、駒グリッド、入力、VFX
runtime/      コマンドバス、アニメーションキュー、リプレイ
domain/       盤面、マッチング、克制、重力、スコア、カタログ、关卡ルール
config/       克制表、リフレッシュ重み、幾何レシピ、关卡 JSON
```

**メインループ（`requestAnimationFrame` の中でルールを計算しない）**：入力 → コマンド → 領域の同期決済（1回の swap で全連鎖を計算し、イベントリストを生成）→ ランタイムがイベントに従ってアニメーションをキューで再生 → アニメーション終了後にのみ次の入力を受け付ける。

これにより「ロジック1フレーム、表現複数フレーム」となり、combo がクリックと状態を奪い合わない。

---

## 3. ディレクトリ構造（推奨）

```
game/xiaoxiaole/
├── design.md
├── architecture.md          ← 本文
├── package.json             # vite, three, gsap, vitest, typescript
├── index.html
├── src/
│   ├── main.ts              # URL を読み、GameApp を起動
│   ├── app/
│   │   ├── GameApp.ts
│   │   └── GameStateMachine.ts
│   ├── domain/
│   │   ├── catalog/         # PieceDef, Faction, Role
│   │   ├── board/           # Grid 8×8, Cell, PieceInstance
│   │   ├── match/           # MatchDetector（同種 / 生態 / 大象）
│   │   ├── eco/             # RestraintTable
│   │   ├── gravity/         # 落下、水たまり遮断、リフレッシュ
│   │   ├── score/           # 計分、肥料倍率
│   │   ├── level/           # LevelDef, Objective, Win/Lose
│   │   └── rng/             # seeded mulberry32
│   ├── runtime/
│   │   ├── CommandBus.ts    # Select, Swap, UseSkill, Quit
│   │   ├── EventLog.ts      # シリアライズ可能なイベント、リプレイ用
│   │   └── AnimationQueue.ts
│   ├── render/
│   │   ├── SceneRoot.ts
│   │   ├── CameraRig.ts
│   │   ├── BoardView.ts
│   │   ├── PieceFactory.ts  # テンプレート幾何
│   │   ├── InputRaycaster.ts
│   │   └── vfx/
│   ├── hud/
│   ├── platform/
│   │   ├── ApiClient.ts
│   │   └── Session.ts
│   └── levels/*.json
└── tests/domain/            # WebGL 不要
```

単一ファイルは 500 行以下。`MatchDetector` と `PieceFactory` が肥大化したら、ルールタイプ / 陣営テンプレートごとに再分割する。

---

## 4. 領域モデル

### 4.1 駒定義（図鑑）

```
PieceDef
  id            speciesId        如 wheat, hen, elephant
  faction       Faction          crop | veg | fruit | flora | insect | poultry | livestock | tree | tool | obstacle | apex
  role          Role             plant | prey | predator_mid | predator_high | apex | obstacle | skill
  tags[]                         crop, edible_by_poultry, tree_seedling, bone …
  rarity        common | rare | legendary
  template      GeometryTemplate 幾何テンプレート名
  tint          RGB              テンプレート内配色
  accessory     optional         くちばし、花びら、象鼻などの区分パーツ
```

計画の作物/野菜/果物/花草/昆虫/家禽/家畜/樹木はすべて図鑑に入る；**tool は格子に生成しない**。大象 `rarity = legendary`、`role = apex`。

### 4.2 格子と盤面

```
Cell
  q, r               列、行（0–7）
  height             地形起伏（レンダリングのみ、ルールに関与しない）
  occupant           PieceInstance | null
  overlay            none | fertilizer | puddle | stone(hp) | tree(hp)
  locked             bool          大象狂欢关：大象は換えられない

PieceInstance
  uid, speciesId, def
  special            none | fertilizer_token
```

- **石 / 木**：格子を占有、交換不可、落下で通過不可。HP は关卡による。
- **水たまり**：格子上に重なる、その格子を貫く重力を遮断（上の駒は水たまりの一つ上で止まる）。
- **肥料**：生態消去後にその格子に残る；次にその格子が関与する消去のスコア ×2、その後消える。

### 4.3 リフレッシュプール（关卡）

```
SpawnPool
  speciesIds[]       5–8 個
  weights[]          species と対応
  maxApex            デフォルト 1
  apexUnlock         combo >= 5 のときにシステムが生成、「交換生成」は禁止
```

关卡はこのプールからのみ `rng` で駒を引く。図鑑が大きくても盤面のエントロピーは制御可能。

---

## 5. コアルールエンジン（機能設計）

### 5.1 操作

1. 駒 A をクリック → 選択（弾み + アウトライン）。
2. 隣接する直交格 B をクリック → 交換を試行（斜め交換禁止）。
3. 隣接しない / 空白をクリック → 選択し直しまたはキャンセル。
4. 交換後に**合法なマッチが一切ない**場合、元に戻すアニメーションを再生し、手数を消費しない。
5. マッチがあれば 1 手消費し、決済へ。

障害格は交換対象に選択不可。ロック格（关卡ルール）も同様。

### 5.2 線分スキャン

各 swap 後の盤面に対して：

- 横・縦の連続格子、長さ ≥ 3 を 1 本の **run** とする。
- 1 本の run は1つのルールのみ適用（D3）。
- 複数の run は交差可能（古典的な L/T 形）、交差格は1回だけ消去。

### 5.3 同種消去

run 内の `speciesId` がすべて同一、かつ障害でなく、大象の特権処理でもない。

- 3 消：基礎点。
- 4 消：追加点、中央格に **肥料** が落ちる（生態肥料と同じ overlay）。
- 5 消：追加点、スキルスロット +1 充電（5.7 参照）。

### 5.4 生態消去（克制チェーン）

判定：**ちょうど 1 体の克制者 + 残りすべてがその克制者の獲物**（3 格なら 1+2）。獲物が同種である必要はない。

| 克制者 | 獲物のマッチ |
|--------|----------|
| 鶏、鴨、鵝 | faction ∈ {flora, veg, fruit, insect}；**crop（五谷）は含まない** |
| 犬 | faction = poultry（鶏鴨鵝鳩など） |
| 豚 | faction ∈ {tree, flora, veg, fruit, insect, crop}；**犬は含まない** |
| 牛、馬 | faction ∈ {flora, crop} または tag `tree_seedling`；昆虫と肉類は含まない |
| 大象 | 5.5 参照、本表は使わない |

効果：

- 全段を消去し、捕食アニメーションを再生（克制者が先に「食べ」、その後獲物と共に退場、または克制者が残留——**第一期は統一して全段退場**とし、残留克制者が落下バランスを崩すのを防ぐ；体験が弱ければ第二期で「克制者残留」スイッチに変更可）。
- 基礎生態点は同種より高い。
- 克制者の元の格子に **肥料** を生成。
- `ecoChainStreak += 1`；同一連鎖内の複数回の生態は streak カウントを1回のノードにだけ加算（resolve 全体の終了時に +1、1回の落下でスキルが満タンになるのを防ぐ）。

**鶏は五谷を食べない**：作物と鶏は同盤に置けるが、生態 run は組めない；作物は同種消去のみ。

### 5.5 大象

- 盤面全体で最大 1 体；リフレッシュ重みは極めて低い；`combo >= 5` の報酬生成のみ、または关卡の `initialPieces` で配置。
- run に 1 体の大象 + 任意の非農具・非障害駒 2 つ → この 3 格をクリア（異なる陣営でも可）。
- 大象は**農具を消せない**（農具は盤面にない、自然に成立）し、障害も消せない（障害は run に入らない）。
- 「大象狂欢」关：開局 1 体、`locked = true`、元の格から交換不可；獲物をその隣に交換して run を作る。

### 5.6 連鎖、重力、リフレッシュ

```
resolve:
  detect runs
  if none → idle
  apply scores, overlays, hp 隣接障害へ
  emit Clear
  gravity: 各列を下から上へ圧縮、stone/tree の実心障害はスキップ；puddle は通過を遮断
  refill: 列の上端から SpawnPool に従って補子（maxApex の制約あり）
  combo++
  goto detect
```

隣接消去は障害に HP を減算：石は隣接の同種/生態のたびに -1、HP=0 で砕ける；木はデフォルトで **锄頭** または关卡「三猪範囲拱」または豚の生態（獲物に木を含む）のみ HP 減算。破壊王关の木は HP=5。

水桶スキル：1 格の水たまりを選択 → overlay をクリア、その列は即座に gravity を1回補う。

### 5.7 スキルスロット（農具）

| スキル | 解放 | 効果 |
|------|------|------|
| 鎌 | 連続 3 回の resolve に生態を含む | 1行または1列をクリック、その線の全 **plant 角色**（crop/veg/fruit/flora）をクリア、手数消費なし、充電を消費 |
| 锄頭 | 同上、または关卡プリセット | 石/木をクリック、直接 HP=0 または -3（关卡設定） |
| 水桶 | 关卡プリセットまたは充電 | 1 格の水たまりを吸い出す |

充電ルール：`ecoResolveCount` が 3 に達する → スロット +1、カウントをリセット。スロット上限 2。鎌/锄頭/水桶は关卡の `allowedSkills[]` でどれが出るか決まる。

### 5.8 計分

```
same_3     = 10 * n * combo * fertilizerMul
eco        = 25 * n * combo * fertilizerMul
elephant   = 40 * n * combo
skill_clear= 8  * n
obstacle   = 20 * brokenCount
```

`combo` は 1 から始まり、連鎖ごとに +1、プレイヤーが次の手動 swap を行うとリセット。肥料は「その格が消去された回」にのみ作用。

---

## 6. 关卡機能

| 关卡 | プール | 勝利 | 失敗 | 特色 |
|------|----|------|------|------|
| 豊穣 | crop/veg/fruit + 高ウェイト poultry | 20 手以内に plant を 50 個消去 | 手数切れ | 鶏鴨鵝が植物の同種消去を妨害 |
| 追い払い | poultry + dog、植物なし | 制限時間内に犬の生態で鶏/鴨を 15 羽消去 | 時間切れ | 家禽の同種消去は目標に数えず、生態必須 |
| 破壊王 | 植物 + 少量の pig + 木 3 本(HP5) | 豚が木 3 本を拱倒す | 手数切れ | 三匹の豚が直線で **3×3 拱撃** を発動（关卡ルール、グローバルではない） |
| 大象狂欢 | 混合プール + 開局ロック大象 | 大象ルールで 30 子消去 | 大象が異常に移動（起こるべきでない）または手数切れ | 大象を保護；システムは2体目を刷らない |

共通 HUD：目標進捗、手数またはカウントダウン、combo、スキルスロット、一時停止/退出。

勝敗判定は1回の resolve（全連鎖アニメーション含む）終了後に決済し、アニメーション途中の誤判定を防ぐ。

---

## 7. Three.js 表現層

| モジュール | 責務 |
|------|------|
| SceneRoot | WebGLRenderer、トーンマッピング、resize、dpr 上限 2 |
| CameraRig | OrthographicCamera、俯仰約 45°、盤面中心を lookAt、OrbitControls 禁止 |
| Lights | Directional（太陽）+ Hemisphere（環境）+ 弱い Rim；リアルタイム影なし、または盤面のみ低解像度シャドウ受信 |
| BoardView | 8×8 の地块；Y 起伏は perlin で事前ベイクした高度マップ（論理格は平坦のまま） |
| PieceFactory | `template` に従って Group を組み立て：球/円柱/円錐/立方体；MeshPhongMaterial；オブジェクトプール |
| InputRaycaster | `Idle/Selected` のときのみ駒 mesh にヒット |
| VFX | 選択アウトライン（自作の発光リング、第一期は全面 OutlinePass にしない）；交換 GSAP；消去 scale+粒子 Points；花粉/蛍は少量の Points でループ |
| HUD | DOM、WebGL に入れない、i18n とアクセシビリティに有利 |

幾何テンプレート（D9）：`grain` `produce` `fruit` `flower` `bug` `bird` `beast` `tree` `apex` `rock`。図鑑は tint と accessory のみ変更。

性能予算：64 駒 + 地块 < 200 draw call（地块は可能な限り merge）；粒子 < 400；低性能端末では粒子と起伏をオフ。

---

## 8. 状態機械

```
Boot → Title → Playing
Playing 子状態:
  Idle → Selected → SwapAnim → ResolveLogic → ClearAnim → GravityAnim → RefillAnim
       ↺ まだマッチがあれば ResolveLogic に戻る（combo）
       → Idle
  Playing → SkillTargeting → SkillAnim → ResolveLogic
  Playing → Won | Lost → Result → Title | next
  Playing → Paused → Playing
```

不正な入力は Idle/Selected/SkillTargeting 以外では破棄。

**コマンド**：`Select` `Swap` `UseSkill` `Pause` `Quit` `AckResult`
**イベント**（EventLog に書く）：`Swapped` `RejectedSwap` `Matches` `Cleared` `Fell` `Refilled` `Combo` `SkillUsed` `Won` `Lost`

---

## 9. プラットフォーム接続

完全なインターフェース契約（launch / balance / bet / settle / refund / play-log / フィーチャーフラグ）は **[api.md](api.md)** を参照。要点：

- 起動：`POST /api/game/launch` が `session_id, api_endpoint, type=self` を返し、`api_endpoint?session_id=&token=` を開く。
- ウォレット：`SelfProvider::bet/settle/refund`、`round_id = session_id + ':' + levelId + ':' + attempt`；領域 `seed = hash(session_id + round_id)`。
- フィーチャーフラグ：`xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet`、オフにすると純粋な同種三消に退化。
- セキュリティ：第一期はクライアント権威の盤面 + サーバー権威のウォレット、単 round は bet/settle を1回のみ；第二期は操作序列をアップロードしてサーバーが再計算。

---

## 10. 非機能要件

| 項目 | 指標 |
|----|------|
| 首屏 | ローポリ + GLTF なし、目標 3s 以内にインタラクション可能（Vite gzip 含む） |
| フレームレート | デスクトップ 60fps；統合 GPU では VFX オフ可 |
| テスト | `domain/**` の単体テストでマッチング/重力/克制/勝敗をカバー；WebGL はテストしない |
| i18n | HUD 文言は key、プラットフォームの `Language` ミドルウェアに追随 |
| アクセシビリティ | キーボード方向選択 + Enter 交換（第二期）；色盲：形状テンプレートを単色より優先 |
| サイズ | FBX を含めない；three + gsap の gzip 後 < 250KB コードを目指す |

---

## 11. 分期

| 期 | 範囲 | 受け入れ条件 |
|----|------|------|
| P0 | 同種三消、8×8、交換/重力/リフレッシュ、直交シーン、3 テンプレート駒 | 目標なしで一局遊べる |
| P1 | 図鑑 + SpawnPool + 四关の目標/手数 HUD | 豊穣关をクリアできる |
| P2 | 克制表 + 生態消去 + 肥料 + combo | 鶏+虫2匹が消せる；五谷は鶏に消されない |
| P3 | 石/木/水たまり + 鎌/锄頭/水桶 | 破壊王关で木を壊せる |
| P4 | 大象 + ロック格 + プラットフォーム bet/settle | 狂欢关；残高の照合 |
| P5 | 粒子、効果音、オブジェクトプール、低性能端末モード、リプレイ | 性能予算を達成 |

P0 はウォレットに接続せず、ローカル `?debug=1` で十分。P4 で初めて `SelfProvider` に接続する。

---

## 12. モジュール責務一覧

| モジュール | 入力 | 出力 | 依存 |
|------|------|------|------|
| Catalog | JSON 図鑑 | PieceDef | なし |
| RestraintTable | 克制設定 | isEcoRun(run) | Catalog |
| Board | コマンド | 新スナップショット | Catalog, RNG |
| MatchDetector | スナップショット | runs[] | RestraintTable |
| Gravity | スナップショット | スナップショット + Fell | Board |
| Level | 消去統計 | 進捗/勝敗 | Board イベント |
| Score | イベント | スコア | Level（倍率） |
| GameStateMachine | コマンド/アニメーション完了 | 状態 | 上記 domain |
| PieceFactory | PieceDef | Object3D | render のみ |
| PlatformAdapter | 勝敗/下注 | HTTP | domain の循環依存なし |
