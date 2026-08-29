# API-Referenzdokument
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · **Deutsch** · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Überblick

Das offene Verwaltungs-Backend (open-admin) basiert auf webman v2 und stellt eine RESTful-JSON-API bereit. Alle Admin-Schnittstellen erfordern JWT-Authentifizierung und RBAC-Berechtigungsprüfung; öffentliche Schnittstellen werden über den API-Versionsheader an versionierte Controller geroutet.

- **Basis-URL**: `http://localhost:8787`
- **API-Version**: über den Request-Header `API-Version: v1` gesteuert (ohne Angabe standardmäßig v1)

> **Endpunktübersicht**: Authentifizierung (5) | Dashboard (1) | Benutzer (7) | Rollen (4) | Berechtigungen (4) | Konfiguration (4) | Protokolle (1) | Persönlicher Bereich (3) | Import/Export (3) | Upload (1) | Betrieb (4: health/metrics/docs/security.txt) | insgesamt 37 Endpunkte
- **Authentifizierung**: `Authorization: Bearer <token>` (JWT)
- **Antwortformat**: `{ "code": 0, "message": "success", "data": {...} }`
- **Dokumentationsendpunkt**: `GET /api/docs` liefert die OpenAPI-3.0-JSON-Spezifikation

### Anforderungen an Anfragen

- Es sind nur die Methoden `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` erlaubt; andere HTTP-Methoden (z. B. TRACE, CONNECT, PATCH) führen zu 405
- Alle `POST`- / `PUT`-Anfragen müssen `Content-Type: application/json` setzen (außer Datei-Uploads), sonst 415
- Der Requestbody darf höchstens 10MB groß sein, sonst 413
- Der Sicherheitsfilter scannt alle Anfrageeingaben auf XSS, SQL-Injection, Pfad-Traversal und Befehlsinjektion; bei Treffer wird 403 zurückgegeben
- 5 fehlgeschlagene Logins in Folge lösen eine Kontosperre aus (15 Minuten); während der Sperre gibt der Login 429 zurück
- Ein Benutzer darf höchstens 3 gültige Tokens gleichzeitig besitzen; bei Überschreitung wird das älteste Token automatisch in die Blacklist aufgenommen

## 2. Fehlercodes

| code | Bedeutung | Auslöseszenario |
|------|------|---------|
| 0 | Erfolg | |
| 400 | Ungültige Anfrageparameter | Anfrageformat nicht korrekt |
| 401 | Nicht authentifiziert | Token fehlt / abgelaufen / bereits in der Blacklist |
| 403 | Keine Berechtigung / Sicherheitsintervention | RBAC-Berechtigung unzureichend / SecurityFilter-Treffer |
| 404 | Ressource nicht gefunden | Ziel der Abfrage/Aktualisierung/Löschung existiert nicht |
| 405 | Anfragemethode nicht erlaubt | nur GET/POST/PUT/DELETE/OPTIONS/HEAD erlaubt, nicht-standardmäßige Methoden werden direkt abgelehnt |
| 413 | Requestbody zu groß | Content-Length über 10MB |
| 415 | Nicht unterstützter Medientyp | POST/PUT-Anfrage mit Content-Type ungleich JSON und kein Datei-Upload |
| 422 | Parametervalidierung fehlgeschlagen | Pflichtfeld fehlt, Format falsch, Geschäftsvalidierung nicht bestanden |
| 429 | Zu viele Anfragen | RateLimit ausgelöst / Kontosperre (5 fehlgeschlagene Logins in Folge sperren 15 Minuten) |
| 500 | Interner Serverfehler | |

## 3. Öffentliche Endpunkte

Alle öffentlichen Endpunkte hängen unter der `/api`-Gruppe und werden über die `ApiVersion`-Middleware gemäß dem `API-Version`-Header an die entsprechenden versionierten Controller verteilt (z. B. `app\api\v1\controller\AuthController`).

### 3.1 Health-Check

```
GET /health
```

- **Authentifizierung**: keine
- **Rate-Limiting**: keins

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.1",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

Werte von `database`, `redis`, `elasticsearch`: `"ok"` | `"unavailable"`. `elasticsearch` liefert `"unavailable"`, wenn ES nicht erreichbar ist; bei nicht grünem/gelbem Cluster-Gesundheitsstatus wird der tatsächliche Statuswert zurückgegeben (z. B. `"red"`).

### 3.2 API-Dokumentation

```
GET /api/docs
```

- **Authentifizierung**: keine
- **Rate-Limiting**: globaler Standard (60/Minute)
- **Antwort**: OpenAPI-3.0.3-JSON-Spezifikation mit allen Endpunktdefinitionen, Parametern und Schemas

### 3.3 Klick-CAPTCHA erzeugen

```
POST /api/captcha/generate
```

- **Authentifizierung**: keine
- **Request-Header**: `API-Version: v1` (erforderlich)
- **Rate-Limiting**: globaler Standard (60/Minute)

**Requestbody**:
```json
{
  "difficulty": "medium"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| difficulty | string | nein | `easy` / `medium` / `hard`, Standard `medium` |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| key | string | CAPTCHA-Kennung, wird bei der Prüfung zurückgesendet |
| image | string | base64-kodiertes PNG-Bild |
| extra.targets[].order | int | Klickreihenfolge |
| extra.targets[].text | string | Hinweistext des Klickziels |

### 3.4 Klick-CAPTCHA prüfen

```
POST /api/captcha/verify
```

- **Authentifizierung**: keine
- **Request-Header**: `API-Version: v1` (erforderlich)
- **Rate-Limiting**: globaler Standard (60/Minute)

**Requestbody**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| key | string | ja | CAPTCHA-Key, von generate zurückgegeben |
| clicks | array{object} | ja | Array der Klickkoordinaten, jedes Element mit `x` (int) und `y` (int) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

Bei fehlgeschlagener Prüfung ist `code` 422, `message` lautet `"验证失败，请重试"` und `data.valid` ist `false`.

### 3.5 Login

```
POST /api/auth/login
```

- **Authentifizierung**: keine
- **Request-Header**: `API-Version: v1` (erforderlich)
- **Rate-Limiting**: 10/Minute (nach IP + Pfad)

**Requestbody**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| username | string | ja | min:3, max:50 | Benutzername |
| password | string | ja | min:6, max:32 | Passwort |
| captcha_key | string | ja | | CAPTCHA-Key |
| clicks | array{object} | ja | min:2 | Array der Klickkoordinaten |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| access_token | string | JWT-Zugriffstoken |
| refresh_token | string | JWT-Refresh-Token |
| expires_in | int | Gültigkeitsdauer des Zugriffstokens (Sekunden), Standard 7200 |
| user.id | string | per hashid verschlüsselte Benutzer-ID |
| user.username | string | Benutzername |
| user.real_name | string | Echter Name |

**Mögliche Fehler**:
- 422: Parametervalidierung fehlgeschlagen (Pflichtfeld fehlt, Format falsch)
- 422: CAPTCHA falsch, bitte erneut versuchen
- 401: Benutzername oder Passwort falsch
- 403: Konto wurde deaktiviert
- 429: Konto gesperrt, bitte in 15 Minuten erneut versuchen (nach 5 fehlgeschlagenen Logins in Folge)

### 3.6 Registrierung

```
POST /api/auth/register
```

- **Authentifizierung**: keine
- **Request-Header**: `API-Version: v1` (erforderlich)
- **Rate-Limiting**: 5/Minute (nach IP + Pfad)

**Requestbody**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| username | string | ja | min:3, max:50 | Benutzername (eindeutig) |
| password | string | ja | min:6, max:32 | Passwort (bcrypt-Hash gespeichert) |
| real_name | string | ja | max:50 | Echter Name |
| captcha_key | string | ja | | CAPTCHA-Key |
| clicks | array{object} | ja | min:2 | Array der Klickkoordinaten |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

Nach erfolgreicher Registrierung werden direkt die JWT-Tokens zurückgegeben; der Benutzerstatus ist standardmäßig aktiviert (status=1).

### 3.7 Token aktualisieren

```
POST /api/auth/refresh
```

- **Authentifizierung**: keine
- **Request-Header**: `API-Version: v1` (erforderlich)
- **Rate-Limiting**: globaler Standard (60/Minute)

**Requestbody**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| refresh_token | string | ja | beim Login/bei der Registrierung erhaltenes refresh_token |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

Bei erfolgreicher Aktualisierung werden gleichzeitig neue access_token und refresh_token zurückgegeben; das alte Token verliert automatisch seine Gültigkeit. Beim Aktualisieren werden die letzte Login-Zeit und IP des Benutzers erneuert.

**Mögliche Fehler**:
- 422: Refresh-Token fehlt
- 401: Refresh-Token ungültig oder abgelaufen

### 3.8 Prometheus-Monitoring-Metriken

```
GET /metrics
```

- **Authentifizierung**: keine
- **Rate-Limiting**: keins
- **Antwortformat**: Prometheus-Textformat (`text/plain; version=0.0.4`)

Öffentlicher Endpunkt für Prometheus-Monitoring-Metriken, für das Scraping durch Grafana/Prometheus.

**Beispielantwort**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| Metrikname | Typ | Beschreibung |
|------|------|------|
| `openadmin_http_requests_total` | gauge | kumulierte Gesamtzahl der HTTP-Anfragen |
| `openadmin_active_users` | gauge | aktuell aktive Benutzer (innerhalb von 24 Stunden angemeldet) |
| `openadmin_db_connection_status` | gauge | Datenbankverbindungsstatus, 1=normal, 0=gestört |
| `openadmin_redis_connection_status` | gauge | Redis-Verbindungsstatus, 1=normal, 0=gestört |
| `openadmin_memory_usage_bytes` | gauge | aktueller Speicherverbrauch des PHP-Prozesses (bytes) |

## 4. Dashboard

Alle Admin-Schnittstellen hängen unter der `/admin`-Gruppe und durchlaufen die drei Middleware-Komponenten `AdminAuth` (JWT-Authentifizierung), `AdminPermission` (RBAC-Berechtigungsprüfung) und `OperationLog` (Aktionsaufzeichnung).

### 4.1 Dashboard-Daten

```
GET /admin/dashboard
```

- **Authentifizierung**: JWT + RBAC
- **Cache**: Redis 5 Minuten

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| stats-Feld | Typ | Beschreibung |
|------|------|------|
| label | string | Name der Kennzahl |
| value | string | Zahlenwert der Kennzahl (String-Typ) |
| icon | string | Material-Symbolname |
| color | string | Kartenfarbwert |
| trend | float? | tägliche Wachstumsrate im Vergleich zum Vortag (Prozent), nur bei „Benutzer gesamt" vorhanden |

| trends-Feld | Typ | Beschreibung |
|------|------|------|
| dates | array{string} | Datumsfolge der letzten 30 Tage |
| series | array{object} | Trendliniendaten, jede Linie mit name (Name), data (Zahlen-Array), color (Farbe) |

## 5. Benutzerverwaltung

Alle von der Benutzerverwaltung zurückgegebenen `id`s sind per hashid verschlüsselte Zeichenfolgen. Das Passwortfeld ist in Antworten ausgeschlossen. Telefonnummer und E-Mail werden in Listenantworten maskiert dargestellt; in Detailantworten werden sie im Klartext zurückgegeben (verschlüsselte Datenbankfelder werden vom Encryptable-Trait automatisch entschlüsselt).

### 5.1 Benutzerliste

```
GET /admin/user
```

- **Authentifizierung**: JWT + RBAC

**Abfrageparameter**:

| Parameter | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| page | int | nein | 1 | Seitennummer |
| limit | int | nein | 15 | Einträge pro Seite |
| keyword | string | nein | | Suchbegriff, matcht Benutzernamen und echte Namen |
| status | int | nein | | Statusfilter, 0=deaktiviert, 1=aktiviert |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | per hashid verschlüsselte Benutzer-ID |
| username | string | Benutzername |
| real_name | string | Echter Name |
| phone | string | maskierte Telefonnummer (Format `138****5678`) |
| email | string | maskierte E-Mail (Format `a***@example.com`) |
| status | int | 1=aktiviert, 0=deaktiviert |
| last_login_at | string | letzte Login-Zeit (datetime) |
| created_at | string | Erstellungszeit (datetime) |

### 5.2 Benutzer anlegen

```
POST /admin/user
```

- **Authentifizierung**: JWT + RBAC

**Requestbody**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| username | string | ja | min:3, max:50 | Benutzername (eindeutig) |
| password | string | ja | min:6, max:32 | Passwort (bcrypt gespeichert) |
| real_name | string | ja | max:50 | Echter Name |
| phone | string | nein | | Telefonnummer (Encryptable-verschlüsselt gespeichert) |
| email | string | nein | | E-Mail (Encryptable-verschlüsselt gespeichert) |
| status | int | nein | in:0,1 | Status, Standard 1 (aktiviert) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**Mögliche Fehler**:
- 422: Benutzername existiert bereits
- 422: Parametervalidierung fehlgeschlagen (Pflichtfeld fehlt)

### 5.3 Benutzerdetails

```
GET /admin/user/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Pfadparameter**: `{id}` ist die per hashid verschlüsselte Benutzer-ID

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

In der Detailschnittstelle werden `phone` und `email` im Klartext zurückgegeben (in der Datenbank verschlüsselt gespeichert, vom Encryptable-Cast automatisch entschlüsselt), ohne Maskierung. `password` und `id_card` erscheinen niemals in Antworten.

**Mögliche Fehler**:
- 404: Benutzer existiert nicht

### 5.4 Benutzer aktualisieren

```
PUT /admin/user/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Pfadparameter**: `{id}` ist die per hashid verschlüsselte Benutzer-ID

**Requestbody**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| real_name | string | nein | Echter Name; ohne Angabe bleibt der bisherige Wert erhalten |
| password | string | nein | Neues Passwort; bei leerer Zeichenfolge oder ohne Angabe keine Änderung |
| phone | string | nein | Telefonnummer |
| email | string | nein | E-Mail |
| status | int | nein | 0=deaktiviert, 1=aktiviert |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**Mögliche Fehler**:
- 404: Benutzer existiert nicht

### 5.5 Benutzer löschen

```
DELETE /admin/user/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Pfadparameter**: `{id}` ist die per hashid verschlüsselte Benutzer-ID
- **Sensible Aktion**: Passwort-Bestätigung erforderlich

**Requestbody**:
```json
{
  "password": "admin_password"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| password | string | ja | Passwort des aktuell angemeldeten Benutzers (sekundäre Bestätigung) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Es wird ein Soft-Delete ausgeführt (Eloquent SoftDeletes): Die Daten werden mit deleted_at markiert und nicht physisch gelöscht.

**Mögliche Fehler**:
- 404: Benutzer existiert nicht
- 422: Bei sensiblen Aktionen ist die Passwortbestätigung erforderlich (password leer)
- 422: Passwortprüfung fehlgeschlagen (Passwort stimmt nicht überein)

### 5.6 Benutzer in Serie löschen

```
POST /admin/user/batch/destroy
```

- **Authentifizierung**: JWT + RBAC
- **Sensible Aktion**: Passwort-Bestätigung erforderlich

**Requestbody**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| ids | array{string} | ja | Array per hashid verschlüsselter Benutzer-IDs |
| password | string | ja | Passwort des aktuell angemeldeten Benutzers (sekundäre Bestätigung) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Es wird ein Soft-Delete ausgeführt; `data.count` ist die tatsächlich gelöschte Anzahl.

**Mögliche Fehler**:
- 422: Bitte zu löschende Benutzer auswählen (ids leer)
- 422: Ungültige ID (hashid-Dekodierung fehlgeschlagen)
- 422: Passwortprüfung fehlgeschlagen

### 5.7 Benutzer in Serie aktivieren/deaktivieren

```
POST /admin/user/batch/status
```

- **Authentifizierung**: JWT + RBAC

**Requestbody**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| ids | array{string} | ja | Array per hashid verschlüsselter Benutzer-IDs |
| status | int | ja | 0=deaktiviert, 1=aktiviert |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

Die message variiert je nach status-Wert zwischen `"批量启用成功"` und `"批量禁用成功"`.

**Mögliche Fehler**:
- 422: Bitte Benutzer auswählen (ids leer)
- 422: Ungültiger Statuswert (status ist weder 0 noch 1)

## 6. Rollenverwaltung

### 6.1 Rollenliste

```
GET /admin/role
```

- **Authentifizierung**: JWT + RBAC

**Abfrageparameter**:

| Parameter | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| page | int | nein | 1 | Seitennummer |
| limit | int | nein | 15 | Einträge pro Seite |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | per hashid verschlüsselte Rollen-ID |
| name | string | Rollenname |
| slug | string | Rollenkennung (eindeutig, für die Berechtigungsprüfung) |
| description | string | Rollenbeschreibung |
| status | int | 1=aktiviert, 0=deaktiviert |
| users_count | int | Anzahl der Benutzer mit dieser Rolle |

### 6.2 Rolle anlegen

```
POST /admin/role
```

- **Authentifizierung**: JWT + RBAC

**Requestbody**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| name | string | ja | max:50 | Rollenname |
| slug | string | ja | max:50 | Rollenkennung |
| description | string | nein | | Rollenbeschreibung, Standard leere Zeichenfolge |
| status | int | nein | | Status, Standard 1 |
| permission_ids | array{int} | nein | | Array der Berechtigungs-IDs (rohe INT-IDs, keine Hashids) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 Rolle aktualisieren

```
PUT /admin/role/{id}
```

- **Authentifizierung**: JWT + RBAC

**Requestbody**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| name | string | nein | Rollenname |
| description | string | nein | Beschreibung |
| status | int | nein | 0=deaktiviert, 1=aktiviert |
| permission_ids | array{int} | nein | Array der Berechtigungs-IDs; bei Angabe werden die Rollenberechtigungen synchronisiert (überschrieben) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 Rolle löschen

```
DELETE /admin/role/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Sensible Aktion**: Passwort-Bestätigung erforderlich

**Requestbody**:
```json
{
  "password": "admin_password"
}
```

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Beim Löschen werden automatisch die Verknüpfungen der Rolle zu allen Berechtigungen und Benutzern gelöst, anschließend wird der Rollendatensatz physisch gelöscht.

## 7. Berechtigungsverwaltung

Berechtigungen nutzen eine Baumstruktur (parent_id-Selbstreferenz) und sind in drei Typen unterteilt. Die Listenansicht gibt den vollständigen Berechtigungsbaum zurück.

### 7.1 Berechtigungsbaum

```
GET /admin/permission
```

- **Authentifizierung**: JWT + RBAC

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | per hashid verschlüsselt |
| parent_id | string | hashid der Elternberechtigung, „0" bedeutet Wurzelknoten |
| name | string | Berechtigungsname |
| slug | string | Berechtigungskennung (Routen-/Button-Kennung) |
| type | int | 1=Menü, 2=Button, 3=Schnittstelle |
| icon | string | Menüsymbol (Material-Symbolname) |
| path | string | Frontend-Routenpfad |
| sort | int | Sortierwert (aufsteigend) |
| children | array? | Liste der Unterberechtigungen (rekursiv); ohne Unterknoten wird dieses Feld nicht ausgegeben |

### 7.2 Berechtigung anlegen

```
POST /admin/permission
```

- **Authentifizierung**: JWT + RBAC

**Requestbody**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| parent_id | int | nein | | ID der Elternberechtigung (roher INT-Typ), Standard 0 |
| name | string | ja | max:50 | Berechtigungsname |
| slug | string | ja | max:100 | Berechtigungskennung |
| type | int | ja | in:1,2,3 | 1=Menü, 2=Button, 3=Schnittstelle |
| icon | string | nein | | Menüsymbol, Standard leer |
| path | string | nein | | Frontend-Routenpfad, Standard leer |
| sort | int | nein | | Sortierwert, Standard 0 |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 Berechtigung aktualisieren

```
PUT /admin/permission/{id}
```

- **Authentifizierung**: JWT + RBAC

**Requestbody**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| name | string | nein | Berechtigungsname |
| icon | string | nein | Symbol |
| path | string | nein | Routenpfad |
| sort | int | nein | Sortierwert |

### 7.4 Berechtigung löschen

```
DELETE /admin/permission/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Sensible Aktion**: Passwort-Bestätigung erforderlich

**Requestbody**:
```json
{
  "password": "admin_password"
}
```

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Beim Löschen werden alle Unterberechtigungen kaskadierend mitgelöscht (Datensätze mit `parent_id` = aktueller Berechtigungs-ID), außerdem werden die Verknüpfungen zu allen Rollen gelöst.

## 8. Systemkonfiguration

Systemkonfigurationen sind über die Kombination `group` + `key` eindeutig.

### 8.1 Konfigurationsliste

```
GET /admin/config
```

- **Authentifizierung**: JWT + RBAC

**Abfrageparameter**:

| Parameter | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| page | int | nein | 1 | Seitennummer |
| limit | int | nein | 15 | Einträge pro Seite |
| group | string | nein | | nach Konfigurationsgruppe filtern |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | hashid |
| group | string | Konfigurationsgruppe (z. B. `system`, `email`, `storage`) |
| key | string | Konfigurationsschlüssel |
| value | string | Konfigurationswert |
| type | string | Hinweis auf den Werttyp (`string`, `integer`, `boolean`, `json` usw.) |
| description | string | Konfigurationsbeschreibung |

### 8.2 Konfiguration anlegen

```
POST /admin/config
```

- **Authentifizierung**: JWT + RBAC

**Requestbody**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| group | string | ja | max:100 | Konfigurationsgruppe |
| key | string | ja | max:100 | Konfigurationsschlüssel (innerhalb der Gruppe eindeutig) |
| value | string | ja | | Konfigurationswert |
| type | string | nein | | Werttyp, Standard `string` |
| description | string | nein | | Konfigurationsbeschreibung, Standard leer |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**Mögliche Fehler**:
- 422: Konfigurationseintrag existiert bereits (gleiche group + key)

### 8.3 Konfiguration aktualisieren

```
PUT /admin/config/{id}
```

- **Authentifizierung**: JWT + RBAC

**Requestbody**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| value | string | nein | Konfigurationswert aktualisieren |
| type | string | nein | Werttyp aktualisieren |
| description | string | nein | Beschreibungstext aktualisieren |

### 8.4 Konfiguration löschen

```
DELETE /admin/config/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Sensible Aktion**: Passwort-Bestätigung erforderlich

**Requestbody**:
```json
{
  "password": "admin_password"
}
```

Löscht den Konfigurationsdatensatz physisch.

## 9. Operationsprotokolle

Operationsprotokolle sind Nur-Lese-Schnittstellen; sie werden von der `OperationLog`-Middleware bei jeder POST/PUT/DELETE-Anfrage automatisch geschrieben. Gespeicherte Felder: `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Liste der Operationsprotokolle

```
GET /admin/log
```

- **Authentifizierung**: JWT + RBAC

**Abfrageparameter**:

| Parameter | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| page | int | nein | 1 | Seitennummer |
| limit | int | nein | 15 | Einträge pro Seite |
| user_id | int | nein | | exakter Filter nach Benutzer-ID (roher INT-Typ) |
| action | string | nein | | exakter Filter nach Aktion |
| path | string | nein | | unscharfer Filter nach Anfragepfad |
| start_date | string | nein | | Startdatum (Format Y-m-d) |
| end_date | string | nein | | Enddatum (Format Y-m-d) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | hashid |
| user_name | string | Benutzername des Ausführenden (über die user-Verknüpfung; bei nicht angemeldeten Aktionen „System") |
| action | string | Beschreibung der Aktion |
| method | string | HTTP-Methode (POST/PUT/DELETE) |
| path | string | Anfragepfad |
| ip | string | Client-IP |
| source | string | Anfragequelle |
| input | string | JSON-Zeichenfolge der Anfrageparameter (ohne Dateien) |
| created_at | string | Zeitpunkt der Aktion (datetime) |

## 10. Persönlicher Bereich

Die Schnittstellen des persönlichen Bereichs benötigen nur die JWT-Authentifizierung (keine RBAC-Berechtigungsprüfung — die `AdminPermission`-Middleware sollte sie in die Whitelist aufnehmen).

### 10.1 Persönliche Daten aktualisieren

```
PUT /admin/profile
```

- **Authentifizierung**: JWT

**Requestbody**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| real_name | string | nein | Echter Name |
| phone | string | nein | Telefonnummer (Encryptable-verschlüsselt gespeichert) |
| email | string | nein | E-Mail (Encryptable-verschlüsselt gespeichert) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

In der Antwort werden `phone` und `email` im Klartext zurückgegeben; `password` und `id_card` sind entfernt.

### 10.2 Passwort ändern

```
PUT /admin/profile/password
```

- **Authentifizierung**: JWT

**Requestbody**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| old_password | string | ja | | aktuelles Passwort |
| new_password | string | ja | min:6, max:32 | neues Passwort |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Mögliche Fehler**:
- 422: Bitte altes und neues Passwort angeben
- 422: Altes Passwort falsch
- 422: Neues Passwort muss 6-32 Zeichen lang sein

### 10.3 Logout

```
POST /admin/profile/logout
```

- **Authentifizierung**: JWT

**Requestbody**: keiner (kein requestBody, das Token wird aus dem Authorization-Header gelesen)

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Logout-Logik: JWT dekodieren, die restliche Gültigkeitsdauer ermitteln (exp - now), den md5-Hash des Tokens in die Redis-Blacklist `jwt_blacklist:{md5}` schreiben, TTL = restliche Gültigkeitsdauer. Tokens in der Blacklist werden in der `AdminAuth`-Middleware abgefangen und liefern 401.

Ohne Token wird 401 zurückgegeben. Bei abgelaufenem/ungültigem Token (Dekodierung wirft eine Ausnahme) gilt der Logout dennoch als erfolgreich.

## 11. Import/Export

### 11.1 Excel exportieren

```
POST /admin/export/excel
```

- **Authentifizierung**: JWT + RBAC
- **Antworttyp**: Dateidownload (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Requestbody**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| Feld | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| table | string | nein | `admin_user` | Name der Exporttabelle. Unterstützt: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | nein | | Array der zu exportierenden Spaltennamen; leer = alle Spalten dieser Tabelle exportieren |
| conditions | object | nein | `{}` | Filterbedingungen, Key-Value-Paare; nicht-leere Werte werden für WHERE verwendet |
| title | string | nein | `数据导出` | Excel-Titel (wird als Sheet-Name angezeigt) |

**Unterstützte Tabellen und Spalten**:

| table | verfügbare Spalten |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Die sensiblen Felder `phone`, `email` und `id_card` werden beim Export automatisch maskiert. Datenlimit: 10000 Zeilen. Erste Excel-Zeile ist eingefroren, automatischer Filter aktiv.

### 11.2 PDF exportieren

```
POST /admin/export/pdf
```

- **Authentifizierung**: JWT + RBAC
- **Antworttyp**: Dateidownload (`application/pdf`, A4-Querformat)

**Requestbody**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

Oder Tabellenmodus:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| Feld | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| type | string | nein | `table` | Exporttyp: `table` / `dashboard` |
| title | string | nein | `数据导出` | PDF-Titel |
| data | object | nein | `{}` | Exportdaten |

Bei `type=dashboard` muss `data` das Array `stats` enthalten (als Karten gerendert); bei `type=table` muss `data` die Arrays `columns` und `rows` enthalten.

Die PDF-Vorlage enthält Copyright-Informationen und einen Exportzeitstempel.

### 11.3 Benutzer importieren (Excel)

```
POST /admin/import/users
```

- **Authentifizierung**: JWT + RBAC
- **Anfragetyp**: `multipart/form-data` (Datei-Upload)

**Formularfelder**:

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| file | file | ja | Format `.xlsx` oder `.xls` |

**Excel-Spaltenanforderungen**:

| Spaltenname | Pflicht | Beschreibung |
|------|------|------|
| username | ja | Benutzername (eindeutig) |
| password | ja | Passwort (bcrypt-Hash gespeichert) |
| real_name | ja | Echter Name |
| phone | nein | Telefonnummer |
| email | nein | E-Mail |
| status | nein | Status, Standard 1 |

Zeile 1 enthält die Spaltenüberschriften (Groß-/Kleinschreibung egal), ab Zeile 2 folgen die Daten.

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| total | int | Gesamtzahl der Zeilen (ohne Überschriftszeile) |
| success | int | erfolgreich importierte Anzahl |
| failed | int | fehlgeschlagene Anzahl |
| errors | array | Fehlerdetails, jeder Eintrag mit row (Excel-Zeilennummer) und reason (Fehlerursache) |

## 12. Datei-Upload

```
POST /admin/upload
```

- **Authentifizierung**: JWT + RBAC
- **Anfragetyp**: `multipart/form-data`

**Formularfelder**:

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| file | file | ja | hochzuladende Datei |

**Erlaubte Dateitypen**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Maximale Dateigröße**: 10MB

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Dateien werden nach Datum sortiert in `public/upload/{Y-m-d}/` gespeichert, Dateiname = `md5(uniqid) + ursprüngliche Endung`. `url` ist ein relativer Pfad relativ zum Stammverzeichnis der Website.

**Mögliche Fehler**:
- 422: Bitte Datei auswählen (nicht hochgeladen)
- 422: Nicht unterstützter Dateityp
- 422: Dateigröße darf 10MB nicht überschreiten
- 500: Datei-Upload fehlgeschlagen (Datei ungültig)

## 13. Response-Header

Alle Schnittstellen (auf globaler Middleware-Ebene injiziert) enthalten folgende Response-Header:

| Header | Beschreibung |
|----|------|
| `X-RateLimit-Limit` | Rate-Limit-Obergrenze (Anzahl) |
| `X-RateLimit-Remaining` | verbleibende Anzahl der Anfragen |
| `X-RateLimit-Reset` | Zeitstempel des Zurücksetzens des Rate-Limit-Fensters |
| `Retry-After` | nur bei ausgelöstem Rate-Limit zurückgegeben, empfohlene Wartezeit in Sekunden |
| `X-Content-Type-Options` | `nosniff` (webman-Standard, verhindert MIME-Sniffing) |
| `X-Frame-Options` | `DENY` (von der CORS-Middleware/Basiskonfiguration von webman bereitgestellt) |

Rate-Limit-Details:
- Standard-Global-Limit: 60/Minute / IP+Pfad
- Login-Endpunkt `/api/auth/login`: 10/Minute
- Registrierungs-Endpunkt `/api/auth/register`: 5/Minute
- nutzt den atomaren Redis-Sliding-Window-Algorithmus (Lua ZSET), vermeidet TOCTOU-Race-Conditions
- Bei nicht verfügbarem Redis fail-closed: 503 zurückgeben (`Retry-After: 5`), Anfrage nicht durchlassen

## 14. Datenanalyse (Analytics)

Alle Endpunkte benötigen Authentifizierung (`AdminAuth` + `AdminPermission`), Echtzeit-Aggregation in MySQL, insgesamt 12:

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/analytics/overview | Plattform-Übersicht (heute/letzte 7 Tage) |
| GET | /admin/analytics/game-ranking | Spiel-Ranking (?days=7) |
| GET | /admin/analytics/dau-trend | DAU-Trend (?days=30) |
| GET | /admin/analytics/hourly-trend | Stunden-Trend |
| GET | /admin/analytics/action-distribution | Verhaltensverteilung |
| GET | /admin/analytics/revenue | Umsatzanalyse |
| GET | /admin/analytics/conversion | Spiel-Konversionsrate |
| GET | /admin/analytics/probability | Gemeinsame/bedingte Wahrscheinlichkeit |
| GET | /admin/analytics/retention | Retentionsanalyse D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Konversions-Funnel |
| GET | /admin/analytics/arpu | ARPU/ARPPU-Trend |
| GET | /admin/analytics/economy | Wirtschaftsindikatoren der Spielwährungen |

## 15. Ticketverwaltung (Ticket)

Alle Endpunkte benötigen Authentifizierung (`AdminAuth` + `AdminPermission`), insgesamt 5:

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/ticket/list | Ticketliste (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | Ticketdetails (inkl. Antworten) |
| POST | /admin/ticket/{hashid}/reply | Ticket beantworten |
| POST | /admin/ticket/{hashid}/close | Ticket schließen |
| POST | /admin/ticket/{hashid}/assign | Bearbeiter zuweisen (admin_id) |

## 16. Authentifizierungsablauf

Vollständige Authentifizierungs-Sequenz:

```
1. Client fordert POST /api/captcha/generate an
   (Request-Header: API-Version: v1)
    ↓
   Server liefert: key + base64-Bild + Hinweis auf Klickziele
   
2. Benutzer klickt auf die Zielpositionen im Bild, Frontend/Client sammelt Klickkoordinaten
   
3. Client fordert POST /api/auth/login an
   (Request-Header: API-Version: v1, Content-Type: application/json)
   Requestbody: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   Server:
   a. Parametervalidierung → 422
   b. CAPTCHA-Prüfung → 422
   c. Prüfung der Benutzerzugangsdaten → 401
   d. Prüfung des Kontostatus → 403
   e. JWT ausstellen (access + refresh) → 200
   f. last_login_at / last_login_ip aktualisieren
    ↓
   Client speichert: access_token, refresh_token, expires_in

4. Folgeanfragen führen JWT mit
   Request-Header: Authorization: Bearer <access_token>
    ↓
   AdminAuth-Middleware:
   a. Bearer-Token extrahieren
   b. Blacklist prüfen (Redis jwt_blacklist:{md5}) → 401
   c. JWT dekodieren, Ablauf prüfen → 401
   d. $request->adminId = sub-Feld setzen
    ↓
   AdminPermission-Middleware:
   a. Nicht angemeldet (adminId leer) → 401
   b. Berechtigungskennung für Ressourcenroute auflösen
   c. Benutzerrollen abfragen → Rollenberechtigungen, abgleichen
   d. Keine Berechtigung → 403
    ↓
   Controller verarbeitet die Anfrage
    ↓
   Response + X-RateLimit-*-Header

5. Vor Ablauf des Access Tokens aktualisieren
   Client fordert POST /api/auth/refresh an
   Requestbody: { refresh_token: "..." }
    ↓
   Server dekodiert refresh_token → stellt neue access + refresh aus
    ↓
   Client aktualisiert lokale Tokens

6. Logout
   Client fordert POST /admin/profile/logout an
   Request-Header: Authorization: Bearer <access_token>
    ↓
   Server:
   a. JWT dekodieren, restliche TTL ermitteln
   b. In Redis-Blacklist schreiben: jwt_blacklist:{md5(token)} = 1, TTL = restliche Gültigkeitsdauer
   c. Erfolg zurückgeben
```

### JWT-Struktur

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, Standard-TTL 7200 Sekunden (gesteuert über die JWT-Konfiguration `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, Standard-TTL 1209600 Sekunden (gesteuert über die JWT-Konfiguration `refresh_expire`, also 14 Tage)

### Sicherheitsverwaltung

- Passwörter werden als `PASSWORD_BCRYPT`-Hash gespeichert
- Sensible Felder (phone, email, id_card) werden über `erikwang2013/encryptable` auf Datenbankebene transparent ver-/entschlüsselt
- API-Ebene-IDs werden über `erikwang2013/hashids` verschlüsselt übertragen, um die ursprüngliche Snowflake-ID-Folge nicht offenzulegen
- SecurityFilter scannt global XSS, SQL-Injection, Pfad-Traversal und Befehlsinjektion; gleiche IP 5-mal/60 Sekunden → temporäre Blacklist für 15 Minuten
- Sensible Aktionen (Löschen von Benutzern, Rollen, Berechtigungen, Konfigurationen) erfordern die Passwort-Bestätigung des aktuell angemeldeten Benutzers
- Begrenzung paralleler Sitzungen: maximal 3 gültige Tokens pro Benutzer; beim Login vom 4. Gerät wird das älteste Token erzwungen in die Blacklist aufgenommen
- Kontosperre: 5 fehlgeschlagene Logins in Folge lösen eine 15-minütige Kontosperre aus; während der Sperre wird 429 zurückgegeben

## 17. Bereitstellung und Betrieb

### Docker Compose

Im Projektstamm liegt `docker-compose.yml`, das 5 Dienste orchestriert (Nginx, webman app, MySQL, Redis, Elasticsearch). PHP wird über die `Dockerfile` gebaut (basiert auf `php:8.3-cli`, mit OPcache).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` definiert die GitHub-Actions-CI-Pipeline:
- `php -l`-Syntaxprüfung
- PHPUnit-Unit-Tests
- `flutter analyze`-statische Analyse

### Datenbanksicherung

Das Verzeichnis `database/backup/` stellt Backup- und Wiederherstellungsskripte bereit:
- `backup.sh` — mysqldump + gzip-komprimierte Sicherung, automatische Bereinigung von Sicherungsdateien älter als 30 Tage
- `restore.sh` — interaktive Wiederherstellung, listet vorhandene Sicherungen zur Auswahl

### Nginx-Sicherheitskonfiguration

Für die Produktionsumgebung `docs/nginx-security.conf` als Referenz zur Härtung des Reverse-Proxys verwenden.

## 18. Datenanalyse (Analytics)

Die Datenanalyse-Schnittstellen werden vom `AnalyticsController` bereitgestellt und basieren alle auf Echtzeit-Aggregation in MySQL (`game_game_play_log`-Spielverhaltensprotokolle / `game_deposit_order`-Einzahlungsaufträge); bei Datenbankfehlern werden leere Daten statt 500 zurückgegeben. Sofern nicht anders angegeben, ist JWT- + RBAC-Authentifizierung erforderlich; das Antwortformat ist einheitlich `{ "code": 0, "message": "success", "data": ... }`.

### 18.1 Plattform-Übersicht

```
GET /admin/analytics/overview
```

**Antwort**: `today` / `week` enthalten jeweils `dau` (aktive Benutzer), `revenue` (bestätigter Einzahlungsgesamtbetrag, Zeichenfolge), `new_users` (neue Benutzer).

### 18.2 Spiel-Ranking

```
GET /admin/analytics/game-ranking?days=7
```

**Antwort**: die Top 10 absteigend nach Anzahl der Spielaktionen, jeder Eintrag mit `game_id` (hashid), `name`, `plays`, `players`.

### 18.3 DAU-Trend

```
GET /admin/analytics/dau-trend?days=30
```

**Antwort**: `{ "日期": 活跃数, ... }`, fehlende Daten werden mit 0 ergänzt.

### 18.4 Stunden-Trend

```
GET /admin/analytics/hourly-trend?game_id=<hashid>
```

**Antwort**: `{ "0": 次数, ... "23": 次数 }` mit 24 Stunden-Slots; bei leerem `game_id` werden alle Spiele gezählt.

### 18.5 Verhaltensverteilung

```
GET /admin/analytics/action-distribution?game_id=<hashid>&hours=24
```

**Antwort**: `{ "start": n, "end": n, "earn": n, "spend": n }` mit vier Verhaltenstypen; `hours` maximal 168.

### 18.6 Umsatzübersicht

```
GET /admin/analytics/revenue?days=7
```

**Antwort**: `{ "total": "总额", "trend": { "日期": "当日额", ... } }`, zählt nur Aufträge mit `status=confirmed`.

### 18.7 Spiel-Konversionsrate

```
GET /admin/analytics/conversion?days=30
```

**Antwort**: jedes Spiel mit `game_id` (hashid), `game_name`, `players` (deduplizierte Spielerzahl), `depositors` (deduplizierte Einzahlerzahl), `conversion_rate` (Einzahlungs-Konversionsrate, 0~1).

### 18.8 Gemeinsame Wahrscheinlichkeit

```
GET /admin/analytics/probability?game_a=<hashid>&game_b=<hashid>
```

**Antwort**: `{ "joint": { "joint_probability": 0.12, "confidence": 0.3 } }` — Jaccard-Koeffizient (gemeinsame Spieler beider Spiele / Vereinigungsmenge der Spieler) und Konfidenz (gemeinsame Spieler / Spieler von Spiel A).

### 18.9 Retentionsanalyse

```
GET /admin/analytics/retention?days=30
```

**Antwort**: `{ "D1": "8.5%", "D3": "...", "D7": "...", "D30": "..." }` mit 1-/3-/7-/30-Tages-Retentionsraten gruppiert nach Registrierungsdatum.

### 18.10 Konversions-Funnel

```
GET /admin/analytics/funnel?days=30
```

**Antwort**: Registrierung → erste Einzahlung → erster Umtausch → erstes Spiel, vier Schritte mit `step`, `count`, `rate` (Prozentsatz relativ zur Registrierungszahl).

### 18.11 ARPU/ARPPU-Trend

```
GET /admin/analytics/arpu?days=30
```

**Antwort**: `{ "dates": [...], "arpu": [...], "arppu": [...] }` mit täglichem Umsatz pro Benutzer (ARPU) und Umsatz pro zahlendem Benutzer (ARPPU).

### 18.12 Wirtschaftsindikatoren der Spielwährungen

```
GET /admin/analytics/economy
```

**Antwort**: Array `currencies`, jeder Eintrag mit `game_name`, `currency`, `symbol`, `total_minted` (Gesamtmenge geprägt), `total_burned` (Gesamtmenge vernichtet), `circulation` (Umlaufmenge), `inflation_rate` (Inflationsrate), berechnet mit bcmath-Hochpräzisionsarithmetik.

## 17. Zahlungsverwaltung (Payment)

Die Zahlungsmethoden-Verwaltung wird von `PaymentController` bereitgestellt; alle 5 Endpunkte erfordern JWT + RBAC-Authentifizierung. `provider`-Whitelist: `stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash`. `config` ist ein JSON-String der Zahlungskonfiguration (verschlüsselt in der Datenbank gespeichert).

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/payment/method/list | Liste der Zahlungsmethoden (aufsteigend nach sort) |
| POST | /admin/payment/method/toggle | Zahlungsmethode aktivieren/deaktivieren |
| POST | /admin/payment/method/create | Zahlungsmethode erstellen |
| PUT | /admin/payment/method/{hashid} | Zahlungsmethode aktualisieren |
| DELETE | /admin/payment/method/{hashid} | Zahlungsmethode löschen (abgelehnt, wenn ausstehende Bestellungen existieren) |

### 17.1 Liste der Zahlungsmethoden

```
GET /admin/payment/method/list
```

- **Authentifizierung**: JWT + RBAC

**Antwortbeispiel**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "name": "Stripe Kreditkarte",
        "type": "fiat",
        "provider": "stripe",
        "status": 1,
        "sort": 1,
        "countries": ["US", "SG"],
        "currency": "USD",
        "min_amount": "10",
        "max_amount": "5000",
        "config": null,
        "created_at": "2026-08-29 10:00:00",
        "updated_at": "2026-08-29 10:00:00"
      }
    ]
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | Zahlungsmethoden-ID (hashid-kodiert) |
| name | string | Name der Zahlungsmethode |
| type | string | `fiat` (Fiat-Währung) / `crypto` (Kryptowährung) |
| provider | string | Gateway-Anbieter: `stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash` |
| status | int | 1=aktiviert, 0=deaktiviert |
| sort | int | Sortierwert (aufsteigend) |
| countries | array{string} | Sichtbare Länder-Codes (leeres Array = global sichtbar) |
| currency | string | Währung (z. B. USD/USDT), leer = keine Einschränkung |
| min_amount / max_amount | string | Betragsbereich (String erhält Präzision), 0 = keine Begrenzung |
| config | string? | Zahlungskonfiguration JSON (verschlüsselt; null, wenn nicht gesetzt) |

### 17.2 Zahlungsmethode aktivieren/deaktivieren

```
POST /admin/payment/method/toggle
```

**Anfragekörper**:
```json
{
  "id": "a1b2c3d4",
  "status": 1
}
```

| Feld | Typ | Erforderlich | Beschreibung |
|------|------|------|------|
| id | string | Ja | Zahlungsmethoden-ID (hashid-kodiert) |
| status | int | Ja | 0=deaktiviert, 1=aktiviert |

**Mögliche Fehler**:
- 422: Validierung fehlgeschlagen (id/status fehlt oder status nicht 0/1)
- 404: Zahlungsmethode nicht gefunden

### 17.3 Zahlungsmethode erstellen

```
POST /admin/payment/method/create
```

**Anfragekörper**:
```json
{
  "name": "USDT Kryptowährung",
  "type": "crypto",
  "provider": "nowpayments",
  "status": 1,
  "sort": 2,
  "countries": [],
  "currency": "USDT",
  "min_amount": "10",
  "max_amount": "10000",
  "config": "{\"api_key\":\"...\"}"
}
```

| Feld | Typ | Erforderlich | Validierung | Beschreibung |
|------|------|------|---------|------|
| name | string | Ja | max:50 | Name der Zahlungsmethode |
| type | string | Ja | in:fiat,crypto | Typ: Fiat/Krypto |
| provider | string | Ja | in:stripe,paypal,nowpayments,coinbase,skrill,neteller,paysafecard,paytm,mercadopago,astropay,paypay,kakaopay,gcash | Gateway-Anbieter-Whitelist |
| status | int | Ja | in:0,1 | Status |
| sort | int | Nein | integer,min:0 | Sortierwert, Standard 0 |
| countries | array{string} | Nein | max:2 | Sichtbare Länder-Codes, leer = global |
| currency | string | Nein | max:10 | Währung, Standard leer |
| min_amount / max_amount | string | Nein | numeric,min:0 | Betragsbereich, Standard "0" |
| config | string | Nein | | Zahlungskonfiguration JSON (verschlüsselt), leerer String wird als NULL gespeichert |

**Antwortbeispiel**:
```json
{
  "code": 0,
  "message": "erfolgreich erstellt",
  "data": { "id": "e5f6g7h8" }
}
```

**Mögliche Fehler**:
- 422: Validierung fehlgeschlagen

### 17.4 Zahlungsmethode aktualisieren

```
PUT /admin/payment/method/{hashid}
```

- **Pfadparameter**: `{hashid}` ist die hashid-kodierte Zahlungsmethoden-ID
- **Anfragekörper**: wie bei Erstellen (17.3), alle Felder optional, nur übergebene Felder werden aktualisiert

**Mögliche Fehler**:
- 404: Zahlungsmethode nicht gefunden
- 422: Validierung fehlgeschlagen

### 17.5 Zahlungsmethode löschen

```
DELETE /admin/payment/method/{hashid}
```

- **Pfadparameter**: `{hashid}` ist die hashid-kodierte Zahlungsmethoden-ID

**Mögliche Fehler**:
- 404: Zahlungsmethode nicht gefunden
- 422: ausstehende Einzahlungsaufträge (status=pending) vorhanden, Löschen nicht möglich
