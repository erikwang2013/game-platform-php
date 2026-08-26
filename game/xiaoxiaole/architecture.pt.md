# 田园消消乐 — Arquitetura técnica
<!-- lang-nav -->

Languages: **中文** · [English](architecture.en.md) · [한국어](architecture.ko.md) · [Русский](architecture.ru.md) · [Deutsch](architecture.de.md) · [Français](architecture.fr.md) · [Español](architecture.es.md) · [Português](architecture.pt.md) · [हिन्दी](architecture.hi.md) · [العربية](architecture.ar.md) · [বাংলা](architecture.bn.md) · [Bahasa Indonesia](architecture.id.md) · [日本語](architecture.ja.md)


> Funcionalidades do jogador e aceitação em `functional-design.md`; cronograma em `plan.md`; visão do tema em `design.md`.
>
> Este documento responde apenas: como dividir módulos, como conectar à plataforma, em qual camada as regras são calculadas. Sem código de implementação.
>
> Posicionamento do produto: H5 próprio (`game.type = self`), match-3 em sandbox 8×8 + cadeia de restrição ecológica, Three.js low-poly 2.5D.

---

## 0. Decisões de arquitetura em relação ao planejamento

O planejamento é a visão da jogabilidade; as decisões abaixo resolvem a contradição entre "jogável, testável, conectável à carteira".

| ID | Decisão | Motivo |
|----|------|------|
| D1 | **O bestiário ≠ peças do tabuleiro**. 100+ espécies são bestiário e aparência; o pool de spawn de cada fase sorteia apenas **5–8 espécies** | Com dezenas de espécies ao mesmo tempo num 8×8, quase nada fecha combinação |
| D2 | Correspondência em duas camadas: **mesma espécie por `speciesId`, ecológica por `role` + tabela de restrição** | O planejamento exige ao mesmo tempo "três maçãs" e "galinha + inseto + inseto" |
| D3 | Prioridade de regras num mesmo segmento: **elefante > ecológica > mesma espécie**; mutuamente exclusivas, sem pontuação dupla | Evita uma linha pontuar duas vezes |
| D4 | **Ferramentas agrícolas não entram no tabuleiro**, ficam apenas na barra de habilidades do HUD; pedra/poça/árvore são obstáculos, não podem ser trocados | A seção 5 do planejamento conflita com o banco de peças; vale a regra de habilidades + obstáculos |
| D5 | **Lógica de domínio com zero dependência de Three.js**, funções puras + snapshots; a camada de apresentação só assina eventos | As regras ficam testáveis unitariamente, reproduzíveis e validáveis no servidor no futuro |
| D6 | No início, `session_id` deriva um **seed RNG determinístico**; quedas/spawns usam todos esse RNG | O mesmo seed permite revisão de partida; deixa porta aberta para anti-cheat |
| D7 | Sem motor físico. Movimento/quique/eliminação com easing, sem introduzir Cannon/Rapier | O planejamento já escreveu "animação simulada"; física não traz ganho para jogo em grade |
| D8 | Câmera **ortográfica 2.5D de posição fixa**, controles de órbita desligados | Consistente com o planejamento, evita erro de operação e tontura |
| D9 | Espécies compartilham **modelo geométrico por facção + cor/acessórios**, sem modelar cada cultura separadamente | Tráfego e prazo; a diferença visual vem da paleta e de uma peça característica |
| D10 | Entrada de fase via `SelfProvider::bet`, `settle` ao concluir, falha no meio não reembolsa a taxa de entrada; sem dar o primeiro passo pode `refund` | Alinhado com a carteira da plataforma e a idempotência do round |

---

## 1. Contexto do sistema

```
Flutter / HarmonyOS / PC Web
        │  POST /api/game/launch { game_id }
        ▼
service/ (webman :8788)
  GameController::launch  → session_id + seed + api_endpoint
  SelfProvider            → bet / settle / refund / getBalance
  GamePlayLog + EventBus  → game.played / conquistas / VIP
        │  abre api_endpoint?session_id=&token=
        ▼
game/xiaoxiaole/  (recursos estáticos, Nginx)
  Vite + TypeScript + Three.js
  Motor de domínio ──eventos──► renderização / HUD
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

O jogo é um **frontend estático**; a sessão autoritativa e o dinheiro ficam em `service/`. O cliente mantém o estado do tabuleiro; o servidor mantém o saldo e a idempotência do round. Na primeira fase não há validação passo a passo no servidor, mas a camada de domínio deve ser determinística, para que na segunda fase `seed + sequência de operações` possa ser enviado ao servidor para recálculo.

---

## 2. Camadas do cliente

De cima para baixo, proibida dependência reversa entre camadas (`render` não pode ser importado por `domain`).

```
app/          montagem, máquina de estados, ciclo de vida das fases
hud/          Overlay HTML: pontuação, passos, objetivo, habilidades, resultado
platform/     parâmetros do launch, carteira, play-log, feature flags
render/       Three.js: cena, tabuleiro, grade de peças, input, VFX
runtime/      barramento de comandos, fila de animações, replay
domain/       tabuleiro, correspondência, restrição, gravidade, pontuação, catálogo, regras de fase
config/       tabela de restrição, pesos de spawn, receitas geométricas, JSON de fases
```

**Loop principal (regras não são calculadas dentro do `requestAnimationFrame`)**: input → comando → liquidação síncrona no domínio (um swap resolve todas as cadeias e produz a lista de eventos) → o runtime reproduz as animações em fila pelos eventos → a animação termina e só então aceita o próximo input.

Assim "uma lógica, muitos frames de apresentação", e o combo não disputa estado com o clique.

---

## 3. Estrutura de diretórios (sugestão)

```
game/xiaoxiaole/
├── design.md
├── architecture.md          ← este documento
├── package.json             # vite, three, gsap, vitest, typescript
├── index.html
├── src/
│   ├── main.ts              # lê a URL, inicia GameApp
│   ├── app/
│   │   ├── GameApp.ts
│   │   └── GameStateMachine.ts
│   ├── domain/
│   │   ├── catalog/         # PieceDef, Faction, Role
│   │   ├── board/           # Grid 8×8, Cell, PieceInstance
│   │   ├── match/           # MatchDetector (mesma espécie / ecológica / elefante)
│   │   ├── eco/             # RestraintTable
│   │   ├── gravity/         # queda, bloqueio por poça, refill
│   │   ├── score/           # pontuação, multiplicador de fertilizante
│   │   ├── level/           # LevelDef, Objective, Win/Lose
│   │   └── rng/             # mulberry32 com seed
│   ├── runtime/
│   │   ├── CommandBus.ts    # Select, Swap, UseSkill, Quit
│   │   ├── EventLog.ts      # eventos serializáveis, para replay
│   │   └── AnimationQueue.ts
│   ├── render/
│   │   ├── SceneRoot.ts
│   │   ├── CameraRig.ts
│   │   ├── BoardView.ts
│   │   ├── PieceFactory.ts  # geometrias de template
│   │   ├── InputRaycaster.ts
│   │   └── vfx/
│   ├── hud/
│   ├── platform/
│   │   ├── ApiClient.ts
│   │   └── Session.ts
│   └── levels/*.json
└── tests/domain/            # sem WebGL
```

Arquivo único com no máximo 500 linhas. Se `MatchDetector` e `PieceFactory` incharem, dividir por tipo de regra / template de facção.

---

## 4. Modelo de domínio

### 4.1 Definição de peça (bestiário)

```
PieceDef
  id            speciesId        ex.: wheat, hen, elephant
  faction       Faction          crop | veg | fruit | flora | insect | poultry | livestock | tree | tool | obstacle | apex
  role          Role             plant | prey | predator_mid | predator_high | apex | obstacle | skill
  tags[]                         crop, edible_by_poultry, tree_seedling, bone …
  rarity        common | rare | legendary
  template      GeometryTemplate nome do template de geometria
  tint          RGB              paleta dentro do template
  accessory     optional         bico, pétala, tromba etc., peça diferenciadora
```

Todas as culturas/vegetais/frutas/flores/insetos/aves/gado/árvores do planejamento entram no bestiário; **tool não é gerado nas células**. Elefante `rarity = legendary`, `role = apex`.

### 4.2 Célula e tabuleiro

```
Cell
  q, r                coluna, linha (0–7)
  height             ondulação do terreno (só renderização, não participa das regras)
  occupant           PieceInstance | null
  overlay            none | fertilizer | puddle | stone(hp) | tree(hp)
  locked             bool          fase Festa do Elefante: elefante não pode ser trocado

PieceInstance
  uid, speciesId, def
  special            none | fertilizer_token
```

- **Pedra / árvore**: ocupam a célula, não podem ser trocadas, peças não caem através delas. HP definido na fase.
- **Poça**: sobreposta à célula, bloqueia a gravidade através dela (a peça de cima para na célula acima da poça).
- **Fertilizante**: fica na célula após eliminação ecológica; na próxima eliminação dessa célula, pontuação ×2, depois desaparece.

### 4.3 Pool de spawn (fase)

```
SpawnPool
  speciesIds[]       5–8 espécies
  weights[]          alinhado com species
  maxApex            padrão 1
  apexUnlock         gerado pelo sistema quando combo >= 5, proibido "gerar por troca"
```

A fase só sorteia peças do próprio pool via `rng`. Por maior que seja o bestiário, a entropia do tabuleiro é controlável.

---

## 5. Motor de regras central (design funcional)

### 5.1 Operações

1. Clicar na peça A → selecionar (quique + contorno).
2. Clicar na célula ortogonal adjacente B → tentar trocar (troca diagonal proibida).
3. Clicar em não adjacente / vazio → mudar seleção ou cancelar.
4. Se após a troca **não houver nenhuma correspondência válida**, reproduz a volta, sem gastar passo.
5. Se houver correspondência, gasta 1 passo e entra na liquidação.

Células de obstáculo não podem ser alvo de troca. Células bloqueadas (regra de fase) idem.

### 5.2 Varredura de segmentos

Para cada tabuleiro após um swap:

- Células contíguas horizontais e verticais, comprimento ≥ 3, formam um **run**.
- Um run aplica apenas uma regra (D3).
- Vários runs podem se cruzar (L/T clássico), a célula de interseção só é eliminada uma vez.

### 5.3 Eliminação do mesmo tipo

Dentro do run, `speciesId` todos iguais, e não obstáculo, nem o privilégio do elefante (tratado à parte).

- 3 iguais: pontuação básica.
- 4 iguais: pontuação extra e a célula central recebe **fertilizante** (mesmo overlay do fertilizante ecológico).
- 5 iguais: pontuação extra, carga de habilidade +1 (ver 5.7).

### 5.4 Eliminação ecológica (cadeia de restrição)

Julgamento: **exatamente 1 predador + as demais todas presas desse predador** (3 células = 1+2). As presas não precisam ser da mesma espécie.

| Predador | Correspondência de presas |
|--------|----------|
| Galinha, pato, ganso | faction ∈ {flora, veg, fruit, insect}; **não inclui crop (cinco grãos)** |
| Cachorro | faction = poultry (galinha, pato, ganso, pombo etc.) |
| Porco | faction ∈ {tree, flora, veg, fruit, insect, crop}; **não inclui cachorro** |
| Boi, cavalo | faction ∈ {flora, crop} ou tag `tree_seedling`; sem insetos e carnes |
| Elefante | ver 5.5, não passa por esta tabela |

Efeito:

- Elimina o segmento inteiro, reproduz animação de predação (o predador "come" primeiro e depois sai junto com as presas, ou o predador permanece — **na primeira fase, o segmento inteiro sai junto**, evitando que predadores remanescentes quebrem o equilíbrio da queda; se a experiência ficar fraca, na segunda fase muda para o switch "predador permanece").
- Pontuação ecológica básica maior que a do mesmo tipo.
- A célula original do predador gera **fertilizante**.
- `ecoChainStreak += 1`; em uma mesma cadeia, múltiplas eliminações ecológicas somam apenas um nó de streak (ao final de toda a resolve, +1, evitando que uma única queda encha a barra de habilidades).

**A galinha não come os cinco grãos**: culturas e galinhas podem coexistir no tabuleiro, mas não formam run ecológico; culturas só eliminam por mesma espécie.

### 5.5 Elefante

- No tabuleiro inteiro, no máximo 1; peso de spawn muito baixo; só gerado como recompensa quando `combo >= 5`, ou colocado em `initialPieces` da fase.
- Run com 1 elefante + quaisquer 2 peças que não sejam ferramentas nem obstáculos → limpa as 3 células (pode conter facções diferentes).
- O elefante **não pode** eliminar ferramentas (ferramentas não estão no tabuleiro, satisfeito naturalmente) nem obstáculos (obstáculos não entram em run).
- Fase "Festa do Elefante": 1 no início, `locked = true`, não pode ser trocado da célula original; as presas são trocadas para o lado dele formando run.

### 5.6 Cadeia, gravidade, refill

```
resolve:
  detect runs
  if none → idle
  aplica pontuações, overlays, hp em obstáculos adjacentes
  emite Clear
  gravity: cada coluna compacta de baixo para cima, pulando stone/tree sólidos; puddle bloqueia a passagem
  refill: do topo da coluna, completa com peças do SpawnPool (limitado por maxApex)
  combo++
  goto detect
```

Eliminações adjacentes descontam HP de obstáculos: pedra -1 a cada eliminação adjacente de mesmo tipo/ecológica, HP=0 quebra; árvore por padrão só descontada por **enxada**, ou o "investida em área de três porcos" da fase, ou ecológica do porco (presa inclui árvore). Na fase Rei da Destruição, árvore HP=5.

Habilidade balde: selecionar uma célula de poça → overlay removido, essa coluna recebe imediatamente uma rodada de gravity.

### 5.7 Barra de habilidades (ferramentas agrícolas)

| Habilidade | Desbloqueio | Efeito |
|------|------|------|
| Foice | 3 resolves consecutivas contendo ecológica | Clicar numa linha ou coluna, limpa todas as peças de **papel plant** da linha (crop/veg/fruit/flora), sem gastar passo, consome a carga |
| Enxada | idem, ou pré-configurada na fase | Clicar em pedra/árvore, HP=0 direto ou -3 (configuração da fase) |
| Balde | pré-configurada na fase ou por carga | Esvazia uma poça |

Regra de carga: `ecoResolveCount` chega a 3 → barra +1, contador zera. Limite de 2 cargas. Quais habilidades aparecem (foice/enxada/balde) é definido por `allowedSkills[]` da fase.

### 5.8 Pontuação

```
same_3     = 10 * n * combo * fertilizerMul
eco        = 25 * n * combo * fertilizerMul
elephant   = 40 * n * combo
skill_clear= 8  * n
obstacle   = 20 * brokenCount
```

`combo` começa em 1, +1 a cada cadeia, resetado quando o jogador faz o próximo swap manual. O fertilizante atua apenas na "eliminação em que a célula é eliminada".

---

## 6. Funcionalidades de fase

| Fase | Pool | Vitória | Derrota | Diferencial |
|------|----|------|------|------|
| Colheita | crop/veg/fruit + poultry com peso alto | eliminar 50 plant em 20 passos | passos esgotados | galinhas, patos e gansos atrapalham a eliminação por mesma espécie das plantas |
| Repulsa | poultry + dog, sem plantas | com o cachorro, eliminar 15 galinhas/patos por ecológica dentro do tempo | tempo esgotado | eliminar aves por mesma espécie não conta para o objetivo, precisa ser ecológica |
| Rei da Destruição | plantas + poucos pig + 3 árvores (HP5) | porco derruba 3 árvores | passos esgotados | três porcos em linha acionam **investida 3×3** (regra da fase, não global) |
| Festa do Elefante | pool misto + elefante bloqueado no início | regra do elefante elimina 30 peças | elefante movido anormalmente (não deveria acontecer) ou passos esgotados | proteger o elefante; o sistema não gera um segundo |

HUD geral: progresso do objetivo, passos ou contagem regressiva, combo, barra de habilidades, pausa/sair.

O julgamento de vitória/derrota acontece após o fim de uma resolve completa (incluindo todas as animações da cadeia), evitando julgamento errado no meio da animação.

---

## 7. Camada de apresentação Three.js

| Módulo | Responsabilidade |
|------|------|
| SceneRoot | WebGLRenderer, mapeamento de tom, resize, dpr máximo 2 |
| CameraRig | OrthographicCamera, inclinação ~45°, lookAt no centro do tabuleiro, sem OrbitControls |
| Lights | Directional (sol) + Hemisphere (ambiente) + Rim fraco; sem sombra em tempo real, ou apenas o tabuleiro recebendo shadow de baixa resolução |
| BoardView | terreno 8×8; ondulação Y com heightmap pré-assado com perlin (a célula lógica continua plana) |
| PieceFactory | monta Group pelo `template`: esfera/cilindro/cone/cubo; MeshPhongMaterial; object pool |
| InputRaycaster | só acerta os meshes de peças em `Idle/Selected` |
| VFX | contorno de seleção (anel luminoso desenhado à mão; na primeira fase, sem OutlinePass de tela cheia); troca com GSAP; eliminação com scale + partículas Points; pólen/vaga-lumes com poucos Points em loop |
| HUD | DOM, fora do WebGL, facilitando i18n e acessibilidade |

Templates geométricos (D9): `grain` `produce` `fruit` `flower` `bug` `bird` `beast` `tree` `apex` `rock`. O bestiário só muda tint e accessory.

Orçamento de performance: 64 peças + terreno < 200 draw calls (mesclar o terreno sempre que possível); partículas < 400; em aparelhos fracos, desligar partículas e ondulação.

---

## 8. Máquina de estados

```
Boot → Title → Playing
Subestados de Playing:
  Idle → Selected → SwapAnim → ResolveLogic → ClearAnim → GravityAnim → RefillAnim
       ↺ se ainda houver correspondência, volta a ResolveLogic (combo)
       → Idle
  Playing → SkillTargeting → SkillAnim → ResolveLogic
  Playing → Won | Lost → Result → Title | next
  Playing → Paused → Playing
```

Input inválido é descartado fora de Idle/Selected/SkillTargeting.

**Comandos**: `Select` `Swap` `UseSkill` `Pause` `Quit` `AckResult`  
**Eventos** (gravados no EventLog): `Swapped` `RejectedSwap` `Matches` `Cleared` `Fell` `Refilled` `Combo` `SkillUsed` `Won` `Lost`

---

## 9. Integração com a plataforma

O contrato completo de interfaces (launch / balance / bet / settle / refund / play-log / feature flags) está em **[api.md](api.md)**. Pontos-chave:

- Inicialização: `POST /api/game/launch` retorna `session_id, api_endpoint, type=self`, abrir `api_endpoint?session_id=&token=`.
- Carteira: `SelfProvider::bet/settle/refund`, `round_id = session_id + ':' + levelId + ':' + attempt`; domínio `seed = hash(session_id + round_id)`.
- Feature flags: `xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet`; desligadas, regride para três-em-linha do mesmo tipo.
- Segurança: na primeira fase, tabuleiro autoritativo no cliente + carteira autoritativa no servidor, cada round só bet/settle uma vez; na segunda fase, enviar sequência de operações para o servidor recalcular.

---

## 10. Não funcionais

| Item | Indicador |
|----|------|
| Primeira tela | low-poly + sem GLTF, objetivo de interagir em 3s (incluindo gzip do Vite) |
| FPS | 60fps no desktop; VFX pode ser desligado em GPUs integradas |
| Testes | testes unitários de `domain/**` cobrindo correspondência/gravidade/restrição/vitória-derrota; sem testar WebGL |
| i18n | textos do HUD por chave, seguindo o middleware `Language` da plataforma |
| Acessibilidade | seleção por setas do teclado + Enter para trocar (segunda fase); daltônicos: templates de forma antes de cor pura |
| Volume | sem FBX; three + gsap gzip, código < 250KB |

---

## 11. Fases de desenvolvimento

| Fase | Escopo | Aceitação |
|----|------|------|
| P0 | match-3 do mesmo tipo, 8×8, troca/gravidade/refill, cena ortográfica, 3 peças de template | dá para jogar uma partida sem objetivo |
| P1 | bestiário + SpawnPool + HUD de objetivo/passos das quatro fases | Fase Colheita dá para concluir |
| P2 | tabela de restrição + eliminação ecológica + fertilizante + combo | galinha + dois insetos elimina; cinco grãos não são eliminados pela galinha |
| P3 | pedra/árvore/poça + foice/enxada/balde | Fase Rei da Destruição dá para quebrar árvores |
| P4 | elefante + célula bloqueada + bet/settle da plataforma | Festa do Elefante; conferência de saldo |
| P5 | partículas, som, object pool, perfil de aparelho fraco, replay | orçamento de performance cumprido |

P0 não conecta carteira, basta `?debug=1` local. Só no P4 conecta o `SelfProvider`.

---

## 12. Visão geral das responsabilidades dos módulos

| Módulo | Entrada | Saída | Dependência |
|------|------|------|------|
| Catalog | JSON do bestiário | PieceDef | nenhuma |
| RestraintTable | configuração de restrição | isEcoRun(run) | Catalog |
| Board | comandos | novo snapshot | Catalog, RNG |
| MatchDetector | snapshot | runs[] | RestraintTable |
| Gravity | snapshot | snapshot + Fell | Board |
| Level | estatísticas de eliminação | progresso/vitória-derrota | eventos do Board |
| Score | eventos | pontuação | Level (multiplicador) |
| GameStateMachine | comandos/fim de animação | estado | domínio acima |
| PieceFactory | PieceDef | Object3D | apenas render |
| PlatformAdapter | vitória-derrota/aposta | HTTP | sem dependência circular de domínio |
