# Arquitetura de Implantação (v2.0 — 7 serviços)
<!-- lang-nav -->

Languages: [中文](12-deployment.md) · [English](12-deployment.en.md) · [한국어](12-deployment.ko.md) · [Русский](12-deployment.ru.md) · [Deutsch](12-deployment.de.md) · [Français](12-deployment.fr.md) · [Español](12-deployment.es.md) · **Português** · [हिन्दी](12-deployment.hi.md) · [العربية](12-deployment.ar.md) · [বাংলা](12-deployment.bn.md) · [Bahasa Indonesia](12-deployment.id.md) · [日本語](12-deployment.ja.md)


```mermaid
flowchart TB
    subgraph "Entrada"
        DNS["DNS: erik.xyz"]
    end

    subgraph "Nginx proxy reverso"
        NGX["HTTPS :443<br/>Roteamento + Gzip<br/>CSP + HSTS"]
    end

    subgraph "Serviços de aplicação"
        ADM["admin :8789<br/>Painel administrativo"]
        SVC["service :8792<br/>Negócio C-side"]
        LB["leaderboard-ws :8790<br/>WebSocket de ranking"]
        CHAT["chat-ws :8791<br/>WebSocket de mensagens privadas"]
    end

    subgraph "Serviços de dados"
        MYSQL["MySQL 8.0 :3306<br/>78 tabelas"]
        REDIS["Redis 7 :6379<br/>cache/limite/EventBus"]
        ES["Elasticsearch :9200<br/>busca fulltext"]
        CH["ClickHouse :8123<br/>análise OLAP"]
    end

    subgraph "Monitoramento"
        MON["Grafana + Prometheus<br/>verificação de saúde /metrics"]
    end

    DNS --> NGX
    NGX --> ADM & SVC & LB & CHAT
    ADM & SVC --> MYSQL & REDIS & ES & CH
    ADM & SVC --> MON
```
