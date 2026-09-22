# Security Defense in Depth
<!-- lang-nav -->

Languages: [中文](11-security-defense.md) · **English** · [한국어](11-security-defense.ko.md) · [Русский](11-security-defense.ru.md) · [Deutsch](11-security-defense.de.md) · [Français](11-security-defense.fr.md) · [Español](11-security-defense.es.md) · [Português](11-security-defense.pt.md) · [हिन्दी](11-security-defense.hi.md) · [العربية](11-security-defense.ar.md) · [বাংলা](11-security-defense.bn.md) · [Bahasa Indonesia](11-security-defense.id.md) · [日本語](11-security-defense.ja.md)


```mermaid
flowchart TB
    l1["Layer 1: Human verification<br/>Click captcha ClickCaptcha<br/>Mandatory for login/register"]
    l2["Layer 2: Operation confirmation<br/>Password re-confirmation<br/>Required for DELETE"]
    l3["Layer 3: Transport security<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["Layer 4: Authentication<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["Layer 5: Authorization<br/>RBAC method.path granularity<br/>super administrator*"]
    l6["Layer 6: Data protection<br/>ID: Hashids-encoded<br/>Request: Encryption-encrypted<br/>Storage: Encryptable-encrypted<br/>Export: masked + copyright"]
    l7["Layer 7: Audit trail<br/>OperationLog<br/>user/IP/time/parameters"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
