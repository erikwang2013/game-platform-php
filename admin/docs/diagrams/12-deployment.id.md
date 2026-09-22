# Arsitektur Deployment (v2.0 — 7 Layanan)
<!-- lang-nav -->

Languages: [中文](12-deployment.md) · [English](12-deployment.en.md) · [한국어](12-deployment.ko.md) · [Русский](12-deployment.ru.md) · [Deutsch](12-deployment.de.md) · [Français](12-deployment.fr.md) · [Español](12-deployment.es.md) · [Português](12-deployment.pt.md) · [हिन्दी](12-deployment.hi.md) · [العربية](12-deployment.ar.md) · [বাংলা](12-deployment.bn.md) · **Bahasa Indonesia** · [日本語](12-deployment.ja.md)


```mermaid
flowchart TB
    subgraph "Titik masuk"
        DNS["DNS: erik.xyz"]
    end

    subgraph "Reverse proxy Nginx"
        NGX["HTTPS :443<br/>Distribusi rute + Gzip<br/>CSP + HSTS"]
    end

    subgraph "Layanan aplikasi"
        ADM["admin :8789<br/>Backend administrasi"]
        SVC["service :8792<br/>Bisnis sisi C"]
        LB["leaderboard-ws :8790<br/>Papan peringkat WebSocket"]
        CHAT["chat-ws :8791<br/>Pesan pribadi WebSocket"]
    end

    subgraph "Layanan data"
        MYSQL["MySQL 8.0 :3306<br/>78 tabel"]
        REDIS["Redis 7 :6379<br/>Cache/pembatasan/EventBus"]
        ES["Elasticsearch :9200<br/>Pencarian full-text"]
        CH["ClickHouse :8123<br/>Analisis OLAP"]
    end

    subgraph "Monitoring"
        MON["Grafana + Prometheus<br/>Pemeriksaan kesehatan /metrics"]
    end

    DNS --> NGX
    NGX --> ADM & SVC & LB & CHAT
    ADM & SVC --> MYSQL & REDIS & ES & CH
    ADM & SVC --> MON
```
