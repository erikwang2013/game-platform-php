# Systemarchitektur-Diagramm (v2.0)
<!-- lang-nav -->

Languages: **中文** · [English](01-system-architecture.en.md) · [한국어](01-system-architecture.ko.md) · [Русский](01-system-architecture.ru.md) · [Deutsch](01-system-architecture.de.md) · [Français](01-system-architecture.fr.md) · [Español](01-system-architecture.es.md) · [Português](01-system-architecture.pt.md) · [हिन्दी](01-system-architecture.hi.md) · [العربية](01-system-architecture.ar.md) · [বাংলা](01-system-architecture.bn.md) · [Bahasa Indonesia](01-system-architecture.id.md) · [日本語](01-system-architecture.ja.md)


```mermaid
flowchart TB
    subgraph "Client-Ebene"
        A1["Flutter Web PC<br/>Admin-Panel"]
        A2["Flutter Web PC<br/>C-End-Nutzerplattform"]
        A3["HarmonyOS ArkTS<br/>Handy-/Tablet-Client"]
    end

    subgraph "Gateway-Ebene"
        B1["Nginx<br/>Reverse-Proxy + HTTPS"]
    end

    subgraph "Anwendungsebene"
        C1["admin/ :8789<br/>Admin-API<br/>45 Controller"]
        C2["service/ :8792<br/>C-End-Geschäfts-API<br/>34 Controller"]
    end

    subgraph "Service-Ebene v2.0"
        D1["GameProvider<br/>Provider-SDK<br/>HMAC-SHA256-Signatur"]
        D2["EventBus<br/>Redis Pub/Sub<br/>asynchroner Ereignisversand"]
        D3["VIP-Engine<br/>Erfahrung/Stufen/Vorteile"]
        D4["Erfolgs-Engine<br/>12 integrierte Erfolge"]
        D5["FeatureFlag<br/>Feature-Flags"]
        D6["SdkSessionAuth<br/>HMAC-signiertes Session-Token"]
    end

    subgraph "Datenschicht"
        E1[("MySQL 8.0<br/>78 Tabellen")]
        E2[("Redis 7.x<br/>Cache/Ratenbegrenzung/Ereignisse")]
        E3[("Elasticsearch<br/>Volltextsuche")]
        E4[("ClickHouse<br/>OLAP-Analyse")]
    end

    A1 & A2 & A3 --> B1
    B1 --> C1 & C2
    C1 & C2 --> D1 & D2 & D3 & D4 & D5 & D6
    C1 & C2 --> E1 & E2 & E3 & E4
```
