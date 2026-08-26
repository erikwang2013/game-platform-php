# उपपरियोजना A: बैकएंड संवर्द्धन — डिज़ाइन विनिर्देश
<!-- lang-nav -->

Languages: [中文](2026-05-20-backend-enhancement-design.md) · [English](2026-05-20-backend-enhancement-design.en.md) · [한국어](2026-05-20-backend-enhancement-design.ko.md) · [Русский](2026-05-20-backend-enhancement-design.ru.md) · [Deutsch](2026-05-20-backend-enhancement-design.de.md) · [Français](2026-05-20-backend-enhancement-design.fr.md) · [Español](2026-05-20-backend-enhancement-design.es.md) · [Português](2026-05-20-backend-enhancement-design.pt.md) · **हिन्दी** · [العربية](2026-05-20-backend-enhancement-design.ar.md) · [বাংলা](2026-05-20-backend-enhancement-design.bn.md) · [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) · [日本語](2026-05-20-backend-enhancement-design.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## दायरा

यह बैकएंड संवर्द्धन है, कुल 15 कार्यात्मक बिंदु, 9 नई फ़ाइलें + 4 संशोधित फ़ाइलें शामिल हैं।

---

## नई/संशोधित फ़ाइल सूची

```
app/middleware/
├── OperationLog.php          # नया: ऑपरेशन लॉग स्वचालित रिकॉर्डिंग
├── Cors.php                  # नया: क्रॉस-ओरिजिन
└── RateLimit.php             # नया: Redis दर सीमा
app/admin/controller/
├── ConfigController.php      # नया: सिस्टम कॉन्फ़िग CRUD
├── LogController.php         # नया: ऑपरेशन लॉग क्वेरी
├── ProfileController.php     # नया: व्यक्तिगत केंद्र (लॉगआउट सहित)
├── UploadController.php      # नया: फ़ाइल अपलोड
├── ImportController.php      # नया: Excel उपयोगकर्ता आयात
└── HealthController.php      # नया: स्वास्थ्य जाँच
app/model/
├── AdminUser.php             # संशोधन: SoftDeletes + Searchable trait जोड़ें
└── OperationLog.php          # संशोधन: public $timestamps = false जोड़ें
app/middleware/
└── AdminAuth.php             # संशोधन: JWT ब्लैकलिस्ट सत्यापन
app/admin/controller/
├── DashboardController.php   # संशोधन: डेटाबेस वास्तविक समय सांख्यिकी में बदलें
└── UserController.php        # संशोधन: बैच क्रियाएँ जोड़ें
config/
└── route.php                 # संशोधन: नए रूट + मिडलवेयर जोड़ें
```

---

## 1. मिडलवेयर

### 1.1 CORS मिडलवेयर

**फ़ाइल**: `app/middleware/Cors.php`

- OPTIONS प्रीफ्लाइट अनुरोध सीधे 204 लौटाएं
- गैर-प्रीफ्लाइट अनुरोधों के प्रतिक्रिया हेडर में `Access-Control-Allow-Origin: *` जोड़ें
- अनुमत हेडर: `Authorization, Content-Type, API-Version`
- अधिकतम कैश: 86400 सेकंड

माउंट: वैश्विक मिडलवेयर (`config/middleware.php`)

### 1.2 दर सीमा मिडलवेयर

**फ़ाइल**: `app/middleware/RateLimit.php`

- भंडारण: Redis Sorted Set स्लाइडिंग विंडो
- डिफ़ॉल्ट: 60 बार/मिनट/IP/रूट
- संवेदनशील इंटरफ़ेस:
  - `/api/auth/login`: 10 बार/मिनट
  - `/api/auth/register`: 5 बार/मिनट
- सीमा पार होने पर `429 Too Many Requests` लौटाएं

माउंट: वैश्विक मिडलवेयर (`config/middleware.php`), Cors के बाद, ApiVersion से पहले

### 1.3 ऑपरेशन लॉग मिडलवेयर

**फ़ाइल**: `app/middleware/OperationLog.php`

- केवल POST/PUT/DELETE रिकॉर्ड करें
- रिकॉर्ड किए गए फ़ील्ड: user_id, action, method, path, ip, input(JSON)
- प्रतिक्रिया लौटने के बाद अतुल्यकालिक रूप से लिखा जाता है (ब्लॉकिंग नहीं)

माउंट: `/admin` रूट समूह, AdminPermission के बाद

### 1.4 वैश्विक मिडलवेयर निष्पादन श्रृंखला

```
सभी अनुरोध:
  Cors → RateLimit → ApiVersion → {Route मिडलवेयर} → Controller

/admin/* अनुरोध:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 लॉगआउट (JWT ब्लैकलिस्ट)

**फ़ाइल**: `app/middleware/AdminAuth.php` (संशोधन)

**सिद्धांत**: JWT स्वयं स्टेटलेस है; लॉगआउट पर टोकन को Redis ब्लैकलिस्ट में जोड़ा जाता है, AdminAuth सत्यापन के समय पहले ब्लैकलिस्ट की जाँच करता है।

**AdminAuth पुनर्गठन**:
- `process()` की शुरुआत में नया: Redis `jwt_blacklist` संग्रह से जाँचें कि वर्तमान टोकन ब्लैकलिस्ट में है या नहीं
- ब्लैकलिस्ट हिट पर 401 लौटाएं

**लॉगआउट रूट** (व्यक्तिगत केंद्र के अंतर्गत):

| विधि | रूट | विवरण |
|------|------|------|
| `POST` | `/admin/profile/logout` | वर्तमान Bearer टोकन को Redis ब्लैकलिस्ट में जोड़ें, TTL=टोकन की शेष वैधता |

**Logout तर्क**:
```php
// टोकन की शेष वैधता पार्स करें
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// ब्लैकलिस्ट में जोड़ें
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. नए कंट्रोलर और मौजूदा पुनर्गठन

### 2.1 सिस्टम कॉन्फ़िग CRUD (`ConfigController`)

`BaseController` से विरासत।

| विधि | रूट | विवरण |
|------|------|------|
| `index()` | GET `/admin/config` | पृष्ठांकित सूची, `group` द्वारा फ़िल्टर किया जा सकता है, `page`/`limit` पृष्ठांकन |
| `store()` | POST `/admin/config` | कॉन्फ़िग आइटम बनाएं, अनिवार्य: group, key, value |
| `update()` | PUT `/admin/config/{id}` | कॉन्फ़िग आइटम value/type/description अपडेट करें |
| `destroy()` | DELETE `/admin/config/{id}` | कॉन्फ़िग आइटम हटाएं, `confirmPassword()` आवश्यक |

### 2.2 ऑपरेशन लॉग क्वेरी (`LogController`)

`BaseController` से विरासत।

| विधि | रूट | विवरण |
|------|------|------|
| `index()` | GET `/admin/log` | पृष्ठांकित सूची, फ़िल्टर समर्थित: user_id, action, path, created_at(सीमा) |

कोई जोड़/बदलाव/हटाना प्रदान नहीं करता, लॉग मिडलवेयर द्वारा स्वचालित रूप से रिकॉर्ड किए जाते हैं।

### 2.3 व्यक्तिगत केंद्र (`ProfileController`)

`BaseController` से विरासत। वर्तमान लॉग-इन उपयोगकर्ता पर कार्य करता है (`$request->adminId`)।

| विधि | रूट | विवरण |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | real_name, phone, email अपडेट करें |
| `updatePassword()` | PUT `/admin/profile/password` | पासवर्ड बदलें, old_password, new_password, new_password_confirmation आवश्यक |

### 2.4 फ़ाइल अपलोड (`UploadController`)

`BaseController` से विरासत।

| विधि | रूट | विवरण |
|------|------|------|
| `upload()` | POST `/admin/upload` | फ़ाइल प्राप्त करें, image/jpeg/png/gif/pdf/xlsx/docx समर्थित |

- अधिकतम 10MB
- भंडारण पथ: `public/upload/{date}/{hash}.{ext}`
- लौटाता है: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 डैशबोर्ड वास्तविक डेटा

**फ़ाइल**: `app/admin/controller/DashboardController.php` (संशोधन)

वर्तमान हार्ड-कोडेड नकली डेटा को डेटाबेस वास्तविक समय सांख्यिकी में बदलें:

| मीट्रिक | स्रोत | विवरण |
|------|------|------|
| कुल उपयोगकर्ता | `AdminUser::count()` | सॉफ्ट डिलीट शामिल नहीं |
| आज के नए | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| कुल भूमिकाएँ | `AdminRole::count()` | |
| कुल अनुमतियाँ | `AdminPermission::count()` | |
| प्रवृत्ति डेटा | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | पिछले 7 दिनों के नए दिन-वार |
| वितरण डेटा | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | स्थिति के अनुसार वितरण |
| हाल के ऑपरेशन | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | हाल की 10 ऑपरेशन लॉग |

### 2.6 उपयोगकर्ता बैच संचालन

**फ़ाइल**: `app/admin/controller/UserController.php` (संशोधन, नई विधियाँ)

| विधि | रूट | विवरण |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | बैच हटाना, अनुरोध निकाय `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | बैच सक्षम/अक्षम, अनुरोध निकाय `{ ids: [hashid, ...], status: 1|0 }` |

- प्रत्येक id को पहले `decodeId()` से BIGINT में बदलें
- `batchDestroy()` को `confirmPassword()` सत्यापन से गुजरना आवश्यक है

### 2.7 डेटा आयात

**फ़ाइल**: `app/admin/controller/ImportController.php` (नया)

| विधि | रूट | विवरण |
|------|------|------|
| `users()` | POST `/admin/import/users` | Excel फ़ाइल अपलोड करें, बैच में उपयोगकर्ता बनाएं |

प्रक्रिया:
1. `.xlsx` फ़ाइल प्राप्त करें
2. PhpSpreadsheet पार्स करता है, अपेक्षित कॉलम: `username, password, real_name, phone, email, status`
3. पंक्ति-दर-पंक्ति सत्यापन + निर्माण (snowflake से ID, bcrypt पासवर्ड, encryption से phone/email एन्क्रिप्ट)
4. परिणाम लौटाएं: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 स्वास्थ्य जाँच

**फ़ाइल**: `app/admin/controller/HealthController.php` (नया)

`GET /health` (प्रमाणीकरण की आवश्यकता नहीं, ऑपरेशन लॉग में गिना नहीं जाता):

प्रत्येक घटक की कनेक्शन स्थिति लौटाता है:
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

- घटक जाँच विफल होने पर संबंधित फ़ील्ड मान त्रुटि विवरण स्ट्रिंग होता है
- रूट `/admin` उपसर्ग पर नहीं टिकता, वैश्विक रूप से अलग से पंजीकृत

---

## 3. मॉडल सुधार

### 3.1 OperationLog टाइमस्टैम्प

**फ़ाइल**: `app/model/OperationLog.php` (संशोधन)

तालिका `erik_operation_log` में केवल `created_at` कॉलम है (कोई `updated_at` नहीं)। Eloquent का डिफ़ॉल्ट `save()` `updated_at` लिखने का प्रयास करता है, जिससे SQL त्रुटि होती है।

सुधार: `public $timestamps = false;` + लिखते समय मैन्युअल रूप से `created_at` निर्दिष्ट करें।

### 3.2 AdminUser मॉडल पुनर्गठन

- `Searchable` trait जोड़ें
- `toSearchableArray()` लागू करें: username, real_name लौटाएं
- `UserController::index()` कीवर्ड का पता चलने पर MySQL LIKE के बजाय `AdminUser::search($kw)->get()` का उपयोग करता है

ES को पहले इंडेक्स बनाना आवश्यक है, Scout कमांड से:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. रूट परिवर्तन

`config/route.php` में नए रूट:

```php
// /admin रूट समूह के भीतर नया:
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

// स्वास्थ्य जाँच (वैश्विक रूट, /admin समूह में नहीं)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// मिडलवेयर:
/admin समूह मिडलवेयर में app\middleware\OperationLog::class जोड़ें
```

`config/middleware.php` वैश्विक मिडलवेयर पंजीकृत करें:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. त्रुटि कोड परिशिष्ट

| code | अर्थ | ट्रिगर परिदृश्य |
|------|------|---------|
| 429 | अनुरोध बहुत बार-बार | RateLimit ट्रिगर |

---

## 6. इस दायरे में शामिल नहीं

- अधिसूचना प्रणाली (मैसेज क्यू + फ्रंटएंड पुश बुनियादी ढाँचे की आवश्यकता है)
- Flutter फ्रंटएंड पेज (उपपरियोजना B)
- HarmonyOS टोकन रिफ्रेश (उपपरियोजना C)
