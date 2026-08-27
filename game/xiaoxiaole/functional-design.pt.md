# 田园消消乐 — Design funcional
<!-- lang-nav -->

Languages: **中文** · [English](functional-design.en.md) · [한국어](functional-design.ko.md) · [Русский](functional-design.ru.md) · [Deutsch](functional-design.de.md) · [Français](functional-design.fr.md) · [Español](functional-design.es.md) · [Português](functional-design.pt.md) · [हिन्दी](functional-design.hi.md) · [العربية](functional-design.ar.md) · [বাংলা](functional-design.bn.md) · [Bahasa Indonesia](functional-design.id.md) · [日本語](functional-design.ja.md)


> Especificação do que o jogador vê, opera e aceita. Divisão técnica em `architecture.md`; visão dos elementos em `design.md`; cronograma em `plan.md`.
>
> Em uma frase: num sandbox rural tridimensional, troque peças adjacentes e limpe o tabuleiro com "três iguais" ou "predador come presa" para completar o objetivo da fase.

---

## 1. Definição do produto

| Item | Conteúdo |
|----|------|
| Nome | 田园消消乐 |
| Tipo | match-3 8×8 + restrição ecológica |
| Perspectiva | sandbox ortográfico 2.5D fixo, sem rotação |
| Operação | clicar em duas peças adjacentes para trocar (apenas cima/baixo/esquerda/direita) |
| Formato de plataforma | H5 próprio, aberto pelo `launch` do lobby de jogos |
| Experiência de sucesso | aprende o match-3 na hora; sente a regra evoluir no primeiro "galinha come inseto"; a queda em cadeia tem ritmo |

**V1 não faz:** rankings em tempo real na partida, espectadores amigos, evolução de peças, mundo aberto, modelos GLTF de alta fidelidade, fases personalizadas pelo jogador.

---

## 2. Fluxo do jogador

```
No lobby, clicar em "Iniciar"
  → página de carregamento (lê a sessão)
  → seleção de fase (lista das quatro fases + saldo, só P4 mostra a dedução)
  → partida
       HUD: objetivo / passos ou contagem regressiva / pontuação / combo / barra de habilidades
       Tabuleiro: clicar para selecionar → clicar em adjacente para trocar
       Sem eliminação: volta, não gasta passo
       Com eliminação: gasta 1 passo → animação de eliminação → queda → refill → cadeia automática
  → liquidação de vitória / derrota
  → próxima fase / tentar de novo / voltar à seleção
```

Na primeira entrada na "Fase Colheita", 3 dicas aparecem e somem; pode pular; não reaparecem (localStorage).

---

## 3. Loop central

1. Olhar o objetivo (quantas plantas / galinhas e patos / árvores / células pisadas pelo elefante faltam).
2. Encontrar um triplo do mesmo tipo, ou mover o predador para perto de duas presas.
3. Trocar → eliminar → queda em cadeia.
4. A eliminação ecológica deixa fertilizante na célula original; na próxima eliminação dessa célula, pontuação ×2.
5. Fazer 3 liquidações com ecológica seguidas, a barra de habilidades acende; usar foice/enxada/balde para destravar.
6. Objetivo completo e passos/tempo ainda sobrando → vitória.

---

## 4. Interfaces

| Interface | Elementos | Comportamento |
|------|------|------|
| Carregamento | nome do jogo, progresso | sessão inválida avisa para voltar ao lobby |
| Seleção de fase | quatro cartões: nome, resumo do objetivo, desbloqueada | V1 as quatro abertas; P4 mostra taxa de entrada |
| HUD superior da partida | nome da fase, barra de progresso do objetivo, passos restantes ou contagem regressiva, pontuação, combo | contagem regressiva em segundos, congela na pausa |
| HUD inferior da partida | barra de habilidades (máx. 2), pausa | slot cinza quando sem carga |
| Tabuleiro | terreno 8×8 + peças | seleção quica + contorno; célula inválida sem contorno |
| Pausa | continuar / reiniciar / desistir | reiniciar gasta uma tentativa; desistir conta como derrota |
| Vitória | pontuação, passos restantes, se premia (P4) | próxima fase / voltar à seleção |
| Derrota | motivo (passos/tempo), quanto faltou do objetivo | tentar de novo / voltar à seleção |
| Saldo insuficiente | texto + ir recarregar | apenas P4 |

Teclado (P5): setas mudam a seleção, Enter troca com a célula na direção da seleção. V1 só mouse/toque.

---

## 5. Regras de operação (visão do jogador)

- Só é possível trocar peças **ortogonalmente adjacentes** e em que ambos os lados sejam móveis.
- Células de pedra, árvore e poça não podem ser alvo de troca. O elefante bloqueado não pode ser trocado (as presas são trocadas para perto).
- Se após a troca não houver "triplo legal" na horizontal ou vertical → volta, **sem gastar passo nem tempo**.
- Se houver triplo legal → gasta 1 passo (fase com tempo não gasta passo, só corre o relógio).
- Só aceita o próximo clique depois que a cadeia toda terminar; clicar no tabuleiro durante a cadeia é ignorado.
- Triplo diagonal não conta. Cruzamento L/T só elimina cada célula uma vez.

---

## 6. Três tipos de eliminação (funcional)

Prioridade: **elefante > ecológica > mesma espécie**. Uma linha pontua apenas pela regra de maior prioridade.

### 6.1 Mesma espécie

Três ou mais **da mesma espécie** em linha. Ex.: maçã-maçã-maçã.

| Comprimento | O que o jogador vê |
|------|----------------|
| 3 | encolhe e some, pontuação básica |
| 4 | some, a célula central ganha fertilizante |
| 5+ | some, carga de habilidade +1 (limitada pelas habilidades permitidas na fase) |

### 6.2 Ecológica (predação)

Numa linha, **exatamente 1 predador**, as demais são todas presas dele; as presas não precisam ser da mesma espécie. Ex.: galinha-formiga-joaninha.

| Predador | Pode comer | Não pode comer |
|--------|------|--------|
| Galinha, pato, ganso | flores, vegetais, frutas, insetos | cinco grãos |
| Cachorro | galinha, pato, ganso, pombo etc. (aves domésticas) | plantas, insetos |
| Porco | árvore, flores, vegetais, frutas, insetos, cinco grãos | cachorro |
| Boi, cavalo | flores, cinco grãos, mudas | insetos, carnes |
| Elefante | ver 6.3 | obstáculos, ferramentas |

O que o jogador vê: animação de predação → as três células ficam vazias (V1 o predador sai junto) → fertilizante na célula original do predador.

### 6.3 Elefante

Uma linha com 1 elefante + outras duas células com peças elimináveis quaisquer → as três células são limpas, ignorando facção. No tabuleiro, no máximo 1 elefante. Não é "sintetizado" por troca comum; com 5 combos o sistema o deixa cair no topo de uma coluna vazia, ou a fase o coloca no início.

---

## 7. Bestiário de exibição V1 (não são as 100 espécies do planejamento)

Todas as espécies do planejamento ficam preservadas como dados do bestiário, mas **a partida V1 só sorteia as abaixo**, garantindo legibilidade e eliminabilidade.

| Espécie | Facção | Fases em que aparece | Identificação do jogador |
|------|------|----------|----------|
| trigo wheat | cinco grãos | Colheita, Rei da Destruição, Festa | espiga dourada |
| arroz rice | cinco grãos | Colheita | espiga verde |
| milho corn | cinco grãos | Colheita | sabugo amarelo |
| couve-chinesa cabbage | vegetais | Colheita | bola de folhas verde-clara |
| tomate tomato | vegetais | Colheita | bola vermelha |
| maçã apple | frutas | Colheita, Rei da Destruição, Festa | bola vermelha + talo |
| rosa rose | flores e plantas | Rei da Destruição | pétalas vermelhas |
| formiga ant | insetos | Colheita (peso baixo) | pretinha |
| joaninha ladybug | insetos | Colheita | pontos vermelhos |
| galinha hen | aves | Colheita, Repulsa, Festa | elipse + bico |
| pato duck | aves | Colheita, Repulsa | bico achatado |
| ganso goose | aves | Repulsa | pescoço longo |
| pombo pigeon | aves | Repulsa | cinza |
| cachorro dog | gado | Repulsa, Festa | quadrúpede |
| porco pig | gado | Rei da Destruição, Festa | elipse rosa |
| pinheiro pine | árvores/obstáculos | Rei da Destruição | copa cônica, não trocável |
| elefante elephant | topo | Festa; outras fases recompensa de 5 combos | cubo grande + tromba |

Ferramentas (foice, enxada, balde) **não entram no tabuleiro**, ficam só no HUD. As demais ferramentas do planejamento não entram no V1.

---

## 8. Especificações das fases

Vitória/derrota são julgadas após o **fim de toda a animação da cadeia**.

### 8.1 Fase Colheita

- Pool: trigo, arroz, milho, couve-chinesa, tomate, maçã, galinha, pato; formiga/joaninha com peso baixo.
- Vitória: eliminar **50** peças de papel plant em 20 passos (cinco grãos + vegetais + frutas + flores). Galinhas e patos eliminados não contam.
- Derrota: passos zerados e objetivo incompleto.
- Habilidade: foice (disponível após carregar).
- Tutorial: ① clicar em adjacente para trocar ② três iguais eliminam ③ a galinha pode comer dois insetos/vegetais/frutas ao lado, mas não come trigo.

### 8.2 Fase Repulsa

- Pool: galinha, pato, ganso, pombo, cachorro. Sem plantas.
- Vitória: em **90 segundos**, eliminar 15 aves com a **eliminação ecológica do cachorro**.
- Derrota: tempo esgotado.
- **Eliminar três galinhas por mesma espécie não conta para o objetivo** (é preciso completar a ecológica cachorro come ave).
- Habilidade: nenhuma. Pausa congela o cronômetro.

### 8.3 Fase Rei da Destruição

- Pool: trigo, maçã, rosa, porco (peso baixo). Fixas 3 árvores de pinheiro, HP=5, não trocáveis, peças não caem através delas.
- Vitória: HP das 3 árvores zerado.
- Derrota: 25 passos esgotados.
- Árvore perde vida: ecológica do porco (árvore no run de presas) -2; três porcos em linha acionam **investida 3×3** (árvore no alcance -5); enxada numa única -3; três-em-linha comum adjacente -1.
- Habilidade: enxada.

### 8.4 Fase Festa do Elefante

- Pool: trigo, maçã, galinha, cachorro, porco. No início, 1 elefante bloqueado perto do centro.
- Vitória: eliminar 30 células pela **regra do elefante** (mesma espécie/ecológica não contam para este objetivo).
- Derrota: 30 passos esgotados.
- Não gera um segundo elefante. O jogador troca presas para os lados, acima ou abaixo do elefante.
- Habilidade: nenhuma.

---

## 9. Obstáculos, fertilizante, habilidades

| Funcionalidade | Percepção do jogador | Regra |
|------|----------|------|
| Pedra | cinza, não clicável | HP3; eliminação adjacente -1; enxada quebra de uma vez |
| Árvore | modelo alto, não clicável | ver Rei da Destruição |
| Poça | superfície da célula refletiva | a peça de cima para na célula acima da poça; depois que o balde esvazia, a queda volta |
| Fertilizante | mancha escura na célula | a próxima eliminação dessa célula pontua ×2 e depois some |
| Foice | ícone na barra inferior | escolher uma linha ou coluna, só limpa plantas, não gasta passo, gasta 1 carga |
| Enxada | ícone na barra inferior | clicar em 1 pedra ou árvore |
| Balde | ícone na barra inferior | clicar em 1 célula de poça |

Carga: em toda liquidação completa disparada por uma operação do jogador, se houver eliminação ecológica, contador +1; ao chegar em 3 ganha 1 slot, limite 2. Cinco do mesmo tipo também dão +1 slot (compartilham o slot com a carga ecológica).

V1 Colheita não tem pedra/poça; Rei da Destruição não tem poça. A poça fica no bestiário e não bloqueia a linha principal das quatro fases.

---

## 10. Pontuação e economia

```
Mesma espécie  10 × eliminados × combo × fertilizante
Ecológica      25 × eliminados × combo × fertilizante
Elefante       40 × eliminados × combo
Limpeza por habilidade  8 × eliminados
Obstáculo quebrado   20 × quebrados
```

combo: a primeira eliminação desta operação vale 1, cada rodada extra de cadeia +1; a próxima operação manual do jogador volta a 1.

**Carteira P4:**

- Ao escolher a fase, deduz a taxa de entrada (padrão 1 moeda de jogo por fase).
- Ao concluir, liquida por estrelas: recursos restantes ≥50% três estrelas, ≥20% duas estrelas, senão uma; prêmio 2 / 3 / 5 (configurável).
- Derrota não reembolsa a taxa de entrada.
- Sair sem trocar nenhuma peça → reembolso.
- Saldo insuficiente não permite abrir a fase.

V1 (P0–P3) sem dedução, jogável localmente.

---

## 11. Lista de funcionalidades e aceitação

| ID | Funcionalidade | Aceitação | Fase |
|----|------|------|------|
| F01 | troca por clique 8×8 | adjacente troca, diagonal não, sem eliminação volta | P0 |
| F02 | match-3 do mesmo tipo + gravidade + refill | três trigos eliminam, o de cima cai, topo recebe peças novas | P0 |
| F03 | cadeia | após a queda elimina de novo automaticamente, combo +1 | P0 |
| F04 | seleção das quatro fases | clicar entra no HUD do objetivo correspondente | P1 |
| F05 | objetivo Colheita | 50 plantas em 20 passos, contagem só de plantas | P1 |
| F06 | eliminação ecológica | galinha + dois insetos elimina; galinha + dois trigos não elimina | P2 |
| F07 | fertilizante | após ecológica, a próxima eliminação dessa célula pontua o dobro uma vez | P2 |
| F08 | objetivo Repulsa | galinhas por mesma espécie não contam; cachorro come galinha conta; 90s | P2 |
| F09 | árvore e enxada | árvore não trocável; enxada/porco quebram | P3 |
| F10 | três porcos 3×3 | três porcos em linha, árvores no alcance quebram direto | P3 |
| F11 | foice | limpa uma linha de plantas, não gasta passo | P3 |
| F12 | elefante bloqueado | elefante não pode ser trocado; elefante + duas peças limpa três células | P4 |
| F13 | objetivo Festa | só a regra do elefante conta para 30 | P4 |
| F14 | taxa de entrada/prêmio | conferência de saldo, liquidação repetida não paga duas vezes | P4 |
| F15 | tutorial | três dicas, pular permanente | P1 |
| F16 | pausa/reiniciar/desistir | cronômetro congela; desistir conta como derrota | P1 |
| F17 | aparelho fraco sem partículas | após desligar, FPS estável e jogável | P5 |

---

## 12. Limites (devem ser fixados)

1. O bestiário pode ser grande, mas **cada fase sorteia no máximo 8 espécies**.
2. Ferramentas agrícolas não entram no tabuleiro.
3. A galinha não come os cinco grãos: uma linha "galinha+trigo+trigo" não é ecológica nem mesma espécie, volta.
4. O cachorro não come plantas; o porco não fuça o cachorro.
5. No tabuleiro, no máximo 1 elefante ao mesmo tempo.
6. Durante a reprodução da cadeia, input é descartado.
7. Vitória/derrota não são julgadas no meio da animação.
8. V1: o predador sai de cena junto com as presas.
9. Fase Repulsa tem limite de 90 segundos, não usa passos.
10. A poça não entra na linha principal das quatro fases.
