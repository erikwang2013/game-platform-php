# Diagrama de arquitectura del sistema (v2.0)
<!-- lang-nav -->

Languages: [中文](01-system-architecture.md) · [English](01-system-architecture.en.md) · [한국어](01-system-architecture.ko.md) · [Русский](01-system-architecture.ru.md) · [Deutsch](01-system-architecture.de.md) · [Français](01-system-architecture.fr.md) · **Español** · [Português](01-system-architecture.pt.md) · [हिन्दी](01-system-architecture.hi.md) · [العربية](01-system-architecture.ar.md) · [বাংলা](01-system-architecture.bn.md) · [Bahasa Indonesia](01-system-architecture.id.md) · [日本語](01-system-architecture.ja.md)


```mermaid
flowchart TB
    subgraph "客户端层"
        A1["Flutter Web PC<br/>管理后台"]
        A2["Flutter Web PC<br/>C端用户平台"]
        A3["HarmonyOS ArkTS<br/>手机/平板客户端"]
    end

    subgraph "网关层"
        B1["Nginx<br/>反向代理 + HTTPS"]
    end

    subgraph "应用层"
        C1["admin/ :8787<br/>管理后台 API<br/>28 控制器"]
        C2["service/ :8788<br/>C端业务 API<br/>25 控制器"]
    end

    subgraph "服务层 v2.0"
        D1["GameProvider<br/>Provider SDK<br/>HMAC-SHA256 签名"]
        D2["EventBus<br/>Redis Pub/Sub<br/>异步事件分发"]
        D3["VIP 引擎<br/>经验值/升级/权益"]
        D4["成就引擎<br/>12 内置成就"]
        D5["FeatureFlag<br/>特性开关"]
        D6["GameSession<br/>心跳+超时检测"]
    end

    subgraph "存储层"
        E1[("MySQL 8.0<br/>52 张表")]
        E2[("Redis 7.x<br/>缓存/限流/事件")]
        E3[("Elasticsearch<br/>全文检索")]
        E4[("ClickHouse<br/>OLAP 分析")]
    end

    A1 & A2 & A3 --> B1
    B1 --> C1 & C2
    C1 & C2 --> D1 & D2 & D3 & D4 & D5 & D6
    C1 & C2 --> E1 & E2 & E3 & E4
```
