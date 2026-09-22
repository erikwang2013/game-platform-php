# ডেটা এনক্রিপশন লেয়ারিং
<!-- lang-nav -->

Languages: [中文](07-encryption-layers.md) · [English](07-encryption-layers.en.md) · [한국어](07-encryption-layers.ko.md) · [Русский](07-encryption-layers.ru.md) · [Deutsch](07-encryption-layers.de.md) · [Français](07-encryption-layers.fr.md) · [Español](07-encryption-layers.es.md) · [Português](07-encryption-layers.pt.md) · [हिन्दी](07-encryption-layers.hi.md) · [العربية](07-encryption-layers.ar.md) · **বাংলা** · [Bahasa Indonesia](07-encryption-layers.id.md) · [日本語](07-encryption-layers.ja.md)


```mermaid
flowchart TB
    subgraph transport["ট্রান্সপোর্ট লেয়ার এনক্রিপশন - encryption"]
        e1["ক্লায়েন্ট সেনসিটিভ ডেটা পাঠায়"]
        e2["AES-256-CBC এনক্রিপশন"]
        e3["API ট্রান্সপোর্ট সাইফারটেক্সট"]
        e4["সার্ভার ডিক্রিপ্ট প্রসেসিং"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["স্টোরেজ লেয়ার এনক্রিপশন - encryptable"]
        d1["Model casts কনফিগ<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["রাইটের সময় অটো এনক্রিপ্ট"]
        d3["MySQL VARCHAR(500) সাইফারটেক্সট সংরক্ষণ"]
        d4["রিডের সময় অটো ডিক্রিপ্ট"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["প্রেজেন্টেশন লেয়ার মাস্কিং"]
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
