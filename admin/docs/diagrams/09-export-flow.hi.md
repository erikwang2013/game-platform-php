# निर्यात व्यवसाय प्रवाह
<!-- lang-nav -->

Languages: [中文](09-export-flow.md) · [English](09-export-flow.en.md) · [한국어](09-export-flow.ko.md) · [Русский](09-export-flow.ru.md) · [Deutsch](09-export-flow.de.md) · [Français](09-export-flow.fr.md) · [Español](09-export-flow.es.md) · [Português](09-export-flow.pt.md) · **हिन्दी** · [العربية](09-export-flow.ar.md) · [বাংলা](09-export-flow.bn.md) · [Bahasa Indonesia](09-export-flow.id.md) · [日本語](09-export-flow.ja.md)


## Excel निर्यात

```mermaid
sequenceDiagram
    participant C as क्लाइंट
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as फ़ाइल सिस्टम

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: क्वेरी परिणाम
    CTL->>CTL: संवेदनशील फ़ील्ड डिक्रिप्ट करें
    CTL->>CTL: मास्किंग(maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet बिल्ड
    Note right of CTL: हेडर नीली पृष्ठभूमि सफ़ेद अक्षर<br/>डेटा पंक्ति पतली सीमा<br/>पहली पंक्ति फ़्रीज़<br/>स्वचालित फ़िल्टर
    CTL->>FS: runtime/tmp/export_*.xlsx में लिखें
    CTL-->>C: फ़ाइल डाउनलोड
```

## PDF निर्यात

```mermaid
sequenceDiagram
    participant C as क्लाइंट
    participant CTL as ExportController
    participant FS as फ़ाइल सिस्टम

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: हेडर:शीर्षक+कॉपीराइट+समय<br/>सामग्री:तालिका या कार्ड<br/>फ़ुटर:कॉपीराइट हटाना असंभव
    CTL->>CTL: Dompdf रेंडरिंग(A4 लैंडस्केप)
    CTL->>FS: runtime/tmp/export_*.pdf में लिखें
    CTL-->>C: फ़ाइल डाउनलोड
```
