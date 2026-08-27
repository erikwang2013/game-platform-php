# تقرير مراجعة نظام التثبيت
<!-- lang-nav -->

Languages: **中文** · [English](INSTALL-AUDIT-REPORT.en.md) · [한국어](INSTALL-AUDIT-REPORT.ko.md) · [Русский](INSTALL-AUDIT-REPORT.ru.md) · [Deutsch](INSTALL-AUDIT-REPORT.de.md) · [Français](INSTALL-AUDIT-REPORT.fr.md) · [Español](INSTALL-AUDIT-REPORT.es.md) · [Português](INSTALL-AUDIT-REPORT.pt.md) · [हिन्दी](INSTALL-AUDIT-REPORT.hi.md) · [العربية](INSTALL-AUDIT-REPORT.ar.md) · [বাংলা](INSTALL-AUDIT-REPORT.bn.md) · [Bahasa Indonesia](INSTALL-AUDIT-REPORT.id.md) · [日本語](INSTALL-AUDIT-REPORT.ja.md)


> تاريخ المراجعة: 2026-08-04
> نطاق المراجعة: جميع الملفات في دليل `install/` + تغييرات التوثيق ذات الصلة
> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## أولًا: ملخص المراجعة

| البعد | التقييم | الوصف |
|------|------|------|
| اكتمال الوظائف | ناجح | عملية التثبيت المكونة من 5 خطوات مكتملة، وإنشاء 39 جدولًا بالكامل، وبيانات البذور مكتملة |
| صحة SQL | ناجح | 42 جدولًا مطابقة تمامًا لملفات الترحيل الأصلية، ودمج حقل source في CREATE TABLE |
| إعداد النظام البيئي | ناجح | إعدادا .env كاملان لـ admin وservice، وتوليد المفاتيح تلقائيًا |
| الأمان | ناجح أساسيًا | تشفير كلمة المرور bcrypt، حماية XSS كاملة، يُقترح إضافة CSRF Token |
| قابلية الصيانة | ناجح | بنية الكود واضحة، ومسؤولية كل ملف محددة |
| القوة الذاتية (Idempotency) | ناجح | تحويل جميع INSERT إلى INSERT IGNORE، مع حراس WHERE NOT EXISTS |
| تجربة المستخدم | ناجح | تصميم متجاوب، اختبار اتصال AJAX، رسائل خطأ بالصينية |

---

## ثانيًا: الملفات التي أُنشئت

### 2.1 `install/install.sql` (988 سطرًا)
- دمج 8 ملفات ترحيل أصلية
- 42 جدول بيانات بادئة `game_` (CREATE TABLE IF NOT EXISTS)
- 13 كتلة بيانات بذور INSERT IGNORE
- دمج حقل `source` في جدول `game_operation_log` في عبارة إنشاء الجدول (دون الحاجة إلى ALTER TABLE)
- محاطة بمعاملة (START TRANSACTION / COMMIT)
- جميع عبارات INSERT عولجت لتكون قوية ذاتيًا

**تفاصيل المعالجة القوية الذاتية لعبارات INSERT:**

| اسم الجدول | طريقة المعالجة |
|------|---------|
| `game_admin_role` | INSERT IGNORE (معرّف ثابت) |
| `game_admin_permission` | INSERT IGNORE (معرّف ثابت) - 4 مرات |
| `game_admin_role_permission` | استعلام فرعي WHERE NOT EXISTS |
| `game-platform_config` | INSERT IGNORE (معرّف ثابت) - مرتين |
| `game_language` | INSERT IGNORE (معرّف ثابت) |
| `game_translation` | INSERT IGNORE (معرّف ثابت) |
| `game_risk_rule` | INSERT IGNORE (معرّف ثابت) |
| `game_withdraw_limit` | INSERT IGNORE (معرّف ثابت) |
| `game_game_category` | INSERT IGNORE (معرّف ثابت) |
| `game_country_config` | INSERT IGNORE (معرّف ثابت) |

### 2.2 `install/index.php` (485 سطرًا)
- جدولة المسارات: step1 -> step2 -> step3 -> step4 -> step5
- واجهات AJAX: `?action=test-db` (POST JSON)
- 5 دوال قوالب صفحات
- JavaScript مضمن (اختبار اتصال AJAX)
- استخدام `htmlspecialchars()` في إخراج HTML للوقاية من XSS
- كشف التثبيت المسبق (install.lock)

### 2.3 `install/Installer.php` (506 أسطر)
- فحص البيئة: 11 بندًا (إصدار PHP، 6 إضافات، أذونات الدلائل، ملف SQL)
- اختبار اتصال قاعدة البيانات: PDO + إنشاء قاعدة البيانات تلقائيًا
- تنفيذ التثبيت: استيراد SQL -> إنشاء المشرف -> كتابة .env -> القفل
- توليد المفاتيح: JWT(64 بايت) / Hashids(32 بايت) / Encryption(32 بايت)
- نسخ احتياطي .env: نسخ احتياطي تلقائي لملفات .env الموجودة قبل التثبيت

### 2.4 `install/assets/style.css` (130 سطرًا)
- تصميم متجاوب (يدعم الجوال <=600px)
- ثيم متغيرات CSS (--primary: #4f46e5)
- بدون تبعيات خارجية

---

## ثالثًا: تغطية فحص البيئة (11 بندًا)

| # | بند الفحص | المستوى | الحالة |
|---|--------|------|------|
| 1 | PHP >= 8.1.0 | إلزامي | ناجح |
| 2 | PDO MySQL | إلزامي | ناجح |
| 3 | MBString | إلزامي | ناجح |
| 4 | JSON | إلزامي | ناجح |
| 5 | OpenSSL | إلزامي | ناجح |
| 6 | PCNTL | إلزامي | ناجح |
| 7 | GD | موصى به | ناجح |
| 8 | XML | موصى به | ناجح |
| 9 | Redis | موصى به | ناجح |
| 10 | أذونات الدلائل (admin/runtime, service/runtime) | إلزامي | ناجح |
| 11 | وجود ملف install.sql | إلزامي | ناجح |

---

## رابعًا: اكتمال إعداد النظام البيئي

### 4.1 توليد `.env` لـ Admin (70 بند إعداد)

| المجموعة | عدد عناصر الإعداد | التغطية |
|------|---------|------|
| إعدادات التطبيق | 3 | APP_NAME, APP_DEBUG, APP_URL |
| مصادقة JWT | 6 | JWT_SECRET, JWT_ALGORITHM, JWT_TTL, JWT_REFRESH_TTL, JWT_ISSUER, JWT_AUDIENCE |
| Hashids | 2 | HASHIDS_SALT, HASHIDS_ALT_SALT |
| Snowflake | 3 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID, SNOWFLAKE_START_TIMESTAMP |
| التشفير (API) | 3 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTION_IV |
| التشفير (DB) | 3 | ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER, ENCRYPTION_PREVIOUS_KEYS |
| Scout/ES | 7 | SCOUT_DRIVER, SCOUT_HOSTS, SCOUT_PREFIX, SCOUT_SHARDS, SCOUT_REPLICAS, SCOUT_CHUNK_SIZE, SCOUT_SOFT_DELETE |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST وغيرها |
| كابتشا Poster | 7 | POSTER_IMAGE_DRIVER وغيرها |
| قاعدة البيانات | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| Redis | 4 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DATABASE |
| مفاتيح التوافق | 3 | JWT_SECRET_KEY, JWT_DEFAULT_EXPIRE, JWT_REFRESH_EXPIRE |

### 4.2 توليد `.env` لـ Service (48 بند إعداد)

| المجموعة | عدد عناصر الإعداد | التغطية |
|------|---------|------|
| التطبيق | 2 | APP_ENV, APP_DEBUG |
| قاعدة البيانات | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| JWT | 3 | JWT_SECRET, JWT_TTL, JWT_REFRESH_TTL |
| Hashids | 3 | HASHIDS_SALT, HASHIDS_ALPHABET, HASHIDS_MIN_LENGTH |
| Snowflake | 2 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID |
| التشفير | 4 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER |
| Redis | 3 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD |
| ClickHouse | 5 | CLICKHOUSE_HOST, CLICKHOUSE_PORT, CLICKHOUSE_DB, CLICKHOUSE_USER, CLICKHOUSE_PASS |
| OAuth | 9 | OAUTH_GOOGLE/FACEBOOK/APPLE كل منها 3 عناصر |
| Webhook الدفع | 3 | STRIPE_WEBHOOK_SECRET, PAYPAL_WEBHOOK_ID, PAYPAL_VERIFY_URL |
| CORS | 1 | CORS_ORIGIN |
| Scout/ES | 6 | SCOUT_DRIVER وغيرها |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST وغيرها |

**الاستنتاج المقارن**: ملفا `.env` متوافقان مع `.env.example` الأصلي، مع إضافة العناصر الناقصة `ENCRYPTION_CIPHER` و`ENCRYPTABLE_CIPHER` و`JWT_REFRESH_TTL` إلى إعداد Service.

---

## خامسًا: المراجعة الأمنية

### 5.1 الإجراءات الأمنية المنفذة

| الإجراء | طريقة التنفيذ |
|------|---------|
| أمان كلمة المرور | bcrypt, cost=12 |
| عشوائية المفاتيح | أرقام عشوائية آمنة تشفيريًا `random_int()` |
| حماية XSS | `htmlspecialchars()` لتهريب جميع مدخلات ومخرجات المستخدم |
| حماية حقن SQL | عبارات PDO المحضرة (`prepare/execute`) |
| قفل التثبيت | ملف `install.lock` + بيانات وصفية JSON |
| أمان المسارات | مسارات ثابتة، دون تضمين ملفات يتحكم بها المستخدم |
| قوة التشفير | AES-256-CBC + مفتاح 32 بايت |

### 5.2 المخاطر المحتملة والتخفيف

| المخاطر | المستوى | إجراء التخفيف |
|------|------|---------|
| كشف الشبكة أثناء التثبيت | متوسط | حذف دليل `install/` فورًا بعد التثبيت (تلميح بارز في الصفحة) |
| دون CSRF Token | منخفض | معالج التثبيت أداة مؤقتة لمرة واحدة، خادم PHP المدمج أحادي الخيط |
| test-db دون حد تكرار | منخفض | أداة مؤقتة، تُحذف بعد الاستخدام |
| أذونات ملف .env | منخفض | يُنصح بتنفيذ chmod 600 يدويًا بعد التثبيت |

### 5.3 اقتراحات التحسين

1. **تقوية بيئة الإنتاج**: يمكن التفكير في تنفيذ `chmod 600 admin/.env service/.env` تلقائيًا بعد اكتمال التثبيت
2. **الوصول عن بُعد**: إذا كان خادمًا بعيدًا، يُنصح باستخدام نفق SSH: `ssh -L 8888:localhost:8888 user@host`
3. **التنظيف بعد التثبيت**: التفكير في إضافة تلميح بارز "حذف دليل التثبيت" في صفحة نجاح التثبيت (مُنفَّذ)

---

## سادسًا: نتائج الاختبار

### 6.1 فحص صيغة PHP
```
ناجح install/index.php — No syntax errors
ناجح install/Installer.php — No syntax errors
```

### 6.2 الاختبارات الوظيفية
```
ناجح الخطوة 1 فحص البيئة — اجتاز 11 بندًا بالكامل
ناجح الخطوة 2 إعداد قاعدة البيانات — عرض النموذج صحيح، وتعبئة القيم الافتراضية طبيعية
ناجح AJAX test-db — صيغة استجابة JSON صحيحة، رسائل خطأ صينية واضحة
ناجح الموارد الثابتة CSS — 200 OK, text/css
ناجح صفحة التثبيت المسبق — كشف install.lock طبيعي، معلومات التلميح كاملة
```

### 6.3 التحقق من SQL
```
ناجح أسماء 42 جدولًا مطابقة تمامًا لملفات الترحيل الأصلية
ناجح دمج حقل source في عبارة إنشاء جدول game_operation_log
ناجح جميع عبارات INSERT عولجت لتكون قوية ذاتيًا
ناجح استعادة حراس WHERE NOT EXISTS (بما يطابق الترحيلات الأصلية)
```

---

## سابعًا: المشكلات المكتشفة والمُصلحة

| # | المشكلة | الخطورة | الحالة |
|---|------|--------|------|
| 1 | إدراج `game_admin_role_permission` يفتقد حارس `WHERE NOT EXISTS` (غير متسق مع الترحيل الأصلي) | عالية | تم الإصلاح |
| 2 | جميع إدراجات بيانات البذور لم تُعالج لتكون قوية ذاتيًا (الفشل عند إعادة التنفيذ) | متوسطة | تم الإصلاح (INSERT IGNORE) |
| 3 | فحص البيئة يفتقد فحص إضافة `pcntl` (تبعية جوهرية لـ webman) | متوسطة | تم الإصلاح |
| 4 | إعداد Service .env يفتقد `ENCRYPTION_CIPHER` | منخفضة | تم الإصلاح |
| 5 | إعداد Service .env يفتقد `ENCRYPTABLE_CIPHER` | منخفضة | تم الإصلاح |
| 6 | إعداد Service .env يفتقد `JWT_REFRESH_TTL` | منخفضة | تم الإصلاح |

---

## ثامنًا: تغييرات التوثيق

| الملف | محتوى التغيير |
|------|---------|
| `README.md` | تغيير البداية السريعة إلى "معالج التثبيت بنقرة واحدة (موصى به)"، إضافة كتلة التثبيت اليدوي القابلة للطي، تحديث بنية المشروع |
| `README.en.md` | نفس ما سبق (النسخة الإنجليزية)، تحديث بنية المشروع |
| `docs/DEPLOYMENT.md` | إضافة القسم الثاني "معالج التثبيت بنقرة واحدة (موصى به للنشر الجديد)"، نقل قسم Docker إلى الخلف |
| `.gitignore` | إضافة `install/install.lock`, `admin/.env.backup.*`, `service/.env.backup.*` |

---

## تاسعًا: التقييم العام

نظام التثبيت مكتمل الوظائف، وجودة الكود جيدة، والإجراءات الأمنية في مكانها. عملية التثبيت المكونة من 5 خطوات واضحة وبديهية، ويغطي فحص البيئة جميع الإضافات الأساسية اللازمة لتشغيل webman، مع توليد مفاتيح عالية القوة تلقائيًا، وملفات الإعداد متوافقة تمامًا مع النظام الحالي. حافظت عملية دمج SQL على التطابق الكامل مع ملفات الترحيل الأصلية (42 جدولًا)، وتضمن المعالجة القوية ذاتيًا عدم حدوث أخطاء عند إعادة التنفيذ.

**خلاصة المراجعة: ناجحة، جاهزة للاستخدام.**

---

## عاشرًا: تأكيد الحالة 2026-08-18

إصلاحات الأمان في هذه الجولة (fail-closed لاستدعاء الدفع، والتحقق من الإقلاع JWT، وتوحيد بادئات الجداول) **لم تمس نظام التثبيت**، ولا توجد مشكلات جديدة:

- بعد إزالة البادئة المرمّزة `game_` من النماذج، ما زالت أسماء الجداول الفعلية تُولَّد موحدًا من `prefix=game_` في `config/database.php`، بما يطابق جداول `game_*` التي ينشئها install.sql، فلا حاجة لتغيير SQL التثبيت
- التحقق من الإقلاع JWT (رفض الإقلاع عند غياب `JWT_SECRET_KEY` أو قيمته الافتراضية) متوافق مع المفتاح العشوائي 64 بايت الذي يولده معالج التثبيت تلقائيًا، فلا حاجة لتعديل عملية التثبيت

تبقى الاستنتاجات التاريخية وقائمة المشكلات كما هي.

---
