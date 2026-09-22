# 요청 수명주기
<!-- lang-nav -->

Languages: [中文](03-request-lifecycle.md) · [English](03-request-lifecycle.en.md) · **한국어** · [Русский](03-request-lifecycle.ru.md) · [Deutsch](03-request-lifecycle.de.md) · [Français](03-request-lifecycle.fr.md) · [Español](03-request-lifecycle.es.md) · [Português](03-request-lifecycle.pt.md) · [हिन्दी](03-request-lifecycle.hi.md) · [العربية](03-request-lifecycle.ar.md) · [বাংলা](03-request-lifecycle.bn.md) · [Bahasa Indonesia](03-request-lifecycle.id.md) · [日本語](03-request-lifecycle.ja.md)


```mermaid
sequenceDiagram
    actor C as 클라이언트
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: HTTPS 요청
    N->>MW1: 요청 전달

    alt Token 누락 또는 무효
        MW1-->>C: 401 Unauthorized
    else Token 유효
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId 설정
    end

    alt 권한 없음
        MW2-->>C: 403 Forbidden
    else 권한 있음
        MW2->>CTL: 컨트롤러 진입
    end

    CTL->>CTL: 파라미터 검증
    CTL->>CTL: decodeId(hashid)

    opt 민감 작업
        CTL->>CTL: confirmPassword()
        alt 비밀번호 오류
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable 자동 복호화
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: hashid 문자열

    CTL-->>C: 200 JSON
```
