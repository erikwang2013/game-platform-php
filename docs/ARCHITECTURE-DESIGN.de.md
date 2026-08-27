# Architektur-Design-Dokument
<!-- lang-nav -->

Languages: **中文** · [English](ARCHITECTURE-DESIGN.en.md) · [한국어](ARCHITECTURE-DESIGN.ko.md) · [Русский](ARCHITECTURE-DESIGN.ru.md) · [Deutsch](ARCHITECTURE-DESIGN.de.md) · [Français](ARCHITECTURE-DESIGN.fr.md) · [Español](ARCHITECTURE-DESIGN.es.md) · [Português](ARCHITECTURE-DESIGN.pt.md) · [हिन्दी](ARCHITECTURE-DESIGN.hi.md) · [العربية](ARCHITECTURE-DESIGN.ar.md) · [বাংলা](ARCHITECTURE-DESIGN.bn.md) · [Bahasa Indonesia](ARCHITECTURE-DESIGN.id.md) · [日本語](ARCHITECTURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Designziele

Aufbau einer weltweit einsetzbaren, internationalisierten Spielaggregationsplattform. Kernanforderungen:

- Benutzer können auf der Plattform einzahlen, Spielwährung tauschen, Spiele spielen, Spielwährung verdienen und auszahlen
- Die Plattform verwaltet einheitlich verschiedene Spiele (Eigenentwicklung + Drittanbieter), jedes Spiel hat eine eigene Spielwährung und einen eigenen Wechselkurs
- Das Backend bietet vollständige Prüfungs-, Schalter- und Risikokontrollfähigkeiten
- Unterstützung des globalen Betriebs mit mehreren Sprachen, Währungen und Zahlungskanälen

## 2. Architektur-Auswahl

### 2.1 Warum Modular Monolith statt Microservices?

In der aktuellen Phase wird ein Modular Monolith gewählt:

| Überlegung | Modular Monolith | Microservices |
|------|----------|--------|
| Entwicklungseffizienz | Aufrufe im selben Prozess, kein RPC nötig | Netzwerklatenz und Serialisierung müssen behandelt werden |
| Transaktionskonsistenz | Lokale Datenbanktransaktionen | Verteilte Transaktionen (komplex) |
| Betriebskomplexität | Ein-Prozess-Deployment | Orchestrierung mehrerer Dienste, Service Discovery |
| Skalierbarkeit | Zukunft: modular in Microservices aufteilbar | Natürlich unabhängig skalierbar |
| Teamgröße | Geeignet für kleine Teams (1-5 Personen) | Geeignet für parallele Entwicklung mehrerer Teams |

**Entscheidung**: admin/ (Verwaltungsbackend) und service/ (C-End-Geschäft) sind zwei unabhängige webman-Instanzen, die auf derselben Maschine (verschiedene Ports) oder getrennt deployed werden können. Die gemeinsame Schicht common/ beseitigt Code-Duplikate über PSR-4 autoload. Wenn das Geschäftsvolumen künftig wächst, kann service/ in mehrere Microservices aufgeteilt werden (Benutzerservice, Wallet-Service, Spieleservice).

### 2.2 Warum webman v2 statt traditionellem PHP-FPM?

| Überlegung | webman v2 | PHP-FPM (Laravel) |
|------|-----------|-------------------|
| Leistung | Residente Speicherung, Coroutine-Unterstützung | Bei jeder Anfrage werden alle Dateien geladen |
| Nebenläufigkeit | Zehntausende QPS pro Maschine | Hunderte QPS pro Maschine |
| Deployment | Einfach, ein Prozess mit mehreren Workern | Nginx + PHP-FPM-Konfiguration komplex |
| Ökosystem | Kompatibel mit Laravel-Illuminate-Komponenten | Vollständiges Ökosystem |

**Entscheidung**: Die Spielplattform muss hochgradig nebenläufige Einzahlungs-Callbacks, Umtauschanfragen und Spielabrechnungen verarbeiten; die residente Speicherung und die hohe Nebenläufigkeit von webman sind besser geeignet. Gleichzeitig ist es mit Laravels ORM, Queue usw. kompatibel, die Entwicklungseffizienz steht traditionellen Frameworks nicht nach.

### 2.3 Warum Flutter Web im PC-Stil?

- Ein Codebestand lässt sich gleichzeitig für Web (PC), iOS, Android und HarmonyOS kompilieren
- Material-3-Komponentenbibliothek ist ausgereift, PC-Sidebar+Topbar-Layout sofort einsatzbereit
- Gemeinsame Geschäftslogikschicht mit dem HarmonyOS-Client
- Vermeidet die Pflege von zwei Frontend-Codebeständen (React/Vue + Flutter)

## 3. Wichtige technische Entscheidungen

### 3.1 ID-System

```
Snowflake generiert BIGINT (intern verteilt eindeutig)
    ↓
Hashids kodiert zu kurzen Strings (nach außen nicht auf echte IDs rückrechenbar)
    ↓
In API-Anfragen/-Antworten werden hashid-Strings übertragen
```

**Gründe**:
- Snowflake global eindeutig, trendsteigend und indexfreundlich, gibt kein Geschäftsvolumen preis
- Hashids verhindert, dass Außenstehende Daten über fortlaufende IDs durchlaufen und Größenordnungen erraten

### 3.2 Währungspräzision

Plattformwährung und Spielwährung verwenden einheitlich die Präzision `DECIMAL(18,4)`; auf PHP-Seite werden alle Geldberechnungen mit der `bcmath`-Funktionsfamilie (bcadd/bcsub/bcmul/bcdiv/bccomp) durchgeführt.

**Grund**: Gleitkommazahlen (float/double) haben Präzisionsfehler, die im Finanzbereich nicht akzeptabel sind. DECIMAL + bcmath gewährleistet exakte Berechnung.

### 3.3 Wallet-Optimistic-Lock

```sql
UPDATE game_user_wallet 
SET balance = balance + ?, version = version + 1 
WHERE user_id = ? AND version = ?
```

Bei fehlgeschlagenem Update automatischer Retry (maximal 5 Versuche).

**Gründe**:
- Einzahlungen, Umtausche und Auszahlungen der Spielplattform können dieselbe Wallet parallel bearbeiten
- Pessimistisches Sperren (SELECT FOR UPDATE) ist bei hoher Nebenläufigkeit leistungsschwach
- Optimistic-Lock übertrifft das pessimistische Sperren bei niedrigen Konfliktquoten deutlich

### 3.4 Auszahlungsprüfungs-Ablauf

```
Benutzer beantragt Auszahlung
  ├─ globaler Schalter aus → Ablehnung
  ├─ Betrag < automatische Prüfschwelle → automatisch genehmigt
  └─ Betrag >= Schwelle → manuelle Prüfung → genehmigt/abgelehnt (bei Ablehnung Plattformwährung zurück)
```

**Gründe**:
- Der globale Schalter dient der Notfall-Risikokontrolle (z. B. bei entdeckten Schwachstellen, anormalem Traffic)
- Automatische Genehmigung kleiner Beträge senkt Personalkosten und verbessert die Benutzererfahrung
- Manuelle Prüfung großer Beträge verhindert Geldwäsche und Betrug

### 3.5 Umtausch-Spread-Modell

Jede Spielwährung hat einen unabhängigen `exchange_rate` (1 Plattformwährung = X Spielwährung) und `spread_pct` (Plattformanteil %).

Beim Kauf: Spielwährungsgutschrift = Plattformwährung × Wechselkurs × (1 - Anteil %)
Beim Verkauf: Plattformwährungsgutschrift = Spielwährung ÷ Wechselkurs × (1 - Anteil %)

**Gründe**:
- Der Plattformerlös stammt aus der Umtauschspanne, nicht aus Zahlungen im Spiel
- Unabhängige Wechselkurse unterstützen Preisstrategien verschiedener Spiele
- Die Spread-Quote ist flexibel anpassbar für feingranulare Betriebsführung

## 4. Sicherheitsarchitektur

Aufbauend auf den bestehenden 18 Verteidigungsebenen, neue Schutzschichten für die Spielplattform:

| Ebene | Maßnahme | Grund |
|------|------|------|
| Nebenläufigkeitssicherheit | Wallet-version-Optimistic-Lock | verhindert doppelte Abbuchung/doppelte Gutschrift |
| Auszahlungssicherheit | globaler Schalter + Betragsschwelle + Tages-/Monatslimit + poster-php-Verifizierung | mehrschichtige Kontrolle, reduziert Geldrisiko |
| Umtauschsicherheit | Preisangebot und Ausführung getrennt, Angebot läuft in 60s ab | verhindert Arbitrage durch Wechselkursschwankungen |
| Spielsicherheit | Signaturprüfung der Drittanbieter-Callbacks + IP-Whitelist + Replay-Attack-Abwehr | verhindert gefälschte Spielabrechnungen |
| Risikokontrolle | Regel-Engine (IP-Blacklist, Großbetrags-Warnung, Frequenzanomalien) | blockiert verdächtige Transaktionen in Echtzeit |

## 5. Internationalisierungs-Design

### 5.1 Spracherkennung

```
Anfrage trifft ein
  ↓
LanguageMiddleware (globale Middleware)
  ├── 1. X-Language-Anfrage-Header
  ├── 2. Accept-Language-Header (zh → zh-CN, en → en-US)
  └── 3. Standard en-US
  ↓
TranslationService::setLocale($locale)
  ↓
Im Controller: __()-Funktion oder TranslationService::trans() für Übersetzungstexte
```

### 5.2 Übersetzungsspeicherung

- Datenbanktabelle `game_translation` speichert alle Übersetzungstexte (group + key + lang_code + value)
- Beim ersten Request werden alle Einträge aus der Datenbank in Redis geladen (key: `i18n:translations`, TTL: 1 Stunde)
- Folge-Requests lesen direkt aus Redis, Speichercache beschleunigt
- Das Verwaltungsbackend kann um eine Übersetzungsverwaltungsseite erweitert werden (Vollversion)

### 5.3 Benennung der Übersetzungsschlüssel

Format: `group.key` z. B. `auth.login_success`, `wallet.insufficient_balance`

| Gruppe | Domäne |
|------|------|
| auth | Authentifizierung |
| wallet | Wallet |
| exchange | Umtausch |
| withdraw | Auszahlung |
| deposit | Einzahlung |
| game | Spiele |
| admin | Verwaltungsbackend |
| error | Fehlermeldungen |

### 5.4 Fallback-Strategie

- Angefragte Sprache hat Übersetzung → verwenden
- Angefragte Sprache hat keine Übersetzung → Fallback auf en-US
- Auch en-US nicht vorhanden → ursprünglichen key zurückgeben

### 5.5 Frontend-i18n

- Flutter verwendet selbstgebautes `AppTranslations` + `LocaleController` (GetX)
- Sprachpräferenz wird in SharedPreferences persistiert
- Beim Sprachwechsel wird über `Get.updateLocale()` ein globales UI-Re-Rendering ausgelöst
- Die Klasse `StringResult` nutzt Dart `toString()` für natürliche Inline-Syntax: `Text('${AppTranslations.t("key")}')`

## 6. Neues Standardversion-Design

### 6.1 Risikokontroll-Engine

Vor kritischen Geldoperationen werden mehrschichtige Regelprüfungen ausgeführt:

```
Einzahlungs-/Auszahlungs-/Umtauschanfrage
  ↓
RiskService::check(userId, type, context)
  ├── IP-Blacklist-Erkennung (ip_blacklist) → block
  ├── Großbetrags-Anomalie-Erkennung (amount_anomaly) → warn
  ├── Frequenzerkennung (frequency) → warn/block
  └── Geschwindigkeitserkennung (velocity) → block
  ↓
passed → normal ausführen
warn   → protokollieren, weiter ausführen
block  → Operation ablehnen
```

Die Regeln liegen in der Tabelle `game_risk_rule`, konfiguriert als JSON, Schwellenwerte und Aktionen dynamisch anpassbar.

### 6.2 KYC-Identitätsprüfung

Dreistufiges Prüfsystem:
- `default` — nicht geprüft, Basislimits
- `verified` — KYC-Prüfung bestanden, höhere Limits + niedrigere Gebühren
- `vip` — VIP-Stufe, höchste Limits + keine Gebühren

Prüfungsablauf:
```
Benutzer reicht Ausweisdaten ein → status=pending
Admin prüft → approve/reject
approve → Benutzer wird automatisch auf verified hochgestuft
reject → Benutzer kann erneut einreichen
```

### 6.3 OAuth-Login von Drittanbietern

Unterstützt Google / Facebook / Apple-Login:

```
Frontend klickt OAuth-Button
  → GET /api/auth/oauth/{provider} → Autorisierungs-URL abrufen
  → Weiterleitung zur Autorisierungsseite des Drittanbieters → Benutzer stimmt zu
  → Callback POST /api/auth/oauth/{provider}/callback
  → bestehende Verknüpfung gefunden → direkt einloggen
  → keine Verknüpfung → neuen Benutzer automatisch registrieren + verknüpfen + Wallet erstellen
```

### 6.4 Zahlungs-Callback

```
Drittanbieter-Zahlung abgeschlossen → POST /api/payment/callback
  → Provider-Whitelist-Prüfung (nur stripe/paypal)
  → Signaturprüfung fail-closed (fehlendes secret/webhook_id, Signaturfehler, Zeitstempel über ±300s: alles ablehnen)
  → Callback-Betrag per bccomp mit Auftragsbetrag abgleichen (gegen kanalübergreifende Missbrauchung)
  → Auftragsstatus auf confirmed aktualisieren (transaktional, Rollback bei Gutschriftfehler)
  → UserWallet::addBalance Gutschrift
  → Transaction protokollieren
  → RiskService::check Risikoprüfung
```

### 6.5 Gestaffelte Auszahlungslimits

Je nach KYC-Stufe des Benutzers gelten unterschiedliche Limits und Gebühren:

| Stufe | Einzellimit | Tageslimit | Monatslimit | Gebühr |
|------|---------|--------|--------|--------|
| default | 1,000 | 10,000 | 50,000 | 1.00% |
| verified | 5,000 | 50,000 | 200,000 | 0.50% |
| vip | 20,000 | 200,000 | 1,000,000 | 0.00% |

## 7. Skalierbarkeits-Design

### 5.1 Horizontale Skalierung

admin/ und service/ unterstützen beide mehrere Worker-Prozesse. In Kombination mit dem Nginx-Reverse-Proxy können mehrere Maschinen für horizontale Skalierung deployed werden:

```
Nginx (Load Balancer)
  ├── admin-1 (:8787)
  ├── admin-2 (:8787)
  ├── service-1 (:8788)
  └── service-2 (:8788)
```

### 5.2 Modul-Splitting-Pfad

Wenn ein einzelnes service/ zum Engpass wird, wird nach folgendem Pfad aufgeteilt:

```
service/ (Monolith)
  → service-user/ (Benutzerservice :8788)
  → service-wallet/ (Wallet-Service :8789)
  → service-game/ (Spieleservice :8790)
  → service-payment/ (Zahlungsservice :8791)
```

Kriterien für den Splitting-Zeitpunkt:
- Der QPS eines einzelnen Moduls übersteigt die Tragfähigkeit einer Maschine
- Ein Modul benötigt einen eigenen Technologie-Stack oder eine eigene Deployment-Strategie
- Das Team ist so gewachsen, dass verschiedene Module parallel entwickelt werden müssen
