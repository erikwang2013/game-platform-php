# Жизненный цикл запроса
<!-- lang-nav -->

Languages: **中文** · [English](03-request-lifecycle.en.md) · [한국어](03-request-lifecycle.ko.md) · [Русский](03-request-lifecycle.ru.md) · [Deutsch](03-request-lifecycle.de.md) · [Français](03-request-lifecycle.fr.md) · [Español](03-request-lifecycle.es.md) · [Português](03-request-lifecycle.pt.md) · [हिन्दी](03-request-lifecycle.hi.md) · [العربية](03-request-lifecycle.ar.md) · [বাংলা](03-request-lifecycle.bn.md) · [Bahasa Indonesia](03-request-lifecycle.id.md) · [日本語](03-request-lifecycle.ja.md)


```mermaid
sequenceDiagram
    actor C as Клиент
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: HTTPS-запрос
    N->>MW1: Пересылка запроса

    alt Token отсутствует или недействителен
        MW1-->>C: 401 Unauthorized
    else Token действителен
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: Установка $request->adminId
    end

    alt Нет прав
        MW2-->>C: 403 Forbidden
    else Есть права
        MW2->>CTL: Вход в контроллер
    end

    CTL->>CTL: Валидация параметров
    CTL->>CTL: decodeId(hashid)

    opt Чувствительная операция
        CTL->>CTL: confirmPassword()
        alt Неверный пароль
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: Автоматическая расшифровка encryptable
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: Строка hashid

    CTL-->>C: 200 JSON
```
