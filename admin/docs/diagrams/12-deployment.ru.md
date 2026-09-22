# Архитектура развёртывания (v2.0 — 7 сервисов)
<!-- lang-nav -->

Languages: **中文** · [English](12-deployment.en.md) · [한국어](12-deployment.ko.md) · [Русский](12-deployment.ru.md) · [Deutsch](12-deployment.de.md) · [Français](12-deployment.fr.md) · [Español](12-deployment.es.md) · [Português](12-deployment.pt.md) · [हिन्दी](12-deployment.hi.md) · [العربية](12-deployment.ar.md) · [বাংলা](12-deployment.bn.md) · [Bahasa Indonesia](12-deployment.id.md) · [日本語](12-deployment.ja.md)


```mermaid
flowchart TB
    subgraph "Точка входа"
        DNS["DNS: erik.xyz"]
    end

    subgraph "Nginx обратный прокси"
        NGX["HTTPS :443<br/>Маршрутизация + Gzip<br/>CSP + HSTS"]
    end

    subgraph "Сервисы приложений"
        ADM["admin :8789<br/>Админ-панель"]
        SVC["service :8792<br/>Бизнес C-стороны"]
        LB["leaderboard-ws :8790<br/>WebSocket рейтинга"]
        CHAT["chat-ws :8791<br/>WebSocket личных сообщений"]
    end

    subgraph "Сервисы данных"
        MYSQL["MySQL 8.0 :3306<br/>78 таблиц"]
        REDIS["Redis 7 :6379<br/>Кэш/лимиты/EventBus"]
        ES["Elasticsearch :9200<br/>Полнотекстовый поиск"]
        CH["ClickHouse :8123<br/>Анализ OLAP"]
    end

    subgraph "Мониторинг"
        MON["Grafana + Prometheus<br/>Проверка здоровья /metrics"]
    end

    DNS --> NGX
    NGX --> ADM & SVC & LB & CHAT
    ADM & SVC --> MYSQL & REDIS & ES & CH
    ADM & SVC --> MON
```
