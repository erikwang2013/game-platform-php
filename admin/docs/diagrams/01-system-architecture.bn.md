# সিস্টেম আর্কিটেকচার ডায়াগ্রাম (v2.0)
<!-- lang-nav -->

Languages: [中文](01-system-architecture.md) · [English](01-system-architecture.en.md) · [한국어](01-system-architecture.ko.md) · [Русский](01-system-architecture.ru.md) · [Deutsch](01-system-architecture.de.md) · [Français](01-system-architecture.fr.md) · [Español](01-system-architecture.es.md) · [Português](01-system-architecture.pt.md) · [हिन्दी](01-system-architecture.hi.md) · [العربية](01-system-architecture.ar.md) · **বাংলা** · [Bahasa Indonesia](01-system-architecture.id.md) · [日本語](01-system-architecture.ja.md)


```mermaid
flowchart TB
    subgraph "ক্লায়েন্ট লেয়ার"
        A1["Flutter Web PC<br/>অ্যাডমিন প্যানেল"]
        A2["Flutter Web PC<br/>C-এন্ড ইউজার প্ল্যাটফর্ম"]
        A3["HarmonyOS ArkTS<br/>মোবাইল/ট্যাবলেট ক্লায়েন্ট"]
    end

    subgraph "গেটওয়ে লেয়ার"
        B1["Nginx<br/>রিভার্স প্রক্সি + HTTPS"]
    end

    subgraph "অ্যাপ্লিকেশন লেয়ার"
        C1["admin/ :8789<br/>অ্যাডমিন প্যানেল API<br/>৪৫টি কন্ট্রোলার"]
        C2["service/ :8792<br/>C-এন্ড বিজনেস API<br/>৩৪টি কন্ট্রোলার"]
    end

    subgraph "সার্ভিস লেয়ার v2.0"
        D1["GameProvider<br/>Provider SDK<br/>HMAC-SHA256 সিগনেচার"]
        D2["EventBus<br/>Redis Pub/Sub<br/>অ্যাসিনক্রোনাস ইভেন্ট ডিস্ট্রিবিউশন"]
        D3["VIP ইঞ্জিন<br/>অভিজ্ঞতা/আপগ্রেড/বেনিফিট"]
        D4["অ্যাচিভমেন্ট ইঞ্জিন<br/>১২টি বিল্ট-ইন অ্যাচিভমেন্ট"]
        D5["FeatureFlag<br/>ফিচার ফ্ল্যাগ"]
        D6["SdkSessionAuth<br/>HMAC স্বাক্ষরিত সেশন টোকেন"]
    end

    subgraph "স্টোরেজ লেয়ার"
        E1[("MySQL 8.0<br/>৭৮টি টেবিল")]
        E2[("Redis 7.x<br/>ক্যাশ/রেট লিমিট/ইভেন্ট")]
        E3[("Elasticsearch<br/>ফুলটেক্সট সার্চ")]
        E4[("ClickHouse<br/>OLAP বিশ্লেষণ")]
    end

    A1 & A2 & A3 --> B1
    B1 --> C1 & C2
    C1 & C2 --> D1 & D2 & D3 & D4 & D5 & D6
    C1 & C2 --> E1 & E2 & E3 & E4
```
