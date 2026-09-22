# Arquitetura do Sistema (v2.0)
<!-- lang-nav -->

Languages: [中文](01-system-architecture.md) · [English](01-system-architecture.en.md) · [한국어](01-system-architecture.ko.md) · [Русский](01-system-architecture.ru.md) · [Deutsch](01-system-architecture.de.md) · [Français](01-system-architecture.fr.md) · [Español](01-system-architecture.es.md) · **Português** · [हिन्दी](01-system-architecture.hi.md) · [العربية](01-system-architecture.ar.md) · [বাংলা](01-system-architecture.bn.md) · [Bahasa Indonesia](01-system-architecture.id.md) · [日本語](01-system-architecture.ja.md)


```mermaid
flowchart TB
    subgraph "Camada de Clientes"
        A1["Flutter Web PC<br/>Painel administrativo"]
        A2["Flutter Web PC<br/>Plataforma do usuário C-side"]
        A3["HarmonyOS ArkTS<br/>Cliente mobile/tablet"]
    end

    subgraph "Camada de Gateway"
        B1["Nginx<br/>Proxy reverso + HTTPS"]
    end

    subgraph "Camada de Aplicação"
        C1["admin/ :8789<br/>API do painel administrativo<br/>45 controllers"]
        C2["service/ :8792<br/>API de negócio C-side<br/>34 controllers"]
    end

    subgraph "Camada de Serviços v2.0"
        D1["GameProvider<br/>Provider SDK<br/>Assinatura HMAC-SHA256"]
        D2["EventBus<br/>Redis Pub/Sub<br/>Distribuição assíncrona de eventos"]
        D3["Motor VIP<br/>EXP/nível/benefícios"]
        D4["Motor de conquistas<br/>12 conquistas integradas"]
        D5["FeatureFlag<br/>Chave de funcionalidades"]
        D6["SdkSessionAuth<br/>Token de sessão assinado com HMAC"]
    end

    subgraph "Camada de Armazenamento"
        E1[("MySQL 8.0<br/>78 tabelas")]
        E2[("Redis 7.x<br/>cache/limite/eventos")]
        E3[("Elasticsearch<br/>busca fulltext")]
        E4[("ClickHouse<br/>análise OLAP")]
    end

    A1 & A2 & A3 --> B1
    B1 --> C1 & C2
    C1 & C2 --> D1 & D2 & D3 & D4 & D5 & D6
    C1 & C2 --> E1 & E2 & E3 & E4
```
