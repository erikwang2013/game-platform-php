# 田园消消乐 — Architecture technique
<!-- lang-nav -->

Languages: [中文](architecture.md) · [English](architecture.en.md) · [한국어](architecture.ko.md) · [Русский](architecture.ru.md) · [Deutsch](architecture.de.md) · **Français** · [Español](architecture.es.md) · [Português](architecture.pt.md) · [हिन्दी](architecture.hi.md) · [العربية](architecture.ar.md) · [বাংলা](architecture.bn.md) · [Bahasa Indonesia](architecture.id.md) · [日本語](architecture.ja.md)


> Les fonctionnalités côté joueur et les critères d'acceptation sont dans `functional-design.fr.md` ; le calendrier dans `plan.fr.md` ; la vision thématique dans `design.fr.md`.
>
> Ce document répond uniquement à : comment découper les modules, comment se brancher à la plateforme, à quelle couche les règles sont calculées. Aucun code d'implémentation.
>
> Positionnement produit : H5 propriétaire (`game.type = self`), match-3 8×8 sur bac à sable + chaîne de prédation écologique, Three.js low-poly 2.5D.

---

## 0. Décisions d'architecture par rapport au plan

Le plan est une vision de gameplay ; les décisions suivantes résolvent la contradiction « jouable, testable, connectable au portefeuille ».

| ID | Décision | Raison |
|----|------|------|
| D1 | **图鉴 ≠ pièces du plateau** : 100+ espèces sont le catalogue et l'apparence ; le pool de rafraîchissement d'un niveau ne tire que **5–8 espèces** | Avec des dizaines d'espèces simultanées sur 8×8, presque aucun alignement n'est possible |
| D2 | Le matching a deux niveaux : **même espèce via `speciesId`**, **écologie via `role` + table de prédation** | Le plan exige à la fois « trois pommes » et « poule + insecte + insecte » |
| D3 | Priorité des règles d'un même segment : **éléphant > écologie > même espèce** ; mutuellement exclusives, pas de double comptage | Éviter qu'une ligne soit scorée deux fois |
| D4 | **Les outils agricoles n'entrent pas sur le plateau**, uniquement dans les cases de compétences du HUD ; pierre/flaque/arbre sont des obstacles non échangeables | Le chapitre 5 du plan entre en conflit avec la bibliothèque de pièces ; retenir compétences + obstacles |
| D5 | **La logique métier n'a aucune dépendance Three.js** : fonctions pures + snapshots ; la couche de présentation ne fait que s'abonner aux événements | Les règles sont unit-testables, rejouables, et vérifiables côté serveur plus tard |
| D6 | Au lancement, `session_id` dérive une **graine RNG déterministe** ; chutes/rafraîchissements passent tous par ce RNG | Même graine = partie rejouable ; porte ouverte pour l'anti-triche |
| D7 | Pas de moteur physique. Les déplacements/rebonds/éliminations utilisent des easing, sans introduire Cannon/Rapier | Le plan a déjà écrit « animations simulées » ; la physique n'apporte rien à un jeu sur grille |
| D8 | Caméra **orthographique 2.5D à cadrage fixe**, contrôles orbitaux désactivés | Conforme au plan, évite les fausses manipulations et la nausée |
| D9 | Les espèces partagent des **gabarits géométriques par camp + couleur/accessoires**, pas de modélisation par culture | Trafic et délais ; la différence visuelle repose sur la palette et une pièce distinctive |
| D10 | L'entrée de niveau passe par `SelfProvider::bet`, la victoire par `settle`, pas de remboursement de droit d'entrée en cas d'abandon ; `refund` possible avant le premier coup | Aligné sur le portefeuille plateforme et l'idempotence du round |

---

## 1. Contexte système

```
Flutter / HarmonyOS / PC Web
        │  POST /api/game/launch { game_id }
        ▼
service/ (webman :8788)
  GameController::launch  → session_id + seed + api_endpoint
  SelfProvider            → bet / settle / refund / getBalance
  GamePlayLog + EventBus  → game.played / succès / VIP
        │  ouvre api_endpoint?session_id=&token=
        ▼
game/xiaoxiaole/  (ressources statiques, Nginx)
  Vite + TypeScript + Three.js
  moteur métier ──événements──► rendu / HUD
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

Le jeu est un **frontend statique** ; la session faisant autorité et l'argent sont dans `service/`. Le client détient l'état du plateau ; le serveur détient le solde et l'idempotence du round. La première phase ne fait pas de validation serveur à chaque coup, mais la couche métier doit être déterministe pour permettre en phase 2 d'envoyer `seed + séquence d'opérations` au serveur pour recalcul.

---

## 2. Couches du client

Du haut vers le bas, interdiction de dépendance inverse entre couches (`render` ne doit pas être importé par `domain`).

```
app/          assemblage, machine à états, cycle de vie des niveaux
hud/          Overlay HTML : score, pas, objectifs, compétences, résultat
platform/     paramètres de launch, portefeuille, play-log, feature flags
render/       Three.js : scène, plateau, grille de pièces, entrée, VFX
runtime/      bus de commandes, file d'animations, rejeu
domain/       plateau, matching, prédation, gravité, score, catalogue, règles de niveau
config/       table de prédation, poids de rafraîchissement, recettes géométriques, JSON de niveaux
```

**Boucle principale (les règles ne sont pas calculées dans `requestAnimationFrame`)** : entrée → commande → résolution synchrone dans le domaine (un seul swap calcule toutes les chaînes, produit une liste d'événements) → le runtime met les animations en file selon les événements → l'entrée suivante n'est acceptée qu'à la fin des animations.

Ainsi « une frame de logique, plusieurs frames d'affichage », et le combo ne se bat pas avec le clic pour l'état.

---

## 3. Structure de répertoires (suggestion)

```
game/xiaoxiaole/
├── design.md
├── architecture.md          ← ce document
├── package.json             # vite, three, gsap, vitest, typescript
├── index.html
├── src/
│   ├── main.ts              # lit l'URL, démarre GameApp
│   ├── app/
│   │   ├── GameApp.ts
│   │   └── GameStateMachine.ts
│   ├── domain/
│   │   ├── catalog/         # PieceDef, Faction, Role
│   │   ├── board/           # Grille 8×8, Cell, PieceInstance
│   │   ├── match/           # MatchDetector (même espèce / écologie / éléphant)
│   │   ├── eco/             # RestraintTable
│   │   ├── gravity/         # chute, blocage par flaque, rafraîchissement
│   │   ├── score/           # score, multiplicateur d'engrais
│   │   ├── level/           # LevelDef, Objective, Victoire/Défaite
│   │   └── rng/             # mulberry32 à graine
│   ├── runtime/
│   │   ├── CommandBus.ts    # Select, Swap, UseSkill, Quit
│   │   ├── EventLog.ts      # événements sérialisables, pour le rejeu
│   │   └── AnimationQueue.ts
│   ├── render/
│   │   ├── SceneRoot.ts
│   │   ├── CameraRig.ts
│   │   ├── BoardView.ts
│   │   ├── PieceFactory.ts  # géométrie par gabarit
│   │   ├── InputRaycaster.ts
│   │   └── vfx/
│   ├── hud/
│   ├── platform/
│   │   ├── ApiClient.ts
│   │   └── Session.ts
│   └── levels/*.json
└── tests/domain/            # sans WebGL
```

Pas plus de 500 lignes par fichier. `MatchDetector` et `PieceFactory`, s'ils enflent, sont redécoupés par type de règle / gabarit de camp.

---

## 4. Modèle métier

### 4.1 Définition des pièces (catalogue)

```
PieceDef
  id            speciesId        ex. wheat, hen, elephant
  faction       Faction          crop | veg | fruit | flora | insect | poultry | livestock | tree | tool | obstacle | apex
  role          Role             plant | prey | predator_mid | predator_high | apex | obstacle | skill
  tags[]                         crop, edible_by_poultry, tree_seedling, bone …
  rarity        common | rare | legendary
  template      GeometryTemplate nom du gabarit géométrique
  tint          RGB              palette dans le gabarit
  accessory     optionnel        bec, pétales, trompe… pièces distinctives
```

Cultures/légumes/fruits/fleurs/insectes/volailles/bétail/arbres du plan entrent tous dans le catalogue ; **tool n'est pas généré dans les cases**. L'éléphant a `rarity = legendary`, `role = apex`.

### 4.2 Cases et plateau

```
Cell
  q, r               colonne, ligne (0–7)
  height             relief du terrain (rendu seul, n'entre pas dans les règles)
  occupant           PieceInstance | null
  overlay            none | fertilizer | puddle | stone(hp) | tree(hp)
  locked             bool          niveau carnaval de l'éléphant : l'éléphant ne peut pas être échangé

PieceInstance
  uid, speciesId, def
  special            none | fertilizer_token
```

- **Pierre / arbre** : occupent la case, non échangeables, non traversables par les chutes. HP selon le niveau.
- **Flaque** : posée sur la case, bloque la gravité à travers cette case (la pièce au-dessus s'arrête sur la case précédant la flaque).
- **Engrais** : reste sur la case après une élimination écologique ; la prochaine élimination impliquant cette case marque ×2, puis disparaît.

### 4.3 Pool de rafraîchissement (niveau)

```
SpawnPool
  speciesIds[]       5–8
  weights[]          aligné sur species
  maxApex            défaut 1
  apexUnlock         généré par le système à combo >= 5, « génération par échange » interdite
```

Le niveau ne tire ses pièces que de son pool via `rng`. Le catalogue a beau être grand, l'entropie du plateau reste maîtrisée.

---

## 5. Moteur de règles central (design fonctionnel)

### 5.1 Opérations

1. Clic sur la pièce A → sélection (rebond + contour).
2. Nouveau clic sur la case adjacente orthogonale B → tentative d'échange (diagonale interdite).
3. Clic sur une case non adjacente / vide → changement de sélection ou annulation.
4. Après l'échange, s'il n'y a **aucun matching valide**, rejouer l'échange en arrière, sans consommer de pas.
5. S'il y a un matching, consommer 1 pas et entrer en résolution.

Une case obstacle ne peut pas être une cible d'échange. Idem pour les cases verrouillées (règle de niveau).

### 5.2 Balayage des segments

Pour chaque plateau après swap :

- Les cases continues en horizontal/vertical, longueur ≥ 3, forment un **run**.
- Un run n'applique qu'une seule règle (D3).
- Plusieurs runs peuvent se croiser (formes L/T classiques) ; une case croisée n'est éliminée qu'une fois.

### 5.3 Élimination par même espèce

Dans un run, `speciesId` tous identiques, non obstacle, hors traitement spécial de l'éléphant.

- 3 éliminés : score de base.
- 4 éliminés : score bonus, et **engrais** tombe sur la case centrale (même overlay que l'engrais écologique).
- 5 éliminés : score bonus, charge de compétence +1 (voir 5.7).

### 5.4 Élimination écologique (chaîne de prédation)

Critère : **exactement 1 prédateur + le reste composé uniquement de ses proies** (3 cases = 1+2). Les proies n'ont pas besoin d'être de la même espèce.

| Prédateur | Proies correspondantes |
|--------|----------|
| Poule, canard, oie | faction ∈ {flora, veg, fruit, insect} ; **sans crop (cinq céréales)** |
| Chien | faction = poultry (poule, canard, oie, pigeon…) |
| Cochon | faction ∈ {tree, flora, veg, fruit, insect, crop} ; **sans chien** |
| Bœuf, cheval | faction ∈ {flora, crop} ou tag `tree_seedling` ; sans insectes ni viande |
| Éléphant | voir 5.5, hors de ce tableau |

Effets :

- Élimination de tout le segment, animation de prédation (le prédateur « mange » d'abord, puis sort avec ses proies, ou reste — **en phase 1, tout le segment sort**, pour éviter qu'un prédateur restant casse l'équilibre des chutes ; si l'expérience paraît trop faible, la phase 2 ajoute l'option « le prédateur reste »).
- Le score écologique de base est supérieur au même-espèce.
- **Engrais** généré sur la case d'origine du prédateur.
- `ecoChainStreak += 1` ; plusieurs éliminations écologiques dans une même chaîne n'ajoutent qu'un seul nœud de streak (incrément à la fin de toute la résolution, pour éviter qu'une seule chute remplisse la compétence).

**La poule ne mange pas les cinq céréales** : cultures et poules peuvent cohabiter sur le plateau, mais ne forment pas de run écologique ; les cultures ne s'éliminent que par même espèce.

### 5.5 Éléphant

- Au plus 1 sur le plateau ; poids de rafraîchissement très faible ; généré uniquement en récompense à `combo >= 5`, ou placé par `initialPieces` du niveau.
- Un run contenant 1 éléphant + 2 pièces quelconques non outils, non obstacles → vide ces 3 cases (camps différents autorisés).
- L'éléphant ne **peut pas** éliminer les outils (ils ne sont pas sur le plateau, satisfait naturellement) ni les obstacles (ils n'entrent pas dans les runs).
- Niveau « carnaval de l'éléphant » : 1 éléphant au départ, `locked = true`, ne peut pas être échangé hors de sa case ; les proies sont déplacées à côté de lui pour former un run.

### 5.6 Chaînes, gravité, rafraîchissement

```
resolve:
  détecter les runs
  aucun → idle
  appliquer scores, overlays, hp aux obstacles adjacents
  émettre Clear
  gravity: chaque colonne compactée de bas en haut, en sautant les obstacles pleins stone/tree ; puddle bloque la traversée
  refill: compléter depuis le haut de colonne selon SpawnPool (contraint par maxApex)
  combo++
  retour à détecter
```

Les éliminations adjacentes infligent des HP aux obstacles : la pierre perd -1 à chaque même-espèce/écologie adjacente, HP=0 → éclatement ; l'arbre ne perd des HP que via la **houe**, le « groin 3×3 » du niveau, ou l'écologie du cochon (proie incluant un arbre). Arbres du niveau roi de la destruction : HP=5.

Compétence seau : sélectionner une case flaque → suppression de l'overlay, la colonne exécute immédiatement une passe de gravité.

### 5.7 Cases de compétences (outils agricoles)

| Compétence | Déblocage | Effet |
|------|------|------|
| Faucille | 3 résolutions consécutives contenant de l'écologie | Clic sur une ligne ou une colonne, élimine tous les personnages **plant** (crop/veg/fruit/flora) de cette ligne, sans consommer de pas, consomme la charge |
| Houe | idem, ou préinstallée par le niveau | Clic sur pierre/arbre, HP=0 direct ou -3 (selon le niveau) |
| Seau | préinstallée par le niveau ou charge | Assèche une case flaque |

Règle de charge : `ecoResolveCount` atteint 3 → +1 case, compteur remis à zéro. Maximum 2 cases. La faucille/la houe/le seau présents selon `allowedSkills[]` du niveau.

### 5.8 Score

```
same_3     = 10 * n * combo * fertilizerMul
eco        = 25 * n * combo * fertilizerMul
elephant   = 40 * n * combo
skill_clear= 8  * n
obstacle   = 20 * brokenCount
```

`combo` part de 1, +1 à chaque chaîne, remis à 1 au prochain swap manuel du joueur. L'engrais n'affecte que « l'élimination de cette case-là ».

---

## 6. Fonctionnalités de niveaux

| Niveau | Pool | Victoire | Défaite | Particularité |
|------|----|------|------|------|
| Moisson | crop/veg/fruit + poultry à poids élevé | Éliminer 50 plant en 20 pas | Pas épuisés | Poules/canards/oies perturbent l'élimination même-espèce des plantes |
| Expulsion | poultry + dog, sans plantes | En temps limité, éliminer 15 poules/canards via l'écologie du chien | Dépassement du temps | L'élimination même-espèce de volailles ne compte pas pour l'objectif, l'écologie est obligatoire |
| Roi de la destruction | plantes + quelques pig + 3 arbres (HP5) | Le cochon abat les 3 arbres | Pas épuisés | Trois cochons alignés déclenchent le **groin 3×3** (règle de niveau, pas globale) |
| Carnaval de l'éléphant | pool mixte + éléphant verrouillé au départ | Éliminer 30 pièces via la règle de l'éléphant | L'éléphant anormalement déplacé (ne devrait pas arriver) ou pas épuisés | Protéger l'éléphant ; le système ne fait pas apparaître de deuxième |

HUD commun : progression d'objectif, pas ou compte à rebours, combo, cases de compétences, pause/quitter.

La victoire/défaite est réglée après la fin d'une résolution (y compris toutes les animations de chaînes), pour éviter les jugements au milieu des animations.

---

## 7. Couche de présentation Three.js

| Module | Responsabilité |
|------|------|
| SceneRoot | WebGLRenderer, tone mapping, resize, dpr plafonné à 2 |
| CameraRig | OrthographicCamera, inclinaison ~45°, lookAt au centre du plateau, OrbitControls interdit |
| Lights | Directional (soleil) + Hemisphere (ambiance) + Rim léger ; pas d'ombres en temps réel ou uniquement une shadow map basse résolution sur le plateau |
| BoardView | Parcelles 8×8 ; relief Y via heightmap perlin pré-bakée (la case logique reste plate) |
| PieceFactory | Groupe selon `template` : sphère/cylindre/cône/cube ; MeshPhongMaterial ; pool d'objets |
| InputRaycaster | Ne teste que les meshes de pièces en `Idle/Selected` |
| VFX | Contour de sélection (halo lumineux dessiné à la main, pas d'OutlinePass plein écran en phase 1) ; échange GSAP ; élimination scale + particules Points ; pollen/lucioles via quelques Points en boucle |
| HUD | DOM, hors WebGL, pour l'i18n et l'accessibilité |

Gabarits géométriques (D9) : `grain` `produce` `fruit` `flower` `bug` `bird` `beast` `tree` `apex` `rock`. Le catalogue ne change que le tint et l'accessory.

Budget de performance : 64 pièces + parcelles < 200 draw calls (fusion des parcelles autant que possible) ; particules < 400 ; sur les machines d'entrée de gamme, désactivation des particules et du relief.

---

## 8. Machine à états

```
Boot → Title → Playing
Sous-états de Playing:
  Idle → Selected → SwapAnim → ResolveLogic → ClearAnim → GravityAnim → RefillAnim
       ↺ s'il reste des matchings, retour à ResolveLogic (combo)
       → Idle
  Playing → SkillTargeting → SkillAnim → ResolveLogic
  Playing → Won | Lost → Result → Title | suivant
  Playing → Paused → Playing
```

Les entrées illégales sont ignorées hors des états Idle/Selected/SkillTargeting.

**Commandes** : `Select` `Swap` `UseSkill` `Pause` `Quit` `AckResult`  
**Événements** (écrits dans EventLog) : `Swapped` `RejectedSwap` `Matches` `Cleared` `Fell` `Refilled` `Combo` `SkillUsed` `Won` `Lost`

---

## 9. Intégration plateforme

Le contrat d'interface complet (launch / balance / bet / settle / refund / play-log / feature flags) est dans **[api.fr.md](api.fr.md)**. Points clés :

- Lancement : `POST /api/game/launch` renvoie `session_id, api_endpoint, type=self`, ouvre `api_endpoint?session_id=&token=`.
- Portefeuille : `SelfProvider::bet/settle/refund`, `round_id = session_id + ':' + levelId + ':' + attempt` ; seed du domaine `seed = hash(session_id + round_id)`.
- Feature flags : `xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet` ; désactivés, le jeu se réduit au match-3 même-espèce pur.
- Sécurité : phase 1, plateau côté client faisant autorité + portefeuille côté serveur faisant autorité, un seul bet/settle par round ; phase 2, envoi de la séquence d'opérations pour recalcul serveur.

---

## 10. Non-fonctionnel

| Élément | Indicateur |
|----|------|
| Premier écran | Low-poly sans GLTF, objectif interactif en 3 s (gzip Vite compris) |
| FPS | 60 fps sur desktop ; VFX désactivables sur GPU intégré |
| Tests | `domain/**` couvert en unitaire : matching/gravité/prédation/victoire-défaite ; pas de test WebGL |
| i18n | Clés de texte du HUD, suit le middleware `Language` de la plateforme |
| Accessibilité | Sélection au clavier + échange à Entrée (phase 2) ; daltonisme : gabarits de formes avant la couleur |
| Volume | Pas de FBX ; three + gsap gzippés visent < 250 Ko de code |

---

## 11. Phasage

| Phase | Périmètre | Acceptation |
|----|------|------|
| P0 | Match-3 même-espèce, 8×8, échange/gravité/rafraîchissement, scène orthographique, 3 gabarits de pièces | Une partie jouable sans objectif |
| P1 | Catalogue + SpawnPool + objectifs/pas des quatre niveaux + HUD | Le niveau moisson est finissable |
| P2 | Table de prédation + élimination écologique + engrais + combo | Poule + deux insectes éliminables ; les cinq céréales ne sont pas mangées par la poule |
| P3 | Pierre/arbre/flaque + faucille/houe/seau | Le niveau roi de la destruction permet d'abattre les arbres |
| P4 | Éléphant + cases verrouillées + bet/settle plateforme | Niveau carnaval ; rapprochement du solde |
| P5 | Particules, sons, pool d'objets, profil machines d'entrée de gamme, rejeu | Budget de performance atteint |

P0 sans portefeuille : jouable en local avec `?debug=1`. `SelfProvider` n'est branché qu'en P4.

---

## 12. Récapitulatif des responsabilités des modules

| Module | Entrée | Sortie | Dépendances |
|------|------|------|------|
| Catalog | Catalogue JSON | PieceDef | Aucune |
| RestraintTable | Configuration de prédation | isEcoRun(run) | Catalog |
| Board | Commandes | Nouveau snapshot | Catalog, RNG |
| MatchDetector | Snapshot | runs[] | RestraintTable |
| Gravity | Snapshot | Snapshot + Fell | Board |
| Level | Statistiques d'élimination | Progression/victoire-défaite | Événements Board |
| Score | Événements | Score | Level (multiplicateurs) |
| GameStateMachine | Commandes/fin d'animation | État | Le domain ci-dessus |
| PieceFactory | PieceDef | Object3D | render uniquement |
| PlatformAdapter | Victoire-défaite/mise | HTTP | Aucune dépendance circulaire du domain |
