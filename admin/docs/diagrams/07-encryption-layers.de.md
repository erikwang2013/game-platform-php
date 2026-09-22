# Datenverschlüsselungs-Schichten
<!-- lang-nav -->

Languages: **中文** · [English](07-encryption-layers.en.md) · [한국어](07-encryption-layers.ko.md) · [Русский](07-encryption-layers.ru.md) · [Deutsch](07-encryption-layers.de.md) · [Français](07-encryption-layers.fr.md) · [Español](07-encryption-layers.es.md) · [Português](07-encryption-layers.pt.md) · [हिन्दी](07-encryption-layers.hi.md) · [العربية](07-encryption-layers.ar.md) · [বাংলা](07-encryption-layers.bn.md) · [Bahasa Indonesia](07-encryption-layers.id.md) · [日本語](07-encryption-layers.ja.md)


```mermaid
flowchart TB
    subgraph transport["Verschlüsselung in der Übertragungsschicht - encryption"]
        e1["Client sendet sensible Daten"]
        e2["AES-256-CBC-Verschlüsselung"]
        e3["Über die API übertragener Geheimtext"]
        e4["Server entschlüsselt und verarbeitet"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["Verschlüsselung in der Speicherschicht - encryptable"]
        d1["Model-casts-Konfiguration<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["Automatische Verschlüsselung beim Schreiben"]
        d3["MySQL VARCHAR(500) speichert Geheimtext"]
        d4["Automatische Entschlüsselung beim Lesen"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["Maskierung in der Darstellungsschicht"]
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
