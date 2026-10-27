# ID Full Lifecycle
<!-- lang-nav -->

Languages: [中文](06-id-lifecycle.md) · **English**


```mermaid
flowchart LR
    subgraph gen["1.Generate"]
        g1["SnowflakeService::generate()"]
        g2["datacenter_id(5bit) + worker_id(5bit)<br/>+ timestamp(41bit) + sequence(12bit)"]
        g3["BIGINT(18)<br/>e.g. 1750123456789"]
        g1 --> g2 --> g3
    end

    subgraph store["2.Store"]
        s1["MySQL game_* table<br/>id BIGINT UNSIGNED NOT NULL"]
        s2["Sensitive fields encryptable cast<br/>AES-128-ECB encrypted storage"]
        g3 --> s1 --> s2
    end

    subgraph transfer["3.Transmit"]
        t1["HashidsService::encode(bigint)"]
        t2["hashid string<br/>e.g. aB3xK9mW2pQ7rT5v"]
        s1 --> t1 --> t2
    end

    subgraph reverse["4.Reverse decode"]
        r1["HashidsService::decode(hashid)"]
        r2["BIGINT"]
        t2 --> r1 --> r2
    end

    style g1 fill:#1677FF,color:#fff
    style s2 fill:#FA8C16,color:#fff
    style t1 fill:#52C41A,color:#fff
    style r1 fill:#722ED1,color:#fff
```
