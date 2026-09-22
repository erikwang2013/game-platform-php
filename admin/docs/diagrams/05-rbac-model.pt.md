# Modelo de Permissões RBAC
<!-- lang-nav -->

Languages: [中文](05-rbac-model.md) · [English](05-rbac-model.en.md) · [한국어](05-rbac-model.ko.md) · [Русский](05-rbac-model.ru.md) · [Deutsch](05-rbac-model.de.md) · [Français](05-rbac-model.fr.md) · [Español](05-rbac-model.es.md) · **Português** · [हिन्दी](05-rbac-model.hi.md) · [العربية](05-rbac-model.ar.md) · [বাংলা](05-rbac-model.bn.md) · [Bahasa Indonesia](05-rbac-model.id.md) · [日本語](05-rbac-model.ja.md)


## Relação Usuário-Role-Permissão

```mermaid
flowchart LR
    subgraph users["Usuários"]
        u1["admin(superadministrador)"]
        u2["editor(edição)"]
        u3["viewer(somente leitura)"]
    end

    subgraph roles["Funções"]
        r1["super_admin<br/>Identificador de permissão: *"]
        r2["editor<br/>Identificador de permissão: get.* post.*"]
        r3["viewer<br/>Identificador de permissão: get.*"]
    end

    subgraph permissions["Permissões (árvore)"]
        p1["dashboard(menu)"]
        p2["user(menu)"]
        p3["get.admin/user(API)"]
        p4["post.admin/user(API)"]
        p5["delete.admin/user(API)"]
        p6["export.excel(botão)"]
    end

    u1 --> r1
    u2 --> r2
    u3 --> r3
    r1 --> p1 & p2 & p3 & p4 & p5 & p6
    r2 --> p1 & p2 & p3 & p4
    r3 --> p1 & p3
    p2 --> p3 & p4 & p5
    p1 --> p6

    style u1 fill:#1677FF,color:#fff
    style r1 fill:#FA8C16,color:#fff
    style p1 fill:#52C41A,color:#fff
```

## Fluxo de Verificação de Permissão

```mermaid
flowchart TD
    start["Requisição recebida"] --> extract["Extrai Token→adminId"]
    extract --> findRoles["Consulta as funções do usuário"]
    findRoles --> collectSlug["Coleta todos os permission.slug"]
    collectSlug --> buildKey["Monta method.path"]
    buildKey --> check{"slug==* ou<br/>slug corresponde?"}
    check -->|"Sim"| allow["200 liberado"]
    check -->|"Não"| deny["403 Forbidden"]

    style allow fill:#52C41A,color:#fff
    style deny fill:#FF4D4F,color:#fff
```

## Tipos de Permissão

```mermaid
flowchart LR
    t1["type=1 Menu<br/>Controla a exibição da barra lateral"]
    t2["type=2 Botão<br/>Controla os botões de ação"]
    t3["type=3 API<br/>Controla o acesso aos endpoints"]

    style t1 fill:#1677FF,color:#fff
    style t2 fill:#FA8C16,color:#fff
    style t3 fill:#52C41A,color:#fff
```
