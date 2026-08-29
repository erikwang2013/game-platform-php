# Offenes Verwaltungs-Backend (open-admin)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · **Deutsch** · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)


Ein Full-Stack-Verwaltungs-Backend-System basierend auf webman v2 + Flutter.

> [Englische Version](README.en.md) | [Architektur-Design](docs/ARCHITECTURE.de.md) | [Design-Dokument](docs/DESIGN.de.md) | [Sicherheitsarchitektur](docs/SECURITY.de.md) | [API-Referenz](docs/API.de.md)

## Funktionsliste

| Geschäftsbereich | Funktion | Beschreibung |
|--------|------|------|
| 🔐 Authentifizierung | Login/Registrierung/Token-Refresh/Logout | Klick-CAPTCHA + JWT + Blacklist |
| | Kontosperre | 5 Fehlversuche sperren für 15 Minuten |
| | Begrenzung paralleler Sitzungen | maximal 3 gültige Token pro Benutzer |
| 📊 Dashboard | Echtzeit-Statistiken/Trenddiagramm/Verteilungsdiagramm/letzte Aktionen | Redis-Cache 5 Minuten |
| 📈 Datenanalyse | 12 Endpunkte: Übersicht/Ranking/DAU/Stunden/Verhaltensverteilung/Umsatz/Konversion/Wahrscheinlichkeit/Retention/Funnel/ARPU/Wirtschaftsindikatoren | Echtzeit-Aggregation in MySQL, bei DB-Ausfall leere Daten |
| 👥 Benutzerverwaltung | CRUD + Batch-Löschen/Aktivieren-Deaktivieren | Soft-Delete + Passwort-Bestätigung |
| | Excel-Batch-Import | zeilenweise Validierung + Fehlerbericht |
| 🔒 Rollen & Berechtigungen | Rollen-CRUD + Berechtigungsbaum | RBAC method.path-granulare Autorisierung |
| ⚙ Systemkonfiguration | Key-Value-CRUD | Gruppenverwaltung |
| 📋 Betriebsprüfung | Protokollabfrage + Quellen-Erkennung | automatische Erkennung von 8 Plattformen |
| 📁 Dateiverwaltung | Upload/Excel-Export/PDF-Export | automatische Maskierung sensibler Daten |
| 🛡 Sicherheitsschutz | 18 Ebenen Verteidigung in der Tiefe | XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF/Rate-Limiting/CSP... |
| 🏥 Betrieb | Health-Check/metrics/API-Dokumentation/security.txt | Prometheus + OpenAPI 3.0 |

## Technologie-Stack

| Ebene | Technologie | Beschreibung |
|---|------|------|
| Backend-Framework | webman v2 (workerman) | Hochleistungs-PHP-Daemon-Framework |
| PHP-Version | 8.3+ | |
| Datenbank | MySQL 8.0+ | Tabellenpräfix `game_`, BIGINT-IDs ohne Auto-Increment |
| Suchmaschine | Elasticsearch | Synchronisation und Abfrage über `webman-scout` |
| Admin-Frontend | Flutter 3.x | Web-Version im PC-Admin-Stil (`apps/flutter/`) |
| Mobil | HarmonyOS ArkTS | Natives HarmonyOS-Client (`apps/harmonyos/`), unterstützt Phone/Tablet/2in1 |

## Kernabhängigkeiten

| Paket | Zweck |
|---|------|
| `erikwang2013/snowflake-php` | Snowflake-Algorithmus zur Generierung global eindeutiger BIGINT-Primärschlüssel |
| `erikwang2013/hashids` | ID-Ver-/Entschlüsselung auf API-Ebene, verbirgt echte Datenbank-IDs |
| `erikwang2013/jwt-webman` | Ausstellung und Prüfung von JWT-Authentifizierungstokens |
| `erikwang2013/encryption` | Ver-/Entschlüsselung sensibler Daten auf Schnittstellen-Transportebene |
| `erikwang2013/encryptable` | automatische Ver-/Entschlüsselung sensibler Felder auf Datenbank-Speicherebene |
| `erikwang2013/webman-scout` | Elasticsearch-Datensynchronisation und Volltextsuche |
| `erikwang2013/season` | Länderflaggen-Daten |
| `erikwang2013/poster-php` | Generierung und Prüfung von Klick-CAPTCHAs + Poster-Generierung |
| `phpoffice/phpspreadsheet` | Excel-Export |
| `barryvdh/laravel-dompdf` | PDF-Export (basiert auf Dompdf) |

## Projektstruktur

```
open-admin/
├── app/
│   ├── admin/controller/       # Admin-Controller
│   │   ├── DashboardController.php # Dashboard (Redis-Cache)
│   │   ├── UserController.php      # Benutzer-CRUD + Batch-Aktionen
│   │   ├── RoleController.php      # Rollen-CRUD
│   │   ├── PermissionController.php# Berechtigungs-CRUD
│   │   ├── ConfigController.php    # Systemkonfigurations-CRUD
│   │   ├── LogController.php       # Abfrage der Operationsprotokolle
│   │   ├── ProfileController.php   # Persönlicher Bereich + Logout
│   │   ├── ExportController.php    # Excel/PDF-Export
│   │   ├── ImportController.php    # Excel-Benutzerimport
│   │   ├── UploadController.php    # Datei-Upload
│   │   ├── HealthController.php    # Health-Check
│   │   ├── DocsController.php      # OpenAPI-Dokumentation
│   │   └── BaseController.php      # Basis-Controller
│   ├── api/
│   │   └── v1/controller/          # API-v1-Controller (Version über Request-Header API-Version gesteuert)
│   │       ├── CaptchaController.php # Klick-CAPTCHA
│   │       └── AuthController.php    # Login/Registrierung/Token-Refresh
│   ├── common/                 # Gemeinsame Hilfsklassen
│   │   ├── HashidsService.php  # ID-Kodierung/-Dekodierung
│   │   ├── SnowflakeService.php# Snowflake-ID-Generierung
│   │   └── EncryptionService.php # Datenver-/entschlüsselung + Maskierung
│   ├── middleware/             # Middleware
│   │   ├── Cors.php            # Cross-Origin
│   │   ├── SecurityFilter.php  # Angriffserkennung und -abwehr (HTTP-Methodenbegrenzung/XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF)
│   │   ├── RateLimit.php       # Redis-Rate-Limiting (Sliding Window + Response-Header)
│   │   ├── ApiVersion.php      # API-Versionsprüfung
│   │   ├── AdminAuth.php       # JWT-Authentifizierung + Blacklist
│   │   ├── AdminPermission.php # RBAC-Berechtigungsprüfung
│   │   └── OperationLog.php    # automatische Aufzeichnung von Operationsprotokollen (inkl. Quellen-Erkennung)
│   └── model/                  # Datenmodelle
├── apps/
│   ├── flutter/                # Flutter-Web-Verwaltungs-Backend (PC-Stil)
│   │   └── lib/app/
│   │       ├── pages/          # 5 vollständige Seiten (Dashboard/Benutzer/Rollen/Konfiguration/Protokolle/Persönlicher Bereich)
│   │       ├── services/       # ApiService (JWT-Interceptor) + AuthService (Token-Persistenz)
│   │       └── layouts/        # Responsives Admin-Layout (Sidebar + Topbar + Inhaltsbereich)
│   └── harmonyos/              # Natives HarmonyOS-Client (nahtloses Token-Refresh)
├── config/                     # Konfigurationsdateien (mit chinesischen Kommentaren)
│   ├── route.php               # Routen + API-Versionsstrategie
│   ├── middleware.php           # Registrierung globaler Middleware
│   └── ...                     # Komponentenkonfigurationen
├── install/        # SQL-Migrationsdateien (inkl. Berechtigungs-Seed-Daten)
├── public/                     # Öffentlicher Einstiegspunkt
├── runtime/                    # Laufzeitdateien
└── vendor/                     # Composer-Abhängigkeiten
```

## Umgebungsanforderungen

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (nur für Frontend-Entwicklung nötig)
- Elasticsearch >= 7.x (optional, für die Suchfunktion nötig)

## Schnellstart

### 1. Abhängigkeiten installieren

```bash
composer install
```

### 2. Umgebungsvariablen konfigurieren

Umgebungsvariablen kopieren und anpassen (optional; ohne Konfiguration gelten die Standardwerte aus `config/*.php`):

```bash
cp .env.example .env
```

Wichtige Konfigurationsoptionen:

| Umgebungsvariable | Beschreibung | Standardwert |
|---------|------|--------|
| `JWT_SECRET` | JWT-Signaturschlüssel | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids-Salt | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API-Verschlüsselungsschlüssel | 32-Byte-Standardwert |
| `SNOWFLAKE_DATACENTER_ID` | Rechenzentrums-ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | Worker-Knoten-ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES-Adresse | `http://localhost:9200` |

**In Produktion müssen alle Schlüssel unbedingt durch Zufallszeichenfolgen ersetzt werden.**

### 3. Datenbank initialisieren

Die SQL-Dateien unter `install/` in Reihenfolge ausführen:

```bash
mysql -u root -p < install/install.sql
```

### 4. Dienst starten

```bash
php start.php start
```

Standardmäßig lauscht der Dienst auf `http://0.0.0.0:8787`.

### 5. Frontend starten (optional)

**Flutter-Verwaltungs-Backend (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (PC-Admin-Stil)
```

**HarmonyOS-Client (Mobil):**

Mit DevEco Studio das Verzeichnis `apps/harmonyos/` öffnen und auf echtem Gerät oder Emulator ausführen.

### 6. Docker-Compose-Ein-Klick-Bereitstellung (für Produktion empfohlen)

Das Projekt bietet eine vollständige Docker-Orchestrierung mit 5 Diensten: Nginx, PHP (webman app), MySQL, Redis, Elasticsearch.

```bash
# 1. Docker-Umgebungsvariablen konfigurieren
cp .env.docker .env

# 2. Alle Dienste starten
docker-compose up -d

# 3. Datenbank initialisieren (im app-Container ausführen)
docker-compose exec app mysql -h mysql -u root -p < install/install.sql

# 4. Zugriff
# http://localhost:8787  (webman)
# http://localhost:8080  (Nginx-Reverse-Proxy)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, basierend auf `php:8.3-cli`
- `docker-compose.yml`: Orchestrierung von 5 Diensten, Netzwerk-Isolation, persistente Datenvolumes
- `.env.docker`: für die Docker-Umgebung spezifische Umgebungsvariablen


## Datenbank-Standards

- **Tabellenpräfix**: `game_`
- **Primärschlüssel**: Alle Tabellen verwenden `id BIGINT UNSIGNED NOT NULL` als Primärschlüssel, **AUTO_INCREMENT ist verboten**
- **ID-Generierung**: Primärschlüssel-IDs werden auf Anwendungsebene per `SnowflakeService::generate()` erzeugt, verteilt eindeutig
- **Pflichtfelder**: Jede Tabelle muss `id`, `created_at`, `updated_at` enthalten
- **Soft-Delete**: Tabellen mit Soft-Delete erhalten `deleted_at DATETIME DEFAULT NULL`
- **Sensible Felder**: Telefonnummern, E-Mails, Ausweisnummern usw. werden über das `encryptable`-Plugin automatisch ver-/entschlüsselt; Datenbankfelder speichern den Geheimtext in `VARCHAR(500)`

## API-Standards

### Einheitliches Antwortformat

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Geschäftsfehlercodes

| Fehlercode | Bedeutung | Beschreibung |
|-------|------|------|
| `0` | Erfolg | |
| `400` | Ungültige Anfrageparameter | |
| `401` | Nicht angemeldet (Token ungültig oder abgelaufen) | |
| `403` | Keine Berechtigung / Sicherheitsintervention | RBAC-Autorisierungsfehler / SecurityFilter-Angriffserkennung |
| `404` | Ressource nicht gefunden | |
| `422` | Parametervalidierung fehlgeschlagen | |
| `413` | Anfragebody zu groß | von SecurityFilter ausgelöst, über 10MB |
| `405` | Anfragemethode nicht erlaubt | von SecurityFilter ausgelöst, nur GET/POST/PUT/DELETE/OPTIONS/HEAD erlaubt |
| `415` | Nicht unterstützter Medientyp | von SecurityFilter ausgelöst, Content-Type ist kein JSON |
| `429` | Zu viele Anfragen | von RateLimit ausgelöst / Kontosperre (5 fehlgeschlagene Logins sperren 15 Minuten) |
| `500` | Interner Serverfehler | |

### ID-Behandlung

- **IDs in Anfragen/Antworten**: mit hashids zu Zeichenfolgen verschlüsselt, echte Datenbank-IDs werden nicht offengelegt
- **Schnittstellenpfade**: `GET /admin/user/{hashid}` — `{id}` im Pfad ist eine Hashid-Zeichenfolge
- **Datenbankspeicher**: BIGINT-Rohwert, von snowflake erzeugt

### API-Versionierung

Die API-Version wird über einen Request-Header gesteuert und **erscheint nicht in der URL**:

```http
API-Version: v1
```

- Ohne Versionsangabe wird standardmäßig `v1` verwendet
- Nicht unterstützte Versionen geben `400 Bad Request` zurück
- Für eine neue Version genügt ein Verzeichnis `app/api/{version}/controller/`; die Middleware registriert die neue Version

### Rate-Limiting

Basiert auf dem Redis-Sliding-Window-Algorithmus, standardmäßig 60 Anfragen/Minute/IP/Route. Sensible Schnittstellen sind strenger:
- Login: 10 pro Minute
- Registrierung: 5 pro Minute

Die Antwort enthält die Header `X-RateLimit-Limit`, `X-RateLimit-Remaining` und `X-RateLimit-Reset`. Bei Überschreitung wird 429 mit `Retry-After` zurückgegeben.

### Middleware-Architektur

Globale Middleware wirkt auf alle Anfragen und wird in Reihenfolge ausgeführt:

```
Cors (Cross-Origin-Vorverarbeitung + Response-Header)
  → SecurityFilter (HTTP-Methodenbegrenzung/Requestbody-Größe/Content-Type-Prüfung/XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF-Angriffsabwehr)
  → RateLimit (Redis-Sliding-Window-Rate-Limiting + Kontosperre: 5 fehlgeschlagene Logins sperren 15 Minuten)
  → ApiVersion (API-Versionsprüfung, /api-Routengruppe)
  → AdminAuth (JWT-Authentifizierung + Blacklist, /admin-Routengruppe)
  → AdminPermission (RBAC-Autorisierung, /admin-Routengruppe)
  → OperationLog (automatische Aufzeichnung von POST/PUT/DELETE, inkl. Quellen-Erkennung, /admin-Routengruppe)
```

`/health` und `/api/docs` sind öffentliche Endpunkte und durchlaufen nur `Cors → SecurityFilter → RateLimit`.

Sicherheitserweiterungen:
- **Kontosperre**: Nach 5 fehlgeschlagenen Logins in Folge wird das Konto automatisch für 15 Minuten gesperrt; während dieser Zeit gibt der Login 429 zurück
- **Begrenzung paralleler Sitzungen**: maximal 3 gültige Tokens pro Benutzer; bei Überschreitung wird das älteste Token automatisch in die Blacklist aufgenommen
- **security.txt**: `GET /.well-known/security.txt` stellt Standard-Sicherheitskontaktinformationen gemäß RFC 9116 bereit
- **Nginx-Sicherheitskonfiguration**: `docs/nginx-security.conf` bietet eine vollständige Referenz zur Härtung des Reverse-Proxys

### Authentifizierung

Login und Registrierung erfordern zunächst die Prüfung des **Klick-CAPTCHAs**:

1. Der Client fordert `POST /api/captcha/generate` an und erhält das CAPTCHA-Bild (base64 PNG) sowie die Liste der Textziele
2. Der Benutzer klickt in der richtigen Reihenfolge auf die entsprechenden Textpositionen im Bild; die Klickkoordinaten `[{x, y}, ...]` werden gesammelt
3. Beim Login werden `captcha_key` und `clicks` mitgesendet; der Server prüft zuerst das CAPTCHA und dann die Zugangsdaten

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

Nachfolgende Admin-Schnittstellen erfordern die JWT-Authentifizierung:

```http
Authorization: Bearer <token>
```

Nach erfolgreichem Login werden ein access_token (2 Stunden gültig) und ein refresh_token (14 Tage gültig) zurückgegeben.

Beim Logout wird das Token in die Redis-Blacklist aufgenommen und kann bis zum Ablauf nicht wiederverwendet werden. POST /admin/profile/logout

### Sekundäre Bestätigung bei sensiblen Aktionen

Bei sensiblen Aktionen wie dem Löschen von Benutzern, Rollen oder Berechtigungen muss im Requestbody das `password` des aktuell angemeldeten Benutzers zur sekundären Identitätsbestätigung übergeben werden:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API-Liste

> Alle `/api/*`-Schnittstellen müssen den Header `API-Version: v1` mitführen (ohne Angabe standardmäßig v1).

### Öffentliche Schnittstellen

| Methode | Pfad | Beschreibung |
|-----|------|------|
| `GET` | `/health` | Health-Check (DB/Redis/ES-Status) |
| `GET` | `/api/docs` | OpenAPI-3.0-Spezifikationsdokument |
| `POST` | `/api/captcha/generate` | Klick-CAPTCHA erzeugen |
| `POST` | `/api/captcha/verify` | Klick-CAPTCHA prüfen |
| `POST` | `/api/auth/login` | Login (CAPTCHA erforderlich) |
| `POST` | `/api/auth/register` | Registrierung (CAPTCHA erforderlich) |
| `POST` | `/api/auth/refresh` | Token aktualisieren |
| `GET` | `/metrics` | Prometheus-Monitoring-Metriken |

### Admin-Schnittstellen (JWT + RBAC erforderlich)

| Methode | Pfad | Beschreibung |
|-----|------|------|
| `GET` | `/admin/dashboard` | Dashboard-Daten (Redis-Cache 5 Minuten) |
| `GET` | `/admin/user` | Benutzerliste (Seitennummerierung + Suche) |
| `POST` | `/admin/user` | Benutzer anlegen |
| `GET` | `/admin/user/{id}` | Benutzerdetails |
| `PUT` | `/admin/user/{id}` | Benutzer aktualisieren |
| `DELETE` | `/admin/user/{id}` | Benutzer löschen (Soft-Delete, Passwortbestätigung erforderlich) |
| `POST` | `/admin/user/batch/destroy` | Benutzer in Serie löschen (Passwortbestätigung erforderlich) |
| `POST` | `/admin/user/batch/status` | Benutzer in Serie aktivieren/deaktivieren |
| `GET` | `/admin/role` | Rollenliste |
| `POST` | `/admin/role` | Rolle anlegen |
| `PUT` | `/admin/role/{id}` | Rolle aktualisieren |
| `DELETE` | `/admin/role/{id}` | Rolle löschen (Passwortbestätigung erforderlich) |
| `GET` | `/admin/permission` | Berechtigungsbaum |
| `POST` | `/admin/permission` | Berechtigung anlegen |
| `PUT` | `/admin/permission/{id}` | Berechtigung aktualisieren |
| `DELETE` | `/admin/permission/{id}` | Berechtigung löschen (kaskadierende Unterberechtigungen, Passwortbestätigung erforderlich) |
| `GET` | `/admin/config` | Systemkonfigurationsliste |
| `POST` | `/admin/config` | Konfigurationseintrag anlegen |
| `PUT` | `/admin/config/{id}` | Konfigurationseintrag aktualisieren |
| `DELETE` | `/admin/config/{id}` | Konfigurationseintrag löschen (Passwortbestätigung erforderlich) |
| `GET` | `/admin/log` | Operationsprotokolle (Seitennummerierung + Filter) |
| `PUT` | `/admin/profile` | Persönliche Daten aktualisieren |
| `PUT` | `/admin/profile/password` | Passwort ändern |
| `POST` | `/admin/profile/logout` | Logout (JWT-Blacklist) |
| `POST` | `/admin/export/excel` | Excel exportieren |
| `POST` | `/admin/export/pdf` | PDF exportieren |
| `POST` | `/admin/import/users` | Benutzer per Excel importieren |
| `POST` | `/admin/upload` | Datei-Upload (Bilder/Dokumente, max. 10MB) |

## Frontend-Hinweise

### Flutter-Verwaltungs-Backend (PC-Stil)

- **Layout**: Sidebar (einklappbar 64px/240px) + Topbar + Inhaltsbereich, responsive mit drei Breakpoints (Mobil/Tablet/Desktop)
- **Seiten**: Login, Dashboard, Benutzerverwaltung, Rollen & Berechtigungen, Systemkonfiguration, Operationsprotokolle, Persönlicher Bereich
- **State-Management**: GetX (`ApiService`-Singleton + `AuthService`-Token-Persistenz)
- **Dashboard**: Statistik-Karten, Trend-Liniendiagramm (fl_chart), Kreisdiagramm, letzte Operationsprotokolle
- **Export**: Excel/PDF-Export, PDF enthält nicht entfernbare Copyright-Informationen
- **Batch-Aktionen**: Mehrfachauswahl-Batch-Löschen, Batch-Aktivieren/Deaktivieren
- **Theme**: Material 3, helles/dunkles Dual-Theme

### HarmonyOS-Mobilclient

- **Seiten**: Login, Dashboard, Benutzerliste/-details, Persönlicher Bereich
- **Authentifizierung**: JWT Bearer + nahtloses automatisches Token-Refresh bei 401; bei Refresh-Fehler automatische Weiterleitung zur Login-Seite
- **Speicherung**: Token wird über AppStorage verwaltet

## Entwicklungsstandards

- Bei globalen Funktionen/Klassen kein vorangestelltes `\`; einheitlich per `use` importieren
- Alle PHP-Dateien müssen oben die Copyright-Erklärung enthalten
- Alle Konfigurationsdateien müssen chinesische Kommentare enthalten
- Datenbank-Primärschlüssel müssen auf Anwendungsebene per snowflake erzeugt werden; Auto-Increment ist verboten
- Alle IDs in API-Parametern und -Antworten müssen per hashids ver-/entschlüsselt werden
- Die AdminPermission-Middleware cached Benutzerberechtigungen in Redis (TTL=60s), um den N+1-Abfrage-Engpass zu beseitigen

## Bereitstellung

### Docker Compose (empfohlen)

Im Projektstamm liegt `docker-compose.yml`, das 5 Dienste orchestriert:

| Dienst | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | lokaler `Dockerfile`-Build | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

Das PHP-Image wird über die `Dockerfile` gebaut, Basis-Image `php:8.3-cli`, mit aktiviertem OPcache.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

GitHub-Actions-CI-Pipeline: `.github/workflows/ci.yml`

- PHP-Syntaxprüfung (`php -l`)
- PHPUnit-Unit-Tests
- Flutter-Statische-Analyse (`flutter analyze`)

### Datenbanksicherung

Verzeichnis `database/backup/`:

- `backup.sh` — mysqldump + gzip-Sicherung, automatische Bereinigung von Sicherungen älter als 30 Tage
- `restore.sh` — interaktive Wiederherstellung, listet verfügbare Sicherungen zur Auswahl

### Nginx-Sicherheitskonfiguration

Für die Produktionsbereitstellung `docs/nginx-security.conf` als Referenz zur Härtung des Reverse-Proxys verwenden.

## Open Source ist harte Arbeit — Unterstützung willkommen

| WeChat Pay | Alipay |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Projekt-Maskottchen

![Projekt-Maskottchen: Dicey](../docs/mascot.svg)

**Dicey** — Plattform-Maskottchen. Der Würfel steht für Spiele und wahrscheinlichkeitsbasiertes Gameplay, die Münze für die Plattform-Ökonomie und die Multi-Payment-Gateways, das Lila spiegelt das Admin-Branding wider. SVG-Quelle: `docs/mascot.svg`, unbegrenzt skalierbar für Doku, Logos und Merchandise.
