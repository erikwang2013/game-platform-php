# Модель разрешений RBAC
<!-- lang-nav -->

Languages: **中文** · [English](05-rbac-model.en.md) · [한국어](05-rbac-model.ko.md) · [Русский](05-rbac-model.ru.md) · [Deutsch](05-rbac-model.de.md) · [Français](05-rbac-model.fr.md) · [Español](05-rbac-model.es.md) · [Português](05-rbac-model.pt.md) · [हिन्दी](05-rbac-model.hi.md) · [العربية](05-rbac-model.ar.md) · [বাংলা](05-rbac-model.bn.md) · [Bahasa Indonesia](05-rbac-model.id.md) · [日本語](05-rbac-model.ja.md)


## Связь «пользователь — роль — разрешение»

```mermaid
flowchart LR
    subgraph users["Пользователи"]
        u1["admin(супер-администратор)"]
        u2["editor(редактор)"]
        u3["viewer(только чтение)"]
    end

    subgraph roles["Роли"]
        r1["super_admin<br/>Идентификатор права: *"]
        r2["editor<br/>Идентификатор права: get.* post.*"]
        r3["viewer<br/>Идентификатор права: get.*"]
    end

    subgraph permissions["Права (дерево)"]
        p1["dashboard(меню)"]
        p2["user(меню)"]
        p3["get.admin/user(API)"]
        p4["post.admin/user(API)"]
        p5["delete.admin/user(API)"]
        p6["export.excel(кнопка)"]
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

## Процесс проверки разрешений

```mermaid
flowchart TD
    start["Запрос поступил"] --> extract["Извлечение Token→adminId"]
    extract --> findRoles["Запрос ролей пользователя"]
    findRoles --> collectSlug["Сбор всех permission.slug"]
    collectSlug --> buildKey["Формирование method.path"]
    buildKey --> check{"slug==* или<br/>slug совпадает?"}
    check -->|"Да"| allow["200 Пропустить"]
    check -->|"Нет"| deny["403 Forbidden"]

    style allow fill:#52C41A,color:#fff
    style deny fill:#FF4D4F,color:#fff
```

## Типы разрешений

```mermaid
flowchart LR
    t1["type=1 Меню<br/>Управление показом боковой панели"]
    t2["type=2 Кнопка<br/>Управление кнопками действий"]
    t3["type=3 API<br/>Управление доступом к API"]

    style t1 fill:#1677FF,color:#fff
    style t2 fill:#FA8C16,color:#fff
    style t3 fill:#52C41A,color:#fff
```
