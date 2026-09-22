# Siklus Hidup Lengkap ID
<!-- lang-nav -->

Languages: [中文](06-id-lifecycle.md) · [English](06-id-lifecycle.en.md) · [한국어](06-id-lifecycle.ko.md) · [Русский](06-id-lifecycle.ru.md) · [Deutsch](06-id-lifecycle.de.md) · [Français](06-id-lifecycle.fr.md) · [Español](06-id-lifecycle.es.md) · [Português](06-id-lifecycle.pt.md) · [हिन्दी](06-id-lifecycle.hi.md) · [العربية](06-id-lifecycle.ar.md) · [বাংলা](06-id-lifecycle.bn.md) · **Bahasa Indonesia** · [日本語](06-id-lifecycle.ja.md)


```mermaid
flowchart LR
    subgraph gen["1. Buat"]
        g1["SnowflakeService::generate()"]
        g2["datacenter_id(5bit) + worker_id(5bit)<br/>+ timestamp(41bit) + sequence(12bit)"]
        g3["BIGINT(18)<br/>contoh: 1750123456789"]
        g1 --> g2 --> g3
    end

    subgraph store["2. Simpan"]
        s1["Tabel MySQL game_*<br/>id BIGINT UNSIGNED NOT NULL"]
        s2["Bidang sensitif encryptable cast<br/>Enkripsi penyimpanan AES-128-ECB"]
        g3 --> s1 --> s2
    end

    subgraph transfer["3. Transmisi"]
        t1["HashidsService::encode(bigint)"]
        t2["String hashid<br/>contoh: aB3xK9mW2pQ7rT5v"]
        s1 --> t1 --> t2
    end

    subgraph reverse["4. Dekode balik"]
        r1["HashidsService::decode(hashid)"]
        r2["BIGINT"]
        t2 --> r1 --> r2
    end

    style g1 fill:#1677FF,color:#fff
    style s2 fill:#FA8C16,color:#fff
    style t1 fill:#52C41A,color:#fff
    style r1 fill:#722ED1,color:#fff
```
