# Schéma d'architecture système (v2.0)
<!-- lang-nav -->

Languages: [中文](01-system-architecture.md) · [English](01-system-architecture.en.md) · [한국어](01-system-architecture.ko.md) · [Русский](01-system-architecture.ru.md) · [Deutsch](01-system-architecture.de.md) · **Français** · [Español](01-system-architecture.es.md) · [Português](01-system-architecture.pt.md) · [हिन्दी](01-system-architecture.hi.md) · [العربية](01-system-architecture.ar.md) · [বাংলা](01-system-architecture.bn.md) · [Bahasa Indonesia](01-system-architecture.id.md) · [日本語](01-system-architecture.ja.md)


```mermaid
flowchart TB
    subgraph "Couche client"
        A1["Flutter Web PC<br/>Administration"]
        A2["Flutter Web PC<br/>Plateforme utilisateur côté C"]
        A3["HarmonyOS ArkTS<br/>Client mobile/tablette"]
    end

    subgraph "Couche passerelle"
        B1["Nginx<br/>Reverse proxy + HTTPS"]
    end

    subgraph "Couche application"
        C1["admin/ :8789<br/>API administration<br/>45 contrôleurs"]
        C2["service/ :8792<br/>API métier côté C<br/>34 contrôleurs"]
    end

    subgraph "Couche services v2.0"
        D1["GameProvider<br/>Provider SDK<br/>Signature HMAC-SHA256"]
        D2["EventBus<br/>Redis Pub/Sub<br/>Diffusion d'événements asynchrone"]
        D3["Moteur VIP<br/>XP/montée de niveau/avantages"]
        D4["Moteur de succès<br/>12 succès intégrés"]
        D5["FeatureFlag<br/>Interrupteur de fonctionnalité"]
        D6["SdkSessionAuth<br/>Jeton de session signé HMAC"]
    end

    subgraph "Couche stockage"
        E1[("MySQL 8.0<br/>78 tables")]
        E2[("Redis 7.x<br/>Cache/limitation de débit/événements")]
        E3[("Elasticsearch<br/>Recherche plein texte")]
        E4[("ClickHouse<br/>Analyse OLAP")]
    end

    A1 & A2 & A3 --> B1
    B1 --> C1 & C2
    C1 & C2 --> D1 & D2 & D3 & D4 & D5 & D6
    C1 & C2 --> E1 & E2 & E3 & E4
```
