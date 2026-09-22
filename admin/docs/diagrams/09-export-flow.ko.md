# 내보내기 업무 플로우
<!-- lang-nav -->

Languages: [中文](09-export-flow.md) · [English](09-export-flow.en.md) · **한국어** · [Русский](09-export-flow.ru.md) · [Deutsch](09-export-flow.de.md) · [Français](09-export-flow.fr.md) · [Español](09-export-flow.es.md) · [Português](09-export-flow.pt.md) · [हिन्दी](09-export-flow.hi.md) · [العربية](09-export-flow.ar.md) · [বাংলা](09-export-flow.bn.md) · [Bahasa Indonesia](09-export-flow.id.md) · [日本語](09-export-flow.ja.md)


## Excel 내보내기

```mermaid
sequenceDiagram
    participant C as 클라이언트
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as 파일 시스템

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: 조회 결과
    CTL->>CTL: 민감 필드 복호화
    CTL->>CTL: 마스킹(maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet 구성
    Note right of CTL: 헤더 파란 배경 흰 글자<br/>데이터 행 얇은 테두리<br/>첫 행 고정<br/>자동 필터
    CTL->>FS: runtime/tmp/export_*.xlsx 쓰기
    CTL-->>C: 파일 다운로드
```

## PDF 내보내기

```mermaid
sequenceDiagram
    participant C as 클라이언트
    participant CTL as ExportController
    participant FS as 파일 시스템

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: 머리글:제목+저작권+시간<br/>본문:표 또는 카드<br/>바닥글:저작권 제거 불가
    CTL->>CTL: Dompdf 렌더링(A4 가로)
    CTL->>FS: runtime/tmp/export_*.pdf 쓰기
    CTL-->>C: 파일 다운로드
```
