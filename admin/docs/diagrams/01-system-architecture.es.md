# Diagrama de arquitectura del sistema (v2.0)
<!-- lang-nav -->

Languages: [中文](01-system-architecture.md) · [English](01-system-architecture.en.md) · [한국어](01-system-architecture.ko.md) · [Русский](01-system-architecture.ru.md) · [Deutsch](01-system-architecture.de.md) · [Français](01-system-architecture.fr.md) · **Español** · [Português](01-system-architecture.pt.md) · [हिन्दी](01-system-architecture.hi.md) · [العربية](01-system-architecture.ar.md) · [বাংলা](01-system-architecture.bn.md) · [Bahasa Indonesia](01-system-architecture.id.md) · [日本語](01-system-architecture.ja.md)


```mermaid
flowchart TB
    subgraph "Capa de clientes"
        A1["Flutter Web PC<br/>Panel de administración"]
        A2["Flutter Web PC<br/>Plataforma de usuario final"]
        A3["HarmonyOS ArkTS<br/>Cliente móvil/tableta"]
    end

    subgraph "Capa de pasarela"
        B1["Nginx<br/>Proxy inverso + HTTPS"]
    end

    subgraph "Capa de aplicación"
        C1["admin/ :8789<br/>API de administración<br/>45 controladores"]
        C2["service/ :8792<br/>API de negocio final<br/>34 controladores"]
    end

    subgraph "Capa de servicios v2.0"
        D1["GameProvider<br/>SDK de proveedor<br/>firma HMAC-SHA256"]
        D2["EventBus<br/>Redis Pub/Sub<br/>despacho asíncrono de eventos"]
        D3["Motor VIP<br/>experiencia/niveles/beneficios"]
        D4["Motor de logros<br/>12 logros integrados"]
        D5["FeatureFlag<br/>interruptores de funciones"]
        D6["SdkSessionAuth<br/>token de sesión firmado con HMAC"]
    end

    subgraph "Capa de datos"
        E1[("MySQL 8.0<br/>78 tablas")]
        E2[("Redis 7.x<br/>caché/límite de frecuencia/eventos")]
        E3[("Elasticsearch<br/>búsqueda de texto completo")]
        E4[("ClickHouse<br/>análisis OLAP")]
    end

    A1 & A2 & A3 --> B1
    B1 --> C1 & C2
    C1 & C2 --> D1 & D2 & D3 & D4 & D5 & D6
    C1 & C2 --> E1 & E2 & E3 & E4
```
