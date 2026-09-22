# सुरक्षा गहराई-स्तरित रक्षा
<!-- lang-nav -->

Languages: [中文](11-security-defense.md) · [English](11-security-defense.en.md) · [한국어](11-security-defense.ko.md) · [Русский](11-security-defense.ru.md) · [Deutsch](11-security-defense.de.md) · [Français](11-security-defense.fr.md) · [Español](11-security-defense.es.md) · [Português](11-security-defense.pt.md) · **हिन्दी** · [العربية](11-security-defense.ar.md) · [বাংলা](11-security-defense.bn.md) · [Bahasa Indonesia](11-security-defense.id.md) · [日本語](11-security-defense.ja.md)


```mermaid
flowchart TB
    l1["परत 1: मानव-मशीन सत्यापन<br/>क्लिक कैप्चा ClickCaptcha<br/>लॉगिन/पंजीकरण अनिवार्य सत्यापन"]
    l2["परत 2: ऑपरेशन पुष्टि<br/>पासवर्ड पुनः पुष्टि<br/>DELETE ऑपरेशन अनिवार्य"]
    l3["परत 3: ट्रांसमिशन सुरक्षा<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["परत 4: पहचान प्रमाणीकरण<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["परत 5: अनुमति सत्यापन<br/>RBAC method.path ग्रेन्युलरिटी<br/>सुपर एडमिन*"]
    l6["परत 6: डेटा सुरक्षा<br/>ID:Hashids एन्क्रिप्शन<br/>रिक्वेस्ट:Encryption एन्क्रिप्शन<br/>भंडारण:Encryptable एन्क्रिप्शन<br/>निर्यात:मास्किंग+कॉपीराइट"]
    l7["परत 7: ऑडिट ट्रेसिंग<br/>OperationLog<br/>उपयोगकर्ता/IP/समय/पैरामीटर"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
