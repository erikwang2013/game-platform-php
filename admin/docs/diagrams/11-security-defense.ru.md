# Многоуровневая защита безопасности
<!-- lang-nav -->

Languages: **中文** · [English](11-security-defense.en.md) · [한국어](11-security-defense.ko.md) · [Русский](11-security-defense.ru.md) · [Deutsch](11-security-defense.de.md) · [Français](11-security-defense.fr.md) · [Español](11-security-defense.es.md) · [Português](11-security-defense.pt.md) · [हिन्दी](11-security-defense.hi.md) · [العربية](11-security-defense.ar.md) · [বাংলা](11-security-defense.bn.md) · [Bahasa Indonesia](11-security-defense.id.md) · [日本語](11-security-defense.ja.md)


```mermaid
flowchart TB
    l1["Уровень 1: проверка человека<br/>Клик-капча ClickCaptcha<br/>Обязательная проверка при входе/регистрации"]
    l2["Уровень 2: подтверждение операции<br/>Повторное подтверждение пароля<br/>Обязательно для DELETE"]
    l3["Уровень 3: безопасность передачи<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["Уровень 4: аутентификация<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["Уровень 5: авторизация прав<br/>RBAC с точностью method.path<br/>Супер-администратор*"]
    l6["Уровень 6: защита данных<br/>ID: шифрование Hashids<br/>Запрос: шифрование Encryption<br/>Хранение: шифрование Encryptable<br/>Экспорт: маскирование+копирайт"]
    l7["Уровень 7: аудит и трассировка<br/>OperationLog<br/>Пользователь/IP/время/параметры"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
