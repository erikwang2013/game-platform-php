# Changelog
<!-- lang-nav -->

Languages: **中文** · [English](CHANGELOG.en.md) · [한국어](CHANGELOG.ko.md) · [Русский](CHANGELOG.ru.md) · [Deutsch](CHANGELOG.de.md) · [Français](CHANGELOG.fr.md) · [Español](CHANGELOG.es.md) · [Português](CHANGELOG.pt.md) · [हिन्दी](CHANGELOG.hi.md) · [العربية](CHANGELOG.ar.md) · [বাংলা](CHANGELOG.bn.md) · [Bahasa Indonesia](CHANGELOG.id.md) · [日本語](CHANGELOG.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Menschenlesbares Änderungsprotokoll. PHP importiert diese Datei nicht. Entspricht PROJECT-PLAN P2-21.

## [1.1] — 2026-08-07

- Redis-Plugin-Integration, Analyseservices, Redis-Degradierung, Testkorrekturen.

## [1.1] security / ops — 2026-08-18

### Sicherheit

- Zahlungs-Callbacks: Provider-Whitelist (stripe/paypal), Fail-closed-Signaturprüfung, Betragsabgleich, transaktionale Gutschrift, Stripe-Zeitstempel ±300s gegen Replay-Angriffe.
- JWT: Start wird verweigert, wenn `JWT_SECRET_KEY` / `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY` fehlen oder Standardwerte haben.
- Apple id_token: JWKS (RS256)-Signaturprüfung + aud/iss/exp.
- Webhooks: nur HTTPS-Public-URLs, interne/Reserviert-Adressen ablehnen (SSRF).
- 2FA: TOTP-HMAC mit RFC-4648-Base32-dekodiertem Schlüssel; `/api/2fa/verify` sperrt bei Fehlversuchen pro Benutzer (5 Versuche / 15 Minuten, Fail-closed bei Redis-Ausfall).
- Auszahlung: atomarer Statuswechsel der UPDATE-Bedingungen bei Prüfung/Auszahlung; optional doppelte Prüfung (`withdraw.require_dual_review`); Redis-Benutzersperre auf Antragsseite gegen Durchbrechen des Limits durch Nebenläufigkeit.
- Ratenbegrenzung: Fail-closed bei Redis-Ausfall.

### Verfügbarkeit

- admin-Analyseservices: 12 Routen `/admin/analytics/*` gemountet.
- Modelle ohne hartkodiertes `game_`-Präfix; DepositLog-Audit wird in die Datenbank geschrieben; Test-Modell gelöscht.

### Beobachtbarkeit

- `GET /metrics` erweitert um ausstehende Auszahlungsprüfungen, heute bestätigte Einzahlungen (COUNT-Abfrage mit Redis-30s-Cache), Event-emit/consume-Zähler, `memory_usage`, `info version=1.1`.
- FeatureFlag: `inRollout` / `abTest` liest `feature.{name}_percent` mit crc32-Bucketing.
- EventBus `emit` / `consume` macht INCR auf Redis `metrics:event_emit_total` / `metrics:event_consume_total`.

### Client / gemeinsame Schicht (gleichen Tag nachgezogen)

- Flutter Platform: Routentabelle `app_pages.dart`; 2FA-Einrichtung/-Validierung, Gutscheine, Ranglisten, Benachrichtigungen, OAuth-Callback-Seiten ergänzt; Lobby-Einstieg an Navigation angebunden.
- HarmonyOS-C-End: `apps/harmonyos/` fünf Seiten (Login/Lobby/Details/Wallet/Profil), Standard-`BASE_URL` zeigt auf service `8788`.
- Gemeinsame Schicht: `packages/platform-common` (`erik/platform-common` Path-Repo) mit ausgezogenen DepositLog / GameDashboard / Probability / GamePlayLog; Modelle weiterhin doppelt vorhanden.
- ClickHouse: Composer-Abhängigkeit entfernt; Analysen laufen weiter über MySQL-Echtzeitaggregation.
- CI: admin / service laufen als getrennte Jobs mit phpunit, Fehlschlag blockiert.

### Verbleibende Lücken

- admin/service-**Modelle** weiterhin doppelt vorhanden (nur Teile von `common/service` im Path-Paket).
- `webman/queue` nicht angeschlossen; Wahrscheinlichkeit/Retention nicht auf OLAP migriert.
- PROJECT-PLAN / VERSIONS / Audit-Berichte können stellenweise hinter diesem CHANGELOG zurückliegen; diese Datei und der Datenträger sind maßgeblich.

## [1.1] resilience — 2026-08-27

### Stabilität

- Gemeinsame Schicht: `CircuitBreaker` (Zustand in Redis, Schwelle 5 / Fenster 30s, fail-open bei Redis-Ausfall) und `Retry` (exponentieller Backoff, nur Netzwerk-Exceptions, max. 5 Versuche) hinzugefügt, in `packages/platform-common/src/`.
- Degradationsschalter `feature.provider_mock`: PushService (FCM/APNs/HarmonyOS), PayoutService (PayPal), ThirdPartyProvider bei `on` überspringen echte Netzwerkaufrufe.
- 11 Typfehler von `getenv($name, '')` behoben (TypeError unter strict_types); Mock-Prüfung in PushService in try/catch verschoben.
- Neue Tests: CircuitBreakerTest / RetryTest / ResilienceMockTest; service-Suite 45 → 60 Fälle, alle grün (Bericht: [test-reports/resilience.md](test-reports/resilience.md)).

## [1.1] payments — 2026-08-29

- Multi-Payment-Gateways: Stripe Checkout / NOWPayments (USDT TRC20+ERC20) / Coinbase Commerce (USDC) + Alipay/WeChat Pay (Stripe Checkout APM).
- Admin-CRUD für Zahlungsmethoden + Länder-Sichtbarkeit + Betragsspannen; Einzahlungsaufträge schreiben checkout_url / expires_at sofort zurück.
- Neue Migration install/migrations/2026_08_29_multi_payment.sql (auszuführen).

## [1.1] cdn — 2026-08-29

- Fünf-Anbieter-CDN-Integration (Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS Upload + Purge + Preload) + Admin-Konfiguration (game_cdn_provider-Tabelle CRUD/Aktivierung/Verbindungstest per HeadBucket), Service liest nur aus der DB (config/cdn.php entfernt).

## [1.1] features — 2026-08-29

- Minispiel Farm Match-3 P0: Domain-Engine + 4-Level-Design + Vitest-Unit-Tests (`game/xiaoxiaole/`).
- Ein-Klick-Installationsassistent: Admin im Browser anlegen, bestehende DBs upgraden (behebt HY093 Bindungs-Parameter-Mismatch, Unknown column 'countries'), install.lock verhindert Neuinstallation.
- CI: automatischer inkrementeller Tag bei Push + GitHub-Release-Veröffentlichung.
- Infrastruktur: Datenbank in game-platform umbenannt, `game_`-Tabellenpräfix vereinheitlicht.
- Dokumentsynchronisierung: FEATURES.md in 13 Sprachen ergänzt um Resilienz (Circuit-Breaker/Retry/Degradation-Schalter), Admin-CRUD für Zahlungsmethoden, Minispiel, Ein-Klick-Installation, CI-Zeilen (entspricht den obigen Einträgen [1.1] resilience / payments).

## [1.1] reports — 2026-08-31

- Datenberichte: Admin `/admin/report/summary|daily|export` (Zusammenfassung/Tagesbericht/CSV-Export, Redis-5-min-Cache, Zeitraum ≤90 Tage).
- Plattform-Statistik (C-Seite): `GET /api/platform/stats` (Spiele/User gesamt, Spiele heute, 7-Tage-aktiv), an die Homepage-Statistik angeschlossen.
- Admin Flutter: Dashboard-Statistikkarten mit echten Daten verbunden, neue Berichtsseite ReportsPage (/reports).
- Doku-Sync: FEATURES/VERSIONS/API um Berichts- und Statistik-Einträge in 13 Sprachen ergänzt, Statistikbox der Funktionsübersicht aktualisiert.
