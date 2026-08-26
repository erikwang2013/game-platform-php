# 田园消消乐 — Diseño funcional
<!-- lang-nav -->

Languages: [中文](functional-design.md) · [English](functional-design.en.md) · [한국어](functional-design.ko.md) · [Русский](functional-design.ru.md) · [Deutsch](functional-design.de.md) · [Français](functional-design.fr.md) · **Español** · [Português](functional-design.pt.md) · [हिन्दी](functional-design.hi.md) · [العربية](functional-design.ar.md) · [বাংলা](functional-design.bn.md) · [Bahasa Indonesia](functional-design.id.md) · [日本語](functional-design.ja.md)


> La especificación de lo que el jugador puede ver, operar y aceptar. La división técnica está en `architecture.md`; la visión de elementos en `design.md`; la planificación en `plan.md`.
>
> En una frase: intercambia piezas adyacentes sobre una maqueta 3D de campo, limpia el tablero con «tres iguales» o «el depredador se come a la presa», y cumple los objetivos del nivel.

---

## 1. Definición del producto

| Elemento | Contenido |
|----|------|
| Nombre | 田园消消乐 |
| Tipo | match-3 de 8×8 + contención del ecosistema |
| Perspectiva | maqueta ortográfica 2.5D fija, no rotable |
| Operación | clic en dos piezas adyacentes para intercambiar (solo arriba/abajo/izquierda/derecha) |
| Forma de plataforma | H5 propio, se abre desde el lobby de juegos con `launch` |
| Experiencia de éxito | dominar el match-3 al instante; sentir la subida de nivel de reglas en el primer «la gallina se come al insecto»; las caídas en cadena tienen ritmo |

**V1 no hace:** clasificación en tiempo real dentro de la partida, amigos espectadores, crianza de piezas, mundo abierto, modelos detallados GLTF, niveles personalizados por el jugador.

---

## 2. Flujo del jugador

```
En el lobby pulsar «Comenzar»
  → página de carga (lee la sesión)
  → elegir nivel (lista de cuatro niveles + saldo, la tarifa se muestra desde P4)
  → partida
       HUD: objetivo / pasos o cuenta atrás / puntuación / combo / ranura de habilidades
       Tablero: clic para seleccionar → clic en el adyacente para intercambiar
       Sin eliminación: rebota, no gasta paso
       Con eliminación: gasta 1 paso → animación de eliminación → caída → relleno → cadena automática
  → liquidación de victoria / derrota
  → siguiente nivel / reintentar / volver a la selección
```

Al entrar por primera vez en el «nivel cosecha» aparecen 3 avisos y luego desaparecen; se pueden saltar y no vuelven a salir (localStorage).

---

## 3. Bucle central

1. Mirar el objetivo (cuántas plantas / aves / árboles / celdas pisadas por el elefante faltan).
2. Buscar un trío del mismo tipo, o mover al depredador junto a dos presas.
3. Intercambiar → eliminar → cadena de caídas.
4. La eliminación ecológica deja fertilizante en la celda original; la siguiente eliminación en esa celda puntúa ×2.
5. Hacer 3 liquidaciones con ecológica seguidas, la ranura de habilidades se ilumina, usar la hoz/azada/cubo para desbloquear la situación.
6. Objetivo cumplido y con pasos/tiempo de sobra → victoria.

---

## 4. Interfaz

| Interfaz | Elementos | Comportamiento |
|------|------|------|
| Carga | nombre del juego, progreso | si la sesión es inválida, avisar de volver al lobby |
| Selección de nivel | cuatro tarjetas de nivel: nombre, resumen del objetivo, desbloqueado o no | V1 abre los cuatro niveles; P4 muestra la cuota de entrada |
| HUD de partida, arriba | nombre del nivel, barra de progreso del objetivo, pasos restantes o cuenta atrás, puntuación, combo | la cuenta atrás avanza por segundos; se congela con la pausa |
| HUD de partida, abajo | ranura de habilidades (máx. 2), pausa | sin carga, la ranura se ve gris |
| Tablero | parcelas 8×8 + piezas | al seleccionar, rebote + contorno; las celdas ilegales sin contorno |
| Pausa | continuar / reiniciar / abandonar | reiniciar gasta un intento; abandonar es liquidación de derrota |
| Victoria | puntuación, pasos restantes, si se entrega premio (P4) | siguiente nivel / volver a la selección |
| Derrota | motivo (pasos/tiempo), cuánto falta del objetivo | reintentar / volver a la selección |
| Saldo insuficiente | texto + ir a recargar | solo P4 |

Teclado (P5): las flechas cambian la selección, Enter intercambia con la celda en la dirección de la selección. V1 solo ratón/táctil.

---

## 5. Reglas de operación (perspectiva del jugador)

- Solo se pueden intercambiar piezas **ortogonalmente adyacentes** y en las que ambos lados sean móviles.
- Las celdas de piedra, árbol y charco no pueden ser objetivo de intercambio. El elefante bloqueado no puede intercambiarse (las presas se mueven hacia él).
- Si tras el intercambio no hay «trío válido» en horizontal ni vertical → se vuelve atrás, **sin gastar paso ni tiempo**.
- Si hay trío válido → gasta 1 paso (en los niveles con límite de tiempo no se gastan pasos, solo corre el reloj).
- Solo se acepta el siguiente clic cuando toda la cadena ha terminado de reproducirse; los clics en el tablero durante la cadena no valen.
- Los tríos diagonales no cuentan. Las formas L/T que se cruzan solo eliminan cada celda una vez.

---

## 6. Las tres eliminaciones (funcional)

Prioridad: **elefante > ecológica > mismo tipo**. Una línea solo puntúa una vez, con la regla de mayor prioridad.

### 6.1 Mismo tipo

Tres o más **de la misma especie** en línea. Ej.: manzana-manzana-manzana.

| Longitud | Resultado que ve el jugador |
|------|----------------|
| 3 | se encoge y desaparece, puntuación base |
| 4 | desaparece, en la celda central aparece fertilizante |
| 5+ | desaparece, la ranura de habilidades gana +1 de carga (limitado por las habilidades permitidas en el nivel) |

### 6.2 Ecológica (depredación)

En una línea hay **exactamente 1 depredador** y el resto son todas sus presas; las presas no tienen que ser del mismo tipo. Ej.: gallina-hormiga-mariquita.

| Depredador | Puede comer | No puede comer |
|--------|------|--------|
| Gallina, pato, ganso | flores, verduras, frutas, insectos | cereales |
| Perro | aves de corral: gallina, pato, ganso, paloma, etc. | plantas, insectos |
| Cerdo | árboles, flores, verduras, frutas, insectos, cereales | perro |
| Vaca, caballo | flores, cereales, plantones | insectos, carne |
| Elefante | ver 6.3 | obstáculos, herramientas |

El jugador ve: animación de depredación → las tres celdas se vacían (en V1 el depredador también sale) → en la celda original del depredador queda fertilizante.

### 6.3 Elefante

Una línea con 1 elefante + otras dos celdas con piezas eliminables cualesquiera → las tres celdas se vacían, sin importar la facción. Como máximo 1 elefante en el tablero. No se «fabrica» con intercambios normales; con 5 combos el sistema lo hace caer en la celda vacía superior, o el nivel lo coloca al inicio.

---

## 7. Enciclopedia de salida en V1 (no son las 100 especies de la planificación)

Todas las especies de la planificación se conservan como datos de enciclopedia, pero **en las partidas de V1 solo se refrescan las siguientes**, para garantizar que se entiendan y se puedan eliminar todas.

| Especie | Facción | Niveles donde aparece | Reconocimiento del jugador |
|------|------|----------|----------|
| 小麦 wheat | cereal | cosecha, destructor, carnaval | espiga dorada |
| 水稻 rice | cereal | cosecha | espiga verde |
| 玉米 corn | cereal | cosecha | mazorca amarilla |
| 白菜 cabbage | verdura | cosecha | bola de hojas verde clara |
| 西红柿 tomato | verdura | cosecha | bola roja |
| 苹果 apple | fruta | cosecha, destructor, carnaval | bola roja + tallo |
| 玫瑰 rose | flor | destructor | pétalos rojos |
| 蚂蚁 ant | insecto | cosecha (peso bajo) | negro pequeño |
| 瓢虫 ladybug | insecto | cosecha | puntos rojos |
| 鸡 hen | aves de corral | cosecha, ahuyentar, carnaval | elipse + pico |
| 鸭 duck | aves de corral | cosecha, ahuyentar | pico aplanado |
| 鹅 goose | aves de corral | ahuyentar | cuello largo |
| 鸽 pigeon | aves de corral | ahuyentar | gris |
| 狗 dog | ganado | ahuyentar, carnaval | cuatro patas |
| 猪 pig | ganado | destructor, carnaval | elipse rosa |
| 松树 pine | árbol/obstáculo | destructor | copa cónica, no intercambiable |
| 象 elephant | ápice | carnaval; en otros niveles, recompensa de 5 combos | cubo grande + trompa |

Las herramientas agrícolas (hoz, azada, cubo) **no entran al tablero**, solo están en el HUD. Las demás herramientas de la planificación no salen en V1.

---

## 8. Especificaciones de los niveles

La victoria/derrota se liquida **cuando termina toda la animación de la cadena**.

### 8.1 Nivel cosecha

- Pool: 小麦, 水稻, 玉米, 白菜, 西红柿, 苹果, 鸡, 鸭; hormigas/mariquitas con peso bajo.
- Victoria: eliminar **50** roles plant en 20 pasos (cereales+verduras+frutas+flores). Las aves eliminadas no cuentan.
- Derrota: pasos en 0 y objetivo sin cumplir.
- Habilidades: hoz (usable tras cargar).
- Tutorial: ①clic en piezas adyacentes para intercambiar ②tres iguales se eliminan ③la gallina puede comerse dos insectos/verduras/frutas de al lado, pero no el trigo.

### 8.2 Nivel ahuyentar

- Pool: 鸡, 鸭, 鹅, 鸽, 狗. Sin plantas.
- Victoria: en **90 segundos**, eliminar 15 aves de corral con la **eliminación ecológica del perro**.
- Derrota: timeout.
- **Eliminar tres gallinas por mismo tipo no cuenta para el objetivo** (hay que completar la ecológica del perro comiendo aves).
- Habilidades: ninguna. La pausa congela el temporizador.

### 8.3 Nivel destructor de árboles

- Pool: 小麦, 苹果, 玫瑰, 猪 (peso bajo). 3 pinos fijos, HP=5, no intercambiables y la caída no los atraviesa.
- Victoria: el HP de los 3 árboles llega a cero.
- Derrota: se agotan los 25 pasos.
- Daño a los árboles: ecológica del cerdo (el árbol está en el run de presas) -2; tres cerdos en línea disparan el **socavado 3×3** (árboles en el radio -5); azada sobre un árbol -3; match-3 normal adyacente -1.
- Habilidades: azada.

### 8.4 Carnaval del elefante

- Pool: 小麦, 苹果, 鸡, 狗, 猪. Al inicio, 1 elefante bloqueado cerca del centro.
- Victoria: eliminar 30 celdas con la **regla del elefante** (el mismo tipo/la ecológica no cuentan para este objetivo).
- Derrota: se agotan los 30 pasos.
- No se refresca un segundo elefante. El jugador intercambia las presas hasta colocarlas a los lados, arriba o abajo del elefante.
- Habilidades: ninguna.

---

## 9. Obstáculos, fertilizante y habilidades

| Función | Percepción del jugador | Regla |
|------|----------|------|
| Piedra | gris, no clicable | HP3; una eliminación adyacente -1; la azada la rompe de una vez |
| Árbol | modelo alto, no clicable | ver destructor de árboles |
| Charco | la celda refleja | la pieza de arriba se detiene en la celda sobre el charco; al drenarlo con el cubo, la caída se restablece |
| Fertilizante | mancha oscura en la celda | la siguiente vez que esa celda se elimina puntúa ×2, luego desaparece |
| Hoz | icono en la barra inferior | elegir una fila o columna, solo limpia plantas, no gasta paso, gasta 1 carga |
| Azada | icono en la barra inferior | clic en 1 piedra o árbol |
| Cubo | icono en la barra inferior | clic en 1 celda de charco |

Carga: en la liquidación completa provocada por una operación del jugador, si aparece alguna eliminación ecológica, el contador +1; al llegar a 3 se obtiene 1 ranura, máx. 2. Un 5 en línea del mismo tipo también da +1 de ranura (comparte ranura con la carga ecológica).

En V1, el nivel cosecha no tiene piedras ni charcos; el destructor no tiene charcos. Los charcos quedan en la enciclopedia y no bloquean la línea principal de los cuatro niveles.

---

## 10. Puntuación y economía

```
Mismo tipo   10 × eliminadas × combo × fertilizante
Ecológica    25 × eliminadas × combo × fertilizante
Elefante     40 × eliminadas × combo
Limpieza de habilidad  8 × eliminadas
Romper obstáculo   20 × rotas
```

combo: la primera eliminación de esta operación es 1, cada ronda de cadena extra +1; la siguiente operación manual del jugador lo devuelve a 1.

**Billetera P4:**

- Al empezar el nivel se cobra la cuota de entrada (por defecto 1 moneda de juego por nivel).
- Al completar, se liquidan estrellas: recursos restantes ≥50% tres estrellas, ≥20% dos estrellas, si no una; recompensa 2 / 3 / 5 (configurable).
- Si se falla no se devuelve la cuota de entrada.
- Si se sale sin haber movido ni una ficha → reembolso.
- Con saldo insuficiente no se puede empezar la partida.

V1 (P0–P3) no cobra; se puede jugar directamente en local.

---

## 11. Lista de funcionalidades y aceptación

| ID | Funcionalidad | Aceptación | Fase |
|----|------|------|------|
| F01 | intercambio por clic en 8×8 | los adyacentes se intercambian, los diagonales no, sin eliminación rebota | P0 |
| F02 | match-3 del mismo tipo + gravedad + relleno | se eliminan tres trigos, caen los de arriba, la parte superior rellena con piezas nuevas | P0 |
| F03 | cadena | tras la caída se vuelve a eliminar solo, el número de combo +1 | P0 |
| F04 | selección de los cuatro niveles | clic entra en el HUD del objetivo correspondiente | P1 |
| F05 | objetivo cosecha | 50 plantas en 20 pasos, el contador solo cuenta plantas | P1 |
| F06 | eliminación ecológica | gallina + dos insectos se eliminan; gallina + dos trigos no se eliminan | P2 |
| F07 | fertilizante | tras la ecológica, esa celda puntúa el doble una vez más | P2 |
| F08 | objetivo ahuyentar | las gallinas por mismo tipo no cuentan; el perro comiendo aves cuenta; 90s | P2 |
| F09 | árboles y azada | los árboles no se intercambian; la azada/el cerdo los derriban | P3 |
| F10 | tres cerdos 3×3 | tres cerdos en línea, los árboles en el radio se rompen directo | P3 |
| F11 | hoz | limpia una línea de plantas, no gasta paso | P3 |
| F12 | elefante bloqueado | el elefante no se puede intercambiar; elefante + dos piezas vacían tres celdas | P4 |
| F13 | objetivo carnaval | solo la regla del elefante cuenta para las 30 | P4 |
| F14 | cuota de entrada/premio | conciliación de saldo, la liquidación repetida no entrega dos veces | P4 |
| F15 | tutorial | tres avisos, saltables para siempre | P1 |
| F16 | pausa/reiniciar/abandonar | el temporizador se congela; abandonar cuenta como derrota | P1 |
| F17 | desactivar partículas en gama baja | con el interruptor, los FPS estables y jugable | P5 |

---

## 12. Límites (hay que fijarlos por escrito)

1. La enciclopedia puede ser grande, **pero los tipos refrescados por nivel ≤ 8**.
2. Las herramientas agrícolas no entran al tablero.
3. La gallina no come cereales: una línea «gallina+trigo+trigo» no es ecológica ni del mismo tipo, rebota.
4. El perro no come plantas; el cerdo no hoza contra el perro.
5. Como máximo 1 elefante a la vez en el tablero.
6. Durante la reproducción de la cadena, las entradas se descartan.
7. La victoria/derrota no se juzga a mitad de animación.
8. En V1 el depredador sale con las presas.
9. El nivel ahuyentar tiene límite de 90 segundos, sin pasos.
10. Los charcos no entran en la línea principal de los cuatro niveles.
