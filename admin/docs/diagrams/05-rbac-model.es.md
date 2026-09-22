# Modelo de permisos RBAC
<!-- lang-nav -->

Languages: [中文](05-rbac-model.md) · [English](05-rbac-model.en.md) · [한국어](05-rbac-model.ko.md) · [Русский](05-rbac-model.ru.md) · [Deutsch](05-rbac-model.de.md) · [Français](05-rbac-model.fr.md) · **Español** · [Português](05-rbac-model.pt.md) · [हिन्दी](05-rbac-model.hi.md) · [العربية](05-rbac-model.ar.md) · [বাংলা](05-rbac-model.bn.md) · [Bahasa Indonesia](05-rbac-model.id.md) · [日本語](05-rbac-model.ja.md)


## Relación usuario-rol-permiso

```mermaid
flowchart LR
    subgraph users["Usuario"]
        u1["admin(superadministrador)"]
        u2["editor(editor)"]
        u3["viewer(solo lectura)"]
    end

    subgraph roles["Rol"]
        r1["super_admin<br/>slug de permiso: *"]
        r2["editor<br/>slug de permiso: get.* post.*"]
        r3["viewer<br/>slug de permiso: get.*"]
    end

    subgraph permissions["Permiso (árbol)"]
        p1["dashboard(menú)"]
        p2["user(menú)"]
        p3["get.admin/user(API)"]
        p4["post.admin/user(API)"]
        p5["delete.admin/user(API)"]
        p6["export.excel(botón)"]
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

## Flujo de verificación de permisos

```mermaid
flowchart TD
    start["Llega la solicitud"] --> extract["Extraer Token→adminId"]
    extract --> findRoles["Consultar los roles del usuario"]
    findRoles --> collectSlug["recopilar todos los permission.slug"]
    collectSlug --> buildKey["construir method.path"]
    buildKey --> check{"¿slug==* o<br/>coincidencia de slug?"}
    check -->|"Sí"| allow["200 Permitir"]
    check -->|"No"| deny["403 Forbidden"]

    style allow fill:#52C41A,color:#fff
    style deny fill:#FF4D4F,color:#fff
```

## Tipos de permisos

```mermaid
flowchart LR
    t1["type=1 menú<br/>controla la visibilidad de la barra lateral"]
    t2["type=2 botón<br/>controla los botones de acción"]
    t3["type=3 API<br/>controla el acceso a la API"]

    style t1 fill:#1677FF,color:#fff
    style t2 fill:#FA8C16,color:#fff
    style t3 fill:#52C41A,color:#fff
```
