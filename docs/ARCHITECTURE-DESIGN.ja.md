# アーキテクチャ設計ドキュメント
<!-- lang-nav -->

Languages: **中文** · [English](ARCHITECTURE-DESIGN.en.md) · [한국어](ARCHITECTURE-DESIGN.ko.md) · [Русский](ARCHITECTURE-DESIGN.ru.md) · [Deutsch](ARCHITECTURE-DESIGN.de.md) · [Français](ARCHITECTURE-DESIGN.fr.md) · [Español](ARCHITECTURE-DESIGN.es.md) · [Português](ARCHITECTURE-DESIGN.pt.md) · [हिन्दी](ARCHITECTURE-DESIGN.hi.md) · [العربية](ARCHITECTURE-DESIGN.ar.md) · [বাংলা](ARCHITECTURE-DESIGN.bn.md) · [Bahasa Indonesia](ARCHITECTURE-DESIGN.id.md) · [日本語](ARCHITECTURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 設計目標

全球通用・国際化対応のゲームアグリゲーションプラットフォームを構築する。コア要件:

- ユーザーはプラットフォーム上でチャージ、ゲームコインへの交換、ゲームプレイ、ゲームコイン獲得、出金ができる
- プラットフォームは複数のゲーム（自研 + サードパーティ）を一元的に管理し、各ゲームに独立したゲームコインと為替レートを持つ
- 管理バックエンドは完全な審査、スイッチ、リスク管理機能を提供する
- 多言語、多通貨、複数決済チャネルのグローバル運営をサポートする

## 2. アーキテクチャ選定

### 2.1 なぜモジュラーモノリスを選び、マイクロサービスではないのか？

現段階ではモジュラーモノリス（Modular Monolith）を選択:

| 考量 | モジュラーモノリス | マイクロサービス |
|------|----------|--------|
| 開発効率 | 同一プロセス内で呼び出し、RPC 不要 | ネットワーク遅延、シリアライゼーションの処理が必要 |
| トランザクション整合性 | ローカル DB トランザクション | 分散トランザクション（複雑） |
| 運用の複雑さ | 単一プロセスデプロイ | マルチサービスオーケストレーション、サービスディスカバリ |
| 拡張性 | 将来モジュール単位でマイクロサービスに分割可能 | 独立したスケールアウトをネイティブサポート |
| チーム規模 | 小規模チーム向き (1-5人) | 複数チームの並行開発向き |

**決定**: admin/（管理バックエンド）と service/（C端業務）は 2 つの独立した webman インスタンスで、同機デプロイ（異なるポート）も分離デプロイも可能。共有レイヤー common/ は PSR-4 autoload でコード重複を解消。将来の業務量拡大後、service/ は複数のマイクロサービス（ユーザーサービス、ウォレットサービス、ゲームサービス）に分割可能。

### 2.2 なぜ伝統的な PHP-FPM ではなく webman v2 なのか？

| 考量 | webman v2 | PHP-FPM (Laravel) |
|------|-----------|-------------------|
| パフォーマンス | 常駐メモリ、コルーチンサポート | リクエストごとに全ファイルをロード |
| 並行性 | 単機で数万 QPS | 単機で数百 QPS |
| デプロイ | 簡単、単一プロセス+複数 worker | Nginx + PHP-FPM の設定が複雑 |
| エコシステム | Laravel Illuminate コンポーネント互換 | 完全なエコシステム |

**決定**: ゲームプラットフォームは高並行のチャージコールバック、交換リクエスト、ゲーム決済を処理する必要があり、webman の常駐メモリと高並行能力がより適している。同時に Laravel の ORM、Queue などのコンポーネントと互換性があり、開発効率は従来フレームワークに劣らない。

### 2.3 なぜ Flutter Web PC スタイルなのか？

- 1 つのコードで Web (PC)、iOS、Android、HarmonyOS を同時にコンパイル可能
- Material 3 コンポーネントライブラリが成熟しており、PC スタイルのサイドバー+トップバーレイアウトがすぐに使える
- HarmonyOS クライアントと業務ロジックレイヤーを共有
- React/Vue + Flutter の 2 セットのフロントエンドコードの保守を回避

## 3. 主要技術決定

### 3.1 ID 体系

```
Snowflake 生成 BIGINT（内部分布式唯一）
    ↓
Hashids 编码为短字符串（对外不可逆推真实ID）
    ↓
API 请求/响应中传输 hashid 字符串
```

**理由**:
- Snowflake はグローバル一意、トレンド増加でインデックスに有利、業務量を公開しない
- Hashids は外部が連番 ID によるデータ走査や規模推定を行うのを防ぐ

### 3.2 通貨精度

プラットフォームコインとゲームコインは統一して `DECIMAL(18,4)` 精度を使用し、PHP 側では `bcmath` 関数群（bcadd/bcsub/bcmul/bcdiv/bccomp）で全金額計算を行う。

**理由**: 浮動小数点数（float/double）は精度誤差があり、金融シナリオでは許容できない。DECIMAL + bcmath で正確な計算を保証する。

### 3.3 ウォレット楽観ロック

```sql
UPDATE erik_user_wallet 
SET balance = balance + ?, version = version + 1 
WHERE user_id = ? AND version = ?
```

更新失敗時は自動リトライ（最大5回）。

**理由**:
- ゲームプラットフォームのチャージ、交換、出金は同一ウォレットに並行アクセスする可能性がある
- 悲観ロック（SELECT FOR UPDATE）は高並行時にパフォーマンスが悪い
- 楽観ロックは衝突率が低いシナリオで悲観ロックよりはるかに優れたパフォーマンスを発揮する

### 3.4 出金審査フロー

```
用户发起提现
  ├─ 全局开关关闭 → 拒绝
  ├─ 金额 < 自动审核阈值 → 自动通过
  └─ 金额 >= 阈值 → 人工审核 → 通过/拒绝（拒绝退回平台币）
```

**理由**:
- グローバルスイッチは緊急リスク管理用（脆弱性発見、異常トラフィックなど）
- 少額の自動通過で人件費を削減し、ユーザー体験を向上
- 高額の手動審査でマネーロンダリングと詐欺を防止

### 3.5 交換差益モデル

各ゲームコインは独立した `exchange_rate`（1プラットフォームコイン = Xゲームコイン）と `spread_pct`（プラットフォーム抽成%）を持つ。

買い時: ゲームコイン入金 = プラットフォームコイン × 為替レート × (1 - 抽成%)
売り時: プラットフォームコイン入金 = ゲームコイン ÷ 為替レート × (1 - 抽成%)

**理由**:
- プラットフォーム収益はゲーム内課金ではなく交換差益に由来
- 独立した為替レートでゲームごとの価格戦略をサポート
- 差益率は柔軟に調整でき、きめ細かな運用が可能

## 4. セキュリティアーキテクチャ

既存の 18 層縦深防御に基づき、ゲームプラットフォーム向けに保護層を追加:

| レイヤー | 対策 | 理由 |
|------|------|------|
| 並行安全性 | ウォレット version 楽観ロック | 重複引き落とし/重複入金の防止 |
| 出金安全性 | グローバルスイッチ + 金額閾値 + 日/月限度額 + poster-php 検証 | 多層防御で資金リスクを低減 |
| 交換安全性 | 見積と約定を分離、見積は60秒で失効 | 為替レート変動による裁定取引の防止 |
| ゲーム安全性 | サードパーティコールバックの署名検証 + IP ホワイトリスト + replay attack 防御 | 偽造ゲーム決済の防止 |
| リスク管理 | ルールエンジン（IP ブラックリスト、高額警告、頻度異常） | 疑わしい取引のリアルタイム遮断 |

## 5. 国際化設計

### 5.1 言語検出

```
请求进入
  ↓
LanguageMiddleware（全局中间件）
  ├── 1. X-Language 请求头
  ├── 2. Accept-Language 头（zh → zh-CN, en → en-US）
  └── 3. 默认 en-US
  ↓
TranslationService::setLocale($locale)
  ↓
Controller 中 __() 函数或 TranslationService::trans() 获取翻译文本
```

### 5.2 翻訳の保存

- データベーステーブル `erik_translation` に全翻訳テキストを保存（group + key + lang_code + value）
- 初回リクエスト時にデータベースから全量を Redis にロード（key: `i18n:translations`、TTL: 1時間）
- 以降のリクエストは Redis から直接読み取り、メモリキャッシュで高速化
- 管理バックエンドで翻訳管理ページを拡張可能（完全版で実装）

### 5.3 翻訳キー命名

形式: `group.key`、例 `auth.login_success`、`wallet.insufficient_balance`

| グループ | ドメイン |
|------|------|
| auth | 認証関連 |
| wallet | ウォレット関連 |
| exchange | 交換関連 |
| withdraw | 出金関連 |
| deposit | チャージ関連 |
| game | ゲーム関連 |
| admin | 管理バックエンド |
| error | エラーメッセージ |

### 5.4 フォールバック戦略

- リクエスト言語に対応する翻訳がある → 使用
- リクエスト言語に対応する翻訳がない → en-US にフォールバック
- en-US にもない → 元の key を返す

### 5.5 フロントエンド i18n

- Flutter は自作の `AppTranslations` + `LocaleController`（GetX）を使用
- 言語設定は SharedPreferences に永続化
- 言語切り替え時に `Get.updateLocale()` でグローバル UI の再レンダリングをトリガー
- `StringResult` クラスは Dart の `toString()` を利用して自然なインライン構文を実現: `Text('${AppTranslations.t("key")}')`

## 6. 標準版で追加される設計

### 6.1 リスク管理エンジン

重要な資金操作の前に多層ルールチェックを実行:

```
充值/提现/兑换请求
  ↓
RiskService::check(userId, type, context)
  ├── IP 黑名单检测 (ip_blacklist) → block
  ├── 大额异常检测 (amount_anomaly) → warn
  ├── 频率检测 (frequency) → warn/block
  └── 速度检测 (velocity) → block
  ↓
passed → 正常执行
warn   → 记录日志，继续执行
block  → 拒绝操作
```

ルールは `erik_risk_rule` テーブルに保存され、設定は JSON で、閾値とアクションを動的に調整できる。

### 6.2 KYC 実名認証

3 段階認証体系:
- `default` — 未認証、基本限度額
- `verified` — KYC 審査通過、限度額引き上げ + 手数料引き下げ
- `vip` — VIP レベル、最高限度額 + 手数料ゼロ

認証フロー:
```
用户提交证件信息 → status=pending
管理员审核 → approve/reject
approve → 用户自动升级为 verified 等级
reject → 用户可重新提交
```

### 6.3 OAuth サードパーティログイン

Google / Facebook / Apple ログインをサポート:

```
前端点击 OAuth 按钮
  → GET /api/auth/oauth/{provider} → 获取授权URL
  → 跳转第三方授权页 → 用户同意
  → 回调 POST /api/auth/oauth/{provider}/callback
  → 查找已有绑定 → 直接登录
  → 无绑定 → 自动注册新用户 + 绑定 + 创建钱包
```

### 6.4 決済コールバック

```
第三方支付完成 → POST /api/payment/callback
  → provider 白名单校验（仅 stripe/paypal）
  → 验签 fail-closed（未配 secret/webhook_id、验签失败、时间戳超 ±300s 一律拒绝）
  → 回调金额与订单金额 bccomp 核对（防跨渠道冒用）
  → 更新订单状态 confirmed（事务化，入账失败回滚）
  → UserWallet::addBalance 到账
  → 记录 Transaction
  → RiskService::check 风控检查
```

### 6.5 段階別出金限度額

ユーザーの KYC レベルに応じて異なる限度額と手数料を適用:

| レベル | 単筆上限 | 日限度額 | 月限度額 | 手数料 |
|------|---------|--------|--------|--------|
| default | 1,000 | 10,000 | 50,000 | 1.00% |
| verified | 5,000 | 50,000 | 200,000 | 0.50% |
| vip | 20,000 | 200,000 | 1,000,000 | 0.00% |

## 7. 拡張性設計

### 5.1 水平拡張

admin/ と service/ はどちらも複数 worker プロセスをサポート。Nginx リバースプロキシと組み合わせて複数マシンにデプロイし水平拡張を実現可能:

```
Nginx (负载均衡)
  ├── admin-1 (:8787)
  ├── admin-2 (:8787)
  ├── service-1 (:8788)
  └── service-2 (:8788)
```

### 5.2 モジュール分割パス

単一の service/ がボトルネックになった場合、以下のパスで分割:

```
service/ (单体)
  → service-user/ (用户服务 :8788)
  → service-wallet/ (钱包服务 :8789)
  → service-game/ (游戏服务 :8790)
  → service-payment/ (支付服务 :8791)
```

分割の判断基準:
- 単一モジュールの QPS が単機の処理能力を超える
- あるモジュールに独立した技術スタックまたはデプロイ戦略が必要
- チーム規模が拡大し、異なるモジュールの並行開発が必要になる
