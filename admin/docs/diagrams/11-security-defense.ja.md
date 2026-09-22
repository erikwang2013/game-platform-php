# セキュリティ多層防御
<!-- lang-nav -->

Languages: **中文** · [English](11-security-defense.en.md) · [한국어](11-security-defense.ko.md) · [Русский](11-security-defense.ru.md) · [Deutsch](11-security-defense.de.md) · [Français](11-security-defense.fr.md) · [Español](11-security-defense.es.md) · [Português](11-security-defense.pt.md) · [हिन्दी](11-security-defense.hi.md) · [العربية](11-security-defense.ar.md) · [বাংলা](11-security-defense.bn.md) · [Bahasa Indonesia](11-security-defense.id.md) · [日本語](11-security-defense.ja.md)


```mermaid
flowchart TB
    l1["第1層: 人機検証<br/>クリック検証コードClickCaptcha<br/>ログイン/登録で必須"]
    l2["第2層: 操作確認<br/>パスワード再確認<br/>DELETE操作で必須"]
    l3["第3層: 伝送セキュリティ<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["第4層: 本人認証<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["第5層: 権限認可<br/>RBAC method.path粒度<br/>スーパー管理者*"]
    l6["第6層: データ保護<br/>ID:Hashids暗号化<br/>リクエスト:Encryption暗号化<br/>ストレージ:Encryptable暗号化<br/>エクスポート:マスキング+著作権"]
    l7["第7層: 監査追跡<br/>OperationLog<br/>ユーザー/IP/時刻/パラメータ"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
