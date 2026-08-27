# Teilprojekt A: Backend-Erweiterung — Designnorm
<!-- lang-nav -->

Languages: **中文** · [English](2026-05-20-backend-enhancement-design.en.md) · [한국어](2026-05-20-backend-enhancement-design.ko.md) · [Русский](2026-05-20-backend-enhancement-design.ru.md) · [Deutsch](2026-05-20-backend-enhancement-design.de.md) · [Français](2026-05-20-backend-enhancement-design.fr.md) · [Español](2026-05-20-backend-enhancement-design.es.md) · [Português](2026-05-20-backend-enhancement-design.pt.md) · [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) · [العربية](2026-05-20-backend-enhancement-design.ar.md) · [বাংলা](2026-05-20-backend-enhancement-design.bn.md) · [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) · [日本語](2026-05-20-backend-enhancement-design.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Umfang

Dies ist eine Backend-Erweiterung mit insgesamt 15 Funktionspunkten, die 9 neue Dateien + 4 geänderte Dateien umfasst.

---

## Liste der neuen/geänderten Dateien

```
app/middleware/
├── OperationLog.php          # Neu: automatische Aufzeichnung von Betriebsprotokollen
├── Cors.php                  # Neu: Cross-Origin (CORS)
└── RateLimit.php             # Neu: Redis-Ratenbegrenzung
app/admin/controller/
├── ConfigController.php      # Neu: Systemkonfiguration CRUD
├── LogController.php         # Neu: Abfrage der Betriebsprotokolle
├── ProfileController.php     # Neu: Persönlicher Bereich (inkl. Abmeldung)
├── UploadController.php      # Neu: Datei-Upload
├── ImportController.php      # Neu: Excel-Import von Benutzern
└── HealthController.php      # Neu: Gesundheitscheck
app/model/
├── AdminUser.php             # Geändert: SoftDeletes + Searchable-Trait hinzugefügt
└── OperationLog.php          # Geändert: public $timestamps = false hinzugefügt
app/middleware/
└── AdminAuth.php             # Geändert: JWT-Blacklist-Prüfung
app/admin/controller/
├── DashboardController.php   # Geändert: Echtzeitstatistiken aus der Datenbank
└── UserController.php        # Geändert: Batch-Aktionen hinzugefügt
config/
└── route.php                 # Geändert: neue Routen + Middleware
```

---

## 1. Middleware

### 1.1 CORS-Middleware

**Datei**: `app/middleware/Cors.php`

- OPTIONS-Preflight-Anfragen geben direkt 204 zurück
- Bei Nicht-Preflight-Anfragen wird `Access-Control-Allow-Origin: *` an die Antwort-Header angehängt
- Erlaubte Header: `Authorization, Content-Type, API-Version`
- Maximale Cache-Dauer: 86400 Sekunden

Einbindung: globale Middleware (`config/middleware.php`)

### 1.2 Ratenbegrenzungs-Middleware

**Datei**: `app/middleware/RateLimit.php`

- Speicher: Redis Sorted Set gleitendes Fenster
- Standard: 60 Anfragen/Minute/IP/Route
- Sensible Schnittstellen:
  - `/api/auth/login`: 10 Anfragen/Minute
  - `/api/auth/register`: 5 Anfragen/Minute
- Bei Überschreitung: `429 Too Many Requests`

Einbindung: globale Middleware (`config/middleware.php`), nach Cors, vor ApiVersion

### 1.3 Betriebsprotokoll-Middleware

**Datei**: `app/middleware/OperationLog.php`

- Erfasst nur POST/PUT/DELETE
- Erfasste Felder: user_id, action, method, path, ip, input(JSON)
- Asynchrones Schreiben nach der Antwort (blockiert nicht)

Einbindung: `/admin`-Routengruppe, nach AdminPermission

### 1.4 Globale Middleware-Ausführungskette

```
Alle Anfragen:
  Cors → RateLimit → ApiVersion → {Route-Middleware} → Controller

/admin/*-Anfragen:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Abmeldung (JWT-Blacklist)

**Datei**: `app/middleware/AdminAuth.php` (geändert)

**Prinzip**: JWT ist von Natur aus zustandslos; bei der Abmeldung wird das Token zur Redis-Blacklist hinzugefügt, und AdminAuth prüft bei der Validierung zuerst die Blacklist.

**AdminAuth-Umbau**:
- Am Anfang von `process()` neu: Prüfen, ob das aktuelle Token in der `jwt_blacklist`-Menge in Redis steht
- Bei Blacklist-Treffer: 401 zurückgeben

**Abmelde-Route** (unter dem persönlichen Bereich):

| Methode | Route | Beschreibung |
|------|------|------|
| `POST` | `/admin/profile/logout` | Fügt das aktuelle Bearer-Token zur Redis-Blacklist hinzu, TTL = verbleibende Token-Gültigkeit |

**Logout-Logik**:
```php
// verbleibende Token-Gültigkeit auswerten
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// zur Blacklist hinzufügen
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Neue Controller und bestehende Umbauten

### 2.1 Systemkonfiguration CRUD (`ConfigController`)

Erbt von `BaseController`.

| Methode | Route | Beschreibung |
|------|------|------|
| `index()` | GET `/admin/config` | Paginierte Liste, filterbar nach `group`, Pagination mit `page`/`limit` |
| `store()` | POST `/admin/config` | Konfigurationseintrag anlegen, Pflichtfelder: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Konfigurationseintrag aktualisieren (value/type/description) |
| `destroy()` | DELETE `/admin/config/{id}` | Konfigurationseintrag löschen, erfordert `confirmPassword()` |

### 2.2 Abfrage der Betriebsprotokolle (`LogController`)

Erbt von `BaseController`.

| Methode | Route | Beschreibung |
|------|------|------|
| `index()` | GET `/admin/log` | Paginierte Liste, Filter: user_id, action, path, created_at (Zeitraum) |

Keine Erstellungs-/Änderungs-/Löschoperationen; die Protokolle werden automatisch von der Middleware erfasst.

### 2.3 Persönlicher Bereich (`ProfileController`)

Erbt von `BaseController`. Arbeitet mit dem aktuell angemeldeten Benutzer (`$request->adminId`).

| Methode | Route | Beschreibung |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Aktualisiert real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Passwort ändern, erfordert old_password, new_password, new_password_confirmation |

### 2.4 Datei-Upload (`UploadController`)

Erbt von `BaseController`.

| Methode | Route | Beschreibung |
|------|------|------|
| `upload()` | POST `/admin/upload` | Empfängt Dateien, unterstützt image/jpeg/png/gif/pdf/xlsx/docx |

- Maximal 10 MB
- Speicherpfad: `public/upload/{date}/{hash}.{ext}`
- Rückgabe: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Dashboard mit echten Daten

**Datei**: `app/admin/controller/DashboardController.php` (geändert)

Die aktuell hartkodierten Fake-Daten durch Echtzeitstatistiken aus der Datenbank ersetzen:

| Kennzahl | Quelle | Beschreibung |
|------|------|------|
| Benutzergesamtzahl | `AdminUser::count()` | ohne Soft-Deletes |
| Heute neu | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Rollen gesamt | `AdminRole::count()` | |
| Berechtigungen gesamt | `AdminPermission::count()` | |
| Trenddaten | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Neu hinzugefügt pro Tag, letzte 7 Tage |
| Verteilungsdaten | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Verteilung nach Status |
| Letzte Aktionen | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | Die letzten 10 Betriebsprotokolle |

### 2.6 Benutzer-Batch-Operationen

**Datei**: `app/admin/controller/UserController.php` (geändert, neue Methoden)

| Methode | Route | Beschreibung |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Batch-Löschen, Anfragebody `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Batch-Aktivieren/Deaktivieren, Anfragebody `{ ids: [hashid, ...], status: 1|0 }` |

- Jede id wird zuerst mit `decodeId()` in BIGINT umgewandelt
- `batchDestroy()` muss mit `confirmPassword()` validiert werden

### 2.7 Datenimport

**Datei**: `app/admin/controller/ImportController.php` (neu)

| Methode | Route | Beschreibung |
|------|------|------|
| `users()` | POST `/admin/import/users` | Excel-Datei hochladen, Benutzer in Masse anlegen |

Ablauf:
1. `.xlsx`-Datei empfangen
2. Mit PhpSpreadsheet parsen, erwartete Spalten: `username, password, real_name, phone, email, status`
3. Zeilenweise validieren + anlegen (snowflake generiert die ID, bcrypt für Passwörter, encryption verschlüsselt phone/email)
4. Ergebnis zurückgeben: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Gesundheitscheck

**Datei**: `app/admin/controller/HealthController.php` (neu)

`GET /health` (keine Authentifizierung erforderlich, wird nicht im Betriebsprotokoll erfasst):

Gibt den Verbindungsstatus der einzelnen Komponenten zurück:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- Bei Fehlschlag der Komponentenprüfung ist der Feldwert die Fehlerbeschreibung als String
- Die Route trägt kein `/admin`-Präfix, sie wird separat global registriert

---

## 3. Modellkorrekturen

### 3.1 OperationLog-Zeitstempel

**Datei**: `app/model/OperationLog.php` (geändert)

Die Tabelle `game_operation_log` hat nur eine `created_at`-Spalte (kein `updated_at`). Eloquents Standard-`save()` versucht, `updated_at` zu schreiben, was einen SQL-Fehler verursacht.

Fix: `public $timestamps = false;` + beim Schreiben `created_at` manuell angeben.

### 3.2 Umbau des AdminUser-Modells

- `Searchable`-Trait hinzufügen
- `toSearchableArray()` implementieren: gibt username, real_name zurück
- `UserController::index()` verwendet bei erkanntem Suchbegriff `AdminUser::search($kw)->get()` statt MySQL LIKE

Für ES muss zuerst der Index erstellt werden, möglich per Scout-Befehlen:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Routenänderungen

Neue Routen in `config/route.php`:

```php
// In der /admin-Routengruppe neu hinzugefügt:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// Gesundheitscheck (globale Route, nicht in der /admin-Gruppe)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// Middleware:
Der /admin-Gruppe app\middleware\OperationLog::class hinzufügen
```

Globale Middleware in `config/middleware.php` registrieren:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Ergänzung der Fehlercodes

| code | Bedeutung | Auslöseszenario |
|------|------|---------|
| 429 | Zu viele Anfragen | RateLimit ausgelöst |

---

## 6. Nicht im Umfang dieses Projekts enthalten

- Benachrichtigungssystem (erfordert Nachrichtenwarteschlange + Frontend-Push-Infrastruktur)
- Flutter-Frontend-Seiten (Teilprojekt B)
- HarmonyOS-Token-Aktualisierung (Teilprojekt C)
