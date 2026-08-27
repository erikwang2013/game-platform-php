# 田园消消乐 — Planejamento de desenvolvimento
<!-- lang-nav -->

Languages: **中文** · [English](plan.en.md) · [한국어](plan.ko.md) · [Русский](plan.ru.md) · [Deutsch](plan.de.md) · [Français](plan.fr.md) · [Español](plan.es.md) · [Português](plan.pt.md) · [हिन्दी](plan.hi.md) · [العربية](plan.ar.md) · [বাংলা](plan.bn.md) · [Bahasa Indonesia](plan.id.md) · [日本語](plan.ja.md)


> Transformar a visão (`design.md`) em algo agendável. Os detalhes funcionais seguem `functional-design.md`; as restrições técnicas seguem `architecture.md`.

---

## 1. Como usar os três documentos

| Documento | Pergunta que responde | Não responde |
|------|------------|--------|
| `design.md` | tema rural, fantasia de restrição, estética 3D | quantas espécies por fase, critérios de aceitação |
| `functional-design.md` | no que o jogador clica, como ganha, quem aparece no V1 | como dividir diretórios, se usa motor físico |
| `architecture.md` | camadas, módulos, carteira da plataforma, RNG determinístico | 90 segundos ou 20 passos (já decidido no funcional) |

O desenvolvimento reconhece apenas os dois últimos; quando a visão conflita com eles, valem os dois últimos (as exceções já decididas estão na seção 12 do design funcional).

---

## 2. Escopo do V1

**Concluir = lançar:** as quatro fases concluíveis, três tipos de eliminação, habilidades e obstáculos do Rei da Destruição, H5 aberto a partir do lobby. Carteira opcional (feature flag `xxl.entry_bet`).

**Cortado ou adiado explicitamente:** 100 espécies ao mesmo tempo no tabuleiro, ferramentas como peças, motor físico, GLTF, espectadores, ranking na partida, poça na linha principal, predador permanecendo após comer, validação passo a passo no servidor.

---

## 3. Marcos

| Marco | Data-alvo (relativa ao início) | Resultado jogável | O que sai |
|--------|----------------------|----------|----------|
| M0 esqueleto | semana 1 | abrir sandbox vazio localmente | Vite, cena ortográfica Three, terreno 8×8 |
| M1 elimina | semana 2 | três iguais eliminam e caem | F01–F03, testes unitários de domain |
| M2 tem fase | semana 3 | Fase Colheita dá para ganhar e perder | F04 F05 F15 F16 |
| M3 ecológica | semana 4 | galinha come inseto, Fase Repulsa | F06 F07 F08 |
| M4 ferramentas | semana 5 | Rei da Destruição quebra árvores | F09 F10 F11 |
| M5 integração | semana 6 | lobby acessa, fase do elefante, dedução opcional | F12 F13 F14 |
| M6 polimento | semana 7 | partículas/som/perfil fraco | F17 |

Uma semana por pessoa em tempo integral. Em paralelo (domínio + renderização), dá para comprimir para cerca de 5 semanas.

---

## 4. Fases e dependências

```
P0 match-3 do mesmo tipo ─────────┐
P1 seleção e Colheita ───────────┼─ P2 ecológica e Repulsa ─ P3 obstáculos e ferramentas ─ P4 elefante+carteira ─ P5 polimento
sandbox de renderização (paralelo ao P0) ┘
```

- P0 não depende de PHP. `?debug=1` joga local.
- P1 não depende da carteira.
- P2 depende da extensão do detector de correspondências do P0, não muda a forma de operar.
- P3 depende do overlay de células.
- P4 depende de `POST /api/game/launch` e do `SelfProvider` já existentes na plataforma; o lado do jogo adiciona ticket, bet, settle.
- P5 sem dependência funcional, o interruptor de aparelho fraco pode ser inserido a qualquer momento.

---

## 5. Pacotes de trabalho (por pessoa)

**A domínio (sem interface)**  
JSON do bestiário → snapshot do tabuleiro → correspondência (mesma espécie/ecológica/elefante) → gravidade → vitória/derrota da fase → pontuação. Vitest antes da tela.

**B apresentação**  
cena, câmera, 3 dos 10 templates primeiro (espiga/fruta/galinha), Raycaster, easing de troca e eliminação. HUD em DOM.

**C conteúdo das fases**  
JSON das quatro fases: pool de spawn, objetivo, passos/tempo, whitelist de habilidades, obstáculos iniciais.

**D plataforma**  
parâmetros de URL do launch, exibição de saldo, bet/settle, estratégia de reembolso em derrota, eventos de play-log.

Ordem sugerida: teste vermelho-verde do P0 de A → B conecta o snapshot → C Colheita → teste de ecológica de A → C das outras três fases → D.

---

## 6. O que mexer no lado da plataforma (só no P4)

O contrato de interfaces está em **[api.md](api.md)**. Pontos de mudança no lado da plataforma:

| Item | Estado atual | Ação planejada |
|----|------|----------|
| Registro de jogo | `GameController::launch` já grava a sessão | no painel, adicionar um registro type=self com api_endpoint apontando para este H5 |
| Carteira | `SelfProvider::bet/settle` já existe | o jogo chama por round_id; definir teto de prêmio por round |
| Feature flags | `FeatureFlag` já existe | `xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet` |
| Hospedagem estática | Nginx já distribui | `/games/xiaoxiaole/` aponta para o build |
| Abertura pelo lobby | `launchUrl` do Flutter | endpoint concatena `session_id` |

P0–P3 **não precisam mudar PHP**.

---

## 7. Riscos

| Risco | Impacto | Mitigação |
|------|------|------|
| Jogadores não entendem a regra ecológica | Fase Repulsa intransponível | terceira dica do tutorial; preview de eliminação fica para o P5 |
| Espécies de spawn ainda muitas | sem peças para eliminar | teto de 8 espécies por fase |
| Elefante forte demais | Festa zera na hora | objetivo só conta a regra do elefante; no tabuleiro, travado em 1 |
| Cliente altera pontuação para fraudar prêmio | carteira | no P4, teto de prêmio; validação por gravação fica para depois |
| Aparelho fraco com queda de FPS | experiência | dpr máximo 2; partículas desligáveis |

---

## 8. Já decidido (não perguntar de novo)

- Após ecológica, o predador sai **junto com as presas**.
- Fase Repulsa **limitada a 90 segundos**, sem passos.
- A poça não entra na linha principal das quatro fases.
- V1 só sorteia a tabela da seção 7 do design funcional; as demais espécies entram apenas nos arquivos do bestiário.

Para mudar esses quatro pontos, primeiro altere `functional-design.md` e depois o código.

---

## 9. Próximo passo (aguardando seu aval)

1. Escrever a lista de tarefas de implementação do P0 (nível de arquivo, teste primeiro), ou
2. Montar direto o esqueleto Vite + `domain` + cena vazia.

Este planejamento não contém implementação de funções específicas.
