# ডিপ্লয়মেন্ট আর্কিটেকচার (v2.0 — ৭ সার্ভিস)
<!-- lang-nav -->

Languages: [中文](12-deployment.md) · [English](12-deployment.en.md) · [한국어](12-deployment.ko.md) · [Русский](12-deployment.ru.md) · [Deutsch](12-deployment.de.md) · [Français](12-deployment.fr.md) · [Español](12-deployment.es.md) · [Português](12-deployment.pt.md) · [हिन्दी](12-deployment.hi.md) · [العربية](12-deployment.ar.md) · **বাংলা** · [Bahasa Indonesia](12-deployment.id.md) · [日本語](12-deployment.ja.md)


```mermaid
flowchart TB
    subgraph "এন্ট্রি"
        DNS["DNS: erik.xyz"]
    end

    subgraph "Nginx রিভার্স প্রক্সি"
        NGX["HTTPS :443<br/>রাউট ডিস্ট্রিবিউশন + Gzip<br/>CSP + HSTS"]
    end

    subgraph "অ্যাপ্লিকেশন সার্ভিস"
        ADM["admin :8789<br/>অ্যাডমিন প্যানেল"]
        SVC["service :8792<br/>C-এন্ড বিজনেস"]
        LB["leaderboard-ws :8790<br/>WebSocket লিডারবোর্ড"]
        CHAT["chat-ws :8791<br/>WebSocket প্রাইভেট মেসেজ"]
    end

    subgraph "ডেটা সার্ভিস"
        MYSQL["MySQL 8.0 :3306<br/>৭৮টি টেবিল"]
        REDIS["Redis 7 :6379<br/>ক্যাশ/রেট লিমিট/EventBus"]
        ES["Elasticsearch :9200<br/>ফুলটেক্সট সার্চ"]
        CH["ClickHouse :8123<br/>OLAP বিশ্লেষণ"]
    end

    subgraph "মনিটরিং"
        MON["Grafana + Prometheus<br/>হেলথ চেক /metrics"]
    end

    DNS --> NGX
    NGX --> ADM & SVC & LB & CHAT
    ADM & SVC --> MYSQL & REDIS & ES & CH
    ADM & SVC --> MON
```
