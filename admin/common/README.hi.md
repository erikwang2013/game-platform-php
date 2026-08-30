# common/ — एडमिन साझा लाइब्रेरी
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · **हिन्दी** · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

एडमिन बैकएंड (admin/) का साझा कोड निर्देशिका। `common\service\*` को साझा पैकेज **erik/platform-common** (`packages/platform-common`) में स्थानांतरित कर दिया गया है; इस निर्देशिका में PHP क्लासें न रखें, क्योंकि वे पैकेज के ऑटोलोड को छायांकित करेंगी। विवरण: `packages/platform-common/README.md`।

## सुविधाएँ

| श्रेणी | स्थान | विवरण |
|------|------|------|
| मॉडल | `app\model\*` | डेटा मॉडल (उपयोगकर्ता/ऑर्डर/गेम आदि) |
| सेवाएँ | `common\service\*` | साझा व्यावसायिक सेवाएँ (erik/platform-common पैकेज में): DepositLogService (जमा ऑडिट + राजस्व/रूपांतरण), GameDashboardService (संचालन डैशबोर्ड), ProbabilityService (संभाव्यता विश्लेषण), GamePlayLogService (गेम गतिविधि लॉग लेखन) |
| मिडलवेयर | `app\middleware\*` | Cors / SecurityFilter / RateLimit / AdminAuth / AdminPermission / OperationLog |

## स्थापना

admin प्रोजेक्ट के भाग के रूप में, निर्भरताएँ पहले से ही `admin/composer.json` में घोषित हैं (path रिपॉज़िटरी `../packages/platform-common` सहित) और `composer install` द्वारा स्वचालित रूप से स्थापित होती हैं; अलग से स्थापना की आवश्यकता नहीं है:

```bash
cd admin && composer install
```

## उपयोग

- `app\...` नेमस्पेस एडमिन प्रोजेक्ट के अपने कोड से मेल खाता है, जैसे: `use app\model\User;`
- `common\...` नेमस्पेस साझा पैकेज erik/platform-common (PSR-4 → `src/`) से मेल खाता है, जैसे:

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```
