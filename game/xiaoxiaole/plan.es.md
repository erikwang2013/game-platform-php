# 田园消消乐 — Planificación de desarrollo
<!-- lang-nav -->

Languages: [中文](plan.md) · [English](plan.en.md) · [한국어](plan.ko.md) · [Русский](plan.ru.md) · [Deutsch](plan.de.md) · [Français](plan.fr.md) · **Español** · [Português](plan.pt.md) · [हिन्दी](plan.hi.md) · [العربية](plan.ar.md) · [বাংলা](plan.bn.md) · [Bahasa Indonesia](plan.id.md) · [日本語](plan.ja.md)


> Convertir la visión (`design.md`) en algo planificable. Los detalles funcionales se rigen por `functional-design.md`; las restricciones técnicas por `architecture.md`.

---

## 1. Cómo usar los tres documentos

| Documento | Responde a | No responde |
|------|------------|--------|
| `design.md` | tema rural, fantasía de contención, estética 3D | cuántas especies refresca un nivel, cláusulas de aceptación |
| `functional-design.md` | qué pulsa el jugador, cómo se gana, quién sale en V1 | cómo dividir directorios, si usar motor de físicas |
| `architecture.md` | capas, módulos, billetera de la plataforma, RNG determinista | 90 segundos o 20 pasos (ya decidido en funcional) |

El desarrollo solo reconoce los dos últimos; si la visión entra en conflicto con los dos últimos, mandan los dos últimos (las excepciones ya decididas están escritas en la sección 12 del diseño funcional).

---

## 2. Alcance de V1

**Esto cuenta como lanzamiento:** los cuatro niveles completables, tres tipos de eliminación, habilidades y obstáculos del destructor de árboles, H5 abrible desde el lobby. La billetera se puede apagar (feature flag `xxl.entry_bet`).

**Cortado o pospuesto explícitamente:** 100 especies a la vez en el tablero, herramientas agrícolas como piezas, motor de físicas, GLTF, espectadores, clasificación en partida, charcos en niveles de la línea principal, el depredador se queda tras comer, validación servidor a servidor por paso.

---

## 3. Hitos

| Hito | Fecha objetivo (desde el inicio) | Resultado jugable | Qué sale |
|--------|----------------------|----------|----------|
| M0 esqueleto | semana 1 | abrir en local una maqueta vacía | Vite, escena ortográfica Three, parcelas 8×8 |
| M1 se elimina | semana 2 | tres iguales se eliminan y caen | F01–F03, tests unitarios de domain |
| M2 hay niveles | semana 3 | el nivel cosecha se puede ganar y perder | F04 F05 F15 F16 |
| M3 ecológica | semana 4 | la gallina come insectos, nivel ahuyentar | F06 F07 F08 |
| M4 herramientas | semana 5 | el destructor tala árboles | F09 F10 F11 |
| M5 integración | semana 6 | se entra desde el lobby, nivel del elefante, tarifa opcional | F12 F13 F14 |
| M6 pulido | semana 7 | partículas/sonido/perfil de gama baja | F17 |

Una semana está estimada para una persona a jornada completa. En paralelo (dominio + renderizado) se puede bajar a unas 5 semanas.

---

## 4. Fases y dependencias

```
P0 match-3 del mismo tipo ────────┐
P1 selección de nivel y cosecha ──┼─ P2 ecológica y ahuyentar ─ P3 obstáculos y herramientas ─ P4 elefante+billetera ─ P5 pulido
maqueta de renderizado (paralela a P0) ┘
```

- P0 no depende de PHP. Se juega en local con `?debug=1`.
- P1 no depende de la billetera.
- P2 depende de ampliar el escaneo de coincidencias de P0, sin cambiar el modo de operación.
- P3 depende del overlay de celdas.
- P4 depende del `POST /api/game/launch` y `SelfProvider` ya existentes en la plataforma; el juego añade ticket, bet, settle.
- P5 no tiene dependencias funcionales; el interruptor de gama baja se puede insertar en cualquier momento.

---

## 5. Paquetes de trabajo (por persona)

**A Dominio (sin interfaz)**
Enciclopedia JSON → snapshot del tablero → emparejamiento (mismo tipo/ecológica/elefante) → gravedad → victoria/derrota de nivel → puntuación. Vitest antes que la imagen.

**B Presentación**
Escena, cámara, de las 10 plantillas hacer primero 3 (espiga/fruta/gallina), Raycaster, easing de intercambio y eliminación. El HUD con DOM.

**C Contenido de niveles**
Cuatro niveles en JSON: pool de refresco, objetivo, pasos/tiempo, lista blanca de habilidades, obstáculos iniciales.

**D Plataforma**
Parámetros de la URL de launch, mostrar saldo, bet/settle, estrategia de reembolso al fallar, eventos de play-log.

Orden recomendado: tests de P0 de A en rojo/verde → B conecta snapshots → C cosecha → tests de ecológica de A → C resto de niveles → D.

---

## 6. Lo que hay que tocar en la plataforma (solo en P4)

El contrato de interfaces está en **[api.md](api.md)**. Puntos de cambio en la plataforma:

| Elemento | Estado actual | Acción planificada |
|----|------|----------|
| Registro de juegos | `GameController::launch` ya escribe la sesión | añadir en el panel un juego con type=self, api_endpoint apuntando a este H5 |
| Billetera | `SelfProvider::bet/settle` ya existe | el juego lo llama por round_id; fijar un tope de premio por round |
| Feature flags | `FeatureFlag` ya existe | `xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet` |
| Alojamiento estático | Nginx ya reparte | `/games/xiaoxiaole/` apunta al artefacto de build |
| Abrir desde el lobby | Flutter `launchUrl` | el endpoint concatena `session_id` |

P0–P3 **no requieren tocar PHP**.

---

## 7. Riesgos

| Riesgo | Impacto | Mitigación |
|------|------|------|
| los jugadores no entienden las reglas ecológicas | no se pasa el nivel ahuyentar | tercera pista del tutorial; vista previa de eliminaciones para P5 |
| todavía refrescan demasiadas especies | no hay piezas que eliminar | tope duro de 8 tipos por nivel |
| el elefante es demasiado fuerte | el carnaval se vacía al instante | el objetivo solo cuenta la regla del elefante; 1 fijado por tablero |
| el cliente altera la puntuación para estafar premios | billetera | tope de premio en P4; validación por grabación pospuesta |
| caída de FPS en gama baja | experiencia | dpr máx. 2; partículas desactivables |

---

## 8. Ya decidido (no se vuelve a preguntar)

- Tras la ecológica, el depredador **sale junto con las presas**.
- El nivel ahuyentar tiene **límite de 90 segundos**, sin pasos.
- Los charcos no entran en la línea principal de los cuatro niveles.
- V1 solo refresca la tabla de la sección 7 del diseño funcional; el resto de especies solo entran en el archivo de enciclopedia.

Para cambiar estas cuatro cosas, primero hay que modificar `functional-design.md` y luego el código.

---

## 9. Siguiente paso (a tu señal)

1. Escribir la lista de tareas de implementación de P0 (a nivel de archivo, test primero), o
2. Montar directamente el esqueleto Vite + `domain` + escena vacía.

En esta planificación no se escriben implementaciones concretas de funciones.
