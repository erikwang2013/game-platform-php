# RBAC-Berechtigungsmodell
<!-- lang-nav -->

Languages: **中文** · [English](05-rbac-model.en.md) · [한국어](05-rbac-model.ko.md) · [Русский](05-rbac-model.ru.md) · [Deutsch](05-rbac-model.de.md) · [Français](05-rbac-model.fr.md) · [Español](05-rbac-model.es.md) · [Português](05-rbac-model.pt.md) · [हिन्दी](05-rbac-model.hi.md) · [العربية](05-rbac-model.ar.md) · [বাংলা](05-rbac-model.bn.md) · [Bahasa Indonesia](05-rbac-model.id.md) · [日本語](05-rbac-model.ja.md)


## Benutzer-Rolle-Berechtigung-Beziehung

```mermaid
flowchart LR
    subgraph users["Benutzer"]
        u1["admin(Superadministrator)"]
        u2["editor(Redakteur)"]
        u3["viewer(schreibgeschützt)"]
    end

    subgraph roles["Rolle"]
        r1["super_admin<br/>Berechtigungs-Slug: *"]
        r2["editor<br/>Berechtigungs-Slug: get.* post.*"]
        r3["viewer<br/>Berechtigungs-Slug: get.*"]
    end

    subgraph permissions["Berechtigung (Baum)"]
        p1["dashboard(Menü)"]
        p2["user(Menü)"]
        p3["get.admin/user(API)"]
        p4["post.admin/user(API)"]
        p5["delete.admin/user(API)"]
        p6["export.excel(Schaltfläche)"]
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

## Ablauf der Berechtigungsprüfung

```mermaid
flowchart TD
    start["Anfrage trifft ein"] --> extract["Token→adminId extrahieren"]
    extract --> findRoles["Benutzerrollen abfragen"]
    findRoles --> collectSlug["alle permission.slug sammeln"]
    collectSlug --> buildKey["method.path bilden"]
    buildKey --> check{"slug==* oder<br/>slug-Übereinstimmung?"}
    check -->|"Ja"| allow["200 Zulassen"]
    check -->|"Nein"| deny["403 Forbidden"]

    style allow fill:#52C41A,color:#fff
    style deny fill:#FF4D4F,color:#fff
```

## Berechtigungstypen

```mermaid
flowchart LR
    t1["type=1 Menü<br/>steuert die Anzeige der Seitenleiste"]
    t2["type=2 Schaltfläche<br/>steuert Aktionsschaltflächen"]
    t3["type=3 API<br/>steuert den API-Zugriff"]

    style t1 fill:#1677FF,color:#fff
    style t2 fill:#FA8C16,color:#fff
    style t3 fill:#52C41A,color:#fff
```
