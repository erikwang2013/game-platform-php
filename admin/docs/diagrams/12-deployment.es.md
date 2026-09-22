# Arquitectura de despliegue (v2.0 — 7 servicios)
<!-- lang-nav -->

Languages: [中文](12-deployment.md) · [English](12-deployment.en.md) · [한국어](12-deployment.ko.md) · [Русский](12-deployment.ru.md) · [Deutsch](12-deployment.de.md) · [Français](12-deployment.fr.md) · **Español** · [Português](12-deployment.pt.md) · [हिन्दी](12-deployment.hi.md) · [العربية](12-deployment.ar.md) · [বাংলা](12-deployment.bn.md) · [Bahasa Indonesia](12-deployment.id.md) · [日本語](12-deployment.ja.md)


```mermaid
flowchart TB
    subgraph "Entrada"
        DNS["DNS: erik.xyz"]
    end

    subgraph "Proxy inverso Nginx"
        NGX["HTTPS :443<br/>Distribución de rutas + Gzip<br/>CSP + HSTS"]
    end

    subgraph "Servicios de aplicación"
        ADM["admin :8789<br/>Panel de administración"]
        SVC["service :8792<br/>Negocio final"]
        LB["leaderboard-ws :8790<br/>Clasificación WebSocket"]
        CHAT["chat-ws :8791<br/>Mensajes directos por WebSocket"]
    end

    subgraph "Servicios de datos"
        MYSQL["MySQL 8.0 :3306<br/>78 tablas"]
        REDIS["Redis 7 :6379<br/>caché/límite de frecuencia/EventBus"]
        ES["Elasticsearch :9200<br/>búsqueda de texto completo"]
        CH["ClickHouse :8123<br/>análisis OLAP"]
    end

    subgraph "Monitorización"
        MON["Grafana + Prometheus<br/>Comprobación de estado /metrics"]
    end

    DNS --> NGX
    NGX --> ADM & SVC & LB & CHAT
    ADM & SVC --> MYSQL & REDIS & ES & CH
    ADM & SVC --> MON
```
