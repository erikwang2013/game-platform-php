<!-- lang-nav -->

Languages: [中文](design.md) · **English** · [한국어](design.ko.md) · [Русский](design.ru.md) · [Deutsch](design.de.md) · [Français](design.fr.md) · [Español](design.es.md) · [Português](design.pt.md) · [हिन्दी](design.hi.md) · [العربية](design.ar.md) · [বাংলা](design.bn.md) · [Bahasa Indonesia](design.id.md) · [日本語](design.ja.md)

OK, as your game designer and 3D technical lead, here is a complete design plan for the Three.js Match-3 game. This plan contains no code; it focuses on **element expansion**, **rule matrix**, **gameplay fusion mechanics**, and **Three.js scene construction ideas**.

---

### 1. Game Element Expansion (Piece Library Design)

To make the board richer, I have broken down the elements into **6 major factions**, totaling **24 kinds** of base pieces + **4 kinds** of special items based on what you provided:

| Faction | Elements | Notes |
| :--- | :--- | :--- |
| **🌾 Crops** | Base clear pieces | Rice, wheat, corn, sorghum, barley, oats, rye, millet, sesame, peanuts, cotton, rapeseed, tea, yellow millet, pearl barley, buckwheat, soybeans, mung beans, red beans, black beans, fava beans, peas, sweet potatoes, potatoes, yams, taro, cassava |
| **🥬 Vegetables** | Base clear pieces | Cabbage, radish, cucumber, tomato, chili, eggplant, scallion, ginger, garlic, lettuce, carrot, bitter melon, cilantro, chives, mustard greens, celery, spinach, cauliflower, winter melon, pumpkin, leek |
| **🥬 Fruits** | Base clear pieces | Apple, pear, peach, apricot, plum, strawberry, watermelon, grape, sour jujube, Chinese dwarf cherry, jujube, walnut, almond, fig, orange, banana, persimmon, pomegranate, kiwi, cherry |
| **🥬 Flowers/Herbs** | Base clear pieces | Rose, sunflower, monthly rose, evening primrose, henna, cockscomb, hibiscus, camellia, peony, jasmine, wisteria, moth orchid, chrysanthemum, plum blossom, orchid, lotus, plantain, rehmannia, goji berry, foxtail grass, dandelion, goosegrass, cloud vegetable |
| **🐜 Animals** | Base clear pieces | Ant, bee, seven-spotted ladybug, caterpillar, cicada, hornet, cricket, grasshopper, lizard, mouse, centipede, leech, frog, toad, shrimp, fish, fox, squirrel, butterfly, mantis, spider, firefly |
| **🐓 Poultry/Birds** | Mid-level predators | Chicken, duck, goose, pigeon, sparrow, magpie, swallow, crow, owl, eagle |
| **🐕 Livestock/Large Animals** | High-level pieces | Pig, dog, cattle, horse, sheep, rabbit, cat, donkey, mule, camel |
| **🌳 Trees/Nature** | Obstacles/special pieces | Pine, willow, poplar, locust, paulownia, phoenix tree, fir, ginkgo, elm, bamboo, birch, maple |
| **🔧 Farm tools** | Skill items | Sickle, hoe, water bucket, hammer, rake, winnowing basket, backpack, straw hat, straw rain cape, flashlight, stone roller, cart, bicycle, axe, shoulder pole, plow, millstone |

---

### 2. Core Rule Expansion ("Ecology Restraint Chain" Design)

Your rule logic is essentially **"targeted clearing"**. On top of traditional match-3 (three identical pieces clear), we embed **"predation/restraint matching"**. When the player lines up a **predator** with **prey** three in a row (or in a specific shape), an advanced clear triggers.

Here is the **complete restraint matrix** I've expanded for you (A restrains B):

| Predator (A) | Restraint method | Prey (B) | Expanded rule notes |
| :--- | :--- | :--- | :--- |
| **Chicken, duck, goose** | Pecking / predation | Flowers/herbs, fruits/vegetables, insects (ant/ladybug/caterpillar) | Addition: they do **not eat** grains (crops), because grains are too hard and need separate clearing. |
| **Dog** | Biting | Chicken, duck, goose, pigeon | Dogs not only bite poultry; addition: **dogs also gnaw bones (from pigs/cattle/horses)**, but for simplicity in the game, they restrain all small-to-medium poultry. |
| **Pig** | Rooting / trashing | Trees, flowers/herbs, fruits/vegetables, insects, **all grains and crops** | The pig is the destroyer king. Addition: pigs do **not** ram dogs (because dogs bite pigs), forming a restraint loop. |
| **Cattle, horse** | Grazing / trampling | Flowers/herbs, **grain crops**, fruit tree saplings | Addition: cattle and horses are large herbivores, specifically restraining crops, but do not eat insects or meat. |
| **Elephant** | Absolute dominance (stomp/fling) | **All elements except elephants (including pigs, dogs, cattle, horses)** | The elephant is the top power. For balance, addition: elephants **cannot** clear "farm tools" (items), and elephants appear at extremely low probability on the board (rare pieces). |
| **Sickle (item)** | Harvesting | All grain crops, flowers/herbs | Clears all plant-type pieces in one horizontal or vertical line. |
| **Hoe (item)** | Smashing | Trees, rocks (obstacles) | Specially clears high-HP obstacles. |

---

### 3. Gameplay Mechanics Design (How to Play the Match-3)

In the Three.js 3D scene, we use a **"click-to-swap + eco determination"** fused mode:

1. **Basic operation**: the player clicks two adjacent 3D pieces to swap their positions.
2. **Determination logic (key)**:
   - **Same-type clear**: after swapping, if **≥3 identical pieces** line up horizontally or vertically, execute a base clear (e.g. three apples).
   - **Eco clear (special)**: after swapping, if a **"predator + any two prey"** line forms horizontally or vertically (e.g. chicken + bug + bug), **the three need not be identical** — the "predation animation" triggers directly, the chicken eats the bugs, bonus points are awarded, and the cell spawns a **"dung fertilizer"** (buff, next clear scores double).
   - **Elephant privilege**: when an elephant lines up with **any two different pieces**, a "bullying" effect triggers, directly clearing those three cells regardless of species.
3. **Chain reactions (Combo)**: after pieces clear, pieces above drop to fill the gaps. If a drop creates a new "eco restraint chain", a combo triggers automatically (no player action needed), delivering satisfying gameplay.

---

### 4. Three.js Scene and Visual Planning (No Code)

To give the 3D match-3 more texture than 2D, the plan is:

| Module | Technical choice/design |
| :--- | :--- |
| **Camera angle** | **45-degree orthographic view (OrthographicCamera)** or **fixed perspective view**. The board looks like a "3D sandbox", easy to see front-to-back stacking. Recommended: 2.5D fixed view, no orbit controls (to prevent player dizziness). |
| **Board layout** | An **8x8 grid**, but each cell gets **Y-axis height variation** (simulating a field/hill feel). Pieces sit on cylinders or square columns with reflective discs at the base. |
| **3D model approach** | No heavy external FBX/GLTF (too much traffic). **All use Three.js base geometry combinations (Group)**:<br>- **Apple**: sphere + cylinder stem.<br>- **Chicken**: ellipsoid (body) + cone (beak) + sphere (head).<br>- **Elephant**: large cube (body) + stretched cylinder (trunk) + flat ears.<br>- **Tree**: cone (crown) + cylinder (trunk).<br>Use **Low Poly** style with **soft lighting materials (MeshPhongMaterial)**, bright colors. |
| **Lighting & atmosphere** | Main light: directional (simulating sunlight). Fill light: ambient (brightening shadows). Backlight: area light (outlining edges). Background uses a gradient sky sphere with floating particles (simulating pollen or fireflies). |
| **Interaction feedback** | **Raycaster (ray casting)** for click picking. When a piece is clicked, it **bounces up + glows with an outline (OutlinePass)**; swaps use **smooth interpolation movement (TWEEN or GSAP)**; clears play **scale-down disappear + particle explosion** effects (using PointsMaterial). |

---

### 5. Special Items and Obstacle Expansion

To give the game strategic depth, the following mechanics are added:

1. **Obstacles (immovable)**:
   - **Rock**: occupies a cell; needs the "hoe" item or three adjacent normal clears to break.
   - **Puddle**: pieces cannot drop through; needs the "water bucket" item to drain.
2. **How "farm tools" are obtained**:
   - They do not appear directly on the board. When the player **triggers "eco restraint" 3 times in a row**, the skill slot below the screen charges up; clicking it releases the corresponding farm tool skill (e.g. the sickle swipes across the screen to clear a line).

---

### 6. Level and Objective Planning

| Level type | Win condition | Feature restriction |
| :--- | :--- | :--- |
| **Harvest level** | Within 20 steps, clear **50 grains/fruits and vegetables**. | Chickens, ducks and geese appear heavily on the board, interfering with plant clears. |
| **Herding level** | Within the time limit, use the **dog** to clear **15 chickens/ducks**. | Only poultry and dogs spawn on the board, no plants. |
| **Destroyer King level** | Use the **pig** to ram down **3 big trees** (tree HP 5). | Pigs appear at low probability; three pigs must be gathered to trigger the area rampage. |
| **Elephant Carnival** | Use the elephant to clear any **30 pieces**. | One elephant is gifted at the start; protect it, it cannot be swapped away. |

---

### 7. Summary of Plan Highlights

1. **Self-consistent rules**: the biological food chain (chickens eat bugs, dogs bite chickens, elephants are invincible) is perfectly converted into match-3 "pairing conditions", making it more educational and strategic than plain match-3.
2. **3D expressiveness**: using Three.js's physics engine (or simulated animation), pieces have "gravity drops" and "elastic bouncing", far more dimensional than 2D sprites.
3. **Balance guarantee**: because the elephant is too strong, it is set as a rare spawn (at most 1 on the board per round), cannot be generated through normal swaps, and can only be spawned by the system as a reward after 5 combos, preventing imbalance.

This plan keeps classic match-3's "easy to learn" while embedding a unique "ecological animal chess" core, and is fully feasible in Three.js (pure Geometry combinations + basic Shader). You can start development directly from this blueprint. If you need to dig into details (such as specific animation curves or particle color schemes), just tell me. 🐘🌾

