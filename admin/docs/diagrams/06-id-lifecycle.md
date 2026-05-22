# ID 全生命周期

```mermaid
flowchart LR
    subgraph gen["1.生成"]
        g1["SnowflakeService::generate()"]
        g2["datacenter_id(5bit) + worker_id(5bit)<br/>+ timestamp(41bit) + sequence(12bit)"]
        g3["BIGINT(18)<br/>例: 1750123456789"]
        g1 --> g2 --> g3
    end

    subgraph store["2.存储"]
        s1["MySQL erik_* 表<br/>id BIGINT UNSIGNED NOT NULL"]
        s2["敏感字段 encryptable cast<br/>AES-128-ECB 加密存储"]
        g3 --> s1 --> s2
    end

    subgraph transfer["3.传输"]
        t1["HashidsService::encode(bigint)"]
        t2["hashid字符串<br/>例: aB3xK9mW2pQ7rT5v"]
        s1 --> t1 --> t2
    end

    subgraph reverse["4.反向解码"]
        r1["HashidsService::decode(hashid)"]
        r2["BIGINT"]
        t2 --> r1 --> r2
    end

    style g1 fill:#1677FF,color:#fff
    style s2 fill:#FA8C16,color:#fff
    style t1 fill:#52C41A,color:#fff
    style r1 fill:#722ED1,color:#fff
```
