# Export-Geschäftsablauf
<!-- lang-nav -->

Languages: **中文** · [English](09-export-flow.en.md) · [한국어](09-export-flow.ko.md) · [Русский](09-export-flow.ru.md) · [Deutsch](09-export-flow.de.md) · [Français](09-export-flow.fr.md) · [Español](09-export-flow.es.md) · [Português](09-export-flow.pt.md) · [हिन्दी](09-export-flow.hi.md) · [العربية](09-export-flow.ar.md) · [বাংলা](09-export-flow.bn.md) · [Bahasa Indonesia](09-export-flow.id.md) · [日本語](09-export-flow.ja.md)


## Excel-Export

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Dateisystem

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Abfrageergebnisse
    CTL->>CTL: Sensible Felder entschlüsseln
    CTL->>CTL: Maskierung (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet-Aufbau
    Note right of CTL: Kopfzeile blau mit weißer Schrift<br/>Dünne Rahmen für Datenzeilen<br/>Erste Zeile fixieren<br/>Autofilter
    CTL->>FS: Schreiben nach runtime/tmp/export_*.xlsx
    CTL-->>C: Dateidownload
```

## PDF-Export

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant FS as Dateisystem

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: Seitenkopf: Titel + Copyright + Zeit<br/>Inhalt: Tabelle oder Karten<br/>Seitenfuß: nicht entfernbares Copyright
    CTL->>CTL: Dompdf-Rendering (A4 Querformat)
    CTL->>FS: Schreiben nach runtime/tmp/export_*.pdf
    CTL-->>C: Dateidownload
```
