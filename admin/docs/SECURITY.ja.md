# セキュリティアーキテクチャ設計ドキュメント
<!-- lang-nav -->

Languages: [中文](SECURITY.md) · [English](SECURITY.en.md) · [한국어](SECURITY.ko.md) · [Русский](SECURITY.ru.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Español](SECURITY.es.md) · [Português](SECURITY.pt.md) · [हिन्दी](SECURITY.hi.md) · [العربية](SECURITY.ar.md) · [বাংলা](SECURITY.bn.md) · [Bahasa Indonesia](SECURITY.id.md) · **日本語**


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 多層防御の全景

システムは7層の多層防御モデルを採用し、外から内へ悪意のあるリクエストを層ごとにフィルタリングします。どの単一層が機能しなくても、後続の防衛線が受け止めることを保証します。

中間ウェアチェーン全体は以下の順序で実行されます（`config/middleware.php` 参照）：

```
请求 → Cors → SecurityFilter → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| 層 | 中間ウェア/メカニズム | 防御対象 |
|----|--------|---------|
| 1 | SecurityFilter | XSS / SQL インジェクション / パストラバーサル / コマンドインジェクション / CSRF 攻撃遮断 |
| 2 | Cors | クロスドメインセキュリティ + レスポンスセキュリティヘッダー注入 |
| 3 | RateLimit | Redis スライディングウィンドウレート制限、ブルートフォース防止 |
| 4 | AdminAuth | JWT 認証 + ブラックリストログアウト |
| 5 | AdminPermission | RBAC method.path 粒度の認可 |
| 6 | OperationLog | 操作監査 + 送信元追跡 |
| 7 | データ暗号化 | Hashids ID 難読化 + Encryptable DB 暗号化 + EncryptionService 転送暗号化 |

フロントエンド側（Flutter）には別途独立した入力検証があり、バックエンドはそれを信頼せず、各層が独立して防御します。

---

## 2. 攻撃検出エンジン

### 2.0 HTTP メソッド制限

SecurityFilter はすべての攻撃検出の前に HTTP メソッドを検証し、以下の標準メソッドのみ許可します：

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

非標準メソッド（TRACE、CONNECT、PATCH、カスタムメソッドなど）は直接 **405 Method Not Allowed** を返し、レスポンスボディは空の HTML で、以降の攻撃検出やビジネスロジックには入りません。

これは多層防御の第一の防衛線で、以下を効果的に阻止します：
- TRACE クロスサイトトレーシング攻撃（XST）
- CONNECT トンネルプロキシの悪用
- 非標準 WebDAV メソッドのプロービング
- 自動スキャナーによる HTTP メソッド列挙

### 2.1 XSS クロスサイトスクリプティング

すべての正規表現は `SecurityFilter::PATTERNS['XSS']` に由来し、大文字小文字を区別しないマッチングです。

| 検出パターン | 正規表現 | 防御する攻撃 |
|----------|------|-----------|
| スクリプトタグ | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` などの空白バリアント |
| イベント属性 | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | `onclick="javascript:..."` などのインラインイベント |
| JS 疑似プロトコル | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` など |
| Data URI XSS | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` など |
| テンプレートインジェクション | `\{\{.*?\}\}` | `{{constructor}}`, `{{7*7}}` などのサーバー側/Angular/Vue テンプレートインジェクション |

### 2.2 SQL インジェクション

| 検出パターン | 正規表現 | 防御する攻撃 |
|----------|------|-----------|
| UNION 複合クエリ | `\bUNION\s+(?:ALL\s+)?SELECT\b` | `UNION SELECT`, `UNION ALL SELECT` によるデータ流出 |
| OR 恒真インジェクション | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| テーブル構造破壊 | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| ストアドプロシージャ呼び出し | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | MSSQL 拡張ストアドプロシージャによるコマンド実行 |
| メタデータ探索 | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | MySQL/PG/SQLite/MSSQL のデータベース構造探索 |
| コメントバイパス | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | `'-- OR SELECT`, `'# AND UPDATE` コメントバイパス |

### 2.3 パストラバーサル

| 検出パターン | 正規表現 | 防御する攻撃 |
|----------|------|-----------|
| ディレクトリトラバーサル | `\.\.[\/\\\\]{2,}` | `../`, `..\`, `....//` 多段ディレクトリトラバーサル |
| 機密ファイル探索 | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` など |
| ヌルバイトトランケーション | `%00` | `../../../etc/passwd%00.jpg` による拡張子検証バイパス |

### 2.4 コマンドインジェクション

| 検出パターン | 正規表現 | 防御する攻撃 |
|----------|------|-----------|
| パイプ/セミコロンコマンド | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| バッククォート置換 | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| $() 置換 | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| リモートダウンロードパイプ | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF クロスサイトリクエストフォージェリ

検証ロジックは `SecurityFilter::checkCsrf()` に実装されています：

```php
// 仅 POST/PUT/DELETE 触发校验
// Origin 头和 Referer 均为空 → 放行（非浏览器客户端）
// Origin 非空 → 解析 Origin 域名与 Host 比对
```

照合ルール：
- Host から `www.` プレフィックスを除去した後、Origin のドメインと完全一致比較
- Host が Origin の親ドメインの場合（例：`Origin: app.example.com`, `Host: example.com` — `str_contains($originHost, '.' . $hostOnly)` を発動）、許可
- 完全一致でもサブドメインでもない → 403 を返し、CSRF 攻撃と判定

注意：非ブラウザクライアント（Origin/Referer を持たない curl など）は直接許可され、CSRF 保護はブラウザ環境でのみ有効です。

### 2.6 悪意のあるファイルアップロード

| 検出パターン | 正規表現 | 防御する攻撃 |
|----------|------|-----------|
| 二重拡張子偽装 | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` によるホワイトリストバイパス |
| PHP 拡張子 | `\.php\s*$/m` | リクエストパラメータで直接 `.php` パスを渡す |

---

## 3. 攻撃エスカレーションと IP ブラックリスト

SecurityFilter は攻撃エスカレーションメカニズムを内蔵し、同一 IP による継続的なスキャン攻撃を防止します。

### エスカレーションフロー

```
第 1 次扫描命中 → Redis INCR security_escalate:{ip} = 1, TTL=60s
第 2 次扫描命中 → INCR → 2
...
第 5 次扫描命中 → INCR → 5
    → 触发封禁: SETEX security_ban:{ip} 900 1
    → 清除计数器 DEL security_escalate:{ip}
    → 写入安全日志: [SECURITY] IP banned 15min
```

### 封禁中の挙動

各リクエストは SecurityFilter に入る際にまず `isBanned()` をチェックします：

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

封禁された IP は15分間、すべてのリクエスト（正当なリクエストを含む）が直接 403 を返し、以降のビジネスロジックを完全にスキップします。

### 設定定数

| 定数 | 値 | 意味 |
|------|-----|------|
| ESCALATE_LIMIT | 5 | 60秒ウィンドウ内の発動回数しきい値 |
| ESCALATE_WINDOW | 60 | カウンターウィンドウ（秒） |
| BAN_DURATION | 900 | ブラックリスト持続時間（秒）、つまり15分 |

### セキュリティログ

ファイル位置：`runtime/logs/security.log`

ログ形式の例：
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### リクエストボディサイズ制限

`Content-Length > 10MB` は直接 413 Payload Too Large を返し、超大リクエストボディによる DoS 攻撃を防止します。

### Content-Type 検証

POST/PUT リクエストは **必ず** `Content-Type` を `application/json` または `application/x-www-form-urlencoded` に宣言する必要があり、そうでない場合は 415 Unsupported Media Type を返します。ファイルアップロードリクエスト（file フィールド付き）はこのチェックをスキップします。

---

## 4. レスポンスセキュリティヘッダー

すべてのヘッダーは `Cors` 中間ウェアで注入され、`$response->withHeaders()` で各レスポンスに追加されます。

| ヘッダー | 値 | 作用 |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | 任意の送信元からのクロスドメインを許可（内網管理画面のシナリオ） |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | 許可されるメソッドセット |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | 許可されるカスタムヘッダー |
| Access-Control-Max-Age | `86400` | プリフライトリクエストを24時間キャッシュ |
| X-Content-Type-Options | `nosniff` | ブラウザの MIME スニッフィングを禁止 |
| X-Frame-Options | `DENY` | すべての iframe 埋め込みを禁止、クリックジャッキング防止 |
| X-XSS-Protection | `1; mode=block` | ブラウザ内蔵 XSS フィルターを有効にし、ページレンダリングを遮断 |
| Referrer-Policy | `strict-origin-when-cross-origin` | 同一送信元は完全 URL、クロスドメインはドメインのみ送信 |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | サイト全体でカメラ/マイク/位置情報 API を無効化 |

OPTIONS プリフライトリクエストは直接 204 空レスポンスを返し、以降の中間ウェアチェーンには入りません。

### 4.2 Content-Security-Policy (CSP)

他のセキュリティヘッダーと一緒に Cors 中間ウェアで注入され、多層防御を提供してブラウザがロード・実行できるリソースの送信元を制限します。

| ヘッダー | 値 | 作用 |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | スクリプト/スタイル/画像/接続/フレーム/フォームなどのリソース送信元を制限 |
| X-Permitted-Cross-Domain-Policies | `none` | Adobe Flash/PDF などのクロスドメインポリシーファイルの読み込みを禁止 |

CSP ポリシーの要点：
- `default-src 'self'`：デフォルトでは同一送信元リソースのみ許可
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`：同一送信元スクリプト + インラインスクリプト（Flutter Web 必須）+ eval（Flutter Web デバッグ必須）を許可
- `frame-ancestors 'none'`：いかなるページによる iframe 埋め込みも禁止、X-Frame-Options: DENY との二重保険
- `base-uri 'self'`：`<base>` タグを同一送信元のみに制限
- `form-action 'self'`：フォームの送信先を同一送信元のみに制限

---

## 5. レート制限戦略

### アルゴリズム

Redis Sorted Set スライディングウィンドウ + Lua 原子化スクリプト、主要操作：

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

Lua スクリプトは Redis サーバー側でシングルスレッド実行されるため、**本質的に原子化**されており、TOCTOU（Time-of-check to Time-of-use）競合状態を排除します。

### レート制限設定

| ルート | 制限 | ウィンドウ | シナリオ |
|------|------|------|------|
| デフォルト（全ルート） | 60 回/分 | 60s | 汎用 API |
| `/api/auth/login` | 10 回/分 | 60s | ログイン（ブルートフォース防止） |
| `/api/auth/register` | 5 回/分 | 60s | 登録（一括登録防止） |

### レスポンスヘッダー

レート制限発動時は HTTP 429 と JSON ボディを返します：
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

すべてのレスポンス（正常レスポンスを含む）には以下のヘッダーが付きます：

| ヘッダー | 説明 |
|----|------|
| X-RateLimit-Limit | 現在のウィンドウで許可される最大リクエスト数 |
| X-RateLimit-Remaining | 現在のウィンドウの残りリクエスト数 |
| X-RateLimit-Reset | ウィンドウリセットの Unix タイムスタンプ |
| Retry-After | レート制限時のみ付与、待機推奨秒数 |

### デグレード戦略

Redis 異常時（接続タイムアウト、利用不可など）は **fail-closed**：

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    // Redis down: 限流不可用即拒绝，避免安全防线失效（登录/支付回调限流为空转）
    return json(['code' => 503, 'message' => '服务暂不可用，请稍后再试', 'data' => []])
        ->withStatus(503)->withHeaders(['Retry-After' => '5']);
}
```

レート制限はログインのブルートフォース防止、決済コールバックのリプレイ防止の第一の安全防衛線であり、Redis 障害時はリクエストを通すより拒否（503）を選びます。

### 5.4 アカウントロックメカニズム

ログインAPIはレート制限に加え、特定ユーザーへの標的型ブルートフォースを防ぐ**アカウントロック**メカニズムを追加しています。

**ロックフロー**：

```
登录失败 → Redis INCR account_lockout:{userId} TTL=900s
连续 5 次失败 → Redis SETEX account_locked:{userId} 900 1
            → 返回 429 "账号已被锁定，请15分钟后再试"
            → 清除计数器 DEL account_lockout:{userId}
```

**ロック中の挙動**：

ロック中はすべてのログインリクエストが直接 429 を返し、パスワード検証を行わず、ブルートフォースの試みを完全に阻止します。

**設定定数**：

| 定数 | 値 | 意味 |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | 最大連続失敗回数 |
| LOCKOUT_DURATION | 900 | ロック持続時間（秒）、つまり15分 |

注意：アカウントロックは IP ではなく `userId` ベースのため、攻撃者が IP を変えてもロックを回避できません。IP レート制限（10回/分）と重ねて二重防御を形成します：
- IP レベル：10 回/分のレート制限で分散ブルートフォースを阻止
- アカウントレベル：5回失敗でロックし、標的型ブルートフォースを阻止

---

## 6. 認証と認可

### 6.1 JWT 認証

AdminAuth 中間ウェアで実装され、認証が必要なルートグループにマウントされます。

**パラメータ設定**（`config/plugin/erikwang2013/jwt/jwt`、`.env` から注入）：

| パラメータ | 値 | 説明 |
|------|-----|------|
| アルゴリズム | HS256 | HMAC-SHA256 対称署名 |
| キー | `JWT_SECRET_KEY` | 環境変数から注入、欠落またはデフォルト値のままの場合は**起動を拒否**（fail-closed） |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_TTL` |
| 発行者 | `open-admin` | `JWT_ISSUER` |
| オーディエンス | `open-admin` | `JWT_AUDIENCE` |

**Token 抽出**：`Authorization: Bearer <token>` ヘッダーから抽出し、`Bearer ` プレフィックスを除去して元の JWT を取得します。

**認証フロー**：
1. 空トークン → 直接 401 `{"code": 401, "message": "未登录"}`
2. Redis ブラックリスト `jwt_blacklist:{md5(token)}` をチェック → ヒット → 401 `Token已失效，请重新登录`
3. JWT decode → 失敗（期限切れ/署名不一致） → 401 `Token已过期或无效`
4. 成功 → `$request->adminId` と `$request->adminUsername` を注入

**ブラックリストメカニズム**：ユーザーログアウト時、`md5(token)` を Redis に書き込み、TTL を JWT の残り有効期間に設定します。Redis 障害時はブラックリストチェックがスキップされ（fail-open）、その場合ログアウト済みの Token が短期間使用可能ですが、JWT 自体の短期有効期間（2h）がフォールバック保護となります。

**Token 更新**：`POST /api/auth/refresh` は元の refresh token（`token_type=refresh` かつ期限切れでない/ブラックリスト入りしていない）を検証してからローテーション発行し、`sub` が有効なユーザー ID であることを検証します —— **sub=null の refresh token は発行しません**。更新失敗時は直接 401 を返します。

### 6.2 同時セッション制限

Token 漏洩後の多デバイス悪用を防ぐため、システムは同一ユーザーが同時に保持できる有効 Token 数を制限します。

**制限ロジック**：

```
登录成功 → 签发新 Token
         → 查询当前用户有效 Token 数量: Redis SCARD user_tokens:{userId}
         → 若数量 >= 3（MAX_CONCURRENT_SESSIONS）:
            → 按创建时间升序排列，移除最旧的 Token:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → 将新 Token 加入集合: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**設定定数**：

| 定数 | 値 | 意味 |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | 同一ユーザーの最大同時 Token 数 |

**強制ログアウトのシナリオ**：ユーザーが4台目のデバイスでログインすると、1台目のデバイスの Token が強制的にブラックリスト入りし、以降のリクエストは 401 "Token已失效，请重新登录" を返します。

ログアウト時、現在の Token はセットから削除されます。Token が自然に期限切れになると Redis キーが自動失効し、セットのメンバーも減少します。

### 6.3 RBAC 権限モデル

AdminPermission 中間ウェアで実装されます。

**データモデル**：User -> Role -> Permission の3層関連

- `game_admin_user` (ユーザーテーブル)
- `game_admin_user_role` (ユーザー-ロール関連テーブル)
- `game_admin_role` (ロールテーブル)
- `game_admin_role_permission` (ロール-権限関連テーブル)
- `game_admin_permission` (権限テーブル)

**権限タイプ**：
| type | 意味 | 例 |
|------|------|------|
| 1 | メニュー権限 | 左側ナビゲーションの可視性を制御 |
| 2 | ボタン権限 | ページ内の操作ボタン (新規/編集/削除) を制御 |
| 3 | API 権限 | バックエンドAPI呼び出しを制御 |

API 権限識別子の形式：`{method}.{path}`

例：
- `post.admin/user` — ユーザー作成
- `put.admin/user` — ユーザー編集
- `delete.admin/user` — ユーザー削除
- `get.admin/user` — ユーザー一覧表示

**認可フロー**：
1. `$request->adminId` が空（未ログイン）→ 直接 401 `{"code": 401, "message": "未登录"}`、以降は進めない
2. ユーザー → ロール（`status=0` の無効ロールはスキップ）→ 権限リストを取得
3. スーパー管理者（`slug = '*'`）→ 直接許可
4. `strtolower(method) . '.' . trim(path, '/')` を構築 → 権限リストと照合
5. 照合失敗 → 403 `{"code": 403, "message": "无权限访问"}`

**再確認**：BaseController が `confirmPassword()` メソッドを提供し、機密操作（ユーザー削除、データエクスポートなど）は Controller 層で現在のパスワード入力を追加要求し、セッションハイジャック後の不正操作を防ぎます。

### 6.4 決済コールバック署名検証（fail-closed）

`POST /api/payment/callback`（Stripe/PayPal 入金コールバック）の署名検証は **fail-closed** を採用し、設定欠落や検証異常はいずれもコールバックを拒否します：

| シナリオ | 挙動 |
|------|------|
| Stripe に `STRIPE_WEBHOOK_SECRET` 未設定 | 拒否（403）、署名なしコールバックは受け付けない |
| Stripe 署名欠落 / 署名検証失敗 | 拒否（403） |
| Stripe タイムスタンプ `t=` 欠落、またはサーバー時間との差が **> ±5 分** | 拒否（403）、リプレイ防止 |
| PayPal に `PAYPAL_WEBHOOK_ID` 未設定 | 拒否（403） |
| PayPal 逆引き検証異常 / 非 SUCCESS | 拒否（403） |
| 任意の `CALLBACK_TRUSTED_IPS` 設定後、送信元 IP がホワイトリスト外 | 拒否（403） |
| コールバック provider が注文の支払い方法と不一致 / 支払い方法が存在しない | 拒否（403） |

コールバック入金（状態更新 + 残高 + 取引履歴）は同一データベーストランザクション内で完了し、いずれかのステップが失敗すると全体がロールバックされ、半入金を防止します。

---

## 7. 監査ログ

### 7.1 操作ログ

OperationLog 中間ウェアが POST / PUT / DELETE リクエストに対して操作ログを自動記録します。GET リクエストは記録されません。

**記録フィールド**：

| フィールド | 取得元 | 説明 |
|------|------|------|
| id | SnowflakeService::generate() | グローバル一意 ID |
| user_id | `$request->adminId` | 操作者 ID、未ログインは 0 |
| action | `$request->method()` | method と同義 |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | リクエストパス |
| ip | `$request->getRealIp()` | クライアントの実 IP |
| source | detectSource() | クライアントの送信元プラットフォーム |
| input | リクエストボディ（マスキング済み JSON） | 操作の送信データ |
| created_at | `date('Y-m-d H:i:s')` | 操作時刻 |

**機密フィールドフィルタリング**：リクエストボディを再帰的に走査し、以下のフィールドの値を `***` に置き換えます：

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**送信元検出**（`detectSource()`）：優先順位に従って：

1. まず `X-Client-Platform` カスタムヘッダーを読み取る（ネイティブクライアントが明示宣言）
2. User-Agent 文字列からの推測にフォールバック（`detectSource()` メソッドの検出順序）：

| プラットフォーム | UA キーワード |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | フォールバックのデフォルト値 |

**フォールトトレランス**：ログ書き込み異常は業務リクエストをブロックしません（`catch (\Throwable)` で静かに握りつぶす）。

### 7.2 セキュリティログ

**ファイル位置**：`runtime/logs/security.log`

**記録内容**：
- 攻撃遮断ログ：攻撃カテゴリ、IP、パス、フィールド、送信元、payload 断片（先頭200文字）
- IP 封禁通知：封禁された IP、発動回数

ログ権限は `FILE_APPEND | LOCK_EX` で、並行安全な書き込みを保証します。

---

## 8. データ保護

システムは3層のデータ保護戦略を採用し、データフローの3段階に対応します。

### 8.1 転送層 — EncryptionService

`EncryptionService` は `erikwang2013/encryption` パッケージを使用し、API リクエスト/レスポンス内の機密フィールドを暗号化・復号します。

**技術詳細**：
- アルゴリズム：`aes-256-cbc-hmac`（HMAC 署名による改ざん防止内蔵）
- キー：`ENCRYPTION_KEY` 環境変数、自動的に32バイトに整列
- 用途：クライアントと API の間で携帯番号、身分証番号などのフィールドを転送

**マスキングユーティリティメソッド**：
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com`（ユーザー名が2文字超）または `a**@example.com`

### 8.2 保存層 — Encryptable Cast

`AdminUser` モデルは `Erikwang2013\Encryptable\Encryptable` Eloquent cast を使用し、対応フィールド：

- `email` → Encryptable に cast、自動暗号化・復号
- `phone` → Encryptable に cast、自動暗号化・復号
- `id_card` → Encryptable に cast、自動暗号化・復号

データベース書き込み時に自動で暗号文に暗号化され、読み取り時に自動で平文に復号されます。データベースの保存列型は `VARCHAR(500)` で、暗号文は base64 形式で保存されます。

**キー体系**：転送層暗号化（`ENCRYPTION_KEY`）とは独立して `ENCRYPTABLE_KEY` を使用し、一方のキーが漏洩しても他方の層が無効になりません。

キーローテーション：`ENCRYPTION_PREVIOUS_KEYS` 環境変数が履歴キーのリスト（カンマ区切り）をサポートし、古いデータの読み取り時に履歴キーでの復号を試行し、書き戻し時に現在のキーで再暗号化します。

### 8.3 表示層 — ID 難読化とマスキング

**Hashids ID 難読化**：`HashidsService` は `erikwang2013/hashids` パッケージを使用します。

- 外部 API に返すデータベースの BIGINT ID を hash 文字列にエンコード（例：`xK3mN9qR2pL7wV8b`）
- クライアントがリクエスト時に hash 文字列を渡し、バックエンドが自動で元の ID にデコード
- ソルト値 `HASHIDS_SALT` を環境変数から注入、ソルトが異なるとエンコード・デコード結果が完全に異なる
- hash 最小長16桁、62桁の英数字文字セットを使用
- BaseController が `encodeId()`, `decodeId()`, `encodeIds()` の便利メソッドを提供

**エクスポート時のマスキング**：Excel/PDF エクスポート時（ExportController）、機密フィールドを一律マスキング：
- 携帯番号：`138****1234`
- メールアドレス：`a***@example.com`
- 身分証番号：完全に `********` で覆う

---

## 9. キー管理

すべてのキーは `.env` 環境変数から注入され、設定ファイルは `getenv()` で読み取り、フォールバックのデフォルト値を内蔵します（開発環境のみ安全）。

| 環境変数 | 用途 | パッケージ | 本番要件 |
|----------|------|-----|---------|
| JWT_SECRET_KEY | JWT 署名キー | erikwang2013/jwt-webman | 64+ 文字のランダム文字列；欠落またはデフォルト値なら起動拒否 |
| JWT_ALGORITHM | JWT 署名アルゴリズム | 同上 | HS256 を維持 |
| HASHIDS_SALT | ID エンコード用ソルト | erikwang2013/hashids | ランダム文字列 |
| SNOWFLAKE_DATACENTER_ID | データセンター ID (0-31) | erikwang2013/snowflake-php | 単一データセンターはデフォルト維持 |
| ENCRYPTION_KEY | API 転送層暗号化キー | erikwang2013/encryption | 32バイトのランダム文字列 |
| ENCRYPTABLE_KEY | DB 保存層暗号化キー | erikwang2013/encryptable | 32バイトのランダム文字列、転送キーとは別 |

**セキュリティ要件**：
- `.env` ファイルは `.gitignore` に追加済みで、リポジトリへのコミットは厳禁
- `.env.example` は公開テンプレートファイルで、実際のキーは含まない
- 本番環境**必ず**すべてのデフォルトキーをランダム文字列に変更
- `openssl rand -base64 32` でのキー生成を推奨

### キー保存の分離

| 層 | 設定キー | キー環境変数 |
|----|--------|-------------|
| 転送暗号化 | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| 保存暗号化 | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| ID 難読化 | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| JWT 署名 | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET_KEY` |

---

## 10. security.txt (RFC 9116)

システムは `/.well-known/security.txt` で RFC 9116 標準に準拠したセキュリティ連絡先情報エンドポイントを提供し、セキュリティ研究者が脆弱性発見時に報告経路を素早く見つけられるようにします。

**アクセス方法**：

```
GET /.well-known/security.txt
```

**レスポンス内容**：

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**フィールド説明**：

| フィールド | 説明 |
|------|------|
| Contact | セキュリティ脆弱性の報告連絡先 |
| Expires | ファイルの有効期限、定期的な更新が必要 |
| Preferred-Languages | 優先的なコミュニケーション言語 |
| Canonical | このファイルの正規 URL |
| Policy | セキュリティポリシー/脆弱性開示ポリシーのリンク |

このエンドポイントはレート制限、認証などの中間ウェアの制限を受けず、誰でも直接アクセスできます。

---

## 11. Nginx セキュリティ設定

プロジェクトは `docs/nginx-security.conf` を本番環境の Nginx リバースプロキシのセキュリティ強化リファレンス設定として提供します。

**含まれるセキュリティ対策**：

| 設定項目 | 作用 |
|--------|------|
| `server_tokens off` | Nginx バージョン番号を隠蔽 |
| `client_max_body_size 10m` | リクエストボディサイズを制限、SecurityFilter と連携 |
| `limit_req_zone` | Nginx レベルでのリクエスト頻度制限 |
| `limit_conn_zone` | 同時接続数の制限 |
| `add_header` セキュリティヘッダー | Nginx レベルで X-XSS-Protection などのセキュリティヘッダーを追加 |
| `if ($request_method)` | Nginx レベルで非標準 HTTP メソッドを拒否 |
| SSL/TLS 設定 | 現代的な TLS 1.2/1.3 設定、弱い暗号スイートを無効化 |
| バックエンドヘッダー隠蔽 | `proxy_hide_header` で webman バージョンなどの機密ヘッダーを除去 |

**使用方法**：`docs/nginx-security.conf` 内の設定を Nginx の server ブロックにマージし、実際のドメインと証明書パスに応じて調整します。

---

## 12. 脅威モデル

### 12.1 防御済みの脅威

| 脅威タイプ | 攻撃ベクトル | 防御階層 |
|----------|---------|---------|
| HTTP メソッド悪用 | TRACE/TRACK XST 攻撃、CONNECT トンネルプロキシ、WebDAV メソッド探索 | SecurityFilter 405 メソッドホワイトリスト (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| 標的型ブルートフォース | 特定ユーザーへのパスワード試行 | アカウントロック (5回失敗で15分ロック) + RateLimit (ログイン 10/分) + Captcha |
| ブルートフォース | 分散 IP でのユーザー名/パスワード試行 | RateLimit (ログイン 10/分) + Captcha |
| XSS クロスサイトスクリプティング | `<script>`, onerror, javascript: | SecurityFilter (5パターン) + X-XSS-Protection レスポンスヘッダー + CSP |
| SQL インジェクション | UNION SELECT, OR 1=1, コメントバイパス | SecurityFilter (6パターン) + Eloquent ORM パラメータ化クエリ |
| CSRF クロスサイトリクエストフォージェリ | 悪意サイトによる代理リクエスト | SecurityFilter Origin/Referer 検証 |
| パストラバーサル | `../../etc/passwd` | SecurityFilter パストラバーサルパターン + UploadController 拡張子ホワイトリスト |
| コマンドインジェクション | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4パターン) |
| セッションハイジャック | JWT Token の窃取 | JWT 短期有効 (2h) + ブラックリストログアウト + 機密操作のパスワード再確認 |
| ID 列挙 | 数字 ID の走査でデータ量を推測 | Hashids でランダム文字列に難読化 |
| データ漏洩 | DB 流出 / 中間者 / ログ漏洩 | 3層暗号化/マスキング + OperationLog 機密フィールドフィルタリング |
| DoS 攻撃 | 超大リクエストボディ / 高頻度リクエスト | リクエストボディ 10MB 制限 + RateLimit 60/分 + IP ブラックリスト |
| 権限昇格 | 低権限ユーザーによる管理APIへのアクセス | RBAC method.path 粒度の認可 |
| ファイルアップロード攻撃 | shell.php.png 二重拡張子 | SecurityFilter 悪意ファイル検出 |

### 12.2 既知の限界

| 限界 | 影響範囲 | 緩和策 |
|------|---------|---------|
| CSRF 保護はブラウザのみ有効 | 非ブラウザクライアント（curl, Postman, モバイル App）は Origin/Referer チェックをスキップ可能 | 非ブラウザクライアントは本来 CSRF 攻撃を受けない；Cookie の代わりに JWT 認証に依存 |
| Redis 利用不可時、レート制限は fail-closed（503）、ブラックリストチェックは fail-open | レート制限中は一部リクエストが拒否；ログアウト済み Token が短期使用可能 | Redis 可用性の監視アラート；JWT 短期有効期間がフォールバック |
| 独立した WAF エンジンなし | SecurityFilter は `@preg_match` 正規表現マッチングで、専用 WAF ルールエンジンではない | 本番環境では Nginx ModSecurity または Cloudflare WAF の前置を推奨 |
| JWT ステートレスで能動的無効化不可 | Token が期限切れになるまでサーバー側から能動的に失効できない（ブラックリスト以外） | ブラックリスト + 短期 2h TTL でリスクウィンドウを低減 |
| IP ブラックリストはメモリ保存のみ | Redis 再起動でブラックリストが消失 | 封禁時間は15分のみで影響は限定的 |
| 管理者エンドポイントに特別なレート制限なし | 管理APIは一般APIと共用の 60/分 デフォルト制限 | 管理者の操作頻度は元々低く、当面区別は不要 |
| `@preg_match` がエラーを抑制 | 不正な正規表現入力時に静かに無効化 | `preg_last_error()` で監視可能、現在は未実装 |
