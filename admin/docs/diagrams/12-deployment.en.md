# Deployment Architecture (v2.0 — 7 Services)
<!-- lang-nav -->

Languages: [中文](12-deployment.md) · **English** · [한국어](12-deployment.ko.md) · [Русский](12-deployment.ru.md) · [Deutsch](12-deployment.de.md) · [Français](12-deployment.fr.md) · [Español](12-deployment.es.md) · [Português](12-deployment.pt.md) · [हिन्दी](12-deployment.hi.md) · [العربية](12-deployment.ar.md) · [বাংলা](12-deployment.bn.md) · [Bahasa Indonesia](12-deployment.id.md) · [日本語](12-deployment.ja.md)


```mermaid
flowchart TB
    subgraph "Entry"
        DNS["DNS: erik.xyz"]
    end

    subgraph "Nginx Reverse Proxy"
        NGX["HTTPS :443<br/>Route dispatch + Gzip<br/>CSP + HSTS"]
    end

    subgraph "Application Services"
        ADM["admin :8789<br/>Admin panel"]
        SVC["service :8792<br/>C-end business"]
        LB["leaderboard-ws :8790<br/>WebSocket leaderboard"]
        CHAT["chat-ws :8791<br/>WebSocket direct messages"]
    end

    subgraph "Data Services"
        MYSQL["MySQL 8.0 :3306<br/>78 tables"]
        REDIS["Redis 7 :6379<br/>Cache/rate limiting/EventBus"]
        ES["Elasticsearch :9200<br/>Full-text search"]
        CH["ClickHouse :8123<br/>OLAP analysis"]
    end

    subgraph "Monitoring"
        MON["Grafana + Prometheus<br/>Health check /metrics"]
    end

    DNS --> NGX
    NGX --> ADM & SVC & LB & CHAT
    ADM & SVC --> MYSQL & REDIS & ES & CH
    ADM & SVC --> MON
```
