# بنية النشر (v2.0 — 8 خدمات)
<!-- lang-nav -->

Languages: **中文** · [English](12-deployment.en.md) · [한국어](12-deployment.ko.md) · [Русский](12-deployment.ru.md) · [Deutsch](12-deployment.de.md) · [Français](12-deployment.fr.md) · [Español](12-deployment.es.md) · [Português](12-deployment.pt.md) · [हिन्दी](12-deployment.hi.md) · [العربية](12-deployment.ar.md) · [বাংলা](12-deployment.bn.md) · [Bahasa Indonesia](12-deployment.id.md) · [日本語](12-deployment.ja.md)


```mermaid
flowchart TB
    subgraph "المدخل"
        DNS["DNS: erik.xyz"]
    end

    subgraph "الوكيل العكسي Nginx"
        NGX["HTTPS :443<br/>توزيع التوجيه + Gzip<br/>CSP + HSTS"]
    end

    subgraph "خدمات التطبيق"
        ADM["admin :8787<br/>لوحة الإدارة"]
        SVC["service :8788<br/>أعمال الطرف C"]
        LB["leaderboard-ws :8789<br/>WebSocket لوحة المتصدرين"]
        CHAT["chat-ws :8790<br/>WebSocket الرسائل الخاصة"]
    end

    subgraph "خدمات البيانات"
        MYSQL["MySQL 8.0 :3306<br/>52 جدولًا"]
        REDIS["Redis 7 :6379<br/>تخزين مؤقت/تقييد/EventBus"]
        ES["Elasticsearch :9200<br/>بحث نصي كامل"]
        CH["ClickHouse :8123<br/>تحليل OLAP"]
    end

    subgraph "المراقبة"
        MON["Grafana + Prometheus<br/>فحص الصحة /metrics"]
    end

    DNS --> NGX
    NGX --> ADM & SVC & LB & CHAT
    ADM & SVC --> MYSQL & REDIS & ES & CH
    ADM & SVC --> MON
```
