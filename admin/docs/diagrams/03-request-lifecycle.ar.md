# دورة حياة الطلب
<!-- lang-nav -->

Languages: **中文** · [English](03-request-lifecycle.en.md) · [한국어](03-request-lifecycle.ko.md) · [Русский](03-request-lifecycle.ru.md) · [Deutsch](03-request-lifecycle.de.md) · [Français](03-request-lifecycle.fr.md) · [Español](03-request-lifecycle.es.md) · [Português](03-request-lifecycle.pt.md) · [हिन्दी](03-request-lifecycle.hi.md) · [العربية](03-request-lifecycle.ar.md) · [বাংলা](03-request-lifecycle.bn.md) · [Bahasa Indonesia](03-request-lifecycle.id.md) · [日本語](03-request-lifecycle.ja.md)


```mermaid
sequenceDiagram
    actor C as العميل
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: طلب HTTPS
    N->>MW1: إعادة توجيه الطلب

    alt Token مفقود أو غير صالح
        MW1-->>C: 401 Unauthorized
    else Token صالح
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: تعيين $request->adminId
    end

    alt لا صلاحية
        MW2-->>C: 403 Forbidden
    else لديه صلاحية
        MW2->>CTL: الدخول إلى وحدة التحكم
    end

    CTL->>CTL: التحقق من المعاملات
    CTL->>CTL: decodeId(hashid)

    opt عمليات حساسة
        CTL->>CTL: confirmPassword()
        alt كلمة المرور خاطئة
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: فك التشفير التلقائي encryptable
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: سلسلة hashid

    CTL-->>C: 200 JSON
```
