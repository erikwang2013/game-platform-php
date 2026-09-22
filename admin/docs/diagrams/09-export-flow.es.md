# Flujo del proceso de exportación
<!-- lang-nav -->

Languages: [中文](09-export-flow.md) · [English](09-export-flow.en.md) · [한국어](09-export-flow.ko.md) · [Русский](09-export-flow.ru.md) · [Deutsch](09-export-flow.de.md) · [Français](09-export-flow.fr.md) · **Español** · [Português](09-export-flow.pt.md) · [हिन्दी](09-export-flow.hi.md) · [العربية](09-export-flow.ar.md) · [বাংলা](09-export-flow.bn.md) · [Bahasa Indonesia](09-export-flow.id.md) · [日本語](09-export-flow.ja.md)


## Exportación Excel

```mermaid
sequenceDiagram
    participant C as Cliente
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Sistema de archivos

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Resultados de la consulta
    CTL->>CTL: Descifrar campos sensibles
    CTL->>CTL: Enmascaramiento (maskPhone/maskEmail)
    CTL->>CTL: Construcción con PhpSpreadsheet
    Note right of CTL: Encabezado azul con texto blanco<br/>Bordes finos en las filas de datos<br/>Congelar primera fila<br/>Filtro automático
    CTL->>FS: Escribir en runtime/tmp/export_*.xlsx
    CTL-->>C: Descarga del archivo
```

## Exportación PDF

```mermaid
sequenceDiagram
    participant C as Cliente
    participant CTL as ExportController
    participant FS as Sistema de archivos

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: Encabezado: título + copyright + hora<br/>Contenido: tabla o tarjetas<br/>Pie: copyright no eliminable
    CTL->>CTL: Renderizado con Dompdf (A4 horizontal)
    CTL->>FS: Escribir en runtime/tmp/export_*.pdf
    CTL-->>C: Descarga del archivo
```
