# 機能設計ドキュメント
<!-- lang-nav -->

Languages: **中文** · [English](FEATURE-DESIGN.en.md) · [한국어](FEATURE-DESIGN.ko.md) · [Русский](FEATURE-DESIGN.ru.md) · [Deutsch](FEATURE-DESIGN.de.md) · [Français](FEATURE-DESIGN.fr.md) · [Español](FEATURE-DESIGN.es.md) · [Português](FEATURE-DESIGN.pt.md) · [हिन्दी](FEATURE-DESIGN.hi.md) · [العربية](FEATURE-DESIGN.ar.md) · [বাংলা](FEATURE-DESIGN.bn.md) · [Bahasa Indonesia](FEATURE-DESIGN.id.md) · [日本語](FEATURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 通貨体系の設計

### 1.1 3層通貨モデル

```
第1層: 法定通貨 (USD / CNY / EUR / JPY ...)
       ↕ チャージ/出金（為替レートで交換）
第2層: プラットフォームコイン（統一、精度 decimal(18,4)）
       ↕ 交換（為替レート + プラットフォーム抽成差）
第3層: ゲームコイン（ゲームごとに独立、独立為替レート）
```

### 1.2 プラットフォームコイン

- プラットフォーム内の統一された価値単位
- 精度：`DECIMAL(18,4)`、最小単位 0.0001
- 法定通貨のチャージで獲得し、任意のゲームコインに交換可能
- ゲームコインもプラットフォームコインに戻せ、さらに法定通貨に出金可能
- プラットフォームは交換差額を収益源として徴収

### 1.3 ゲームコイン

- 各ゲームは複数のゲーム通貨を持つことができる（例: ゴールド、ダイヤモンド、ポイント）
- 各通貨はプラットフォームコインに対する交換レート (`exchange_rate`) を独立設定
- 各通貨はプラットフォーム抽成率 (`spread_pct`) を独立設定
- 最小/最大交換限度額 (`min_exchange` / `max_exchange`) の設定をサポート

### 1.4 交換計算式

**ゲームコインの購入（プラットフォームコイン → ゲームコイン）：**
```
ゲームコイン到着額 = プラットフォームコイン数量 × exchange_rate × (1 - spread_pct / 100)
```

**ゲームコインの売却（ゲームコイン → プラットフォームコイン）：**
```
プラットフォームコイン到着額 = ゲームコイン数量 ÷ exchange_rate × (1 - spread_pct / 100)
```

**例：**
- exchange_rate = 100（1プラットフォームコイン = 100ゲームコイン）
- spread_pct = 5%（プラットフォームが5%の差額を抽成）
- ユーザーが 10 プラットフォームコインを購入：(10 × 100 × 0.95) = 950 ゲームコイン
- ユーザーが 950 ゲームコインを売却：(950 ÷ 100 × 0.95) = 9.025 プラットフォームコイン
- プラットフォーム収益：10 - 9.025 = 0.975 プラットフォームコイン

## 2. ウォレット設計

### 2.1 プラットフォームコインウォレット (game_user_wallet)

ユーザー登録時に自動作成され、残高は初期 0。

| フィールド | 説明 |
|------|------|
| balance | 利用可能残高（チャージ/出金/交換可能） |
| frozen_balance | 凍結残高（予約、例: 出金中） |
| total_earned | 累計収入 |
| total_spent | 累計支出 |
| version | 楽観ロックバージョン番号（更新のたびに+1） |

### 2.2 ゲームコインウォレット (game_user_game_wallet)

ユーザー+ゲーム+通貨の3次元で一意。初回交換時に自動作成。

### 2.3 並行安全性

楽観ロックを使用して並行問題を防止：

```php
// 更新時にバージョン番号をチェック
$updated = Wallet::where('id', $wallet->id)
    ->where('version', $wallet->version)
    ->update([
        'balance' => bcadd($wallet->balance, $amount, 4),
        'version' => $wallet->version + 1,
    ]);

// 更新失敗（バージョン番号が変更済み）→ リトライ、最大5回
```

## 3. 出金システムの設計

### 3.1 多層制御

```
第1層: グローバル出金スイッチ
       ├─ オフ → 全出金を拒否、緊急リスク管理用
       └─ オン → 第2層のチェックへ

第2層: 限度額チェック
       ├─ 単筆最低金額 (min_amount)
       ├─ 単筆最高金額 (max_amount)
       └─ 日次累計限度額 (daily_limit)

第3層: 審査フロー
       ├─ 金額 < 自動審査閾値 → 自動通過
       └─ 金額 >= 自動審査閾値 → 人工審査 → 通過/拒否
```

### 3.2 出金ステートマシン

```
pending (審査待ち)
  ├─→ approved (承認済み) → completed (完了)
  └─→ rejected (拒否済み) → 残高返還 + 返金明細
```

### 3.3 管理バックエンドの制御

- **グローバルスイッチボタン**：全ユーザーの出金をワンクリックで有効/無効化
- **審査キュー**：時間順に並べた審査待ちリスト、承認/拒否ボタン
- **限度額設定**：各限度額パラメータをビジュアルに設定

## 4. チャージ設計

### 4.1 チャージフロー

```
1. ユーザーが決済方法と金額を選択
2. プラットフォームがチャージ注文を作成 (status=pending、一意の order_no を生成)
3. サードパーティ決済ページへ遷移
4. ユーザーが決済を完了
5. サードパーティがプラットフォームへコールバック (POST /api/payment/callback)
6. プラットフォームが署名検証 → 注文を更新 (status=confirmed)
7. プラットフォームコイン到着 → 明細を記録
```

### 4.2 決済方法

| タイプ | プロバイダー | 説明 |
|------|--------|------|
| 法定通貨 | Stripe | 国際クレジットカード決済 |
| 法定通貨 | PayPal | グローバル電子ウォレット |
| 法定通貨 | Alipay | 支付宝（国際版、Stripe Checkout APM 経由） |
| 法定通貨 | WeChat Pay | 微信支付（国際版、Stripe Checkout APM 経由） |
| 暗号通貨 | USDT-TRC20 | トロンチェーン USDT |

基本版ではまず単一の決済方法（例: Stripe）と連携し、標準版で全チャネルに拡張する。

## 5. ゲーム統合設計

### 5.1 自研ゲーム

自研ゲームはプラットフォームに直接統合され、ユーザー体系とウォレットを共有：

- ゲームは内部 API でユーザーのゲームコイン残高を照会
- ゲームの決済は内部 API でゲームコインを減算/加算
- 追加の署名検証は不要

### 5.2 サードパーティゲーム

サードパーティゲームは SDK/API で連携：

```
プラットフォーム側:
  1. ユーザーが「ゲームに入る」をクリック
  2. プラットフォームが署名を生成（user_id + timestamp + api_secret → HMAC-SHA256）
  3. 302リダイレクトまたはiframeでゲームURLをロード（署名パラメータを携帯）

ゲーム側:
  4. 署名検証 → ゲームセッションを確立
  5. 残高照会：GET /api/game/balance?user_id=...&sign=...
  6. 決済コールバック：POST /api/game/callback {user_id, amount, type, sign}
  7. プラットフォームが署名検証 → 残高更新 → 明細記録 → 結果を返す
```

### 5.3 署名アルゴリズム

```
signature = HMAC-SHA256(
    secret = api_secret,
    message = user_id + "|" + timestamp + "|" + amount + "|" + nonce,
)
```

検証条件：
- 署名が正しい
- タイムスタンプが ±60s 以内（replay attack 防止）
- nonce が未使用（Redis に記録、60s で期限切れ）
- リクエスト IP がホワイトリスト内

## 6. 権限設計

### 6.1 ロールプリセット

| ロール | 権限範囲 |
|------|---------|
| スーパー管理者 | * (すべての権限) |
| ゲーム運営 | ゲーム管理、お知らせ管理、ダッシュボード |
| 財務審査 | 出金審査、決済管理、明細閲覧 |
| カスタマーサポート | C端ユーザー閲覧、チャージ注文閲覧 |

### 6.2 権限粒度

```
{method}.{path}

例:
  get.admin/game/list      → ゲーム一覧の閲覧
  post.admin/game/create   → ゲームの作成
  put.admin/withdraw/review → 出金の審査
  put.admin/withdraw/switch → 出金スイッチの操作（スーパー管理者のみ）
```

## 呼. 標準版で追加される設計

### 8.1 リスク管理エンジン

4種類のルールタイプ：
- `ip_blacklist` — IP ブラックリスト一致、ヒット時は即時ブロック
- `amount_anomaly` — 単筆大額検知、閾値超過で警告を発出
- `frequency` — 時間ウィンドウ内の操作頻度検知
- `velocity` — 短時間の複数アカウント関連検知

ルールは priority の降順で実行され、最初に一致したルールが結果を決定する（block > warn > log）。

### 8.2 OAuth サードパーティログイン

対応プロバイダー：Google、Facebook、Apple

フロー：
1. フロントエンドが `GET /api/auth/oauth/{provider}` をリクエストし認可 URL を取得
2. ユーザーがサードパーティに遷移して認可を完了
3. コールバック `POST /api/auth/oauth/{provider}/callback` が認可コードを携帯
4. バックエンドが既存の連携を検索 → 直接ログイン；連携なし → 自動登録+連携+ウォレット作成

### 8.3 KYC 限度額体系

| レベル | 取得方法 | 単筆上限 | 日限度額 | 手数料 |
|------|---------|---------|--------|--------|
| default | 登録時デフォルト | 1,000 | 10,000 | 1.00% |
| verified | KYC審査通過 | 5,000 | 50,000 | 0.50% |
| vip | 運営が付与 | 20,000 | 200,000 | 0.00% |

### 8.4 ゲーム区サーバー

各ゲームは複数の区サーバーを設定可能（region: global/asia/eu/na）、区サーバーの状態：メンテナンス/正常/人気/新サーバー。

### 8.5 日次統計スナップショット

毎日早朝に crontab が `ComputeDailyStats::run()` を実行し、5つの指標を計算：
- ユーザー統計（新規/アクティブ/累計）
- チャージ統計（件数/総額）
- 出金統計（件数/総額）
- 交換統計（件数/手数料総額）
- ゲーム統計（プレイヤー数/セッション数）

## 9. プロダクションレベル機能

### 9.1 通知システム

通知タイプ：system/deposit/withdraw/kyc/coupon/announcement

自動トリガーシナリオ：
- チャージ到着 → NotificationService::send()
- 出金審査の承認/拒否 → 自動通知
- KYC 審査の承認/拒否 → 自動通知
- クーポン受取 → 自動通知
- 紹介報酬到着 → 自動通知

サイト内メッセージ + メールの2チャネルをサポート（メールは MAIL_HOST 環境変数の設定が必要）。

### 9.2 紹介返利

```
ユーザーA が紹介コードを生成 → ユーザーB に共有
ユーザーB が登録時に紹介コードを入力 → 双方が登録報酬(signup_reward)を獲得
ユーザーB がチャージ → A がチャージコミッション(deposit_commission_pct%)を獲得
```

### 9.3 2FA 二要素認証

- TOTP 標準プロトコル (RFC 6238)、Google Authenticator と互換
- 有効化フロー：シークレットキー取得 → QRコードで連携 → TOTP検証 → 8つの予備リカバリーコードを生成
- ログイン時の二次検証：POST /api/2fa/verify
- ±1 タイムウィンドウの許容差をサポート (30秒)

### 9.4 実 OAuth 連携

| プロバイダー | Tokenエンドポイント | ユーザー情報エンドポイント |
|--------|----------|------------|
| Google | oauth2.googleapis.com/token | www.googleapis.com/oauth2/v3/userinfo |
| Facebook | graph.facebook.com/v18.0/oauth/access_token | graph.facebook.com/me |
| Apple | appleid.apple.com/auth/token | JWT id_token デコード |

設定は PlatformConfig または環境変数で行い、リクエスト失敗時は自動で mock モードにフォールバック。

### 9.5 決済 Webhook 署名検証

- Stripe: HMAC-SHA256 署名検証 (Stripe-Signature ヘッダー)
- PayPal: 検証エンドポイントに POST で再送
- シークレット未設定時は検証を自動スキップ（開発モード）

### 9.6 WebSocket リアルタイムランキング

- プロトコル：WebSocket (ws://host:8789)
- 購読：{action: "subscribe", leaderboard_id: 123}
- プッシュ：{type: "ranking_update", rankings: [...]}
- ping/pong ハートビートのキープアライブをサポート

## 7. 国際化設計

### 7.1 対応言語

| コード | 名称 | 現地語 | アイコン |
|------|------|--------|------|
| en-US | English | English | us |
| zh-CN | Chinese (Simplified) | 简体中文 | cn |
| ja-JP | Japanese | 日本語 | jp |
| ko-KR | Korean | 한국어 | kr |

### 7.2 翻訳管理

- 翻訳は `group.key` 形式で整理（例: `auth.login_success`）
- データベーステーブル `game_translation` に保存、Redis キャッシュ（TTL 1時間）
- API: `GET /api/language/list` で利用可能な言語を取得、`POST /api/language/switch` で言語切替
- フロントエンドは `X-Language` リクエストヘッダーまたは `Accept-Language` で自動検出
- 翻訳がない場合は en-US にフォールバック、en-US にもなければ元の key を返す

### 7.3 ユーザー言語設定

- 登録時にブラウザの `Accept-Language` に基づいて自動設定
- ログイン後は `PUT /api/user/profile` で `language` フィールドを変更可能
- 言語切替時はユーザー記録も同期更新

## 8. プラットフォーム収益モデル

| 収益源 | 計算方法 | 説明 |
|---------|---------|------|
| 交換差額 | 交換ごとの spread_fee | 売買双方向で徴収 |
| 出金手数料 | 出金額 × fee_pct | 標準版で実装 |
| ゲーム分与 | サードパーティゲーム収益の分与 | 契約に基づく |
| チャージ為替差 | 法定通貨→プラットフォームコインの為替差 | プラットフォーム設定レートと市場レートの差 |
