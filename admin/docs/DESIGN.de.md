# Offenes Verwaltungs-Backend — Design-Dokument
<!-- lang-nav -->

Languages: [中文](DESIGN.md) · [English](DESIGN.en.md) · [한국어](DESIGN.ko.md) · [Русский](DESIGN.ru.md) · **Deutsch** · [Français](DESIGN.fr.md) · [Español](DESIGN.es.md) · [Português](DESIGN.pt.md) · [हिन्दी](DESIGN.hi.md) · [العربية](DESIGN.ar.md) · [বাংলা](DESIGN.bn.md) · [Bahasa Indonesia](DESIGN.id.md) · [日本語](DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Detaillierte Mermaid-Architekturdiagramme siehe [ARCHITECTURE.md](ARCHITECTURE.de.md) (in GitHub/GitLab/VS Code automatisch gerendert).

## 1. Systemarchitektur

> **Funktionsliste**: Authentifizierung (login/register/refresh/logout + Kontosperre + Sitzungslimit) | Dashboard (Redis-Cache) | Benutzer-CRUD + Batch + Import | Rollen & Berechtigungen (RBAC) | Systemkonfiguration | Betriebsprüfung (8-Plattform-Quellen) | Dateien (Upload + Export + Maskierung) | Sicherheit (18 Ebenen Verteidigung) | Betrieb (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Backend-Architektur

### 2.1 Schichten-Design

| Schicht | Verzeichnis | Verantwortung |
|---|------|------|
| Routen | `config/route.php` | URL-zu-Controller-Zuordnung, Middleware-Bindung, versionierte Routen |
| Middleware | `app/middleware/` | Angriffsabwehr (SecurityFilter), Rate-Limiting (RateLimit), Authentifizierung (JWT), Autorisierung (RBAC), API-Version (ApiVersion) |
| Controller | 30 Stück: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs/Metrics/Analytics/Game/Payment/Withdraw... (Admin) + Captcha/Auth (API v1) | Validierung der Anfrageparameter, Aufruf der Geschäftslogik, Antwortformatierung |
| Geschäftsdienste | `common/service/` | Datenanalyse: GameDashboardService (Übersicht/Ranking/Trend), DepositLogService (Umsatz/Konversion), ProbabilityService (gemeinsame/bedingte Wahrscheinlichkeit, SQL-Builder); bei DB-Ausfall leere Daten statt Fehler |
| Datenmodelle | `app/model/` | ORM-Zuordnung, Beziehungen, Feldver-/entschlüsselung |
| Gemeinsame Werkzeuge | `app/common/` | Hashids-, Snowflake-, Encryption-Dienste |

### 2.2 Anfrage-Lebenszyklus

```
Client-Anfrage
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route-Matching
  │
  ▼
Middleware-Kette:
  SecurityFilter ──────► HTTP-Methodenprüfung → 405 (nur GET/POST/PUT/DELETE/OPTIONS/HEAD erlaubt)
  │                     XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF-Angriffsabwehr (403)
  ▼
  RateLimit ───────────► Redis-Sliding-Window-Rate-Limiting
  │ (bei Fehler 429 + Retry-After-Header)
  ▼
  ApiVersion ─────────► API-Version-Header-Prüfung, injiziert $request->apiVersion
  │ (bei Fehler 400)
  ▼
  AdminAuth ──────────► JWT-Prüfung, injiziert $request->adminId
  │ (bei Fehler 401)
  ▼
  AdminPermission ────► RBAC-Berechtigungsprüfung (Redis-Cache 60s)
  │ (bei Fehler 403)
  ▼
  OperationLog ───────► Operationsprotokoll (POST/PUT/DELETE), automatische Quellen-Erkennung
  │
  ▼
Controller::method()
  │
  ├─► Parametervalidierung (validator)
  ├─► Bestätigung sensibler Aktionen (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model-Operationen (automatische encryptable-Ver-/entschlüsselung)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 ID-Lebenszyklus

```
Generierung (Snowflake) → Speicherung (MySQL BIGINT) → Transport (Hashids-Kodierung) → extern (hash-Zeichenfolge)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Datenverschlüsselungssystem

```
Transportebene (encryption)     — AES-256-CBC, eigener Schlüssel
Speicherebene (encryptable)     — AES-128-ECB, eigener Schlüssel, automatische Verarbeitung über Model-$casts
Anzeigeebene (mask)             — Telefonnummer: 138****1234, E-Mail: a***@example.com
```

## 3. Datenbank-Design

### 3.1 ER-Beziehungen

```
erik_admin_user ──┬── erik_admin_user_role ──┬── erik_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    erik_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    erik_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           erik_operation_log
             (操作日志)

erik_system_config (系统配置) — 独立表
```

### 3.2 Kern-Tabellenstrukturen

| Tabellenname | Anzahl Felder | Beschreibung |
|------|-------|------|
| `erik_admin_user` | 14 | Verwaltungsbenutzer, phone/email/id_card verschlüsselt gespeichert, Soft-Delete unterstützt |
| `erik_admin_role` | 7 | Rollen, slug eindeutig |
| `erik_admin_permission` | 10 | Berechtigungsbaum (parent_id-Selbstreferenz), type: 1=Menü 2=Button 3=API |
| `erik_admin_user_role` | 2 | Viele-zu-viele-Zwischentabelle Benutzer-Rolle |
| `erik_admin_role_permission` | 2 | Viele-zu-viele-Zwischentabelle Rolle-Berechtigung |
| `erik_system_config` | 8 | Key-Value-Konfiguration, group+key gemeinsam eindeutig |
| `erik_operation_log` | 9 | Operationsprüfprotokolle (inkl. source-Quellenangabe) |

### 3.3 Primärschlüssel-Standards

- Typ: `BIGINT UNSIGNED NOT NULL`
- Eigenschaft: **nicht auto-inkrementierend**, auf Anwendungsebene vom Snowflake-Algorithmus erzeugt
- Vorteile: global eindeutig, verteilungsfreundlich, trendmäßig aufsteigend (indexfreundlich), gibt keine Geschäftszahlen preis
- Konfiguration: datacenter_id(0-31) + worker_id(0-31), unterstützt 1024 Knoten parallel

## 4. API-Design

### 4.1 URL-Standards

```
Öffentliche Schnittstellen:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

Admin:     /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

Ressourcen-Routen:
  GET    /admin/user          → Liste
  POST   /admin/user          → anlegen
  GET    /admin/user/{hashid} → Details
  PUT    /admin/user/{hashid} → aktualisieren
  DELETE /admin/user/{hashid} → löschen (Passwortbestätigung erforderlich)

Systemkonfiguration:  /admin/config[/{hashid}]
Operationsprotokoll:  /admin/log
Persönlicher Bereich:  /admin/profile[/password|/logout]
Import:     /admin/import/users
Upload:     /admin/upload
Batch:     /admin/user/batch/{destroy|status}
Dokumentation:     /api/docs     (OpenAPI 3.0)
Health:     /health
```

### 4.2 API-Versionsstrategie

Die API-Version wird über einen Request-Header gesteuert und **erscheint nicht im URL-Pfad**:

```http
API-Version: v1
```

| Mechanismus | Beschreibung |
|------|------|
| Standardversion | ohne `API-Version`-Header standardmäßig `v1` |
| Prüfung | `ApiVersion`-Middleware prüft; nicht unterstützte Versionen liefern 400 |
| Routing | die Hilfsfunktion `v()` löst Controller-Klassen dynamisch je nach Version auf |
| Verzeichnis | Controller nach Version organisiert: `app/api/{version}/controller/` |

Erweiterungsbeispiel — neue v2-API hinzufügen:
1. `app/api/v2/controller/AuthController.php` anlegen
2. In der `ApiVersion`-Middleware der `SUPPORTED`-Konstante `'v2'` hinzufügen
3. Routendefinitionen müssen nicht geändert werden

```bash
# v1 verwenden
curl -H "API-Version: v1" /api/auth/login

# v2 verwenden
curl -H "API-Version: v2" /api/auth/login

# Ohne Header, standardmäßig v1
curl /api/auth/login
```

### 4.3 Rate-Limiting-Strategie

Basiert auf dem Redis-Sorted-Set-Sliding-Window-Algorithmus, ausgeführt als atomares Lua-Skript:

| Schnittstelle | Limit |
|------|------|
| Standard | 60/Minute/IP/Route |
| POST /api/auth/login | 10/Minute |
| POST /api/auth/register | 5/Minute |

Bei Überschreitung wird 429 zurückgegeben; der Response enthält die Header X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 Einheitliche Antwort

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Bedeutung | Auslöseszenario |
|------|------|---------|
| 0 | Erfolg | normale Antwort |
| 400 | Parameterfehler | Anfrageformat nicht korrekt |
| 401 | Nicht authentifiziert | Token fehlt/abgelaufen/ungültig |
| 403 | Keine Berechtigung | Benutzerrolle enthält die benötigte Berechtigung nicht |
| 404 | Nicht vorhanden | Ressource nicht gefunden |
| 422 | Validierung fehlgeschlagen | Formularparameter entsprechen nicht den Regeln / Passwortbestätigung fehlgeschlagen |
| 500 | Serverfehler | unerwartete Ausnahme |

### 4.5 Authentifizierungsablauf (inkl. Klick-CAPTCHA)

```
Client                               Server
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② Benutzer klickt auf Textpositionen im Bild │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 Berechtigungsmodell (RBAC)

```
  Benutzer ──┬── Rolle ──┬── Berechtigung
  User       Role         Permission
                 │
                 ├── type=1: Menü (steuert Sichtbarkeit der Sidebar)
                 ├── type=2: Button (steuert Aktionen innerhalb der Seite)
                 └── type=3: API  (steuert Schnittstellenzugriff)

  Format der Berechtigungskennung: {method}.{path}
  Beispiel: get.admin/user  post.admin/user  delete.admin/user
  Super-Admin-Kennung: * (überspringt alle Berechtigungsprüfungen)
```

### 4.7 Sekundäre Bestätigung bei sensiblen Aktionen

Bei sensiblen Aktionen wie dem Löschen von Benutzern, Rollen oder Berechtigungen muss im Requestbody das Passwort des aktuellen Benutzers zur Identitätsüberprüfung übergeben werden:

```
Client                           Server
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → falsches Passwort liefert 422
  │                                │ → richtiges Passwort: Ausführung fortsetzen
  │◄── 200 { code: 0 }           │
```

Das Frontend zeigt vor dem Auslösen der Löschaktion einen Bestätigungsdialog und sendet die Anfrage nach Eingabe des Benutzerpassworts.

## 5. Frontend-Design

### 5.1 Flutter-Web-Verwaltungs-Backend

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

Eigenschaften: einklappbare Sidebar, Material-3-Dual-Theme, hochdichte Datentabellen, Dialog-Popups, Hover-Interaktionen

### 5.2 HarmonyOS-Mobilclient

Seitenrouting:

| Seite | Route | Beschreibung |
|------|------|------|
| LoginPage | `pages/LoginPage` | Login mit Benutzername/Passwort + Klick-CAPTCHA |
| DashboardPage | `pages/DashboardPage` | Statistik-Karten + letzte Aktionen |
| UserListPage | `pages/UserListPage` | Benutzerliste, Suche + Pull-to-Refresh + Scroll-Laden |
| UserDetailPage | `pages/UserDetailPage` | Anlegen/Bearbeiten/Anzeigen/Löschen (AlertDialog-Bestätigung) |
| ProfilePage | `pages/ProfilePage` | Persönlicher Bereich, Logout (AlertDialog-Bestätigung) |

Datenfluss: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Sicherheitsdesign

### 6.1 Verteidigung in der Tiefe

| Ebene | Maßnahme |
|------|------|
| Methodenbegrenzung | SecurityFilter-HTTP-Methoden-Whitelist, nur GET/POST/PUT/DELETE/OPTIONS/HEAD erlaubt, nicht-standardmäßige Methoden liefern 405 |
| Angriffsabwehr | SecurityFilter-Middleware, Erkennung und Abwehr von XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF |
| Mensch-Maschine-Verifikation | Klick-CAPTCHA (Click Captcha), erzwungene Prüfung bei Login/Registrierung |
| Kontosperre | 5 fehlgeschlagene Logins in Folge sperren das Konto für 15 Minuten; während der Sperre wird 429 zurückgegeben |
| Sitzungslimit | maximal 3 parallele Tokens pro Benutzer; bei Überschreitung wird das älteste Token automatisch in die Blacklist aufgenommen |
| Rate-Limiting | RateLimit-Middleware, Redis-Sliding-Window, atomar per Lua |
| CSP | Content-Security-Policy-Header begrenzt Ressourcenquellen, gegen XSS und Dateninjektion |
| Aktionsbestätigung | bei sensiblen Aktionen wie Löschen muss das Passwort des aktuellen Benutzers zur sekundären Bestätigung eingegeben werden |
| Transport | HTTPS + JWT-Bearer-Token |
| Schnittstellen-IDs | Hashids-Verschlüsselung, echte IDs extern nicht umkehrbar |
| Requestbody | AES-256-CBC-Verschlüsselung sensibler Felder |
| Datenbank | BIGINT-Primärschlüssel (gibt keinen Auto-Increment-Wert preis) |
| Datenbank | AES-128-ECB-Verschlüsselung sensibler Felder |
| Authentifizierung | JWT HS256, 2h Ablauf + Refresh-Token |
| Autorisierung | RBAC, method.path-granulare Berechtigungssteuerung |
| Prüfung | OperationLog zeichnet alle Aktionen auf (inkl. automatischer Quellen-Erkennung `source`) |

### 6.2 Schlüsselverwaltung

```
JWT_SECRET          → per Umgebungsvariable injiziert, 64 Zeichen Zufallszeichenfolge
HASHIDS_SALT        → eindeutiger Salt-Wert, bei Leak globaler Austausch nötig
ENCRYPTION_KEY      → API-Transport-Verschlüsselungsschlüssel, 32 Bytes
ENCRYPTABLE_KEY     → DB-Speicher-Verschlüsselungsschlüssel, getrennt vom Transportschlüssel
SCOUT_HOSTS         → ES-Adresse, Intranet-Bereitstellung
```

### 6.3 Schutz sensibler Daten

| Szenario | Feld | Maßnahme |
|------|------|------|
| Listenansicht | phone | maskiert: 138****1234 |
| Listenansicht | email | maskiert: a***@example.com |
| Detailansicht | phone/email | benötigt Entschlüsselungsschnittstelle |
| Excel-Export | phone/email | maskiert exportieren |
| PDF-Export | alle Felder | maskiert + nicht entfernbarer Copyright-Wasserzeichen |
| Speicherung | phone/email/id_card | per encryptable zu Geheimtext verschlüsselt |

## 7. Export-Design

### 7.1 Excel-Export

```
Anfrage: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() Daten abfragen (limit 10000)
  → sensible Felder maskieren
  → PhpSpreadsheet-Aufbau (blauer Hintergrund/weiße Schrift im Kopf + erste Zeile eingefroren + Auto-Filter)
  → in runtime/tmp/ schreiben → download-Antwort
```

### 7.2 PDF-Export

```
Anfrage: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + Inline-CSS + Copyright im Kopf + nicht entfernbarer Copyright-Fuß
  → Dompdf rendert A4-Querformat
  → in runtime/tmp/ schreiben → download-Antwort
```

## 8. Bereitstellungsarchitektur

### 8.1 Empfohlene Topologie

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                    Statische Dateien: Flutter Web build/
```

### 8.2 Docker Compose (für Produktion empfohlen)

Das `docker-compose.yml` im Projektstamm orchestriert alle Dienste der obigen Topologie:

| Dienst | Image/Build | Port | Beschreibung |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Reverse-Proxy + statische Dateien + Gzip |
| `app` | lokaler `Dockerfile`-Build | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Hauptdatenbank, persistentes Datenvolume |
| `redis` | redis:7-alpine | 6379 | Cache / Rate-Limiting / CAPTCHA |
| `elasticsearch` | elasticsearch:8.x | 9200 | Volltextsuche |

Vor dem Start die Schlüssel `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY` usw. in `docker-compose.yml` durch Zufallszeichenfolgen ersetzen.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

Die GitHub-Actions-Integration ist in `.github/workflows/ci.yml` definiert:
- PHP-Syntaxprüfung (`php -l`)
- PHPUnit-Unit-Tests
- Flutter-Statische-Analyse (`flutter analyze`)

### 8.4 Datenbanksicherung

`database/backup/backup.sh` — mysqldump + gzip-Sicherung, automatische Bereinigung von Sicherungen älter als 30 Tage.
`database/backup/restore.sh` — interaktive Auswahl und Wiederherstellung von Sicherungen.

### 8.5 Monitoring

Der Endpunkt `GET /metrics` (`MetricsController`) legt im Prometheus-Textformat 5 Gauge-Metriken offen: HTTP-Anfrage-Gesamtzahl, aktive Benutzer, Datenbank-/Redis-Verbindungsstatus, Speichernutzung.

### 8.6 Umgebungsanforderungen

| Komponente | Mindestversion | Empfohlene Konfiguration |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ mit OPcache aktiviert |
| MySQL | 8.0+ | 8.0+ Master-Replica-Replikation |
| Elasticsearch | 7.x | 8.x 3-Knoten-Cluster |
| Redis | 6.x | 7.x Sentinel-Modus |
| Nginx | 1.20+ | Reverse-Proxy + gzip + SSL |
| Flutter SDK | 3.41+ | neueste stabile Version |
| HarmonyOS | API 12 | DevEco Studio 5.x |
