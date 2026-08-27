# 田园消消乐 — Arquitectura técnica
<!-- lang-nav -->

Languages: [中文](architecture.md) · [English](architecture.en.md) · [한국어](architecture.ko.md) · [Русский](architecture.ru.md) · [Deutsch](architecture.de.md) · [Français](architecture.fr.md) · **Español** · [Português](architecture.pt.md) · [हिन्दी](architecture.hi.md) · [العربية](architecture.ar.md) · [বাংলা](architecture.bn.md) · [Bahasa Indonesia](architecture.id.md) · [日本語](architecture.ja.md)


> Las funcionalidades para jugadores y los criterios de aceptación están en `functional-design.md`; la planificación en `plan.md`; la visión temática en `design.md`.
>
> Este documento solo responde a cómo dividir los módulos, cómo conectar con la plataforma y en qué capa se calculan las reglas. No escribe código de implementación.
>
> Posicionamiento del producto: H5 propio (`game.type = self`), match-3 de 8×8 en tablero de arena + cadena de contención del ecosistema, Three.js low-poly 2.5D.

---

## 0. Decisiones de arquitectura frente a la planificación

La planificación es la visión de la jugabilidad; estas decisiones resuelven la contradicción entre «jugable, testeable, conectable con la billetera».

| ID | Decisión | Motivo |
|----|------|------|
| D1 | **La enciclopedia ≠ las piezas del tablero**. Las 100+ especies son enciclopedia y apariencia; el pool de refresco de cada nivel solo extrae **5–8 especies** | Con docenas de especies a la vez en 8×8 es casi imposible formar eliminaciones |
| D2 | El emparejamiento se divide en dos capas: **mismo tipo por `speciesId`**, ecológico por `role` + tabla de contención | La planificación exige a la vez «tres manzanas» y «gallina + insecto + insecto» |
| D3 | Prioridad de reglas en un mismo segmento de línea: **elefante > ecológico > mismo tipo**; mutuamente excluyentes, sin puntuación doble | Evitar que una línea puntúe dos veces a la vez |
| D4 | **Las herramientas agrícolas no entran al tablero**, solo en la ranura de habilidades del HUD; rocas/charcos/árboles son obstáculos, no intercambiables | La sección 5 de la planificación entra en conflicto con la biblioteca de piezas; se rige por habilidades + obstáculos |
| D5 | **La lógica de dominio tiene cero dependencia de Three.js**, funciones puras + snapshots; la capa de presentación solo se suscribe a eventos | Las reglas se pueden testear unitariamente, reproducir y, en el futuro, validar en el servidor |
| D6 | El `session_id` de inicio deriva una **semilla RNG determinista**; caídas/refrescos usan siempre ese RNG | La misma semilla permite repasar la partida; deja la puerta abierta al anti-cheat |
| D7 | Sin motor de físicas. Los desplazamientos/saltos/eliminaciones usan easing, sin introducir Cannon/Rapier | La planificación ya escribió «animación simulada»; la física no aporta nada a un juego de cuadrícula |
| D8 | Cámara **ortográfica 2.5D con posición fija**, controles de órbita desactivados | Coherente con la planificación; evita errores de operación y mareos |
| D9 | Las especies comparten **plantillas geométricas por facción + color/accesorios**, sin modelar cada cultivo por separado | Tráfico y plazo; la diferencia visual se logra con paleta y una pieza característica |
| D10 | La entrada al nivel usa `SelfProvider::bet`, al completar `settle`, si se falla a mitad no se devuelve la cuota; sin dar el primer paso se puede `refund` | Alineado con la billetera de la plataforma y la idempotencia de round |

---

## 1. Contexto del sistema

```
Flutter / HarmonyOS / PC Web
        │  POST /api/game/launch { game_id }
        ▼
service/ (webman :8788)
  GameController::launch  → session_id + seed + api_endpoint
  SelfProvider            → bet / settle / refund / getBalance
  GamePlayLog + EventBus  → game.played / logros / VIP
        │  abrir api_endpoint?session_id=&token=
        ▼
game/xiaoxiaole/  (recursos estáticos, Nginx)
  Vite + TypeScript + Three.js
  Motor de dominio ──eventos──► renderizado / HUD
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

El juego es un **frontend estático**; la sesión autoritativa y el dinero están en `service/`. El cliente mantiene el estado del tablero; el servidor mantiene el saldo y la idempotencia del round. En la primera fase no se hace validación servidor a servidor por paso, pero la capa de dominio debe ser determinista para que en la segunda fase se pueda enviar `seed + secuencia de operaciones` al servidor para recalcular.

---

## 2. Capas del cliente

De arriba abajo, prohibido invertir dependencias entre capas (`render` no debe ser importado por `domain`).

```
app/          ensamblado, máquina de estados, ciclo de vida del nivel
hud/          overlay HTML: puntuación, pasos, objetivo, habilidades, resultado
platform/     parámetros de launch, billetera, play-log, feature flags
render/       Three.js: escena, tablero, cuadrícula de piezas, entrada, VFX
runtime/      bus de comandos, cola de animaciones, repetición
domain/       tablero, emparejamiento, contención, gravedad, puntuación, catálogo, reglas de nivel
config/       tabla de contención, pesos de refresco, recetas geométricas, JSON de niveles
```

**Bucle principal (no calcular reglas dentro de `requestAnimationFrame`)**: entrada → comando → liquidación síncrona del dominio (un solo swap calcula todas las cadenas y produce una lista de eventos) → el runtime encola las animaciones por evento → solo se acepta la siguiente entrada cuando terminan las animaciones.

Así «una lógica por frame, varias de presentación», y el combo no compite con el clic por el estado.

---

## 3. Estructura de directorios (sugerida)

```
game/xiaoxiaole/
├── design.md
├── architecture.md          ← este documento
├── package.json             # vite, three, gsap, vitest, typescript
├── index.html
├── src/
│   ├── main.ts              # lee la URL, inicia GameApp
│   ├── app/
│   │   ├── GameApp.ts
│   │   └── GameStateMachine.ts
│   ├── domain/
│   │   ├── catalog/         # PieceDef, Faction, Role
│   │   ├── board/           # Grid 8×8, Cell, PieceInstance
│   │   ├── match/           # MatchDetector (mismo tipo / ecológico / elefante)
│   │   ├── eco/             # RestraintTable
│   │   ├── gravity/         # caída, bloqueo por charco, refresco
│   │   ├── score/           # puntuación, multiplicador de fertilizante
│   │   ├── level/           # LevelDef, Objective, Win/Lose
│   │   └── rng/             # mulberry32 con semilla
│   ├── runtime/
│   │   ├── CommandBus.ts    # Select, Swap, UseSkill, Quit
│   │   ├── EventLog.ts      # eventos serializables, para repetición
│   │   └── AnimationQueue.ts
│   ├── render/
│   │   ├── SceneRoot.ts
│   │   ├── CameraRig.ts
│   │   ├── BoardView.ts
│   │   ├── PieceFactory.ts  # geometría por plantilla
│   │   ├── InputRaycaster.ts
│   │   └── vfx/
│   ├── hud/
│   ├── platform/
│   │   ├── ApiClient.ts
│   │   └── Session.ts
│   └── levels/*.json
└── tests/domain/            # sin WebGL
```

Ningún archivo supera las 500 líneas. Si `MatchDetector` y `PieceFactory` crecen, se dividen por tipo de regla / plantilla de facción.

---

## 4. Modelo de dominio

### 4.1 Definición de piezas (enciclopedia)

```
PieceDef
  id            speciesId        p. ej. wheat, hen, elephant
  faction       Faction          crop | veg | fruit | flora | insect | poultry | livestock | tree | tool | obstacle | apex
  role          Role             plant | prey | predator_mid | predator_high | apex | obstacle | skill
  tags[]                         crop, edible_by_poultry, tree_seedling, bone …
  rarity        common | rare | legendary
  template      GeometryTemplate nombre de la plantilla geométrica
  tint          RGB              paleta dentro de la plantilla
  accessory     optional         pico, pétalos, trompa, etc. como piezas distintivas
```

Todos los cultivos/verduras/frutas/flores/insectos/aves de corral/ganado/árboles de la planificación entran en la enciclopedia; **tool no se genera en las celdas**. El elefante tiene `rarity = legendary`, `role = apex`.

### 4.2 Celdas y tablero

```
Cell
  q, r               columna, fila (0–7)
  height             relieve del terreno (solo renderizado, no participa en reglas)
  occupant           PieceInstance | null
  overlay            none | fertilizer | puddle | stone(hp) | tree(hp)
  locked             bool          niveles de carnaval del elefante: el elefante no puede intercambiarse

PieceInstance
  uid, speciesId, def
  special            none | fertilizer_token
```

- **Piedra / Árbol**: ocupan la celda, no se intercambian, la caída no los atraviesa. HP según el nivel.
- **Charco**: se superpone a la celda, bloquea el paso de la gravedad por esa celda (la pieza de arriba se detiene en la celda sobre el charco).
- **Fertilizante**: queda en la celda tras una eliminación ecológica; la siguiente eliminación que pase por esa celda puntúa ×2 y luego desaparece.

### 4.3 Pool de refresco (nivel)

```
SpawnPool
  speciesIds[]       5–8
  weights[]          alineado con species
  maxApex            por defecto 1
  apexUnlock         generado por el sistema cuando combo >= 5; prohibido «generar por intercambio»
```

El nivel solo extrae piezas de este pool con el `rng`. Por grande que sea la enciclopedia, la entropía del tablero se mantiene controlada.

---

## 5. Motor de reglas central (diseño funcional)

### 5.1 Operaciones

1. Clic en la pieza A → selección (rebote + contorno).
2. Clic en la celda ortogonal adyacente B → intentar intercambio (prohibido el intercambio diagonal).
3. Clic en una celda no adyacente / vacía → cambiar selección o cancelar.
4. Si tras el intercambio **no hay ninguna coincidencia válida**, se reproduce la vuelta atrás sin gastar paso.
5. Si hay coincidencia, se gasta 1 paso y se entra en la liquidación.

Las celdas de obstáculo no pueden elegirse como objetivo de intercambio. Las celdas bloqueadas (regla de nivel) igual.

### 5.2 Escaneo de segmentos de línea

Para cada tablero tras un swap:

- Celdas consecutivas horizontales o verticales, con longitud ≥ 3, forman un **run**.
- Un run solo aplica una regla (D3).
- Varios runs pueden intersecarse (forma clásica de L/T); las celdas de intersección solo se eliminan una vez.

### 5.3 Eliminación del mismo tipo

Dentro de un run, todos los `speciesId` son iguales, y no es obstáculo ni privilegio de elefante (se trata aparte).

- 3 en línea: puntuación base.
- 4 en línea: puntuación extra, y en la celda central cae **fertilizante** (mismo overlay que el fertilizante ecológico).
- 5 en línea: puntuación extra, la ranura de habilidades gana +1 de carga (ver 5.7).

### 5.4 Eliminación ecológica (cadena de contención)

Condición: **exactamente 1 depredador + el resto son todas presas de ese depredador** (3 celdas = 1+2). Las presas no tienen que ser del mismo tipo.

| Depredador | Coincidencia de presas |
|--------|----------|
| Gallina, pato, ganso | faction ∈ {flora, veg, fruit, insect}; **excluye crop (cereales)** |
| Perro | faction = poultry (gallinas, patos, gansos, palomas, etc.) |
| Cerdo | faction ∈ {tree, flora, veg, fruit, insect, crop}; **excluye al perro** |
| Vaca, caballo | faction ∈ {flora, crop} o tag `tree_seedling`; excluye insectos y carne |
| Elefante | ver 5.5, no pasa por esta tabla |

Efectos:

- Eliminación completa del segmento, con animación de depredación (el depredador primero «come», luego sale con las presas, o el depredador se queda —**en la primera fase todo el segmento sale**, para no romper el equilibrio de caídas con depredadores residuales; si la experiencia resulta floja, en la segunda fase se cambia al interruptor «el depredador se queda»).
- La puntuación ecológica base es mayor que la del mismo tipo.
- En la celda original del depredador se genera **fertilizante**.
- `ecoChainStreak += 1`; en una misma cadena, varias eliminaciones ecológicas solo añaden un nodo de conteo de streak (al terminar toda la resolución +1, para evitar llenar la habilidad con una sola caída).

**La gallina no come cereales**: los cultivos y las gallinas pueden estar en el mismo tablero, pero no pueden formar un run ecológico; los cultivos solo se eliminan por mismo tipo.

### 5.5 Elefante

- Como máximo 1 elefante en todo el tablero; peso de refresco muy bajo; solo se genera como recompensa con `combo >= 5`, o lo coloca `initialPieces` del nivel.
- Un run que contiene 1 elefante + 2 piezas cualesquiera que no sean herramientas ni obstáculos → se vacían esas 3 celdas (pueden ser de facciones distintas).
- El elefante **no** puede eliminar herramientas (no están en el tablero, se cumple de forma natural) ni obstáculos (los obstáculos no entran en los runs).
- Nivel «carnaval del elefante»: 1 elefante al inicio, `locked = true`, no se puede mover de su celda; las presas se intercambian hasta colocarse junto a él para formar runs.

### 5.6 Cadenas, gravedad, refresco

```
resolve:
  detectar runs
  si ninguno → idle
  aplicar puntuaciones, overlays, hp a obstáculos adyacentes
  emitir Clear
  gravity: cada columna compacta de abajo hacia arriba, saltando obstáculos sólidos stone/tree; puddle bloquea el paso
  refill: rellenar desde la parte superior de la columna con el SpawnPool (limitado por maxApex)
  combo++
  ir a detectar
```

Las eliminaciones adyacentes restan HP a los obstáculos: cada eliminación adyacente de mismo tipo/ecológica resta -1 a la piedra, con HP=0 se rompe; al árbol por defecto solo le resta HP la **azada**, el nivel «tres cerdos que socavan en área» o la ecológica del cerdo (las presas incluyen árboles). En el nivel destructor de árboles, los árboles tienen HP=5.

Habilidad del cubo: elegir una celda de charco → se limpia el overlay y esa columna recibe una gravedad de inmediato.

### 5.7 Ranura de habilidades (herramientas agrícolas)

| Habilidad | Desbloqueo | Efecto |
|------|------|------|
| Hoz | 3 resolves consecutivos con ecológica | Elegir una fila o columna, limpia todos los **roles plant** de esa línea (crop/veg/fruit/flora), sin gastar paso, consume carga |
| Azada | igual que arriba, o preinstalada en el nivel | Clic en piedra/árbol, HP directo a 0 o -3 (config del nivel) |
| Cubo | preinstalado en el nivel o por carga | Drena el charco de una celda |

Regla de carga: `ecoResolveCount` llega a 3 → la ranura gana +1, el contador se reinicia. Límite de ranuras: 2. La hoz/azada/cubo aparecen según `allowedSkills[]` del nivel.

### 5.8 Puntuación

```
same_3     = 10 * n * combo * fertilizerMul
eco        = 25 * n * combo * fertilizerMul
elephant   = 40 * n * combo
skill_clear= 8  * n
obstacle   = 20 * brokenCount
```

`combo` empieza en 1, cada cadena +1, y se reinicia en el siguiente swap manual del jugador. El fertilizante solo actúa en «la vez en que se elimina esa celda».

---

## 6. Funcionalidad de los niveles

| Nivel | Pool | Victoria | Derrota | Particularidad |
|------|----|------|------|------|
| Cosecha | crop/veg/fruit + poultry con peso alto | eliminar 50 plant en 20 pasos | se agotan los pasos | gallinas/patos/gansos interfieren la eliminación por mismo tipo de las plantas |
| Ahuyentar | poultry + dog, sin plantas | en tiempo limitado, usar la ecológica del perro para eliminar 15 gallinas/patos | timeout | la eliminación por mismo tipo de aves de corral no cuenta para el objetivo, solo la ecológica |
| Destructor de árboles | plantas + pocos pig + 3 árboles (HP5) | los cerdos derriban los 3 árboles | se agotan los pasos | tres cerdos en línea disparan el **socavado 3×3** (regla de nivel, no global) |
| Carnaval del elefante | pool mixto + elefante bloqueado al inicio | eliminar 30 piezas con la regla del elefante | el elefante se mueve anómalamente (no debería ocurrir) o se agotan los pasos | proteger al elefante; el sistema no genera un segundo |

HUD común: progreso del objetivo, pasos o cuenta atrás, combo, ranura de habilidades, pausa/salir.

La victoria/derrota se liquida al terminar una resolución (incluidas todas las animaciones de cadena), para evitar juicios erróneos a mitad de animación.

---

## 7. Capa de presentación Three.js

| Módulo | Responsabilidad |
|------|------|
| SceneRoot | WebGLRenderer, mapeo tonal, resize, dpr máx. 2 |
| CameraRig | OrthographicCamera, inclinación ~45°, lookAt al centro del tablero, prohibido OrbitControls |
| Lights | Directional (sol) + Hemisphere (ambiente) + rim suave; sin sombras en tiempo real o solo el tablero recibe shadow de baja resolución |
| BoardView | parcelas 8×8; el relieve Y usa un mapa de alturas perlin prehorneado (las celdas lógicas siguen planas) |
| PieceFactory | compone Group según `template`: esfera/cilindro/cono/cubo; MeshPhongMaterial; object pool |
| InputRaycaster | solo acierta piezas mesh en `Idle/Selected` |
| VFX | contorno de selección (anillo brillante dibujado a mano; en la primera fase sin OutlinePass a pantalla completa); swap con GSAP; eliminación con escala + Points de partículas; polen/luciérnagas con pocos Points en bucle |
| HUD | DOM, fuera de WebGL, para facilitar i18n y accesibilidad |

Plantillas geométricas (D9): `grain` `produce` `fruit` `flower` `bug` `bird` `beast` `tree` `apex` `rock`. La enciclopedia solo cambia tint y accessory.

Presupuesto de rendimiento: 64 piezas + parcelas < 200 draw calls (mergear parcelas en lo posible); partículas < 400; en dispositivos de gama baja se desactivan partículas y relieve.

---

## 8. Máquina de estados

```
Boot → Title → Playing
Subestados de Playing:
  Idle → Selected → SwapAnim → ResolveLogic → ClearAnim → GravityAnim → RefillAnim
       ↺ si siguen existiendo coincidencias, volver a ResolveLogic (combo)
       → Idle
  Playing → SkillTargeting → SkillAnim → ResolveLogic
  Playing → Won | Lost → Result → Title | next
  Playing → Paused → Playing
```

Las entradas ilegales se descartan fuera de Idle/Selected/SkillTargeting.

**Comandos**: `Select` `Swap` `UseSkill` `Pause` `Quit` `AckResult`
**Eventos** (escritos en EventLog): `Swapped` `RejectedSwap` `Matches` `Cleared` `Fell` `Refilled` `Combo` `SkillUsed` `Won` `Lost`

---

## 9. Integración con la plataforma

El contrato completo de interfaces (launch / balance / bet / settle / refund / play-log / feature flags) está en **[api.md](api.md)**. Puntos clave:

- Inicio: `POST /api/game/launch` devuelve `session_id, api_endpoint, type=self`, y se abre `api_endpoint?session_id=&token=`.
- Billetera: `SelfProvider::bet/settle/refund`, con `round_id = session_id + ':' + levelId + ':' + attempt`; el dominio usa `seed = hash(session_id + round_id)`.
- Feature flags: `xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet`; desactivados, el nivel se degrada a match-3 puro del mismo tipo.
- Seguridad: en la primera fase el cliente es autoritativo para el tablero y el servidor para la billetera, cada round solo hace bet/settle una vez; en la segunda fase se sube la secuencia de operaciones para que el servidor recalcule.

---

## 10. Requisitos no funcionales

| Elemento | Métrica |
|----|------|
| Primer pantallazo | low-poly + sin GLTF, objetivo interactivo en 3s (incluido gzip de Vite) |
| FPS | 60fps en escritorio; en gráficas integradas se puede desactivar VFX |
| Pruebas | cobertura unitaria de `domain/**` para emparejamiento/gravedad/contención/victoria-derrota; no se prueba WebGL |
| i18n | textos del HUD con key, siguiendo el middleware `Language` de la plataforma |
| Accesibilidad | selección con flechas de teclado + Enter para intercambiar (segunda fase); daltonismo: plantillas de forma antes que color plano |
| Volumen | sin FBX; three + gsap gzip, objetivo < 250KB de código |

---

## 11. Fases

| Fase | Alcance | Aceptación |
|----|------|------|
| P0 | match-3 del mismo tipo, 8×8, intercambio/gravedad/refresco, escena ortográfica, 3 piezas plantilla | se puede jugar una partida sin objetivo |
| P1 | enciclopedia + SpawnPool + HUD de objetivo/pasos de los cuatro niveles | el nivel Cosecha se puede completar |
| P2 | tabla de contención + eliminación ecológica + fertilizante + combo | gallina + dos insectos se pueden eliminar; los cereales no los elimina la gallina |
| P3 | piedra/árbol/charco + hoz/azada/cubo | el nivel Destructor de árboles permite talar |
| P4 | elefante + celdas bloqueadas + bet/settle de la plataforma | nivel de carnaval; conciliación de saldo |
| P5 | partículas, sonido, object pool, perfil de gama baja, repetición | se cumplen los presupuestos de rendimiento |

P0 no conecta la billetera, basta con `?debug=1` local. Solo en P4 se conecta `SelfProvider`.

---

## 12. Resumen de responsabilidades de los módulos

| Módulo | Entrada | Salida | Dependencias |
|------|------|------|------|
| Catalog | enciclopedia JSON | PieceDef | ninguna |
| RestraintTable | configuración de contención | isEcoRun(run) | Catalog |
| Board | comandos | nuevo snapshot | Catalog, RNG |
| MatchDetector | snapshot | runs[] | RestraintTable |
| Gravity | snapshot | snapshot + Fell | Board |
| Level | estadísticas de eliminación | progreso/victoria-derrota | eventos de Board |
| Score | eventos | puntuación | Level (multiplicadores) |
| GameStateMachine | comandos/animaciones completadas | estado | los domain anteriores |
| PieceFactory | PieceDef | Object3D | solo render |
| PlatformAdapter | victoria-derrota/apuestas | HTTP | sin dependencia circular con domain |
