<!-- lang-nav -->

Languages: [中文](design.md) · [English](design.en.md) · [한국어](design.ko.md) · [Русский](design.ru.md) · [Deutsch](design.de.md) · [Français](design.fr.md) · **Español** · [Português](design.pt.md) · [हिन्दी](design.hi.md) · [العربية](design.ar.md) · [বাংলা](design.bn.md) · [Bahasa Indonesia](design.id.md) · [日本語](design.ja.md)

De acuerdo, como tu diseñador de juego y responsable técnico de 3D, te prepararé una planificación de diseño completa del《Match-3 en Three.js》. Esta propuesta no incluye código y se centra en la **ampliación de elementos**, la **matriz de reglas**, los **mecanismos de fusión de jugabilidad** y las **ideas de montaje de la escena Three.js**.

---

### 一、 Ampliación de elementos del juego (diseño de la biblioteca de piezas)

Para enriquecer el tablero, partiendo de lo que aportaste, divido los elementos en **6 grandes facciones**, con un total de **24 piezas básicas** + **4 accesorios especiales**:

| Facción | Elementos incluidos | Notas complementarias |
| :--- | :--- | :--- |
| **🌾 Cultivos** | Piezas básicas de eliminación | 水稻、小麦、玉米、高粱、大麦、燕麦、黑麦、小米、芝麻、花生、棉花、油菜、茶叶、黄米、薏米、荞麦、黄豆、绿豆、红豆、黑豆、蚕豆、豌豆、红薯、土豆、山药、芋头、木薯 |
| **🥬 Verduras** | Piezas básicas de eliminación | 白菜、萝卜、黄瓜、西红柿、辣椒、茄子、葱、姜、蒜、生菜、胡萝卜、苦瓜、芫荽、小葱、芥菜、芹菜、菠菜、花菜、冬瓜、南瓜、韭菜 |
| **🥬 Frutas** | Piezas básicas de eliminación | 苹果、梨、桃、杏、李子、草莓、西瓜、葡萄、酸枣、欧李、枣、核桃、杏仁、无花果、橘子、香蕉、柿子、石榴、猕猴桃、车厘子、樱桃 |
| **🥬 Flores y plantas** | Piezas básicas de eliminación | 玫瑰、向日葵、月季、烧汤花、指甲花、鸡冠花、木槿花、山茶花、牡丹花、茉莉花、紫藤花、蝴蝶兰、菊花、梅花、兰花、荷花、车前草、地黄、枸杞、狗尾草、蒲公英、牛筋草、云酱菜 |
| **🐜 Animales** | Piezas básicas de eliminación | 蚂蚁、蜜蜂、七星瓢虫、毛毛虫、蝉、马蜂、蟋蟀、蚂蚱、蜥蜴、老鼠、倾听、水蛭、青蛙、蛤蟆、虾、鱼、狐狸、松鼠、蝴蝶、螳螂、蜘蛛、萤火虫 |
| **🐓 Aves de corral/volátiles** | Depredadores de nivel medio | 鸡、鸭、鹅、鸽子、麻雀、喜鹊、燕子、乌鸦、猫头鹰、老鹰 |
| **🐕 Ganado/animales grandes** | Piezas de nivel alto | 猪、狗、牛、马、羊、兔子、猫、驴、骡子、骆驼 |
| **🌳 Árboles/naturaleza** | Obstáculos/piezas especiales | 松树、柳树、杨树、槐树、泡桐、梧桐、杉树、银杏、榆树、竹子、桦树、枫树 |
| **🔧 Herramientas agrícolas** | Accesorios de habilidad | 镰刀、锄头、水桶、锤子、耙子、簸箕、背篓、草帽、蓑衣、手电筒、石磙、架子车、自行车、斧头、扁担、犁、磨盘 |

---

### 二、 Ampliación de las reglas centrales (diseño de la «cadena de contención del ecosistema»)

La lógica de tus reglas es esencialmente **«eliminación dirigida»**. Sobre el match-3 clásico (tres iguales se eliminan), incrustamos el **«emparejamiento depredador/presa»**. Cuando el jugador junta al **depredador** con sus **presas** en una línea de tres (o una forma concreta), se dispara la eliminación avanzada.

Esta es la **matriz de contención completa** que te amplío (A contiene a B):

| Depredador (A) | Modo de contención | Presa (B) | Notas de reglas ampliadas |
| :--- | :--- | :--- | :--- |
| **Gallinas, patos, gansos** | Picotear / depredar | flores, verduras/frutas, insectos (hormigas/mariquitas/orugas) | Complemento: **no comen** cereales (las cosechas), porque el grano es demasiado duro y requiere eliminación aparte. |
| **Perro** | Mordisco | gallinas, patos, gansos, palomas | El perro no solo muerde aves de corral; complemento: **el perro también roe huesos (de cerdo/vaca/caballo)**, pero en el juego se simplifica y contiene a todas las aves de corral pequeñas y medianas. |
| **Cerdo** | Hozar / arrasar | árboles, flores, verduras/frutas, insectos, **todos los cultivos de cereales** | El cerdo es el destructor; complemento: el cerdo **no hoza contra el perro** (porque el perro muerde al cerdo), formando un ciclo de contención cerrado. |
| **Vacas, caballos** | Ramoneo / pisoteo | flores, **cultivos de cereales**, plantones de árboles frutales | Complemento: vacas y caballos son el gran herbívoro, contienen específicamente a los cultivos, pero no comen insectos ni carne. |
| **Elefante** | Supremacía absoluta (pisotear/azotar) | **todos los elementos excepto el propio elefante (incluidos cerdo, perro, vaca, caballo)** | El elefante es el poder máximo. Para equilibrar, complemento: el elefante **no** puede eliminar «herramientas» (accesorios), y su probabilidad de aparición en el tablero es muy baja (pieza rara). |
| **Hoz (accesorio)** | Cosechar | todos los cultivos de cereales, flores | Elimina de una vez toda la línea horizontal o vertical de plantas. |
| **Azada (accesorio)** | Romper | árboles, piedras (obstáculos) | Especializada en eliminar obstáculos de mucha vida. |

---

### 三、 Diseño de los mecanismos de juego (cómo se juega al «match-3»)

En la escena 3D de Three.js adoptamos el modo combinado de **«clic para intercambiar + juicio ecológico»**:

1. **Operación básica**: el jugador hace clic en dos piezas 3D adyacentes para intercambiarlas de posición.
2. **Lógica de juicio (clave)**:
    - **Eliminación del mismo tipo**: tras el intercambio, si en horizontal o vertical se forman **≥3 piezas iguales**, se ejecuta la eliminación básica (por ejemplo, tres manzanas).
    - **Eliminación ecológica (especial)**: tras el intercambio, si en horizontal o vertical se forma **«depredador + dos presas cualesquiera»** (p. ej.: gallina + insecto + insecto), **sin exigir que las tres sean iguales**, se dispara directamente la «animación de depredación», la gallina se come a los insectos, con puntos extra, y en esa celda se genera un **«abono/fertilizante»** (buff, la siguiente eliminación puntúa el doble).
    - **Privilegio del elefante**: el elefante alineado con **dos piezas diferentes cualesquiera** dispara el efecto de «acoso»: vacía directamente esas tres celdas, sin importar la especie.
3. **Reacción en cadena (Combo)**: tras eliminar piezas, las de arriba caen para rellenar. Si la caída genera una nueva «cadena de contención ecológica», se dispara automáticamente el combo (sin intervención del jugador), para una sensación satisfactoria.

---

### 四、 Escena Three.js y planificación visual (sin código)

Para que el match-3 en 3D tenga más cuerpo que el 2D, la planificación es la siguiente:

| Módulo | Elección técnica / diseño |
| :--- | :--- |
| **Ángulo de cámara** | **Vista ortográfica de 45 grados (OrthographicCamera)** o perspectiva fija. Que el tablero parezca un «maqueta de arena 3D», fácil de observar el apilamiento delante/detrás. Recomendado: vista fija 2.5D, sin controles orbitales (para evitar mareos). |
| **Disposición del tablero** | Cuadrícula **8x8**, pero cada celda tiene **variación de altura en el eje Y** (simulando lomas de campo). Las piezas se elevan con cilindros o columnas, con un disco de reflejo en la base. |
| **Modelado 3D** | Sin cargar FBX/GLTF externos complejos (mucho tráfico). **Todo con geometrías básicas de Three.js combinadas (Group)**:<br>- **Manzana**: esfera + tallo cilíndrico.<br>- **Gallina**: elipsoide (cuerpo) + cono (pico) + esfera (cabeza).<br>- **Elefante**: cubo grande (cuerpo) + cilindro estirado (trompa) + orejas laminares.<br>- **Árbol**: cono (copa) + cilindro (tronco).<br>Estilo **Low Poly** con **materiales de luz suave (MeshPhongMaterial)** y colores vivos. |
| **Iluminación y atmósfera** | Luz principal: direccional (simula el sol). Luz auxiliar: ambiental (ilumina las sombras). Contraluz: luz de área (delinea los bordes). Fondo con esfera de cielo degradado, acompañada de partículas flotantes (simulando polen o luciérnagas). |
| **Retroalimentación de interacción** | Uso de **Raycaster (detección por rayos)** para la selección con clic. Al hacer clic en una pieza, esta **rebota hacia arriba + contorno luminoso (OutlinePass)**; al intercambiar, **movimiento interpolado suave (TWEEN o GSAP)**; al eliminar, efectos de **desaparición con escala + explosión de partículas** (con PointsMaterial). |

---

### 五、 Ampliación de accesorios especiales y obstáculos

Para dar profundidad estratégica al juego, se añaden estos mecanismos:

1. **Obstáculos (inamovibles)**:
    - **Piedra**: ocupa la celda; hay que romperla con el accesorio «azada» o con tres eliminaciones normales adyacentes.
    - **Charco**: las piezas no pueden caer a través de él; hay que drenarlo con el accesorio «cubo».
2. **Cómo obtener las «herramientas agrícolas»**:
    - No aparecen directamente en el tablero. Cuando el jugador dispara **3 veces seguidas una «contención ecológica»**, la ranura de habilidades bajo la pantalla se carga y, al pulsarla, se libera la habilidad de herramienta correspondiente (como la hoz que barre una línea entera).

---

### 六、 Planificación de niveles y objetivos

| Tipo de nivel | Condición de victoria | Restricciones características |
| :--- | :--- | :--- |
| **Nivel cosecha** | En 20 pasos, eliminar **50 cereales/verduras-frutas**. | En el tablero aparecen muchas gallinas, patos y gansos que interfieren tu eliminación de plantas. |
| **Nivel ahuyentar** | En tiempo limitado, usar al **perro** para eliminar **15 gallinas/patos**. | En el tablero solo se refrescan aves de corral y perros, sin plantas. |
| **Nivel destructor** | Usar al **cerdo** para derribar **3 árboles grandes** (vida del árbol: 5 puntos). | El cerdo aparece con poca frecuencia; hay que reunir tres cerdos para disparar el socavado en área. |
| **Carnaval del elefante** | Usar al elefante para eliminar **30 piezas** cualesquiera. | Se regala un elefante al inicio; protégelo, no puede ser intercambiado. |

---

### 七、 Puntos destacados del resumen de la planificación

1. **Reglas coherentes**: la cadena alimentaria biológica (la gallina come insectos, el perro muerde a la gallina, el elefante es invencible) se transforma a la perfección en «condiciones de emparejamiento» del match-3, con más valor educativo y estratégico que un simple «match-3».
2. **Expresividad 3D**: aprovechando el motor físico de Three.js (o animaciones simuladas), las piezas tienen «caída por gravedad» y «rebotes elásticos», con una tridimensionalidad muy superior a los sprites 2D.
3. **Garantía de equilibrio**: como el elefante es demasiado fuerte, se establece como refresco raro (como máximo 1 en cada partida), no se puede generar por intercambio normal, y solo lo genera el sistema como recompensa tras 5 combos, para evitar desequilibrar el juego.

Esta planificación conserva la «facilidad de entrada» del match-3 clásico, incrusta el núcleo único del «ajedrez de animales ecológico» y es totalmente viable en la implementación con Three.js (combinación de geometría pura + shaders básicos). Puedes arrancar el desarrollo directamente con este plano. Si necesitas profundizar en detalles (como curvas de animación concretas o paletas de partículas), dímelo cuando quieras. 🐘🌾

