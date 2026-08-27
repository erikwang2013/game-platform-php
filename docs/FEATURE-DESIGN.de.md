# Funktionsdesign-Dokument
<!-- lang-nav -->

Languages: **中文** · [English](FEATURE-DESIGN.en.md) · [한국어](FEATURE-DESIGN.ko.md) · [Русский](FEATURE-DESIGN.ru.md) · [Deutsch](FEATURE-DESIGN.de.md) · [Français](FEATURE-DESIGN.fr.md) · [Español](FEATURE-DESIGN.es.md) · [Português](FEATURE-DESIGN.pt.md) · [हिन्दी](FEATURE-DESIGN.hi.md) · [العربية](FEATURE-DESIGN.ar.md) · [বাংলা](FEATURE-DESIGN.bn.md) · [Bahasa Indonesia](FEATURE-DESIGN.id.md) · [日本語](FEATURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Währungssystem-Design

### 1.1 Drei-Ebenen-Währungsmodell

```
第1层: 法币 (USD / CNY / EUR / JPY ...)
       ↕ 充值/提现（按汇率兑换）
第2层: 平台币（统一，精度 decimal(18,4)）
       ↕ 兑换（含汇率 + 平台抽成差价）
第3层: 游戏币（每种游戏独立，独立汇率）
```

### 1.2 Plattformwährung

- Einheitliche Bewertungseinheit innerhalb der Plattform
- Präzision: `DECIMAL(18,4)`, kleinste Einheit 0.0001
- Erhältlich durch Einzahlung mit Fiatgeld, umtauschbar in beliebige Spielwährungen
- Spielwährungen können auch zurück in Plattformwährung getauscht und dann als Fiatgeld ausgezahlt werden
- Die Plattform erhebt die Umtauschspanne als Einnahmequelle

### 1.3 Spielwährung

- Jedes Spiel kann mehrere Spielwährungen haben (z. B. Goldmünzen, Diamanten, Punkte)
- Jede Währung hat einen unabhängigen Umtauschkurs zur Plattformwährung (`exchange_rate`)
- Jede Währung hat einen unabhängigen Plattformanteil (`spread_pct`)
- Unterstützt Mindest-/Höchstumtauschlimits (`min_exchange` / `max_exchange`)

### 1.4 Umtauschformeln

**Spielwährung kaufen (Plattformwährung → Spielwährung):**
```
游戏币到账 = 平台币数量 × exchange_rate × (1 - spread_pct / 100)
```

**Spielwährung verkaufen (Spielwährung → Plattformwährung):**
```
平台币到账 = 游戏币数量 ÷ exchange_rate × (1 - spread_pct / 100)
```

**Beispiel:**
- exchange_rate = 100 (1 Plattformwährung = 100 Spielwährungen)
- spread_pct = 5% (Plattformanteil 5%)
- Benutzer kauft mit 10 Plattformwährung: (10 × 100 × 0.95) = 950 Spielwährungen
- Benutzer verkauft 950 Spielwährungen: (950 ÷ 100 × 0.95) = 9.025 Plattformwährung
- Plattformerlös: 10 - 9.025 = 0.975 Plattformwährung

## 2. Wallet-Design

### 2.1 Plattformwährungs-Wallet (game_user_wallet)

Wird bei der Benutzerregistrierung automatisch erstellt, Startguthaben 0.

| Feld | Beschreibung |
|------|------|
| balance | Verfügbares Guthaben (ein-/auszahlbar und umtauschbar) |
| frozen_balance | Eingefrorenes Guthaben (reserviert, z. B. bei laufender Auszahlung) |
| total_earned | Kumulierte Einnahmen |
| total_spent | Kumulierte Ausgaben |
| version | Optimistic-Lock-Versionsnummer (bei jedem Update +1) |

### 2.2 Spielwährungs-Wallet (game_user_game_wallet)

Eindeutig über die Kombination Benutzer + Spiel + Währung. Wird bei der ersten Umtauschtransaktion automatisch erstellt.

### 2.3 Nebenläufigkeitssicherheit

Der Optimistic Lock verhindert Nebenläufigkeitsprobleme:

```php
// 更新时检查版本号
$updated = Wallet::where('id', $wallet->id)
    ->where('version', $wallet->version)
    ->update([
        'balance' => bcadd($wallet->balance, $amount, 4),
        'version' => $wallet->version + 1,
    ]);

// 更新失败（版本号已变）→ 重试，最多5次
```

## 3. Auszahlungssystem-Design

### 3.1 Mehrschichtige Kontrolle

```
第1层: 全局提现开关
       ├─ 关闭 → 所有提现拒绝，用于紧急风控
       └─ 开启 → 进入第2层检查

第2层: 限额检查
       ├─ 单笔最低金额 (min_amount)
       ├─ 单笔最高金额 (max_amount)
       └─ 每日累计限额 (daily_limit)

第3层: 审核流程
       ├─ 金额 < 自动审核阈值 → 自动通过
       └─ 金额 >= 自动审核阈值 → 人工审核 → 通过/拒绝
```

### 3.2 Auszahlungs-Zustandsautomat

```
pending (待审核)
  ├─→ approved (已通过) → completed (已完成)
  └─→ rejected (已拒绝) → 余额退回 + 退款流水
```

### 3.3 Verwaltungsbackend-Steuerung

- **Globaler Schalter**: Ein-Klick-Aktivierung/Deaktivierung aller Benutzerauszahlungen
- **Prüfungs-Warteschlange**: Nach Zeit sortierte Liste ausstehender Prüfungen mit Durchführen/Ablehnen-Schaltflächen
- **Limitkonfiguration**: Visuelle Einstellung der Limitparameter

## 4. Einzahlungsdesign

### 4.1 Einzahlungsablauf

```
1. 用户选择支付方式和金额
2. 平台创建充值订单 (status=pending, 生成唯一 order_no)
3. 跳转第三方支付页面
4. 用户完成支付
5. 第三方回调通知平台 (POST /api/payment/callback)
6. 平台验签 → 更新订单 (status=confirmed)
7. 平台币到账 → 记录流水
```

### 4.2 Zahlungsmethoden

| Typ | Anbieter | Beschreibung |
|------|--------|------|
| Fiat | Stripe | Internationale Kreditkartenzahlung |
| Fiat | PayPal | Globales E-Wallet |
| Fiat | Alipay | Alipay (Festlandchina) |
| Fiat | WeChat Pay | WeChat Pay (Festlandchina) |
| Kryptowährung | USDT-TRC20 | USDT auf dem Tron-Netzwerk |

Die Basisversion integriert zunächst eine einzelne Zahlungsmethode (z. B. Stripe), die Standardversion erweitert auf alle Kanäle.

## 5. Spielintegrationsdesign

### 5.1 Eigene Spiele

Eigene Spiele werden direkt in die Plattform integriert und teilen sich Benutzersystem und Wallet:

- Spiele fragen das Spielwährungsguthaben über die interne API ab
- Spielabrechnungen über die interne API buchen Spielwährungen ab/zu
- Keine zusätzliche Signaturprüfung erforderlich

### 5.2 Drittanbieter-Spiele

Drittanbieter-Spiele werden über SDK/API angebunden:

```
平台侧:
  1. 用户点击"进入游戏"
  2. 平台生成签名（user_id + timestamp + api_secret → HMAC-SHA256）
  3. 302跳转或iframe加载游戏URL（携带签名参数）

游戏侧:
  4. 验签 → 建立游戏会话
  5. 查询余额：GET /api/game/balance?user_id=...&sign=...
  6. 结算回调：POST /api/game/callback {user_id, amount, type, sign}
  7. 平台验签 → 更新余额 → 记录流水 → 返回结果
```

### 5.3 Signaturalgorithmus

```
signature = HMAC-SHA256(
    secret = api_secret,
    message = user_id + "|" + timestamp + "|" + amount + "|" + nonce,
)
```

Verifizierungsbedingungen:
- Signatur korrekt
- Zeitstempel innerhalb ±60s (Schutz vor Replay-Angriffen)
- nonce noch nicht verwendet (in Redis gespeichert, 60s Ablauf)
- Anfrage-IP in der Whitelist

## 6. Berechtigungsdesign

### 6.1 Rollenvorgaben

| Rolle | Berechtigungsumfang |
|------|---------|
| Superadministrator | * (alle Berechtigungen) |
| Spielbetrieb | Spielverwaltung, Ankündigungsverwaltung, Dashboard |
| Finanzprüfung | Auszahlungsprüfung, Zahlungsverwaltung, Transaktionsansicht |
| Kundenservice | C-End-Benutzeransicht, Einzahlungsauftragsansicht |

### 6.2 Berechtigungsgranularität

```
{method}.{path}

示例:
  get.admin/game/list      → 查看游戏列表
  post.admin/game/create   → 创建游戏
  put.admin/withdraw/review → 审核提现
  put.admin/withdraw/switch → 操作提现开关（仅超级管理员）
```

## 呼. Neu hinzugefügtes Standardversion-Design

### 8.1 Risikokontroll-Engine

Vier Regeltypen:
- `ip_blacklist` — IP-Blacklist-Treffer, direkt blockieren
- `amount_anomaly` — Einzelbetrags-Großmengenerkennung, Warnung bei Überschreiten des Schwellenwerts
- `frequency` — Erkennung der Operationsfrequenz innerhalb eines Zeitfensters
- `velocity` — Kurzzeitige Multi-Account-Verknüpfungserkennung

Regeln werden in absteigender priority-Reihenfolge ausgeführt, die erste übereinstimmende Regel entscheidet (block > warn > log).

### 8.2 OAuth-Drittanbieter-Login

Unterstützte Anbieter: Google, Facebook, Apple

Ablauf:
1. Frontend fragt `GET /api/auth/oauth/{provider}` ab, um die Autorisierungs-URL zu erhalten
2. Benutzer wird zum Drittanbieter weitergeleitet und schließt die Autorisierung ab
3. Callback `POST /api/auth/oauth/{provider}/callback` übermittelt den Autorisierungscode
4. Backend findet bestehende Verknüpfung → direkter Login; keine Verknüpfung → automatische Registrierung + Verknüpfung + Wallet-Erstellung

### 8.3 KYC-Limitsystem

| Stufe | Erhaltungsweise | Einzelbetragsobergrenze | Tageslimit | Gebühr |
|------|---------|---------|--------|--------|
| default | Standard bei Registrierung | 1,000 | 10,000 | 1.00% |
| verified | KYC-Prüfung bestanden | 5,000 | 50,000 | 0.50% |
| vip | Von der Betriebsabteilung vergeben | 20,000 | 200,000 | 0.00% |

### 8.4 Spielregionen/-server

Jedes Spiel kann mehrere Regionen konfigurieren (region: global/asia/eu/na), Serverstatus: Wartung/Normal/Voll/Neu.

### 8.5 Tägliche Statistik-Snapshots

Täglich um Mitternacht führt crontab `ComputeDailyStats::run()` aus und berechnet fünf Kennzahlen:
- Benutzerstatistik (neu/aktiv/kumuliert)
- Einzahlungsstatistik (Anzahl/Gesamtbetrag)
- Auszahlungsstatistik (Anzahl/Gesamtbetrag)
- Umtauschstatistik (Anzahl/Gebührengesamt)
- Spielstatistik (Spielerzahl/Sessionszahl)

## 9. Produktionsreife Funktionen

### 9.1 Benachrichtigungssystem

Benachrichtigungstypen: system/deposit/withdraw/kyc/coupon/announcement

Automatisch ausgelöste Szenarien:
- Einzahlung eingegangen → NotificationService::send()
- Auszahlungsprüfung durchgeführt/abgelehnt → automatische Benachrichtigung
- KYC-Prüfung durchgeführt/abgelehnt → automatische Benachrichtigung
- Gutschein eingelöst → automatische Benachrichtigung
- Empfehlungsbelohnung eingegangen → automatische Benachrichtigung

Unterstützt In-App-Nachrichten + E-Mail als duale Kanäle (E-Mail erfordert die MAIL_HOST-Umgebungsvariable).

### 9.2 Empfehlungsprovision

```
用户A 生成推荐码 → 分享给用户B
用户B 注册时填写推荐码 → 双方各得注册奖励(signup_reward)
用户B 充值 → A 获得充值返佣(deposit_commission_pct%)
```

### 9.3 2FA-Zwei-Faktor-Authentifizierung

- TOTP-Standardprotokoll (RFC 6238), kompatibel mit Google Authenticator
- Aktivierungsablauf: Schlüssel abrufen → QR-Code scannen und binden → TOTP verifizieren → 8 Backup-Wiederherstellungscodes generieren
- Zweite Anmeldeverifizierung: POST /api/2fa/verify
- Unterstützt ±1 Zeitfenster-Toleranz (30 Sekunden)

### 9.4 Echte OAuth-Anbindung

| Anbieter | Token-Endpunkt | Benutzerinfo-Endpunkt |
|--------|----------|------------|
| Google | oauth2.googleapis.com/token | www.googleapis.com/oauth2/v3/userinfo |
| Facebook | graph.facebook.com/v18.0/oauth/access_token | graph.facebook.com/me |
| Apple | appleid.apple.com/auth/token | JWT id_token-Dekodierung |

Konfiguration über PlatformConfig oder Umgebungsvariablen; bei Anfragefehlern automatischer Fallback auf den Mock-Modus.

### 9.5 Zahlungs-Webhook-Verifizierung

- Stripe: HMAC-SHA256-Signaturprüfung (Stripe-Signature-Header)
- PayPal: POST zurück zum PayPal-Verifizierungsendpunkt
- Bei nicht konfiguriertem Schlüssel wird die Prüfung automatisch übersprungen (Entwicklungsmodus)

### 9.6 WebSocket-Echtzeit-Rangliste

- Protokoll: WebSocket (ws://host:8789)
- Abonnement: {action: "subscribe", leaderboard_id: 123}
- Push: {type: "ranking_update", rankings: [...]}
- Unterstützt ping/pong-Heartbeat zur Verbindungserhaltung

## 7. Internationalisierungsdesign

### 7.1 Unterstützte Sprachen

| Code | Name | Lokale Bezeichnung | Symbol |
|------|------|--------|------|
| en-US | English | English | us |
| zh-CN | Chinese (Simplified) | 简体中文 | cn |
| ja-JP | Japanese | 日本語 | jp |
| ko-KR | Korean | 한국어 | kr |

### 7.2 Übersetzungsverwaltung

- Übersetzungen sind im Format `group.key` organisiert (z. B. `auth.login_success`)
- Speicherung in der Datenbanktabelle `game_translation`, Redis-Cache (TTL 1 Stunde)
- API: `GET /api/language/list` ruft verfügbare Sprachen ab, `POST /api/language/switch` wechselt die Sprache
- Das Frontend erkennt automatisch über den `X-Language`-Request-Header oder `Accept-Language`
- Bei fehlender Übersetzung Fallback auf en-US; auch en-US fehlt → Rückgabe des Original-keys

### 7.3 Benutzersprachpräferenz

- Bei der Registrierung automatisch anhand des Browser-`Accept-Language` gesetzt
- Nach dem Login über `PUT /api/user/profile` das Feld `language` änderbar
- Beim Sprachwechsel wird der Benutzerdatensatz synchron aktualisiert

## 8. Plattform-Erlösmodell

| Einnahmequelle | Berechnungsweise | Beschreibung |
|---------|---------|------|
| Umtauschspanne | spread_fee pro Umtausch | Beim Kauf und Verkauf erhoben |
| Auszahlungsgebühr | Auszahlungsbetrag × fee_pct | In der Standardversion umgesetzt |
| Spielumsatzbeteiligung | Umsatzbeteiligung Drittanbieter-Spiele | Gemäß Vertragsvereinbarung |
| Einzahlungswechselkursdifferenz | Wechselkursdifferenz Fiat→Plattformwährung | Differenz zwischen Plattformkurs und Marktkurs |
