# Funktionsdokument
<!-- lang-nav -->

Languages: **中文** · [English](FEATURES.en.md) · [한국어](FEATURES.ko.md) · [Русский](FEATURES.ru.md) · [Deutsch](FEATURES.de.md) · [Français](FEATURES.fr.md) · [Español](FEATURES.es.md) · [Português](FEATURES.pt.md) · [हिन्दी](FEATURES.hi.md) · [العربية](FEATURES.ar.md) · [বাংলা](FEATURES.bn.md) · [Bahasa Indonesia](FEATURES.id.md) · [日本語](FEATURES.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Funktionsübersicht

### Basisversion (MVP) — Abgeschlossen

| Bereich | Funktion | Status |
|----|------|------|
| Benutzer | Registrierung/Login/JWT/Verifizierungscode | Abgeschlossen |
| Wallet | Plattformwährungsguthaben/Transaktionsabfrage | Abgeschlossen |
| Einzahlung | Einzahlungsauftrag erstellen (Stripe 125+ lokale Zahlungsarten / NOWPayments USDT TRC20·ERC20 / Coinbase USDC·BTC·ETH / PayPal-Callback) | Abgeschlossen |
| Umtausch | Plattformwährung⇄Spielwährung (fester Kurs + Spread) | Abgeschlossen |
| Auszahlung | Antrag/Abfrage/globaler Schalter/automatische Prüfung/manuelle Prüfung | Abgeschlossen |
| Spiele | Backend-CRUD/Währungsverwaltung/C-End-Liste/Details/Start | Abgeschlossen |
| Verwaltung | Spielverwaltung/Auszahlungsprüfung/Benutzerverwaltung/Zahlungsverwaltung/Ankündigungsverwaltung | Abgeschlossen |
| Dashboard | Plattform-Dashboard (DAU/Transaktionen/Erlös/Rangliste) | Abgeschlossen |
| Export | Excel-Export Benutzer/Transaktionen/Auszahlungen | Abgeschlossen |
| Internationalisierung | Chinesisch/Englisch-Umschaltung, Übersetzungstabelle, Sprachdetektions-Middleware | Abgeschlossen |
| Frontend | Flutter-PC-Verwaltungsbackend + C-End-Benutzerplattform (inkl. i18n) | Abgeschlossen |

### Standardversion — Abgeschlossen

| Bereich | Funktion | Status |
|----|------|------|
| Benutzer | OAuth-Login (Google/Facebook/Apple/Twitter/Microsoft/LinkedIn/GitHub) | Abgeschlossen |
| Zahlung | Automatischer Callback mehrerer Zahlungskanäle (Stripe/PayPal/NOWPayments IPN/Coinbase Webhook) | Abgeschlossen |
| Spiele | Regionen-/Serververwaltung, Spielprotokollverfolgung | Abgeschlossen |
| Auszahlung | KYC-Stufenlimits (default/verified/vip) + Gebühren | Abgeschlossen |
| KYC | Identitätsprüfungsantrag + Prüfung | Abgeschlossen |
| Risikokontrolle | IP-Blacklist/Großbetragswarnung/Frequenz-/Geschwindigkeitserkennung | Abgeschlossen |
| Statistik | Tägliche Statistik-Snapshots (Benutzer/Einzahlung/Auszahlung/Umtausch/Spiele) | Abgeschlossen |
| Frontend | Admin: KYC-Prüfung + Risikolog / Platform: OAuth+KYC+Spielprotokoll | Abgeschlossen |

### Vollversion — Abgeschlossen

| Bereich | Funktion | Status |
|----|------|------|
| Spielelobby | 10 voreingestellte Kategorien, Kategoriefilter, Spiel-Kategorie-Verknüpfung | Abgeschlossen |
| Rangliste | Tages-/Wochen-/Monats-/Gesamtrangliste, Redis-Cache, mehrere Metriken | Abgeschlossen |
| Gutscheine | Festbetrag + Prozentrabatt, zeit-/mengenbegrenzt, Einlösungs-/Nutzungsverfolgung | Abgeschlossen |
| Länderkonfiguration | 8 Länder voreingestellt, differenzierte Zahlungs-/Auszahlungsmethoden, Mindesteinzahlung | Abgeschlossen |
| Statistik | Tägliche Statistik-Snapshots + Plattform-Erlösverfolgung | Abgeschlossen |
| Suche | Elasticsearch-Volltextsuche (auf Modell-Ebene integriert) | Abgeschlossen |

### Produktionsreife Upgrades — Abgeschlossen

| Bereich | Funktion | Status |
|----|------|------|
| OAuth | Google/Facebook/Apple echter Token-Austausch | Abgeschlossen |
| Zahlung | Signaturprüfung der Zahlungs-Callbacks (Stripe/PayPal-Webhook, NOWPayments IPN HMAC-SHA512, Coinbase HMAC-SHA256 base64) | Abgeschlossen |
| Verifizierungscode | poster-php Klick-Captcha | Abgeschlossen |
| Benachrichtigung | In-App-Nachrichten + E-Mail, automatische Benachrichtigung bei Einzahlung/Auszahlung/KYC/Gutschein | Abgeschlossen |
| 2FA | Google Authenticator TOTP + Backup-Wiederherstellungscodes | Abgeschlossen |
| Empfehlung | Empfehlungscode, Registrierungsbelohnung, Einzahlungsprovision | Abgeschlossen |
| Suche | ES-Such-API + Spielvorschläge + LIKE-Fallback | Abgeschlossen |
| Rangliste | WebSocket-Echtzeit-Push (Port 8789) | Abgeschlossen |
| Bereitstellung | Docker Compose 7 Dienste + Nginx-Reverse-Proxy | Abgeschlossen |
| Daten | MySQL-Echtzeit-Aggregationsanalyse + Verbund-/Bedingte-Wahrscheinlichkeitsberechnung | Abgeschlossen |
| HarmonyOS | admin-Seite 8 Seiten; C-End `apps/harmonyos/` mit Login/Lobby/Details/Wallet/Profil (zeigt auf 8788) | Teilweise abgeschlossen (Projekt läuft, echte Geräte benötigen IP-Anpassung) |
| API-Dokumentation | hg/apidoc interaktive Dokumentation | Abgeschlossen |

### Ökosystem-Erweiterung (v2.0) — Gerade abgeschlossen

| Bereich | Funktion | Status |
|----|------|------|
| Spielanbindung | GameProvider-Abstraktionsschicht (Self/ThirdParty) + HMAC-SHA256-Signatur | Abgeschlossen |
| Spiel-Callback | Provider-API-Gateway (balance/bet/settle/refund) + ProviderAuth-Middleware | Abgeschlossen |
| Spielsession | Redis-Heartbeat + 15-Minuten-Timeout-Automatikabrechnung + GameSessionService | Abgeschlossen |
| Ticket-System | C-End-Erstellung/Antwort + Verwaltungsseite Bearbeitung/Zuweisung/Schließung, 5 Tickettypen | Abgeschlossen |
| E-Mail-Verifizierung | 6-stelliger Code, Redis 10 Minuten Ablauf, 60 Sekunden Wiedersendelimit | Abgeschlossen |
| Push-Benachrichtigung | PushService (FCM/APNs/Huawei-Push) + DeviceToken-Modell | Abgeschlossen |
| VIP-System | 5 Stufen (Standard/Silber/Gold/Platin/Diamant) + Erfahrungspunkte + automatischer Aufstieg | Abgeschlossen |
| VIP-Vorteile | Umtauschrabatt 2-15 %, Auszahlungsgebührenermäßigung 10-100 %, Wechselkursbonus 0.1-1.0 % | Abgeschlossen |
| Erfolge-System | 12 eingebaute Erfolge; EventConsumer → AchievementService ereignisgesteuerte Erkennung und VIP-EXP | Abgeschlossen |
| Freundesystem | Antrag/Annehmen/Ablehnen/Löschen/Suche, pending/accepted/blocked-Status | Abgeschlossen |
| Direktnachrichten/Chat | REST-Direktnachrichten + WebSocket-Echtzeitnachrichten (Port 8790), nur Freunde können senden | Abgeschlossen |
| Event-Bus | Redis Pub/Sub; emit + EventConsumer verarbeitet Erfolge/Webhook + metrics INCR | Abgeschlossen |
| Feature-Schalter | FeatureFlag auf DB-Basis; `inRollout`/`abTest` crc32-Bucketing liest `feature.{name}_percent` | Abgeschlossen |
| Erweiterte Analysen | Retention/D1-D30, Conversion-Funnel, ARPU/ARPPU, Spielwährungs-Wirtschaftsindikatoren (MySQL-Echtzeitaggregation) | Abgeschlossen |
| Webhook | Abonnementverwaltung + Redis-Pub/Sub-Ereigniszustellung, 7 Ereignistypen wählbar | Abgeschlossen |
| Chat | REST-Direktnachrichten + WebSocket-Echtzeitnachrichten (Port 8791), nur Freunde können senden | Abgeschlossen |
| Turniere | Erstellen/list/detail/join, FeatureFlag-Schalter, Rangliste, Teilnehmerobergrenze | Abgeschlossen |
| Mehrstufige Provision | Zweistufige Empfehlungsgewinnbeteiligung, ReferralCommission-Modell, konfigurierbare Provisionssätze | Abgeschlossen |
| Gutscheinbedingungen | min_deposit/first_user_only/game_id drei Bedingungsarten | Abgeschlossen |
| SDK-Dokumentation | Provider-Anbindungsdokumentation (PHP/Go/Python-Beispiele + 4 API-Endpunkte) | Abgeschlossen |

## 2. C-End-Benutzerfunktionen

### 2.1 Benutzerreise

```
注册 → 登录 → 邮箱/手机验证 → 浏览游戏大厅 → 进入游戏详情
                                           ↓
查看钱包 ← 玩游戏 ← 兑换游戏币 (VIP折扣) ← 充值平台币
    ↓
  提现 (VIP手续费减免) → 后台审核 → 到账
    ↓
好友系统 → 私信聊天 → 排行榜竞技 → 成就追踪
    ↓
工单支持
```

### 2.2 API-Schnittstellen

| Methode | Pfad | Beschreibung | Authentifizierung |
|------|------|------|------|
| POST | /api/auth/register | Benutzerregistrierung | nein |
| POST | /api/auth/login | Benutzerlogin | nein |
| POST | /api/auth/refresh | Token aktualisieren | nein |
| GET | /api/game/list | Spielliste | nein |
| GET | /api/game/detail/{id} | Spieldetails | nein |
| GET | /api/announcement/list | Ankündigungsliste | nein |
| GET | /api/wallet/info | Wallet-Guthaben | ja |
| GET | /api/wallet/transactions | Transaktionsverlauf | ja |
| POST | /api/deposit/create | Einzahlungsauftrag erstellen | ja |
| GET | /api/payment/methods | Zahlungsarten auflisten (nach Land geroutet) | ja |
| POST | /api/exchange/quote | Umtausch-Preisangebot (VIP-Rabatt) | ja |
| POST | /api/exchange/buy | Spielwährung kaufen | ja |
| POST | /api/exchange/sell | Spielwährung verkaufen | ja |
| POST | /api/withdraw/apply | Auszahlungsantrag (VIP-Ermäßigung) | ja |
| POST | /api/game/launch | Spiel starten | ja |
| GET | /api/game/play-logs | Spielverlauf | ja |
| POST | /api/referral/apply | Empfehlungscode verwenden | ja |
| POST | /api/verify/send-email | E-Mail-Verifizierungscode senden | ja |
| POST | /api/verify/confirm-email | E-Mail bestätigen | ja |
| GET | /api/ticket/list | Ticketliste | ja |
| POST | /api/ticket/create | Ticket erstellen | ja |
| POST | /api/ticket/{id}/reply | Ticket beantworten | ja |

## 3. Verwaltungsbackend-Funktionen

### 3.1 API-Schnittstellen (neu)

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/dashboard/platform | Plattform-Dashboard-Daten |
| GET | /admin/analytics/overview | Plattformübersicht (MySQL-Echtzeitaggregation) |
| GET | /admin/analytics/game-ranking | Spielrangliste |
| GET | /admin/analytics/dau-trend | DAU-Trend |
| GET | /admin/analytics/hourly-trend | Stundentrend |
| GET | /admin/analytics/action-distribution | Verhaltensverteilung |
| GET | /admin/analytics/revenue | Umsatzanalyse |
| GET | /admin/analytics/conversion | Spielkonversion |
| GET | /admin/analytics/probability | Verbund-/Bedingte Wahrscheinlichkeit |
| GET | /admin/analytics/retention | Retentionsanalyse D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Conversion-Funnel |
| GET | /admin/analytics/arpu | ARPU/ARPPU-Trend |
| GET | /admin/analytics/economy | Spielwährungs-Wirtschaftsindikatoren |
| GET | /admin/game/list | Spielliste |
| POST | /admin/game/create | Spiel erstellen (inkl. provider_config) |
| PUT | /admin/game/{id} | Spiel bearbeiten |
| GET | /admin/withdraw/orders | Auszahlungsauftragsliste |
| PUT | /admin/withdraw/review | Auszahlung prüfen |
| GET | /admin/ticket/list | Ticketliste |
| GET | /admin/ticket/{id} | Ticketdetails |
| POST | /admin/ticket/{id}/reply | Ticket beantworten |
| POST | /admin/ticket/{id}/close | Ticket schließen |
| POST | /admin/ticket/{id}/assign | Bearbeiter zuweisen |

## 4. Provider-API (Spiel-Callbacks)

| Methode | Pfad | Beschreibung | Authentifizierung |
|------|------|------|------|
| POST | /api/provider/balance | Benutzerguthaben abfragen | HMAC-SHA256 |
| POST | /api/provider/bet | Einsatz melden | HMAC-SHA256 |
| POST | /api/provider/settle | Abrechnung melden | HMAC-SHA256 |
| POST | /api/provider/refund | Rückerstattung melden | HMAC-SHA256 |

Signaturalgorithmus: `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)`
Request-Header: `X-Game-Id` + `X-Timestamp` + `X-Signature`
Zeitfenster: 5 Minuten

## 5. VIP-System

| Stufe | Kumulierte EXP | Umtauschrabatt | Auszahlungsgebührenermäßigung | Wechselkursbonus |
|------|---------|---------|-------------|---------|
| Standard | 0 | 0% | 0% | Basis |
| Silber | 500 | 2% | 10% | +0.1% |
| Gold | 2,500 | 5% | 30% | +0.3% |
| Platin | 12,500 | 10% | 50% | +0.5% |
| Diamant | 62,500 | 15% | 100% | +1.0% |

### Erfahrungspunkte-Erwerb

| Verhalten | EXP |
|------|-----|
| Einzahlung von 1 Einheit | 10 |
| Täglicher Login | 5 |
| KYC abgeschlossen | 50 |
| Neuen Benutzer einladen | 100 |
| Erfolg erreicht | 10-100 |

## 6. Erfolgsliste

| Erfolg | Bedingung | Punkte |
|------|------|------|
| First Deposit | Erste Einzahlung | 20 |
| Century Club | Kumulierte Einzahlungen 100 | 50 |
| High Roller | Kumulierte Einzahlungen 1000 | 100 |
| Trader | Erster Umtausch | 20 |
| Day Trader | 100 Umtäusche insgesamt | 100 |
| Explorer | 3 Spiele gespielt | 30 |
| Adventurer | 5 Spiele gespielt | 50 |
| Conqueror | 10 Spiele gespielt | 100 |
| Weekly Warrior | 7 Tage in Folge eingeloggt | 30 |
| Monthly Master | 30 Tage in Folge eingeloggt | 100 |
| Connector | 1 Freund eingeladen | 30 |
| Influencer | 10 Freunde eingeladen | 100 |

## 7. Datenbanktabellen-Liste

### Neu in der Ökosystem-Erweiterung (10 Tabellen)

| Tabellenname | Beschreibung | Hauptmerkmale |
|------|------|---------|
| game_ticket | Ticket | user_id+type+status-Index, assigned_to |
| game_ticket_reply | Ticket-Antwort | ticket_id-Index, is_admin zur Unterscheidung |
| game_device_token | Geräte-Token | user_id+platform+token eindeutiger Index |
| game_vip_level | VIP-Stufendefinition | level eindeutiger Index, benefits JSON |
| game_user_vip | Benutzer-VIP-Datensatz | user_id eindeutiger Index, level+exp+total_exp |
| game_exp_log | Erfahrungspunkt-Log | user_id+source kombinierter Index |
| game_achievement | Erfolgsdefinition | key eindeutiger Index, condition_json JSON |
| game_user_achievement | Benutzer-Erfolge | user_id+achievement_id eindeutiger Index |
| game_friend | Freundschaftsbeziehung | user_id+friend_id eindeutiger Index |
| game_message | Direktnachricht | from_user_id+to_user_id / to_user_id+is_read |

### Tabellenstruktur-Änderungen

| Tabellenname | Änderung |
|------|------|
| game_game | +provider_config (JSON) |
| game_game_play_log | +round_id, +bet_amount, +win_amount |

**Gesamt: install.sql 43 Tabellen** (die 10 Ökosystem-Erweiterungs-Tabellen liegen in `install/` und sind nicht in install.sql zusammengeführt). Modelle nicht geteilt: admin 46 / service 44, jeweils eigene Kopie.

## 8. Testabdeckung

| Testdatei | Testfälle | Abdeckung |
|---------|--------|---------|
| PlatformTest | 56 | bcmath-Präzision/Umtauschberechnung/Auszahlungsgebühren/Limits/Risikokontrolle/Gutscheine/KYC/i18n |
| BackendEnhancementTest | 23 | Verschlüsselungsdienst/Hashids/Snowflake |
| CaptchaTest | 7 | Captcha-Erzeugung/-Prüfung |
| EncryptionServiceTest | 6 | AES-Ver-/Entschlüsselung/Maskierung |
| EnvConfigTest | 4 | Umgebungsvariablen-Konfiguration |
| HashidsServiceTest | 8 | ID-Codierung/Decodierung-Roundtrip |
| SnowflakeServiceTest | 6 | Eindeutigkeit der ID-Generierung |

**Gesamt: admin ~132 Testfälle / 8 Dateien; service 3 Testfälle (WebhookUrlSafety + EventBusMessageFormat). service ist nicht in die CI-Fehlschlag-Sperre einbezogen.**

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
