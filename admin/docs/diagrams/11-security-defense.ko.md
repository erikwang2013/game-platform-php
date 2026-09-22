# 보안 심층 방어
<!-- lang-nav -->

Languages: [中文](11-security-defense.md) · [English](11-security-defense.en.md) · **한국어** · [Русский](11-security-defense.ru.md) · [Deutsch](11-security-defense.de.md) · [Français](11-security-defense.fr.md) · [Español](11-security-defense.es.md) · [Português](11-security-defense.pt.md) · [हिन्दी](11-security-defense.hi.md) · [العربية](11-security-defense.ar.md) · [বাংলা](11-security-defense.bn.md) · [Bahasa Indonesia](11-security-defense.id.md) · [日本語](11-security-defense.ja.md)


```mermaid
flowchart TB
    l1["1계층: 휴먼 검증<br/>클릭형 캡차 ClickCaptcha<br/>로그인/회원가입 필수 검증"]
    l2["2계층: 작업 확인<br/>비밀번호 2차 확인<br/>DELETE 작업 필수"]
    l3["3계층: 전송 보안<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["4계층: 인증<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["5계층: 권한 검증<br/>RBAC method.path 세분화<br/>슈퍼 관리자*"]
    l6["6계층: 데이터 보호<br/>ID:Hashids 암호화<br/>요청:Encryption 암호화<br/>저장:Encryptable 암호화<br/>내보내기:마스킹+저작권"]
    l7["7계층: 감사 추적<br/>OperationLog<br/>사용자/IP/시간/파라미터"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
