# 田园消消乐 — Design fonctionnel
<!-- lang-nav -->

Languages: [中文](functional-design.md) · [English](functional-design.en.md) · [한국어](functional-design.ko.md) · [Русский](functional-design.ru.md) · [Deutsch](functional-design.de.md) · **Français** · [Español](functional-design.es.md) · [Português](functional-design.pt.md) · [हिन्दी](functional-design.hi.md) · [العربية](functional-design.ar.md) · [বাংলা](functional-design.bn.md) · [Bahasa Indonesia](functional-design.id.md) · [日本語](functional-design.ja.md)


> Spécifications que le joueur peut voir, manipuler et accepter. La stratification technique est dans `architecture.fr.md` ; la vision des éléments dans `design.fr.md` ; le calendrier dans `plan.fr.md`.
>
> En une phrase : échanger des pièces adjacentes sur un bac à sable champêtre en 3D, vider le plateau avec « trois identiques » ou « le prédateur mange la proie », et atteindre les objectifs de niveau.

---

## 1. Définition du produit

| Élément | Contenu |
|----|------|
| Nom | 田园消消乐 |
| Type | Match-3 8×8 + contrainte écologique |
| Vue | Bac à sable orthographique 2.5D fixe, non rotatif |
| Opération | Clic sur deux pièces adjacentes pour échanger (haut/bas/gauche/droite uniquement) |
| Forme de plateforme | H5 propriétaire, ouvert depuis le hall de jeux via `launch` |
| Expérience de réussite | Le match-3 s'apprend au premier essai ; première « poule mange le ver » : la règle monte en niveau ; les chaînes de chutes ont du rythme |

**V1 ne fait pas :** classement temps réel en partie, spectateurs amis, évolution des pièces, monde ouvert, modèles GLTF fins, niveaux personnalisés par le joueur.

---

## 2. Flux du joueur

```
« Commencer » dans le hall
  → page de chargement (lecture de la session)
  → sélection de niveau (liste des quatre niveaux + solde, le débit n'apparaît qu'en P4)
  → partie
       HUD : objectif / pas ou compte à rebours / score / combo / barre de compétences
       Plateau : clic pour sélectionner → clic sur une case adjacente pour échanger
       Sans élimination : rebond, pas de pas consommé
       Avec élimination : 1 pas consommé → animation d'élimination → chute → remplissage → chaîne automatique
  → règlement victoire / défaite
  → niveau suivant / nouvel essai / retour à la sélection
```

À la première entrée dans le « niveau moisson », 3 astuces s'affichent puis disparaissent, skippables, ne réapparaissent plus (localStorage).

---

## 3. Boucle centrale

1. Regarder l'objectif (combien de plantes / volailles / arbres / cases piétinées par l'éléphant manquent).
2. Trouver un trio identique, ou déplacer le prédateur à côté de deux proies.
3. Échanger → éliminer → chaîne de chutes.
4. L'élimination écologique laisse de l'engrais sur la case d'origine ; la prochaine élimination de cette case marque ×2.
5. Faire 3 règlements avec écologie d'affilée : la barre de compétences s'allume, utiliser la faucille/la houe/le seau pour débloquer.
6. Objectif atteint et pas/temps restants → victoire.

---

## 4. Interfaces

| Interface | Éléments | Comportement |
|------|------|------|
| Chargement | Nom du jeu, progression | Session invalide → invitation à revenir au hall |
| Sélection de niveau | Quatre cartes de niveau : nom, résumé d'objectif, déblocage | V1 : les quatre niveaux ouverts ; P4 : affichage du droit d'entrée |
| HUD haut de partie | Nom du niveau, barre de progression d'objectif, pas restants ou compte à rebours, score, combo | Le compte à rebours défile, figé en pause |
| HUD bas de partie | Barre de compétences (2 max), pause | Cases grises tant que non chargées |
| Plateau | Cases 8×8 + pièces | Sélection : rebond + contour ; case illégale : pas de contour |
| Pause | Continuer / recommencer / abandonner | Recommencer consomme une tentative ; abandonner = règlement en défaite |
| Victoire | Score, pas restants, récompense ou non (P4) | Niveau suivant / retour à la sélection |
| Défaite | Raison (pas/dépassement), écart sur l'objectif | Nouvel essai / retour à la sélection |
| Solde insuffisant | Texte + aller recharger | P4 uniquement |

Clavier (P5) : les flèches déplacent la sélection, Entrée échange avec la case dans la direction. V1 : souris/tactile uniquement.

---

## 5. Règles d'opération (vue joueur)

- Seules les pièces **orthogonalement adjacentes** et mobiles des deux côtés peuvent être échangées.
- Les cases pierre, arbre et flaque ne peuvent pas être objets d'échange. L'éléphant verrouillé ne peut pas être échangé (les proies viennent à lui).
- Pas de « trio légal » en horizontal ou vertical après l'échange → retour en arrière, **ni pas ni temps consommés**.
- Trio légal → 1 pas consommé (les niveaux chronométrés ne consomment pas de pas, seul le chrono tourne).
- La prochaine action n'est acceptée qu'après la fin de toutes les chaînes ; cliquer le plateau pendant une chaîne est sans effet.
- Les trios en diagonale ne comptent pas. Les croisements L / T ne font qu'une élimination par case.

---

## 6. Les trois types d'élimination (fonctionnalités)

Priorité : **éléphant > écologique > identique**. Une ligne ne compte qu'une fois le score, selon la priorité la plus haute.

### 6.1 Identique

Trois pièces ou plus **de la même espèce** alignées. Ex. : pomme-pomme-pomme.

| Longueur | Résultat visible par le joueur |
|------|----------------|
| 3 | Rétrécissement et disparition, score de base |
| 4 | Disparition, engrais sur la case centrale |
| 5+ | Disparition, barre de compétences +1 de charge (limité aux compétences autorisées par le niveau) |

### 6.2 Écologique (prédation)

Dans une ligne, **exactement 1 prédateur**, les autres étant ses proies, sans obligation de même espèce. Ex. : poule-fourmi-coccinelle.

| Prédateur | Peut manger | Ne peut pas manger |
|--------|------|--------|
| Poule, canard, oie | Fleurs, légumes, fruits, insectes | Les cinq céréales |
| Chien | Poule, canard, oie, pigeon et autres volailles | Plantes, insectes |
| Cochon | Arbres, fleurs, légumes, fruits, insectes, cinq céréales | Chien |
| Bœuf, cheval | Fleurs, cinq céréales, jeunes arbres | Insectes, viande |
| Éléphant | Voir 6.3 | Obstacles, outils agricoles |

Le joueur voit : animation de prédation → les trois cases se vident (V1 : le prédateur quitte aussi le plateau) → engrais sur la case d'origine du prédateur.

### 6.3 Éléphant

Une ligne contenant 1 éléphant + deux autres pièces éliminables quelconques → les trois cases se vident, sans tenir compte des familles. Au plus 1 éléphant sur le plateau. Ne se « compose » jamais par échange normal ; au combo 5, le système le fait tomber dans une case supérieure vide, ou il est placé au début du niveau.

---

## 7. Catalogue V1 (pas les 100 espèces de la planification)

Toutes les espèces planifiées restent dans les données du catalogue, mais **V1 ne fait apparaître que celles-ci** en partie, pour garantir la lisibilité et la complétion.

| Espèce | Famille | Niveaux d'apparition | Reconnaissance joueur |
|------|------|----------|----------|
| Blé wheat | Cinq céréales | Moisson, roi de la destruction, carnaval | Épi doré |
| Riz rice | Cinq céréales | Moisson | Épi vert |
| Maïs corn | Cinq céréales | Moisson | Épi jaune |
| Chou cabbage | Légumes | Moisson | Boule de feuilles vert clair |
| Tomate tomato | Légumes | Moisson | Boule rouge |
| Pomme apple | Fruits | Moisson, roi de la destruction, carnaval | Boule rouge + tige |
| Rose rose | Fleurs et herbes | Roi de la destruction | Pétales rouges |
| Fourmi ant | Insectes | Moisson (poids faible) | Petite noire |
| Coccinelle ladybug | Insectes | Moisson | Points rouges |
| Poule hen | Volailles | Moisson, expulsion, carnaval | Ovale + bec |
| Canard duck | Volailles | Moisson, expulsion | Bec plat |
| Oie goose | Volailles | Expulsion | Long cou |
| Pigeon pigeon | Volailles | Expulsion | Gris |
| Chien dog | Bétail | Expulsion, carnaval | Quadrupède |
| Cochon pig | Bétail | Roi de la destruction, carnaval | Ovale rose |
| Pin pine | Arbres/obstacles | Roi de la destruction | Houppier conique, non échangeable |
| Éléphant elephant | Top | Carnaval ; récompense combo 5 dans les autres niveaux | Grand cube + trompe |

Les outils agricoles (faucille, houe, seau) **ne montent pas sur le plateau**, uniquement dans le HUD. Les autres outils planifiés ne sortent pas en V1.

---

## 8. Spécifications des niveaux

La victoire/défaite est réglée **après la fin de toute la séquence de chaînes**.

### 8.1 Niveau moisson

- Pool : blé, riz, maïs, chou, tomate, pomme, poule, canard ; fourmi/coccinelle à poids faible.
- Victoire : éliminer **50** personnages végétaux (cinq céréales + légumes + fruits + fleurs) en 20 pas. Les poules/canards éliminés ne comptent pas.
- Défaite : pas à 0 et objectif non atteint.
- Compétence : faucille (utilisable une fois chargée).
- Didacticiel : ① cliquer deux pièces adjacentes pour échanger ② trois identiques s'éliminent ③ la poule peut manger deux insectes/légumes/fruits à côté, mais pas le blé.

### 8.2 Niveau expulsion

- Pool : poule, canard, oie, pigeon, chien. Pas de plantes.
- Victoire : éliminer 15 volailles en **90 secondes** avec l'**élimination écologique du chien**.
- Défaite : dépassement du temps.
- **Un trio identique de poules ne compte pas dans l'objectif** (l'écologie chien-mange-volaille est obligatoire).
- Compétence : aucune. La pause fige le chrono.

### 8.3 Niveau roi de la destruction

- Pool : blé, pomme, rose, cochon (poids faible). 3 pins fixes, HP=5, non échangeables, non traversables par les chutes.
- Victoire : les HP des 3 arbres à zéro.
- Défaite : 25 pas épuisés.
- Dégâts aux arbres : écologie du cochon (l'arbre dans la run de proies) -2 ; trois cochons alignés déclenchent la **fouille 3×3** (arbre dans la zone : -5) ; houe sur un arbre : -3 ; élimination identique adjacente normale : -1.
- Compétence : houe.

### 8.4 Carnaval de l'éléphant

- Pool : blé, pomme, poule, chien, cochon. 1 éléphant verrouillé près du centre au début.
- Victoire : éliminer 30 cases via la **règle de l'éléphant** (identique/écologique ne comptent pas dans cet objectif).
- Défaite : 30 pas épuisés.
- Pas de deuxième éléphant. Le joueur déplace les proies à côté ou au-dessus/en dessous de l'éléphant.
- Compétence : aucune.

---

## 9. Obstacles, engrais, compétences

| Fonction | Perception joueur | Règle |
|------|----------|------|
| Pierre | Grise, non cliquable | HP3 ; élimination adjacente : -1 ; la houe la casse d'un coup |
| Arbre | Grand modèle, non cliquable | Voir roi de la destruction |
| Flaque | Case réfléchissante | Les pièces s'arrêtent sur la case au-dessus de la flaque ; après assèchement au seau, la chute reprend |
| Engrais | Tache sombre sur la case | La prochaine élimination de cette case marque ×2, puis disparaît |
| Faucille | Icône en barre basse | Choisir une ligne ou une colonne, ne nettoie que les plantes, ne consomme pas de pas, consomme 1 charge |
| Houe | Icône en barre basse | Clic sur 1 pierre ou 1 arbre |
| Seau | Icône en barre basse | Clic sur 1 case flaque |

Charge : dans tout règlement déclenché par une action du joueur, si une élimination écologique apparaît, compteur +1 ; à 3, obtention d'1 case, maximum 2. Un alignement identique de 5 donne aussi +1 case (case partagée avec la charge écologique).

V1 : le niveau moisson n'a ni pierre ni flaque ; le roi de la destruction n'a pas de flaque. Les flaques restent dans le catalogue, ne bloquent pas la ligne principale des quatre niveaux.

---

## 10. Score et économie

```
Identique    10 × nombre éliminé × combo × engrais
Écologique   25 × nombre éliminé × combo × engrais
Éléphant     40 × nombre éliminé × combo
Cases nettoyées par compétence  8 × nombre éliminé
Obstacle brisé   20 × nombre brisé
```

combo : la première élimination de l'action vaut 1, +1 à chaque chaîne supplémentaire ; la prochaine action manuelle du joueur remet à 1.

**P4 Portefeuille :**

- Le démarrage d'un niveau débite le droit d'entrée (défaut : 1 devise de jeu par niveau).
- Victoire réglée par étoiles : ressources restantes ≥50 % trois étoiles, ≥20 % deux étoiles, sinon une étoile ; récompenses 2 / 3 / 5 (configurables).
- Défaite : pas de remboursement du droit d'entrée.
- Sortie sans avoir bougé une seule pièce → remboursement.
- Solde insuffisant : pas de début de partie.

V1 (P0–P3) : sans débit, jouable directement en local.

---

## 11. Liste des fonctionnalités et critères d'acceptation

| ID | Fonctionnalité | Acceptation | Phase |
|----|------|------|------|
| F01 | Échange au clic 8×8 | Adjacent échangeable, diagonale impossible, sans trio : rebond | P0 |
| F02 | Match-3 identique + gravité + remplissage | Trois blés éliminés, chute d'en haut, nouvelles pièces en haut | P0 |
| F03 | Chaîne | Ré-élimination automatique après la chute, combo +1 | P0 |
| F04 | Sélection des quatre niveaux | Clic pour entrer dans le HUD d'objectif correspondant | P1 |
| F05 | Objectif moisson | 50 plantes en 20 pas, le comptage ne contient que les plantes | P1 |
| F06 | Élimination écologique | Poule + deux insectes éliminés ; poule + deux blés non éliminés | P2 |
| F07 | Engrais | Après l'écologie, la case éliminée marque le double une fois | P2 |
| F08 | Objectif expulsion | Poules identiques non comptées ; chien mange poule compté ; 90 s | P2 |
| F09 | Arbre et houe | Arbre non échangeable ; houe/cochon peuvent le démonter | P3 |
| F10 | Trois cochons 3×3 | Trois cochons alignés, arbres de la zone brisés | P3 |
| F11 | Faucille | Nettoie une ligne de plantes, ne consomme pas de pas | P3 |
| F12 | Éléphant verrouillé | L'éléphant ne peut pas être échangé ; éléphant + deux pièces vide trois cases | P4 |
| F13 | Objectif carnaval | Seule la règle de l'éléphant compte vers 30 | P4 |
| F14 | Droit d'entrée/récompense | Solde vérifié, pas de double règlement | P4 |
| F15 | Didacticiel | Trois phrases d'astuce, skip permanent | P1 |
| F16 | Pause/recommencer/abandonner | Chrono figé ; abandon = défaite | P1 |
| F17 | Particules bas de gamme | Après activation, fréquence stable et jouable | P5 |

---

## 12. Limites (à graver dans le marbre)

1. Le catalogue peut être grand, **espèces de rafraîchissement par niveau ≤ 8**.
2. Les outils agricoles ne montent pas sur le plateau.
3. La poule ne mange pas les cinq céréales : une ligne « poule+blé+blé » n'est ni écologique ni identique, rebond.
4. Le chien ne mange pas les plantes ; le cochon ne fouille pas le chien.
5. Au plus 1 éléphant à la fois sur le plateau.
6. Les entrées sont ignorées pendant la lecture des chaînes.
7. La victoire/défaite n'est pas jugée au milieu de l'animation.
8. V1 : le prédateur quitte le plateau avec les proies.
9. Le niveau expulsion est limité à 90 secondes, sans pas.
10. Les flaques n'entrent pas dans la ligne principale des quatre niveaux.
