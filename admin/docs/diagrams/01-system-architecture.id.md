# Diagram Arsitektur Sistem (v2.0)
<!-- lang-nav -->

Languages: [中文](01-system-architecture.md) · [English](01-system-architecture.en.md) · [한국어](01-system-architecture.ko.md) · [Русский](01-system-architecture.ru.md) · [Deutsch](01-system-architecture.de.md) · [Français](01-system-architecture.fr.md) · [Español](01-system-architecture.es.md) · [Português](01-system-architecture.pt.md) · [हिन्दी](01-system-architecture.hi.md) · [العربية](01-system-architecture.ar.md) · [বাংলা](01-system-architecture.bn.md) · **Bahasa Indonesia** · [日本語](01-system-architecture.ja.md)


```mermaid
flowchart TB
    subgraph "Lapisan Klien"
        A1["Flutter Web PC<br/>Backend administrasi"]
        A2["Flutter Web PC<br/>Platform pengguna sisi C"]
        A3["HarmonyOS ArkTS<br/>Klien ponsel/tablet"]
    end

    subgraph "Lapisan gateway"
        B1["Nginx<br/>Reverse proxy + HTTPS"]
    end

    subgraph "Lapisan aplikasi"
        C1["admin/ :8789<br/>API backend administrasi<br/>45 controller"]
        C2["service/ :8792<br/>API bisnis sisi C<br/>34 controller"]
    end

    subgraph "Lapisan layanan v2.0"
        D1["GameProvider<br/>SDK Provider<br/>Tanda tangan HMAC-SHA256"]
        D2["EventBus<br/>Redis Pub/Sub<br/>Distribusi event asinkron"]
        D3["Mesin VIP<br/>EXP/upgrade/hak"]
        D4["Mesin pencapaian<br/>12 pencapaian bawaan"]
        D5["FeatureFlag<br/>Sakelar fitur"]
        D6["SdkSessionAuth<br/>Token sesi bertanda tangan HMAC"]
    end

    subgraph "Lapisan penyimpanan"
        E1[("MySQL 8.0<br/>78 tabel")]
        E2[("Redis 7.x<br/>Cache/pembatasan/event")]
        E3[("Elasticsearch<br/>Pencarian full-text")]
        E4[("ClickHouse<br/>Analisis OLAP")]
    end

    A1 & A2 & A3 --> B1
    B1 --> C1 & C2
    C1 & C2 --> D1 & D2 & D3 & D4 & D5 & D6
    C1 & C2 --> E1 & E2 & E3 & E4
```
