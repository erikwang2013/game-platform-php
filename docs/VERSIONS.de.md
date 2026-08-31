# Versionsvergleich
<!-- lang-nav -->

Languages: **中文** · [English](VERSIONS.en.md) · [한국어](VERSIONS.ko.md) · [Русский](VERSIONS.ru.md) · [Deutsch](VERSIONS.de.md) · [Français](VERSIONS.fr.md) · [Español](VERSIONS.es.md) · [Português](VERSIONS.pt.md) · [हिन्दी](VERSIONS.hi.md) · [العربية](VERSIONS.ar.md) · [বাংলা](VERSIONS.bn.md) · [Bahasa Indonesia](VERSIONS.id.md) · [日本語](VERSIONS.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Überblick

| | Basisversion (Lite) | Standardversion (Standard) | Vollversion (Full) |
|------|------|------|------|
| Datentabellen (install.sql) | 19 | 29 | **66** (22 neu in v1.3.15-22) |
| API-Endpunkte | 38 | 54 | ~260 (admin+service, inkl. Webhook/Provider) |
| Backend-Controller | 14 | 22 | admin 46 + service 35 |
| Datenmodelle | nicht geteilt | nicht geteilt | **gemeinsam 52 (platform-common) + admin 8 + service 10** |
| Gemeinsame Services | keine gemeinsame Schicht | keine gemeinsame Schicht | `packages/platform-common` einzelnes gemeinsames Paket |
| Admin-Frontend-Seiten | 11 | 13 | 15 |
| Platform-Frontend-Seiten | 8 | 10 | 10 |
| HarmonyOS (admin) | - | Login + Dashboard | **8 Seiten** `admin/apps/harmonyos/` |
| HarmonyOS (C-End) | - | - | **5 Seiten** `apps/harmonyos/` (Login/Spielelobby/Details/Wallet/Profil) |
| Docker-Dienste | - | - | **7** (nginx/admin/service/leaderboard-ws/mysql/redis/elasticsearch) |
| Testfälle | 60 | 60 | admin ~132; service 3 |

---

## Benutzerauthentifizierung

| Funktion | Basisversion | Standardversion | Vollversion |
|------|--------|--------|--------|
| Benutzername/Passwort-Registrierung/Login | ✓ | ✓ | ✓ |
| JWT-Token (2h+14d) | ✓ | ✓ | ✓ |
| Klick-Captcha | stub | stub | ✓ poster-php |
| Kontosperre (5 Versuche/15 Minuten) | ✓ | ✓ | ✓ |
| Sitzungslimit (3 gleichzeitig) | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ 7 Plattformen (inkl. X/MS/LinkedIn/GitHub) |
| 2FA TOTP-Zwei-Faktor-Authentifizierung | - | - | ✓ |
| GDPR-Datenexport/-Löschung | - | - | ✓ |

---

## Wallet und Finanzen

| Funktion | Basisversion | Standardversion | Vollversion |
|------|--------|--------|--------|
| Plattformwährungs-Wallet | ✓ | ✓ | ✓ |
| Wallet-Optimistic-Lock | ✓ | ✓ | ✓ |
| Transaktionsprotokoll | ✓ | ✓ | ✓ |
| Spielwährungs-Wallet | ✓ | ✓ | ✓ |
| Einzahlungsauftrag erstellen (checkout_url/expires_at sofort zurückgeschrieben) | ✓ | ✓ | ✓ |
| Automatische Gutschrift per Einzahlungs-Callback | - | ✓ manuell | ✓ Stripe (incl. Alipay/WeChat Pay)/PayPal/NowPayments-IPN/Coinbase-Webhook-Signaturprüfung |
| Umtausch-Preisangebot/Kauf/Verkauf | ✓ | ✓ | ✓ |
| Umtauschspannen-Erlös | ✓ | ✓ | ✓ |
| Auszahlungsantrag | ✓ | ✓ | ✓ |
| Globaler Auszahlungsschalter | ✓ | ✓ | ✓ |
| Auszahlungsprüfung | ✓ manuell | ✓ manuell | ✓ Batch + manuell |
| KYC-Stufenlimits | - | ✓ 3 Stufen | ✓ |
| Auszahlungsgebühr | - | - | ✓ |
| PDF-Beleg | - | - | ✓ |

---

## Spieleverwaltung

| Funktion | Basisversion | Standardversion | Vollversion |
|------|--------|--------|--------|
| Spiel-CRUD | ✓ | ✓ | ✓ |
| Spielwährungsverwaltung | ✓ | ✓ | ✓ |
| C-End-Spieleliste/-details | ✓ | ✓ | ✓ |
| Spielstart | ✓ | ✓ | ✓ |
| Spielkategorien (10 Kategorien) | - | - | ✓ |
| Kategoriefilter | - | - | ✓ |
| Spielserver-Verwaltung | - | ✓ | ✓ |
| Spielprotokoll-Tracking | - | ✓ | ✓ |
| ES-Volltextsuche | - | - | ✓ |
| Suchvorschläge | - | - | ✓ |
| Provider-SDK für Drittanbieter-Spiele | - | - | ✓ HMAC-SHA256 |

---

## Betriebswerkzeuge

| Funktion | Basisversion | Standardversion | Vollversion |
|------|--------|--------|--------|
| Ankündigungsverwaltung | ✓ | ✓ | ✓ |
| Dashboard | ✓ Verwaltungsbackend | ✓ Verwaltungsbackend | ✓ Verwaltung + Plattform |
| Excel-Export | ✓ | ✓ | ✓ |
| PDF-Export | ✓ | ✓ | ✓ |
| Echte Dashboard-Diagramme | - | - | ✓ fl_chart |
| Gutscheinsystem | - | - | ✓ |
| Ranglisten (täglich/wöchentlich/monatlich/gesamt) | - | - | ✓ Redis-Cache |
| WebSocket-Echtzeit-Rangliste | - | - | ✓ Port 8789 |
| Benachrichtigungssystem (In-App + E-Mail) | - | - | ✓ |
| Empfehlungsprovision | - | - | ✓ |
| Tagesstatistik-Snapshot | - | ✓ | ✓ |
| Datenberichte (Zusammenfassung/Tagesbericht/CSV) | - | - | ✓ |
| Plattform-Statistik (C-Seite) | - | - | ✓ |
| Plattformerlös-Tracking | - | - | ✓ |

---

## Sicherheit und Compliance

| Funktion | Basisversion | Standardversion | Vollversion |
|------|--------|--------|--------|
| 18 Ebenen Verteidigung in der Tiefe | ✓ | ✓ | ✓ |
| RBAC-Berechtigungssteuerung | ✓ | ✓ | ✓ |
| Betriebs-Auditprotokoll | ✓ | ✓ | ✓ |
| Erkennung von 8 Plattform-Quellen | ✓ | ✓ | ✓ |
| Redis-Gleitfenster-Ratenbegrenzung | ✓ | ✓ | ✓ |
| KYC-Identitätsprüfung | - | ✓ | ✓ |
| Risikokontroll-Engine (4 Regeln) | - | ✓ | ✓ |
| Signaturprüfung der Zahlungs-Callbacks | - | - | ✓ |

---

## Internationalisierung

| Funktion | Basisversion | Standardversion | Vollversion |
|------|--------|--------|--------|
| Mehrsprachigkeit | Chinesisch/Englisch | 4 Sprachen | 4 Sprachen |
| Übersetzungstabelle + Cache | ✓ | ✓ | ✓ |
| Automatische Spracherkennung | ✓ | ✓ | ✓ |
| Länderspezifische Konfiguration | - | - | ✓ 8 Länder |

---

## Deployment und Betrieb

| Funktion | Basisversion | Standardversion | Vollversion |
|------|--------|--------|--------|
| Webman-Eigenständige Bereitstellung | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ 7 Dienste |
| Nginx-Reverse-Proxy | - | - | ✓ |
| CDN | - | - | ✓ 5-Anbieter-Anbindung + Admin-Konfiguration/Aktiv-Deaktivierung/Konnektivitätstest (Zugangsdaten verschlüsselt, service liest ausschließlich aus DB) |
| Crontab-Planungsaufgaben | - | ✓ | ✓ |
| Prometheus-Überwachung | ✓ | ✓ | ✓ `/metrics` Geschäfts-Gauges + Event-Counter |
| Gesundheitscheck | ✓ | ✓ | ✓ |
| hg/apidoc-Online-Dokumentation | - | - | ✓ 41 Controller |

---

## Clients

| Funktion | Basisversion | Standardversion | Vollversion |
|------|--------|--------|--------|
| Flutter Web PC-Verwaltungsbackend | ✓ 5 Seiten | ✓ 11 Seiten | ✓ 17 Seiten |
| Flutter Web PC-Benutzerplattform | ✓ 5 Seiten | ✓ 8 Seiten | ✓ 10 Seiten |
| HarmonyOS admin | - | ✓ Login + Dashboard | ✓ 8 Seiten `admin/apps/harmonyos/` |
| HarmonyOS C-End | - | - | ✓ 5 Seiten `apps/harmonyos/` |

---

## Datenbanktabellen

### Basisversion (19 Tabellen)
```
Verwaltungsbackend (7):  game_admin_user, game_admin_role, game_admin_permission,
               game_admin_user_role, game_admin_role_permission,
               game_operation_log, game_system_config

Plattform-Kern (12): game_user, game_user_wallet, game_user_game_wallet,
               game_game, game_game_currency, game_deposit_order,
               game_withdraw_order, game_exchange_record, game_transaction,
               game_payment_method, game_announcement, game-platform_config
```

### Standardversion neu (10 Tabellen)
```
game_user_identity, game_user_oauth, game_user_payment_account,
game_user_session, game_game_server, game_game_play_log,
game_withdraw_limit, game_risk_rule, game_risk_log, game_stat_daily
```

### Vollversion neu (13 Tabellen)
```
game_game_category, game_game_category_rel, game_leaderboard,
game_coupon, game_user_coupon, game_language, game_translation,
game_country_config, game-platform_revenue,
game_notification, game_referral, game_referral_reward, game_user_2fa
```

### Neu in v1.3.15-22 (22 Tabellen)
```
game_event_outbox, game_reconciliation_batch, game_reconciliation_diff,
game_reconciliation_statement, game_device_fingerprint, game_device_account_map,
game_ip_reputation, game_account_account_link,
game_activity, game_activity_participation, game_activity_reward_log,
game_anticheat_event, game_anticheat_daily_stat,
game_group, game_group_member, game_share_link,
game_aml_rule, game_aml_hit, game_kyc_level, game_user_kyc, game_user_trust, game_risk_cluster
```

---

## API-Endpunkte

| Modul | Basisversion | Standardversion | Vollversion |
|------|--------|--------|--------|
| Authentifizierung | 3 | 3 | 7 (+OAuth×2 +2FA×5) |
| Wallet | 2 | 2 | 3 (+Einzahlungs-Callback) |
| Umtausch | 4 | 4 | 4 |
| Auszahlung | 2 | 2 | 8 (+Batch+Limits+Prüfung) |
| Spiele | 3 | 4 | 7 (+Server+Protokoll+Suche) |
| Benutzer | 2 | 2 | 7 (+KYC+GDPR+Privatsphäre) |
| Verwaltungsbackend | 18 | 25 | 79 |
| Betriebswerkzeuge | - | - | 30 (+Ranglisten+Gutscheine+Benachrichtigungen+Empfehlungen) |
| Internationalisierung | 2 | 2 | 4 (+Länderkonfiguration) |
| **Gesamt** | **38** | **54** | **~260** |

---

## Ökosystem-Erweiterung (v2.0) — neu

| Funktion | Beschreibung |
|------|------|
| GameProvider-Abstraktionsschicht | SelfProvider (DB-Transaktion) + ThirdPartyProvider (HTTP+Signatur) |
| Provider-API-Gateway | balance/bet/settle/refund-Callbacks + ProviderAuth-Middleware |
| Ticket-System | C-End erstellen/antworten + Verwaltungsseite bearbeiten/zuweisen/schließen |
| E-Mail-Verifizierung | 6-stelliger Code, Redis 10 Minuten Ablauf, 60-Sekunden-Wiederholungslimit |
| Push-Benachrichtigungen | PushService (FCM/APNs/Huawei-Push) |
| VIP-System | 5 Stufen, Erfahrungspunkte-Akkumulation, automatischer Aufstieg, Umtauschrabatt, Auszahlungsermäßigung, Wechselkursbonus |
| Errungenschaftssystem | 12 eingebaute Errungenschaften, ereignisgesteuerte Erkennung, Fortschrittsverfolgung |
| Freundessystem | Anfrage/annehmen/ablehnen/löschen/suchen |
| Private Nachrichten/Chat | REST + WebSocket-Echtzeitnachrichten (Port 8790) |
| Event-Bus | Redis Pub/Sub; emit INCR `metrics:event_*`; Konsumprozess `EventConsumer` umgesetzt |
| Feature-Schalter | FeatureFlag basierend auf DB; `inRollout`/`abTest` liest `feature.{name}_percent` |
| Webhook | - | - | ✓ 7 Ereignistypen + Pub/Sub-Zustellung |
| Chat | - | - | ✓ REST+WebSocket :8791 |
| Turniersystem | - | - | ✓ FeatureFlag+tournament |
| Gutscheinbedingungen | - | - | ✓ min_deposit/first_user/game_id |
| Mehrstufige Provision | - | - | ✓ zweistufige Gewinnbeteiligung |
| SDK-Dokumentation | - | - | ✓ PHP/Go/Python |
| Erweiterte Analysen | Retention/D1-D30, Konversions-Trichter, ARPU/ARPPU |

### Neue Datentabellen (10 Tabellen)
```
game_ticket, game_ticket_reply, game_device_token,
game_vip_level, game_user_vip, game_exp_log,
game_achievement, game_user_achievement,
game_friend, game_message
```

### Neue Provider-API-Endpunkte (4)
```
POST /api/provider/balance  — Balance abfragen
POST /api/provider/bet      — Einsatz melden
POST /api/provider/settle   — Abrechnung melden
POST /api/provider/refund   — Erstattung melden
```

### Neue C-End-API-Endpunkte (8)
```
POST /api/verify/send-email    — E-Mail-Verifizierungscode senden
POST /api/verify/confirm-email — E-Mail bestätigen
GET  /api/ticket/list             — Ticketliste
POST /api/ticket/create           — Ticket erstellen
GET  /api/ticket/{id}             — Ticketdetails
POST /api/ticket/{id}/reply       — Ticket beantworten
GET  /api/user/vip-status         — VIP-Status
GET  /api/user/achievements       — Errungenschaftsliste
```

### Neue Verwaltungsbackend-API-Endpunkte (6)
```
GET  /admin/ticket/list          — Ticketliste
GET  /admin/ticket/{id}          — Ticketdetails
POST /admin/ticket/{id}/reply    — Ticket beantworten
POST /admin/ticket/{id}/close    — Ticket schließen
POST /admin/ticket/{id}/assign   — Bearbeiter zuweisen
GET  /admin/analytics/retention  — Retentionsanalyse
GET  /admin/analytics/funnel     — Konversions-Trichter
GET  /admin/analytics/arpu       — ARPU-Trend
GET  /admin/analytics/economy    — Wirtschaftskennzahlen
```
