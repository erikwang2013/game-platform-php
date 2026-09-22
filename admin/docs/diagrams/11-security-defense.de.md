# Sicherheit durch verteidigungsstarke Tiefe
<!-- lang-nav -->

Languages: **中文** · [English](11-security-defense.en.md) · [한국어](11-security-defense.ko.md) · [Русский](11-security-defense.ru.md) · [Deutsch](11-security-defense.de.md) · [Français](11-security-defense.fr.md) · [Español](11-security-defense.es.md) · [Português](11-security-defense.pt.md) · [हिन्दी](11-security-defense.hi.md) · [العربية](11-security-defense.ar.md) · [বাংলা](11-security-defense.bn.md) · [Bahasa Indonesia](11-security-defense.id.md) · [日本語](11-security-defense.ja.md)


```mermaid
flowchart TB
    l1["Ebene 1: Mensch-Maschine-Verifizierung<br/>Click-Captcha ClickCaptcha<br/>Pflichtprüfung bei Anmeldung/Registrierung"]
    l2["Ebene 2: Vorgangsbestätigung<br/>Passwort-Zweitbestätigung<br/>Pflicht bei DELETE"]
    l3["Ebene 3: Übertragungssicherheit<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["Ebene 4: Authentifizierung<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["Ebene 5: Autorisierung<br/>RBAC method.path-Granularität<br/>Superadministrator*"]
    l6["Ebene 6: Datenschutz<br/>ID: Hashids-Verschlüsselung<br/>Request: Encryption-Verschlüsselung<br/>Speicher: Encryptable-Verschlüsselung<br/>Export: Maskierung + Copyright"]
    l7["Ebene 7: Audit-Nachverfolgung<br/>OperationLog<br/>Benutzer/IP/Zeit/Parameter"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
