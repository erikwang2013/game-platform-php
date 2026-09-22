# バックエンド階層アーキテクチャ
<!-- lang-nav -->

Languages: **中文** · [English](02-backend-layers.en.md) · [한국어](02-backend-layers.ko.md) · [Русский](02-backend-layers.ru.md) · [Deutsch](02-backend-layers.de.md) · [Français](02-backend-layers.fr.md) · [Español](02-backend-layers.es.md) · [Português](02-backend-layers.pt.md) · [हिन्दी](02-backend-layers.hi.md) · [العربية](02-backend-layers.ar.md) · [বাংলা](02-backend-layers.bn.md) · [Bahasa Indonesia](02-backend-layers.id.md) · [日本語](02-backend-layers.ja.md)


```mermaid
flowchart TD
    subgraph route["ルーティング層"]
        r1["config/route.php<br/>URL→Controller マッピング"]
    end

    subgraph middleware["ミドルウェア層"]
        m1["AdminAuth<br/>JWT Token 検証<br/>adminId の注入"]
        m2["AdminPermission<br/>RBAC 認可<br/>method.path 一致"]
    end

    subgraph controller["コントローラー層"]
        base["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        user["UserController"]
        role["RoleController"]
        perm["PermissionController"]
        dash["DashboardController"]
        export["ExportController"]
        captcha["CaptchaController"]
        auth["AuthController"]
    end

    subgraph service["サービス層"]
        s1["HashidsService<br/>ID エンコード/デコード"]
        s2["SnowflakeService<br/>グローバル ID 生成"]
        s3["EncryptionService<br/>暗号化/復号+マスキング"]
    end

    subgraph model["モデル層"]
        md1["AdminUser<br/>encryptable casts"]
        md2["AdminRole"]
        md3["AdminPermission"]
        md4["OperationLog"]
        md5["SystemConfig"]
    end

    subgraph driver["ドライバー層"]
        d1["MySQL PDO"]
        d2["Elasticsearch HTTP"]
        d3["Redis"]
    end

    r1 --> m1 --> m2
    m2 --> user & role & perm & dash & export
    m1 --> captcha & auth
    base -.->|extends| user & role & perm & dash & export
    user & role & perm & dash & export & captcha & auth --> s1 & s2 & s3
    user & role & perm & dash & export & captcha & auth --> md1 & md2 & md3 & md4 & md5
    md1 & md2 & md3 & md4 & md5 --> d1
    md1 --> d2
    captcha --> d3

    style r1 fill:#722ED1,color:#fff
    style m1 fill:#FA8C16,color:#fff
    style m2 fill:#FA8C16,color:#fff
    style base fill:#1677FF,color:#fff
    style s1 fill:#52C41A,color:#fff
    style s2 fill:#52C41A,color:#fff
    style s3 fill:#52C41A,color:#fff
```
