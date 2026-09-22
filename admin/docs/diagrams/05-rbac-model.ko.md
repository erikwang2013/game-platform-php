# RBAC 권한 모델
<!-- lang-nav -->

Languages: [中文](05-rbac-model.md) · [English](05-rbac-model.en.md) · **한국어** · [Русский](05-rbac-model.ru.md) · [Deutsch](05-rbac-model.de.md) · [Français](05-rbac-model.fr.md) · [Español](05-rbac-model.es.md) · [Português](05-rbac-model.pt.md) · [हिन्दी](05-rbac-model.hi.md) · [العربية](05-rbac-model.ar.md) · [বাংলা](05-rbac-model.bn.md) · [Bahasa Indonesia](05-rbac-model.id.md) · [日本語](05-rbac-model.ja.md)


## 사용자-역할-권한 관계

```mermaid
flowchart LR
    subgraph users["사용자"]
        u1["admin(슈퍼 관리자)"]
        u2["editor(편집자)"]
        u3["viewer(읽기 전용)"]
    end

    subgraph roles["역할"]
        r1["super_admin<br/>권한 식별자: *"]
        r2["editor<br/>권한 식별자: get.* post.*"]
        r3["viewer<br/>권한 식별자: get.*"]
    end

    subgraph permissions["권한(트리)"]
        p1["dashboard(메뉴)"]
        p2["user(메뉴)"]
        p3["get.admin/user(API)"]
        p4["post.admin/user(API)"]
        p5["delete.admin/user(API)"]
        p6["export.excel(버튼)"]
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

## 권한 판정 플로우

```mermaid
flowchart TD
    start["요청 도착"] --> extract["Token 추출→adminId"]
    extract --> findRoles["사용자 역할 조회"]
    findRoles --> collectSlug["모든 permission.slug 수집"]
    collectSlug --> buildKey["method.path 구성"]
    buildKey --> check{"slug==* 또는<br/>slug 매칭?"}
    check -->|"예"| allow["200 허용"]
    check -->|"아니오"| deny["403 Forbidden"]

    style allow fill:#52C41A,color:#fff
    style deny fill:#FF4D4F,color:#fff
```

## 권한 유형

```mermaid
flowchart LR
    t1["type=1 메뉴<br/>사이드바 표시 제어"]
    t2["type=2 버튼<br/>작업 버튼 제어"]
    t3["type=3 API<br/>인터페이스 접근 제어"]

    style t1 fill:#1677FF,color:#fff
    style t2 fill:#FA8C16,color:#fff
    style t3 fill:#52C41A,color:#fff
```
