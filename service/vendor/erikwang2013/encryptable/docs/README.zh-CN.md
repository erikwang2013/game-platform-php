**语言：** [English / 英文](../README.md) | **简体中文（本页）**

---

# Encryptable（erikwang2013/encryptable）

在 **PHP 8.2+** 环境下，为敏感字段提供「可检索的匿名化 / 加密」能力：写入数据库前加密，经 Eloquent（或手动 API）读出时解密；同时可生成与 **MySQL / PostgreSQL** 兼容的 SQL 片段，便于在查询条件中对接已加密列。命名空间仍为 `Maize\Encryptable\...`（历史兼容）。

本仓库在思路与行为上参考并演进自开源项目 **[laravel-encryptable](https://github.com/maize-tech/laravel-encryptable)**（Maize Tech）。若需对照原版设计、Issue 与发布说明，请优先查阅该上游仓库。

---

## 项目说明

### 解决什么问题

处理个人身份信息（PII）、医疗记录、财务数据或密钥等敏感字段时，通常需要**持久层加密**（非简单哈希或脱敏），同时保留**按原始值检索**的能力（唯一性校验、存在性查询）。纯加密库需要自行对接持久层，ORM 专用方案锁定单一框架。本项目填补了这两层空白。

### 核心设计

- **双加密路径** — `Encryption::php()` 负责应用层加解密（OpenSSL，与 Eloquent Cast 同一路径）；`Encryption::db()` 生成在数据库引擎内解密的 SQL 片段。两条路径共享同一套密钥与算法配置。
- **默认确定性加密** — 默认算法 `aes-128-ecb` 确保相同明文产生相同密文，使 `UniqueEncrypted` / `ExistsEncrypted` 校验成为可能。当需要隐藏数据模式时，可切换为 CBC/GCM 类算法。
- **容器无关解析** — `Encryption::resolve()` 依次探测 Hyperf、Laravel 及用户注入的 PSR-11 容器；无容器时回退至 `ENCRYPTION_KEY` / `ENCRYPTION_CIPHER` 环境变量。已为 **Laravel 10–12**、**Webman**（Illuminate 生态）、**Hyperf 2–3**、**ThinkPHP 6–8** 提供框架桥接。
- **零停机密钥轮换** — 主密钥 + `previous_keys` 解密环使密钥替换无需大规模重加密；`rotateToCurrentKey()` 支持在线渐进密文迁移。

### 相较上游 `laravel-encryptable` 的扩展

| 方面 | 扩展内容 |
|------|----------|
| **多框架** | 除 Laravel/Lumen 外增加 Webman、Hyperf、ThinkPHP 桥接。 |
| **Composer 插件** | 安装/更新时自动检测项目框架栈并写入对应配置文件。 |
| **密钥轮换** | `previous_keys` 解密环 + `PreviousKeysParser` 解析器 + `rotateToCurrentKey()`（上游仅支持单一密钥）。 |
| **容器解析** | `Encryption::setResolver()` / `setContainer()` 回调，非 Laravel 栈无需改动核心代码即可接入。 |
| **类型覆盖** | 所有 public/protected 方法均声明返回类型，基线 PHP 8.2+。 |
| **配置布局** | 统一 `config/plugin/{vendor}/{package}/app.php` 布局（Webman / Laravel / ThinkPHP 共用）；Hyperf 采用 autoload 点号键映射。 |

### 适用场景

| 推荐使用 | 建议替代方案 |
|----------|--------------|
| 需要 Eloquent 属性加密，在 `$casts` 中声明即可。 | 需要全盘或文件系统级加密（如 LUKS、云盘加密）。 |
| 需要对加密列执行唯一性 / 存在性查询。 | 不需要查询能力，偏好更强的认证加密（如 libsodium `secretbox`）。 |
| 面向多框架交付，希望加密契约统一。 | 仅面向单一框架，优先使用其原生加密（如 Laravel 内置 `encrypted` cast）。 |
| 需要零停机密钥轮换。 | 安全模型要求逐行随机 IV/Nonce 和 HMAC 完整性认证。 |

---

## 功能概览

- **Eloquent 自定义 Cast**：在模型 `$casts` 中使用 `Encryptable::class`，自动加解密指定属性。
- **PHP 侧加解密**：`Encryption::php()->encrypt()` / `decrypt()`，适合命令行、队列、非模型场景。
- **数据库表达式**：`Encryption::db()->decrypt()` 返回可在 SQL 中拼接的解密片段（MySQL / Postgres 语法分支），便于 `whereRaw` 等与密文列对照查询。
- **校验规则**：`UniqueEncrypted`、`ExistsEncrypted` 及 `Rule::uniqueEncrypted()` / `Rule::existsEncrypted()` 宏（依赖 Laravel 的 `illuminate/validation`）。
- **多运行时桥接**：在 **Laravel 10–12**、基于 Illuminate 的 **Webman**、**Hyperf 2–3**、**ThinkPHP 6–8** 下通过各自方式注册容器与配置；无完整容器时可用 **`ENCRYPTION_KEY`**、**`ENCRYPTION_CIPHER`**、可选 **`ENCRYPTION_PREVIOUS_KEYS`** 兜底 `Encryption::php()`（各栈能力与接入方式见下文 **「支持的框架」** 表格）。
- **Composer 安装钩子**：本包为 **Composer 插件**（`composer-plugin`）。安装/更新时会读取 **`vendor/composer/installed.php`**、**`composer.lock`**、**`composer.json`**，并结合**目录结构**判断当前项目栈，再按各框架官方路径写入默认配置；未识别则跳过。**不会覆盖**已有配置文件。详见 **「安装 → Composer 插件」**。
- **密钥优雅轮换（仅 `Encryption::php()` 路径）**：主密钥 + `previous_keys` / `ENCRYPTION_PREVIOUS_KEYS` 解密环，可选 **`rotateToCurrentKey()`** 渐进重加密；完整行为说明、上线步骤与配置入口见 **「配置说明 → 密钥优雅轮换」**。

---

## 环境要求

| 项目 | 说明 |
|------|------|
| PHP | `^8.2`，需启用 `openssl` 扩展 |
| 数据库 | 文档与实现针对 **MySQL**、**PostgreSQL**（`Encryption::db()` 的方言检测依赖驱动名） |

Composer 包名：**[erikwang2013/encryptable](https://packagist.org/packages/erikwang2013/encryptable)**（`composer.json` 中 `name` 字段）。

---

## 支持的框架

下表说明各栈的**约定兼容范围**、接入方式，以及本包能力与 Laravel 专属能力（Eloquent Cast、基于 `illuminate/validation` 的规则）的对应关系。实际以你项目锁定的 PHP / 框架小版本为准。**配置文件**在允许 Composer 插件时会自动安装（见「安装」）；也可按下文表格手动 `cp` 或使用 `vendor:publish`。

| 框架 | 版本（约定） | 接入方式 | Eloquent `$casts`（`Encryptable`） | `Encryption::php()` | `Encryption::db()` | `UniqueEncrypted` / `ExistsEncrypted` 与宏 |
|------|----------------|----------|-----------------------------------|---------------------|---------------------|---------------------------------------------|
| **Laravel** | 10.x ~ 12.x（运行环境 PHP ≥ 8.2） | Composer 包发现注册 `EncryptableServiceProvider` + **`config/plugin/erikwang2013/encryptable/app.php`**（或旧版 `config/encryptable.php`） | ✓ | ✓ | ✓ | ✓ |
| **Webman** | 1.x / 2.x，且项目已引入 **Illuminate**（database / support / validation） | 注册 `EncryptableServiceProvider` + **`config/plugin/erikwang2013/encryptable/app.php`**（Composer 插件或手动） | ✓（使用 Eloquent 时） | ✓ | ✓ | ✓ |
| **Hyperf** | 2.x / 3.x | `extra.hyperf.config` 合并 `Bridge\Hyperf\ConfigProvider` + **`config/autoload/plugins/erikwang2013/encryptable.php`**（或旧版 `config/autoload/encryptable.php`） | —（非 Laravel 模型 Cast；可在实体/仓储中调用 `Encryption::php()`） | ✓ | ✓（需安装 `hyperf/db-connection`） | —（依赖 Illuminate 校验栈） |
| **ThinkPHP** | 6.x ~ 8.x | `ThinkphpEncryptable::register($app)` + **`config/plugin/erikwang2013/encryptable/app.php`**（或旧版 `config/encryptable.php`） | —（请用模型获取器/修改器或类型字段自行调用 `Encryption::php()`） | ✓ | ✓ | — |

**图例：** ✓ 表示该能力在本栈有官方桥接或可直接使用；**—** 表示本包未提供该栈的专用实现，需自行在业务层对接。

---

## 安装

```bash
composer require erikwang2013/encryptable
```

### Composer 插件（自动发布配置）

本包为 `"type": "composer-plugin"`，通过 `extra.class` 注册 `Maize\Encryptable\Composer\Plugin`。在 **`erikwang2013/encryptable` 被安装或更新**后，插件会：

1. 按顺序汇总 Composer 包名（小写）：**`vendor/composer/installed.php`**（及 **`installed.json`**）、**`composer.lock`**、根 **`composer.json`** 的 `require` / `require-dev`。这样能反映 **`vendor` 里真实已安装** 的包（含传递依赖），避免根 `composer.json` 未写 `workerman/webman` 时误判。
2. 若仍不足以判断，再结合**项目目录特征**（如 Webman：`support/bootstrap.php` 或 `start.php` / `windows.php` 且存在 `config/`；Laravel：`artisan` 与 `bootstrap/app.php` 等；Hyperf：`bin/hyperf.php` 或 `config/autoload/server.php`；ThinkPHP：根目录可执行文件 `think`）。
3. **仅当识别到下方表格中的框架时**才写入文件；目标文件已存在则**不覆盖**。
4. **仍无法识别**时只输出跳过说明，避免在纯 PHP 库里误建 `config/`。

| 识别到的依赖（示例） | 遵循的官方约定 | 若缺失则创建的文件 |
|----------------------|------------------|---------------------|
| `laravel/framework` 或 `laravel/lumen-framework` | [Laravel 配置](https://laravel.com/docs/configuration) — `config/` 下 PHP（本包与 Webman 共用同一物理插件路径） | `config/plugin/erikwang2013/encryptable/app.php`（由 `EncryptableServiceProvider` 合并为 `config('encryptable.*')`） |
| `workerman/webman` / `webman/*` | [Webman 插件](https://webman.workerman.net/doc/zh-cn/plugin/create.html) — `config/plugin/{vendor}/{name}/app.php` | `config/plugin/erikwang2013/encryptable/app.php`（`config('plugin.erikwang2013.encryptable.app.*')`） |
| `topthink/framework` 或 `topthink/think` | [ThinkPHP 8 配置文件](https://doc.thinkphp.cn/v8_0/config_file.html) — 应用 `config/`；本包采用插件式子路径 | `config/plugin/erikwang2013/encryptable/app.php`（在 `ThinkphpEncryptable::register` 中注入为 `encryptable.*`） |
| `hyperf/framework` 或 `hyperf/hyperf` | [Hyperf 配置](https://hyperf.wiki/zh-cn/config.html) — `config/autoload/` 下 PHP（相对路径映射为点号键） | `config/autoload/plugins/erikwang2013/encryptable.php`（`plugins.erikwang2013.encryptable.*`，见 `HyperfEncryptableConfig`） |

**Laravel / Lumen / ThinkPHP / Webman** 共用 **`config/plugin/erikwang2013/encryptable/app.php`**（模板 `config/stubs/plugin-app.php`）。**Hyperf** 在新装且不存在旧版 **`config/autoload/encryptable.php`** 时写入 **`config/autoload/plugins/erikwang2013/encryptable.php`**；已有旧路径的项目保持不变，`HyperfEncryptableConfig` **优先**读插件路径，再回退 **`encryptable.*`**。若同时识别多种栈（如单体仓库），则对每种缺失文件分别发布。

**Composer 2.2+** 默认可能拦截插件，需在业务项目的 `composer.json` 中允许本包（配置一次即可；若已拒绝过可删 `composer.lock` 后重试或手动加）：

```json
"config": {
    "allow-plugins": {
        "erikwang2013/encryptable": true
    }
}
```

也可在 `composer require` 时按 Composer 交互提示选择信任本插件。

### 配置文件（手动复制，可选）

若未启用插件或希望自行管理模板，可**将包内对应文件复制到框架约定路径**（再按需修改 `.env` 等）。

| 框架 | 复制源（vendor 内路径，包名以 `erikwang2013/encryptable` 为准） | 复制到（项目内目标路径） |
|------|------------------------------------------------------------------|--------------------------|
| **Laravel / Lumen / ThinkPHP / Webman** | `vendor/erikwang2013/encryptable/config/stubs/plugin-app.php` | `config/plugin/erikwang2013/encryptable/app.php` |
| **Laravel（可选旧版）** | `vendor/erikwang2013/encryptable/config/encryptable.php` | `config/encryptable.php`（无插件路径时仍会被合并） |
| **Hyperf（推荐）** | `vendor/erikwang2013/encryptable/config/stubs/hyperf-plugin-autoload.php` | `config/autoload/plugins/erikwang2013/encryptable.php` |
| **Hyperf（旧版）** | `vendor/erikwang2013/encryptable/config/stubs/hyperf-autoload-encryptable.php` | `config/autoload/encryptable.php`（顶层 `key` / `cipher` → `encryptable.*`） |

> **说明：** **`plugin-app.php`** 为共用 stub（顶层 `key`、`cipher`）。**Webman** 原生读取为 `plugin.erikwang2013.encryptable.app.*`。**Laravel / Lumen** 合并到 `encryptable.*`。**ThinkPHP** 在 `ThinkphpEncryptable::register` 中注入为 `encryptable.*`。**Hyperf** 中 autoload 文件名与相对路径决定键名：推荐路径对应 **`plugins.erikwang2013.encryptable.*`**；旧文件 `encryptable.php` 对应 **`encryptable.*`**。

**Shell 示例：**

```bash
# Laravel / Lumen / ThinkPHP / Webman（共用插件布局）
mkdir -p config/plugin/erikwang2013/encryptable
cp vendor/erikwang2013/encryptable/config/stubs/plugin-app.php config/plugin/erikwang2013/encryptable/app.php

# Hyperf（推荐）
mkdir -p config/autoload/plugins/erikwang2013
cp vendor/erikwang2013/encryptable/config/stubs/hyperf-plugin-autoload.php config/autoload/plugins/erikwang2013/encryptable.php
```

Laravel 也可使用 `vendor:publish`（会发布插件路径、可选旧版扁平文件及 Hyperf stub 路径）：

```bash
php artisan vendor:publish --provider="Maize\Encryptable\EncryptableServiceProvider" --tag="encryptable-config"
```

### Laravel（其余步骤）

安装后若已启用包自动发现，会注册 `Maize\Encryptable\EncryptableServiceProvider`。请确保存在 **`config/plugin/erikwang2013/encryptable/app.php`**（Composer 钩子或上表 / `vendor:publish`），或仅保留旧版 **`config/encryptable.php`**。

### Webman（使用 Illuminate 组件时）

按需安装 `illuminate/database`、`illuminate/support`、`illuminate/validation` 等。确保存在 **`config/plugin/erikwang2013/encryptable/app.php`**（Composer 安装钩子或 `vendor:publish` 会生成），然后在插件或启动流程中注册：

`Maize\Encryptable\EncryptableServiceProvider`

运行时读取 **`config('plugin.erikwang2013.encryptable.app.key')`** 与 **`.cipher`**，见 [Webman 插件配置说明](https://webman.workerman.net/doc/zh-cn/plugin/create.html)。

### Hyperf

1. 按上表复制或依赖安装钩子生成 **`config/autoload/plugins/erikwang2013/encryptable.php`**（或旧版 **`config/autoload/encryptable.php`**）。读取顺序为 **`plugins.erikwang2013.encryptable.*`**，再回退 **`encryptable.*`**。
2. 本包在 `composer.json` 的 `extra.hyperf.config` 中声明了 `Maize\Encryptable\Bridge\Hyperf\ConfigProvider`，由 Hyperf 合并依赖注入配置。
3. 若使用 `Encryption::db()`，请安装 **`hyperf/db-connection`**。

### ThinkPHP

1. 推荐 **`config/plugin/erikwang2013/encryptable/app.php`**（与 Webman/Laravel 相同 stub）。若仍使用旧版 **`config/encryptable.php`** 亦可。
2. 在应用启动阶段（例如服务注册或引导类中）调用：

```php
\Maize\Encryptable\Bridge\ThinkPHP\ThinkphpEncryptable::register($app);
```

---

## 配置说明

复制或发布得到 **`config/plugin/erikwang2013/encryptable/app.php`**、旧版 **`config/encryptable.php`**，或 Hyperf 下 **`config/autoload/plugins/erikwang2013/encryptable.php`** / **`config/autoload/encryptable.php`** 后，核心项为：

| 键 | 说明 |
|----|------|
| `key` | 当前主密钥，对应 `ENCRYPTION_KEY`；**新密文**始终用该密钥加密。 |
| `cipher` | 算法标识，默认 `aes-128-ecb`，对应 `ENCRYPTION_CIPHER`；需与数据库侧及既有数据约定一致。**密钥环内所有密钥须使用同一 `cipher`。** |
| `previous_keys` | 已下线但仍用于**解密**尝试的密钥列表，在 `key` 解密失败后再依次尝试。来自 `ENCRYPTION_PREVIOUS_KEYS`（逗号分隔或 JSON 数组）或配置项 `previous_keys`。 |

无 Laravel / 未绑定容器时，`Encryption::php()` 可回退读取 **`ENCRYPTION_KEY`**、**`ENCRYPTION_CIPHER`**、**`ENCRYPTION_PREVIOUS_KEYS`**（见 `EnvEncryptableConfig`）。

### 密钥优雅轮换（应用层、零停机思路）

在**不强制全库立刻重加密**的前提下更换主密钥：**新写入**始终用当前 `ENCRYPTION_KEY`（主密钥）；**历史密文**只要其加密时所用的密钥仍在「密钥环」（主密钥或 `previous_keys`）内，即可继续解密。

#### 行为说明（本包实现摘要）

| 方面 | 行为 |
|------|------|
| **配置契约** | `EncryptableConfigContract::getPreviousKeys(): array` 表示仅用于**解密兜底**的已下线密钥列表，在主密钥 `getKey()` 无法解出合法明文后再**按顺序**尝试。Laravel（`encryptable.previous_keys`）、Webman 插件 `app.php`、Hyperf（`plugins.*` / `encryptable.*`）、ThinkPHP（`encryptable.previous_keys`）、无容器时的 `EnvEncryptableConfig`（`ENCRYPTION_PREVIOUS_KEYS`）均已对接。 |
| **解析** | `Maize\Encryptable\Support\PreviousKeysParser` 将环境变量或配置统一为 `list<string>`：支持逗号分隔、`["k1","k2"]` 形式的 JSON 字符串、或 PHP 数组。 |
| **密钥环** | 基类 `Encrypter::getDecryptionKeyRing()` 生成 `[主密钥, …previous_keys]`，去空、去与主密钥重复项。**环内所有密钥须与主密钥使用同一 `cipher`。** |
| **加密** | `PHPEncrypter::encrypt()` **仅使用主密钥**（`getKey()`），不会用 `previous_keys` 加密。 |
| **解密与 `isEncrypted()`** | 对每个候选密钥尝试 OpenSSL 解密；仅当解密结果以包内约定的脏前缀（`crypt:`）开头时才视为成功，避免把错误密钥产生的乱码当成明文。Eloquent `Encryptable` 等经 `Encryption::php()` 的路径与此一致。 |
| **重加密 API** | `PHPEncrypter::rotateToCurrentKey(?string $payload, bool $serialize = true)`：先用密钥环解密，再用**当前主密钥**加密。`Encryption::php()->rotateToCurrentKey(...)` 为其代理。非密文原样返回，`null` 仍为 `null`。 |
| **不适用场景** | **`Encryption::db()` / `DBEncrypter`**：生成 SQL 时**只嵌入主密钥**，**不读取** `previous_keys`。数据库函数侧密文轮换需在应用层或迁移脚本中解密再写回，不能依赖本节的密钥环。 |

#### 建议上线步骤（运维）

1. 将**新**密钥写入 `ENCRYPTION_KEY`；将**旧主密钥**加入 `ENCRYPTION_PREVIOUS_KEYS`（或配置的 `previous_keys`）。若有多枚旧钥，建议**最近退役的排在前面**。
2. 发布部署后，读路径自动按「主 → 旧」尝试，业务无需停机切换密文。
3. （可选）用队列/命令对字段逐条调用 **`Encryption::php()->rotateToCurrentKey($密文)`**（`$serialize` 须与写入时一致），使密文逐步改为新主密钥加密；之后可缩短 `previous_keys` 列表。
4. 从 `previous_keys` 中删除某一旧密钥前，须确认**已无任何数据**仍依赖该密钥加密，否则对应行将无法解密。

#### 配置入口一览

| 来源 | 键名 |
|------|------|
| 环境变量 | `ENCRYPTION_KEY`、`ENCRYPTION_CIPHER`、`ENCRYPTION_PREVIOUS_KEYS` |
| Laravel / 合并后的 `encryptable` | `key`、`cipher`、`previous_keys`（见包内 `config/encryptable.php` 或已发布的插件 `app.php`） |
| Webman 插件 `app.php` | 同上；框架侧读取路径为 `plugin.*.app.*` |
| Hyperf autoload 模板 | `key`、`cipher`、`previous_keys`（推荐 `plugins.erikwang2013.encryptable.*` 或旧版 `encryptable.*`） |

#### 多个旧密钥怎么配

解密顺序固定为：**先用当前主密钥 `ENCRYPTION_KEY`**，不成功再按 **`previous_keys` 数组顺序**依次尝试。有多枚旧钥时，建议把**最近刚下线的主密钥**排在最前，更早的排在后面。

**方式一：环境变量 `ENCRYPTION_PREVIOUS_KEYS`**

- **英文逗号分隔**（每项首尾空格会被去掉）：

```env
ENCRYPTION_PREVIOUS_KEYS=oldKeyOne16bytes!!,olderKeyTwo16byte,ancientKeyThree16b
```

- **JSON 数组**（整段写在 `.env` 一行；若某枚密钥里可能含逗号，优先用这种方式）：

```env
ENCRYPTION_PREVIOUS_KEYS=["oldKeyOne16bytes!!","olderKeyTwo16byte","ancientKeyThree16b"]
```

**方式二：Laravel / ThinkPHP 合并配置 `encryptable.previous_keys`**

在 `config/encryptable.php` 或插件 `app.php` 里写 PHP 数组（顺序即尝试顺序；须与当前 `cipher` 要求的长度一致，例如 `aes-128-ecb` 常见为 **16 字符**）：

```php
'previous_keys' => [
    'oldKeyOne16bytes!!',
    'olderKeyTwo16byte',
    'ancientKeyThree16b',
],
```

包内默认也可用 **`PreviousKeysParser::parse(env('ENCRYPTION_PREVIOUS_KEYS'))`**，与方式一组合：环境变量里放多枚，由解析器转成数组。

**方式三：Webman `app.php`、Hyperf autoload**

字段名同样是 **`previous_keys`**：要么与方式二相同的 **PHP 数组**，要么 `PreviousKeysParser::parse(env('ENCRYPTION_PREVIOUS_KEYS'))`，与英文文档一致。

---

## 使用说明

### 1. 模型 Cast（Laravel / 使用 Eloquent 的 Webman）

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Maize\Encryptable\Encryptable;

class User extends Model
{
    protected $fillable = ['name', 'email'];

    protected $casts = [
        'name' => Encryptable::class,
        'email' => Encryptable::class,
    ];
}
```

读写该模型属性时，会自动经过 PHP 侧加解密。

### 2. 手动加解密（PHP）

```php
use Maize\Encryptable\Encryption;

$plain = '需要保护的值';
$cipher = Encryption::php()->encrypt($plain);

$restored = Encryption::php()->decrypt($cipher);
```

### 3. 生成 SQL 中的解密表达式（DB）

```php
use Maize\Encryptable\Encryption;

// 返回可在 SELECT / WHERE 中使用的片段（具体语法随 MySQL / Postgres 变化）
$expr = Encryption::db()->decrypt($encryptedPayloadFromDb);
```

> `DBEncrypter::encrypt()` 会抛出「不支持」异常；本类主要面向「在 SQL 中解密比对」的场景。

### 4. 自定义验证规则

```php
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maize\Encryptable\Rules\ExistsEncrypted;
use Maize\Encryptable\Rules\UniqueEncrypted;

Validator::make($data, [
    'email' => [
        'required',
        'email',
        new ExistsEncrypted('users'),
        // 或 Rule::existsEncrypted('users')
    ],
]);

Validator::make($data, [
    'email' => [
        'required',
        'email',
        new UniqueEncrypted('users'),
        // 或 Rule::uniqueEncrypted('users')
    ],
]);
```

---

## 依赖与 Composer 说明

- **正式依赖**仅包含 `php`、`ext-openssl`、`psr/container`，避免在 **Hyperf** 等栈中强行拉入整套 Illuminate。
- **Laravel / Webman（Illuminate）**：请通过 `laravel/framework` 或按需引入的 `illuminate/*` 满足 `EncryptableServiceProvider`、Cast 与验证规则对 Illuminate 的要求（详见 `composer.json` 的 `suggest` 段）。

---

## 开发与测试

```bash
composer install
composer test        # Pest
composer analyse     # PHPStan（需项目内配置）
composer format      # Laravel Pint
```

---

## 参考与致谢

- **上游参考实现**：[maize-tech/laravel-encryptable](https://github.com/maize-tech/laravel-encryptable) — 原始 Laravel 包的设计、文档与社区讨论请以该仓库为准。
- 本分支在 **多框架桥接**、**Composer 元数据**、**配置与容器解析**等方面做了扩展与调整；若你正在从上游迁移，请对照两边的 `CHANGELOG` 与配置项。

---

## 许可证

MIT。详见仓库内 `LICENSE.md`。
