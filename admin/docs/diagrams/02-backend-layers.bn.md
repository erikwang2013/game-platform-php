# ব্যাকএন্ড লেয়ারড আর্কিটেকচার
<!-- lang-nav -->

Languages: [中文](02-backend-layers.md) · [English](02-backend-layers.en.md) · [한국어](02-backend-layers.ko.md) · [Русский](02-backend-layers.ru.md) · [Deutsch](02-backend-layers.de.md) · [Français](02-backend-layers.fr.md) · [Español](02-backend-layers.es.md) · [Português](02-backend-layers.pt.md) · [हिन्दी](02-backend-layers.hi.md) · [العربية](02-backend-layers.ar.md) · **বাংলা** · [Bahasa Indonesia](02-backend-layers.id.md) · [日本語](02-backend-layers.ja.md)


```mermaid
flowchart TD
    subgraph route["রাউট লেয়ার"]
        r1["config/route.php<br/>URL→Controller ম্যাপিং"]
    end

    subgraph middleware["মিডলওয়্যার লেয়ার"]
        m1["AdminAuth<br/>JWT Token যাচাই<br/>adminId ইনজেক্ট"]
        m2["AdminPermission<br/>RBAC পারমিশন চেক<br/>method.path ম্যাচিং"]
    end

    subgraph controller["কন্ট্রোলার লেয়ার"]
        base["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        user["UserController"]
        role["RoleController"]
        perm["PermissionController"]
        dash["DashboardController"]
        export["ExportController"]
        captcha["CaptchaController"]
        auth["AuthController"]
    end

    subgraph service["সার্ভিস লেয়ার"]
        s1["HashidsService<br/>ID এনকোড/ডিকোড"]
        s2["SnowflakeService<br/>গ্লোবাল ID জেনারেশন"]
        s3["EncryptionService<br/>এনক্রিপ্ট/ডিক্রিপ্ট+মাস্কিং"]
    end

    subgraph model["মডেল লেয়ার"]
        md1["AdminUser<br/>encryptable casts"]
        md2["AdminRole"]
        md3["AdminPermission"]
        md4["OperationLog"]
        md5["SystemConfig"]
    end

    subgraph driver["ড্রাইভার লেয়ার"]
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
