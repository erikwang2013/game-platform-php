# طبقات تشفير البيانات
<!-- lang-nav -->

Languages: **中文** · [English](07-encryption-layers.en.md) · [한국어](07-encryption-layers.ko.md) · [Русский](07-encryption-layers.ru.md) · [Deutsch](07-encryption-layers.de.md) · [Français](07-encryption-layers.fr.md) · [Español](07-encryption-layers.es.md) · [Português](07-encryption-layers.pt.md) · [हिन्दी](07-encryption-layers.hi.md) · [العربية](07-encryption-layers.ar.md) · [বাংলা](07-encryption-layers.bn.md) · [Bahasa Indonesia](07-encryption-layers.id.md) · [日本語](07-encryption-layers.ja.md)


```mermaid
flowchart TB
    subgraph transport["تشفير طبقة النقل - encryption"]
        e1["العميل يرسل بيانات حساسة"]
        e2["تشفير AES-256-CBC"]
        e3["نقل النص المشفر عبر API"]
        e4["الخادم يفك التشفير ويعالج"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["تشفير طبقة التخزين - encryptable"]
        d1["إعدادات Model casts<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["تشفير تلقائي عند الكتابة"]
        d3["تخزين النص المشفر في MySQL VARCHAR(500)"]
        d4["فك تشفير تلقائي عند القراءة"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["إخفاء البيانات في العرض"]
        m1["phone: 138****1234"]
        m2["email: a***@example.com"]
        m3["id_card: ********"]
        d4 --> m1 & m2 & m3
    end

    e4 --> d1

    style e2 fill:#1677FF,color:#fff
    style d2 fill:#FA8C16,color:#fff
    style m1 fill:#52C41A,color:#fff
```
