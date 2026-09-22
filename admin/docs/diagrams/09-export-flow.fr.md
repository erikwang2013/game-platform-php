# Processus métier d'export
<!-- lang-nav -->

Languages: [中文](09-export-flow.md) · [English](09-export-flow.en.md) · [한국어](09-export-flow.ko.md) · [Русский](09-export-flow.ru.md) · [Deutsch](09-export-flow.de.md) · **Français** · [Español](09-export-flow.es.md) · [Português](09-export-flow.pt.md) · [हिन्दी](09-export-flow.hi.md) · [العربية](09-export-flow.ar.md) · [বাংলা](09-export-flow.bn.md) · [Bahasa Indonesia](09-export-flow.id.md) · [日本語](09-export-flow.ja.md)


## Export Excel

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Système de fichiers

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Données
    CTL->>CTL: Déchiffrement des champs sensibles
    CTL->>CTL: Traitement de masquage (maskPhone/maskEmail)
    CTL->>CTL: Construction PhpSpreadsheet
    Note right of CTL: En-tête bleu avec texte blanc<br/>Lignes de données à bordures fines<br/>Ligne d'en-tête figée<br/>Filtre automatique
    CTL->>FS: Écriture de runtime/tmp/export_*.xlsx
    CTL-->>C: Téléchargement du fichier
```

## Export PDF

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant FS as Système de fichiers

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: En-tête: titre + copyright + heure<br/>Contenu: tableau ou cartes<br/>Pied de page: copyright inamovible
    CTL->>CTL: Rendu Dompdf A4 paysage
    CTL->>FS: Écriture de runtime/tmp/export_*.pdf
    CTL-->>C: Téléchargement du fichier
```
