# service/ — API-Dienst der Benutzerplattform (C-Seite)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · **Deutsch** · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Der API-Dienst der Benutzerplattform (C-Seite) ist ein leistungsstarkes PHP-Backend auf Basis von webman v2 (Workerman) und bietet Nutzern den vollständigen Funktionsumfang der Spiel-Aggregationsplattform: Registrierung und Login, Wallet, Einzahlung, Auszahlung, Tausch, Spiele, Ranglisten, Gutscheine, Support-Tickets, VIP, Erfolge, Soziale Funktionen und Ankündigungen.

## Funktionsübersicht

| Modul | Beschreibung |
|------|------|
| Benutzer | Registrierung/Login (Benutzername/Passwort + 7-Plattform-OAuth + 2FA TOTP), Profil |
| Wallet | Plattform-Token-Wallet (optimistisches Sperren) + Spielwährungs-Wallet + Transaktionsverlauf |
| Einzahlung | 13 Zahlungsanbieter (Stripe/PayPal/NowPayments/Coinbase usw.) mit Callback-Signaturprüfung und automatischer Gutschrift |
| Auszahlung | Antrag → Prüfung → Auszahlung, gestaffelte KYC-Limits |
| Tausch | Echtzeitkurse Plattform-Token ⇄ Spielwährung, VIP-Rabatte und Kurszuschläge |
| Spiele | Spielliste/Kategorien/Suche, Spielverlauf, Provider-Settlement-Callbacks |
| Ranglisten | Tages-/Wochen-/Monats-/Gesamt + WebSocket-Echtzeit-Push |
| Gutscheine | Festbetrag + prozentualer Rabatt, zeit- und mengenbegrenzt |
| Tickets | Nutzer erstellen/beantworten Support-Tickets |
| VIP | 5 Loyalitätsstufen, Erfahrungspunkte, Tauschrabatte |
| Erfolge | 12 integrierte Erfolge, ereignisgesteuerte Erkennung |
| Sozial | Freundessystem + WebSocket-Echtzeitnachrichten |
| Ankündigungen | In-App-Ankündigungen + Benachrichtigungen/E-Mail |

## Technologie-Stack

- PHP 8.3+ / webman v2 (workerman/webman)
- MySQL 8.0+ (Tabellenpräfix `game_`, BIGINT-Primärschlüssel ohne Auto-Increment)
- Redis (Session / Cache / Ratenbegrenzung)
- ClickHouse (OLAP-Analysen / Wahrscheinlichkeitsberechnung)
- Elasticsearch (Volltextsuche)
- JWT-Authentifizierung + HMAC-SHA256-Provider-Signatur

## Projektstruktur

```
service/
├── app/
│   ├── api/v1/controller/  # C-Seiten-API-Controller (35)
│   ├── middleware/         # Middleware (Cors/SecurityFilter/RateLimit/ApiVersion/UserAuth/ProviderAuth)
│   ├── model/              # Datenmodelle
│   ├── service/            # Geschäftsdienste (VIP/Ranglisten/Risiko/Benachrichtigungen usw.)
│   ├── event/              # Event-Bus (EventBus Redis Pub/Sub)
│   ├── provider/           # Spiel-Provider-Schicht
│   └── payment/            # Zahlungsanbieter
├── common/                 # Gemeinsame Dienste (implementiert im Paket erik/platform-common)
├── config/                 # Konfigurationsdateien
├── public/                 # Web-Einstieg
├── tests/                  # PHPUnit-Tests
├── start.php               # Startpunkt
└── composer.json
```

## Ein-Klick-Installation

Empfohlen wird der Ein-Klick-Installationsassistent im Projektstamm (im Projektstamm ausführen):

```bash
# 1. Installationsassistent starten
php -S 0.0.0.0:8888 -t install/

# 2. http://localhost:8888 im Browser öffnen
#    Dem Assistenten folgen: Umgebungsprüfung → Datenbankkonfiguration → Admin-Konto → Auto-Installation
```

Oder alles per Docker Compose starten (Projektstamm):

```bash
docker compose up -d
```

## Manuelle Installation

```bash
# 1. Abhängigkeiten installieren
cd service && composer install

# 2. Umgebungsvariablen konfigurieren
cp .env.example .env
# .env bearbeiten: Datenbankverbindung, JWT-Schlüssel usw.

# 3. Dienst starten (Standardport 8788)
php start.php start        # Vordergrund
php start.php start -d     # Hintergrund (Daemon)
```

## Verwendung

- API-Referenz: `docs/API.md` (vollständige Referenz)
- Online-Dokumentation: http://localhost:8788/apidoc/ (interaktive hg/apidoc-Dokumentation)
- Health-Check: `GET http://localhost:8788/health`
- C-Seiten-Frontend: `apps/flutter/platform/` (Flutter-Web-Benutzerplattform)
- Admin-Backend: `admin/` (Backend und Frontend unter `admin/apps/flutter/`)

## Tests

```bash
cd service
SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
```
