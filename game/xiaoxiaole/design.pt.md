<!-- lang-nav -->

Languages: [中文](design.md) · [English](design.en.md) · [한국어](design.ko.md) · [Русский](design.ru.md) · [Deutsch](design.de.md) · [Français](design.fr.md) · [Español](design.es.md) · **Português** · [हिन्दी](design.hi.md) · [العربية](design.ar.md) · [বাংলা](design.bn.md) · [Bahasa Indonesia](design.id.md) · [日本語](design.ja.md)

Certamente, como seu planejador de jogos e responsável técnico de 3D, vou elaborar um planejamento completo para o 《Three.js 消消乐》. Este plano não envolve código, com foco em **expansão de elementos**, **matriz de regras**, **mecanismos de fusão de jogabilidade** e **ideias de montagem da cena Three.js**.

---

### 1. Expansão dos elementos do jogo (design do banco de peças)

Para deixar o tabuleiro mais rico, com base no que você forneceu, dividi os elementos em **6 grandes facções**, totalizando **24 peças básicas** + **4 itens especiais**:

| Facção | Elementos incluídos | Observações complementares |
| :--- | :--- | :--- |
| **🌾 Culturas**|Peças básicas de eliminação | arroz, trigo, milho, sorgo, cevada, aveia, centeio, milheto, gergelim, amendoim, algodão, colza, chá, milheto amarelo, cevadinha, trigo sarraceno, soja, feijão-mungo, feijão-vermelho, feijão-preto, fava, ervilha, batata-doce, batata, inhame, taro, mandioca |
| **🥬 Vegetais** |Peças básicas de eliminação | couve-chinesa, rabanete, pepino, tomate, pimenta, berinjela, cebolinha, gengibre, alho, alface, cenoura, melão-amargo, coentro, cebolinha-fina, mostarda, aipo, espinafre, couve-flor, abobrinha, abóbora, alho-poró |
| **🥬 Frutas** |Peças básicas de eliminação | maçã, pera, pêssego, damasco, ameixa, morango, melancia, uva, jujuba azeda, ameixa-de-galinha, jujuba, noz, amêndoa, figo, laranja, banana, caqui, romã, kiwi, cereja, ginja |
| **🥬 Flores e plantas** |Peças básicas de eliminação | rosa, girassol, rosa-chinesa, tagetes, henna, crista-de-galo, hibisco, camélia, peônia, jasmim, glicínia, orquídea-borboleta, crisântemo, ameixeira em flor, orquídea, lótus, tanchagem, rehmannia, goji, capim-rabo-de-raposa, dente-de-leão, grama-pé-de-galinha, mostarda-do-campo |
| **🐜 Animais** |Peças básicas de eliminação | formiga, abelha, joaninha-de-sete-pontos, lagarta, cigarra, vespa, grilo, gafanhoto, lagarto, rato, sanguessuga, sapo, rã, camarão, peixe, raposa, esquilo, borboleta, louva-a-deus, aranha, vaga-lume |
| **🐓 Aves domésticas/aves** |Predadores intermediários | galinha, pato, ganso, pombo, pardal, pega, andorinha, corvo, coruja, águia |
| **🐕 Gado/animais grandes**|Peças avançadas | porco, cachorro, boi, cavalo, ovelha, coelho, gato, burro, mula, camelo |
| **🌳 Árvores/natureza** |Obstáculos/peças especiais | pinheiro, salgueiro, álamo, acácia, paulownia, plátano, cedro, ginkgo, olmo, bambu, bétula, bordo |
| **🔧 Ferramentas agrícolas** |Itens de habilidade | foice, enxada, balde, martelo, ancinho, peneira, cesto de costas, chapéu de palha, capa de palha, lanterna, rolo de pedra, carroça, bicicleta, machado, vara de carregar, arado, mós |

---

### 2. Expansão das regras centrais (design da "cadeia de restrição ecológica")

Sua lógica de regras é essencialmente **"eliminação direcionada"**. Com base no três-em-linha tradicional (três iguais eliminam), incorporamos a **"correspondência de predação/restrição"**. Quando o jogador alinha três **predadores** com **presas** em uma fileira (ou em um padrão específico), a eliminação avançada é acionada.

A seguir está a **matriz de restrição completa** que expandi para você (A restringe B):

| Predador (A) | Forma de restrição | Presa (B) | Observações sobre a regra expandida |
| :--- | :--- | :--- | :--- |
| **Galinha, pato, ganso** | Bicar / predar | flores, vegetais/frutas, insetos (formiga/joaninha/lagarta) | Complemento: eles **não comem** os cinco grãos (plantações), porque os grãos são duros demais, precisando ser eliminados separadamente. |
| **Cachorro** | Morder | galinha, pato, ganso, pombo | O cachorro não só morde aves; complemento: **o cachorro também rói ossos (correspondentes aos de porco/boi/cavalo)**, mas no jogo simplificamos: restringe todas as aves de pequeno e médio porte. |
| **Porco** | Fuçar / devastar | árvores, flores, vegetais/frutas, insetos, **todos os cinco grãos/plantações** | O porco é o rei da destruição; complemento: o porco **não fuça** o cachorro (porque o cachorro morde o porco), formando um ciclo de restrição fechado. |
| **Boi, cavalo** | Pastar / pisar | flores, **cinco grãos/plantações**, mudas de árvores frutíferas | Complemento: boi e cavalo, como grandes herbívoros, restringem especificamente as culturas, mas não comem insetos nem carne. |
| **Elefante** | Supremacia absoluta (pisar/jogar) | **todos os elementos exceto o próprio elefante (incluindo porco, cachorro, boi e cavalo)** | O elefante é a força máxima. Para equilibrar, complemento: o elefante **não pode** eliminar "ferramentas agrícolas" (itens), e sua probabilidade de aparecer no tabuleiro é extremamente baixa (peça rara). |
| **Foice (item)** | Colher | todos os cinco grãos/plantações, flores | Elimina de uma vez toda uma fileira horizontal ou vertical de plantas. |
| **Enxada (item)** | Quebrar | árvores, pedras (obstáculos) | Especializada em destruir obstáculos de alta resistência. |

---

### 3. Design da mecânica de jogo (como operar o "match-3")

Na cena 3D do Three.js, adotamos o modo integrado de **"clique para trocar + julgamento ecológico"**:

1.  **Operação básica**: o jogador clica em duas peças 3D adjacentes para trocá-las de posição.
2.  **Lógica de julgamento (chave)**:
    - **Eliminação do mesmo tipo**: após a troca, se houver **≥3 peças iguais** em linha horizontal ou vertical, executa a eliminação básica (por exemplo, três maçãs).
    - **Eliminação ecológica (especial)**: após a troca, se houver **"predador + quaisquer duas presas"** em linha horizontal ou vertical (ex.: galinha + inseto + inseto), **sem exigir que os três sejam iguais**, aciona diretamente a "animação de predação", a galinha come o inseto, com pontos extras, e a célula gera um **"fertilizante de fezes"** (buff de bônus, pontuação dobrada na próxima eliminação).
    - **Privilégio do elefante**: quando o elefante se alinha com **quaisquer duas peças diferentes**, aciona o efeito de "intimidação", limpando diretamente as três células, ignorando a espécie.
3.  **Reação em cadeia (Combo)**: após a eliminação, as peças de cima caem para preencher. Se a queda gerar uma nova "cadeia de restrição ecológica", o combo é acionado automaticamente (sem operação do jogador), garantindo a sensação de satisfação.

---

### 4. Planejamento da cena e do visual Three.js (sem código)

Para dar ao match-3 3D mais textura que o 2D, o planejamento é o seguinte:

| Módulo | Seleção técnica/plano de design |
| :--- | :--- |
| **Perspectiva da câmera** | Usar **perspectiva ortográfica de 45 graus (OrthographicCamera)** ou **perspectiva fixa**. Garante que o tabuleiro pareça uma "maquete tridimensional", facilitando observar o empilhamento frontal e traseiro. Sugestão: perspectiva fixa 2.5D, sem controles de órbita (para evitar tontura do jogador). |
| **Layout do tabuleiro** | Usar **grade 8x8**, mas com **variação de altura no eixo Y** em cada célula (simulando a ondulação do campo). As peças são elevadas por cilindros ou prismas, com disco de reflexo na base. |
| **Plano dos modelos 3D** | Sem carregar FBX/GLTF externos complexos (tráfego alto). **Todos montados com geometrias básicas do Three.js (Group)**:<br>- **Maçã**: esfera + cilindro de talo.<br>- **Galinha**: elipsoide (corpo) + cone (bico) + esfera (cabeça).<br>- **Elefante**: cubo grande (corpo) + cilindro esticado (tromba) + orelhas em placa.<br>- **Árvore**: cone (copa) + cilindro (tronco).<br>Estilo **Low Poly** com **material suave (MeshPhongMaterial)**, cores vivas. |
| **Iluminação e atmosfera** | Luz principal: luz direcional (simulando luz do dia). Luz secundária: luz ambiente (clareia sombras). Contraluz: luz de área (contorna bordas). Fundo com céu em gradiente, acompanhado de partículas flutuantes (simulando pólen ou vaga-lumes). |
| **Feedback de interação** | Usar **Raycaster (detecção por raio)** para captura de clique. Ao clicar na peça, ela **salta para cima + contorno brilhante (OutlinePass)**; na troca, **movimento interpolado suave (TWEEN ou GSAP)**; na eliminação, efeito de **encolher e sumir + explosão de partículas** (usando PointsMaterial). |

---

### 5. Expansão de itens especiais e obstáculos

Para dar profundidade estratégica ao jogo, adicionamos os seguintes mecanismos:

1.  **Obstáculos (imóveis)**:
    - **Pedra**: ocupa a célula, precisa do item "enxada" ou de três eliminações comuns adjacentes para quebrar.
    - **Poça**: as peças não caem através dela, precisa do item "balde" para esvaziar.
2.  **Forma de obter "ferramentas agrícolas"**:
    - Não aparecem diretamente no tabuleiro. Quando o jogador aciona **"restrição ecológica" 3 vezes seguidas**, a barra de habilidades na parte inferior da tela carrega; ao clicar, libera a habilidade da ferramenta correspondente (ex.: a foice desliza pela tela limpando uma fileira).

---

### 6. Planejamento de fases e objetivos

| Tipo de fase | Condição de vitória | Restrições especiais |
| :--- | :--- | :--- |
| **Fase da colheita** | Em 20 passos, eliminar **50 grãos/vegetais e frutas**. | Galinhas, patos e gansos aparecem em massa no tabuleiro, atrapalhando sua eliminação de plantas. |
| **Fase da repulsa** | Em tempo limitado, usar o **cachorro** para eliminar **15 galinhas/patos**. | No tabuleiro só aparecem aves e cachorros, sem plantas. |
| **Fase do rei da destruição** | Usar o **porco** para derrubar **3 árvores grandes** (árvores com 5 de vida). | A probabilidade de o porco aparecer é baixa, é preciso juntar três porcos para acionar a investida em área. |
| **Festa do elefante** | Usar o elefante para eliminar **30 peças quaisquer**. | Um elefante é dado no início; proteja-o, não pode ser trocado. |

---

### 7. Destaques do planejamento (resumo)

1. **Regras coerentes**: transformar a cadeia alimentar biológica (galinha come inseto, cachorro morde galinha, elefante invencível) perfeitamente nas "condições de correspondência" do match-3, tornando-o mais educativo e estratégico que um simples "match-3".
2. **Expressividade 3D**: usando o motor físico do Three.js (ou animação simulada), as peças ganham "queda por gravidade" e "salto elástico", com tridimensionalidade muito superior aos sprites 2D.
3. **Garantia de equilíbrio**: como o elefante é forte demais, definimos aparição rara (no máximo 1 simultâneo por partida), e não pode ser gerado por troca comum; só pode ser concedido pelo sistema como recompensa após 5 combos, evitando desequilíbrio do jogo.

Este planejamento mantém a "facilidade de aprendizado" do match-3 clássico e insere o núcleo único de "batalha ecológica de animais", sendo totalmente viável na implementação técnica com Three.js (combinação de geometrias puras + Shader básico). Você pode iniciar o desenvolvimento seguindo este blueprint. Se precisar de detalhes mais profundos (como curvas de animação específicas ou combinações de cores de partículas), é só me avisar. 🐘🌾

