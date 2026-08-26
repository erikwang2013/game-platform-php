# 田园消消乐 — Funktionsdesign
<!-- lang-nav -->

Languages: **中文** · [English](functional-design.en.md) · [한국어](functional-design.ko.md) · [Русский](functional-design.ru.md) · [Deutsch](functional-design.de.md) · [Français](functional-design.fr.md) · [Español](functional-design.es.md) · [Português](functional-design.pt.md) · [हिन्दी](functional-design.hi.md) · [العربية](functional-design.ar.md) · [বাংলা](functional-design.bn.md) · [Bahasa Indonesia](functional-design.id.md) · [日本語](functional-design.ja.md)


> Die Spezifikation dessen, was der Spieler sehen, bedienen und abnehmen kann. Technische Schichtung siehe `architecture.md`; Elementvision siehe `design.md`; Zeitplan siehe `plan.md`.
>
> In einem Satz: Auf einem dreidimensionalen Feld-Sandmodell benachbarte Figuren tauschen, mit „drei Gleichen" oder „Raubtier frisst Beute" das Brett räumen und das Levelziel erreichen.

---

## 1. Produktdefinition

| Punkt | Inhalt |
|----|------|
| Name | 田园消消乐 (Landleben-Match-3) |
| Typ | 8×8 Match-3 + ökologische Kette |
| Perspektive | Feste 2.5D-Orthografie-Sandmodell, nicht drehbar |
| Bedienung | Zwei benachbarte Figuren per Klick tauschen (nur oben/unten/links/rechts) |
| Plattformform | Eigenentwickeltes H5, wird aus der Spielelobby per `launch` geöffnet |
| Erfolgserlebnis | Match-3 sofort verstanden; beim ersten „Huhn frisst Käfer" Regel-Upgrade spüren; Ketten-Fall hat Rhythmus |

**V1 macht nicht:** Echtzeit-Rangliste im Spiel, Zuschauen bei Freunden, Figurenaufzucht, offene Welt, GLTF-Hochdetailmodelle, vom Spieler erstellte Level.

---

## 2. Spielerablauf

```
大厅点「开始」
  → 加载页（读 session）
  → 选关（四关列表 + 余额，P4 才显示扣费）
  → 对局
       HUD：目标 / 步数或倒计时 / 分数 / combo / 技能槽
       棋盘：点击选中 → 点相邻交换
       无消：弹回，不扣步
       有消：扣 1 步 → 消除动画 → 下落 → 补子 → 自动连锁
  → 通关 / 失败结算
  → 下一关 / 重试 / 回选关
```

Beim ersten Betreten des „Erntelevels" erscheinen 3 Hinweise und verschwinden wieder; überspringbar, erscheint danach nicht mehr (localStorage).

---

## 3. Kernzyklus

1. Ziel ansehen (wie viele Pflanzen / Hühner und Enten / Bäume / Elefantentritte noch fehlen).
2. Gleiche Drei-in-einer-Reihe finden oder den Unterdrücker neben zwei Beutetiere bewegen.
3. Tauschen → Eliminieren → Fall-Kette.
4. Ökologische Eliminierung hinterlässt auf dem Ursprungsfeld Dünger; die nächste Eliminierung auf diesem Feld bringt ×2 Punkte.
5. 3-mal hintereinander eine Auswertung mit Ökologie abschließen → Fähigkeitsleiste leuchtet; mit Sichel/Hacke/Eimer das Brett lösen.
6. Ziel erfüllt und Schritte/Zeit reichen noch → Sieg.

---

## 4. Oberflächen

| Oberfläche | Elemente | Verhalten |
|------|------|------|
| Laden | Spielname, Fortschritt | Session ungültig → Hinweis zurück zur Lobby |
| Levelauswahl | Vier Levelkarten: Name, Zielzusammenfassung, Freigeschaltet? | V1 alle vier Level offen; P4 Anzeige der Eintrittsgebühr |
| HUD oben im Spiel | Levelname, Ziel-Fortschrittsbalken, verbleibende Schritte oder Countdown, Punktzahl, combo | Countdown läuft in Sekunden, friert bei Pause ein |
| HUD unten im Spiel | Fähigkeitsleiste (max. 2), Pause | Nicht aufgeladene Slots grau |
| Brett | 8×8 Felder + Figuren | Ausgewählt: Hüpfen + Kontur; ungültige Felder ohne Kontur |
| Pause | Fortsetzen / Neu starten / Aufgeben | Neu starten kostet einen Versuch; Aufgeben zählt als Niederlage |
| Sieg | Punktzahl, verbleibende Schritte, ob Belohnung (P4) | Nächstes Level / zurück zur Auswahl |
| Niederlage | Grund (Schritte/Zeitüberschreitung), wie viel vom Ziel fehlt | Erneut versuchen / zurück zur Auswahl |
| Unzureichendes Guthaben | Text + „Aufladen" | nur P4 |

Tastatur (P5): Pfeiltasten ändern die Auswahl, Enter tauscht mit der Zielfigur des gewählten Feldes. V1 nur Maus/Touch.

---

## 5. Bedienungsregeln (Spielerperspektive)

- Es können nur **orthogonal benachbarte** und beidseitig bewegliche Figuren getauscht werden.
- Stein-, Baum- und Wasserpfützenfelder können nicht als Tauschobjekt dienen. Ein gesperrter Elefant kann nicht weggetauscht werden (Beute wird herangetauscht).
- Ergibt sich nach dem Tausch horizontal oder vertikal keine „gültige Drei-in-einer-Reihe" → zurücktauschen, **kein Schritt- und kein Zeitabzug**.
- Gültige Drei-in-einer-Reihe → 1 Schritt abziehen (Zeitlevels ziehen keine Schritte ab, nur die Uhr läuft).
- Erst wenn die Kette vollständig abgespielt ist, wird die nächste Eingabe akzeptiert; Klicks auf das Brett während der Kette sind wirkungslos.
- Diagonal zählt nicht. L-/T-förmige Überschneidungen eliminieren jede Zelle nur einmal.

---

## 6. Drei Eliminierungsarten (Funktionen)

Priorität: **Elefant > Ökologie > Gleichart**. Eine Reihe wird nur einmal nach der höchsten Priorität gewertet.

### 6.1 Gleichart

Drei oder mehr **derselben Art** in einer Linie. Beispiel: Apfel-Apfel-Apfel.

| Länge | Was der Spieler sieht |
|------|----------------|
| 3 | Schrumpfen und verschwinden, Grundpunkte |
| 4 | Verschwinden, auf dem Mittelfeld erscheint Dünger |
| 5+ | Verschwinden, Fähigkeitsleiste +1 Aufladung (begrenzt durch die im Level erlaubten Fähigkeiten) |

### 6.2 Ökologie (Jagd)

In einer Linie **genau 1 Unterdrücker**, der Rest sind seine Beutetiere; die Beutetiere müssen nicht gleich sein. Beispiel: Huhn-Ameise-Marienkäfer.

| Unterdrücker | Kann fressen | Kann nicht fressen |
|--------|------|--------|
| 鸡、鸭、鹅 (Huhn, Ente, Gans) | Blumen, Gemüse, Obst, Insekten | Getreide |
| 狗 (Hund) | Huhn, Ente, Gans, Taube usw. Geflügel | Pflanzen, Insekten |
| 猪 (Schwein) | Bäume, Blumen, Gemüse, Obst, Insekten, Getreide | Hund |
| 牛、马 (Rind, Pferd) | Blumen, Getreide, Baumschösslinge | Insekten, Fleisch |
| 大象 (Elefant) | siehe 6.3 | Hindernisse, Werkzeuge |

Was der Spieler sieht: Jagdanimation → alle drei Felder leeren sich (V1: Unterdrücker verlässt mit zusammen das Brett) → auf dem Ursprungsfeld des Unterdrückers bleibt Dünger.

### 6.3 Elefant

Eine Linie mit 1 Elefanten + zwei weiteren beliebigen eliminierbaren Figuren → drei Felder leeren, ohne Rücksicht auf die Fraktion. Maximal 1 Elefant gleichzeitig auf dem Brett. Entsteht nicht durch normalen Tausch „synthetisiert"; nach 5 Combos lässt das System einen auf ein leeres oberes Feld fallen, oder er wird zu Levelbeginn platziert.

---

## 7. V1-Auftrittstafel (nicht die 100 Arten aus der Planung)

Alle geplanten Arten bleiben als Tafeldaten erhalten, aber **V1 spawnt im Spiel nur die folgenden**, damit alles erkennbar und vollständig eliminierbar ist.

| Art | Fraktion | Vorkommende Level | Erkennung durch den Spieler |
|------|------|----------|----------|
| Weizen wheat | Getreide | Ernte, Zerstörer, Party | Goldene Ähre |
| Reis rice | Getreide | Ernte | Grüne Ähre |
| Mais corn | Getreide | Ernte | Gelber Kolben |
| Kohl cabbage | Gemüse | Ernte | Hellgrüner Blattkopf |
| Tomate tomato | Gemüse | Ernte | Rote Kugel |
| Apfel apple | Obst | Ernte, Zerstörer, Party | Rote Kugel + Stiel |
| Rose rose | Blumen | Zerstörer | Rote Blütenblätter |
| Ameise ant | Insekten | Ernte (geringes Gewicht) | Kleines Schwarz |
| Marienkäfer ladybug | Insekten | Ernte | Rot mit Punkten |
| Huhn hen | Geflügel | Ernte, Vertreiben, Party | Ellipse + Schnabel |
| Ente duck | Geflügel | Ernte, Vertreiben | Flacher Schnabel |
| Gans goose | Geflügel | Vertreiben | Langer Hals |
| Taube pigeon | Geflügel | Vertreiben | Grau |
| Hund dog | Nutztiere | Vertreiben, Party | Vierbeinig |
| Schwein pig | Nutztiere | Zerstörer, Party | Rosa Ellipse |
| Kiefer pine | Bäume/Hindernis | Zerstörer | Kegelförmige Krone, nicht tauschbar |
| Elefant elephant | Top-Level | Party; in anderen Leveln Combo-5-Belohnung | Großer Würfel + Rüssel |

Werkzeuge (Sichel, Hacke, Eimer) **kommen nicht aufs Brett**, nur im HUD. Die übrigen geplanten Werkzeuge sind in V1 nicht im Spiel.

---

## 8. Level-Spezifikationen

Sieg/Niederlage werden **nach dem Ende der gesamten Kettenanimation** gewertet.

### 8.1 Erntelevel

- Pool: Weizen, Reis, Mais, Kohl, Tomate, Apfel, Huhn, Ente; Ameise/Marienkäfer mit geringem Gewicht.
- Sieg: Innerhalb von 20 Schritten **50** Pflanzenfiguren eliminieren (Getreide+Gemüse+Obst+Blumen). Eliminierte Hühner/Enten zählen nicht.
- Niederlage: 0 Schritte und Ziel nicht erreicht.
- Fähigkeit: Sichel (nach Aufladung nutzbar).
- Tutorial: ① Benachbarte tauschen ② Drei Gleiche eliminieren ③ Das Huhn kann zwei angrenzende Käfer/Gemüse/Obst fressen, aber kein Getreide.

### 8.2 Vertreiblevel

- Pool: Huhn, Ente, Gans, Taube, Hund. Keine Pflanzen.
- Sieg: Innerhalb von **90 Sekunden** mit der **ökologischen Eliminierung des Hundes** 15 Geflügel eliminieren.
- Niederlage: Zeitüberschreitung.
- **Drei gleiche Hühner zählen nicht für das Ziel** (die Ökologie Hund-frisst-Geflügel muss abgeschlossen werden).
- Fähigkeit: keine. Pause friert die Zeit ein.

### 8.3 Zerstörerlevel

- Pool: Weizen, Apfel, Rose, Schwein (geringes Gewicht). Feste 3 Kiefern, HP=5, nicht tauschbar, nicht durchfallbar.
- Sieg: HP der 3 Bäume auf 0.
- Niederlage: 25 Schritte verbraucht.
- Baumschaden: Schwein-Ökologie (Baum in der Beuteliste) -2; drei Schweine in einer Linie lösen **3×3-Wühlangriff** aus (Baum im Bereich -5); Hacke auf einzelnen Baum -3; normale benachbarte Drei-in-einer-Reihe -1.
- Fähigkeit: Hacke.

### 8.4 Elefantenparty

- Pool: Weizen, Apfel, Huhn, Hund, Schwein. Zu Beginn 1 gesperrter Elefant nahe der Mitte.
- Sieg: Mit der **Elefantenregel** 30 Felder eliminieren (Gleichart/Ökologie zählen nicht für dieses Ziel).
- Niederlage: 30 Schritte verbraucht.
- Es wird kein zweiter Elefant gespawnt. Der Spieler tauscht Beute neben oder über/unter den Elefanten.
- Fähigkeit: keine.

---

## 9. Hindernisse, Dünger, Fähigkeiten

| Funktion | Wahrnehmung des Spielers | Regeln |
|------|----------|------|
| Stein | Grau, nicht anklickbar | HP3; benachbarte Eliminierung -1; Hacke zertrümmert ihn auf einmal |
| Baum | Großes Modell, nicht anklickbar | siehe Zerstörerlevel |
| Wasserpfütze | Reflektierende Feldoberfläche | Figuren oben stoppen auf dem Feld über der Pfütze; nach dem Ausschöpfen mit dem Eimer fällt das Fallen weiter |
| Dünger | Dunkler Fleck auf der Feldoberfläche | Nächste Eliminierung auf diesem Feld ×2 Punkte, danach verschwindet er |
| Sichel | Symbol in der unteren Leiste | Eine Zeile oder Spalte wählen, nur Pflanzen, kostet keinen Schritt, kostet 1 Aufladung |
| Hacke | Symbol in der unteren Leiste | Auf 1 Stein oder Baum tippen |
| Eimer | Symbol in der unteren Leiste | Auf 1 Wasserpfützenfeld tippen |

Aufladung: Wenn in der gesamten Auswertung einer Spieleraktion eine ökologische Eliminierung vorkam, zählt der Zähler +1; bei 3 erhält man 1 Slot, Obergrenze 2. Auch 5 gleiche in einer Reihe geben +1 Slot (teilt sich die Slots mit der Öko-Aufladung).

V1: Ernte ohne Steine/Pfützen; Zerstörer ohne Pfützen. Die Pfütze bleibt in der Tafel und blockiert nicht die vier Hauptlevel.

---

## 10. Punkte und Wirtschaft

```
同种     10 × 消掉数 × combo × 肥料
生态     25 × 消掉数 × combo × 肥料
大象     40 × 消掉数 × combo
技能清格  8 × 消掉数
破障碍   20 × 破碎数
```

combo: Erste Eliminierung dieser Aktion = 1, jede weitere Kettenrunde +1; bei der nächsten manuellen Aktion des Spielers zurück auf 1.

**P4-Wallet:**

- Beim Levelstart wird die Eintrittsgebühr abgezogen (Standard: 1 Spielwährung pro Level).
- Abschluss wird nach Sternen bewertet: verbleibende Ressourcen ≥50 % drei Sterne, ≥20 % zwei Sterne, sonst ein Stern; Belohnung 2 / 3 / 5 (konfigurierbar).
- Bei Niederlage wird die Eintrittsgebühr nicht erstattet.
- Beenden ohne einen einzigen Tausch → Rückerstattung.
- Unzureichendes Guthaben → kein Levelstart möglich.

V1 (P0–P3) ohne Gebühren, lokal direkt spielbar.

---

## 11. Funktionsliste und Abnahme

| ID | Funktion | Abnahme | Phase |
|----|------|------|------|
| F01 | 8×8 Klicken-Tauschen | Benachbart tauschbar, diagonal nicht, ohne Eliminierung zurückspringen | P0 |
| F02 | Gleichart-Match-3 + Schwerkraft + Nachfüllen | Drei Weizen eliminieren, oben fällt nach, oben werden neue Figuren nachgefüllt | P0 |
| F03 | Kette | Nach dem Fall automatisch weiter eliminieren, combo-Zahl +1 | P0 |
| F04 | Levelauswahl vier Level | Klick führt zum entsprechenden Ziel-HUD | P1 |
| F05 | Ernteziel | 20 Schritte, 50 Pflanzen, Zählung nur Pflanzen | P1 |
| F06 | Ökologische Eliminierung | Huhn+zwei Käfer eliminieren; Huhn+zwei Weizen nicht | P2 |
| F07 | Dünger | Nach Ökologie bringt die nächste Eliminierung auf diesem Feld doppelte Punkte (einmal) | P2 |
| F08 | Vertreibeziel | Gleiche Hühner zählen nicht; Hund-frisst-Huhn zählt; 90s | P2 |
| F09 | Baum und Hacke | Baum nicht tauschbar; Hacke/Schwein können ihn abbauen | P3 |
| F10 | Drei Schweine 3×3 | Drei Schweine in einer Linie, Bäume im Bereich zerbrechen direkt | P3 |
| F11 | Sichel | Eine Pflanzenzeile leeren, kostet keinen Schritt | P3 |
| F12 | Gesperrter Elefant | Elefant nicht wegzutauschen; Elefant+zwei Figuren leeren drei Felder | P4 |
| F13 | Partyeziel | Nur Elefantenregel zählt 30 | P4 |
| F14 | Eintrittsgebühr/Belohnung | Guthabenabgleich, keine Doppelauszahlung bei wiederholter Abrechnung | P4 |
| F15 | Tutorial | Drei Hinweise, Überspringen dauerhaft | P1 |
| F16 | Pause/Neu starten/Aufgeben | Zeit friert ein; Aufgeben zählt als Niederlage | P1 |
| F17 | Partikel für schwache Geräte | Nach Aktivierung stabile Bildrate, spielbar | P5 |

---

## 12. Grenzen (müssen festgeschrieben werden)

1. Die Tafel kann groß sein, **aber die Spawnarten pro Level ≤ 8**.
2. Werkzeuge kommen nicht aufs Brett.
3. Hühner fressen kein Getreide: Eine Linie „Huhn+Weizen+Weizen" ist weder Ökologie noch Gleichart — zurückspringen.
4. Hunde fressen keine Pflanzen; Schweine wühlen nicht gegen Hunde.
5. Maximal 1 Elefant gleichzeitig auf dem Brett.
6. Während der Kettenanimation wird Eingabe verworfen.
7. Sieg/Niederlage werden nicht mitten in der Animation entschieden.
8. V1: Unterdrücker und Beute verlassen zusammen das Brett.
9. Vertreiblevel: 90 Sekunden Zeitlimit, keine Schritte.
10. Wasserpfützen kommen nicht in die vier Hauptlevel.
