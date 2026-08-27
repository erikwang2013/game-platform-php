<!-- lang-nav -->

Languages: [中文](design.md) · [English](design.en.md) · [한국어](design.ko.md) · [Русский](design.ru.md) · **Deutsch** · [Français](design.fr.md) · [Español](design.es.md) · [Português](design.pt.md) · [हिन्दी](design.hi.md) · [العربية](design.ar.md) · [বাংলা](design.bn.md) · [Bahasa Indonesia](design.id.md) · [日本語](design.ja.md)

Gut, als dein Game-Designer und 3D-Technologieverantwortlicher erstelle ich dir eine vollständige Design- und Planungsspezifikation für das 《Three.js 消消乐》 (Three.js Match-3). Diese Spezifikation enthält keinen Code und konzentriert sich auf die **Elementerweiterung**, die **Regelmatrix**, den **Gameplay-Fusionsmechanismus** sowie die **Three.js-Szenenaufbaustrategie**.

---

### 一、 Spielelemente-Erweiterung (Figurenbibliothek-Design)

Um das Brett reicher zu gestalten, habe ich auf Basis deiner Vorgaben die Elemente in **6 große Fraktionen** unterteilt, insgesamt **24** Grundfiguren + **4** Spezial-Items:

| Fraktion | Enthaltene Elemente | Ergänzende Erläuterung |
| :--- | :--- | :--- |
| **🌾 Feldfrüchte** | Basis-Eliminierungsfiguren | 水稻、小麦、玉米、高粱、大麦、燕麦、黑麦、小米、芝麻、花生、棉花、油菜、茶叶、黄米、薏米、荞麦、黄豆、绿豆、红豆、黑豆、蚕豆、豌豆、红薯、土豆、山药、芋头、木薯 |
| **🥬 Gemüse** | Basis-Eliminierungsfiguren | 白菜、萝卜、黄瓜、西红柿、辣椒、茄子、葱、姜、蒜、生菜、胡萝卜、苦瓜、芫荽、小葱、芥菜、芹菜、菠菜、花菜、冬瓜、南瓜、韭菜 |
| **🥬 Obst** | Basis-Eliminierungsfiguren | 苹果、梨、桃、杏、李子、草莓、西瓜、葡萄、酸枣、欧李、枣、核桃、杏仁、无花果、橘子、香蕉、柿子、石榴、猕猴桃、车厘子、樱桃 |
| **🥬 Blumen und Kräuter** | Basis-Eliminierungsfiguren | 玫瑰、向日葵、月季、烧汤花、指甲花、鸡冠花、木槿花、山茶花、牡丹花、茉莉花、紫藤花、蝴蝶兰、菊花、梅花、兰花、荷花、车前草、地黄、枸杞、狗尾草、蒲公英、牛筋草、云酱菜 |
| **🐜 Tiere** | Basis-Eliminierungsfiguren | 蚂蚁、蜜蜂、七星瓢虫、毛毛虫、蝉、马蜂、蟋蟀、蚂蚱、蜥蜴、老鼠、倾听、水蛭、青蛙、蛤蟆、虾、鱼、狐狸、松鼠、蝴蝶、螳螂、蜘蛛、萤火虫 |
| **🐓 Geflügel/Gefieder** | Mittlere Raubtiere | 鸡、鸭、鹅、鸽子、麻雀、喜鹊、燕子、乌鸦、猫头鹰、老鹰 |
| **🐕 Nutztiere/Großtiere** | Hochwertige Figuren | 猪、狗、牛、马、羊、兔子、猫、驴、骡子、骆驼 |
| **🌳 Bäume/Natur** | Hindernisse/Spezialfiguren | 松树、柳树、杨树、槐树、泡桐、梧桐、杉树、银杏、榆树、竹子、桦树、枫树 |
| **🔧 Werkzeuge** | Fähigkeits-Items | 镰刀、锄头、水桶、锤子、耙子、簸箕、背篓、草帽、蓑衣、手电筒、石磙、架子车、自行车、斧头、扁担、犁、磨盘 |

---

### 二、 Erweiterung der Kernregeln („Ökologische Kettmechanik"-Design)

Deine Regellogik ist im Kern ein **„gerichtetes Eliminieren"**. Beim klassischen Match-3 (drei Gleiche eliminieren) betten wir eine **„Fressen/Unterdrücken-Matching"**-Mechanik ein. Wenn der Spieler einen **Unterdrücker** und **Unterdrückte** zu einer Drei-in-einer-Reihe (oder einer bestimmten Form) anordnet, wird eine erweiterte Eliminierung ausgelöst.

Im Folgenden die von mir erweiterte **vollständige Unterdrückungsmatrix** (A unterdrückt B):

| Unterdrücker (A) | Unterdrückungsweise | Unterdrückte (B) | Erläuterung der erweiterten Regel |
| :--- | :--- | :--- | :--- |
| **鸡、鸭、鹅 (Huhn, Ente, Gans)** | Picken / Jagen | 花草、蔬果、昆虫（蚂蚁/瓢虫/毛毛虫） (Blumen, Gemüse/Obst, Insekten) | Ergänzung: Sie **fressen keine** Getreidesorten (Feldfrüchte), weil das Korn zu hart ist und separat eliminiert werden muss. |
| **狗 (Hund)** | Beißen | 鸡、鸭、鹅、鸽子 (Huhn, Ente, Gans, Taube) | Der Hund beißt nicht nur Geflügel; ergänzend **nagt der Hund auch an Knochen (entspricht Schweine/Rinder/Pferdeknochen)**, aber im Spiel vereinfacht unterdrückt er alle kleinen und mittleren Geflügelarten. |
| **猪 (Schwein)** | Wühlen / Verwüsten | 树木、花草、蔬果、昆虫、**所有五谷庄稼** (Bäume, Blumen, Gemüse/Obst, Insekten, **alle Getreidefeldfrüchte**) | Das Schwein ist der Zerstörer; Ergänzung: Das Schwein **wühlt nicht gegen** den Hund (weil der Hund das Schwein beißt), wodurch ein Unterdrückungszyklus entsteht. |
| **牛、马 (Rind, Pferd)** | Fressen / Zertreten | 花草、**五谷庄稼**、果树苗 (Blumen, **Getreidefeldfrüchte**, Obstbaum-Setzlinge) | Ergänzung: Rind und Pferd sind große pflanzenfressende Zugtiere, die speziell Feldfrüchte unterdrücken, aber keine Insekten und kein Fleisch fressen. |
| **大象 (Elefant)** | Absolute Dominanz (Treten/Schleudern) | **除大象外所有元素（包括猪狗牛马）** (alle Elemente außer dem Elefanten, einschließlich Schwein, Hund, Rind, Pferd) | Der Elefant ist die höchste Kampfkraft. Für die Balance: Der Elefant **kann** „Werkzeuge" (Items) **nicht** eliminieren, und die Auftrittswahrscheinlichkeit des Elefanten auf dem Brett ist extrem gering (seltene Figur). |
| **镰刀（道具）(Sichel, Item)** | Ernten | 所有五谷庄稼、花草 (alle Getreidefeldfrüchte, Blumen) | Entfernt einmalig alle Pflanzenreihen oder -spalten. |
| **锄头（道具）(Hacke, Item)** | Zerschlagen | 树木、石头（障碍） (Bäume, Steine/Hindernisse) | Beseitigt gezielt Hindernisse mit hoher Lebenspunktezahl. |

---

### 三、 Gameplay-Mechanik-Design (Wie funktioniert das Match-3?)

In der Three.js-3D-Szene verwenden wir den Fusionsmodus **„Klicken-tauschen + Ökologische-Auswertung"**:

1.  **Basisoperation**: Der Spieler tauscht zwei benachbarte 3D-Figuren per Klick.
2.  **Auswertungslogik (entscheidend)**:
    - **Gleichart-Eliminierung**: Bilden sich nach dem Tausch horizontal oder vertikal **≥3 gleiche Figuren**, wird die Basiseliminierung ausgeführt (z. B. drei Äpfel).
    - **Ökologische Eliminierung (speziell)**: Bilden sich nach dem Tausch horizontal oder vertikal **„Unterdrücker + zwei beliebige Unterdrückte"** (z. B. Huhn + Käfer + Käfer), müssen **nicht alle drei gleich sein** — die „Jagdanimation" wird direkt ausgelöst, das Huhn frisst den Käfer, mit Extrapunkten, und auf dem Feld entsteht ein **„Kot-Dünger"** (Verstärkungs-Buff: die nächste Eliminierung bringt doppelte Punkte).
    - **Elefantenprivileg**: Der Elefant löst mit **zwei beliebigen verschiedenen Figuren** in einer Reihe den „Schikanieren"-Effekt aus und leert die drei Felder direkt, ohne Rücksicht auf die Art.
3.  **Kettenreaktion (Combo)**: Nach der Eliminierung fallen die Figuren von oben nach und füllen die Lücken. Wenn der Fall eine neue „ökologische Kette" erzeugt, wird automatisch eine Combo ausgelöst (ohne Spielereingriff) — für das befriedigende Spielgefühl.

---

### 四、 Three.js-Szenen- und Visualisierungsplanung (ohne Code)

Damit das 3D-Match-3 hochwertiger wirkt als 2D, ist folgende Planung vorgesehen:

| Modul | Technische Auswahl/Designansatz |
| :--- | :--- |
| **Kameraperspektive** | **45-Grad-Orthografie-Perspektive (OrthographicCamera)** oder **feste Perspektivansicht**. Das Brett soll wie ein „dreidimensionales Sandmodell" wirken, um die Betrachtung der Stapel in der Tiefe zu erleichtern. Empfohlen wird eine feste 2.5D-Perspektive ohne Orbitsteuerung (gegen Schwindel). |
| **Brettlayout** | **8x8-Raster**, wobei jedes Feld eine **Y-Achsen-Höhenvariation** erhält (Simulation von Feldhügeln). Die Figuren werden auf Zylindern oder Quadsäulen erhöht, mit einem Reflexionskreis am Boden. |
| **3D-Modellansatz** | Keine externen komplexen FBX/GLTF-Dateien laden (zu viel Datenvolumen). **Ausschließlich Three.js-Basisgeometrie-Kombinationen (Group)**:<br>- **Apfel**: Kugel + Zylinderstiel.<br>- **Huhn**: Ellipsoid (Körper) + Kegel (Schnabel) + Kugel (Kopf).<br>- **Elefant**: großer Würfel (Körper) + gestreckter Zylinder (Rüssel) + plattenförmige Ohren.<br>- **Baum**: Kegel (Krone) + Zylinder (Stamm).<br>Verwendung des **Low-Poly-Stils** mit **weichem Lichtmaterial (MeshPhongMaterial)**, kräftige Farben. |
| **Licht und Atmosphäre** | Hauptlicht: paralleles Licht (simuliert Sonnenlicht). Zusatzlicht: Umgebungslicht (hellt Schattenbereiche auf). Gegenlicht: Bereichslicht (konturiert Kanten). Hintergrund mit Farbverlaufs-Himmelskugel und schwebenden Partikeln (simuliert Pollen oder Glühwürmchen). |
| **Interaktionsfeedback** | **Raycaster (Strahlenerkennung)** für Klick-Auswahl. Beim Klick auf eine Figur **springt sie hoch + Glow-Kontur (OutlinePass)**; beim Tausch **weiche Interpolation (TWEEN oder GSAP)**; bei der Eliminierung **Skalierungs-Verschwinden + Partikelexplosion** (mit PointsMaterial). |

---

### 五、 Erweiterung von Spezial-Items und Hindernissen

Für zusätzliche strategische Tiefe werden folgende Mechanismen ergänzt:

1.  **Hindernisse (unbeweglich)**:
    - **Stein**: Belegt ein Feld; muss mit der „Hacke"-Fähigkeit oder drei normalen Eliminierungen benachbart zerschlagen werden.
    - **Wasserpfütze**: Figuren können nicht hindurchfallen; muss mit der „Eimer"-Fähigkeit trockengelegt werden.
2.  **Erwerbsweise der „Werkzeuge"**:
    - Sie erscheinen nicht direkt auf dem Brett. Wenn der Spieler **3-mal hintereinander „ökologische Unterdrückung"** auslöst, lädt sich die Fähigkeitsleiste unten auf; per Klick kann die entsprechende Werkzeugfähigkeit freigesetzt werden (z. B. mit der Sichel über den Bildschirm streichen und eine Reihe leeren).

---

### 六、 Level- und Zielplanung

| Leveltyp | Siegbedingung | Besondere Einschränkung |
| :--- | :--- | :--- |
| **Erntelevel** | Innerhalb von 20 Zügen **50 Getreide/Gemüse-Figuren** eliminieren. | Hühner, Enten und Gänse erscheinen massenhaft auf dem Brett und stören die Pflanzeneliminierung. |
| **Vertreiblevel** | Innerhalb der Zeitvorgabe mit dem **Hund** **15 Hühner/Enten** eliminieren. | Auf dem Brett erscheinen nur Geflügel und Hunde, keine Pflanzen. |
| **Zerstörerlevel** | Mit dem **Schwein** **3 große Bäume** umwühlen (Baum-Lebenspunkte 5). | Die Auftrittswahrscheinlichkeit des Schweins ist gering; drei Schweine müssen zusammengebracht werden, um den Flächen-Angriff auszulösen. |
| **Elefantenparty** | Mit dem Elefanten **30 beliebige Figuren** eliminieren. | Zu Beginn wird ein Elefant geschenkt; beschütze ihn — er darf nicht weggetauscht werden. |

---

### 七、 Zusammenfassung der Planungshighlights

1. **In sich stimmige Regeln**: Die biologische Nahrungskette (Huhn frisst Käfer, Hund beißt Huhn, Elefant unbesiegbar) wird perfekt in die „Paarungsbedingungen" des Match-3 übersetzt — lehrreicher und strategischer als reines Match-3.
2. **3D-Ausdruckskraft**: Mit der Three.js-Physikengine (oder simulierten Animationen) erhalten die Figuren „Schwerkraft-Fall" und „elastisches Hüpfen" — deutlich mehr Räumlichkeit als 2D-Sprites.
3. **Balancesicherung**: Da der Elefant zu stark ist, wird er als seltene Erscheinung eingestuft (maximal 1 gleichzeitig pro Partie) und kann nicht durch normalen Tausch erzeugt werden; er entsteht nur als Belohnung des Systems nach 5 Combos, um eine Unbalance zu verhindern.

Diese Planung bewahrt die „leicht zu erlernende" Zugänglichkeit des klassischen Match-3, pflanzt aber den einzigartigen Kern des „ökologischen Tierkampfspiels" ein und ist technisch mit Three.js vollständig umsetzbar (reine Geometry-Kombinationen + Basisshader). Du kannst die Entwicklung direkt nach diesem Bauplan starten. Wenn du Details vertiefen möchtest (z. B. konkrete Animationskurven oder Partikelfarbabstimmungen), sag mir jederzeit Bescheid. 🐘🌾

