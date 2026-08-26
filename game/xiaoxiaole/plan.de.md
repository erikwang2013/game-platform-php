# 田园消消乐 — Entwicklungsplanung
<!-- lang-nav -->

Languages: **中文** · [English](plan.en.md) · [한국어](plan.ko.md) · [Русский](plan.ru.md) · [Deutsch](plan.de.md) · [Français](plan.fr.md) · [Español](plan.es.md) · [Português](plan.pt.md) · [हिन्दी](plan.hi.md) · [العربية](plan.ar.md) · [বাংলা](plan.bn.md) · [Bahasa Indonesia](plan.id.md) · [日本語](plan.ja.md)


> Die Vision (`design.md`) in planbare Aufgaben verwandeln. Für Funktionsdetails gilt `functional-design.md`, für technische Einschränkungen `architecture.md`.

---

## 1. Wie die drei Dokumente zu verwenden sind

| Dokument | Beantwortete Frage | Beantwortet nicht |
|------|------------|--------|
| `design.md` | Landleben-Thema, Kettfantasie, 3D-Charakter | Wie viele Arten pro Level spawnen, Abnahmeklauseln |
| `functional-design.md` | Worauf der Spieler klickt, wie man gewinnt, wer in V1 auftritt | Wie das Verzeichnis aufgeteilt ist, ob eine Physikengine eingesetzt wird |
| `architecture.md` | Schichtung, Module, Plattform-Wallet, deterministischer RNG | 90 Sekunden oder 20 Schritte (bereits im Funktionsdesign entschieden) |

Die Entwicklung richtet sich nur nach den letzten beiden Dokumenten; bei Konflikten zwischen Vision und diesen beiden gelten die letzten beiden (bereits entschiedene Ausnahmen stehen in Abschnitt 12 des Funktionsdesigns).

---

## 2. V1-Umfang

**Fertig = veröffentlicht:** Die vier Level durchspielbar, drei Eliminierungsarten, Fähigkeiten und Zerstörer-Hindernisse, H5 aus der Lobby öffnbar. Wallet abschaltbar (Feature-Schalter `xxl.entry_bet`).

**Eindeutig gestrichen oder verschoben:** 100 Arten gleichzeitig auf dem Brett, Werkzeuge als Figuren, Physikengine, GLTF, Zuschauen, Rangliste im Spiel, Wasserpfützen-Hauptlevel, Unterdrücker bleibt nach dem Fressen stehen, serverseitige Prüfung jedes Schritts.

---

## 3. Meilensteine

| Meilenstein | Zieldatum (relativ zum Start) | Spielbares Ergebnis | Was entstanden ist |
|--------|----------------------|----------|----------|
| M0 Gerüst | Woche 1 | Lokal leeres Sandmodell öffnen | Vite, Three-Orthografieszene, 8×8-Felder |
| M1 Eliminieren geht | Woche 2 | Drei Gleiche eliminieren und fallen | F01–F03, domain-Unit-Tests |
| M2 Level vorhanden | Woche 3 | Erntelevel gewinn-/verlierbar | F04 F05 F15 F16 |
| M3 Ökologie | Woche 4 | Huhn frisst Käfer, Vertreiblevel | F06 F07 F08 |
| M4 Werkzeuge | Woche 5 | Zerstörerlevel baut Bäume ab | F09 F10 F11 |
| M5 Anbindung | Woche 6 | Aus der Lobby betretbar, Elefantenlevel, optionale Gebühr | F12 F13 F14 |
| M6 Politur | Woche 7 | Partikel/Sound/Gerätemodus | F17 |

Eine Woche entspricht einer Person in Vollzeit. Parallelisierung (Domäne + Rendering) kann auf etwa 5 Wochen drücken.

---

## 4. Phasen und Abhängigkeiten

```
P0 同种三消 ─────────┐
P1 选关与丰收 ───────┼─ P2 生态与驱赶 ─ P3 障碍农具 ─ P4 象+钱包 ─ P5 抛光
渲染沙盘（可与 P0 并行）┘
```

- P0 hängt nicht von PHP ab. Mit `?debug=1` lokal spielbar.
- P1 hängt nicht vom Wallet ab.
- P2 baut auf der Erweiterung des P0-Matching-Scans auf, ändert die Bedienung nicht.
- P3 hängt vom Feld-Overlay ab.
- P4 hängt von den bereits vorhandenen `POST /api/game/launch` und `SelfProvider` der Plattform ab; spielseitig kommen ticket, bet, settle hinzu.
- P5 hat keine Funktionsabhängigkeiten, der Gerätemodus-Schalter kann jederzeit eingefügt werden.

---

## 5. Arbeitspakete (nach Person)

**A Domäne (ohne Oberfläche)**  
Tafel-JSON → Brett-Snapshot → Matching (Gleichart/Ökologie/Elefant) → Schwerkraft → Level-Sieg/Niederlage → Punkte. Vitest vor der Grafik.

**B Darstellung**  
Szene, Kamera, aus 10 Vorlagen zunächst 3 umsetzen (Ähre/Obst/Huhn), Raycaster, Tausch- und Eliminierungs-Easing. HUD mit DOM.

**C Levelinhalt**  
Vier Level-JSONs: Spawn-Pool, Ziel, Schritte/Zeitlimit, Fähigkeits-Whitelist, Start-Hindernisse.

**D Plattform**  
launch-URL-Parameter, Guthabenanzeige, bet/settle, Rückerstattungsstrategie bei Niederlage, play-log-Ereignisse.

Empfohlene Reihenfolge: P0-Tests von A rot-grün → B übernimmt Snapshot → C Erntelevel → A Ökologie-Tests → C die übrigen drei Level → D.

---

## 6. Was plattformseitig geändert werden muss (erst in P4)

Schnittstellenvertrag siehe **[api.md](api.md)**. Plattformseitige Änderungspunkte:

| Punkt | Ist-Zustand | Geplante Aktion |
|----|------|----------|
| Spielprotokoll | `GameController::launch` schreibt bereits die Session | Im Backend einen Datensatz mit type=self und api_endpoint auf dieses H5 anlegen |
| Wallet | `SelfProvider::bet/settle` vorhanden | Spiel ruft nach round_id auf; Auszahlungsobergrenze pro round setzen |
| Feature-Schalter | `FeatureFlag` vorhanden | `xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet` |
| Statisches Hosting | Nginx verteilt bereits | `/games/xiaoxiaole/` auf die Build-Artefakte zeigen lassen |
| Lobby-Öffnung | Flutter `launchUrl` | endpoint um `session_id` ergänzen |

P0–P3 **erfordern keine PHP-Änderungen**.

---

## 7. Risiken

| Risiko | Auswirkung | Gegenmaßnahme |
|------|------|------|
| Ökologieregel für Spieler unverständlich | Vertreiblevel nicht schaffbar | Tutorial-Hinweis 3; Eliminierungsvorschau auf P5 verschieben |
| Spawnarten weiterhin zu viele | Keine eliminierbaren Figuren | Hartes Limit von 8 Arten pro Level |
| Elefant zu stark | Party sofort leer | Ziel zählt nur Elefantenregel; 1 Stück auf dem Brett gesperrt |
| Client manipuliert Punktzahl für Belohnung | Wallet | P4-Auszahlungsobergrenze; Replay-Prüfung nachgelagert |
| Schwache Geräte droppen Frames | Erlebnis | dpr-Obergrenze 2; Partikel abschaltbar |

---

## 8. Bereits entschieden (nicht mehr fragen)

- Nach der Ökologie verlässt der Unterdrücker **zusammen mit der Beute** das Brett.
- Vertreiblevel **90 Sekunden Zeitlimit**, keine Schritte.
- Wasserpfützen kommen nicht in die vier Hauptlevel.
- V1 spawnt nur die Tabelle aus Abschnitt 7 des Funktionsdesigns; alle übrigen Arten nur in der Tafeldatei.

Wer diese vier Punkte ändern will, ändert zuerst `functional-design.md` und dann den Code.

---

## 9. Nächste Schritte (wartet auf dein Go)

1. Nach P0 eine Aufgabenliste für die Implementierung schreiben (auf Dateiebene, testfirst), oder  
2. direkt das Vite- + `domain`-Gerüst + leere Szene aufsetzen.

Konkrete Funktionsimplementierungen stehen nicht in dieser Planung.
