# 数据加密分层

```mermaid
flowchart TB
    subgraph transport["传输层加密 - encryption"]
        e1["客户端发送敏感数据"]
        e2["AES-256-CBC 加密"]
        e3["API传输密文"]
        e4["服务端解密处理"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["存储层加密 - encryptable"]
        d1["Model casts配置<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["写入时自动加密"]
        d3["MySQL VARCHAR(500)存储密文"]
        d4["读取时自动解密"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["展示层脱敏"]
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
