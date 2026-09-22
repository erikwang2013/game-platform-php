# Data Encryption Layers
<!-- lang-nav -->

Languages: [中文](07-encryption-layers.md) · **English** · [한국어](07-encryption-layers.ko.md) · [Русский](07-encryption-layers.ru.md) · [Deutsch](07-encryption-layers.de.md) · [Français](07-encryption-layers.fr.md) · [Español](07-encryption-layers.es.md) · [Português](07-encryption-layers.pt.md) · [हिन्दी](07-encryption-layers.hi.md) · [العربية](07-encryption-layers.ar.md) · [বাংলা](07-encryption-layers.bn.md) · [Bahasa Indonesia](07-encryption-layers.id.md) · [日本語](07-encryption-layers.ja.md)


```mermaid
flowchart TB
    subgraph transport["Transport-layer encryption - encryption"]
        e1["Client sends sensitive data"]
        e2["AES-256-CBC encryption"]
        e3["Ciphertext transmitted over API"]
        e4["Server decrypts and processes"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["Storage-layer encryption - encryptable"]
        d1["Model casts config<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["Automatic encryption on write"]
        d3["MySQL VARCHAR(500) stores ciphertext"]
        d4["Automatic decryption on read"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["Presentation-layer masking"]
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
