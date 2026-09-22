# Siklus Hidup Permintaan
<!-- lang-nav -->

Languages: [中文](03-request-lifecycle.md) · [English](03-request-lifecycle.en.md) · [한국어](03-request-lifecycle.ko.md) · [Русский](03-request-lifecycle.ru.md) · [Deutsch](03-request-lifecycle.de.md) · [Français](03-request-lifecycle.fr.md) · [Español](03-request-lifecycle.es.md) · [Português](03-request-lifecycle.pt.md) · [हिन्दी](03-request-lifecycle.hi.md) · [العربية](03-request-lifecycle.ar.md) · [বাংলা](03-request-lifecycle.bn.md) · **Bahasa Indonesia** · [日本語](03-request-lifecycle.ja.md)


```mermaid
sequenceDiagram
    actor C as Klien
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: Permintaan HTTPS
    N->>MW1: Teruskan permintaan

    alt Token hilang atau tidak valid
        MW1-->>C: 401 Unauthorized
    else Token valid
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: Setel $request->adminId
    end

    alt Tidak ada izin
        MW2-->>C: 403 Forbidden
    else Memiliki izin
        MW2->>CTL: Masuk ke Controller
    end

    CTL->>CTL: Validasi parameter
    CTL->>CTL: decodeId(hashid)

    opt Operasi sensitif
        CTL->>CTL: confirmPassword()
        alt Kata sandi salah
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: Dekripsi otomatis encryptable
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: String hashid

    CTL-->>C: 200 JSON
```
