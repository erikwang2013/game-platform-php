# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · **हिन्दी** · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

admin/ और service/ द्वारा साझा की जाने वाली `common\service\*` परत, जो Composer path रिपॉज़िटरी के माध्यम से स्थानीय स्रोत को संदर्भित करती है।

## सेवाएँ

| सेवा | विवरण |
|------|------|
| DepositLogService | रिचार्ज ऑडिट + राजस्व/रूपांतरण |
| GameDashboardService | संचालन डैशबोर्ड |
| ProbabilityService | प्रायिकता विश्लेषण |
| GamePlayLogService | गेम व्यवहार लॉग लेखन |
| CircuitBreaker / Retry | स्थिरता तंत्र (सर्किट ब्रेकर/पुनः प्रयास) |

निर्भरता: होस्ट द्वारा `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log` प्रदान किए जाते हैं।

## स्थापना

पैकेज नाम `erik/platform-common`। admin/ और service/ दोनों ने composer.json में path रिपॉज़िटरी (`../packages/platform-common`) पहले से कॉन्फ़िगर की है, इसलिए यह `composer install` के साथ स्वचालित रूप से स्थापित होता है; admin/ या service/ से अलग से अपडेट भी किया जा सकता है:

```bash
composer update erik/platform-common
```

यदि Packagist पर प्रकाशित हो, तो सीधे भी स्थापित किया जा सकता है:

```bash
composer require erik/platform-common
```

## उपयोग

नेमस्पेस `common\` (PSR-4 → `src/`):

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```

## वन-क्लिक स्थापना

प्लेटफ़ॉर्म के वन-क्लिक इंस्टॉलेशन विज़ार्ड (`install/`) द्वारा स्वचालित रूप से पूर्ण होती है: विज़ार्ड admin/ और service/ के लिए `composer install` चलाता है, path रिपॉज़िटरी निर्भरता स्वचालित रूप से स्थापित होती है; मैनुअल कॉन्फ़िगरेशन की आवश्यकता नहीं है।

## शेष दोहरी प्रतियाँ

`app/model/*`, `app/common/*Service`, अधिकांश `app/service/*` और EventBus अभी भी दोनों पक्षों में कॉपी किए जाते हैं।
