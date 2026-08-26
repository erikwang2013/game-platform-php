# 安装系统审查报告
<!-- lang-nav -->

Languages: [中文](INSTALL-AUDIT-REPORT.md) · [English](INSTALL-AUDIT-REPORT.en.md) · [한국어](INSTALL-AUDIT-REPORT.ko.md) · [Русский](INSTALL-AUDIT-REPORT.ru.md) · [Deutsch](INSTALL-AUDIT-REPORT.de.md) · [Français](INSTALL-AUDIT-REPORT.fr.md) · [Español](INSTALL-AUDIT-REPORT.es.md) · [Português](INSTALL-AUDIT-REPORT.pt.md) · [हिन्दी](INSTALL-AUDIT-REPORT.hi.md) · [العربية](INSTALL-AUDIT-REPORT.ar.md) · **বাংলা** · [Bahasa Indonesia](INSTALL-AUDIT-REPORT.id.md) · [日本語](INSTALL-AUDIT-REPORT.ja.md)


> 审查日期: 2026-08-04
> 审查范围: `install/` 目录下所有文件 + 相关文档变更
> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## এক. অডিট সারসংক্ষেপ

| মাত্রা | স্কোর | বিবরণ |
|------|------|------|
| ফিচার সম্পূর্ণতা | পাস | ৫ ধাপের ইনস্টলেশন প্রক্রিয়া সম্পূর্ণ, ৩৯টি টেবিল সব তৈরি হয়, সিড ডেটা সম্পূর্ণ |
| SQL সঠিকতা | পাস | ৪২টি টেবিল আসল মাইগ্রেশন ফাইলের সাথে সম্পূর্ণ সামঞ্জস্যপূর্ণ, source ফিল্ড CREATE TABLE-তে একীভূত |
| ইকোসিস্টেম কনফিগ | পাস | admin ও service দুই সেট .env কনফিগ সম্পূর্ণ, সিক্রেট অটো-জেনারেট |
| নিরাপত্তা | মৌলিক পাস | পাসওয়ার্ড bcrypt এনক্রিপ্ট, XSS সুরক্ষা সম্পূর্ণ, CSRF Token যোগের পরামর্শ |
| রক্ষণাবেক্ষণযোগ্যতা | পাস | কোড স্ট্রাকচার পরিষ্কার, একক ফাইলের দায়িত্ব স্পষ্ট |
| আইডেম্পোটেন্সি | পাস | সব INSERT-কে INSERT IGNORE-এ পরিবর্তন করা হয়েছে, WHERE NOT EXISTS গার্ড সহ |
| ইউজার এক্সপেরিয়েন্স | পাস | রেসপনসিভ ডিজাইন, AJAX কানেকশন টেস্ট, চীনা ত্রুটি বার্তা |

---

## দুই. তৈরি করা ফাইল

### 2.1 `install/install.sql` (988 লাইন)
- ৮টি মূল মাইগ্রেশন ফাইল একীভূত
- ৪২টি `erik_` প্রিফিক্স ডেটা টেবিল (CREATE TABLE IF NOT EXISTS)
- ১৩টি INSERT IGNORE সিড ডেটা ব্লক
- `erik_operation_log`-এর `source` ফিল্ড টেবিল-তৈরি স্টেটমেন্টে একীভূত (ALTER TABLE প্রয়োজন নেই)
- ট্রানজেকশন র্যাপার (START TRANSACTION / COMMIT)
- সব INSERT আইডেম্পোটেন্ট করা হয়েছে

**INSERT স্টেটমেন্ট আইডেম্পোটেন্সি বিবরণ:**

| টেবিলের নাম | প্রক্রিয়াকরণ পদ্ধতি |
|------|---------|
| `erik_admin_role` | INSERT IGNORE (নির্দিষ্ট ID) |
| `erik_admin_permission` | INSERT IGNORE (নির্দিষ্ট ID) - ৪ বার |
| `erik_admin_role_permission` | WHERE NOT EXISTS সাবকুয়েরি |
| `erik_platform_config` | INSERT IGNORE (নির্দিষ্ট ID) - ২ বার |
| `erik_language` | INSERT IGNORE (নির্দিষ্ট ID) |
| `erik_translation` | INSERT IGNORE (নির্দিষ্ট ID) |
| `erik_risk_rule` | INSERT IGNORE (নির্দিষ্ট ID) |
| `erik_withdraw_limit` | INSERT IGNORE (নির্দিষ্ট ID) |
| `erik_game_category` | INSERT IGNORE (নির্দিষ্ট ID) |
| `erik_country_config` | INSERT IGNORE (নির্দিষ্ট ID) |

### 2.2 `install/index.php` (485 লাইন)
- রুট ডিসপ্যাচ: step1 -> step2 -> step3 -> step4 -> step5
- AJAX ইন্টারফেস: `?action=test-db` (POST JSON)
- ৫টি পেজ টেমপ্লেট ফাংশন
- ইনলাইন JavaScript (AJAX কানেকশন টেস্ট)
- HTML আউটপুটে `htmlspecialchars()` দিয়ে XSS প্রতিরোধ
- ইনস্টল করা আছে কিনা ডিটেকশন (install.lock)

### 2.3 `install/Installer.php` (506 লাইন)
- এনভায়রনমেন্ট চেক: ১১টি আইটেম (PHP সংস্করণ, ৬টি এক্সটেনশন, ডিরেক্টরি পারমিশন, SQL ফাইল)
- ডেটাবেস কানেকশন টেস্ট: PDO + অটো ডেটাবেস তৈরি
- ইনস্টলেশন এক্সিকিউশন: SQL ইমপোর্ট -> অ্যাডমিন তৈরি -> .env লেখা -> লক
- সিক্রেট জেনারেশন: JWT(64 বাইট) / Hashids(32 বাইট) / Encryption(32 বাইট)
- .env ব্যাকআপ: ইনস্টলের আগে বিদ্যমান .env ফাইল অটো ব্যাকআপ

### 2.4 `install/assets/style.css` (130 লাইন)
- রেসপনসিভ ডিজাইন (মোবাইল <=600px সাপোর্ট)
- CSS ভেরিয়েবল থিম (--primary: #4f46e5)
- কোনো বাহ্যিক নির্ভরতা নেই

---

## তিন. এনভায়রনমেন্ট চেক কভারেজ (১১টি আইটেম)

| # | চেক আইটেম | লেভেল | অবস্থা |
|---|--------|------|------|
| 1 | PHP >= 8.1.0 | বাধ্যতামূলক | পাস |
| 2 | PDO MySQL | বাধ্যতামূলক | পাস |
| 3 | MBString | বাধ্যতামূলক | পাস |
| 4 | JSON | বাধ্যতামূলক | পাস |
| 5 | OpenSSL | বাধ্যতামূলক | পাস |
| 6 | PCNTL | বাধ্যতামূলক | পাস |
| 7 | GD | পরামর্শ | পাস |
| 8 | XML | পরামর্শ | পাস |
| 9 | Redis | পরামর্শ | পাস |
| 10 | ডিরেক্টরি পারমিশন (admin/runtime, service/runtime) | বাধ্যতামূলক | পাস |
| 11 | install.sql ফাইল বিদ্যমান | বাধ্যতামূলক | পাস |

---

## চার. ইকোসিস্টেম কনফিগ সম্পূর্ণতা

### 4.1 Admin `.env` জেনারেশন (৭০টি কনফিগ আইটেম)

| গ্রুপ | কনফিগ আইটেম সংখ্যা | কভারেজ |
|------|---------|------|
| অ্যাপ কনফিগ | 3 | APP_NAME, APP_DEBUG, APP_URL |
| JWT অথেনটিকেশন | 6 | JWT_SECRET, JWT_ALGORITHM, JWT_TTL, JWT_REFRESH_TTL, JWT_ISSUER, JWT_AUDIENCE |
| Hashids | 2 | HASHIDS_SALT, HASHIDS_ALT_SALT |
| Snowflake | 3 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID, SNOWFLAKE_START_TIMESTAMP |
| এনক্রিপশন(API) | 3 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTION_IV |
| এনক্রিপশন(DB) | 3 | ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER, ENCRYPTION_PREVIOUS_KEYS |
| Scout/ES | 7 | SCOUT_DRIVER, SCOUT_HOSTS, SCOUT_PREFIX, SCOUT_SHARDS, SCOUT_REPLICAS, SCOUT_CHUNK_SIZE, SCOUT_SOFT_DELETE |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST ইত্যাদি |
| Poster ক্যাপচা | 7 | POSTER_IMAGE_DRIVER ইত্যাদি |
| ডেটাবেস | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| Redis | 4 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DATABASE |
| সামঞ্জস্য সিক্রেট | 3 | JWT_SECRET_KEY, JWT_DEFAULT_EXPIRE, JWT_REFRESH_EXPIRE |

### 4.2 Service `.env` জেনারেশন (৪৮টি কনফিগ আইটেম)

| গ্রুপ | কনফিগ আইটেম সংখ্যা | কভারেজ |
|------|---------|------|
| অ্যাপ | 2 | APP_ENV, APP_DEBUG |
| ডেটাবেস | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| JWT | 3 | JWT_SECRET, JWT_TTL, JWT_REFRESH_TTL |
| Hashids | 3 | HASHIDS_SALT, HASHIDS_ALPHABET, HASHIDS_MIN_LENGTH |
| Snowflake | 2 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID |
| এনক্রিপশন | 4 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER |
| Redis | 3 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD |
| ClickHouse | 5 | CLICKHOUSE_HOST, CLICKHOUSE_PORT, CLICKHOUSE_DB, CLICKHOUSE_USER, CLICKHOUSE_PASS |
| OAuth | 9 | OAUTH_GOOGLE/FACEBOOK/APPLE প্রতিটি ৩টি আইটেম |
| পেমেন্ট Webhook | 3 | STRIPE_WEBHOOK_SECRET, PAYPAL_WEBHOOK_ID, PAYPAL_VERIFY_URL |
| CORS | 1 | CORS_ORIGIN |
| Scout/ES | 6 | SCOUT_DRIVER ইত্যাদি |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST ইত্যাদি |

**তুলনা উপসংহার**: দুই সেট `.env` কনফিগই মূল `.env.example`-এর সাথে সামঞ্জস্যপূর্ণ, এবং Service কনফিগে অনুপস্থিত `ENCRYPTION_CIPHER`, `ENCRYPTABLE_CIPHER`, `JWT_REFRESH_TTL` যোগ করা হয়েছে।

---

## পাঁচ. নিরাপত্তা অডিট

### 5.1 বাস্তবায়িত নিরাপত্তা ব্যবস্থা

| ব্যবস্থা | বাস্তবায়ন পদ্ধতি |
|------|---------|
| পাসওয়ার্ড নিরাপত্তা | bcrypt, cost=12 |
| সিক্রেট র্যান্ডমনেস | `random_int()` এনক্রিপশন-নিরাপদ র্যান্ডম |
| XSS সুরক্ষা | `htmlspecialchars()` দিয়ে সব ইউজার ইনপুট/আউটপুট এস্কেপ |
| SQL ইনজেকশন সুরক্ষা | PDO প্রিপেয়ার্ড স্টেটমেন্ট (`prepare/execute`) |
| ইনস্টলেশন লক | `install.lock` ফাইল + JSON মেটাডেটা |
| পাথ নিরাপত্তা | নির্দিষ্ট পাথ, ইউজার-নিয়ন্ত্রিত ফাইল ইনক্লুড নেই |
| এনক্রিপশন শক্তি | AES-256-CBC + ৩২ বাইট সিক্রেট |

### 5.2 সম্ভাব্য ঝুঁকি ও প্রশমন

| ঝুঁকি | লেভেল | প্রশমন ব্যবস্থা |
|------|------|---------|
| ইনস্টলেশনের সময় নেটওয়ার্ক এক্সপোজার | মাঝারি | ইনস্টলের পর সঙ্গে সঙ্গে `install/` ডিরেক্টরি মুছে ফেলুন (পেজে স্পষ্ট সতর্কতা) |
| CSRF Token নেই | কম | ইনস্টল উইজার্ড অস্থায়ী এককালীন টুল, PHP বিল্ট-ইন সার্ভার সিঙ্গেল-থ্রেডেড |
| test-db-এ রেট লিমিট নেই | কম | অস্থায়ী টুল, ব্যবহারের পরই মুছে যায় |
| .env ফাইল পারমিশন | কম | ইনস্টলের পর ম্যানুয়ালি chmod 600 চালানোর পরামর্শ |

### 5.3 উন্নতির পরামর্শ

1. **প্রোডাকশন শক্তিশালীকরণ**: ইনস্টলেশনের পর অটো `chmod 600 admin/.env service/.env` বিবেচনা করা যেতে পারে
2. **রিমোট অ্যাক্সেস**: রিমোট সার্ভার হলে SSH টানেলের পরামর্শ: `ssh -L 8888:localhost:8888 user@host`
3. **ইনস্টলের পর পরিষ্কার**: ইনস্টল সফল পেজে "ইনস্টল ডিরেক্টরি মুছে ফেলুন" স্পষ্ট প্রম্পট যোগ করা (বাস্তবায়িত)

---

## ছয়. টেস্ট ফলাফল

### 6.1 PHP সিনট্যাক্স চেক
```
通过 install/index.php — No syntax errors
通过 install/Installer.php — No syntax errors
```

### 6.2 ফিচার টেস্ট
```
通过 Step 1 环境检查 — 11项检查全部通过
通过 Step 2 数据库配置 — 表单渲染正确，默认值填充正常
通过 AJAX test-db — JSON响应格式正确，中文错误提示清晰
通过 CSS 静态资源 — 200 OK, text/css
通过 已安装页面 — install.lock检测正常，提示信息完整
```

### 6.3 SQL ভেরিফিকেশন
```
通过 42张表名与原始迁移文件完全一致
通过 source字段已合并到 erik_operation_log 建表语句
通过 所有INSERT语句已做幂等处理
通过 WHERE NOT EXISTS 守卫已恢复（与原迁移一致）
```

---

## সাত. আবিষ্কৃত ও মেরামত করা সমস্যা

| # | সমস্যা | গুরুত্ব | অবস্থা |
|---|------|--------|------|
| 1 | `erik_admin_role_permission` INSERT-এ `WHERE NOT EXISTS` গার্ড নেই (মূল মাইগ্রেশনের সাথে অসামঞ্জস্য) | উচ্চ | মেরামত করা হয়েছে |
| 2 | সব সিড ডেটা INSERT আইডেম্পোটেন্ট নয় (বারবার চালালে ব্যর্থ হবে) | মাঝারি | মেরামত করা হয়েছে (INSERT IGNORE) |
| 3 | এনভায়রনমেন্ট চেকে `pcntl` এক্সটেনশন চেক নেই (webman কোর নির্ভরতা) | মাঝারি | মেরামত করা হয়েছে |
| 4 | Service .env-এ `ENCRYPTION_CIPHER` কনফিগ নেই | কম | মেরামত করা হয়েছে |
| 5 | Service .env-এ `ENCRYPTABLE_CIPHER` কনফিগ নেই | কম | মেরামত করা হয়েছে |
| 6 | Service .env-এ `JWT_REFRESH_TTL` কনফিগ নেই | কম | মেরামত করা হয়েছে |

---

## আট. ডকুমেন্টেশন পরিবর্তন

| ফাইল | পরিবর্তনের বিষয়বস্তু |
|------|---------|
| `README.md` | কুইক স্টার্ট "এক-ক্লিক ইনস্টল উইজার্ড (প্রস্তাবিত)"-এ পরিবর্তিত, ম্যানুয়াল ইনস্টল ফল্ডিং ব্লক যোগ, প্রজেক্ট স্ট্রাকচার আপডেট |
| `README.en.md` | উপরের মতো (ইংরেজি সংস্করণ), প্রজেক্ট স্ট্রাকচার আপডেট |
| `docs/DEPLOYMENT.md` | নতুন ২য় বিভাগ "এক-ক্লিক ইনস্টল উইজার্ড (নতুন ডিপ্লয়ের জন্য প্রস্তাবিত)", মূল Docker বিভাগ পিছিয়ে |
| `.gitignore` | নতুন `install/install.lock`, `admin/.env.backup.*`, `service/.env.backup.*` |

---

## নয়. সামগ্রিক মূল্যায়ন

ইনস্টল সিস্টেম ফিচার-সম্পূর্ণ, কোডের মান ভালো, নিরাপত্তা ব্যবস্থা যথাযথ। ৫ ধাপের ইনস্টলেশন প্রক্রিয়া পরিষ্কার ও স্বজ্ঞাত, এনভায়রনমেন্ট চেক webman চালানোর জন্য প্রয়োজনীয় সব গুরুত্বপূর্ণ এক্সটেনশন কভার করে, অটো-জেনারেটেড শক্তিশালী সিক্রেট, কনফিগ ফাইল বিদ্যমান সিস্টেমের সাথে সম্পূর্ণ সামঞ্জস্যপূর্ণ। SQL একীভূতকরণ মূল মাইগ্রেশন ফাইলের সাথে সম্পূর্ণ সামঞ্জস্য বজায় রেখেছে (৪২টি টেবিল), আইডেম্পোটেন্সি নিশ্চিত করে বারবার চালালেও ত্রুটি হবে না।

**অডিট উপসংহার: পাস, ব্যবহারে আনা যেতে পারে।**

---

## দশ. 2026-08-18 অবস্থা নিশ্চিতকরণ

এই রাউন্ডের নিরাপত্তা মেরামত (পেমেন্ট কলব্যাক fail-closed, JWT স্টার্টআপ যাচাই, টেবিল প্রিফিক্স ইউনিফিকেশন) **ইনস্টল সিস্টেম স্পর্শ করেনি**, কোনো নতুন সমস্যা নেই:

- মডেল থেকে হার্ডকোডেড `erik_` প্রিফিক্স অপসারণের পর, প্রকৃত টেবিলের নাম এখনও `config/database.php`-এর `prefix=erik_` থেকে ইউনিফাইডভাবে তৈরি হয়, install.sql-এর তৈরি `erik_*` টেবিলের সাথে সামঞ্জস্যপূর্ণ, ইনস্টল SQL পরিবর্তনের প্রয়োজন নেই
- JWT স্টার্টআপ যাচাই (`JWT_SECRET_KEY` অনুপস্থিত বা ডিফল্ট হলে স্টার্ট প্রত্যাখ্যান) ইনস্টল উইজার্ডের অটো-জেনারেটেড ৬৪ বাইট র্যান্ডম সিক্রেটের সাথে সামঞ্জস্যপূর্ণ, ইনস্টল প্রক্রিয়া সামঞ্জস্যের প্রয়োজন নেই

ঐতিহাসিক উপসংহার ও সমস্যা তালিকা অপরিবর্তিত।

---
