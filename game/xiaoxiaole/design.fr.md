<!-- lang-nav -->

Languages: [中文](design.md) · [English](design.en.md) · [한국어](design.ko.md) · [Русский](design.ru.md) · [Deutsch](design.de.md) · **Français** · [Español](design.es.md) · [Português](design.pt.md) · [हिन्दी](design.hi.md) · [العربية](design.ar.md) · [বাংলা](design.bn.md) · [Bahasa Indonesia](design.id.md) · [日本語](design.ja.md)

D'accord, en tant que votre game designer et responsable technique 3D, voici une planification complète pour le jeu 《Three.js 消消乐》. Ce plan ne contient pas de code ; il se concentre sur l'**enrichissement des éléments**, la **matrice de règles**, les **mécanismes de gameplay fusionnés** et les **idées de montage de la scène Three.js**.

---

### I. Enrichissement des éléments de jeu (conception de la bibliothèque de pièces)

Pour enrichir le plateau, à partir de votre base, je détaille les éléments en **6 grandes familles**, soit au total **24 types** de pièces de base + **4 types** d'accessoires spéciaux :

| Famille | Éléments inclus | Complément |
| :--- | :--- | :--- |
| **🌾 Cultures** | Pièces de base à éliminer | riz, blé, maïs, sorgho, orge, avoine, seigle, millet, sésame, arachide, coton, colza, thé, millet jaune, larmes-de-Job, sarrasin, soja jaune, haricot mungo, haricot rouge, soja noir, fève, pois, patate douce, pomme de terre, igname, taro, manioc |
| **🥬 Légumes** | Pièces de base à éliminer | chou chinois, radis, concombre, tomate, piment, aubergine, oignon, gingembre, ail, laitue, carotte, melon amer, coriandre, ciboule, moutarde, céleri, épinard, chou-fleur, courge d'hiver, potiron, poireau |
| **🥬 Fruits** | Pièces de base à éliminer | pomme, poire, pêche, abricot, prune, fraise, pastèque, raisin, jujube sauvage, cerisier des oiseaux, jujube, noix, amande, figue, mandarine, banane, kaki, grenade, kiwi, griotte, cerise |
| **🥬 Fleurs et herbes** | Pièces de base à éliminer | rose, tournesol, rosier de Chine, mauve, henné, célosie, hibiscus, camélia, pivoine, jasmin, glycine, phalaenopsis, chrysanthème, prunier, orchidée, lotus, plantain, digitale, lyciet, sétaire, pissenlit, chiendent, fleur d'épinard |
| **🐜 Animaux** | Pièces de base à éliminer | fourmi, abeille, coccinelle à sept points, chenille, cigale, guêpe, grillon, sauterelle, lézard, souris, sangsue, grenouille, crapaud, crevette, poisson, renard, écureuil, papillon, mante, araignée, luciole |
| **🐓 Volailles / oiseaux** | Prédateurs intermédiaires | poule, canard, oie, pigeon, moineau, pie, hirondelle, corneille, hibou, aigle |
| **🐕 Bétail / grands animaux** | Pièces de niveau supérieur | cochon, chien, bœuf, cheval, mouton, lapin, chat, âne, mulet, chameau |
| **🌳 Arbres / nature** | Obstacles / pièces spéciales | pin, saule, peuplier, robinier, paulownia, platane, sapin, ginkgo, orme, bambou, bouleau, érable |
| **🔧 Outils agricoles** | Accessoires de compétences | faucille, houe, seau, marteau, râteau, van, hotte, chapeau de paille, cape de paille, lampe torche, rouleau de pierre, chariot, vélo, hache, palanche, charrue, meule |

---

### II. Extension des règles de base (conception de la « chaîne de contrainte écologique »)

Votre logique de règle est par essence une **« élimination ciblée »**. Sur la base du match-3 traditionnel (trois identiques s'éliminent), nous intégrons une **« correspondance prédation / contrainte »**. Lorsque le joueur aligne le **prédateur** avec les **proies** sur trois cases (ou une forme particulière), l'élimination avancée se déclenche.

Voici la **matrice de contraintes complète** que je vous propose (A contraint B) :

| Prédateur (A) | Mode de contrainte | Proie (B) | Règle étendue |
| :--- | :--- | :--- | :--- |
| **Poule, canard, oie** | picorer / chasser | fleurs, légumes-fruits, insectes (fourmi/coccinelle/chenille) | Complément : ils **ne mangent pas** les cinq céréales (récoltes), trop dures, qui doivent être éliminées séparément. |
| **Chien** | mordre | poule, canard, oie, pigeon | Le chien ne mord pas seulement la volaille ; complément : **le chien ronge aussi les os (ceux des cochons/bœufs/chevaux)**, mais par simplification, il contraint toute la petite et moyenne volaille. |
| **Cochon** | fouiller / saccager | arbres, fleurs, légumes-fruits, insectes, **toutes les cinq céréales** | Le cochon est le roi de la destruction ; complément : le cochon **ne fouille pas** le chien (le chien le mord), formant une boucle de contrainte fermée. |
| **Bœuf, cheval** | brouter / piétiner | fleurs, **cinq céréales**, jeunes arbres fruitiers | Complément : les bovins et équidés, grands herbivores, contrent spécifiquement les cultures, mais ne mangent ni insectes ni viande. |
| **Éléphant** | domination absolue (piétiner / projeter) | **tous les éléments sauf l'éléphant (y compris cochon, chien, bœuf, cheval)** | L'éléphant est la force de pointe. Pour équilibrer : l'éléphant **ne peut pas** éliminer les « outils agricoles » (accessoires), et sa probabilité d'apparition sur le plateau est très faible (pièce rare). |
| **Faucille (accessoire)** | moissonner | toutes les cinq céréales, fleurs | Élimine d'un coup toute une rangée ou colonne d'éléments végétaux. |
| **Houe (accessoire)** | fracasser | arbres, pierres (obstacles) | Élimine spécifiquement les obstacles à points de vie élevés. |

---

### III. Conception des mécanismes de gameplay (comment jouer au « match-3 »)

Dans la scène 3D Three.js, nous adoptons un mode fusionné **« échange au clic + jugement écologique »** :

1. **Opération de base** : le joueur clique sur deux pièces 3D adjacentes pour les échanger.
2. **Logique de jugement (clé)** :
    - **Élimination par identité** : après l'échange, si une rangée ou colonne forme **≥3 pièces identiques**, élimination de base (ex. trois pommes).
    - **Élimination écologique (spéciale)** : après l'échange, si une rangée ou colonne forme **« prédateur + deux proies quelconques »** (ex. : poule + ver + ver), sans exiger trois identiques, l'« animation de prédation » se déclenche : la poule mange les vers, bonus de points, et la case génère un **« engrais de fumier »** (buff, le prochain élimination marque le double de points).
    - **Privilège de l'éléphant** : l'éléphant aligné avec **deux pièces différentes quelconques** déclenche l'effet « intimidation », vidant directement les trois cases, sans tenir compte de l'espèce.
3. **Réactions en chaîne (Combo)** : après l'élimination, les pièces du dessus tombent pour combler. Si la chute crée une nouvelle « chaîne de contrainte écologique », l'enchaînement se déclenche automatiquement (sans action du joueur), pour un effet satisfaisant.

---

### IV. Scène et visuels Three.js (sans code)

Pour que le match-3 3D ait plus de matière que la 2D, voici le plan :

| Module | Choix technique / plan de conception |
| :--- | :--- |
| **Angle de caméra** | **Vue orthographique à 45 degrés (OrthographicCamera)** ou **vue perspective fixe**. Le plateau doit ressembler à un « bac à sable en relief », facile à observer en profondeur. Recommandation : vue fixe 2.5D, sans contrôle orbital (pour éviter le vertige). |
| **Disposition du plateau** | **Grille 8x8**, mais chaque case gagne une **variation de hauteur sur l'axe Y** (sensation de champs vallonnés). Les pièces sont surélevées par des cylindres ou cubes, avec un disque de réflexion en dessous. |
| **Modèles 3D** | Pas de chargement de FBX/GLTF externes complexes (trop de trafic). **Uniquement des primitives géométriques Three.js combinées (Group)** :<br>- **Pomme** : sphère + tige cylindrique.<br>- **Poule** : ellipsoïde (corps) + cône (bec) + sphère (tête).<br>- **Éléphant** : grand cube (corps) + cylindre étiré (trompe) + oreilles en plaques.<br>- **Arbre** : cône (houppier) + cylindre (tronc).<br>Style **Low Poly** avec **matériaux à lumière douce (MeshPhongMaterial)**, couleurs vives. |
| **Lumières et ambiance** | Lumière principale : lumière directionnelle (simule le soleil). Lumière secondaire : lumière ambiante (éclaircit les ombres). Contre-jour : lumière de zone (délimite les contours). Fond en sphère de ciel en dégradé, avec particules flottantes (pollen ou lucioles). |
| **Retour interactif** | **Raycaster (détection de rayons)** pour la sélection au clic. Au clic sur une pièce : **rebond + contour lumineux (OutlinePass)** ; à l'échange : **interpolation fluide (TWEEN ou GSAP)** ; à l'élimination : **disparition en zoom + explosion de particules** (PointsMaterial). |

---

### V. Accessoires spéciaux et obstacles étendus

Pour donner de la profondeur stratégique au jeu, ajoutons ces mécanismes :

1. **Obstacles (immobiles)** :
    - **Pierre** : occupe une case, nécessite l'accessoire « houe » ou trois éliminations normales adjacentes pour se briser.
    - **Flaque** : les pièces ne peuvent pas la traverser en tombant, nécessite l'accessoire « seau » pour l'assécher.
2. **Obtention des « outils agricoles »** :
    - Ils n'apparaissent pas directement sur le plateau. Quand le joueur déclenche **3 « contraintes écologiques » d'affilée**, la barre de compétences sous l'écran se charge ; au clic, la compétence d'outil correspondante se libère (ex. la faucille balaie une rangée).

---

### VI. Niveaux et objectifs

| Type de niveau | Condition de victoire | Limitation spécifique |
| :--- | :--- | :--- |
| **Niveau moisson** | En 20 pas, éliminer **50 cinq-céréales/légumes-fruits**. | Poules, canards et oies nombreux sur le plateau, gênent l'élimination des plantes. |
| **Niveau expulsion** | En temps limité, éliminer **15 poules/canards** avec le **chien**. | Le plateau ne fait apparaître que volaille et chiens, pas de plantes. |
| **Niveau roi de la destruction** | Avec le **cochon**, faire tomber **3 grands arbres** (5 PV par arbre). | Le cochon apparaît rarement ; il faut aligner trois cochons pour déclencher l'effondrement de zone. |
| **Carnaval de l'éléphant** | Éliminer **30 pièces quelconques** avec l'éléphant. | Un éléphant offert au départ ; protégez-le, il ne doit pas être échangé. |

---

### VII. Points forts du plan

1. **Règles cohérentes** : la chaîne alimentaire biologique (la poule mange le ver, le chien mord la poule, l'éléphant est invincible) se convertit parfaitement en « conditions d'appariement » de match-3, plus pédagogique et stratégique qu'un simple « match-3 ».
2. **Rendu 3D** : grâce au moteur physique Three.js (ou à l'animation simulée), les pièces ont une « chute par gravité », des « rebonds élastiques », un relief bien supérieur aux sprites 2D.
3. **Équilibre garanti** : l'éléphant étant trop fort, il apparaît rarement (au plus 1 à la fois par partie), ne peut pas être généré par échange normal, seulement offert par le système après 5 combos, pour éviter le déséquilibre.

Ce plan conserve la « prise en main facile » du match-3 classique, intègre le noyau unique du « jeu de combat écologique », et reste parfaitement réalisable en Three.js (combinaison pure de Geometry + shaders de base). Vous pouvez lancer le développement sur cette base. Si vous voulez approfondir des détails (comme les courbes d'animation concrètes ou les palettes de particules), dites-le-moi à tout moment. 🐘🌾

