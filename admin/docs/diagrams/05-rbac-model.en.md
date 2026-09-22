# RBAC Permission Model
<!-- lang-nav -->

Languages: [中文](05-rbac-model.md) · **English** · [한국어](05-rbac-model.ko.md) · [Русский](05-rbac-model.ru.md) · [Deutsch](05-rbac-model.de.md) · [Français](05-rbac-model.fr.md) · [Español](05-rbac-model.es.md) · [Português](05-rbac-model.pt.md) · [हिन्दी](05-rbac-model.hi.md) · [العربية](05-rbac-model.ar.md) · [বাংলা](05-rbac-model.bn.md) · [Bahasa Indonesia](05-rbac-model.id.md) · [日本語](05-rbac-model.ja.md)


## User-Role-Permission Relationships

```mermaid
flowchart LR
    subgraph users["User"]
        u1["admin(super administrator)"]
        u2["editor(editor)"]
        u3["viewer(read-only)"]
    end

    subgraph roles["Role"]
        r1["super_admin<br/>permission slug: *"]
        r2["editor<br/>permission slug: get.* post.*"]
        r3["viewer<br/>permission slug: get.*"]
    end

    subgraph permissions["Permission (tree)"]
        p1["dashboard(menu)"]
        p2["user(menu)"]
        p3["get.admin/user(API)"]
        p4["post.admin/user(API)"]
        p5["delete.admin/user(API)"]
        p6["export.excel(button)"]
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

## Permission Check Flow

```mermaid
flowchart TD
    start["Request arrives"] --> extract["Extract Token→adminId"]
    extract --> findRoles["Query user roles"]
    findRoles --> collectSlug["Collect all permission.slug"]
    collectSlug --> buildKey["Build method.path"]
    buildKey --> check{"slug==* or<br/>slug match?"}
    check -->|"Yes"| allow["200 Allow"]
    check -->|"No"| deny["403 Forbidden"]

    style allow fill:#52C41A,color:#fff
    style deny fill:#FF4D4F,color:#fff
```

## Permission Types

```mermaid
flowchart LR
    t1["type=1 menu<br/>Controls sidebar visibility"]
    t2["type=2 button<br/>Controls action buttons"]
    t3["type=3 API<br/>Controls API access"]

    style t1 fill:#1677FF,color:#fff
    style t2 fill:#FA8C16,color:#fff
    style t3 fill:#52C41A,color:#fff
```
