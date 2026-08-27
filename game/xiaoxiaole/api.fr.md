# 田园消消乐 — API d'intégration à la plateforme
<!-- lang-nav -->

Languages: [中文](api.md) · [English](api.en.md) · [한국어](api.ko.md) · [Русский](api.ru.md) · [Deutsch](api.de.md) · **Français** · [Español](api.es.md) · [Português](api.pt.md) · [हिन्दी](api.hi.md) · [العربية](api.ar.md) · [বাংলা](api.bn.md) · [Bahasa Indonesia](api.id.md) · [日本語](api.ja.md)


> Ce document est le contrat d'interface complet entre 《田园消消乐》 et la plateforme de jeux. La stratification technique est dans `architecture.fr.md`, le calendrier dans `plan.fr.md`, les fonctionnalités joueurs dans `functional-design.fr.md`.

---

## 1. Chaîne de lancement

```
Flutter / HarmonyOS / PC Web
        │  POST /api/game/launch { game_id }
        ▼
service/ (webman :8788)
  GameController::launch  → session_id + seed + api_endpoint
  SelfProvider            → bet / settle / refund / getBalance
  GamePlayLog + EventBus  → game.played / 成就 / VIP
        │  ouvre api_endpoint?session_id=&token=
        ▼
game/xiaoxiaole/  (ressources statiques, Nginx)
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

Le jeu est un **frontend statique** ; la session faisant autorité et l'argent vivent dans `service/`. Le client détient l'état du plateau ; le serveur détient le solde et l'idempotence du round. La première phase ne fait pas de validation serveur à chaque étape, mais la couche domaine doit être déterministe pour qu'en phase 2, `seed + séquence d'opérations` puisse être envoyé au serveur et recalculé.

---

## 2. Liste des interfaces

| Interface | Méthode | Sens | Description |
|------|------|------|------|
| `/api/game/launch` | POST | plateforme → service | Lance une session de jeu, renvoie `session_id, api_endpoint, type=self` |
| `/api/provider/balance` | GET | jeu → service | Interroge le solde de devises de jeu |
| `/api/provider/bet` | POST | jeu → service | Débit du droit d'entrée au début d'un niveau |
| `/api/provider/settle` | POST | jeu → service | Règlement de la récompense à la victoire |
| `/api/provider/refund` | POST | jeu → service | Remboursement si sortie sans avoir joué le premier pas |

Le jeu appelle `/api/provider/*` via `PlatformAdapter`, signé HMAC/JWT.

---

## 3. Flux de lancement

1. La plateforme `POST /api/game/launch` renvoie `session_id, api_endpoint, type=self`.
2. Ouvrir `api_endpoint?session_id=&token=` (token = ticket de jeu court, ou réutilisation du JWT).
3. Le jeu `GET /api/provider/balance` affiche les devises de jeu.
4. Le joueur clique « Commencer le niveau » → `POST /api/provider/bet`, `round_id = session_id + ':' + levelId + ':' + attempt`.
5. Le domaine calcule `seed = hash(session_id + round_id)`.
6. Victoire → `settle` ; défaite → pas de settle ; sortie sans action → `refund`.

---

## 4. Remontée des journaux de jeu (play-log)

`launch` (déjà existant) + remontée par le jeu des événements suivants (peuvent d'abord alimenter ClickHouse `GamePlayLogService`) :

| Événement | Moment |
|------|------|
| `level_start` | Entrée dans le niveau |
| `level_win` | Victoire du niveau |
| `level_fail` | Défaite |
| `skill_use` | Utilisation d'une compétence |

---

## 5. Interrupteurs de fonctionnalités (FeatureFlag)

| Interrupteur | Défaut | Description |
|------|------|------|
| `xxl.eco_chain` | on | Chaîne de contrainte écologique |
| `xxl.elephant` | off | Règle de l'éléphant |
| `xxl.skills` | on | Compétences d'outils agricoles |
| `xxl.entry_bet` | off | Droit d'entrée / portefeuille |

Lorsqu'ils sont désactivés, les niveaux dégénèrent en pur match-3 classique, ce qui permet un déploiement progressif.

---

## 6. Portefeuille et idempotence du round

- `SelfProvider::bet/settle/refund` existe déjà ; le jeu l'appelle par `round_id` ; plafonner la récompense par round.
- Un round ne fait qu'un seul bet/settle ; session expirée → invalidée ; score anormalement élevé seulement journalisé, pas de récompense automatique (plafond de settle configurable).
- Défaite → pas de remboursement du droit d'entrée ; sortie sans avoir bougé une seule pièce → `refund`.

---

## 7. Phase 2 : recalcul côté serveur

Téléversement de la séquence d'opérations ; le serveur exécute un portage PHP du même `domain` ou un worker Node pour recalculer (`seed + séquence d'opérations` → validation du plateau et du score).
