# RBAC পারমিশন মডেল
<!-- lang-nav -->

Languages: [中文](05-rbac-model.md) · [English](05-rbac-model.en.md) · [한국어](05-rbac-model.ko.md) · [Русский](05-rbac-model.ru.md) · [Deutsch](05-rbac-model.de.md) · [Français](05-rbac-model.fr.md) · [Español](05-rbac-model.es.md) · [Português](05-rbac-model.pt.md) · [हिन्दी](05-rbac-model.hi.md) · [العربية](05-rbac-model.ar.md) · **বাংলা** · [Bahasa Indonesia](05-rbac-model.id.md) · [日本語](05-rbac-model.ja.md)


## ইউজার-রোল-পারমিশন সম্পর্ক

```mermaid
flowchart LR
    subgraph users["ইউজার"]
        u1["admin(সুপার অ্যাডমিন)"]
        u2["editor(এডিটর)"]
        u3["viewer(রিড-অনলি)"]
    end

    subgraph roles["ভূমিকা"]
        r1["super_admin<br/>পারমিশন স্লাগ: *"]
        r2["editor<br/>পারমিশন স্লাগ: get.* post.*"]
        r3["viewer<br/>পারমিশন স্লাগ: get.*"]
    end

    subgraph permissions["পারমিশন(ট্রি)"]
        p1["dashboard(মেনু)"]
        p2["user(মেনু)"]
        p3["get.admin/user(API)"]
        p4["post.admin/user(API)"]
        p5["delete.admin/user(API)"]
        p6["export.excel(বোতাম)"]
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

## পারমিশন চেক ফ্লো

```mermaid
flowchart TD
    start["অনুরোধ পৌঁছায়"] --> extract["Token→adminId এক্সট্র্যাক্ট"]
    extract --> findRoles["ইউজার ভূমিকা কোয়েরি"]
    findRoles --> collectSlug["সব permission.slug সংগ্রহ"]
    collectSlug --> buildKey["method.path তৈরি"]
    buildKey --> check{"slug==* বা<br/>slug ম্যাচ?"}
    check -->|"হ্যাঁ"| allow["200 পাস"]
    check -->|"না"| deny["403 Forbidden"]

    style allow fill:#52C41A,color:#fff
    style deny fill:#FF4D4F,color:#fff
```

## পারমিশন টাইপ

```mermaid
flowchart LR
    t1["type=1 মেনু<br/>সাইডবার প্রদর্শন নিয়ন্ত্রণ"]
    t2["type=2 বোতাম<br/>অপারেশন বোতাম নিয়ন্ত্রণ"]
    t3["type=3 API<br/>API অ্যাক্সেস নিয়ন্ত্রণ"]

    style t1 fill:#1677FF,color:#fff
    style t2 fill:#FA8C16,color:#fff
    style t3 fill:#52C41A,color:#fff
```
