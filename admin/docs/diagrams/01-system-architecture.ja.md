# システムアーキテクチャ図 (v2.0)
<!-- lang-nav -->

Languages: **中文** · [English](01-system-architecture.en.md) · [한국어](01-system-architecture.ko.md) · [Русский](01-system-architecture.ru.md) · [Deutsch](01-system-architecture.de.md) · [Français](01-system-architecture.fr.md) · [Español](01-system-architecture.es.md) · [Português](01-system-architecture.pt.md) · [हिन्दी](01-system-architecture.hi.md) · [العربية](01-system-architecture.ar.md) · [বাংলা](01-system-architecture.bn.md) · [Bahasa Indonesia](01-system-architecture.id.md) · [日本語](01-system-architecture.ja.md)


```mermaid
flowchart TB
    subgraph "クライアント層"
        A1["Flutter Web PC<br/>管理画面"]
        A2["Flutter Web PC<br/>C側ユーザープラットフォーム"]
        A3["HarmonyOS ArkTS<br/>スマホ/タブレットクライアント"]
    end

    subgraph "ゲートウェイ層"
        B1["Nginx<br/>リバースプロキシ + HTTPS"]
    end

    subgraph "アプリケーション層"
        C1["admin/ :8789<br/>管理画面 API<br/>45 コントローラー"]
        C2["service/ :8792<br/>C側業務 API<br/>34 コントローラー"]
    end

    subgraph "サービス層 v2.0"
        D1["GameProvider<br/>Provider SDK<br/>HMAC-SHA256 署名"]
        D2["EventBus<br/>Redis Pub/Sub<br/>非同期イベント配信"]
        D3["VIP エンジン<br/>経験値/昇格/特典"]
        D4["成就エンジン<br/>12 個の内蔵成就"]
        D5["FeatureFlag<br/>フィーチャーフラグ"]
        D6["SdkSessionAuth<br/>HMAC 署名セッショントークン"]
    end

    subgraph "ストレージ層"
        E1[("MySQL 8.0<br/>78 テーブル")]
        E2[("Redis 7.x<br/>キャッシュ/レート制限/イベント")]
        E3[("Elasticsearch<br/>全文検索")]
        E4[("ClickHouse<br/>OLAP 分析")]
    end

    A1 & A2 & A3 --> B1
    B1 --> C1 & C2
    C1 & C2 --> D1 & D2 & D3 & D4 & D5 & D6
    C1 & C2 --> E1 & E2 & E3 & E4
```
