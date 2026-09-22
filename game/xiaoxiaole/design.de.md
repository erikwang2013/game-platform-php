<!-- lang-nav -->

Languages: [中文](design.md) · [English](design.en.md) · [한국어](design.ko.md) · [Русский](design.ru.md) · **Deutsch** · [Français](design.fr.md) · [Español](design.es.md) · [Português](design.pt.md) · [हिन्दी](design.hi.md) · [العربية](design.ar.md) · [বাংলা](design.bn.md) · [Bahasa Indonesia](design.id.md) · [日本語](design.ja.md)

Gut, als dein Game-Designer und 3D-Technologieverantwortlicher erstelle ich dir eine vollständige Design- und Planungsspezifikation für das Three.js-Match-3-Spiel. Diese Spezifikation enthält keinen Code und konzentriert sich auf die **Elementerweiterung**, die **Regelmatrix**, den **Gameplay-Fusionsmechanismus** sowie die **Three.js-Szenenaufbaustrategie**.

---

### 1. Spielelemente-Erweiterung (Figurenbibliothek-Design)

Um das Brett reicher zu gestalten, habe ich auf Basis deiner Vorgaben die Elemente in **6 große Fraktionen** unterteilt, insgesamt **24** Grundfiguren + **4** Spezial-Items:

| Fraktion | Enthaltene Elemente | Ergänzende Erläuterung |
| :--- | :--- | :--- |
| **🌾 Feldfrüchte** | Basis-Eliminierungsfiguren | Reis, Weizen, Mais, Sorghum, Gerste, Hafer, Roggen, Hirse, Sesam, Erdnüsse, Baumwolle, Raps, Tee, Gelbhirse, Perlgerste, Buchweizen, Sojabohnen, Mungbohnen, rote Bohnen, schwarze Bohnen, Ackerbohnen, Erbsen, Süßkartoffeln, Kartoffeln, Yams, Taro, Maniok |
| **🥬 Gemüse** | Basis-Eliminierungsfiguren | Kohl, Rettich, Gurke, Tomate, Chili, Aubergine, Frühlingszwiebel, Ingwer, Knoblauch, Kopfsalat, Karotte, Bittermelone, Koriander, Schnittlauch, Senfblätter, Sellerie, Spinat, Blumenkohl, Wintermelone, Kürbis, Lauch |
| **🥬 Obst** | Basis-Eliminierungsfiguren | Apfel, Birne, Pfirsich, Aprikose, Pflaume, Erdbeere, Wassermelone, Weintraube, Sauerjujube, Chinesische Zwergkirsche, Jujube, Walnuss, Mandel, Feige, Orange, Banane, Kaki, Granatapfel, Kiwi, Kirsche |
| **🥬 Blumen und Kräuter** | Basis-Eliminierungsfiguren | Rose, Sonnenblume, Monatsrose, Nachtkerze, Henna, Hahnenkamm, Hibiskus, Kamelie, Pfingstrose, Jasmin, Glyzinie, Phalaenopsis, Chrysantheme, Pflaumenblüte, Orchidee, Lotus, Spitzwegerich, Rehmannia, Goji-Beere, Fuchsschwanzgras, Löwenzahn, Hühnerhirse, Wolkengemüse |
| **🐜 Tiere** | Basis-Eliminierungsfiguren | Ameise, Biene, Siebenpunkt-Marienkäfer, Raupe, Zikade, Hornisse, Grille, Grashüpfer, Eidechse, Maus, Tausendfüßer, Blutegel, Frosch, Kröte, Garnele, Fisch, Fuchs, Eichhörnchen, Schmetterling, Gottesanbeterin, Spinne, Glühwürmchen |
| **🐓 Geflügel/Gefieder** | Mittlere Raubtiere | Huhn, Ente, Gans, Taube, Spatz, Elster, Schwalbe, Krähe, Eule, Adler |
| **🐕 Nutztiere/Großtiere** | Hochwertige Figuren | Schwein, Hund, Rind, Pferd, Schaf, Kaninchen, Katze, Esel, Maultier, Kamel |
| **🌳 Bäume/Natur** | Hindernisse/Spezialfiguren | Kiefer, Weide, Pappel, Schnurbaum, Paulownie, Firmiana, Tanne, Ginkgo, Ulme, Bambus, Birke, Ahorn |
| **🔧 Werkzeuge** | Fähigkeits-Items | Sichel, Hacke, Wassereimer, Hammer, Rechen, Worfelkorb, Rückentrage, Strohhut, Strohumhang, Taschenlampe, Steinwalze, Karren, Fahrrad, Axt, Schulterjoch, Pflug, Mühlstein |

---

### 2. Erweiterung der Kernregeln („Ökologische Kettmechanik"-Design)

Deine Regellogik ist im Kern ein **„gerichtetes Eliminieren"**. Beim klassischen Match-3 (drei Gleiche eliminieren) betten wir eine **„Fressen/Unterdrücken-Matching"**-Mechanik ein. Wenn der Spieler einen **Unterdrücker** und **Unterdrückte** zu einer Drei-in-einer-Reihe (oder einer bestimmten Form) anordnet, wird eine erweiterte Eliminierung ausgelöst.

Im Folgenden die von mir erweiterte **vollständige Unterdrückungsmatrix** (A unterdrückt B):

| Unterdrücker (A) | Unterdrückungsweise | Unterdrückte (B) | Erläuterung der erweiterten Regel |
| :--- | :--- | :--- | :--- |
| **Huhn, Ente, Gans** | Picken / Jagen | Blumen, Gemüse/Obst, Insekten | Ergänzung: Sie **fressen keine** Getreidesorten (Feldfrüchte), weil das Korn zu hart ist und separat eliminiert werden muss. |
| **Hund** | Beißen | Huhn, Ente, Gans, Taube | Der Hund beißt nicht nur Geflügel; ergänzend **nagt der Hund auch an Knochen (entspricht Schweine/Rinder/Pferdeknochen)**, aber im Spiel vereinfacht unterdrückt er alle kleinen und mittleren Geflügelarten. |
| **Schwein** | Wühlen / Verwüsten | Bäume, Blumen, Gemüse/Obst, Insekten, **alle Getreidefeldfrüchte** | Das Schwein ist der Zerstörer; Ergänzung: Das Schwein **wühlt nicht gegen** den Hund (weil der Hund das Schwein beißt), wodurch ein Unterdrückungszyklus entsteht. |
| **Rind, Pferd** | Fressen / Zertreten | Blumen, **Getreidefeldfrüchte**, Obstbaum-Setzlinge | Ergänzung: Rind und Pferd sind große pflanzenfressende Zugtiere, die speziell Feldfrüchte unterdrücken, aber keine Insekten und kein Fleisch fressen. |
| **Elefant** | Absolute Dominanz (Treten/Schleudern) | **alle Elemente außer dem Elefanten (einschließlich Schwein, Hund, Rind, Pferd)** | Der Elefant ist die höchste Kampfkraft. Für die Balance: Der Elefant **kann** „Werkzeuge" (Items) **nicht** eliminieren, und die Auftrittswahrscheinlichkeit des Elefanten auf dem Brett ist extrem gering (seltene Figur). |
| **Sichel (Item)** | Ernten | alle Getreidefeldfrüchte, Blumen | Entfernt einmalig alle Pflanzenreihen oder -spalten. |
| **Hacke (Item)** | Zerschlagen | Bäume, Steine/Hindernisse | Beseitigt gezielt Hindernisse mit hoher Lebenspunktezahl. |

---

### 3. Gameplay-Mechanik-Design (Wie funktioniert das Match-3?)

In der Three.js-3D-Szene verwenden wir den Fusionsmodus **„Klicken-tauschen + Ökologische-Auswertung"**:

1.  **Basisoperation**: Der Spieler tauscht zwei benachbarte 3D-Figuren per Klick.
2.  **Auswertungslogik (entscheidend)**:
    - **Gleichart-Eliminierung**: Bilden sich nach dem Tausch horizontal oder vertikal **≥3 gleiche Figuren**, wird die Basiseliminierung ausgeführt (z. B. drei Äpfel).
    - **Ökologische Eliminierung (speziell)**: Bilden sich nach dem Tausch horizontal oder vertikal **„Unterdrücker + zwei beliebige Unterdrückte"** (z. B. Huhn + Käfer + Käfer), müssen **nicht alle drei gleich sein** — die „Jagdanimation" wird direkt ausgelöst, das Huhn frisst den Käfer, mit Extrapunkten, und auf dem Feld entsteht ein **„Kot-Dünger"** (Verstärkungs-Buff: die nächste Eliminierung bringt doppelte Punkte).
    - **Elefantenprivileg**: Der Elefant löst mit **zwei beliebigen verschiedenen Figuren** in einer Reihe den „Schikanieren"-Effekt aus und leert die drei Felder direkt, ohne Rücksicht auf die Art.
3.  **Kettenreaktion (Combo)**: Nach der Eliminierung fallen die Figuren von oben nach und füllen die Lücken. Wenn der Fall eine neue „ökologische Kette" erzeugt, wird automatisch eine Combo ausgelöst (ohne Spielereingriff) — für das befriedigende Spielgefühl.

---

### 4. Three.js-Szenen- und Visualisierungsplanung (ohne Code)

Damit das 3D-Match-3 hochwertiger wirkt als 2D, ist folgende Planung vorgesehen:

| Modul | Technische Auswahl/Designansatz |
| :--- | :--- |
| **Kameraperspektive** | **45-Grad-Orthografie-Perspektive (OrthographicCamera)** oder **feste Perspektivansicht**. Das Brett soll wie ein „dreidimensionales Sandmodell" wirken, um die Betrachtung der Stapel in der Tiefe zu erleichtern. Empfohlen wird eine feste 2.5D-Perspektive ohne Orbitsteuerung (gegen Schwindel). |
| **Brettlayout** | **8x8-Raster**, wobei jedes Feld eine **Y-Achsen-Höhenvariation** erhält (Simulation von Feldhügeln). Die Figuren werden auf Zylindern oder Quadsäulen erhöht, mit einem Reflexionskreis am Boden. |
| **3D-Modellansatz** | Keine externen komplexen FBX/GLTF-Dateien laden (zu viel Datenvolumen). **Ausschließlich Three.js-Basisgeometrie-Kombinationen (Group)**:<br>- **Apfel**: Kugel + Zylinderstiel.<br>- **Huhn**: Ellipsoid (Körper) + Kegel (Schnabel) + Kugel (Kopf).<br>- **Elefant**: großer Würfel (Körper) + gestreckter Zylinder (Rüssel) + plattenförmige Ohren.<br>- **Baum**: Kegel (Krone) + Zylinder (Stamm).<br>Verwendung des **Low-Poly-Stils** mit **weichem Lichtmaterial (MeshPhongMaterial)**, kräftige Farben. |
| **Licht und Atmosphäre** | Hauptlicht: paralleles Licht (simuliert Sonnenlicht). Zusatzlicht: Umgebungslicht (hellt Schattenbereiche auf). Gegenlicht: Bereichslicht (konturiert Kanten). Hintergrund mit Farbverlaufs-Himmelskugel und schwebenden Partikeln (simuliert Pollen oder Glühwürmchen). |
| **Interaktionsfeedback** | **Raycaster (Strahlenerkennung)** für Klick-Auswahl. Beim Klick auf eine Figur **springt sie hoch + Glow-Kontur (OutlinePass)**; beim Tausch **weiche Interpolation (TWEEN oder GSAP)**; bei der Eliminierung **Skalierungs-Verschwinden + Partikelexplosion** (mit PointsMaterial). |

---

### 5. Erweiterung von Spezial-Items und Hindernissen

Für zusätzliche strategische Tiefe werden folgende Mechanismen ergänzt:

1.  **Hindernisse (unbeweglich)**:
    - **Stein**: Belegt ein Feld; muss mit der „Hacke"-Fähigkeit oder drei normalen Eliminierungen benachbart zerschlagen werden.
    - **Wasserpfütze**: Figuren können nicht hindurchfallen; muss mit der „Eimer"-Fähigkeit trockengelegt werden.
2.  **Erwerbsweise der „Werkzeuge"**:
    - Sie erscheinen nicht direkt auf dem Brett. Wenn der Spieler **3-mal hintereinander „ökologische Unterdrückung"** auslöst, lädt sich die Fähigkeitsleiste unten auf; per Klick kann die entsprechende Werkzeugfähigkeit freigesetzt werden (z. B. mit der Sichel über den Bildschirm streichen und eine Reihe leeren).

---

### 6. Level- und Zielplanung

| Leveltyp | Siegbedingung | Besondere Einschränkung |
| :--- | :--- | :--- |
| **Erntelevel** | Innerhalb von 20 Zügen **50 Getreide/Gemüse-Figuren** eliminieren. | Hühner, Enten und Gänse erscheinen massenhaft auf dem Brett und stören die Pflanzeneliminierung. |
| **Vertreiblevel** | Innerhalb der Zeitvorgabe mit dem **Hund** **15 Hühner/Enten** eliminieren. | Auf dem Brett erscheinen nur Geflügel und Hunde, keine Pflanzen. |
| **Zerstörerlevel** | Mit dem **Schwein** **3 große Bäume** umwühlen (Baum-Lebenspunkte 5). | Die Auftrittswahrscheinlichkeit des Schweins ist gering; drei Schweine müssen zusammengebracht werden, um den Flächen-Angriff auszulösen. |
| **Elefantenparty** | Mit dem Elefanten **30 beliebige Figuren** eliminieren. | Zu Beginn wird ein Elefant geschenkt; beschütze ihn — er darf nicht weggetauscht werden. |

---

### 7. Zusammenfassung der Planungshighlights

1. **In sich stimmige Regeln**: Die biologische Nahrungskette (Huhn frisst Käfer, Hund beißt Huhn, Elefant unbesiegbar) wird perfekt in die „Paarungsbedingungen" des Match-3 übersetzt — lehrreicher und strategischer als reines Match-3.
2. **3D-Ausdruckskraft**: Mit der Three.js-Physikengine (oder simulierten Animationen) erhalten die Figuren „Schwerkraft-Fall" und „elastisches Hüpfen" — deutlich mehr Räumlichkeit als 2D-Sprites.
3. **Balancesicherung**: Da der Elefant zu stark ist, wird er als seltene Erscheinung eingestuft (maximal 1 gleichzeitig pro Partie) und kann nicht durch normalen Tausch erzeugt werden; er entsteht nur als Belohnung des Systems nach 5 Combos, um eine Unbalance zu verhindern.

Diese Planung bewahrt die „leicht zu erlernende" Zugänglichkeit des klassischen Match-3, pflanzt aber den einzigartigen Kern des „ökologischen Tierkampfspiels" ein und ist technisch mit Three.js vollständig umsetzbar (reine Geometry-Kombinationen + Basisshader). Du kannst die Entwicklung direkt nach diesem Bauplan starten. Wenn du Details vertiefen möchtest (z. B. konkrete Animationskurven oder Partikelfarbabstimmungen), sag mir jederzeit Bescheid. 🐘🌾

