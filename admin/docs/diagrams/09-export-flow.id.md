# Alur Proses Ekspor
<!-- lang-nav -->

Languages: [中文](09-export-flow.md) · [English](09-export-flow.en.md) · [한국어](09-export-flow.ko.md) · [Русский](09-export-flow.ru.md) · [Deutsch](09-export-flow.de.md) · [Français](09-export-flow.fr.md) · [Español](09-export-flow.es.md) · [Português](09-export-flow.pt.md) · [हिन्दी](09-export-flow.hi.md) · [العربية](09-export-flow.ar.md) · [বাংলা](09-export-flow.bn.md) · **Bahasa Indonesia** · [日本語](09-export-flow.ja.md)


## Ekspor Excel

```mermaid
sequenceDiagram
    participant C as Klien
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Sistem file

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Hasil kueri
    CTL->>CTL: Dekripsi bidang sensitif
    CTL->>CTL: Penyamaran(maskPhone/maskEmail)
    CTL->>CTL: Membangun PhpSpreadsheet
    Note right of CTL: Header biru teks putih<br/>Baris data garis tepi tipis<br/>Bekukan baris pertama<br/>Filter otomatis
    CTL->>FS: Tulis ke runtime/tmp/export_*.xlsx
    CTL-->>C: Unduh file
```

## Ekspor PDF

```mermaid
sequenceDiagram
    participant C as Klien
    participant CTL as ExportController
    participant FS as Sistem file

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: Header: judul+hak cipta+waktu<br/>Isi: tabel atau kartu<br/>Footer: hak cipta tidak dapat dihapus
    CTL->>CTL: Render Dompdf(A4 lanskap)
    CTL->>FS: Tulis ke runtime/tmp/export_*.pdf
    CTL-->>C: Unduh file
```
