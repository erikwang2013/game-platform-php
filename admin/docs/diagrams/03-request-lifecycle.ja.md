# リクエストライフサイクル
<!-- lang-nav -->

Languages: **中文** · [English](03-request-lifecycle.en.md) · [한국어](03-request-lifecycle.ko.md) · [Русский](03-request-lifecycle.ru.md) · [Deutsch](03-request-lifecycle.de.md) · [Français](03-request-lifecycle.fr.md) · [Español](03-request-lifecycle.es.md) · [Português](03-request-lifecycle.pt.md) · [हिन्दी](03-request-lifecycle.hi.md) · [العربية](03-request-lifecycle.ar.md) · [বাংলা](03-request-lifecycle.bn.md) · [Bahasa Indonesia](03-request-lifecycle.id.md) · [日本語](03-request-lifecycle.ja.md)


```mermaid
sequenceDiagram
    actor C as クライアント
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: HTTPS リクエスト
    N->>MW1: リクエスト転送

    alt Token が欠落または無効
        MW1-->>C: 401 Unauthorized
    else Token が有効
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId の設定
    end

    alt 権限なし
        MW2-->>C: 403 Forbidden
    else 権限あり
        MW2->>CTL: コントローラーへ遷移
    end

    CTL->>CTL: パラメータ検証
    CTL->>CTL: decodeId(hashid)

    opt 機密操作
        CTL->>CTL: confirmPassword()
        alt パスワード誤り
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable による自動復号
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: hashid 文字列

    CTL-->>C: 200 JSON
```
