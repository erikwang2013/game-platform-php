# Anfrage-Lebenszyklus
<!-- lang-nav -->

Languages: **中文** · [English](03-request-lifecycle.en.md) · [한국어](03-request-lifecycle.ko.md) · [Русский](03-request-lifecycle.ru.md) · [Deutsch](03-request-lifecycle.de.md) · [Français](03-request-lifecycle.fr.md) · [Español](03-request-lifecycle.es.md) · [Português](03-request-lifecycle.pt.md) · [हिन्दी](03-request-lifecycle.hi.md) · [العربية](03-request-lifecycle.ar.md) · [বাংলা](03-request-lifecycle.bn.md) · [Bahasa Indonesia](03-request-lifecycle.id.md) · [日本語](03-request-lifecycle.ja.md)


```mermaid
sequenceDiagram
    actor C as Client
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: HTTPS-Anfrage
    N->>MW1: Anfrage weiterleiten

    alt Token fehlt oder ist ungültig
        MW1-->>C: 401 Unauthorized
    else Token gültig
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId setzen
    end

    alt Keine Berechtigung
        MW2-->>C: 403 Forbidden
    else Berechtigung vorhanden
        MW2->>CTL: Controller betreten
    end

    CTL->>CTL: Parameterprüfung
    CTL->>CTL: decodeId(hashid)

    opt Sensible Operation
        CTL->>CTL: confirmPassword()
        alt Falsches Passwort
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable automatische Entschlüsselung
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: hashid-Zeichenkette

    CTL-->>C: 200 JSON
```
