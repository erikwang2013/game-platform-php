# poster-php 架构设计与业务逻辑图

> 所有图表使用 Mermaid 语法，GitHub / GitLab 原生渲染。

---

## 一、系统架构总览

```mermaid
graph TB
    subgraph "API Layer 接口层"
        HELPERS["helpers.php<br/>captcha_create / captcha_verify / poster_create"]
        FACADES["Framework Facades<br/>Laravel / ThinkPHP / Webman / Hyperf"]
    end

    subgraph "Business Layer 业务层"
        CAPTCHA["Captcha Module 验证码模块<br/>CaptchaManager → CaptchaFactory → Click/Rotate/Slider"]
        POSTER["Poster Module 海报模块<br/>PosterBuilder → 14 Elements → PosterTemplate"]
    end

    subgraph "Core Layer 核心层"
        DRIVERS["Image Drivers 图像驱动<br/>ImageDriverInterface<br/>GdDriver / ImagickDriver"]
        STORAGE["Storage Drivers 存储驱动<br/>StorageInterface<br/>FileStorage / SessionStorage / RedisStorage"]
        QRCODE["QR Code Generator 二维码生成器<br/>QrcodeGenerator<br/>Pure PHP, Model 2, v1-40"]
        CONFIG["Config Loader 配置加载<br/>PosterConfig<br/>load / get / merge / reset"]
    end

    subgraph "Foundation 基础层"
        PHP["PHP 8.0+<br/>ext-gd / ext-mbstring"]
        OPTIONAL["Optional 可选<br/>ext-imagick / ext-redis"]
    end

    HELPERS --> CAPTCHA
    HELPERS --> POSTER
    FACADES --> CAPTCHA
    FACADES --> POSTER

    CAPTCHA --> DRIVERS
    CAPTCHA --> STORAGE
    CAPTCHA --> CONFIG

    POSTER --> DRIVERS
    POSTER --> QRCODE
    POSTER --> CONFIG

    DRIVERS --> PHP
    DRIVERS --> OPTIONAL
    STORAGE --> PHP
    STORAGE --> OPTIONAL
    QRCODE --> PHP
    CONFIG --> PHP
```

---

## 二、分层依赖关系

```mermaid
graph LR
    subgraph "Presentation 表现层"
        A1["Controller / Route"]
    end

    subgraph "API 接口"
        B1["Helpers 辅助函数"]
        B2["CaptchaManager"]
        B3["PosterBuilder"]
    end

    subgraph "Domain 领域"
        C1["ClickCaptcha<br/>RotateCaptcha<br/>SliderCaptcha"]
        C2["14 Element Types"]
        C3["CaptchaFactory"]
        C4["PosterTemplate"]
    end

    subgraph "Infrastructure 基础设施"
        D1["GdDriver"]
        D2["ImagickDriver"]
        D3["FileStorage"]
        D4["SessionStorage"]
        D5["RedisStorage"]
        D6["QrcodeGenerator"]
        D7["PosterConfig"]
    end

    A1 --> B1
    A1 --> B2
    A1 --> B3

    B1 --> B2
    B1 --> B3

    B2 --> C1
    B2 --> C3

    B3 --> C2
    B3 --> C4

    C1 --> D1
    C1 --> D3
    C2 --> D1
    C2 --> D6

    B2 --> D1
    B2 --> D3
    B2 --> D7
    B3 --> D1
    B3 --> D7
```

---

## 三、组件关系图

```mermaid
graph TB
    CM["CaptchaManager<br/>验证码管理器"] --> CF["CaptchaFactory<br/>验证码工厂"]
    CF --> CC["ClickCaptcha<br/>点击验证"]
    CF --> RC["RotateCaptcha<br/>旋转验证"]
    CF --> SC["SliderCaptcha<br/>滑块验证"]
    CF --> RANDOM["random → 随机选取"]

    CC --> AC["AbstractCaptcha<br/>抽象基类"]
    RC --> AC
    SC --> AC

    AC --> ID["ImageDriverInterface<br/>图像驱动接口"]
    AC --> SI["StorageInterface<br/>存储接口"]

    ID --> GD["GdDriver"]
    ID --> IM["ImagickDriver"]

    SI --> FS["FileStorage"]
    SI --> SS["SessionStorage"]
    SI --> RS["RedisStorage"]

    PB["PosterBuilder<br/>海报构建器"] --> ELEMENTS["14 Element Types<br/>14种元素"]
    PB --> PT["PosterTemplate<br/>海报模板"]

    ELEMENTS --> ID
    ELEMENTS --> QG["QrcodeGenerator<br/>二维码生成器"]

    PT --> ELEMENTS
```

---

## 四、验证码生成流程 (Captcha Generation)

```mermaid
sequenceDiagram
    participant Client as 前端/客户端
    participant Helper as captcha_create()
    participant Manager as CaptchaManager
    participant Factory as CaptchaFactory
    participant Captcha as ClickCaptcha<br/>(or Rotate/Slider)
    participant Driver as GdDriver
    participant Storage as FileStorage
    participant Config as PosterConfig

    Client->>Helper: captcha_create('click' | 'random')
    Helper->>Config: get('image.driver') / get('captcha.storage')
    Config-->>Helper: 'gd' / 'file'
    Helper->>Manager: new CaptchaManager(driver, storage)
    Helper->>Manager: create('click')
    Manager->>Factory: create('click', driver, storage)
    
    alt type === 'random'
        Factory->>Factory: array_rand(['click','rotate','slider'])
    end
    
    Factory-->>Manager: ClickCaptcha instance
    Manager-->>Helper: captcha
    
    Helper->>Captcha: setDifficulty('easy')
    Helper->>Captcha: generate()
    
    Captcha->>Captcha: generateKey() → bin2hex(random_bytes(16))
    Captcha->>Driver: clone() → createBackground()
    Driver-->>Captcha: background image
    
    Captcha->>Captcha: placeTargets() → random positions
    Captcha->>Driver: ellipse() + text() → draw targets
    Captcha->>Captcha: store(['targets'=>[...], 'type'=>'click', 'attempts'=>0])
    Captcha->>Storage: set(key, data, ttl)
    Storage-->>Captcha: true
    
    Captcha->>Driver: output('png') → base64
    Captcha-->>Helper: ['key','type'=>'click','image'=>'data:...','extra'=>['targets'=>[...]]]
    Helper-->>Client: result array
```

---

## 五、验证码验证流程 (Captcha Verification)

```mermaid
sequenceDiagram
    participant Client as 前端
    participant Helper as captcha_verify()
    participant Manager as CaptchaManager
    participant Storage as FileStorage
    participant Config as PosterConfig

    Client->>Helper: captcha_verify(key, type, data)
    Helper->>Manager: verify(key, ['type'=>type, 'data'=>data])
    
    Manager->>Storage: get(key)
    
    alt key not found / expired
        Storage-->>Manager: null
        Manager-->>Helper: false
    end
    
    Storage-->>Manager: stored data
    
    Manager->>Config: get('captcha.max_attempts', 3)
    Config-->>Manager: 3
    
    alt attempts >= max_attempts
        Manager->>Storage: del(key)
        Manager-->>Helper: false
    end
    
    Manager->>Manager: check(type, stored, userData)
    
    alt type === 'click'
        Manager->>Manager: checkClick(stored, data, tolerance)
        Note over Manager: 逐点检查距离 ≤ 18px
    else type === 'rotate'
        Manager->>Manager: checkRotate(stored, data, tolerance)
        Note over Manager: |用户角度 - (360-实际角度)| ≤ 5°
    else type === 'slider'
        Manager->>Manager: checkSlider(stored, data, tolerance)
        Note over Manager: |用户x - 实际x| ≤ 4px
    end
    
    alt check passed
        Manager->>Storage: del(key)
        Manager-->>Helper: true
    else check failed
        Manager->>Storage: incrementAttempts(key)
        Note over Manager,Storage: 保留 key 允许重试<br/>(最多 max_attempts 次)
        Manager-->>Helper: false
    end
    
    Helper-->>Client: true / false
```

---

## 六、海报生成流程 (Poster Generation)

```mermaid
sequenceDiagram
    participant Client as 调用方
    participant Builder as PosterBuilder
    participant Driver as GdDriver
    participant Element as TextElement<br/>(or any of 14 types)
    participant QR as QrcodeGenerator

    Client->>Builder: poster_create(750, 1334)
    Client->>Builder: background('#FFFFFF')
    Note over Builder: 延迟画布创建<br/>存储 pendingBgColor

    Client->>Builder: addText('标题', [...])
    Builder->>Builder: elements[] = new TextElement(opts)

    Client->>Builder: addImage('photo.jpg', [...])
    Builder->>Builder: elements[] = new ImageElement(opts)

    Client->>Builder: addQrcode('https://...', [...])
    Builder->>Builder: elements[] = new QrcodeElement(opts)

    Client->>Builder: addChart('bar', data, [...])
    Builder->>Builder: elements[] = new ChartElement(opts)

    Client->>Builder: save('poster.jpg', 90)

    Builder->>Builder: render()
    
    Note over Builder: 1. 解析模板(如有)<br/>2. 确定最终宽高<br/>3. 创建画布 + 背景

    Builder->>Driver: create(width, height)
    Builder->>Driver: rectangle(0,0,w,h) → background
    Driver-->>Builder: canvas ready

    loop for each element
        Builder->>Element: resolve(variables)
        Note over Element: 替换 {{placeholder}}
        Builder->>Element: render(canvas)

        alt TextElement
            Element->>Driver: text(content, x, y, opts)
        else ImageElement
            Element->>Driver: load(src) → resize
            Element->>Driver: image(overlay, x, y, opts)
        else QrcodeElement
            Element->>QR: setText(content) → render()
            QR-->>Element: GdImage
            Element->>Driver: setGdResource(qr)
            Element->>Driver: image(qrDriver, x, y)
        else ChartElement
            loop per data point
                Element->>Driver: rectangle / line / ellipse
            end
        else CalendarElement
            loop per day cell
                Element->>Driver: rectangle + text
            end
        else ArtisticTextElement
            alt stroke style
                loop stroke width
                    Element->>Driver: text() (offset positions)
                end
            else gradient style
                Element->>Element: create temp mask
                Note over Element: 逐像素 Y 轴渐变着色
                Element->>Driver: image(temp, x, y)
            end
        end
    end

    Builder->>Driver: save(path, 'jpg', 90)
    Builder-->>Client: true
```

---

## 七、模板系统流程 (Template System)

```mermaid
sequenceDiagram
    participant User as 调用方
    participant Builder as PosterBuilder
    participant Template as PosterTemplate
    participant Element as Elements

    User->>Template: PosterTemplate::fromConfig([...])
    Note over Template: 存储宽高 + 元素定义数组

    User->>Builder: useTemplate(template)
    Builder->>Builder: this.template = template

    User->>Builder: with(['title'=>'新品', 'url'=>'...'])
    Builder->>Builder: this.templateVars = variables

    User->>Builder: save('poster.jpg')

    Builder->>Builder: render()
    Builder->>Template: build(variables)
    
    loop for each element definition
        Template->>Template: match type
        alt 'text'
            Template->>Element: new TextElement(def)
        else 'image'
            Template->>Element: new ImageElement(def)
        else 'qrcode'
            Template->>Element: new QrcodeElement(def)
        else 'chart'
            Template->>Element: new ChartElement(def)
        else 'calendar'
            Template->>Element: new CalendarElement(def)
        else 'artistictext'
            Template->>Element: new ArtisticTextElement(def)
        else 'emoji'
            Template->>Element: new EmojiElement(def)
        else 'icon'
            Template->>Element: new IconElement(def)
        else 'emoticon'
            Template->>Element: new EmoticonElement(def)
        else ... (all 14 types)
            Template->>Element: new ...Element(def)
        end
        Template->>Element: resolve(variables)
        Note over Element: '{{title}}' → '新品'<br/>'{{url}}' → 'https://...'
    end

    Template-->>Builder: resolved elements[]
    
    loop for each element
        Builder->>Element: render(canvas)
    end
```

---

## 八、驱动层自动检测

```mermaid
graph TB
    START["DriverFactory::create()"] --> CHECK_DRIVER{"driver param?"}

    CHECK_DRIVER -->|"'auto'"| AUTO_DETECT
    CHECK_DRIVER -->|"'imagick'"| IM["new ImagickDriver()"]
    CHECK_DRIVER -->|"'gd'"| GD["new GdDriver()"]
    CHECK_DRIVER -->|"other"| GD

    AUTO_DETECT["auto detect 自动检测"] --> IM_CHECK{"ext-imagick loaded<br/>&& class_exists('Imagick')?"}
    IM_CHECK -->|"yes"| IM
    IM_CHECK -->|"no"| GD

    START2["StorageFactory::create()"] --> CHECK_STORAGE{"driver param?"}

    CHECK_STORAGE -->|"'auto'"| S_AUTO
    CHECK_STORAGE -->|"'redis'"| RS["new RedisStorage()"]
    CHECK_STORAGE -->|"'session'"| SS["new SessionStorage()"]
    CHECK_STORAGE -->|"'file'"| FS["new FileStorage()"]

    S_AUTO["auto detect 自动检测"] --> REDIS_CHECK{"ext-redis loaded<br/>&& class_exists('Redis')?"}
    REDIS_CHECK -->|"yes"| TRY_REDIS["try new RedisStorage()"]
    TRY_REDIS -->|"success"| RS
    TRY_REDIS -->|"catch Throwable"| SESSION_CHECK
    
    REDIS_CHECK -->|"no"| SESSION_CHECK{"PHP_SAPI !== 'cli'<br/>&& session active?"}
    SESSION_CHECK -->|"yes"| SS
    SESSION_CHECK -->|"no"| FS
```

---

## 九、验证码安全模型

```mermaid
stateDiagram-v2
    [*] --> Generated: captcha_create()
    
    Generated --> Stored: store answer + type + attempts=0
    
    state Stored {
        [*] --> Active
        Active --> Expired: after TTL seconds
    }
    
    Stored --> VerifyAttempt: user submits data
    
    VerifyAttempt --> CheckAttempts: get stored data
    CheckAttempts --> Deleted: attempts >= max_attempts
    CheckAttempts --> CheckType: attempts < max_attempts
    
    CheckType --> Failed: type mismatch
    CheckType --> CheckAnswer: type matches
    
    CheckAnswer --> Success: answer within tolerance
    CheckAnswer --> Retry: answer out of tolerance
    
    Retry --> IncrementAttempts: incrementAttempts(key)
    IncrementAttempts --> Stored: key preserved for retry
    
    Success --> Deleted: del(key)
    Failed --> Deleted: del(key)
    Expired --> Deleted: del(key)
    
    Deleted --> [*]: key invalidated
```

---

## 十、14 种海报元素分类

```mermaid
graph TB
    subgraph "基础元素 Basic"
        TEXT["TextElement<br/>文字"]
        IMAGE["ImageElement<br/>图片"]
        AVATAR["AvatarElement<br/>头像"]
        SHAPE["ShapeElement<br/>形状"]
        LINE["LineElement<br/>分割线"]
    end

    subgraph "复合元素 Composite"
        QRCODE["QrcodeElement<br/>二维码"]
        TABLE["TableElement<br/>表格"]
        WATERMARK["WatermarkElement<br/>水印"]
        CHART["ChartElement<br/>图表"]
        CALENDAR["CalendarElement<br/>日历"]
    end

    subgraph "装饰元素 Decorative"
        ARTISTIC["ArtisticTextElement<br/>艺术字体"]
        EMOJI["EmojiElement<br/>Emoji"]
        ICON["IconElement<br/>字体图标"]
        EMOTICON["EmoticonElement<br/>颜文字"]
    end

    TEXT --> IE["implements"]
    IMAGE --> IE
    AVATAR --> IE
    SHAPE --> IE
    LINE --> IE
    QRCODE --> IE
    TABLE --> IE
    WATERMARK --> IE
    CHART --> IE
    CALENDAR --> IE
    ARTISTIC --> IE
    EMOJI --> IE
    ICON --> IE
    EMOTICON --> IE

    IE["ElementInterface<br/>render() + toArray()"]
```

---

## 十一、目录结构映射

```mermaid
graph LR
    ROOT["poster-php/"] --> SRC["src/"]
    ROOT --> CONFIG_DIR["config/"]
    ROOT --> TESTS["tests/"]
    ROOT --> EXAMPLES["examples/"]
    ROOT --> DOCS["docs/"]

    SRC --> CAPTCHA_DIR["Captcha/"]
    SRC --> POSTER_DIR["Poster/"]
    SRC --> DRIVERS_DIR["Drivers/"]
    SRC --> QRCODE_DIR["Qrcode/"]
    SRC --> STORAGE_DIR["Storage/"]
    SRC --> ADAPTERS_DIR["Adapters/"]

    CAPTCHA_DIR --> C_FILES["7 files<br/>Interface + Abstract + 3 impl + Factory + Manager"]
    POSTER_DIR --> P_FILES["17 files<br/>Builder + Template + 14 elements + Interface + Abstract"]
    DRIVERS_DIR --> D_FILES["3 files<br/>Interface + Gd + Imagick"]
    QRCODE_DIR --> Q_FILES["1 file<br/>Pure PHP QR Code Generator"]
    STORAGE_DIR --> S_FILES["4 files<br/>Interface + File + Session + Redis"]
    ADAPTERS_DIR --> A_FILES["16 files<br/>Laravel / ThinkPHP / Webman / Hyperf"]

    TESTS --> T_DIRS["5 test suites<br/>Drivers / Storage / Captcha / Poster / QR"]
    DOCS --> DOC_FILES["specs/ + plans/ + architecture.md"]
```

---

> 以上图表可在支持 Mermaid 的 Markdown 渲染器中直接查看（GitHub / GitLab / VS Code / Typora）。
