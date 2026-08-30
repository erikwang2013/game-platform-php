# 开放管理后台 (open-admin)

## प्रोजेक्ट मास्कट

<img src="../docs/mascot.svg" width="120" alt="Dicey"/>

**डाइसी (Dicey)** — प्लेटफ़ॉर्म मास्कट। पासा गेम और संभावना-आधारित गेमप्ले को दर्शाता है, सिक्का प्लेटफ़ॉर्म अर्थव्यवस्था और मल्टी-पेमेंट गेटवे को, और बैंगनी रंग एडमिन ब्रांडिंग को दर्शाता है। SVG फ़ाइल: `docs/mascot.svg`, दस्तावेज़ों, लोगो और सामान के लिए असीमित स्केलेबल।
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · **हिन्दी** · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

webman v2 + Flutter पर आधारित फुल-स्टैक एडमिन बैकएंड प्रणाली।

> [English version](README.en.md) | [架构设计图](docs/ARCHITECTURE.hi.md) | [设计文档](docs/DESIGN.hi.md) | [安全架构](docs/SECURITY.hi.md) | [API 参考](docs/API.hi.md)

## सुविधा सूची

| व्यावसायिक डोमेन | सुविधा | विवरण |
|--------|------|------|
| 🔐 प्रमाणीकरण | लॉगिन/पंजीकरण/टोकन रीफ़्रेश/लॉगआउट | क्लिक कैप्चा + JWT + ब्लैकलिस्ट |
| | खाता लॉक | 5 असफल प्रयास पर 15 मिनट लॉक |
| | समवर्ती सत्र सीमा | एक उपयोगकर्ता के अधिकतम 3 सक्रिय Token |
| 📊 डैशबोर्ड | रीयल-टाइम स्टैट्स/ट्रेंड चार्ट/वितरण/हालिया ऑपरेशन | Redis कैश 5 मिनट |
| 📈 डेटा विश्लेषण | 12 एंडपॉइंट: अवलोकन/रैंकिंग/DAU/घंटे/व्यवहार वितरण/रेवेन्यू/रूपांतरण/संभाव्यता/रिटेंशन/फ़नल/ARPU/आर्थिक मेट्रिक्स | MySQL रीयल-टाइम एग्रीगेशन, DB विफलता पर खाली डेटा |
| 📊 डेटा रिपोर्ट | सारांश/दैनिक/CSV निर्यात | Redis 5 मिनट कैश, अवधि ≤90 दिन |
| 👥 उपयोगकर्ता प्रबंधन | CRUD + बैच डिलीट/सक्षम-अक्षम | सॉफ्ट डिलीट + पासवर्ड पुनः पुष्टि |
| | Excel बैच आयात | पंक्ति-दर-पंक्ति सत्यापन + त्रुटि रिपोर्ट |
| 🔒 भूमिका अनुमतियाँ | भूमिका CRUD + अनुमति ट्री | RBAC method.path ग्रैन्युलैरिटी प्रमाणीकरण |
| ⚙ सिस्टम कॉन्फ़िगरेशन | कुंजी-मान CRUD | समूह प्रबंधन |
| 🖥 CDN प्रबंधन | 5 प्रदाता कॉन्फ़िग CRUD + सक्षम/अक्षम + कनेक्टिविटी टेस्ट | क्रेडेंशियल AES एन्क्रिप्टेड, service केवल DB से पढ़ता है |
| 📋 ऑपरेशन ऑडिट | लॉग क्वेरी + स्रोत पहचान | 8 प्लेटफ़ॉर्म स्वचालित पहचान |
| 📁 फ़ाइल प्रबंधन | अपलोड/Excel निर्यात/PDF निर्यात | संवेदनशील डेटा स्वचालित मास्किंग |
| 🛡 सुरक्षा सुरक्षा | 18 परत गहराई सुरक्षा | XSS/SQL इंजेक्शन/पाथ ट्रैवर्सल/कमांड इंजेक्शन/CSRF/रेट लिमिट/CSP... |
| 🏥 संचालन | हेल्थ चेक/metrics/API दस्तावेज़/security.txt | Prometheus + OpenAPI 3.0 |

## प्रौद्योगिकी स्टैक

| परत | तकनीक | विवरण |
|---|------|------|
| बैकएंड फ्रेमवर्क | webman v2 (workerman) | अति-उच्च प्रदर्शन PHP रेज़िडेंट-प्रोसेस फ्रेमवर्क |
| PHP संस्करण | 8.3+ | |
| डेटाबेस | MySQL 8.0+ | तालिका उपसर्ग `game_`, BIGINT गैर-ऑटो-इन्क्रीमेंट प्राथमिक कुंजी |
| सर्च इंजन | Elasticsearch | `webman-scout` के माध्यम से सिंक और क्वेरी |
| एडमिन फ्रंटएंड | Flutter 3.x | वेब संस्करण PC एडमिन शैली (`apps/flutter/`) |
| मोबाइल | HarmonyOS ArkTS | हार्मनीOS नेटिव क्लाइंट (`apps/harmonyos/`), फ़ोन/टैबलेट/2in1 समर्थन |

## मुख्य निर्भरताएँ

| पैकेज | उपयोग |
|---|------|
| `erikwang2013/snowflake-php` | Snowflake एल्गोरिदम से वैश्विक अद्वितीय BIGINT प्राथमिक कुंजी |
| `erikwang2013/hashids` | API परत ID एन्क्रिप्शन, वास्तविक डेटाबेस ID छिपाना |
| `erikwang2013/jwt-webman` | JWT प्रमाणीकरण टोकन जारी और सत्यापन |
| `erikwang2013/encryption` | इंटरफ़ेस ट्रांसमिशन परत संवेदनशील डेटा एन्क्रिप्शन |
| `erikwang2013/encryptable` | डेटाबेस स्टोरेज परत संवेदनशील फ़ील्ड स्वचालित एन्क्रिप्शन |
| `erikwang2013/webman-scout` | Elasticsearch डेटा सिंक और पूर्ण-पाठ खोज |
| `erikwang2013/season` | देश के झंडे डेटा |
| `erikwang2013/poster-php` | क्लिक कैप्चा जनरेशन और सत्यापन + पोस्टर जनरेशन |
| `phpoffice/phpspreadsheet` | Excel निर्यात |
| `barryvdh/laravel-dompdf` | PDF निर्यात (Dompdf आधारित) |

## प्रोजेक्ट संरचना

```
open-admin/
├── app/
│   ├── admin/controller/       # 管理端控制器
│   │   ├── DashboardController.php # 仪表盘（Redis缓存）
│   │   ├── UserController.php      # 用户 CRUD + 批量操作
│   │   ├── RoleController.php      # 角色 CRUD
│   │   ├── PermissionController.php# 权限 CRUD
│   │   ├── ConfigController.php    # 系统配置 CRUD
│   │   ├── LogController.php       # 操作日志查询
│   │   ├── ProfileController.php   # 个人中心 + 登出
│   │   ├── ExportController.php    # Excel/PDF 导出
│   │   ├── ImportController.php    # Excel 导入用户
│   │   ├── UploadController.php    # 文件上传
│   │   ├── HealthController.php    # 健康检查
│   │   ├── DocsController.php      # OpenAPI 文档
│   │   └── BaseController.php      # 基础控制器
│   ├── api/
│   │   └── v1/controller/          # API v1 控制器（版本由请求头 API-Version 控制）
│   │       ├── CaptchaController.php # 点击验证码
│   │       └── AuthController.php    # 登录/注册/刷新令牌
│   ├── common/                 # 公共工具类
│   │   ├── HashidsService.php  # ID 编解码
│   │   ├── SnowflakeService.php# Snowflake ID 生成
│   │   └── EncryptionService.php # 数据加解密 + 脱敏
│   ├── middleware/             # 中间件
│   │   ├── Cors.php            # 跨域
│   │   ├── SecurityFilter.php  # 攻击检测拦截（HTTP方法限制/XSS/SQL注入/路径遍历/命令注入/CSRF）
│   │   ├── RateLimit.php       # Redis 限流（滑动窗口 + 响应头）
│   │   ├── ApiVersion.php      # API 版本校验
│   │   ├── AdminAuth.php       # JWT 认证 + 黑名单
│   │   ├── AdminPermission.php # RBAC 权限校验
│   │   └── OperationLog.php    # 操作日志自动记录（含来源端检测）
│   └── model/                  # 数据模型
├── apps/
│   ├── flutter/                # Flutter Web 管理后台（PC 风格）
│   │   └── lib/app/
│   │       ├── pages/          # 5 个完整页面（仪表盘/用户/角色/配置/日志/个人中心）
│   │       ├── services/       # ApiService（JWT 拦截器）+ AuthService（Token 持久化）
│   │       └── layouts/        # 响应式管理后台布局（侧边栏+顶栏+内容区）
│   └── harmonyos/              # HarmonyOS 原生客户端（Token 无感刷新）
├── config/                     # 配置文件（含中文注释）
│   ├── route.php               # 路由 + API 版本策略
│   ├── middleware.php           # 全局中间件注册
│   └── ...                     # 各组件配置
├── install/        # SQL 迁移文件（含权限种子数据）
├── public/                     # 公共入口
├── runtime/                    # 运行时文件
└── vendor/                     # Composer 依赖
```

## पर्यावरण आवश्यकताएँ

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (केवल फ्रंटएंड विकास के लिए)
- Elasticsearch >= 7.x (वैकल्पिक, खोज सुविधा के लिए)

## त्वरित आरंभ

### 1. निर्भरताएँ स्थापित करें

```bash
composer install
```

### 2. पर्यावरण चर कॉन्फ़िगर करें

पर्यावरण चर कॉपी करके संशोधित करें (वैकल्पिक, कॉन्फ़िगर न करने पर `config/*.php` में डिफ़ॉल्ट मान उपयोग होते हैं):

```bash
cp .env.example .env
```

मुख्य कॉन्फ़िगरेशन आइटम:

| पर्यावरण चर | विवरण | डिफ़ॉल्ट मान |
|---------|------|--------|
| `JWT_SECRET` | JWT सिग्नेचर कुंजी | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids सॉल्ट | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API एन्क्रिप्शन कुंजी | 32 बाइट डिफ़ॉल्ट मान |
| `SNOWFLAKE_DATACENTER_ID` | डेटासेंटर ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | वर्कर नोड ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES पता | `http://localhost:9200` |

**प्रोडक्शन में सभी कुंजियाँ यादृच्छिक स्ट्रिंग में बदलना अनिवार्य है।**

### 3. डेटाबेस आरंभ करें

`install/` के तहत SQL फ़ाइलें क्रम से चलाएँ:

```bash
mysql -u root -p < install/install.sql
```

### 4. सेवा शुरू करें

```bash
php start.php start
```

डिफ़ॉल्ट रूप से `http://0.0.0.0:8787` पर सुनता है।

### 5. फ्रंटएंड शुरू करें (वैकल्पिक)

**Flutter एडमिन बैकएंड (वेब):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web 端（PC 管理后台风格）
```

**HarmonyOS क्लाइंट (फ़ोन):**

DevEco Studio से `apps/harmonyos/` निर्देशिका खोलें, वास्तविक डिवाइस या एमुलेटर से चलाएँ।

### 6. Docker Compose वन-क्लिक डिप्लॉयमेंट (प्रोडक्शन के लिए अनुशंसित)

प्रोजेक्ट पूर्ण Docker ऑर्केस्ट्रेशन प्रदान करता है, जिसमें 5 सेवाएँ: Nginx, PHP (webman app), MySQL, Redis, Elasticsearch।

```bash
# 1. 配置 Docker 环境变量
cp .env.docker .env

# 2. 启动所有服务
docker-compose up -d

# 3. 初始化数据库（进入 app 容器执行）
docker-compose exec app mysql -h mysql -u root -p < install/install.sql

# 4. 访问
# http://localhost:8787  (webman)
# http://localhost:8080  (Nginx 反向代理)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, `php:8.3-cli` पर आधारित
- `docker-compose.yml`: 5 सेवा ऑर्केस्ट्रेशन, नेटवर्क आइसोलेशन, डेटा वॉल्यूम पर्सिस्टेंस
- `.env.docker`: Docker पर्यावरण के लिए विशेष पर्यावरण चर

## डेटाबेस मानक

- **तालिका उपसर्ग**: `game_`
- **प्राथमिक कुंजी**: सभी तालिकाओं की प्राथमिक कुंजी `id BIGINT UNSIGNED NOT NULL` है, **AUTO_INCREMENT निषिद्ध**
- **ID जनरेशन**: प्राथमिक कुंजी ID एप्लिकेशन परत `SnowflakeService::generate()` से उत्पन्न, वितरित रूप से अद्वितीय
- **अनिवार्य फ़ील्ड**: प्रत्येक तालिका में `id`, `created_at`, `updated_at` होना चाहिए
- **सॉफ्ट डिलीट**: सॉफ्ट डिलीट वाली तालिकाओं में `deleted_at DATETIME DEFAULT NULL` जोड़ें
- **संवेदनशील फ़ील्ड**: फ़ोन नंबर, ईमेल, ID कार्ड नंबर आदि `encryptable` प्लगइन से स्वचालित एन्क्रिप्शन, DB फ़ील्ड में `VARCHAR(500)` साइफरटेक्स्ट संग्रहीत

## API मानक

### समान प्रतिक्रिया प्रारूप

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### व्यावसायिक त्रुटि कोड

| त्रुटि कोड | अर्थ | विवरण |
|-------|------|------|
| `0` | सफल | |
| `400` | अनुरोध पैरामीटर त्रुटि | |
| `401` | लॉगिन नहीं (Token अमान्य या समाप्त) | |
| `403` | कोई अनुमति नहीं / सुरक्षा अवरोध | RBAC प्रमाणीकरण विफल / SecurityFilter आक्रमण पहचान |
| `404` | संसाधन मौजूद नहीं | |
| `422` | पैरामीटर सत्यापन विफल | |
| `413` | अनुरोध निकाय बहुत बड़ा | SecurityFilter ट्रिगर, 10MB से अधिक |
| `405` | अनुरोध विधि अनुमत नहीं | SecurityFilter ट्रिगर, केवल GET/POST/PUT/DELETE/OPTIONS/HEAD |
| `415` | असमर्थित मीडिया प्रकार | SecurityFilter ट्रिगर, Content-Type JSON नहीं |
| `429` | अनुरोध बहुत बार-बार | RateLimit ट्रिगर / खाता लॉक (5 असफल लॉगिन पर 15 मिनट लॉक) |
| `500` | सर्वर आंतरिक त्रुटि | |

### ID हैंडलिंग

- **अनुरोध/प्रतिक्रिया में ID**: hashids से स्ट्रिंग में एन्क्रिप्ट, वास्तविक डेटाबेस ID उजागर नहीं
- **इंटरफ़ेस पथ**: `GET /admin/user/{hashid}` — पथ में `{id}` hashid स्ट्रिंग है
- **डेटाबेस स्टोरेज**: BIGINT मूल मान, snowflake द्वारा उत्पन्न

### API संस्करण

API संस्करण अनुरोध हेडर से नियंत्रित होता है, **URL में प्रकट नहीं होता**:

```http
API-Version: v1
```

- संस्करण न देने पर डिफ़ॉल्ट `v1` उपयोग होता है
- असमर्थित संस्करण `400 Bad Request` लौटाता है
- नया संस्करण जोड़ने के लिए केवल `app/api/{version}/controller/` निर्देशिका बनाएं और मिडलवेयर में नया संस्करण पंजीकृत करें

### दर सीमा

Redis स्लाइडिंग विंडो एल्गोरिदम पर आधारित, डिफ़ॉल्ट 60 बार/मिनट/IP/रूट। संवेदनशील इंटरफ़ेस अधिक सख्त:
- लॉगिन: 10 बार/मिनट
- पंजीकरण: 5 बार/मिनट

प्रतिक्रिया हेडर में `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` शामिल हैं। सीमा से अधिक पर 429 लौटता है और `Retry-After` संलग्न होता है।

### मिडलवेयर आर्किटेक्चर

वैश्विक मिडलवेयर सभी अनुरोधों पर क्रम से लागू होता है:

```
Cors（跨域预处理 + 响应头）
  → SecurityFilter（HTTP方法限制/请求体大小/Content-Type校验/XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截）
  → RateLimit（Redis 滑动窗口限流 + 账号锁定：5次登录失败锁定15分钟）
  → ApiVersion（API 版本校验，/api 路由组）
  → AdminAuth（JWT 认证 + 黑名单，/admin 路由组）
  → AdminPermission（RBAC 鉴权，/admin 路由组）
  → OperationLog（POST/PUT/DELETE 自动记录，含来源端检测，/admin 路由组）
```

`/health` और `/api/docs` सार्वजनिक एंडपॉइंट हैं, केवल `Cors → SecurityFilter → RateLimit` से गुजरते हैं।

सुरक्षा संवर्द्धन:
- **खाता लॉक**: लगातार 5 असफल लॉगिन पर खाता स्वचालित रूप से 15 मिनट लॉक, इस दौरान लॉगिन 429 लौटाता है
- **समवर्ती सत्र सीमा**: एक उपयोगकर्ता के अधिकतम 3 सक्रिय Token, अधिक होने पर सबसे पुराना Token स्वचालित रूप से ब्लैकलिस्ट
- **security.txt**: `GET /.well-known/security.txt` RFC 9116 मानक सुरक्षा संपर्क जानकारी प्रदान करता है
- **Nginx सुरक्षा कॉन्फ़िगरेशन**: `docs/nginx-security.conf` देखें, पूर्ण रिवर्स प्रॉक्सी सुरक्षा हार्डनिंग उदाहरण

### प्रमाणीकरण

लॉगिन और पंजीकरण से पहले **क्लिक कैप्चा** सत्यापन पास करना आवश्यक है:

1. क्लाइंट `POST /api/captcha/generate` से कैप्चा छवि (base64 PNG) और टेक्स्ट लक्ष्य सूची प्राप्त करता है
2. उपयोगकर्ता छवि में संबंधित टेक्स्ट स्थानों पर क्रम से क्लिक करता है, क्लिक निर्देशांक `[{x, y}, ...]` एकत्र होते हैं
3. लॉगिन के समय `captcha_key` और `clicks` साथ सबमिट करें, सर्वर पहले कैप्चा फिर क्रेडेंशियल सत्यापित करता है

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

एडमिन पक्ष के आगे के इंटरफ़ेस के लिए JWT प्रमाणीकरण आवश्यक है:

```http
Authorization: Bearer <token>
```

लॉगिन सफल होने पर access_token लौटता है, वैधता 2 घंटे; साथ ही refresh_token, वैधता 14 दिन।

लॉगआउट पर Token Redis ब्लैकलिस्ट में जाता है, वैधता अवधि में पुनः उपयोग नहीं हो सकता। POST /admin/profile/logout

### संवेदनशील ऑपरेशन की द्वितीय पुष्टि

उपयोगकर्ता, भूमिका, अनुमति हटाने जैसे संवेदनशील ऑपरेशनों में अनुरोध निकाय में वर्तमान लॉगिन उपयोगकर्ता का `password` देना आवश्यक है:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API सूची

> सभी `/api/*` इंटरफ़ेस में अनुरोध हेडर `API-Version: v1` होना चाहिए (न देने पर डिफ़ॉल्ट v1)।

### सार्वजनिक इंटरफ़ेस

| विधि | पथ | विवरण |
|-----|------|------|
| `GET` | `/health` | स्वास्थ्य जांच (DB/Redis/ES स्थिति) |
| `GET` | `/api/docs` | OpenAPI 3.0 विनिर्देश दस्तावेज़ |
| `POST` | `/api/captcha/generate` | क्लिक कैप्चा उत्पन्न करें |
| `POST` | `/api/captcha/verify` | क्लिक कैप्चा सत्यापित करें |
| `POST` | `/api/auth/login` | लॉगिन (कैप्चा आवश्यक) |
| `POST` | `/api/auth/register` | पंजीकरण (कैप्चा आवश्यक) |
| `POST` | `/api/auth/refresh` | टोकन रीफ़्रेश |
| `GET` | `/metrics` | Prometheus मॉनिटरिंग मेट्रिक्स |

### एडमिन इंटरफ़ेस (JWT + RBAC आवश्यक)

| विधि | पथ | विवरण |
|-----|------|------|
| `GET` | `/admin/dashboard` | डैशबोर्ड डेटा (Redis कैश 5 मिनट) |
| `GET` | `/admin/user` | उपयोगकर्ता सूची (पेजिनेशन + खोज) |
| `POST` | `/admin/user` | उपयोगकर्ता बनाएं |
| `GET` | `/admin/user/{id}` | उपयोगकर्ता विवरण |
| `PUT` | `/admin/user/{id}` | उपयोगकर्ता अपडेट |
| `DELETE` | `/admin/user/{id}` | उपयोगकर्ता हटाएं (सॉफ्ट डिलीट, पासवर्ड पुष्टि आवश्यक) |
| `POST` | `/admin/user/batch/destroy` | बैच उपयोगकर्ता हटाएं (पासवर्ड पुष्टि आवश्यक) |
| `POST` | `/admin/user/batch/status` | बैच सक्षम/अक्षम करें |
| `GET` | `/admin/role` | भूमिका सूची |
| `POST` | `/admin/role` | भूमिका बनाएं |
| `PUT` | `/admin/role/{id}` | भूमिका अपडेट |
| `DELETE` | `/admin/role/{id}` | भूमिका हटाएं (पासवर्ड पुष्टि आवश्यक) |
| `GET` | `/admin/permission` | अनुमति ट्री |
| `POST` | `/admin/permission` | अनुमति बनाएं |
| `PUT` | `/admin/permission/{id}` | अनुमति अपडेट |
| `DELETE` | `/admin/permission/{id}` | अनुमति हटाएं (कैस्केड बाल अनुमतियाँ, पासवर्ड पुष्टि आवश्यक) |
| `GET` | `/admin/config` | सिस्टम कॉन्फ़िगरेशन सूची |
| `POST` | `/admin/config` | कॉन्फ़िगरेशन आइटम बनाएं |
| `PUT` | `/admin/config/{id}` | कॉन्फ़िगरेशन आइटम अपडेट |
| `DELETE` | `/admin/config/{id}` | कॉन्फ़िगरेशन आइटम हटाएं (पासवर्ड पुष्टि आवश्यक) |
| `GET` | `/admin/log` | ऑपरेशन लॉग (पेजिनेशन + फ़िल्टर) |
| `PUT` | `/admin/profile` | व्यक्तिगत जानकारी अपडेट |
| `PUT` | `/admin/profile/password` | पासवर्ड बदलें |
| `POST` | `/admin/profile/logout` | लॉगआउट (JWT ब्लैकलिस्ट) |
| `POST` | `/admin/export/excel` | Excel निर्यात |
| `POST` | `/admin/export/pdf` | PDF निर्यात |
| `POST` | `/admin/import/users` | Excel उपयोगकर्ता आयात |
| `POST` | `/admin/upload` | फ़ाइल अपलोड (छवि/दस्तावेज़, अधिकतम 10MB) |

## फ्रंटएंड विवरण

### Flutter एडमिन बैकएंड (PC शैली)

- **लेआउट**: साइडबार (फोल्डेबल 64px/240px) + टॉपबार + कंटेंट क्षेत्र, रिस्पॉन्सिव तीन ब्रेकपॉइंट (फ़ोन/टैबलेट/डेस्कटॉप)
- **पेज**: लॉगिन, डैशबोर्ड, उपयोगकर्ता प्रबंधन, भूमिका अनुमतियाँ, सिस्टम कॉन्फ़िगरेशन, ऑपरेशन लॉग, प्रोफ़ाइल
- **स्टेट प्रबंधन**: GetX (`ApiService` सिंगलटन + `AuthService` Token पर्सिस्टेंस)
- **डैशबोर्ड**: स्टैट कार्ड, ट्रेंड लाइन चार्ट (fl_chart), पाई चार्ट, हालिया ऑपरेशन लॉग
- **निर्यात**: Excel/PDF निर्यात, PDF में न हटाने योग्य कॉपीराइट जानकारी
- **बैच ऑपरेशन**: मल्टी-सिलेक्ट बैच डिलीट, बैच सक्षम/अक्षम
- **थीम**: Material 3 लाइट/डार्क दोहरी थीम

### HarmonyOS मोबाइल

- **पेज**: लॉगिन, डैशबोर्ड, उपयोगकर्ता सूची/विवरण, प्रोफ़ाइल
- **प्रमाणीकरण**: JWT Bearer + 401 पर स्वचालित सहज Token रीफ़्रेश, रीफ़्रेश विफल पर स्वचालित लॉगिन पेज रीडायरेक्ट
- **स्टोरेज**: Token AppStorage से प्रबंधित

## विकास मानक

- वैश्विक फ़ंक्शन/क्लास संदर्भ में आगे `\` न जोड़ें, समान रूप से `use` आयात करें
- सभी PHP फ़ाइलों के शीर्ष पर कॉपीराइट घोषणा अनिवार्य
- सभी कॉन्फ़िगरेशन फ़ाइलों में चीनी टिप्पणियाँ अनिवार्य
- डेटाबेस प्राथमिक कुंजी एप्लिकेशन परत snowflake से उत्पन्न होनी चाहिए, ऑटो-इन्क्रीमेंट निषिद्ध
- API परत के सभी पैरामीटर और प्रतिक्रिया ID hashids से एन्क्रिप्ट/डिक्रिप्ट होनी चाहिए
- AdminPermission मिडलवेयर उपयोगकर्ता अनुमतियाँ Redis कैश (TTL=60s) करता है, N+1 क्वेरी बाधा समाप्त

## डिप्लॉयमेंट

### Docker Compose (अनुशंसित)

प्रोजेक्ट रूट में `docker-compose.yml` उपलब्ध है, 5 सेवाओं का ऑर्केस्ट्रेशन:

| सेवा | इमेज | पोर्ट |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | स्थानीय `Dockerfile` निर्माण | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

PHP इमेज `Dockerfile` से निर्मित, आधार इमेज `php:8.3-cli`, OPcache सक्षम।

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

GitHub Actions निरंतर एकीकरण पाइपलाइन: `.github/workflows/ci.yml`

- PHP सिंटैक्स जांच (`php -l`)
- PHPUnit यूनिट टेस्ट
- Flutter स्टैटिक विश्लेषण (`flutter analyze`)

### डेटाबेस बैकअप

`database/backup/` निर्देशिका:

- `backup.sh` — mysqldump + gzip बैकअप, 30 दिन पुराने बैकअप स्वचालित सफाई
- `restore.sh` — इंटरैक्टिव पुनर्स्थापना, उपलब्ध बैकअप सूचीबद्ध करता है

### Nginx सुरक्षा कॉन्फ़िगरेशन

प्रोडक्शन डिप्लॉयमेंट में रिवर्स प्रॉक्सी सुरक्षा हार्डनिंग के लिए `docs/nginx-security.conf` देखें।

## ओपन-सोर्स आसान नहीं, समर्थन का स्वागत है

| 微信 | 支付宝 |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

