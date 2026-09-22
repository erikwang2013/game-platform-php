<!-- lang-nav -->

Languages: [中文](design.md) · [English](design.en.md) · [한국어](design.ko.md) · [Русский](design.ru.md) · [Deutsch](design.de.md) · [Français](design.fr.md) · **Español** · [Português](design.pt.md) · [हिन्दी](design.hi.md) · [العربية](design.ar.md) · [বাংলা](design.bn.md) · [Bahasa Indonesia](design.id.md) · [日本語](design.ja.md)

De acuerdo, como tu diseñador de juego y responsable técnico de 3D, te prepararé una planificación de diseño completa del《Match-3 en Three.js》. Esta propuesta no incluye código y se centra en la **ampliación de elementos**, la **matriz de reglas**, los **mecanismos de fusión de jugabilidad** y las **ideas de montaje de la escena Three.js**.

---

### 1. Ampliación de elementos del juego (diseño de la biblioteca de piezas)

Para enriquecer el tablero, partiendo de lo que aportaste, divido los elementos en **6 grandes facciones**, con un total de **24 piezas básicas** + **4 accesorios especiales**:

| Facción | Elementos incluidos | Notas complementarias |
| :--- | :--- | :--- |
| **🌾 Cultivos** | Piezas básicas de eliminación | arroz, trigo, maíz, sorgo, cebada, avena, centeno, mijo, sésamo, cacahuetes, algodón, colza, té, mijo amarillo, cebada perlada, trigo sarraceno, soja, judías mung, judías rojas, judías negras, habas, guisantes, batatas, patatas, ñames, taro, yuca |
| **🥬 Verduras** | Piezas básicas de eliminación | col, rábano, pepino, tomate, chile, berenjena, cebolleta, jengibre, ajo, lechuga, zanahoria, melón amargo, cilantro, cebollino, hojas de mostaza, apio, espinaca, coliflor, calabaza de invierno, calabaza, puerro |
| **🥬 Frutas** | Piezas básicas de eliminación | manzana, pera, melocotón, albaricoque, ciruela, fresa, sandía, uva, azufaifa agria, cereza enana china, azufaifa, nuez, almendra, higo, naranja, plátano, caqui, granada, kiwi, cereza |
| **🥬 Flores y plantas** | Piezas básicas de eliminación | rosa, girasol, rosa mensual, onagra, henna, cresta de gallo, hibisco, camelia, peonía, jazmín, glicinia, orquídea phalaenopsis, crisantemo, flor de ciruelo, orquídea, loto, llantén, rehmannia, baya de goji, cola de zorro, diente de león, pata de gallina, verdura de nube |
| **🐜 Animales** | Piezas básicas de eliminación | hormiga, abeja, mariquita de siete puntos, oruga, cigarra, avispón, grillo, saltamontes, lagarto, ratón, ciempiés, sanguijuela, rana, sapo, gamba, pez, zorro, ardilla, mariposa, mantis, araña, luciérnaga |
| **🐓 Aves de corral/volátiles** | Depredadores de nivel medio | gallina, pato, ganso, paloma, gorrión, urraca, golondrina, cuervo, búho, águila |
| **🐕 Ganado/animales grandes** | Piezas de nivel alto | cerdo, perro, vaca, caballo, oveja, conejo, gato, burro, mula, camello |
| **🌳 Árboles/naturaleza** | Obstáculos/piezas especiales | pino, sauce, álamo, sófora, paulonia, firmiana, abeto, ginkgo, olmo, bambú, abedul, arce |
| **🔧 Herramientas agrícolas** | Accesorios de habilidad | hoz, azada, cubo de agua, martillo, rastrillo, criba, mochila, sombrero de paja, capa de paja, linterna, rodillo de piedra, carreta, bicicleta, hacha, balancín, arado, piedra de molino |

---

### 2. Ampliación de las reglas centrales (diseño de la «cadena de contención del ecosistema»)

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

### 3. Diseño de los mecanismos de juego (cómo se juega al «match-3»)

En la escena 3D de Three.js adoptamos el modo combinado de **«clic para intercambiar + juicio ecológico»**:

1. **Operación básica**: el jugador hace clic en dos piezas 3D adyacentes para intercambiarlas de posición.
2. **Lógica de juicio (clave)**:
    - **Eliminación del mismo tipo**: tras el intercambio, si en horizontal o vertical se forman **≥3 piezas iguales**, se ejecuta la eliminación básica (por ejemplo, tres manzanas).
    - **Eliminación ecológica (especial)**: tras el intercambio, si en horizontal o vertical se forma **«depredador + dos presas cualesquiera»** (p. ej.: gallina + insecto + insecto), **sin exigir que las tres sean iguales**, se dispara directamente la «animación de depredación», la gallina se come a los insectos, con puntos extra, y en esa celda se genera un **«abono/fertilizante»** (buff, la siguiente eliminación puntúa el doble).
    - **Privilegio del elefante**: el elefante alineado con **dos piezas diferentes cualesquiera** dispara el efecto de «acoso»: vacía directamente esas tres celdas, sin importar la especie.
3. **Reacción en cadena (Combo)**: tras eliminar piezas, las de arriba caen para rellenar. Si la caída genera una nueva «cadena de contención ecológica», se dispara automáticamente el combo (sin intervención del jugador), para una sensación satisfactoria.

---

### 4. Escena Three.js y planificación visual (sin código)

Para que el match-3 en 3D tenga más cuerpo que el 2D, la planificación es la siguiente:

| Módulo | Elección técnica / diseño |
| :--- | :--- |
| **Ángulo de cámara** | **Vista ortográfica de 45 grados (OrthographicCamera)** o perspectiva fija. Que el tablero parezca un «maqueta de arena 3D», fácil de observar el apilamiento delante/detrás. Recomendado: vista fija 2.5D, sin controles orbitales (para evitar mareos). |
| **Disposición del tablero** | Cuadrícula **8x8**, pero cada celda tiene **variación de altura en el eje Y** (simulando lomas de campo). Las piezas se elevan con cilindros o columnas, con un disco de reflejo en la base. |
| **Modelado 3D** | Sin cargar FBX/GLTF externos complejos (mucho tráfico). **Todo con geometrías básicas de Three.js combinadas (Group)**:<br>- **Manzana**: esfera + tallo cilíndrico.<br>- **Gallina**: elipsoide (cuerpo) + cono (pico) + esfera (cabeza).<br>- **Elefante**: cubo grande (cuerpo) + cilindro estirado (trompa) + orejas laminares.<br>- **Árbol**: cono (copa) + cilindro (tronco).<br>Estilo **Low Poly** con **materiales de luz suave (MeshPhongMaterial)** y colores vivos. |
| **Iluminación y atmósfera** | Luz principal: direccional (simula el sol). Luz auxiliar: ambiental (ilumina las sombras). Contraluz: luz de área (delinea los bordes). Fondo con esfera de cielo degradado, acompañada de partículas flotantes (simulando polen o luciérnagas). |
| **Retroalimentación de interacción** | Uso de **Raycaster (detección por rayos)** para la selección con clic. Al hacer clic en una pieza, esta **rebota hacia arriba + contorno luminoso (OutlinePass)**; al intercambiar, **movimiento interpolado suave (TWEEN o GSAP)**; al eliminar, efectos de **desaparición con escala + explosión de partículas** (con PointsMaterial). |

---

### 5. Ampliación de accesorios especiales y obstáculos

Para dar profundidad estratégica al juego, se añaden estos mecanismos:

1. **Obstáculos (inamovibles)**:
    - **Piedra**: ocupa la celda; hay que romperla con el accesorio «azada» o con tres eliminaciones normales adyacentes.
    - **Charco**: las piezas no pueden caer a través de él; hay que drenarlo con el accesorio «cubo».
2. **Cómo obtener las «herramientas agrícolas»**:
    - No aparecen directamente en el tablero. Cuando el jugador dispara **3 veces seguidas una «contención ecológica»**, la ranura de habilidades bajo la pantalla se carga y, al pulsarla, se libera la habilidad de herramienta correspondiente (como la hoz que barre una línea entera).

---

### 6. Planificación de niveles y objetivos

| Tipo de nivel | Condición de victoria | Restricciones características |
| :--- | :--- | :--- |
| **Nivel cosecha** | En 20 pasos, eliminar **50 cereales/verduras-frutas**. | En el tablero aparecen muchas gallinas, patos y gansos que interfieren tu eliminación de plantas. |
| **Nivel ahuyentar** | En tiempo limitado, usar al **perro** para eliminar **15 gallinas/patos**. | En el tablero solo se refrescan aves de corral y perros, sin plantas. |
| **Nivel destructor** | Usar al **cerdo** para derribar **3 árboles grandes** (vida del árbol: 5 puntos). | El cerdo aparece con poca frecuencia; hay que reunir tres cerdos para disparar el socavado en área. |
| **Carnaval del elefante** | Usar al elefante para eliminar **30 piezas** cualesquiera. | Se regala un elefante al inicio; protégelo, no puede ser intercambiado. |

---

### 7. Puntos destacados del resumen de la planificación

1. **Reglas coherentes**: la cadena alimentaria biológica (la gallina come insectos, el perro muerde a la gallina, el elefante es invencible) se transforma a la perfección en «condiciones de emparejamiento» del match-3, con más valor educativo y estratégico que un simple «match-3».
2. **Expresividad 3D**: aprovechando el motor físico de Three.js (o animaciones simuladas), las piezas tienen «caída por gravedad» y «rebotes elásticos», con una tridimensionalidad muy superior a los sprites 2D.
3. **Garantía de equilibrio**: como el elefante es demasiado fuerte, se establece como refresco raro (como máximo 1 en cada partida), no se puede generar por intercambio normal, y solo lo genera el sistema como recompensa tras 5 combos, para evitar desequilibrar el juego.

Esta planificación conserva la «facilidad de entrada» del match-3 clásico, incrusta el núcleo único del «ajedrez de animales ecológico» y es totalmente viable en la implementación con Three.js (combinación de geometría pura + shaders básicos). Puedes arrancar el desarrollo directamente con este plano. Si necesitas profundizar en detalles (como curvas de animación concretas o paletas de partículas), dímelo cuando quieras. 🐘🌾

