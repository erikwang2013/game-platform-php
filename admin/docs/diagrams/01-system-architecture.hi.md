# सिस्टम आर्किटेक्चर आरेख (v2.0)
<!-- lang-nav -->

Languages: [中文](01-system-architecture.md) · [English](01-system-architecture.en.md) · [한국어](01-system-architecture.ko.md) · [Русский](01-system-architecture.ru.md) · [Deutsch](01-system-architecture.de.md) · [Français](01-system-architecture.fr.md) · [Español](01-system-architecture.es.md) · [Português](01-system-architecture.pt.md) · **हिन्दी** · [العربية](01-system-architecture.ar.md) · [বাংলা](01-system-architecture.bn.md) · [Bahasa Indonesia](01-system-architecture.id.md) · [日本語](01-system-architecture.ja.md)


```mermaid
flowchart TB
    subgraph "क्लाइंट परत"
        A1["Flutter Web PC<br/>प्रशासन कंसोल"]
        A2["Flutter Web PC<br/>C-छोर उपयोगकर्ता प्लेटफ़ॉर्म"]
        A3["HarmonyOS ArkTS<br/>मोबाइल/टैबलेट क्लाइंट"]
    end

    subgraph "गेटवे परत"
        B1["Nginx<br/>रिवर्स प्रॉक्सी + HTTPS"]
    end

    subgraph "एप्लिकेशन परत"
        C1["admin/ :8789<br/>प्रशासन कंसोल API<br/>45 कंट्रोलर"]
        C2["service/ :8792<br/>C-छोर व्यवसाय API<br/>34 कंट्रोलर"]
    end

    subgraph "सेवा परत v2.0"
        D1["GameProvider<br/>Provider SDK<br/>HMAC-SHA256 हस्ताक्षर"]
        D2["EventBus<br/>Redis Pub/Sub<br/>अतुल्यकालिक इवेंट वितरण"]
        D3["VIP इंजन<br/>अनुभव/उन्नयन/लाभ"]
        D4["उपलब्धि इंजन<br/>12 अंतर्निहित उपलब्धियाँ"]
        D5["FeatureFlag<br/>फ़ीचर स्विच"]
        D6["SdkSessionAuth<br/>HMAC हस्ताक्षरित सत्र टोकन"]
    end

    subgraph "भंडारण परत"
        E1[("MySQL 8.0<br/>78 तालिकाएँ")]
        E2[("Redis 7.x<br/>कैश/दर सीमा/इवेंट")]
        E3[("Elasticsearch<br/>पूर्ण-पाठ खोज")]
        E4[("ClickHouse<br/>OLAP विश्लेषण")]
    end

    A1 & A2 & A3 --> B1
    B1 --> C1 & C2
    C1 & C2 --> D1 & D2 & D3 & D4 & D5 & D6
    C1 & C2 --> E1 & E2 & E3 & E4
```
