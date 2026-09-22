# Camadas de Criptografia de Dados
<!-- lang-nav -->

Languages: [中文](07-encryption-layers.md) · [English](07-encryption-layers.en.md) · [한국어](07-encryption-layers.ko.md) · [Русский](07-encryption-layers.ru.md) · [Deutsch](07-encryption-layers.de.md) · [Français](07-encryption-layers.fr.md) · [Español](07-encryption-layers.es.md) · **Português** · [हिन्दी](07-encryption-layers.hi.md) · [العربية](07-encryption-layers.ar.md) · [বাংলা](07-encryption-layers.bn.md) · [Bahasa Indonesia](07-encryption-layers.id.md) · [日本語](07-encryption-layers.ja.md)


```mermaid
flowchart TB
    subgraph transport["Criptografia na camada de transporte - encryption"]
        e1["Cliente envia dados sensíveis"]
        e2["Criptografia AES-256-CBC"]
        e3["API transmite o texto cifrado"]
        e4["Servidor descriptografa e processa"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["Criptografia na camada de armazenamento - encryptable"]
        d1["Configuração de casts no Model<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["Criptografia automática na gravação"]
        d3["MySQL VARCHAR(500) armazena o texto cifrado"]
        d4["Descriptografia automática na leitura"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["Mascaramento na camada de exibição"]
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
