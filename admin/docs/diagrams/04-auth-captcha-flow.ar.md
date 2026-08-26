# تدفق المصادقة والتحقق (كابتشا)
<!-- lang-nav -->

Languages: **中文** · [English](04-auth-captcha-flow.en.md) · [한국어](04-auth-captcha-flow.ko.md) · [Русский](04-auth-captcha-flow.ru.md) · [Deutsch](04-auth-captcha-flow.de.md) · [Français](04-auth-captcha-flow.fr.md) · [Español](04-auth-captcha-flow.es.md) · [Português](04-auth-captcha-flow.pt.md) · [हिन्दी](04-auth-captcha-flow.hi.md) · [العربية](04-auth-captcha-flow.ar.md) · [বাংলা](04-auth-captcha-flow.bn.md) · [Bahasa Indonesia](04-auth-captcha-flow.id.md) · [日本語](04-auth-captcha-flow.ja.md)


```mermaid
sequenceDiagram
    actor U as المستخدم
    participant CL as العميل
    participant SV as الخادم
    participant CAP as Captcha
    participant JWT as خدمة JWT

    rect rgb(230, 240, 255)
    Note over U,CAP: الخطوة 1: الحصول على الكابتشا
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP-->>SV: key, image(base64 PNG), targets
    SV-->>CL: 200 {key, image, extra.targets}
    end

    rect rgb(230, 255, 230)
    Note over U,CAP: الخطوة 2: نقر المستخدم
    CL->>CL: عرض الصورة، تلميح "اضغط: شجرة→طائر→زهرة"
    U->>CL: النقر على مواضع الكلمات في الصورة بالتتابع
    CL->>CL: جمع clicks:[{x,y},{x,y},{x,y}]
    end

    rect rgb(255, 240, 230)
    Note over U,CAP: الخطوة 3: التحقق عند تسجيل الدخول
    CL->>SV: POST /api/auth/login {username,password,captcha_key,clicks}
    SV->>CAP: captcha_verify(key,'click',clicks)

    alt الكابتشا خاطئة
        CAP-->>SV: false
        SV-->>CL: 422 خطأ في الكابتشا
    else الكابتشا صحيحة
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt بيانات الاعتماد خاطئة
            SV-->>CL: 401 اسم المستخدم أو كلمة المرور خاطئة
        else بيانات الاعتماد صحيحة
            SV->>JWT: jwt()->create()
            JWT-->>SV: access_token(2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token(14d)
            SV-->>CL: 200 {tokens, user}
        end
    end
    end

    rect rgb(245, 245, 255)
    Note over U,CL: الخطوة 4: الطلبات اللاحقة
    CL->>SV: GET /admin/dashboard
    Note right of CL: Authorization: Bearer token
    SV->>JWT: jwt()->verify()
    JWT-->>SV: {sub, username}
    SV-->>CL: 200 {بيانات لوحة التحكم}
    end
```
