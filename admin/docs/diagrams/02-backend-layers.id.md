# Arsitektur Berlapis Backend
<!-- lang-nav -->

Languages: [中文](02-backend-layers.md) · [English](02-backend-layers.en.md) · [한국어](02-backend-layers.ko.md) · [Русский](02-backend-layers.ru.md) · [Deutsch](02-backend-layers.de.md) · [Français](02-backend-layers.fr.md) · [Español](02-backend-layers.es.md) · [Português](02-backend-layers.pt.md) · [हिन्दी](02-backend-layers.hi.md) · [العربية](02-backend-layers.ar.md) · [বাংলা](02-backend-layers.bn.md) · **Bahasa Indonesia** · [日本語](02-backend-layers.ja.md)


```mermaid
flowchart TD
    subgraph route["Lapisan rute"]
        r1["config/route.php<br/>Pemetaan URL→Controller"]
    end

    subgraph middleware["Lapisan middleware"]
        m1["AdminAuth<br/>Verifikasi Token JWT<br/>Menyuntikkan adminId"]
        m2["AdminPermission<br/>Otorisasi RBAC<br/>Pencocokan method.path"]
    end

    subgraph controller["Lapisan Controller"]
        base["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        user["UserController"]
        role["RoleController"]
        perm["PermissionController"]
        dash["DashboardController"]
        export["ExportController"]
        captcha["CaptchaController"]
        auth["AuthController"]
    end

    subgraph service["Lapisan layanan"]
        s1["HashidsService<br/>Enkode/dekode ID"]
        s2["SnowflakeService<br/>Pembuatan ID global"]
        s3["EncryptionService<br/>Enkripsi/dekripsi+penyamaran"]
    end

    subgraph model["Lapisan model"]
        md1["AdminUser<br/>encryptable casts"]
        md2["AdminRole"]
        md3["AdminPermission"]
        md4["OperationLog"]
        md5["SystemConfig"]
    end

    subgraph driver["Lapisan driver"]
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
