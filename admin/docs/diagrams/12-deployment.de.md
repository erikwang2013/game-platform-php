# Bereitstellungsarchitektur (v2.0 — 7 Dienste)
<!-- lang-nav -->

Languages: **中文** · [English](12-deployment.en.md) · [한국어](12-deployment.ko.md) · [Русский](12-deployment.ru.md) · [Deutsch](12-deployment.de.md) · [Français](12-deployment.fr.md) · [Español](12-deployment.es.md) · [Português](12-deployment.pt.md) · [हिन्दी](12-deployment.hi.md) · [العربية](12-deployment.ar.md) · [বাংলা](12-deployment.bn.md) · [Bahasa Indonesia](12-deployment.id.md) · [日本語](12-deployment.ja.md)


```mermaid
flowchart TB
    subgraph "Einstieg"
        DNS["DNS: erik.xyz"]
    end

    subgraph "Nginx-Reverse-Proxy"
        NGX["HTTPS :443<br/>Routing-Verteilung + Gzip<br/>CSP + HSTS"]
    end

    subgraph "Anwendungsdienste"
        ADM["admin :8789<br/>Admin-Panel"]
        SVC["service :8792<br/>C-End-Geschäft"]
        LB["leaderboard-ws :8790<br/>WebSocket-Rangliste"]
        CHAT["chat-ws :8791<br/>WebSocket-Direktnachrichten"]
    end

    subgraph "Datendienste"
        MYSQL["MySQL 8.0 :3306<br/>78 Tabellen"]
        REDIS["Redis 7 :6379<br/>Cache/Ratenbegrenzung/EventBus"]
        ES["Elasticsearch :9200<br/>Volltextsuche"]
        CH["ClickHouse :8123<br/>OLAP-Analyse"]
    end

    subgraph "Monitoring"
        MON["Grafana + Prometheus<br/>Health-Check /metrics"]
    end

    DNS --> NGX
    NGX --> ADM & SVC & LB & CHAT
    ADM & SVC --> MYSQL & REDIS & ES & CH
    ADM & SVC --> MON
```
