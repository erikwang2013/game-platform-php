# Бизнес-процесс экспорта
<!-- lang-nav -->

Languages: **中文** · [English](09-export-flow.en.md) · [한국어](09-export-flow.ko.md) · [Русский](09-export-flow.ru.md) · [Deutsch](09-export-flow.de.md) · [Français](09-export-flow.fr.md) · [Español](09-export-flow.es.md) · [Português](09-export-flow.pt.md) · [हिन्दी](09-export-flow.hi.md) · [العربية](09-export-flow.ar.md) · [বাংলা](09-export-flow.bn.md) · [Bahasa Indonesia](09-export-flow.id.md) · [日本語](09-export-flow.ja.md)


## Экспорт в Excel

```mermaid
sequenceDiagram
    participant C as Клиент
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Файловая система

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Результат запроса
    CTL->>CTL: Расшифровка чувствительных полей
    CTL->>CTL: Маскирование (maskPhone/maskEmail)
    CTL->>CTL: Сборка PhpSpreadsheet
    Note right of CTL: Заголовок: синий фон, белый текст<br/>Тонкие границы строк данных<br/>Закрепление первой строки<br/>Автофильтр
    CTL->>FS: Запись runtime/tmp/export_*.xlsx
    CTL-->>C: Скачивание файла
```

## Экспорт в PDF

```mermaid
sequenceDiagram
    participant C as Клиент
    participant CTL as ExportController
    participant FS as Файловая система

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: Колонтитул: заголовок+копирайт+время<br/>Содержимое: таблица или карточки<br/>Подвал: неудаляемый копирайт
    CTL->>CTL: Рендеринг Dompdf (A4, альбомная)
    CTL->>FS: Запись runtime/tmp/export_*.pdf
    CTL-->>C: Скачивание файла
```
