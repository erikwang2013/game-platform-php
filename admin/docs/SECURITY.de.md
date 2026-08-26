# Sicherheitsarchitektur-Design-Dokument
<!-- lang-nav -->

Languages: [中文](SECURITY.md) · [English](SECURITY.en.md) · [한국어](SECURITY.ko.md) · [Русский](SECURITY.ru.md) · **Deutsch** · [Français](SECURITY.fr.md) · [Español](SECURITY.es.md) · [Português](SECURITY.pt.md) · [हिन्दी](SECURITY.hi.md) · [العربية](SECURITY.ar.md) · [বাংলা](SECURITY.bn.md) · [Bahasa Indonesia](SECURITY.id.md) · [日本語](SECURITY.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Übersicht Verteidigung in der Tiefe

Das System verwendet ein 7-stufiges Verteidigungsmodell in der Tiefe, das bösartige Anfragen von außen nach innen auf jeder Ebene herausfiltert und sicherstellt, dass bei Ausfall einer beliebigen einzelnen Ebene weiterhin nachgelagerte Verteidigungslinien greifen.

Die gesamte Middleware-Kette wird in folgender Reihenfolge ausgeführt (siehe `config/middleware.php`):

```
请求 → Cors → SecurityFilter → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Ebene | Middleware/Mechanismus | Schutzziel |
|----|--------|---------|
| 1 | SecurityFilter | Abwehr von XSS / SQL-Injection / Pfad-Traversal / Befehlsinjektion / CSRF-Angriffen |
| 2 | Cors | Cross-Origin-Sicherheit + Injektion von Sicherheits-Response-Headern |
| 3 | RateLimit | Redis-Sliding-Window-Rate-Limiting, gegen Brute-Force |
| 4 | AdminAuth | JWT-Authentifizierung + Blacklist-Logout |
| 5 | AdminPermission | RBAC method.path-granulare Autorisierung |
| 6 | OperationLog | Operationsprüfung + Quellen-Tracking |
| 7 | Datenverschlüsselung | Hashids-ID-Verschleierung + Encryptable-DB-Verschlüsselung + EncryptionService-Transportverschlüsselung |

Das Frontend (Flutter) hat auf drei Ebenen unabhängige Eingabevalidierungen; das Backend vertraut ihnen nicht, jede Ebene verteidigt unabhängig.

---

## 2. Angriffserkennungs-Engine

### 2.0 HTTP-Methodenbegrenzung

Der SecurityFilter prüft vor allen Angriffserkennungen zuerst die HTTP-Methode und erlaubt nur die folgenden Standardmethoden:

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

Nicht-standardmäßige Methoden (z. B. TRACE, CONNECT, PATCH, eigene Methoden usw.) liefern direkt **405 Method Not Allowed** mit leerem HTML-Responsebody und erreichen weder die Angriffserkennung noch die Geschäftslogik.

Dies ist die erste Verteidigungslinie in der Tiefe und blockiert wirksam:
- TRACE-Cross-Site-Tracing-Angriffe (XST)
- Missbrauch von CONNECT-Tunnel-Proxys
- Sondierungen nicht-standardmäßiger WebDAV-Methoden
- HTTP-Methoden-Enumeration durch automatisierte Scanner

### 2.1 XSS (Cross-Site Scripting)

Alle regulären Ausdrücke stammen aus `SecurityFilter::PATTERNS['XSS']`, Groß-/Kleinschreibung wird nicht beachtet.

| Erkennungsmuster | Regulärer Ausdruck | Abgewehrte Angriffe |
|----------|------|-----------|
| Skript-Tags | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` usw. mit Leerzeichenvarianten |
| Event-Attribute | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | `onclick="javascript:..."` und andere Inline-Events |
| JS-Pseudo-Protokoll | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` usw. |
| Data-URI-XSS | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` usw. |
| Template-Injection | `\{\{.*?\}\}` | `{{constructor}}`, `{{7*7}}` und andere Server-/Angular-/Vue-Template-Injection |

### 2.2 SQL-Injection

| Erkennungsmuster | Regulärer Ausdruck | Abgewehrte Angriffe |
|----------|------|-----------|
| UNION-Abfragen | `\bUNION\s+(?:ALL\s+)?SELECT\b` | `UNION SELECT`, `UNION ALL SELECT`-Datenbank-Dumps |
| OR-Immer-Wahr-Injection | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| Tabellenstruktur-Zerstörung | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| Stored-Procedure-Aufrufe | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | Befehlausführung über MSSQL-erweiterte Stored Procedures |
| Metadaten-Sondierung | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | MySQL/PG/SQLite/MSSQL-Datenbankstruktur-Sondierung |
| Kommentar-Bypass | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | `'-- OR SELECT`, `'# AND UPDATE`-Kommentar-Bypass |

### 2.3 Pfad-Traversal

| Erkennungsmuster | Regulärer Ausdruck | Abgewehrte Angriffe |
|----------|------|-----------|
| Verzeichnis-Traversal | `\.\.[\/\\\\]{2,}` | `../`, `..\`, `....//` mehrstufiges Verzeichnis-Traversal |
| Sondierung sensibler Dateien | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` usw. |
| Null-Byte-Trunkierung | `%00` | `../../../etc/passwd%00.jpg` umgeht die Endungsprüfung |

### 2.4 Befehlsinjektion

| Erkennungsmuster | Regulärer Ausdruck | Abgewehrte Angriffe |
|----------|------|-----------|
| Pipe/Semikolon-Befehle | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| Backtick-Substitution | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| $()-Substitution | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| Remote-Download-Pipes | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF (Cross-Site Request Forgery)

Die Prüflogik ist in `SecurityFilter::checkCsrf()` implementiert:

```php
// 仅 POST/PUT/DELETE 触发校验
// Origin 头和 Referer 均为空 → 放行（非浏览器客户端）
// Origin 非空 → 解析 Origin 域名与 Host 比对
```

Abgleichregeln:
- Nach Entfernen des `www.`-Präfixes von Host exakter Vergleich mit der Origin-Domain
- Ist Host eine übergeordnete Domain von Origin (z. B. `Origin: app.example.com`, `Host: example.com` — löst `str_contains($originHost, '.' . $hostOnly)` aus), wird durchgelassen
- Weder exakte Übereinstimmung noch Subdomain → 403, als CSRF-Angriff gewertet

Hinweis: Nicht-Browser-Clients (z. B. curl ohne Origin/Referer) werden direkt durchgelassen; der CSRF-Schutz wirkt nur in Browser-Umgebungen.

### 2.6 Bösartige Datei-Uploads

| Erkennungsmuster | Regulärer Ausdruck | Abgewehrte Angriffe |
|----------|------|-----------|
| Doppelte-Endungs-Tarnung | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` umgehen die Whitelist |
| PHP-Endungen | `\.php\s*$/m` | direkte Übergabe von `.php`-Pfaden in Anfrageparametern |

---

## 3. Angriffs-Eskalation und IP-Blacklist

Der SecurityFilter enthält einen Angriffs-Eskalationsmechanismus, um fortlaufende Scan-Angriffe derselben IP zu verhindern.

### Eskalationsablauf

```
1. Scan-Treffer → Redis INCR security_escalate:{ip} = 1, TTL=60s
2. Scan-Treffer → INCR → 2
...
5. Scan-Treffer → INCR → 5
    → Sperre auslösen: SETEX security_ban:{ip} 900 1
    → Zähler löschen DEL security_escalate:{ip}
    → Sicherheitsprotokoll schreiben: [SECURITY] IP banned 15min
```

### Verhalten während der Sperre

Jede Anfrage prüft beim Eintritt in den SecurityFilter zuerst `isBanned()`:

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

Eine gesperrte IP erhält 15 Minuten lang für alle Anfragen (auch legitime) direkt 403 und überspringt die gesamte nachgelagerte Geschäftslogik.

### Konfigurationskonstanten

| Konstante | Wert | Bedeutung |
|------|-----|------|
| ESCALATE_LIMIT | 5 | Schwellwert für Treffer innerhalb des 60-Sekunden-Fensters |
| ESCALATE_WINDOW | 60 | Zählerfenster (Sekunden) |
| BAN_DURATION | 900 | Dauer der Blacklist (Sekunden), also 15 Minuten |

### Sicherheitsprotokoll

Dateipfad: `runtime/logs/security.log`

Beispiel des Protokollformats:
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### Begrenzung der Requestbody-Größe

`Content-Length > 10MB` liefert direkt 413 Payload Too Large, gegen DoS-Angriffe mit übergroßen Requestbodys.

### Content-Type-Prüfung

POST/PUT-Anfragen **müssen** `Content-Type` als `application/json` oder `application/x-www-form-urlencoded` deklarieren, sonst wird 415 Unsupported Media Type zurückgegeben. Datei-Upload-Anfragen (mit file-Feld) überspringen diese Prüfung.

---

## 4. Sicherheits-Response-Header

Alle Header werden in der `Cors`-Middleware injiziert und über `$response->withHeaders()` an jede Antwort angehängt.

| Header | Wert | Wirkung |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | beliebige Quellen Cross-Origin erlauben (Intranet-Admin-Szenario) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | erlaubter Methodensatz |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | erlaubte benutzerdefinierte Header |
| Access-Control-Max-Age | `86400` | Preflight-Cache 24 Stunden |
| X-Content-Type-Options | `nosniff` | verhindert Browser-MIME-Sniffing |
| X-Frame-Options | `DENY` | verhindert jede iframe-Einbettung, gegen Clickjacking |
| X-XSS-Protection | `1; mode=block` | aktiviert den integrierten Browser-XSS-Filter und blockiert das Seiten-Rendering |
| Referrer-Policy | `strict-origin-when-cross-origin` | gleichherkunftige volle URL, Cross-Origin nur Domain |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | deaktiviert Kamera-/Mikrofon-/Standort-APIs für die gesamte Site |

OPTIONS-Preflight-Anfragen liefern direkt eine leere 204-Antwort und durchlaufen die weitere Middleware-Kette nicht.

### 4.2 Content-Security-Policy (CSP)

Wird zusammen mit den anderen Sicherheits-Headern in der Cors-Middleware injiziert, bietet Verteidigung in der Tiefe und begrenzt die Ressourcenquellen, die der Browser laden und ausführen darf.

| Header | Wert | Wirkung |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | begrenzt Ressourcenquellen für Skripte/Stile/Bilder/Verbindungen/Frames/Formulare usw. |
| X-Permitted-Cross-Domain-Policies | `none` | verbietet das Laden von Cross-Domain-Policy-Dateien durch Adobe Flash/PDF usw. |

CSP-Policy-Kernpunkte:
- `default-src 'self'`: standardmäßig nur gleichherkunftige Ressourcen
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: erlaubt gleichherkunftige Skripte + Inline-Skripte (für Flutter Web erforderlich) + eval (für das Flutter-Web-Debugging erforderlich)
- `frame-ancestors 'none'`: verbietet iframe-Einbettung durch beliebige Seiten, doppelte Absicherung mit X-Frame-Options: DENY
- `base-uri 'self'`: begrenzt `<base>`-Tags auf gleichherkunftige Ziele
- `form-action 'self'`: begrenzt Formular-Submissions auf gleichherkunftige Ziele

---

## 5. Rate-Limiting-Strategie

### Algorithmus

Redis Sorted Set Sliding Window + atomares Lua-Skript, Schlüsseloperationen:

```lua
-- 1. 清理窗口外的旧记录
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. 检查当前窗口计数
local count = redis.call('ZCARD', KEYS[1])
-- 3. 超限则返回 {0, count}，未超限则 ZADD 并返回 {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- 随机后缀避免同毫秒覆盖
redis.call('EXPIRE', KEYS[1], window + 10)
```

Das Lua-Skript wird serverseitig in Redis single-threaded ausgeführt, **von Natur aus atomar**, und beseitigt TOCTOU-Race-Conditions (Time-of-check to Time-of-use).

### Rate-Limit-Konfiguration

| Route | Limit | Fenster | Szenario |
|------|------|------|------|
| Standard (alle Routen) | 60/Minute | 60s | Allgemeine API |
| `/api/auth/login` | 10/Minute | 60s | Login (gegen Brute-Force) |
| `/api/auth/register` | 5/Minute | 60s | Registrierung (gegen Massen-Registrierung) |

### Response-Header

Bei ausgelöstem Rate-Limit wird HTTP 429 mit JSON-Body zurückgegeben:
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

Alle Antworten (auch normale) tragen die folgenden Header:

| Header | Beschreibung |
|----|------|
| X-RateLimit-Limit | maximale Anzahl erlaubter Anfragen im aktuellen Fenster |
| X-RateLimit-Remaining | verbleibende Anzahl nutzbarer Anfragen im aktuellen Fenster |
| X-RateLimit-Reset | Unix-Zeitstempel des Fenster-Reset |
| Retry-After | nur bei Rate-Limit ausgelöst, empfohlene Wartezeit in Sekunden |

### Degradationsstrategie

Bei Redis-Störungen (Verbindungstimeout, nicht verfügbar usw.) gilt **fail-closed**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    // Redis down: 限流不可用即拒绝，避免安全防线失效（登录/支付回调限流为空转）
    return json(['code' => 503, 'message' => '服务暂不可用，请稍后再试', 'data' => []])
        ->withStatus(503)->withHeaders(['Retry-After' => '5']);
}
```

Das Rate-Limiting ist die erste Sicherheitslinie gegen Brute-Force beim Login und Replay bei Zahlungs-Callbacks; bei Redis-Ausfall werden Anfragen lieber abgelehnt (503), als durchgelassen.

### 5.4 Kontosperr-Mechanismus

Die Login-Schnittstelle hat zusätzlich zum Rate-Limit einen **Kontosperr**-Mechanismus gegen gezieltes Brute-Force auf bestimmte Benutzer.

**Sperrablauf**:

```
Login-Fehlschlag → Redis INCR account_lockout:{userId} TTL=900s
5 Fehlschläge in Folge → Redis SETEX account_locked:{userId} 900 1
            → 429 "账号已被锁定，请15分钟后再试"
            → Zähler löschen DEL account_lockout:{userId}
```

**Verhalten während der Sperre**:

Während der Sperre liefern alle Login-Anfragen direkt 429 zurück, ohne Passwortprüfung; Brute-Force-Versuche werden vollständig blockiert.

**Konfigurationskonstanten**:

| Konstante | Wert | Bedeutung |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | maximale Anzahl aufeinanderfolgender Fehlschläge |
| LOCKOUT_DURATION | 900 | Sperrdauer (Sekunden), also 15 Minuten |

Hinweis: Die Kontosperre basiert auf `userId`, nicht auf der IP; ein IP-Wechsel des Angreifers kann die Sperre also nicht umgehen. Zusammen mit dem IP-Rate-Limit (10/Minute) ergibt sich doppelter Schutz:
- IP-Ebene: 10/Minute-Rate-Limit blockiert verteiltes Brute-Force
- Kontoebene: Sperre nach 5 Fehlschlägen blockiert gezieltes Brute-Force

---

## 6. Authentifizierung und Autorisierung

### 6.1 JWT-Authentifizierung

Implementiert in der AdminAuth-Middleware, an den authentifizierungspflichtigen Routengruppen montiert.

**Parameterkonfiguration** (`config/plugin/erikwang2013/jwt/jwt`, per `.env` injiziert):

| Parameter | Wert | Beschreibung |
|------|-----|------|
| Algorithmus | HS256 | HMAC-SHA256-Symmetrie-Signatur |
| Schlüssel | `JWT_SECRET_KEY` | per Umgebungsvariable injiziert; fehlend oder noch Standardwert → **Startverweigerung** (fail-closed) |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Aussteller | `open-admin` | `JWT_ISSUER` |
| Zielgruppe | `open-admin` | `JWT_AUDIENCE` |

**Token-Extraktion**: aus dem Header `Authorization: Bearer <token>` extrahieren, das Präfix `Bearer ` entfernen, um das rohe JWT zu erhalten.

**Authentifizierungsablauf**:
1. leeres Token → direkt 401 `{"code": 401, "message": "未登录"}`
2. Redis-Blacklist `jwt_blacklist:{md5(token)}` prüfen → Treffer → 401 `Token已失效，请重新登录`
3. JWT-Decode → Fehler (abgelaufen/Signatur stimmt nicht) → 401 `Token已过期或无效`
4. Erfolg → `$request->adminId` und `$request->adminUsername` injizieren

**Blacklist-Mechanismus**: Beim Logout wird `md5(token)` in Redis geschrieben, TTL = verbleibende JWT-Gültigkeitsdauer. Bei Redis-Ausfall wird die Blacklist-Prüfung übersprungen (fail-open); ausgeloggte Tokens bleiben dann kurz nutzbar, aber die kurze JWT-Gültigkeit (2h) selbst dient als Auffangschutz.

**Token-Refresh**: `POST /api/auth/refresh` prüft das ursprüngliche Refresh-Token (`token_type=refresh` und nicht abgelaufen/nicht gesperrt), bevor neue Tokens ausgestellt werden, und prüft, dass `sub` eine gültige Benutzer-ID ist — **es werden keine Refresh-Tokens mit sub=null mehr ausgestellt**; bei Refresh-Fehler wird direkt 401 zurückgegeben.

### 6.2 Begrenzung paralleler Sitzungen

Um Missbrauch eines geleakten Tokens auf mehreren Geräten zu verhindern, begrenzt das System die Anzahl gleichzeitig gültiger Tokens pro Benutzer.

**Begrenzungslogik**:

```
Login erfolgreich → neues Token ausstellen
         → Anzahl gültiger Tokens des aktuellen Benutzers abfragen: Redis SCARD user_tokens:{userId}
         → wenn Anzahl >= 3 (MAX_CONCURRENT_SESSIONS):
            → aufsteigend nach Erstellungszeit sortieren, ältestes Token entfernen:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → neues Token zur Menge hinzufügen: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Konfigurationskonstanten**:

| Konstante | Wert | Bedeutung |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | maximale Anzahl paralleler Tokens pro Benutzer |

**Szenario „abgemeldet"**: Beim Login vom 4. Gerät wird das Token des 1. Geräts erzwungen in die Blacklist aufgenommen; nachfolgende Anfragen liefern 401 "Token已失效，请重新登录".

Beim Logout wird das aktuelle Token aus der Menge entfernt. Läuft ein Token natürlich ab, verfällt der Redis-Key automatisch und die Mengenmitglieder reduzieren sich entsprechend.

### 6.3 RBAC-Berechtigungsmodell

Implementiert in der AdminPermission-Middleware.

**Datenmodell**: dreistufige Verknüpfung User -> Role -> Permission

- `erik_admin_user` (Benutzertabelle)
- `erik_admin_user_role` (Benutzer-Rollen-Verknüpfungstabelle)
- `erik_admin_role` (Rollentabelle)
- `erik_admin_role_permission` (Rollen-Berechtigungs-Verknüpfungstabelle)
- `erik_admin_permission` (Berechtigungstabelle)

**Berechtigungstypen**:
| type | Bedeutung | Beispiel |
|------|------|------|
| 1 | Menü-Berechtigung | steuert Sichtbarkeit der linken Navigation |
| 2 | Button-Berechtigung | steuert Aktionsbuttons innerhalb der Seite (anlegen/bearbeiten/löschen) |
| 3 | API-Berechtigung | steuert Backend-Schnittstellenaufrufe |

Format der API-Berechtigungskennung: `{method}.{path}`

Zum Beispiel:
- `post.admin/user` — Benutzer anlegen
- `put.admin/user` — Benutzer bearbeiten
- `delete.admin/user` — Benutzer löschen
- `get.admin/user` — Benutzerliste anzeigen

**Autorisierungsablauf**:
1. `$request->adminId` leer (nicht angemeldet) → direkt 401 `{"code": 401, "message": "未登录"}`, keine Freigabe mehr
2. Benutzer abrufen → Rollen (deaktivierte Rollen mit `status=0` überspringen) → Berechtigungsliste
3. Super-Admin (`slug = '*'`) → direkte Freigabe
4. `strtolower(method) . '.' . trim(path, '/')` konstruieren → mit der Berechtigungsliste vergleichen
5. kein Match → 403 `{"code": 403, "message": "无权限访问"}`

**Sekundäre Bestätigung**: Die BaseController stellt die Methode `confirmPassword()` bereit; bei sensiblen Aktionen (Benutzer löschen, Datenexport usw.) wird auf Controller-Ebene zusätzlich die Eingabe des aktuellen Passworts verlangt, um unbefugte Aktionen nach einer Session-Hijacking zu verhindern.

### 6.4 Zahlungs-Callback-Signaturprüfung (fail-closed)

Der `POST /api/payment/callback` (Stripe/PayPal-Einzahlungs-Callback) verwendet für die Signaturprüfung **fail-closed**; jede fehlende Konfiguration oder Prüfanomalie lehnt den Callback ab:

| Szenario | Verhalten |
|------|------|
| Stripe ohne konfiguriertes `STRIPE_WEBHOOK_SECRET` | Ablehnung (403), keine un signierten Callbacks mehr akzeptiert |
| Stripe-Signatur fehlt / Prüfung fehlgeschlagen | Ablehnung (403) |
| Stripe-Zeitstempel `t=` fehlt oder Abweichung von der Serverzeit **> ±5 Minuten** | Ablehnung (403), gegen Replay |
| PayPal ohne konfiguriertes `PAYPAL_WEBHOOK_ID` | Ablehnung (403) |
| PayPal-Rückprüfung anomal / nicht SUCCESS | Ablehnung (403) |
| optionales `CALLBACK_TRUSTED_IPS` konfiguriert und Quell-IP nicht in der Whitelist | Ablehnung (403) |
| Callback-Provider stimmt nicht mit der Zahlungsmethode des Auftrags überein / Zahlungsmethode existiert nicht | Ablehnung (403) |

Die Callback-Buchung (Statusaktualisierung + Guthaben + Transaktionsprotokoll) erfolgt innerhalb derselben Datenbanktransaktion; bei einem Fehlschlag in einem beliebigen Schritt wird alles zurückgerollt, um Teilbuchungen zu verhindern.

---

## 7. Prüfprotokolle

### 7.1 Operationsprotokolle

Die OperationLog-Middleware zeichnet für POST-/PUT-/DELETE-Anfragen automatisch Operationsprotokolle auf. GET-Anfragen werden nicht protokolliert.

**Erfasste Felder**:

| Feld | Quelle | Beschreibung |
|------|------|------|
| id | SnowflakeService::generate() | global eindeutige ID |
| user_id | `$request->adminId` | ID des Ausführenden, bei nicht angemeldet 0 |
| action | `$request->method()` | entspricht method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Anfragepfad |
| ip | `$request->getRealIp()` | echte Client-IP |
| source | detectSource() | Quellplattform des Clients |
| input | Requestbody (maskiertes JSON) | übermittelte Aktionsdaten |
| created_at | `date('Y-m-d H:i:s')` | Zeitpunkt der Aktion |

**Filterung sensibler Felder**: Der Requestbody wird rekursiv durchlaufen; die Werte folgender Felder werden durch `***` ersetzt:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Quellen-Erkennung** (`detectSource()`): nach Priorität:

1. zuerst den benutzerdefinierten Header `X-Client-Platform` lesen (explizite Deklaration nativer Clients)
2. Degradierung zur Ableitung aus dem User-Agent-String (Erkennungsreihenfolge der Methode `detectSource()`):

| Plattform | UA-Schlüsselwörter |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Standard-Fallback |

**Fehlertoleranz**: Protokollschreibfehler blockieren keine Geschäftsanfragen (`catch (\Throwable)` schluckt still).

### 7.2 Sicherheitsprotokoll

**Dateipfad**: `runtime/logs/security.log`

**Erfasster Inhalt**:
- Angriffsabwehr-Protokolle: Angriffskategorie, IP, Pfad, Feld, Quelle, Payload-Ausschnitt (erste 200 Zeichen)
- IP-Sperr-Meldungen: gesperrte IP, Anzahl der Auslöser

Die Protokollberechtigungen sind `FILE_APPEND | LOCK_EX`, um konkurrierende Schreibzugriffe sicher zu machen.

---

## 8. Datenschutz

Das System verwendet eine dreistufige Datenschutzstrategie, die den drei Phasen des Datenflusses entspricht.

### 8.1 Transportebene — EncryptionService

`EncryptionService` verwendet das Paket `erikwang2013/encryption` zur Ver-/Entschlüsselung sensibler Felder in API-Anfragen/-Antworten.

**Technische Details**:
- Algorithmus: `aes-256-cbc-hmac` (mit HMAC-Signatur gegen Manipulation)
- Schlüssel: Umgebungsvariable `ENCRYPTION_KEY`, automatisch auf 32 Bytes ausgerichtet
- Verwendung: Transport von Feldern wie Telefonnummern und Ausweisnummern zwischen Client und API

**Maskierungs-Hilfsmethoden**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (Benutzername über 2 Zeichen) oder `a**@example.com`

### 8.2 Speicherebene — Encryptable Cast

Das Modell `AdminUser` verwendet den Eloquent-Cast `Erikwang2013\Encryptable\Encryptable` für folgende Felder:

- `email` → als Encryptable gecastet, automatische Ver-/Entschlüsselung
- `phone` → als Encryptable gecastet, automatische Ver-/Entschlüsselung
- `id_card` → als Encryptable gecastet, automatische Ver-/Entschlüsselung

Beim Schreiben in die Datenbank wird automatisch zu Geheimtext verschlüsselt, beim Lesen automatisch zu Klartext entschlüsselt. Die Speicherspalte ist vom Typ `VARCHAR(500)`, der Geheimtext wird als base64 gespeichert.

**Schlüsselsystem**: Unabhängig von der Transportverschlüsselung (`ENCRYPTION_KEY`) wird `ENCRYPTABLE_KEY` verwendet; der Leak eines Schlüssels setzt die andere Ebene nicht außer Gefecht.

Schlüsselrotation: Die Umgebungsvariable `ENCRYPTION_PREVIOUS_KEYS` unterstützt eine Liste historischer Schlüssel (kommagetrennt); beim Lesen alter Daten wird mit historischen Schlüsseln entschlüsselt, beim Zurückschreiben mit dem aktuellen Schlüssel neu verschlüsselt.

### 8.3 Anzeigeebene — ID-Verschleierung und Maskierung

**Hashids-ID-Verschleierung**: `HashidsService` verwendet das Paket `erikwang2013/hashids`.

- Nach außen gerichtete API-BIGINT-IDs werden als Hash-Zeichenfolgen kodiert (z. B. `xK3mN9qR2pL7wV8b`)
- Clients senden Hash-Zeichenfolgen in Anfragen, das Backend dekodiert automatisch zu den ursprünglichen IDs
- Salt-Wert aus der Umgebungsvariablen `HASHIDS_SALT`; bei unterschiedlichen Salts sind die Kodier-/Dekodierergebnisse völlig verschieden
- minimale Hash-Länge 16 Zeichen, Zeichensatz mit 62 alphanumerischen Zeichen
- BaseController bietet die Komfortmethoden `encodeId()`, `decodeId()`, `encodeIds()`

**Export-Maskierung**: Beim Excel/PDF-Export (ExportController) werden sensible Felder einheitlich maskiert:
- Telefonnummer: `138****1234`
- E-Mail: `a***@example.com`
- Ausweisnummer: vollständig abgedeckt als `********`

---

## 9. Schlüsselverwaltung

Alle Schlüssel werden über `.env`-Umgebungsvariablen injiziert; die Konfigurationsdateien lesen sie per `getenv()` und enthalten eingebaute Fallback-Standardwerte (nur für Entwicklungsumgebungen sicher).

| Umgebungsvariable | Zweck | Paket | Produktionsanforderung |
|----------|------|-----|---------|
| JWT_SECRET_KEY | JWT-Signaturschlüssel | erikwang2013/jwt-webman | 64+ Zeichen Zufallszeichenfolge; fehlend oder Standardwert → Startverweigerung |
| JWT_ALGORITHM | JWT-Signaturalgorithmus | wie oben | HS256 beibehalten |
| HASHIDS_SALT | Salt-Wert der ID-Kodierung | erikwang2013/hashids | Zufallszeichenfolge |
| SNOWFLAKE_DATACENTER_ID | Rechenzentrums-ID (0-31) | erikwang2013/snowflake-php | bei einem Rechenzentrum Standard beibehalten |
| ENCRYPTION_KEY | Verschlüsselungsschlüssel API-Transportebene | erikwang2013/encryption | 32-Byte-Zufallszeichenfolge |
| ENCRYPTABLE_KEY | Verschlüsselungsschlüssel DB-Speicherebene | erikwang2013/encryptable | 32-Byte-Zufallszeichenfolge, verschieden vom Transportschlüssel |

**Sicherheitsanforderungen**:
- Die `.env`-Datei ist in `.gitignore` aufgenommen; das Einchecken ins Repository ist strikt verboten
- `.env.example` ist eine öffentliche Vorlage und enthält keine echten Schlüssel
- In Produktion **müssen** alle Standard-Schlüssel durch Zufallszeichenfolgen ersetzt werden
- Empfohlen wird die Generierung mit `openssl rand -base64 32`

### Isolierung der Schlüsselspeicherung

| Ebene | Konfigurationsschlüssel | Schlüssel-Umgebungsvariable |
|----|--------|-------------|
| Transportverschlüsselung | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Speicherverschlüsselung | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| ID-Verschleierung | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| JWT-Signatur | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET_KEY` |

---

## 10. security.txt (RFC 9116)

Das System stellt unter `/.well-known/security.txt` einen Sicherheitskontakt-Endpunkt gemäß RFC 9116 bereit, damit Sicherheitsforscher bei entdeckten Schwachstellen schnell einen Meldekanal finden.

**Zugriffsmethode**:

```
GET /.well-known/security.txt
```

**Antwortinhalt**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Feldbeschreibungen**:

| Feld | Beschreibung |
|------|------|
| Contact | Kontaktmöglichkeit für Sicherheitsfehler-Meldungen |
| Expires | Ablaufzeit der Datei, regelmäßig zu aktualisieren |
| Preferred-Languages | bevorzugte Kommunikationssprachen |
| Canonical | kanonische URL dieser Datei |
| Policy | Link zur Sicherheitsrichtlinie/Offenlegungsrichtlinie |

Dieser Endpunkt unterliegt keiner Begrenzung durch Rate-Limiting, Authentifizierung usw.; jeder kann ihn direkt aufrufen.

---

## 11. Nginx-Sicherheitskonfiguration

Das Projekt stellt `docs/nginx-security.conf` als Referenzkonfiguration zur Sicherheitshärtung des Nginx-Reverse-Proxys in Produktionsumgebungen bereit.

**Enthaltene Sicherheitsmaßnahmen**:

| Konfigurationseintrag | Wirkung |
|--------|------|
| `server_tokens off` | versteckt die Nginx-Versionsnummer |
| `client_max_body_size 10m` | begrenzt die Requestbody-Größe, wirkt zusammen mit dem SecurityFilter |
| `limit_req_zone` | Anfragenratenbegrenzung auf Nginx-Ebene |
| `limit_conn_zone` | Begrenzung paralleler Verbindungen |
| `add_header`-Sicherheitsheader | fügt auf Nginx-Ebene Sicherheitsheader wie X-XSS-Protection hinzu |
| `if ($request_method)` | lehnt nicht-standardmäßige HTTP-Methoden auf Nginx-Ebene ab |
| SSL/TLS-Konfiguration | moderne TLS-1.2/1.3-Konfiguration, schwache Cipher-Suiten deaktiviert |
| Backend-Header ausblenden | `proxy_hide_header` entfernt sensible Header wie die webman-Version |

**Verwendung**: Die Konfiguration aus `docs/nginx-security.conf` in den eigenen Nginx-Server-Block übernehmen und an die tatsächliche Domain und Zertifikatspfade anpassen.

---

## 12. Bedrohungsmodell

### 12.1 Abgewehrte Bedrohungen

| Bedrohungstyp | Angriffsvektor | Verteidigungsebenen |
|----------|---------|---------|
| HTTP-Methodenmissbrauch | TRACE/TRACK-XST-Angriffe, CONNECT-Tunnel-Proxys, WebDAV-Methoden-Sondierung | SecurityFilter-405-Methoden-Whitelist (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| Gezieltes Brute-Force | wiederholte Passwortversuche gegen bestimmte Benutzer | Kontosperre (5 Fehlschläge sperren 15 Minuten) + RateLimit (Login 10/min) + Captcha |
| Brute-Force | verteilte IP-Versuche mit Benutzername/Passwort | RateLimit (Login 10/min) + Captcha |
| XSS (Cross-Site Scripting) | `<script>`, onerror, javascript: | SecurityFilter (5 Muster) + X-XSS-Protection-Response-Header + CSP |
| SQL-Injection | UNION SELECT, OR 1=1, Kommentar-Bypass | SecurityFilter (6 Muster) + Eloquent-ORM-Parameterabfragen |
| CSRF (Cross-Site Request Forgery) | bösartige Websites senden Anfragen im Namen des Opfers | SecurityFilter-Origin/Referer-Prüfung |
| Pfad-Traversal | `../../etc/passwd` | SecurityFilter-Pfad-Traversal-Muster + UploadController-Endungs-Whitelist |
| Befehlsinjektion | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 Muster) |
| Session-Hijacking | Diebstahl von JWT-Tokens | kurze JWT-Gültigkeit (2h) + Blacklist-Logout + sekundäre Passwortbestätigung bei sensiblen Aktionen |
| ID-Enumeration | Durchlaufen numerischer IDs zur Datenmengen-Schätzung | Hashids-Verschleierung zu Zufallszeichenfolgen |
| Datenleak | DB-Dumps / Man-in-the-Middle / Protokoll-Leaks | dreistufige Verschlüsselung/Maskierung + OperationLog-Filterung sensibler Felder |
| DoS-Angriffe | übergroße Requestbodys / hochfrequente Anfragen | Requestbody-10MB-Limit + RateLimit 60/min + IP-Blacklist |
| Privilege-Escalation | Benutzer mit niedrigen Rechten greifen Admin-Schnittstellen an | RBAC method.path-granulare Autorisierung |
| Datei-Upload-Angriffe | shell.php.png mit Doppel-Endung | SecurityFilter-Erkennung bösartiger Dateien |

### 12.2 Bekannte Einschränkungen

| Einschränkung | Betroffener Bereich | Abmilderung |
|------|---------|---------|
| CSRF-Schutz wirkt nur im Browser | Nicht-Browser-Clients (curl, Postman, Mobile-Apps) können die Origin/Referer-Prüfung überspringen | Nicht-Browser-Clients sind von Natur aus nicht durch CSRF gefährdet; JWT-Authentifizierung ersetzt Cookies |
| Bei Redis-Ausfall Rate-Limiting fail-closed (503), Blacklist-Prüfung fail-open | während des Ausfalls werden einige Anfragen abgelehnt; ausgeloggte Tokens kurz nutzbar | Redis-Verfügbarkeit überwachen und alarmieren; kurze JWT-Gültigkeit als Auffangschutz |
| Keine eigenständige WAF-Engine | SecurityFilter nutzt `@preg_match`-RegEx-Matching, keine dedizierte WAF-Regel-Engine | für Produktion wird vorgelagertes Nginx ModSecurity oder Cloudflare WAF empfohlen |
| JWT ist zustandslos und kann nicht aktiv widerrufen werden | Tokens können vor Ablauf nicht serverseitig aktiv widerrufen werden (außer über die Blacklist) | Blacklist + kurze 2h-TTL verringern das Risikofenster |
| IP-Blacklist nur im Speicher | nach Redis-Neustart geht die Blacklist verloren | Ban-Dauer nur 15 Minuten, Auswirkung begrenzt |
| Admin-Endpunkte ohne spezielles Rate-Limiting | Admin-Schnittstellen teilen das 60/min-Standardlimit mit normalen Schnittstellen | Admin-Operationsfrequenz ist von Natur aus niedrig, vorerst keine Unterscheidung nötig |
| `@preg_match` unterdrückt Fehler | bei fehlerhaftem RegEx-Eingabe stilles Fehlschlagen | `preg_last_error()` könnte überwacht werden, derzeit nicht implementiert |
