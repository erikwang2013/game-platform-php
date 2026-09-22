# 인증 및 캡차 플로우
<!-- lang-nav -->

Languages: [中文](04-auth-captcha-flow.md) · [English](04-auth-captcha-flow.en.md) · **한국어** · [Русский](04-auth-captcha-flow.ru.md) · [Deutsch](04-auth-captcha-flow.de.md) · [Français](04-auth-captcha-flow.fr.md) · [Español](04-auth-captcha-flow.es.md) · [Português](04-auth-captcha-flow.pt.md) · [हिन्दी](04-auth-captcha-flow.hi.md) · [العربية](04-auth-captcha-flow.ar.md) · [বাংলা](04-auth-captcha-flow.bn.md) · [Bahasa Indonesia](04-auth-captcha-flow.id.md) · [日本語](04-auth-captcha-flow.ja.md)


```mermaid
sequenceDiagram
    actor U as 사용자
    participant CL as 클라이언트
    participant SV as 서버
    participant CAP as Captcha
    participant JWT as JWT Service

    rect rgb(230, 240, 255)
    Note over U,CAP: 1단계: 캡차 가져오기
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP-->>SV: key, image(base64 PNG), targets
    SV-->>CL: 200 {key, image, extra.targets}
    end

    rect rgb(230, 255, 230)
    Note over U,CAP: 2단계: 사용자 클릭
    CL->>CL: 이미지 렌더링, 안내 "클릭: 나무→새→꽃"
    U->>CL: 이미지 속 글자 위치를 순서대로 클릭
    CL->>CL: clicks 수집:[{x,y},{x,y},{x,y}]
    end

    rect rgb(255, 240, 230)
    Note over U,CAP: 3단계: 로그인 검증
    CL->>SV: POST /api/auth/login {username,password,captcha_key,clicks}
    SV->>CAP: captcha_verify(key,'click',clicks)

    alt 캡차 오류
        CAP-->>SV: false
        SV-->>CL: 422 캡차 오류
    else 캡차 정상
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt 자격 증명 오류
            SV-->>CL: 401 사용자 이름 또는 비밀번호 오류
        else 자격 증명 정상
            SV->>JWT: jwt()->create()
            JWT-->>SV: access_token(2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token(14d)
            SV-->>CL: 200 {tokens, user}
        end
    end
    end

    rect rgb(245, 245, 255)
    Note over U,CL: 4단계: 후속 요청
    CL->>SV: GET /admin/dashboard
    Note right of CL: Authorization: Bearer token
    SV->>JWT: jwt()->verify()
    JWT-->>SV: {sub, username}
    SV-->>CL: 200 {dashboard data}
    end
```
