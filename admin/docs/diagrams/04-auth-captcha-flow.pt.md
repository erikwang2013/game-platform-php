# Fluxo de Autenticação e Captcha
<!-- lang-nav -->

Languages: [中文](04-auth-captcha-flow.md) · [English](04-auth-captcha-flow.en.md) · [한국어](04-auth-captcha-flow.ko.md) · [Русский](04-auth-captcha-flow.ru.md) · [Deutsch](04-auth-captcha-flow.de.md) · [Français](04-auth-captcha-flow.fr.md) · [Español](04-auth-captcha-flow.es.md) · **Português** · [हिन्दी](04-auth-captcha-flow.hi.md) · [العربية](04-auth-captcha-flow.ar.md) · [বাংলা](04-auth-captcha-flow.bn.md) · [Bahasa Indonesia](04-auth-captcha-flow.id.md) · [日本語](04-auth-captcha-flow.ja.md)


```mermaid
sequenceDiagram
    actor U as Usuário
    participant CL as Cliente
    participant SV as Servidor
    participant CAP as Captcha
    participant JWT as JWT Service

    rect rgb(230, 240, 255)
    Note over U,CAP: Primeira etapa: obter o captcha
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP-->>SV: key, image(base64 PNG), targets
    SV-->>CL: 200 {key, image, extra.targets}
    end

    rect rgb(230, 255, 230)
    Note over U,CAP: Segunda etapa: clique do usuário
    CL->>CL: Renderiza a imagem, exibe "clique em: árvore → pássaro → flor"
    U->>CL: Clica em sequência nas posições do texto na imagem
    CL->>CL: Coleta clicks: [{x,y},{x,y},{x,y}]
    end

    rect rgb(255, 240, 230)
    Note over U,CAP: Terceira etapa: validação no login
    CL->>SV: POST /api/auth/login {username,password,captcha_key,clicks}
    SV->>CAP: captcha_verify(key,'click',clicks)

    alt Captcha incorreto
        CAP-->>SV: false
        SV-->>CL: 422 captcha incorreto
    else Captcha correto
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Credenciais incorretas
            SV-->>CL: 401 usuário ou senha incorretos
        else Credenciais corretas
            SV->>JWT: jwt()->create()
            JWT-->>SV: access_token(2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token(14d)
            SV-->>CL: 200 {tokens, user}
        end
    end
    end

    rect rgb(245, 245, 255)
    Note over U,CL: Quarta etapa: requisições seguintes
    CL->>SV: GET /admin/dashboard
    Note right of CL: Authorization: Bearer token
    SV->>JWT: jwt()->verify()
    JWT-->>SV: {sub, username}
    SV-->>CL: 200 {dashboard data}
    end
```
