# 데이터 암호화 레이어
<!-- lang-nav -->

Languages: [中文](07-encryption-layers.md) · [English](07-encryption-layers.en.md) · **한국어** · [Русский](07-encryption-layers.ru.md) · [Deutsch](07-encryption-layers.de.md) · [Français](07-encryption-layers.fr.md) · [Español](07-encryption-layers.es.md) · [Português](07-encryption-layers.pt.md) · [हिन्दी](07-encryption-layers.hi.md) · [العربية](07-encryption-layers.ar.md) · [বাংলা](07-encryption-layers.bn.md) · [Bahasa Indonesia](07-encryption-layers.id.md) · [日本語](07-encryption-layers.ja.md)


```mermaid
flowchart TB
    subgraph transport["전송 계층 암호화 - encryption"]
        e1["클라이언트 민감 데이터 전송"]
        e2["AES-256-CBC 암호화"]
        e3["API 전송 암호문"]
        e4["서버 복호화 처리"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["저장 계층 암호화 - encryptable"]
        d1["Model casts 설정<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["쓰기 시 자동 암호화"]
        d3["MySQL VARCHAR(500) 암호문 저장"]
        d4["읽기 시 자동 복호화"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["표시 계층 마스킹"]
        m1["phone: 138****1234"]
        m2["email: a***@example.com"]
        m3["id_card: ********"]
        d4 --> m1 & m2 & m3
    end

    e4 --> d1

    style e2 fill:#1677FF,color:#fff
    style d2 fill:#FA8C16,color:#fff
    style m1 fill:#52C41A,color:#fff
```
