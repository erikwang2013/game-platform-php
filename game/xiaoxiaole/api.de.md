# 田园消消乐 — Plattform-Integrations-API
<!-- lang-nav -->

Languages: **中文** · [English](api.en.md) · [한국어](api.ko.md) · [Русский](api.ru.md) · [Deutsch](api.de.md) · [Français](api.fr.md) · [Español](api.es.md) · [Português](api.pt.md) · [हिन्दी](api.hi.md) · [العربية](api.ar.md) · [বাংলা](api.bn.md) · [Bahasa Indonesia](api.id.md) · [日本語](api.ja.md)


> Dieses Dokument ist der vollständige Schnittstellenvertrag zwischen dem Spiel 《田园消消乐》 (Landleben-Match-3) und der Spielplattform. Technische Schichtung siehe `architecture.md`, Zeitplan siehe `plan.md`, Spielerfunktionen siehe `functional-design.md`.

---

## 1. Startkette

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
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

Das Spiel ist ein **statisches Frontend**; die maßgebliche Session und das Geld liegen in `service/`. Der Client hält den Brettzustand; der Server hält das Guthaben und die round-Idempotenz. In Phase 1 gibt es keine serverseitige Prüfung jedes einzelnen Schritts, aber die Domänenschicht muss deterministisch sein, damit in Phase 2 `seed + Operationssequenz` zur Neuberechnung an den Server gesendet werden können.

---

## 2. Schnittstellenliste

| Schnittstelle | Methode | Richtung | Beschreibung |
|------|------|------|------|
| `/api/game/launch` | POST | Plattform → service | Spielsession starten, gibt `session_id, api_endpoint, type=self` zurück |
| `/api/provider/balance` | GET | Spiel → service | Spielwährungsguthaben abfragen |
| `/api/provider/bet` | POST | Spiel → service | Eintrittsgebühr für Rundenstart abbuchen |
| `/api/provider/settle` | POST | Spiel → service | Belohnung bei Abschluss auszahlen |
| `/api/provider/refund` | POST | Spiel → service | Rückerstattung beim Beenden ohne ersten Schritt |

Die Spielseite ruft `/api/provider/*` über den `PlatformAdapter` mit HMAC/JWT-Signatur auf.

---

## 3. Startablauf

1. Die Plattform `POST /api/game/launch` gibt `session_id, api_endpoint, type=self` zurück.
2. Öffnen von `api_endpoint?session_id=&token=` (token ist ein kurzlebiges Spielticket oder wiederverwendetes JWT).
3. Das Spiel zeigt mit `GET /api/provider/balance` die Spielwährung an.
4. Der Spieler klickt auf „Runde starten" → `POST /api/provider/bet`, `round_id = session_id + ':' + levelId + ':' + attempt`.
5. Domänenseed `seed = hash(session_id + round_id)`.
6. Bei Abschluss `settle`, bei Niederlage kein settle; bei Beenden ohne Operation `refund`.

---

## 4. Play-Log-Meldung

`launch` (bereits vorhanden) + spielseitige Meldung der folgenden Ereignisse (kann zunächst über ClickHouse `GamePlayLogService` protokolliert werden):

| Ereignis | Zeitpunkt |
|------|------|
| `level_start` | Level betreten |
| `level_win` | Level abgeschlossen |
| `level_fail` | Fehlgeschlagen |
| `skill_use` | Fähigkeit verwendet |

### `meta`-Feldvertrag (gemeinsam von `bet` / `settle` genutzt, Anti-Cheat H5)

Die Felddefinitionen von `meta` (Objekt) im Request-Body von `POST /api/provider/bet` und `POST /api/provider/settle`:

| Feld | Typ | Erforderlich | Beschreibung |
|------|------|------|------|
| `device_id` | string | Nein | Geräte-ID (auf dem Server im Klartext gespeichert, für gerätebezogene Aggregation) |
| `result` | string | Erforderlich bei settle | Rundergebnis: `win` / `fail` |
| `move_count` | int | Nein | Anzahl der Züge dieser Runde (Eingabe für die Zugfrequenz-Erkennung) |
| `ended_at` | string | Nein | Endzeit der Runde `YYYY-MM-DD HH:MM:SS` |
| `level_id` | int | Nein | Level-ID |
| `ip` | string | Nein | Quell-IP des Spielers (die Spielseite leitet die echte IP weiter; der Server speichert nur den sha256 als `ip_hash`, keinen Klartext) |
| `user_agent` | string | Nein | User-Agent des Spielers (der Server speichert nur den sha256 als `user_agent_hash`) |

Beim Speichern auf dem Server in `game_game_play_log`: `result / move_count / ended_at_round / device_id / level_id` kommen in eigene Spalten; `ip` / `user_agent` werden gehasht in `ip_hash` / `user_agent_hash` gespeichert; `meta` wird unverändert in `metadata` (JSON) abgelegt.

---

## 5. Feature-Schalter (FeatureFlag)

| Schalter | Standard | Beschreibung |
|------|------|------|
| `xxl.eco_chain` | on | Ökologische Kettmechanik |
| `xxl.elephant` | off | Elefantenregel |
| `xxl.skills` | on | Werkzeug-Fähigkeiten |
| `xxl.entry_bet` | off | Eintrittsgebühr/Wallet |

Bei Deaktivierung degenerieren die Level zu reinem Gleichart-Match-3, was die schrittweise Auslieferung erleichtert.

---

## 6. Wallet und round-Idempotenz

- `SelfProvider::bet/settle/refund` existiert bereits; das Spiel ruft sie mit `round_id` auf; pro round wird ein Auszahlungslimit gesetzt.
- Eine round wird nur einmal bet/settle ausgeführt; eine abgelaufene Session wird verworfen; abnormale Höchstwerte werden nur protokolliert und nicht automatisch ausgezahlt (settle-Limit kann gesetzt werden).
- Bei Niederlage wird die Eintrittsgebühr nicht erstattet; Beenden ohne einen einzigen Austausch → `refund`.

---

## 7. Phase 2: Serverseitige Neuberechnung

Die Operationssequenz wird hochgeladen; der Server führt dieselbe `domain`-Logik als PHP-Portierung oder Node-Worker neu aus (`seed + Operationssequenz` → Validierung von Brett und Punktzahl).
