# Défense en profondeur de la sécurité
<!-- lang-nav -->

Languages: [中文](11-security-defense.md) · [English](11-security-defense.en.md) · [한국어](11-security-defense.ko.md) · [Русский](11-security-defense.ru.md) · [Deutsch](11-security-defense.de.md) · **Français** · [Español](11-security-defense.es.md) · [Português](11-security-defense.pt.md) · [हिन्दी](11-security-defense.hi.md) · [العربية](11-security-defense.ar.md) · [বাংলা](11-security-defense.bn.md) · [Bahasa Indonesia](11-security-defense.id.md) · [日本語](11-security-defense.ja.md)


```mermaid
flowchart TB
    l1["Couche 1: Vérification homme-machine<br/>Captcha à clic ClickCaptcha<br/>Obligatoire à la connexion/inscription"]
    l2["Couche 2: Confirmation d'opération<br/>Double confirmation par mot de passe<br/>Obligatoire pour les opérations DELETE"]
    l3["Couche 3: Sécurité de transmission<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["Couche 4: Authentification d'identité<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14j"]
    l5["Couche 5: Autorisation des permissions<br/>Granularité RBAC method.path<br/>Super administrateur *"]
    l6["Couche 6: Protection des données<br/>ID: chiffrement Hashids<br/>Requête: chiffrement Encryption<br/>Stockage: chiffrement Encryptable<br/>Export: masquage + copyright"]
    l7["Couche 7: Traçabilité d'audit<br/>OperationLog<br/>Utilisateur/IP/heure/paramètres"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
