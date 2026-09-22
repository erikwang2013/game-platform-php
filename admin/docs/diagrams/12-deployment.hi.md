# परिनियोजन आर्किटेक्चर (v2.0 — 7 सेवाएँ)
<!-- lang-nav -->

Languages: [中文](12-deployment.md) · [English](12-deployment.en.md) · [한국어](12-deployment.ko.md) · [Русский](12-deployment.ru.md) · [Deutsch](12-deployment.de.md) · [Français](12-deployment.fr.md) · [Español](12-deployment.es.md) · [Português](12-deployment.pt.md) · **हिन्दी** · [العربية](12-deployment.ar.md) · [বাংলা](12-deployment.bn.md) · [Bahasa Indonesia](12-deployment.id.md) · [日本語](12-deployment.ja.md)


```mermaid
flowchart TB
    subgraph "प्रवेश"
        DNS["DNS: erik.xyz"]
    end

    subgraph "Nginx रिवर्स प्रॉक्सी"
        NGX["HTTPS :443<br/>रूट वितरण + Gzip<br/>CSP + HSTS"]
    end

    subgraph "एप्लिकेशन सेवा"
        ADM["admin :8789<br/>प्रशासन कंसोल"]
        SVC["service :8792<br/>C-छोर व्यवसाय"]
        LB["leaderboard-ws :8790<br/>WebSocket लीडरबोर्ड"]
        CHAT["chat-ws :8791<br/>WebSocket निजी संदेश"]
    end

    subgraph "डेटा सेवा"
        MYSQL["MySQL 8.0 :3306<br/>78 तालिकाएँ"]
        REDIS["Redis 7 :6379<br/>कैश/दर सीमा/EventBus"]
        ES["Elasticsearch :9200<br/>पूर्ण-पाठ खोज"]
        CH["ClickHouse :8123<br/>OLAP विश्लेषण"]
    end

    subgraph "मॉनिटरिंग"
        MON["Grafana + Prometheus<br/>हेल्थ चेक /metrics"]
    end

    DNS --> NGX
    NGX --> ADM & SVC & LB & CHAT
    ADM & SVC --> MYSQL & REDIS & ES & CH
    ADM & SVC --> MON
```
