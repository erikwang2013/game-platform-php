# تدفق عملية التصدير
<!-- lang-nav -->

Languages: **中文** · [English](09-export-flow.en.md) · [한국어](09-export-flow.ko.md) · [Русский](09-export-flow.ru.md) · [Deutsch](09-export-flow.de.md) · [Français](09-export-flow.fr.md) · [Español](09-export-flow.es.md) · [Português](09-export-flow.pt.md) · [हिन्दी](09-export-flow.hi.md) · [العربية](09-export-flow.ar.md) · [বাংলা](09-export-flow.bn.md) · [Bahasa Indonesia](09-export-flow.id.md) · [日本語](09-export-flow.ja.md)


## تصدير Excel

```mermaid
sequenceDiagram
    participant C as العميل
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as نظام الملفات

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: نتائج الاستعلام
    CTL->>CTL: فك تشفير الحقول الحساسة
    CTL->>CTL: إخفاء البيانات(maskPhone/maskEmail)
    CTL->>CTL: بناء PhpSpreadsheet
    Note right of CTL: رأس الجدول بخلفية زرقاء ونص أبيض<br/>صفوف البيانات بحدود رفيعة<br/>تجميد الصف الأول<br/>تصفية تلقائية
    CTL->>FS: كتابة runtime/tmp/export_*.xlsx
    CTL-->>C: تنزيل الملف
```

## تصدير PDF

```mermaid
sequenceDiagram
    participant C as العميل
    participant CTL as ExportController
    participant FS as نظام الملفات

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: رأس الصفحة: العنوان+الحقوق+الوقت<br/>المحتوى: جدول أو بطاقات<br/>تذييل: حقوق لا يمكن إزالتها
    CTL->>CTL: عرض Dompdf(A4 أفقي)
    CTL->>FS: كتابة runtime/tmp/export_*.pdf
    CTL-->>C: تنزيل الملف
```
