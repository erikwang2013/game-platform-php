# নিরাপত্তা ডিফেন্স-ইন-ডেপথ
<!-- lang-nav -->

Languages: [中文](11-security-defense.md) · [English](11-security-defense.en.md) · [한국어](11-security-defense.ko.md) · [Русский](11-security-defense.ru.md) · [Deutsch](11-security-defense.de.md) · [Français](11-security-defense.fr.md) · [Español](11-security-defense.es.md) · [Português](11-security-defense.pt.md) · [हिन्दी](11-security-defense.hi.md) · [العربية](11-security-defense.ar.md) · **বাংলা** · [Bahasa Indonesia](11-security-defense.id.md) · [日本語](11-security-defense.ja.md)


```mermaid
flowchart TB
    l1["স্তর ১: হিউম্যান ভেরিফিকেশন<br/>ক্লিক ক্যাপচা ClickCaptcha<br/>লগইন/রেজিস্ট্রেশনে বাধ্যতামূলক"]
    l2["স্তর ২: অপারেশন নিশ্চিতকরণ<br/>পাসওয়ার্ড রি-কনফার্মেশন<br/>DELETE অপারেশনে আবশ্যক"]
    l3["স্তর ৩: ট্রান্সপোর্ট সিকিউরিটি<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["স্তর ৪: আইডেন্টিটি অথেনটিকেশন<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["স্তর ৫: পারমিশন অথরাইজেশন<br/>RBAC method.path গ্রানুলারিটি<br/>সুপার অ্যাডমিন*"]
    l6["স্তর ৬: ডেটা সুরক্ষা<br/>ID:Hashids এনক্রিপশন<br/>রিকোয়েস্ট:Encryption এনক্রিপশন<br/>স্টোরেজ:Encryptable এনক্রিপশন<br/>এক্সপোর্ট:মাস্কিং+কপিরাইট"]
    l7["স্তর ৭: অডিট ট্রেসিং<br/>OperationLog<br/>ইউজার/IP/সময়/প্যারামিটার"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
