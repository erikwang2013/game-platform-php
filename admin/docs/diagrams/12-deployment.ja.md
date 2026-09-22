# デプロイアーキテクチャ (v2.0 — 7 サービス)
<!-- lang-nav -->

Languages: **中文** · [English](12-deployment.en.md) · [한국어](12-deployment.ko.md) · [Русский](12-deployment.ru.md) · [Deutsch](12-deployment.de.md) · [Français](12-deployment.fr.md) · [Español](12-deployment.es.md) · [Português](12-deployment.pt.md) · [हिन्दी](12-deployment.hi.md) · [العربية](12-deployment.ar.md) · [বাংলা](12-deployment.bn.md) · [Bahasa Indonesia](12-deployment.id.md) · [日本語](12-deployment.ja.md)


```mermaid
flowchart TB
    subgraph "エントリーポイント"
        DNS["DNS: erik.xyz"]
    end

    subgraph "Nginx リバースプロキシ"
        NGX["HTTPS :443<br/>ルーティング + Gzip<br/>CSP + HSTS"]
    end

    subgraph "アプリケーションサービス"
        ADM["admin :8789<br/>管理画面"]
        SVC["service :8792<br/>C側業務"]
        LB["leaderboard-ws :8790<br/>WebSocket ランキング"]
        CHAT["chat-ws :8791<br/>WebSocket ダイレクトメッセージ"]
    end

    subgraph "データサービス"
        MYSQL["MySQL 8.0 :3306<br/>78 テーブル"]
        REDIS["Redis 7 :6379<br/>キャッシュ/レート制限/EventBus"]
        ES["Elasticsearch :9200<br/>全文検索"]
        CH["ClickHouse :8123<br/>OLAP 分析"]
    end

    subgraph "監視"
        MON["Grafana + Prometheus<br/>ヘルスチェック /metrics"]
    end

    DNS --> NGX
    NGX --> ADM & SVC & LB & CHAT
    ADM & SVC --> MYSQL & REDIS & ES & CH
    ADM & SVC --> MON
```
