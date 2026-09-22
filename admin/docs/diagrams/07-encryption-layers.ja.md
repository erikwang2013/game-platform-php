# データ暗号化レイヤー
<!-- lang-nav -->

Languages: **中文** · [English](07-encryption-layers.en.md) · [한국어](07-encryption-layers.ko.md) · [Русский](07-encryption-layers.ru.md) · [Deutsch](07-encryption-layers.de.md) · [Français](07-encryption-layers.fr.md) · [Español](07-encryption-layers.es.md) · [Português](07-encryption-layers.pt.md) · [हिन्दी](07-encryption-layers.hi.md) · [العربية](07-encryption-layers.ar.md) · [বাংলা](07-encryption-layers.bn.md) · [Bahasa Indonesia](07-encryption-layers.id.md) · [日本語](07-encryption-layers.ja.md)


```mermaid
flowchart TB
    subgraph transport["トランスポート層暗号化 - encryption"]
        e1["クライアントが機密データを送信"]
        e2["AES-256-CBC 暗号化"]
        e3["API で暗号文を転送"]
        e4["サーバーがデータを復号"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["ストレージ層暗号化 - encryptable"]
        d1["Model casts 設定<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["書き込み時に自動暗号化"]
        d3["MySQL VARCHAR(500) に暗号文を保存"]
        d4["読み取り時に自動復号"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["表示層マスキング"]
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
