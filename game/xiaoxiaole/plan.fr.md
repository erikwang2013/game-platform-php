# 田园消消乐 — Plan de développement
<!-- lang-nav -->

Languages: [中文](plan.md) · [English](plan.en.md) · [한국어](plan.ko.md) · [Русский](plan.ru.md) · [Deutsch](plan.de.md) · **Français** · [Español](plan.es.md) · [Português](plan.pt.md) · [हिन्दी](plan.hi.md) · [العربية](plan.ar.md) · [বাংলা](plan.bn.md) · [Bahasa Indonesia](plan.id.md) · [日本語](plan.ja.md)


> Transformer la vision (`design.fr.md`) en tâches planifiables. Les détails fonctionnels font foi dans `functional-design.fr.md`, les contraintes techniques dans `architecture.fr.md`.

---

## 1. Comment utiliser les trois documents

| Document | Question à laquelle il répond | Ce qu'il ne répond pas |
|------|------------|--------|
| `design.fr.md` | Thème champêtre, fantasy de contrainte, allure 3D | Combien d'espèces par niveau, clauses de recette |
| `functional-design.fr.md` | Sur quoi le joueur clique, comment on gagne, qui apparaît en V1 | Comment découper les répertoires, moteur physique ou non |
| `architecture.fr.md` | Couches, modules, portefeuille de plateforme, RNG déterministe | 90 secondes ou 20 pas (tranché dans le fonctionnel) |

Le développement ne reconnaît que les deux derniers ; en cas de conflit entre la vision et les deux derniers, les deux derniers font foi (les exceptions déjà tranchées sont dans la section 12 du design fonctionnel).

---

## 2. Périmètre V1

**Fait = en ligne :** quatre niveaux jouables, trois types d'élimination, compétences et obstacle « roi de la destruction », l'H5 s'ouvre depuis le hall. Portefeuille désactivable (interrupteur de fonctionnalité `xxl.entry_bet`).

**Explicitement coupé ou repoussé :** 100 espèces simultanées sur le plateau, outils agricoles comme pièces, moteur physique, GLTF, spectateurs, classement en partie, niveau principal à flaques, prédateur restant sur le plateau après consommation, validation serveur à chaque pas.

---

## 3. Jalons

| Jalon | Date cible (depuis le début) | Résultat jouable | Ce qui sort |
|--------|----------------------|----------|----------|
| M0 Squelette | Semaine 1 | Ouverture locale d'un bac à sable vide | Vite, scène orthographique Three, terrain 8×8 |
| M1 Ça s'élimine | Semaine 2 | Trois identiques s'éliminent et tombent | F01–F03, tests unitaires du domaine |
| M2 Des niveaux | Semaine 3 | Le niveau moisson se gagne et se perd | F04 F05 F15 F16 |
| M3 Écologie | Semaine 4 | La poule mange le ver, niveau expulsion | F06 F07 F08 |
| M4 Outils | Semaine 5 | Le roi de la destruction démonte les arbres | F09 F10 F11 |
| M5 Intégration | Semaine 6 | Entrée depuis le hall, niveau éléphant, débit optionnel | F12 F13 F14 |
| M6 Polissage | Semaine 7 | Particules/effets sonores/tier bas de gamme | F17 |

Une semaine est estimée pour une personne à temps plein. Le parallélisme (domaine + rendu) peut réduire à environ 5 semaines.

---

## 4. Phases et dépendances

```
P0 match-3 identique ─────────┐
P1 sélection et moisson ──────┼─ P2 écologie et expulsion ─ P3 obstacles/outils ─ P4 éléphant+portefeuille ─ P5 polissage
rendu du bac à sable (parallèle à P0)┘
```

- P0 ne dépend pas de PHP. `?debug=1` pour jouer en local.
- P1 ne dépend pas du portefeuille.
- P2 dépend de l'extension de l'analyse de correspondance de P0, sans changer le mode d'opération.
- P3 dépend de l'overlay des cases.
- P4 dépend du `POST /api/game/launch` et de `SelfProvider` déjà présents sur la plateforme ; ajout de ticket, bet, settle côté jeu.
- P5 sans dépendance fonctionnelle, interrupteur bas de gamme insérable à tout moment.

---

## 5. Lots de travail (par personne)

**A Domaine (sans interface)**
JSON du catalogue → instantané du plateau → correspondance (identique/écologique/éléphant) → gravité → victoire/défaite de niveau → score. Vitest avant l'écran.

**B Rendu**
Scène, caméra, 3 gabarits sur les 10 (épi/fruit/poule), Raycaster, échanges et éliminations en easing. HUD en DOM.

**C Contenu des niveaux**
JSON des quatre niveaux : pools de rafraîchissement, objectif, pas/temps, liste blanche de compétences, obstacles de départ.

**D Plateforme**
Paramètres d'URL de launch, affichage du solde, bet/settle, stratégie de remboursement en cas d'échec, événements play-log.

Ordre recommandé : tests rouges/verts de P0 de A → B branche l'instantané → C moisson → tests écologie de A → C les trois autres niveaux → D.

---

## 6. Ce qu'il faut toucher côté plateforme (fait en P4 uniquement)

Le contrat d'interface est dans **[api.fr.md](api.fr.md)**. Points de modification côté plateforme :

| Élément | État actuel | Action prévue |
|----|------|----------|
| Journal des jeux | `GameController::launch` écrit déjà la session | Ajouter au backend une entrée type=self, api_endpoint pointant vers cette H5 |
| Portefeuille | `SelfProvider::bet/settle` existe déjà | Le jeu appelle par round_id ; plafonner la récompense par round |
| Interrupteurs de fonctionnalités | `FeatureFlag` existe déjà | `xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet` |
| Hébergement statique | Nginx distribue déjà | `/games/xiaoxiaole/` pointe vers l'artefact de build |
| Ouverture depuis le hall | Flutter `launchUrl` | endpoint concaténé avec `session_id` |

P0–P3 **ne nécessitent aucune modification PHP**.

---

## 7. Risques

| Risque | Impact | Contre-mesure |
|------|------|------|
| Les règles écologiques incompréhensibles | Le niveau expulsion est infranchissable | Troisième didacticiel ; aperçu des éliminables repoussé en P5 |
| Trop d'espèces de rafraîchissement | Aucune élimination possible | Plafond dur de 8 espèces par niveau |
| Éléphant trop fort | Carnaval vidé d'un coup | Objectif compté uniquement par la règle de l'éléphant ; verrouillé à 1 sur le plateau |
| Le client modifie le score pour voler la récompense | Portefeuille | Plafond de récompense en P4 ; vérification par enregistrement différée |
| Chute de fps sur machines faibles | Expérience | dpr max 2 ; particules désactivables |

---

## 8. Tranché (on ne redemande plus)

- Après l'élimination écologique, le prédateur **quitte aussi le plateau**.
- Le niveau expulsion est **limité à 90 secondes**, sans pas.
- Les flaques n'entrent pas dans la ligne principale des quatre niveaux.
- V1 ne fait apparaître que le tableau de la section 7 du design fonctionnel ; les autres espèces n'entrent que dans le fichier du catalogue.

Pour modifier ces quatre points, modifiez d'abord `functional-design.fr.md`, puis le code.

---

## 9. Prochaines étapes (en attendant votre feu vert)

1. Écrire la liste de tâches d'implémentation selon P0 (au niveau fichier, tests d'abord), ou
2. Monter directement Vite + squelette `domain` + scène vide.

Ce plan n'écrit pas d'implémentations de fonctions concrètes.
