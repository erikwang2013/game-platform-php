# مخطط بنية النظام (v2.0)
<!-- lang-nav -->

Languages: **中文** · [English](01-system-architecture.en.md) · [한국어](01-system-architecture.ko.md) · [Русский](01-system-architecture.ru.md) · [Deutsch](01-system-architecture.de.md) · [Français](01-system-architecture.fr.md) · [Español](01-system-architecture.es.md) · [Português](01-system-architecture.pt.md) · [हिन्दी](01-system-architecture.hi.md) · [العربية](01-system-architecture.ar.md) · [বাংলা](01-system-architecture.bn.md) · [Bahasa Indonesia](01-system-architecture.id.md) · [日本語](01-system-architecture.ja.md)


```mermaid
flowchart TB
    subgraph "طبقة العملاء"
        A1["Flutter Web PC<br/>لوحة الإدارة"]
        A2["Flutter Web PC<br/>منصة مستخدمي الطرف C"]
        A3["HarmonyOS ArkTS<br/>عميل الهاتف/الجهاز اللوحي"]
    end

    subgraph "طبقة البوابة"
        B1["Nginx<br/>وكيل عكسي + HTTPS"]
    end

    subgraph "طبقة التطبيق"
        C1["admin/ :8787<br/>إدارة API الخلفية<br/>28 وحدة تحكم"]
        C2["service/ :8788<br/>API أعمال الطرف C<br/>25 وحدة تحكم"]
    end

    subgraph "طبقة الخدمات v2.0"
        D1["GameProvider<br/>Provider SDK<br/>توقيع HMAC-SHA256"]
        D2["EventBus<br/>Redis Pub/Sub<br/>توزيع الأحداث غير المتزامن"]
        D3["محرك VIP<br/>خبرة/ترقية/امتيازات"]
        D4["محرك الإنجازات<br/>12 إنجازًا مدمجًا"]
        D5["FeatureFlag<br/>مفاتيح الميزات"]
        D6["GameSession<br/>نبض+كشف انتهاء المهلة"]
    end

    subgraph "طبقة التخزين"
        E1[("MySQL 8.0<br/>52 جدولًا")]
        E2[("Redis 7.x<br/>تخزين مؤقت/تقييد/أحداث")]
        E3[("Elasticsearch<br/>بحث نصي كامل")]
        E4[("ClickHouse<br/>تحليل OLAP")]
    end

    A1 & A2 & A3 --> B1
    B1 --> C1 & C2
    C1 & C2 --> D1 & D2 & D3 & D4 & D5 & D6
    C1 & C2 --> E1 & E2 & E3 & E4
```
