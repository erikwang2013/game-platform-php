# Couches de chiffrement des données
<!-- lang-nav -->

Languages: [中文](07-encryption-layers.md) · [English](07-encryption-layers.en.md) · [한국어](07-encryption-layers.ko.md) · [Русский](07-encryption-layers.ru.md) · [Deutsch](07-encryption-layers.de.md) · **Français** · [Español](07-encryption-layers.es.md) · [Português](07-encryption-layers.pt.md) · [हिन्दी](07-encryption-layers.hi.md) · [العربية](07-encryption-layers.ar.md) · [বাংলা](07-encryption-layers.bn.md) · [Bahasa Indonesia](07-encryption-layers.id.md) · [日本語](07-encryption-layers.ja.md)


```mermaid
flowchart TB
    subgraph transport["Chiffrement de la couche de transmission - encryption"]
        e1["Le client envoie des données sensibles"]
        e2["Chiffrement AES-256-CBC"]
        e3["Transmission du texte chiffré via l'API"]
        e4["Déchiffrement et traitement côté serveur"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["Chiffrement de la couche de stockage - encryptable"]
        d1["Model $casts<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["Écriture: chiffrement automatique"]
        d3["MySQL VARCHAR(500) stockage du texte chiffré"]
        d4["Lecture: déchiffrement automatique"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["Masquage de la couche d'affichage"]
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
