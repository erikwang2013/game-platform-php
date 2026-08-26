# Diagram ER Basis Data (v2.0 — 52 Tabel)
<!-- lang-nav -->

Languages: [中文](08-database-er.md) · [English](08-database-er.en.md) · [한국어](08-database-er.ko.md) · [Русский](08-database-er.ru.md) · [Deutsch](08-database-er.de.md) · [Français](08-database-er.fr.md) · [Español](08-database-er.es.md) · [Português](08-database-er.pt.md) · [हिन्दी](08-database-er.hi.md) · [العربية](08-database-er.ar.md) · [বাংলা](08-database-er.bn.md) · **Bahasa Indonesia** · [日本語](08-database-er.ja.md)


```mermaid
erDiagram
    erik_user ||--|| erik_user_wallet : "1:1"
    erik_user ||--|| erik_user_vip : "1:1"
    erik_user ||--o{ erik_user_game_wallet : "1:N"
    erik_user ||--o{ erik_deposit_order : "1:N"
    erik_user ||--o{ erik_withdraw_order : "1:N"
    erik_user ||--o{ erik_exchange_record : "1:N"
    erik_user ||--o{ erik_transaction : "1:N"
    erik_user ||--o{ erik_user_achievement : "1:N"
    erik_user ||--o{ erik_exp_log : "1:N"
    erik_user ||--o{ erik_ticket : "1:N"
    erik_user ||--o{ erik_device_token : "1:N"
    erik_user ||--o{ erik_user_session : "1:N"
    erik_user ||--o{ erik_friend : "1:N"
    erik_user ||--o{ erik_message : "1:N"

    erik_ticket ||--o{ erik_ticket_reply : "1:N"
    erik_vip_level ||--o{ erik_user_vip : "1:N"
    erik_achievement ||--o{ erik_user_achievement : "1:N"

    erik_game ||--o{ erik_game_currency : "1:N"
    erik_game ||--o{ erik_user_game_wallet : "1:N"
    erik_game ||--o{ erik_exchange_record : "1:N"
    erik_game ||--o{ erik_game_play_log : "1:N"
```
