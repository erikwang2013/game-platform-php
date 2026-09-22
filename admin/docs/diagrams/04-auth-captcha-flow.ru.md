# Процесс аутентификации и проверки кода
<!-- lang-nav -->

Languages: **中文** · [English](04-auth-captcha-flow.en.md) · [한국어](04-auth-captcha-flow.ko.md) · [Русский](04-auth-captcha-flow.ru.md) · [Deutsch](04-auth-captcha-flow.de.md) · [Français](04-auth-captcha-flow.fr.md) · [Español](04-auth-captcha-flow.es.md) · [Português](04-auth-captcha-flow.pt.md) · [हिन्दी](04-auth-captcha-flow.hi.md) · [العربية](04-auth-captcha-flow.ar.md) · [বাংলা](04-auth-captcha-flow.bn.md) · [Bahasa Indonesia](04-auth-captcha-flow.id.md) · [日本語](04-auth-captcha-flow.ja.md)


```mermaid
sequenceDiagram
    actor U as Пользователь
    participant CL as Клиент
    participant SV as Сервер
    participant CAP as Captcha
    participant JWT as JWT Service

    rect rgb(230, 240, 255)
    Note over U,CAP: Шаг первый: получение капчи
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP-->>SV: key, image(base64 PNG), targets
    SV-->>CL: 200 {key, image, extra.targets}
    end

    rect rgb(230, 255, 230)
    Note over U,CAP: Шаг второй: клик пользователя
    CL->>CL: Отрисовка картинки, подсказка "нажмите: дерево→птица→цветок"
    U->>CL: Последовательные клики по символам на картинке
    CL->>CL: Сбор clicks:[{x,y},{x,y},{x,y}]
    end

    rect rgb(255, 240, 230)
    Note over U,CAP: Шаг третий: проверка при входе
    CL->>SV: POST /api/auth/login {username,password,captcha_key,clicks}
    SV->>CAP: captcha_verify(key,'click',clicks)

    alt Неверная капча
        CAP-->>SV: false
        SV-->>CL: 422 Неверная капча
    else Капча верна
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Неверные учётные данные
            SV-->>CL: 401 Неверное имя пользователя или пароль
        else Учётные данные верны
            SV->>JWT: jwt()->create()
            JWT-->>SV: access_token(2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token(14d)
            SV-->>CL: 200 {tokens, user}
        end
    end
    end

    rect rgb(245, 245, 255)
    Note over U,CL: Шаг четвёртый: последующие запросы
    CL->>SV: GET /admin/dashboard
    Note right of CL: Authorization: Bearer token
    SV->>JWT: jwt()->verify()
    JWT-->>SV: {sub, username}
    SV-->>CL: 200 {dashboard data}
    end
```
