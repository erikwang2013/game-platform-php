# 시스템 아키텍처 다이어그램 (v2.0)
<!-- lang-nav -->

Languages: [中文](01-system-architecture.md) · [English](01-system-architecture.en.md) · **한국어** · [Русский](01-system-architecture.ru.md) · [Deutsch](01-system-architecture.de.md) · [Français](01-system-architecture.fr.md) · [Español](01-system-architecture.es.md) · [Português](01-system-architecture.pt.md) · [हिन्दी](01-system-architecture.hi.md) · [العربية](01-system-architecture.ar.md) · [বাংলা](01-system-architecture.bn.md) · [Bahasa Indonesia](01-system-architecture.id.md) · [日本語](01-system-architecture.ja.md)


```mermaid
flowchart TB
    subgraph "클라이언트 레이어"
        A1["Flutter Web PC<br/>관리 백오피스"]
        A2["Flutter Web PC<br/>C단 사용자 플랫폼"]
        A3["HarmonyOS ArkTS<br/>모바일/태블릿 클라이언트"]
    end

    subgraph "게이트웨이 레이어"
        B1["Nginx<br/>리버스 프록시 + HTTPS"]
    end

    subgraph "애플리케이션 레이어"
        C1["admin/ :8789<br/>관리 백오피스 API<br/>컨트롤러 45개"]
        C2["service/ :8792<br/>C단 비즈니스 API<br/>컨트롤러 34개"]
    end

    subgraph "서비스 레이어 v2.0"
        D1["GameProvider<br/>Provider SDK<br/>HMAC-SHA256 서명"]
        D2["EventBus<br/>Redis Pub/Sub<br/>비동기 이벤트 분배"]
        D3["VIP 엔진<br/>경험치/승급/혜택"]
        D4["업적 엔진<br/>내장 업적 12개"]
        D5["FeatureFlag<br/>기능 스위치"]
        D6["SdkSessionAuth<br/>HMAC 서명 세션 토큰"]
    end

    subgraph "저장 레이어"
        E1[("MySQL 8.0<br/>78장 테이블")]
        E2[("Redis 7.x<br/>캐시/레이트 리밋/이벤트")]
        E3[("Elasticsearch<br/>전문 검색")]
        E4[("ClickHouse<br/>OLAP 분석")]
    end

    A1 & A2 & A3 --> B1
    B1 --> C1 & C2
    C1 & C2 --> D1 & D2 & D3 & D4 & D5 & D6
    C1 & C2 --> E1 & E2 & E3 & E4
```
