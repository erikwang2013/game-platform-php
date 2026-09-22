# অথেনটিকেশন ও ক্যাপচা ফ্লো
<!-- lang-nav -->

Languages: [中文](04-auth-captcha-flow.md) · [English](04-auth-captcha-flow.en.md) · [한국어](04-auth-captcha-flow.ko.md) · [Русский](04-auth-captcha-flow.ru.md) · [Deutsch](04-auth-captcha-flow.de.md) · [Français](04-auth-captcha-flow.fr.md) · [Español](04-auth-captcha-flow.es.md) · [Português](04-auth-captcha-flow.pt.md) · [हिन्दी](04-auth-captcha-flow.hi.md) · [العربية](04-auth-captcha-flow.ar.md) · **বাংলা** · [Bahasa Indonesia](04-auth-captcha-flow.id.md) · [日本語](04-auth-captcha-flow.ja.md)


```mermaid
sequenceDiagram
    actor U as ইউজার
    participant CL as ক্লায়েন্ট
    participant SV as সার্ভার
    participant CAP as Captcha
    participant JWT as JWT Service

    rect rgb(230, 240, 255)
    Note over U,CAP: ধাপ ১: ক্যাপচা সংগ্রহ
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP-->>SV: key, image(base64 PNG), targets
    SV-->>CL: 200 {key, image, extra.targets}
    end

    rect rgb(230, 255, 230)
    Note over U,CAP: ধাপ ২: ইউজার ক্লিক
    CL->>CL: ইমেজ রেন্ডার, প্রম্পট "ক্লিক করুন: গাছ→পাখি→ফুল"
    U->>CL: ইমেজের টেক্সটের অবস্থানে ক্রমানুসারে ক্লিক
    CL->>CL: clicks সংগ্রহ:[{x,y},{x,y},{x,y}]
    end

    rect rgb(255, 240, 230)
    Note over U,CAP: ধাপ ৩: লগইন ভেরিফিকেশন
    CL->>SV: POST /api/auth/login {username,password,captcha_key,clicks}
    SV->>CAP: captcha_verify(key,'click',clicks)

    alt ক্যাপচা ভুল
        CAP-->>SV: false
        SV-->>CL: 422 ক্যাপচা ভুল
    else ক্যাপচা সঠিক
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt ক্রেডেনশিয়াল ভুল
            SV-->>CL: 401 ইউজারনেম বা পাসওয়ার্ড ভুল
        else ক্রেডেনশিয়াল সঠিক
            SV->>JWT: jwt()->create()
            JWT-->>SV: access_token(2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token(14d)
            SV-->>CL: 200 {tokens, user}
        end
    end
    end

    rect rgb(245, 245, 255)
    Note over U,CL: ধাপ ৪: পরবর্তী অনুরোধ
    CL->>SV: GET /admin/dashboard
    Note right of CL: Authorization: Bearer token
    SV->>JWT: jwt()->verify()
    JWT-->>SV: {sub, username}
    SV-->>CL: 200 {dashboard data}
    end
```
