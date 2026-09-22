# 배포 아키텍처 (v2.0 — 7개 서비스)
<!-- lang-nav -->

Languages: [中文](12-deployment.md) · [English](12-deployment.en.md) · **한국어** · [Русский](12-deployment.ru.md) · [Deutsch](12-deployment.de.md) · [Français](12-deployment.fr.md) · [Español](12-deployment.es.md) · [Português](12-deployment.pt.md) · [हिन्दी](12-deployment.hi.md) · [العربية](12-deployment.ar.md) · [বাংলা](12-deployment.bn.md) · [Bahasa Indonesia](12-deployment.id.md) · [日本語](12-deployment.ja.md)


```mermaid
flowchart TB
    subgraph "진입점"
        DNS["DNS: erik.xyz"]
    end

    subgraph "Nginx 리버스 프록시"
        NGX["HTTPS :443<br/>라우팅 분배 + Gzip<br/>CSP + HSTS"]
    end

    subgraph "애플리케이션 서비스"
        ADM["admin :8789<br/>관리 백오피스"]
        SVC["service :8792<br/>C단 비즈니스"]
        LB["leaderboard-ws :8790<br/>WebSocket 랭킹"]
        CHAT["chat-ws :8791<br/>WebSocket 쪽지"]
    end

    subgraph "데이터 서비스"
        MYSQL["MySQL 8.0 :3306<br/>78장 테이블"]
        REDIS["Redis 7 :6379<br/>캐시/레이트 리밋/EventBus"]
        ES["Elasticsearch :9200<br/>전문 검색"]
        CH["ClickHouse :8123<br/>OLAP 분석"]
    end

    subgraph "모니터링"
        MON["Grafana + Prometheus<br/>헬스 체크 /metrics"]
    end

    DNS --> NGX
    NGX --> ADM & SVC & LB & CHAT
    ADM & SVC --> MYSQL & REDIS & ES & CH
    ADM & SVC --> MON
```
