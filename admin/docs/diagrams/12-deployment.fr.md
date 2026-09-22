# Architecture de déploiement (v2.0 — 7 services)
<!-- lang-nav -->

Languages: [中文](12-deployment.md) · [English](12-deployment.en.md) · [한국어](12-deployment.ko.md) · [Русский](12-deployment.ru.md) · [Deutsch](12-deployment.de.md) · **Français** · [Español](12-deployment.es.md) · [Português](12-deployment.pt.md) · [हिन्दी](12-deployment.hi.md) · [العربية](12-deployment.ar.md) · [বাংলা](12-deployment.bn.md) · [Bahasa Indonesia](12-deployment.id.md) · [日本語](12-deployment.ja.md)


```mermaid
flowchart TB
    subgraph "Entrée"
        DNS["DNS: erik.xyz"]
    end

    subgraph "Reverse proxy Nginx"
        NGX["HTTPS :443<br/>Routage + Gzip<br/>CSP + HSTS"]
    end

    subgraph "Services applicatifs"
        ADM["admin :8789<br/>Administration"]
        SVC["service :8792<br/>Métier côté C"]
        LB["leaderboard-ws :8790<br/>Classement WebSocket"]
        CHAT["chat-ws :8791<br/>Messagerie privée WebSocket"]
    end

    subgraph "Services de données"
        MYSQL["MySQL 8.0 :3306<br/>78 tables"]
        REDIS["Redis 7 :6379<br/>Cache/limitation de débit/EventBus"]
        ES["Elasticsearch :9200<br/>Recherche plein texte"]
        CH["ClickHouse :8123<br/>Analyse OLAP"]
    end

    subgraph "Surveillance"
        MON["Grafana + Prometheus<br/>Contrôles de santé /metrics"]
    end

    DNS --> NGX
    NGX --> ADM & SVC & LB & CHAT
    ADM & SVC --> MYSQL & REDIS & ES & CH
    ADM & SVC --> MON
```
