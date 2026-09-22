# রিকোয়েস্ট লাইফসাইকেল
<!-- lang-nav -->

Languages: [中文](03-request-lifecycle.md) · [English](03-request-lifecycle.en.md) · [한국어](03-request-lifecycle.ko.md) · [Русский](03-request-lifecycle.ru.md) · [Deutsch](03-request-lifecycle.de.md) · [Français](03-request-lifecycle.fr.md) · [Español](03-request-lifecycle.es.md) · [Português](03-request-lifecycle.pt.md) · [हिन्दी](03-request-lifecycle.hi.md) · [العربية](03-request-lifecycle.ar.md) · **বাংলা** · [Bahasa Indonesia](03-request-lifecycle.id.md) · [日本語](03-request-lifecycle.ja.md)


```mermaid
sequenceDiagram
    actor C as ক্লায়েন্ট
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: HTTPS অনুরোধ
    N->>MW1: অনুরোধ ফরওয়ার্ড

    alt Token অনুপস্থিত বা অকার্যকর
        MW1-->>C: 401 Unauthorized
    else Token কার্যকর
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId সেট
    end

    alt পারমিশন নেই
        MW2-->>C: 403 Forbidden
    else পারমিশন আছে
        MW2->>CTL: কন্ট্রোলারে প্রবেশ
    end

    CTL->>CTL: প্যারামিটার ভ্যালিডেশন
    CTL->>CTL: decodeId(hashid)

    opt সেনসিটিভ অপারেশন
        CTL->>CTL: confirmPassword()
        alt পাসওয়ার্ড ভুল
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable অটো ডিক্রিপ্ট
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: hashid স্ট্রিং

    CTL-->>C: 200 JSON
```
