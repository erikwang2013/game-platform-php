# प्रमाणीकरण और कैप्चा प्रवाह
<!-- lang-nav -->

Languages: [中文](04-auth-captcha-flow.md) · [English](04-auth-captcha-flow.en.md) · [한국어](04-auth-captcha-flow.ko.md) · [Русский](04-auth-captcha-flow.ru.md) · [Deutsch](04-auth-captcha-flow.de.md) · [Français](04-auth-captcha-flow.fr.md) · [Español](04-auth-captcha-flow.es.md) · [Português](04-auth-captcha-flow.pt.md) · **हिन्दी** · [العربية](04-auth-captcha-flow.ar.md) · [বাংলা](04-auth-captcha-flow.bn.md) · [Bahasa Indonesia](04-auth-captcha-flow.id.md) · [日本語](04-auth-captcha-flow.ja.md)


```mermaid
sequenceDiagram
    actor U as उपयोगकर्ता
    participant CL as क्लाइंट
    participant SV as सर्वर
    participant CAP as Captcha
    participant JWT as JWT Service

    rect rgb(230, 240, 255)
    Note over U,CAP: चरण 1: कैप्चा प्राप्त करें
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP-->>SV: key, image(base64 PNG), targets
    SV-->>CL: 200 {key, image, extra.targets}
    end

    rect rgb(230, 255, 230)
    Note over U,CAP: चरण 2: उपयोगकर्ता क्लिक
    CL->>CL: छवि रेंडर करें, संकेत "क्लिक करें:पेड़→पक्षी→फूल"
    U->>CL: छवि में अक्षर स्थानों पर क्रम से क्लिक करें
    CL->>CL: clicks एकत्र करें:[{x,y},{x,y},{x,y}]
    end

    rect rgb(255, 240, 230)
    Note over U,CAP: चरण 3: लॉगिन सत्यापन
    CL->>SV: POST /api/auth/login {username,password,captcha_key,clicks}
    SV->>CAP: captcha_verify(key,'click',clicks)

    alt कैप्चा गलत
        CAP-->>SV: false
        SV-->>CL: 422 कैप्चा गलत
    else कैप्चा सही
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt क्रेडेंशियल गलत
            SV-->>CL: 401 उपयोगकर्ता नाम या पासवर्ड गलत
        else क्रेडेंशियल सही
            SV->>JWT: jwt()->create()
            JWT-->>SV: access_token(2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token(14d)
            SV-->>CL: 200 {tokens, user}
        end
    end
    end

    rect rgb(245, 245, 255)
    Note over U,CL: चरण 4: बाद के अनुरोध
    CL->>SV: GET /admin/dashboard
    Note right of CL: Authorization: Bearer token
    SV->>JWT: jwt()->verify()
    JWT-->>SV: {sub, username}
    SV-->>CL: 200 {dashboard data}
    end
```
