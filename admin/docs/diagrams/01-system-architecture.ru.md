# Архитектура системы (v2.0)
<!-- lang-nav -->

Languages: **中文** · [English](01-system-architecture.en.md) · [한국어](01-system-architecture.ko.md) · [Русский](01-system-architecture.ru.md) · [Deutsch](01-system-architecture.de.md) · [Français](01-system-architecture.fr.md) · [Español](01-system-architecture.es.md) · [Português](01-system-architecture.pt.md) · [हिन्दी](01-system-architecture.hi.md) · [العربية](01-system-architecture.ar.md) · [বাংলা](01-system-architecture.bn.md) · [Bahasa Indonesia](01-system-architecture.id.md) · [日本語](01-system-architecture.ja.md)


```mermaid
flowchart TB
    subgraph "Клиентский слой"
        A1["Flutter Web PC<br/>Админ-панель"]
        A2["Flutter Web PC<br/>Пользовательская платформа C-стороны"]
        A3["HarmonyOS ArkTS<br/>Клиент смартфон/планшет"]
    end

    subgraph "Слой шлюза"
        B1["Nginx<br/>Обратный прокси + HTTPS"]
    end

    subgraph "Слой приложения"
        C1["admin/ :8789<br/>API админ-панели<br/>45 контроллеров"]
        C2["service/ :8792<br/>Бизнес-API C-стороны<br/>34 контроллера"]
    end

    subgraph "Слой сервисов v2.0"
        D1["GameProvider<br/>Provider SDK<br/>Подпись HMAC-SHA256"]
        D2["EventBus<br/>Redis Pub/Sub<br/>Асинхронная доставка событий"]
        D3["VIP-движок<br/>Опыт/повышение уровня/привилегии"]
        D4["Движок достижений<br/>12 встроенных достижений"]
        D5["FeatureFlag<br/>Переключатель функций"]
        D6["SdkSessionAuth<br/>Подписанный HMAC сессионный токен"]
    end

    subgraph "Слой хранения"
        E1[("MySQL 8.0<br/>78 таблиц")]
        E2[("Redis 7.x<br/>Кэш/лимиты/события")]
        E3[("Elasticsearch<br/>Полнотекстовый поиск")]
        E4[("ClickHouse<br/>Анализ OLAP")]
    end

    A1 & A2 & A3 --> B1
    B1 --> C1 & C2
    C1 & C2 --> D1 & D2 & D3 & D4 & D5 & D6
    C1 & C2 --> E1 & E2 & E3 & E4
```
