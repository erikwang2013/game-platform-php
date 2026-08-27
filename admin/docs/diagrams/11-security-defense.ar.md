# الدفاع الأمني المتعمق
<!-- lang-nav -->

Languages: **中文** · [English](11-security-defense.en.md) · [한국어](11-security-defense.ko.md) · [Русский](11-security-defense.ru.md) · [Deutsch](11-security-defense.de.md) · [Français](11-security-defense.fr.md) · [Español](11-security-defense.es.md) · [Português](11-security-defense.pt.md) · [हिन्दी](11-security-defense.hi.md) · [العربية](11-security-defense.ar.md) · [বাংলা](11-security-defense.bn.md) · [Bahasa Indonesia](11-security-defense.id.md) · [日本語](11-security-defense.ja.md)


```mermaid
flowchart TB
    l1["الطبقة 1: التحقق من الإنسان<br/>كابتشا النقر ClickCaptcha<br/>تحقق إلزامي عند الدخول/التسجيل"]
    l2["الطبقة 2: تأكيد العملية<br/>إعادة تأكيد كلمة المرور<br/>إلزامية لعمليات DELETE"]
    l3["الطبقة 3: أمان النقل<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["الطبقة 4: مصادقة الهوية<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["الطبقة 5: تفويض الصلاحيات<br/>RBAC بدقة method.path<br/>المشرف العام *"]
    l6["الطبقة 6: حماية البيانات<br/>ID: تشفير Hashids<br/>الطلبات: تشفير Encryption<br/>التخزين: تشفير Encryptable<br/>التصدير: إخفاء+حقوق"]
    l7["الطبقة 7: التدقيق والتتبع<br/>OperationLog<br/>المستخدم/العنوان/الوقت/المعاملات"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
