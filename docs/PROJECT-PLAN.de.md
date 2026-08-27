# 项目全面规划 (Projekt-Gesamtplan)
<!-- lang-nav -->

Languages: **中文** · [English](PROJECT-PLAN.en.md) · [한국어](PROJECT-PLAN.ko.md) · [Русский](PROJECT-PLAN.ru.md) · [Deutsch](PROJECT-PLAN.de.md) · [Français](PROJECT-PLAN.fr.md) · [Español](PROJECT-PLAN.es.md) · [Português](PROJECT-PLAN.pt.md) · [हिन्दी](PROJECT-PLAN.hi.md) · [العربية](PROJECT-PLAN.ar.md) · [বাংলা](PROJECT-PLAN.bn.md) · [Bahasa Indonesia](PROJECT-PLAN.id.md) · [日本語](PROJECT-PLAN.ja.md)


> Erstellt am: 2026-08-16 · Basierend auf einer Nur-Lese-Bestandsaufnahme durch ein 6-köpfiges Team (researcher/architect/backend-dev/frontend-dev/tester/reviewer) + praktischer Verifikation der wichtigsten Aussagen
> Umfang: Ist-Stand-Zusammenfassung / Probleme und Risiken / P0-P1-P2-Roadmap / Dokumentationsreparatur / Qualitätstore

---

## 一、Projektstand

**Globale Spielaggregationsplattform** — PHP 8.3 + webman v2, Zwei-Anwendungs-Monorepo:
`admin/`(8787 Verwaltungsbackend) + `service/`(8788 C-End) + `apps/`(Flutter + HarmonyOS) + `install/`(Installationsassistent, 43 Tabellen).

| Dimension | Praktisch gemessener Umfang |
|------|---------|
| Controller | admin 32 + service 30 = 62 |
| API-Endpunkte | ~149 (admin 103 / service 88, inkl. Webhook/Provider-Callbacks) |
| Datenmodelle | admin 46 / service 44, admin/service **doppelt kopiert** (keine gemeinsame Schicht) |
| Tests | 132 Fälle / 8 Dateien (admin-Projekt), service-Projekt **null Tests** |
| Version | v1.1 (2026-08-07): Redis-Plugin, Analyseservices, Redis-Degradierung, Testkorrekturen |

Bereits implementierte Fähigkeiten: JWT+RBAC, Wallet-Optimistic-Lock, Einzahlungen (Stripe/PayPal-Signaturprüfung), Umtauschspanne, Auszahlungsprüfung + PayPal-Auszahlung, Spiel-CRUD/Provider-Gateway (HMAC), Gutscheine/VIP/Errungenschaften/Tickets/Empfehlungsprovision/2FA/Soziales (Freunde/Chat-WS)/Turniere/Webhooks/Push (FCM/APNs/Huawei)/i18n zweisprachig.

---

## 二、Probleme und Risiken (praktisch verifiziert)

### KRITISCH — Finanzsicherheit

| # | Problem | Stelle |
|---|------|------|
| C1 | `provider` bei Zahlungs-Callbacks vom Client übergeben; bei nicht stripe/paypal wird die Signaturprüfung **vollständig übersprungen**, gefälschte Callbacks werden direkt gutgeschrieben | service/.../PaymentController.php:36-42 |
| C2 | Signaturprüfung fail-open: `STRIPE_WEBHOOK_SECRET` nicht konfiguriert → `return true`; jede PayPal-Ausnahme → `return true`. Angriffskette: eigene Einzahlungsaufträge anlegen → gefälschte Callbacks → unbegrenzte Einzahlungen | PaymentController.php:167, 225 |
| C3 | `JWT_SECRET_KEY` fällt standardmäßig auf den öffentlich hartkodierten Schlüssel `open-admin-jwt-secret-change-in-production` zurück; ohne env in Produktion können Admin-Token gefälscht werden | admin+service config/plugin/erikwang2013/jwt/jwt.php:12 |

### HOCH — Korrektheit/Konsistenz

| # | Problem | Stelle |
|---|------|------|
| H1 | Analyseservice AnalyticsController: 12 Methoden vollständig implementiert, aber **null Routen**, alles 404-Totcode, während VERSIONS.md die Lieferung behauptet | admin/config/route.php (0 analytics-Einträge) |
| H2 | Event-Bus unterbrochen: emit hat 4 Aufrufstellen (game.played/withdraw.completed/exchange.completed/referral.applied), `subscribe()` hat keinerlei Prozessregistrierung, Events gehen beim Publizieren verloren; VIP/Errungenschaften/Benachrichtigungs-Engine hängen alle in der Luft | admin+service app/event/EventBus.php |
| H3 | common/ und model/ doppelt kopiert und inzwischen auseinandergedriftet (DepositLogService zwei Versionen mit unterschiedlichem Inhalt, User.php inkonsistent), Einzelpunkt-Fixes werden Doppelarbeit. **common/service bereits ausgezogen** nach `packages/platform-common` (erik/platform-common, das ursprüngliche common-php wurde eingegliedert); model und die app/common-Wrapper sind weiterhin doppelt | admin/common vs service/common → packages/platform-common |
| H4 | ~~HarmonyOS-C-End `apps/harmonyos/` ist ein leeres Verzeichnis, 0 Seiten vs. VERSIONS.md behauptet 5 Seiten~~ — umgesetzt (2026-08-18: 5 Seiten implementiert in `apps/harmonyos/`) | apps/harmonyos/ |
| H5 | Stripe-Callback prüft die `t=`-Zeitstempeltoleranz nicht (Replay möglich), und der gutgeschriebene Betrag wird nicht mit dem tatsächlich vom Gateway gezahlten Betrag abgeglichen | PaymentController.php:191-194 |
| H6 | Apple id_token wird nur base64-dekodiert, ohne Signaturprüfung und ohne aud/iss/exp-Prüfung, Risiko der Identitätsverwechslung über Anwendungen hinweg | OAuthController.php:376-380 |

### MITTEL — Zuverlässigkeit/Implementierungsmängel

| # | Problem |
|---|------|
| M1 | 2FA-Mängel doppelt: `/api/2fa/verify` öffentlich ohne pro-Benutzer-Versuchssperre (Brute-Force-Oracle); TOTP verwendet den Base32-String direkt als HMAC-Schlüssel (nicht dekodiert), nicht kompatibel mit Authenticator → **2FA praktisch unbrauchbar** |
| M2 | Auszahlungsprüfung/-auszahlung ist check-then-act ohne atomaren Statuswechsel, Nebenläufigkeit kann mehrfach auszahlen; keine doppelte Prüfung |
| M3 | Webhook-Callback-URL nur per filter_var geprüft, kann auf interne IPs zeigen (SSRF), dispatch POSTet an beliebige URLs |
| M4 | Tages-/Monatslimit der Auszahlung ist "erst prüfen, dann einfügen" nicht atomar, Nebenläufigkeit kann das Limit durchbrechen |
| M5 | Redis-Ausfall fail-open ohne einheitliche Abstraktion: JWT-Blacklist-Abmeldung wirkungslos, Ratenbegrenzung stumm wirkungslos; Degradierungslücken: PayoutService::getAccessToken, ChatWebSocket brpop, OAuth-state-Speicherung |
| M6 | ClickHouse null Nutzung: Wahrscheinlichkeitsberechnung ist tatsächlich MySQL-Echtzeit-COUNT(DISTINCT)+Subquery-JOIN, O(n²)-Risiko auf großen Tabellen; composer-Abhängigkeit ohne Funktion |
| M7 | Warteschlange halbfertig: admin/app/queue hat ComputeDailyStats + 3 ES-Tasks, aber webman/queue ist nicht installiert, process.php ohne Registrierung, alles ohne Aufrufer |
| M8 | Toter Code: Vip/Achievement/Notification/FeatureFlag-Services ohne Aufrufer; DepositLogService::log() leere Implementierung; Test-Modellrest; Retention-Algorithmus grobe Einzel-Cohort-Berechnung |

### NIEDRIG
- Auszahlung ohne 2FA/KYC-Pflicht kann an beliebige PayPal-E-Mail-Adressen ausgezahlt werden; Prüfungsnotizen gelangen in Benachrichtigungstexte (XSS-Fläche)
- Dokumente weichen von der Realität ab: install.sql 43 Tabellen vs. Dokumente schrieben einst 52; docker-compose 7 Dienste vs. FEATURES.md schrieb einst 8; "gemeinsame Modelle 34" unzutreffend (admin 46 / service 44 je eine Kopie, keine gemeinsame Schicht). CHANGELOG wurde ergänzt, siehe `docs/CHANGELOG.md`.

### Bestandene Punkte (vom Sicherheitsreview als unproblematisch bestätigt)
Wallet-Optimistic-Lock + Versionsbedingtes Update korrekt; Callback-Idempotenz `where status=pending`-bedingtes Update korrekt; durchgehend ORM ohne direktes SQL-Splicing; .env nicht in git; admin alle Routen mit AdminAuth+RBAC Standard-Ablehnung; OAuth-state-Prüfung + einmaliger Verbrauch korrekt.

> **Reparaturstatus 2026-08-18**: C1/C2/C3/H1/H5/H6 behoben; H2 Event-Bus: `process.php` registriert `event-consumer` und die Konsumentenklasse `EventConsumer` ist umgesetzt, emit hat Konsumenten; M1 Base32 + pro-Benutzer-Sperre behoben; M2 Auszahlungsstatus atomar + optionale doppelte Prüfung umgesetzt; M3 Webhook-SSRF blockiert; M4 Redis-Benutzersperre bei Auszahlungsanträgen umgesetzt; M5 teilweise fertig (RateLimit fail-closed); P2-19 Geschäftskennzahlen + FeatureFlag-Canary umgesetzt. Die Problemliste bleibt als historisches Audit-Ergebnis erhalten.

---

## 三、Roadmap

### P0 — Finanzsicherheit + Korrektheit (zuerst, blockiert den Release)

1. **Zahlungs-Callback fail-closed**: Provider-Whitelist (nur stripe/paypal) + fehlender Schlüssel muss mit 500 ablehnen + PayPal-Ausnahmen immer ablehnen (C1/C2) — ✅ abgeschlossen (2026-08-18: Provider-Whitelist + kanalübergreifende Missbrauchsprüfung + optionale Quell-IP-Prüfung + transaktionale Callback-Gutschrift)
2. **JWT-Startprüfung**: env ohne `JWT_SECRET_KEY` verweigert den Start (C3) — ✅ abgeschlossen (2026-08-18: Start verweigert, wenn JWT_SECRET_KEY fehlt oder den Standardwert `open-admin-jwt-secret-change-in-production` hat, admin/service konsistent)
3. **Analyseservice-Routen einhängen**: analytics-12-Routen + Berechtigungspunkte registrieren, VERSIONS.md-Zusage reparieren (H1) — ✅ abgeschlossen (2026-08-18: admin/config/route.php registriert 12 Routen `/admin/analytics/*`)
4. **Event-Bus durchgängig machen**: dauerhaften Subscribe-Prozess zur Konsumation registrieren oder auf synchronen Direktaufruf umstellen; Events in die Datenbank schreiben + Fehler-Retry (H2) — ✅ abgeschlossen (2026-08-18: emit/consume macht INCR auf Redis-Zähler; `service/config/process.php` registriert `event-consumer`, `service/app/process/EventConsumer.php` konsumiert Events)
5. **Apple id_token-Signaturprüfung**: JWKS-Verifikation + aud/iss/exp (H6) — ✅ abgeschlossen (2026-08-18: RS256 JWKS + kid-Refresh + aud/iss/exp)
6. **Stripe-Replay und Betragsabgleich**: Zeitstempeltoleranz + Vergleich mit Gateway-Betrag (H5) — ✅ abgeschlossen (2026-08-18: t=-Zeitstempel ±300s gegen Replay + bccomp-Präzisionsbetragsabgleich + Ablehnung bei fehlendem secret/webhook_id oder Signaturfehlern)

### P1 — Zuverlässigkeit + Konsistenz

7. **Gemeinsame Schicht entdoppeln**: common/model als composer path repo ausziehen (oder Symlink), Doppelkopie-Drift beseitigen (H3) — 🔶 teilweise abgeschlossen (2026-08-18: `common/service` in ein einziges `packages/platform-common` / `erik/platform-common` path repo ausgezogen (das ursprüngliche `common-php` wurde eingegliedert), admin+service referenzieren es; model und die host-gebundenen `app/common`-Wrapper sind weiterhin doppelt, siehe `packages/platform-common/DUAL_MODELS.md`)
8. **Einheitliche Redis-Degradierungs-Kapselung**: Fail-Strategien explizit machen + Alarm nicht stumm; PayoutService/OAuth/ChatWebSocket-Fallback ergänzen (M5) — 🔶 teilweise abgeschlossen (RateLimit fail-closed umgesetzt: bei Redis-Ausfall wird die Ratenbegrenzung abgelehnt statt stumm freigegeben; Rest offen)
9. **webman/queue verdrahten**: Events und Webhook-Zustellung tragen (Konsum-Retry, Dead-Letter), ComputeDailyStats/ES-Tasks aktivieren oder löschen (M7) — ⬜ nicht erledigt
10. **2FA-Reparatur**: Base32-Dekodierung + verify mit Login-Status und pro-Benutzer-Versuchssperre (M1) — ✅ abgeschlossen (2026-08-18: HMAC nach RFC-4648-Base32-Dekodierung; `/api/2fa/verify` sperrt nach 5 Fehlversuchen für 15 Minuten, fail-closed bei Redis-Ausfall)
11. **Auszahlung atomarisieren**: bedingtes Update von Prüfung/Auszahlung + doppelte Prüfung; Limits per Redis Lua/eindeutiger Constraint (M2/M4) — 🔶 teilweise abgeschlossen (2026-08-18: pending→approved/rejected, approved→processing als bedingte UPDATEs; optionale doppelte Prüfung `withdraw.require_dual_review`; Redis-Benutzersperre auf Antragsseite. Keine Lua-Limits/eindeutige Constraints)
12. **Webhook-SSRF-Blockade**: interne/Reserviert-Adressen ablehnen (M3) — ✅ abgeschlossen (2026-08-18: `isSafeWebhookUrl()` nur HTTPS-Public)
13. **ClickHouse: eine von zwei Optionen**: echte Anbindung oder Abhängigkeit entfernen + Dokumente überarbeiten (M6) — ⬜ nicht erledigt
14. **Toten Code bereinigen**: Vip/Achievement/Notification/FeatureFlag verdrahten oder löschen; Test-Modell löschen; DepositLog-Audit in die Datenbank (M8) — 🔶 teilweise abgeschlossen (2026-08-18: Test-Modell gelöscht, DepositLog-Audit in der Datenbank; Vip/FeatureFlag/Notification haben Aufrufer; AchievementService wird von EventConsumer aufgerufen)
15. **service-Tests + CI-Tor**: Integrations-Tests für Callback-Signaturprüfung/Auszahlungsfluss/Redis-Degradierung/Wahrscheinlichkeitsberechnung/Optimistic-Lock-Nebenläufigkeit; phpunit-Fehlschlag blockiert; service in CI aufnehmen (aktuell `|| echo warning` erlaubt Fehlschlag) — 🔶 teilweise abgeschlossen (service hat WebhookUrlSafety / EventBusMessageFormat; in CI-Job `phpunit-service` aufgenommen, Fehlschlag blockiert)

**In dieser Runde (2026-08-18) zusätzlich abgeschlossen (außerhalb der ursprünglichen Nummerierung)**:
- **Tabellenpräfix-Fix**: 52 Modelle vom hartkodierten `game_`-Präfix befreit, Doppelpräfix `game_game_` beseitigt; DB-Präfix kommt einheitlich aus config/database.php `prefix=game_`, install.sql unverändert
- **refresh token neu geschrieben**: service AuthController-Refresh-Token-Logik neu geschrieben
- **DepositLogService service-Version portiert**: service/common/service/DepositLogService.php vervollständigt (beseitigt einen der admin/service-Doppelkopie-Drifts)

### P2 — Beobachtbarkeit / Erweiterung / Erlebnis

16. **HarmonyOS-C-End** 5 Seiten von Null implementieren (Login/Lobby/Details/Wallet/Profil) (H4) — ✅ abgeschlossen (2026-08-18: `apps/harmonyos/entry/src/main/ets/pages/` 5 Seiten im Repo)
17. **Frontend vervollständigen**: 2FA-Verifizierungsseite, Gutschein-/Ranglisten-/Benachrichtigungs-Einstiege, ES-Such-UI; main.dart/app_pages.dart-Routeneinstieg zusammenführen; echte OAuth-Callbacks; AES-Transportschicht im Frontend
18. **Wahrscheinlichkeitsberechnung auf ClickHouse migrieren** oder MySQL-materialisierte Statistiktabelle + Cache; Retention nach echter Cohort neu berechnen
19. **Prometheus-Geschäftskennzahlen** (Event-Zustellungs-/Konsumrate, Warteschlangentiefe) + Canary-AB-Verteilungs-Middleware (FeatureFlag wiederverwenden) — 🔶 teilweise abgeschlossen (2026-08-18: `GET /metrics` ausstehende Auszahlungsprüfungen/heute bestätigte Einzahlungen/Event-emit·consume-Zähler; FeatureFlag `inRollout`/`abTest` crc32-Bucketing. Warteschlangentiefe offen)
20. **WebSocket-Datenkette schließen**: Bestätigung der Persistierung von Ranglisten/Chat
21. **Dokumente angleichen**: Tabellen-/Dienstanzahl-/Gemeinsame-Schicht-Beschreibungen korrigieren, API-Dokumentation mit Implementierung angleichen, CHANGELOG ergänzen — ✅ abgeschlossen (2026-08-18: siehe `docs/CHANGELOG.md`, FEATURES/VERSIONS/PROJECT-PLAN/Audit-Berichte §十)

---

## 四、Qualitätstore (Teamkoordination)

- Bei jeder Codeänderung: admin-Gesamttests `vendor/bin/phpunit` müssen bestehen (ohne `|| echo warning`)
- Neue sensible Pfade (Zahlung/Auszahlung/Authentifizierung) müssen Tests mitbringen
- Bei Änderungen an common/model beide Seiten admin+service synchronisieren (bis zur gemeinsamen Schicht)
- Review-Bericht-Empfehlungen mit Schwerpunkt: ProviderAuth-Signatur, AES-Verschlüsselung, handgeschriebenes SQL in ProbabilityService

## 五、Team

Das game-platform-Team (6 Mitglieder: researcher/architect/backend-dev/frontend-dev/tester/reviewer) ist bereit, P0 direkt auszuführen.
