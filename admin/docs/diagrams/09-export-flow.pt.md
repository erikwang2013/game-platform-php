# Fluxo de Negócio de Exportação
<!-- lang-nav -->

Languages: [中文](09-export-flow.md) · [English](09-export-flow.en.md) · [한국어](09-export-flow.ko.md) · [Русский](09-export-flow.ru.md) · [Deutsch](09-export-flow.de.md) · [Français](09-export-flow.fr.md) · [Español](09-export-flow.es.md) · **Português** · [हिन्दी](09-export-flow.hi.md) · [العربية](09-export-flow.ar.md) · [বাংলা](09-export-flow.bn.md) · [Bahasa Indonesia](09-export-flow.id.md) · [日本語](09-export-flow.ja.md)


## Exportação Excel

```mermaid
sequenceDiagram
    participant C as Cliente
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Sistema de arquivos

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Resultado da consulta
    CTL->>CTL: Descriptografa campos sensíveis
    CTL->>CTL: Mascaramento(maskPhone/maskEmail)
    CTL->>CTL: Montagem com PhpSpreadsheet
    Note right of CTL: Cabeçalho azul com texto branco<br/>Bordas finas nas linhas de dados<br/>Congela a primeira linha<br/>Filtro automático
    CTL->>FS: Grava em runtime/tmp/export_*.xlsx
    CTL-->>C: Download do arquivo
```

## Exportação PDF

```mermaid
sequenceDiagram
    participant C as Cliente
    participant CTL as ExportController
    participant FS as Sistema de arquivos

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: Cabeçalho: título + copyright + hora<br/>Conteúdo: tabela ou cartões<br/>Rodapé: copyright não removível
    CTL->>CTL: Renderização com Dompdf (A4 paisagem)
    CTL->>FS: Grava em runtime/tmp/export_*.pdf
    CTL-->>C: Download do arquivo
```
